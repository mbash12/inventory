<?php

namespace Src\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;
use Src\Models\AccountingSyncSnapshot;
use Src\Models\Project;
use Src\Models\PoDeposit;

class ProjectSyncService
{
    public const KEY_GROUPED = 'po';

    private $accountingApiUrl;
    private $bearerToken;
    private $ppnTypeCompanyIdMap;

    /** Send complete payloads even when a snapshot exists (manual full resync). */
    private bool $forceFull = false;

    /** @var list<string> so_keys synced during the current PO run */
    private array $touchedKeys = [];

    public function __construct()
    {
        $this->accountingApiUrl = rtrim(config('app.accounting_api_url', env('ACCOUNTING_API_URL', 'http://localhost:8001/api')), '/');
        $this->bearerToken = env('EPROC_INTEGRATION_BEARER');

        // PPN Type to Company ID mapping from env
        $this->ppnTypeCompanyIdMap = [
            'ppn' => env('PPN_COMPANY_ID', 12),
            'non_ppn' => env('NON_PPN_COMPANY_ID', 1),
        ];
    }

    /**
     * Convert ppn_type to company_id
     */
    private function getCompanyIdFromPpnType($ppnType)
    {
        return $this->ppnTypeCompanyIdMap[$ppnType] ?? $this->ppnTypeCompanyIdMap['ppn'];
    }

    /**
     * Sync projects to Sales Orders in Accounting system
     * 
     * For NON-DEPOSIT (is_po_deposit = false): 
     * - Group all projects under the same po_deposit into a SINGLE Sales Order
     * 
     * For DEPOSIT (is_po_deposit = true):
     * - Each project becomes a SEPARATE Sales Order (split)
     * - Non-actual projects (is_real = false) are synced with order_type = 'deposit'
     * - Actual projects (is_real = true) are synced with order_type = 'aktual'
     * 
     * @param int|null $poDepositId Sync specific PO Deposit (null for all)
     * @param int|null $companyId Target company ID in accounting system
     * @return array Sync results
     */
    public function syncSinglePoDeposit(PoDeposit $poDeposit, ?int $companyId = null, bool $forceFull = false): array
    {
        $this->forceFull = $forceFull;

        // Get company_id from ppn_type if not provided
        if ($companyId === null) {
            $ppnType = $poDeposit->ppn_type ?? 'ppn';
            $companyId = $this->getCompanyIdFromPpnType($ppnType);
        }

        $results = [
            'success' => true,
            'message' => '',
            'synced' => [],
            'errors' => [],
            'blocked' => false,
        ];

        try {
            $syncResult = $this->syncPoDeposit($poDeposit, $companyId);
            
            if (!empty($syncResult)) {
                $results['synced'] = $syncResult;
                $results['message'] = 'Sync completed successfully';
            } else {
                $results['message'] = 'No projects to sync';
            }

        } catch (AccountingSyncBlocked $e) {
            $results['success'] = false;
            $results['blocked'] = true;
            $results['errors'][] = [
                'po_deposit_id' => $poDeposit->id,
                'job_number' => $poDeposit->job_number,
                'error' => $e->getMessage(),
                'reasons' => $e->reasons,
            ];
            $results['message'] = 'Sync blocked by Accounting: ' . $e->getMessage();
        } catch (\Exception $e) {
            $results['success'] = false;
            $results['errors'][] = [
                'po_deposit_id' => $poDeposit->id,
                'job_number' => $poDeposit->job_number,
                'error' => $e->getMessage()
            ];
            $results['message'] = 'Sync failed: ' . $e->getMessage();
        }

        return $results;
    }

    public function syncToSalesOrders(?int $poDepositId = null, ?int $companyId = null): array
    {
        $results = [
            'success' => true,
            'message' => '',
            'synced' => [],
            'skipped' => [],
            'errors' => []
        ];

        try {
            // Build query for PoDeposits - load all projects, filter later based on deposit type
            $query = PoDeposit::with(['projects_data', 'projects_data.products_data']);

            if ($poDepositId) {
                $query->where('id', $poDepositId);
            }

            $poDeposits = $query->get();

            foreach ($poDeposits as $poDeposit) {
                // Skip if no client_code
                if (empty($poDeposit->client_code)) {
                    $results['skipped'][] = [
                        'po_deposit_id' => $poDeposit->id,
                        'job_number' => $poDeposit->job_number,
                        'reason' => 'No client_code'
                    ];
                    continue;
                }

                // Get company_id from ppn_type if not provided
                $poDepositCompanyId = $companyId;
                if ($poDepositCompanyId === null) {
                    $ppnType = $poDeposit->ppn_type ?? 'ppn';
                    $poDepositCompanyId = $this->getCompanyIdFromPpnType($ppnType);
                }

                try {
                    $syncResult = $this->syncPoDeposit($poDeposit, $poDepositCompanyId);
                    $results['synced'][] = $syncResult;
                } catch (\Exception $e) {
                    Log::error('Error syncing PO Deposit to Sales Order', [
                        'po_deposit_id' => $poDeposit->id,
                        'error' => $e->getMessage()
                    ]);
                    $results['errors'][] = [
                        'po_deposit_id' => $poDeposit->id,
                        'job_number' => $poDeposit->job_number,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $successCount = count($results['synced']);
            $skippedCount = count($results['skipped']);
            $errorCount = count($results['errors']);
            $results['message'] = "Sync completed. Success: {$successCount}, Skipped: {$skippedCount}, Errors: {$errorCount}";

        } catch (\Exception $e) {
            Log::error('Project sync failed', ['error' => $e->getMessage()]);
            $results['success'] = false;
            $results['message'] = 'Sync failed: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Sync a single PO Deposit to Sales Order(s)
     */
    private function syncPoDeposit(PoDeposit $poDeposit, ?int $companyId): array
    {
        $this->touchedKeys = [];
        $result = $this->buildAndSyncPoDeposit($poDeposit, $companyId);
        $this->retireOrphanedOrders($poDeposit);

        return $result;
    }

    private function buildAndSyncPoDeposit(PoDeposit $poDeposit, ?int $companyId): array
    {
        // Check if this is a deposit or non-deposit
        if ($poDeposit->is_po_deposit) {
            // DEPOSIT: Sync ALL projects (both actual and non-actual), one-to-one SO
            $allProjects = $poDeposit->projects_data;
            
            Log::info('Checking deposit projects', [
                'po_deposit_id' => $poDeposit->id,
                'total_projects' => $poDeposit->projects_data->count(),
                'actual_count' => $poDeposit->projects_data->where('is_real', true)->count(),
                'non_actual_count' => $poDeposit->projects_data->where('is_real', false)->count(),
                'projects' => $poDeposit->projects_data->map(fn($p) => ['id' => $p->id, 'is_real' => $p->is_real])->toArray()
            ]);
            
            if ($allProjects->isEmpty()) {
                Log::info('Skipping deposit - no projects', ['po_deposit_id' => $poDeposit->id]);
                return [];
            }
            
            return $this->syncDepositProjects($poDeposit, $allProjects, $companyId);
        } else {
            // NON-DEPOSIT: Sync all real projects, grouped into single SO
            $realProjects = $poDeposit->projects_data->where('is_real', true);
            
            if ($realProjects->isEmpty()) {
                Log::info('Skipping non-deposit - no real projects', ['po_deposit_id' => $poDeposit->id]);
                return [];
            }
            
            return $this->syncNonDepositProjects($poDeposit, $realProjects, $companyId);
        }
    }

    /**
     * Sync NON-DEPOSIT projects: Group all into SINGLE Sales Order
     */
    private function syncNonDepositProjects(PoDeposit $poDeposit, $projects, ?int $companyId): array
    {
        $items = [];
        $subtotal = 0;
        $totalTax = 0;

        foreach ($projects as $project) {
            foreach ($project->products_data as $product) {
                $itemTotal = ($product->quantity ?? 0) * ($product->price ?? 0);
                $taxAmount = $this->calculateTax($product);
                
                $items[] = [
                    'quantity' => $product->quantity ?? 0,
                    'unit_price' => $product->price ?? 0,
                    'total' => $itemTotal,
                    'description' => $product->description ?? '',
                    'discount' => 0,
                    'discount_percentage' => 0,
                    'tax_amount' => $taxAmount,
                    'product_id' => null, // Will be looked up by code in accounting
                    'product_code' => $product->product_code, // Product identification by code
                    'product_name' => $product->name, // Custom item name (may differ from master product)
                    'item_name' => $product->name, // Sales order item name (custom name)
                    'unit_id' => null,
                    'uom_code' => $product->uom_code,
                    'tax_id' => null,
                    'tax_code' => $product->tax_code,
                    'is_production' => (bool) ($product->is_production ?? false),
                    'qty_per_set' => $product->qty_per_set ?? null,
                    'is_group_main' => (bool) ($product->is_group_main ?? false),
                    'source_project_id' => $project->id,
                    'source_product_id' => $product->id,
                    'project_type' => $project->project_type, // For product category mapping
                ];

                $subtotal += $itemTotal;
                $totalTax += $taxAmount;
            }
        }

        if (empty($items)) {
            throw new \Exception('No products found in projects');
        }

        $salesOrderData = [
            'order_number' => $poDeposit->job_number,
            'order_type' => 'sales_order',
            'date' => $poDeposit->client_po_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'reference_no' => $poDeposit->client_po_number,
            'client_po_number' => $poDeposit->client_po_number,
            'description' => 'Synced from Inventory - Job: ' . $poDeposit->job_number,
            'subtotal' => $subtotal,
            'tax_amount' => $totalTax,
            'total_amount' => $subtotal + $totalTax,
            'discount' => 0,
            'discount_percentage' => 0,
            'other_charges' => 0,
            'status' => 'open',
            'job_number' => $poDeposit->job_number,
            'customer_name' => $poDeposit->client_company,
            'customer_code' => $poDeposit->client_code,
            'company_id' => $companyId,
            'items' => $items,
            'is_grouped' => true,
            'source_po_deposit_id' => $poDeposit->id,
            'project_count' => $projects->count(),
            'is_bundle' => (bool) ($poDeposit->is_bundle ?? false),
            'bundle_meta' => [
                'is_bundle_main' => false,
                'bundle_group' => null,
            ],
        ];

        $response = $this->syncOrder($poDeposit, self::KEY_GROUPED, $salesOrderData);

        return [
            'type' => 'non-deposit',
            'po_deposit_id' => $poDeposit->id,
            'job_number' => $poDeposit->job_number,
            'sales_order_number' => $response['data']['order_number'] ?? $response['order_number'] ?? $poDeposit->job_number,
            'project_ids' => $projects->pluck('id')->toArray(),
            'item_count' => count($items),
            'total_amount' => $salesOrderData['total_amount'],
            'accounting_response' => $response,
        ];
    }

    /**
     * Sync DEPOSIT projects: Each project becomes SEPARATE Sales Order
     * 
     * Flow:
     * 1. First sync NON-ACTUAL projects (deposit SOs)
     * 2. Then sync ACTUAL projects with reference to their parent deposit SO
     */
    private function syncDepositProjects(PoDeposit $poDeposit, $projects, ?int $companyId): array
    {
        $syncedOrders = [];
        
        // Store non-actual SO references for linking with actual SOs
        // Key: project_id, Value: ['sales_order_id', 'sales_order_number', 'job_number']
        $nonActualSoMap = [];

        // Separate projects by type
        $nonActualProjects = $projects->where('is_real', false)->values();
        $actualProjects = $projects->where('is_real', true)->values();

        // Log actual projects' deposit_id for debugging
        foreach ($actualProjects as $project) {
            Log::info('Actual project deposit_id check', [
                'project_id' => $project->id,
                'job_number' => $project->job_number,
                'deposit_id' => $project->deposit_id,
                'client_po_number' => $project->client_po_number,
            ]);
        }

        Log::info('Syncing deposit projects - phase separation', [
            'po_deposit_id' => $poDeposit->id,
            'non_actual_count' => $nonActualProjects->count(),
            'actual_count' => $actualProjects->count(),
            'non_actual_project_ids' => $nonActualProjects->pluck('id')->toArray(),
            'actual_project_ids' => $actualProjects->pluck('id')->toArray(),
            'actual_deposit_ids' => $actualProjects->pluck('deposit_id')->toArray(),
        ]);

        // ========== PHASE 1: Sync NON-ACTUAL projects (deposit SOs) FIRST ==========
        foreach ($nonActualProjects as $index => $project) {
            $items = $this->buildProjectItems($project);
            
            if (empty($items)) {
                Log::warning('Skipping non-actual project - no items', [
                    'project_id' => $project->id,
                    'job_number' => $project->job_number,
                ]);
                continue;
            }

            $totals = $this->calculateProjectTotals($items);
            
            // Non-actual projects use 'deposit' type and -D prefix
            $orderNumber = $poDeposit->job_number . '-D' . ($index + 1);

            $salesOrderData = [
                'order_number' => $orderNumber,
                'order_type' => 'deposit',
                'date' => $project->client_po_date?->format('Y-m-d') ?? $poDeposit->client_po_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'reference_no' => $project->client_po_number ?? $poDeposit->client_po_number,
                'client_po_number' => $project->client_po_number ?? $poDeposit->client_po_number,
                'description' => 'Synced from Inventory - Project: ' . $project->job_number . ' (Deposit)',
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['totalTax'],
                'total_amount' => $totals['subtotal'] + $totals['totalTax'],
                'discount' => 0,
                'discount_percentage' => 0,
                'other_charges' => 0,
                'status' => 'open',
                'job_number' => $project->job_number,
                'customer_name' => $poDeposit->client_company,
                'customer_code' => $poDeposit->client_code,
                'company_id' => $companyId,
                'items' => $items,
                'is_grouped' => false,
                'source_po_deposit_id' => $poDeposit->id,
                'source_project_id' => $project->id,
                'is_actual' => false,
                'is_bundle' => (bool) ($poDeposit->is_bundle ?? false),
                'bundle_meta' => [
                    'is_bundle_main' => (bool) ($poDeposit->is_bundle ?? false),
                    'bundle_project_id' => $project->id,
                    'bundle_group' => $project->client_po_number,
                ],
            ];

            $response = $this->syncOrder($poDeposit, 'p' . $project->id, $salesOrderData);

            // Store the SO info for linking with actual projects
            $soInfo = [
                'sales_order_id' => $response['data']['sales_order_id'] ?? null,
                'sales_order_number' => $response['data']['order_number'] ?? $orderNumber,
                'external_order_number' => $orderNumber,
                'job_number' => $project->job_number,
                'project_id' => $project->id,
            ];
            $nonActualSoMap[$project->id] = $soInfo;

            $syncedOrders[] = [
                'sales_order_number' => $orderNumber,
                'accounting_so_number' => $response['data']['order_number'] ?? null,
                'accounting_so_id' => $response['data']['sales_order_id'] ?? null,
                'project_id' => $project->id,
                'project_job_number' => $project->job_number,
                'is_actual' => false,
                'order_type' => 'deposit',
                'item_count' => count($items),
                'total_amount' => $salesOrderData['total_amount'],
                'accounting_response' => $response,
                'linked_actual_projects' => [], // Will be populated later
            ];

            Log::info('Non-actual project synced (deposit SO)', [
                'project_id' => $project->id,
                'job_number' => $project->job_number,
                'order_number' => $orderNumber,
                'accounting_so_id' => $soInfo['sales_order_id'],
            ]);
        }

        // ========== PHASE 2: Sync ACTUAL projects with parent SO reference ==========
        foreach ($actualProjects as $index => $project) {
            $items = $this->buildProjectItems($project);
            
            if (empty($items)) {
                Log::warning('Skipping actual project - no items', [
                    'project_id' => $project->id,
                    'job_number' => $project->job_number,
                ]);
                continue;
            }

            $totals = $this->calculateProjectTotals($items);
            
            // Actual projects use 'aktual' type and -A prefix
            $orderNumber = $poDeposit->job_number . '-A' . ($index + 1);

            // Find parent deposit SO if deposit_id is set
            $parentSoInfo = null;
            $parentDepositProjectId = $project->deposit_id;
            
            if ($parentDepositProjectId && isset($nonActualSoMap[$parentDepositProjectId])) {
                $parentSoInfo = $nonActualSoMap[$parentDepositProjectId];
                Log::info('Found parent deposit SO for actual project', [
                    'actual_project_id' => $project->id,
                    'parent_deposit_project_id' => $parentDepositProjectId,
                    'parent_so_id' => $parentSoInfo['sales_order_id'],
                    'parent_so_number' => $parentSoInfo['sales_order_number'],
                ]);
            } else {
                Log::warning('No parent deposit SO found for actual project', [
                    'actual_project_id' => $project->id,
                    'deposit_id' => $parentDepositProjectId,
                    'available_deposit_projects' => array_keys($nonActualSoMap),
                ]);
            }

            $salesOrderData = [
                'order_number' => $orderNumber,
                'order_type' => 'aktual',
                'date' => $project->client_po_date?->format('Y-m-d') ?? $poDeposit->client_po_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'reference_no' => $project->client_po_number ?? $poDeposit->client_po_number,
                'client_po_number' => $project->client_po_number ?? $poDeposit->client_po_number,
                'description' => 'Synced from Inventory - Project: ' . $project->job_number . ' (Actual)',
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['totalTax'],
                'total_amount' => $totals['subtotal'] + $totals['totalTax'],
                'discount' => 0,
                'discount_percentage' => 0,
                'other_charges' => 0,
                'status' => 'open',
                'job_number' => $project->job_number,
                'customer_name' => $poDeposit->client_company,
                'customer_code' => $poDeposit->client_code,
                'company_id' => $companyId,
                'items' => $items,
                'is_grouped' => false,
                'source_po_deposit_id' => $poDeposit->id,
                'source_project_id' => $project->id,
                'is_actual' => true,
                'is_bundle' => (bool) ($poDeposit->is_bundle ?? false),
                'bundle_meta' => [
                    'is_bundle_main' => false,
                    'bundle_project_id' => $project->id,
                    'bundle_group' => $project->client_po_number,
                ],
                // Link to parent deposit SO
                'parent_deposit_so_id' => $parentSoInfo['sales_order_id'] ?? null,
                'parent_deposit_so_number' => $parentSoInfo['sales_order_number'] ?? null,
                'parent_deposit_project_id' => $parentDepositProjectId,
            ];

            $response = $this->syncOrder($poDeposit, 'p' . $project->id, $salesOrderData);

            $syncedOrder = [
                'sales_order_number' => $orderNumber,
                'accounting_so_number' => $response['data']['order_number'] ?? null,
                'accounting_so_id' => $response['data']['sales_order_id'] ?? null,
                'project_id' => $project->id,
                'project_job_number' => $project->job_number,
                'is_actual' => true,
                'order_type' => 'aktual',
                'item_count' => count($items),
                'total_amount' => $salesOrderData['total_amount'],
                'accounting_response' => $response,
                'parent_deposit_so' => $parentSoInfo,
            ];

            // Update the parent deposit SO to track this linked actual project
            if ($parentDepositProjectId && isset($nonActualSoMap[$parentDepositProjectId])) {
                foreach ($syncedOrders as &$order) {
                    if ($order['project_id'] == $parentDepositProjectId) {
                        $order['linked_actual_projects'][] = [
                            'project_id' => $project->id,
                            'job_number' => $project->job_number,
                            'so_number' => $orderNumber,
                            'accounting_so_id' => $response['data']['sales_order_id'] ?? null,
                        ];
                        break;
                    }
                }
            }

            $syncedOrders[] = $syncedOrder;

            Log::info('Actual project synced (aktual SO)', [
                'project_id' => $project->id,
                'job_number' => $project->job_number,
                'order_number' => $orderNumber,
                'accounting_so_id' => $response['data']['sales_order_id'] ?? null,
                'parent_deposit_so_id' => $parentSoInfo['sales_order_id'] ?? null,
            ]);
        }

        return [
            'type' => 'deposit',
            'po_deposit_id' => $poDeposit->id,
            'job_number' => $poDeposit->job_number,
            'orders' => $syncedOrders,
            'order_count' => count($syncedOrders),
            'non_actual_orders' => count($nonActualProjects),
            'actual_orders' => count($actualProjects),
            'non_actual_so_map' => $nonActualSoMap,
        ];
    }

    /**
     * Build items array for a project
     */
    private function buildProjectItems($project): array
    {
        $items = [];

        foreach ($project->products_data as $product) {
            $itemTotal = ($product->quantity ?? 0) * ($product->price ?? 0);
            $taxAmount = $this->calculateTax($product);
            
            $items[] = [
                'quantity' => $product->quantity ?? 0,
                'unit_price' => $product->price ?? 0,
                'total' => $itemTotal,
                'description' => $product->description ?? '',
                'discount' => 0,
                'discount_percentage' => 0,
                'tax_amount' => $taxAmount,
                'product_id' => null, // Will be looked up by code in accounting
                'product_code' => $product->product_code, // Product identification by code
                'product_name' => $product->name, // Custom item name (may differ from master product)
                'item_name' => $product->name, // Sales order item name (custom name)
                'unit_id' => null,
                'uom_code' => $product->uom_code,
                'tax_id' => null,
                'tax_code' => $product->tax_code,
                'is_production' => (bool) ($product->is_production ?? false),
                'qty_per_set' => $product->qty_per_set ?? null,
                'is_group_main' => (bool) ($product->is_group_main ?? false),
                'source_project_id' => $project->id,
                'source_product_id' => $product->id,
                'project_type' => $project->project_type,
            ];
        }

        return $items;
    }

    /**
     * Calculate totals for project items
     */
    private function calculateProjectTotals(array $items): array
    {
        $subtotal = 0;
        $totalTax = 0;

        foreach ($items as $item) {
            $subtotal += $item['total'];
            $totalTax += $item['tax_amount'];
        }

        return [
            'subtotal' => $subtotal,
            'totalTax' => $totalTax,
        ];
    }

    /**
     * Calculate tax amount for a product
     */
    private function calculateTax($product): float
    {
        // Default tax calculation - can be enhanced based on tax_code
        $taxRate = 0.11; // 11% default (Indonesian VAT)
        $price = $product->price ?? 0;
        $quantity = $product->quantity ?? 0;
        
        return $price * $quantity * $taxRate;
    }

    /**
     * Send one Sales Order to Accounting: as a delta against the last snapshot
     * when possible, otherwise as a complete payload.
     *
     * @param  array<string, mixed>  $payload  complete payload (what a full sync sends)
     * @return array<string, mixed> Accounting's decoded response
     *
     * @throws AccountingSyncBlocked when Accounting refuses the change on business grounds
     */
    private function syncOrder(PoDeposit $poDeposit, string $soKey, array $payload): array
    {
        $this->touchedKeys[] = $soKey;
        $payload['source_po_deposit_id'] = $poDeposit->id;

        $desired = SnapshotDiffer::stateFromPayload($payload);
        $snapshot = AccountingSyncSnapshot::where('po_deposit_id', $poDeposit->id)->where('so_key', $soKey)->first();
        $version = ($snapshot?->sync_version ?? 0) + 1;

        if ($snapshot && ! $this->forceFull && (int) $snapshot->company_id === (int) $payload['company_id']) {
            $delta = SnapshotDiffer::diff($snapshot->state, $desired);

            if ($delta === null) {
                Log::info('Sales Order unchanged since last sync, skipping', [
                    'po_deposit_id' => $poDeposit->id,
                    'so_key' => $soKey,
                ]);

                return ['data' => [
                    'sales_order_id' => $snapshot->accounting_so_id,
                    'order_number' => $snapshot->accounting_order_number,
                    'mode' => 'update',
                    'status' => 'unchanged',
                ]];
            }

            $response = $this->post($this->deltaBody($payload, $snapshot, $delta, $version));

            if ($response->successful() && ($response->json('data.status') !== 'stale')) {
                return $this->remember($poDeposit, $soKey, $payload, $desired, $version, $response);
            }

            if ($response->successful()) {
                // Accounting already holds a newer version than our snapshot knows about.
                $version = max($version, (int) $response->json('data.sync_version') + 1);
                Log::warning('Delta rejected as stale, falling back to full sync', ['po_deposit_id' => $poDeposit->id, 'so_key' => $soKey]);
            } elseif ($response->status() === 404 || $this->needsFullSync($response)) {
                Log::info('Delta not applicable, falling back to full sync', [
                    'po_deposit_id' => $poDeposit->id,
                    'so_key' => $soKey,
                    'status' => $response->status(),
                ]);
            } else {
                throw $this->failureFrom($response);
            }
        }

        $payload['sync_version'] = $version;
        $response = $this->post($payload);

        if (! $response->successful()) {
            throw $this->failureFrom($response);
        }

        return $this->remember($poDeposit, $soKey, $payload, $desired, $version, $response);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{header: array<string, mixed>, items: list<array<string, mixed>>}  $delta
     * @return array<string, mixed>
     */
    private function deltaBody(array $payload, AccountingSyncSnapshot $snapshot, array $delta, int $version): array
    {
        return [
            'mode' => 'delta',
            'company_id' => $payload['company_id'],
            'source_po_deposit_id' => $payload['source_po_deposit_id'],
            'source_project_id' => $payload['source_project_id'] ?? null,
            'lookup_job_number' => $snapshot->state['header']['job_number'] ?? null,
            'sync_version' => $version,
            'header' => $delta['header'],
            'items' => $delta['items'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function remember(PoDeposit $poDeposit, string $soKey, array $payload, array $state, int $version, Response $response): array
    {
        $json = $response->json() ?? ['status' => 'success'];

        AccountingSyncSnapshot::updateOrCreate(
            ['po_deposit_id' => $poDeposit->id, 'so_key' => $soKey],
            [
                'company_id' => $payload['company_id'],
                'sync_version' => max($version, (int) ($json['data']['sync_version'] ?? 0)),
                'accounting_so_id' => $json['data']['sales_order_id'] ?? null,
                'accounting_order_number' => $json['data']['order_number'] ?? null,
                'state' => $state,
                'synced_at' => now(),
            ],
        );

        Log::info('Sales Order synced successfully', [
            'order_number' => $payload['order_number'],
            'so_key' => $soKey,
            'response' => $json,
        ]);

        return $json;
    }

    /**
     * Orders this PO used to have that are no longer produced (project removed,
     * set to non-real, or emptied): take their items out of Accounting. The
     * Sales Order itself is never deleted.
     */
    private function retireOrphanedOrders(PoDeposit $poDeposit): void
    {
        $orphans = AccountingSyncSnapshot::where('po_deposit_id', $poDeposit->id)
            ->whereNotIn('so_key', $this->touchedKeys ?: [''])
            ->get();

        foreach ($orphans as $snapshot) {
            $ids = array_keys($snapshot->state['items'] ?? []);

            if ($ids === []) {
                continue;
            }

            $version = $snapshot->sync_version + 1;
            $response = $this->post([
                'mode' => 'delta',
                'company_id' => $snapshot->company_id,
                'source_po_deposit_id' => $poDeposit->id,
                'source_project_id' => str_starts_with($snapshot->so_key, 'p') ? (int) substr($snapshot->so_key, 1) : null,
                'lookup_job_number' => $snapshot->state['header']['job_number'] ?? null,
                'sync_version' => $version,
                'header' => [],
                'items' => array_map(fn ($id) => ['op' => 'delete', 'source_product_id' => (int) $id], $ids),
            ]);

            if ($response->successful() || $response->status() === 404) {
                $state = $snapshot->state;
                $state['items'] = [];
                $snapshot->update(['state' => $state, 'sync_version' => $version, 'synced_at' => now()]);
                continue;
            }

            throw $this->failureFrom($response);
        }
    }

    private function post(array $body): Response
    {
        $url = $this->accountingApiUrl . '/sales-orders/sync';

        Log::info('Sending Sales Order to Accounting', [
            'url' => $url,
            'mode' => $body['mode'] ?? 'full',
            'order_number' => $body['order_number'] ?? null,
            'company_id' => $body['company_id'] ?? 'NULL',
            'source_po_deposit_id' => $body['source_po_deposit_id'] ?? null,
            'source_project_id' => $body['source_project_id'] ?? null,
            'sync_version' => $body['sync_version'] ?? null,
            'item_ops' => isset($body['mode']) ? count($body['items'] ?? []) : null,
        ]);

        try {
            return Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->bearerToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($url, $body);
        } catch (\Exception $e) {
            Log::error('Failed to send to Accounting API', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function needsFullSync(Response $response): bool
    {
        if ($response->status() !== 422) {
            return false;
        }

        return collect($response->json('reasons') ?? [])->contains(fn ($reason) => ($reason['code'] ?? null) === 'needs_full_sync');
    }

    private function failureFrom(Response $response): \Exception
    {
        $reasons = $response->json('reasons');

        if (in_array($response->status(), [409, 422], true) && is_array($reasons) && $reasons !== []) {
            $summary = collect($reasons)->map(fn ($r) => ($r['code'] ?? 'rejected') . (isset($r['detail']) ? ': ' . $r['detail'] : ''))->implode('; ');

            return new AccountingSyncBlocked($response->status(), $summary, $reasons);
        }

        $message = 'Accounting API error: ' . $response->status() . ' - ' . $response->body();
        Log::error($message);

        return new \Exception($message);
    }

    /**
     * Get sync preview without actually syncing
     * Shows what would be synced
     */
    public function getSyncPreview(?int $poDepositId = null): array
    {
        $preview = [
            'non_deposits' => [],
            'deposits' => []
        ];

        // For deposits: load ALL projects (both actual and non-actual)
        // For non-deposits: load only real projects
        $query = PoDeposit::with(['projects_data', 'projects_data.products_data']);

        if ($poDepositId) {
            $query->where('id', $poDepositId);
        }

        $poDeposits = $query->get();

        foreach ($poDeposits as $poDeposit) {
            if ($poDeposit->is_po_deposit) {
                // DEPOSIT: Include ALL projects (both actual and non-actual)
                $allProjects = $poDeposit->projects_data;
                
                if ($allProjects->isEmpty()) {
                    continue;
                }

                $totalItems = 0;
                $totalAmount = 0;

                foreach ($allProjects as $project) {
                    $projectItems = $project->products_data->count();
                    $projectAmount = $project->products_data->sum(function($p) {
                        return ($p->quantity ?? 0) * ($p->price ?? 0);
                    });
                    $totalItems += $projectItems;
                    $totalAmount += $projectAmount;
                }

                $actualCount = $allProjects->where('is_real', true)->count();
                $nonActualCount = $allProjects->where('is_real', false)->count();

                $data = [
                    'po_deposit_id' => $poDeposit->id,
                    'job_number' => $poDeposit->job_number,
                    'client_company' => $poDeposit->client_company,
                    'client_po_number' => $poDeposit->client_po_number,
                    'project_count' => $allProjects->count(),
                    'actual_count' => $actualCount,
                    'non_actual_count' => $nonActualCount,
                    'total_items' => $totalItems,
                    'total_amount' => $totalAmount,
                    'projects' => $allProjects->map(function($p) {
                        return [
                            'id' => $p->id,
                            'job_number' => $p->job_number,
                            'title' => $p->title,
                            'total_price' => $p->total_price,
                            'item_count' => $p->products_data->count(),
                            'is_actual' => $p->is_real,
                            'type' => $p->is_real ? 'actual' : 'deposit',
                        ];
                    })->toArray()
                ];

                // Deposit: will be split (each project = 1 SO)
                $data['sales_orders_count'] = $allProjects->count();
                $data['grouping'] = 'split (each project = 1 SO)';
                $data['actual_orders'] = $actualCount;
                $data['non_actual_orders'] = $nonActualCount;
                $preview['deposits'][] = $data;
            } else {
                // NON-DEPOSIT: Include only real projects, grouped into single SO
                $realProjects = $poDeposit->projects_data->where('is_real', true);
                
                if ($realProjects->isEmpty()) {
                    continue;
                }

                $totalItems = 0;
                $totalAmount = 0;

                foreach ($realProjects as $project) {
                    $projectItems = $project->products_data->count();
                    $projectAmount = $project->products_data->sum(function($p) {
                        return ($p->quantity ?? 0) * ($p->price ?? 0);
                    });
                    $totalItems += $projectItems;
                    $totalAmount += $projectAmount;
                }

                $data = [
                    'po_deposit_id' => $poDeposit->id,
                    'job_number' => $poDeposit->job_number,
                    'client_company' => $poDeposit->client_company,
                    'client_po_number' => $poDeposit->client_po_number,
                    'project_count' => $realProjects->count(),
                    'total_items' => $totalItems,
                    'total_amount' => $totalAmount,
                    'projects' => $realProjects->map(function($p) {
                        return [
                            'id' => $p->id,
                            'job_number' => $p->job_number,
                            'title' => $p->title,
                            'total_price' => $p->total_price,
                            'item_count' => $p->products_data->count(),
                        ];
                    })->toArray()
                ];

                // Non-deposit: will be grouped
                $data['sales_orders_count'] = 1;
                $data['grouping'] = 'grouped (all projects = 1 SO)';
                $preview['non_deposits'][] = $data;
            }
        }

        return $preview;
    }
}
