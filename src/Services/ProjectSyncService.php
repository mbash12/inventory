<?php

namespace Src\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Src\Models\Project;
use Src\Models\PoDeposit;

class ProjectSyncService
{
    private $accountingApiUrl;
    private $bearerToken;

    public function __construct()
    {
        $this->accountingApiUrl = rtrim(config('app.accounting_api_url', env('ACCOUNTING_API_URL', 'http://localhost:8001/api')), '/');
        $this->bearerToken = env('EPROC_INTEGRATION_BEARER');
    }

    /**
     * Sync projects to Sales Orders in Accounting system
     * 
     * For NON-DEPOSIT (is_po_deposit = false): 
     * - Group all projects under the same po_deposit into a SINGLE Sales Order
     * 
     * For DEPOSIT (is_po_deposit = true):
     * - Each project becomes a SEPARATE Sales Order (split)
     * 
     * @param int|null $poDepositId Sync specific PO Deposit (null for all)
     * @param int|null $companyId Target company ID in accounting system
     * @return array Sync results
     */
    public function syncSinglePoDeposit(PoDeposit $poDeposit, ?int $companyId = null): array
    {
        $companyId = $companyId ?? env('DEFAULT_COMPANY_ID', 12);
        
        $results = [
            'success' => true,
            'message' => '',
            'synced' => [],
            'errors' => []
        ];

        try {
            $syncResult = $this->syncPoDeposit($poDeposit, $companyId);
            
            if (!empty($syncResult)) {
                $results['synced'] = $syncResult;
                $results['message'] = 'Sync completed successfully';
            } else {
                $results['message'] = 'No projects to sync';
            }

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
        // Default company_id from env
        $companyId = $companyId ?? env('DEFAULT_COMPANY_ID', 12);
        
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

                try {
                    $syncResult = $this->syncPoDeposit($poDeposit, $companyId);
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
        // Check if this is a deposit or non-deposit
        if ($poDeposit->is_po_deposit) {
            // DEPOSIT: Only sync non-actual projects (is_real = false), one-to-one SO
            $nonActualProjects = $poDeposit->projects_data->where('is_real', false);
            
            Log::info('Checking deposit projects', [
                'po_deposit_id' => $poDeposit->id,
                'total_projects' => $poDeposit->projects_data->count(),
                'non_actual_count' => $nonActualProjects->count(),
                'projects' => $poDeposit->projects_data->map(fn($p) => ['id' => $p->id, 'is_real' => $p->is_real])->toArray()
            ]);
            
            if ($nonActualProjects->isEmpty()) {
                Log::info('Skipping deposit - no non-actual projects', ['po_deposit_id' => $poDeposit->id]);
                return [];
            }
            
            return $this->syncDepositProjects($poDeposit, $nonActualProjects, $companyId);
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
                    'description' => $product->name . ($product->description ? ' - ' . $product->description : ''),
                    'discount' => 0,
                    'discount_percentage' => 0,
                    'tax_amount' => $taxAmount,
                    'product_id' => null, // Will be looked up by name in accounting
                    'product_name' => $product->name,
                    'unit_id' => null,
                    'uom_code' => $product->uom_code,
                    'tax_id' => null,
                    'tax_code' => $product->tax_code,
                    'source_project_id' => $project->id,
                    'source_product_id' => $product->id,
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
            'project_count' => $projects->count(),
        ];

        $response = $this->sendToAccounting($salesOrderData);

        return [
            'type' => 'non-deposit',
            'po_deposit_id' => $poDeposit->id,
            'job_number' => $poDeposit->job_number,
            'sales_order_number' => $response['order_number'] ?? $poDeposit->job_number,
            'project_ids' => $projects->pluck('id')->toArray(),
            'item_count' => count($items),
            'total_amount' => $salesOrderData['total_amount'],
            'accounting_response' => $response,
        ];
    }

    /**
     * Sync DEPOSIT projects: Each project becomes SEPARATE Sales Order
     */
    private function syncDepositProjects(PoDeposit $poDeposit, $projects, ?int $companyId): array
    {
        $syncedOrders = [];

        foreach ($projects as $index => $project) {
            $items = [];
            $subtotal = 0;
            $totalTax = 0;

            foreach ($project->products_data as $product) {
                $itemTotal = ($product->quantity ?? 0) * ($product->price ?? 0);
                $taxAmount = $this->calculateTax($product);
                
                $items[] = [
                    'quantity' => $product->quantity ?? 0,
                    'unit_price' => $product->price ?? 0,
                    'total' => $itemTotal,
                    'description' => $product->name . ($product->description ? ' - ' . $product->description : ''),
                    'discount' => 0,
                    'discount_percentage' => 0,
                    'tax_amount' => $taxAmount,
                    'product_id' => null,
                    'product_name' => $product->name,
                    'unit_id' => null,
                    'uom_code' => $product->uom_code,
                    'tax_id' => null,
                    'tax_code' => $product->tax_code,
                    'source_project_id' => $project->id,
                    'source_product_id' => $product->id,
                ];

                $subtotal += $itemTotal;
                $totalTax += $taxAmount;
            }

            if (empty($items)) {
                continue;
            }

            // For deposits, append index to make order number unique
            $orderNumber = $poDeposit->job_number . '-D' . ($index + 1);

            $salesOrderData = [
                'order_number' => $orderNumber,
                'order_type' => 'deposit',
                'date' => $project->client_po_date?->format('Y-m-d') ?? $poDeposit->client_po_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'reference_no' => $project->client_po_number ?? $poDeposit->client_po_number,
                'description' => 'Synced from Inventory - Project: ' . $project->job_number . ' (Deposit)',
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'total_amount' => $subtotal + $totalTax,
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
            ];

            $response = $this->sendToAccounting($salesOrderData);

            $syncedOrders[] = [
                'sales_order_number' => $orderNumber,
                'project_id' => $project->id,
                'project_job_number' => $project->job_number,
                'item_count' => count($items),
                'total_amount' => $salesOrderData['total_amount'],
                'accounting_response' => $response,
            ];
        }

        return [
            'type' => 'deposit',
            'po_deposit_id' => $poDeposit->id,
            'job_number' => $poDeposit->job_number,
            'orders' => $syncedOrders,
            'order_count' => count($syncedOrders),
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
     * Send Sales Order data to Accounting API
     */
    private function sendToAccounting(array $salesOrderData): array
    {
        $url = $this->accountingApiUrl . '/sales-orders/sync';

        Log::info('Sending Sales Order to Accounting', [
            'url' => $url,
            'order_number' => $salesOrderData['order_number'],
            'company_id' => $salesOrderData['company_id'] ?? 'NULL',
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->bearerToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($url, $salesOrderData);

            if ($response->successful()) {
                Log::info('Sales Order synced successfully', [
                    'order_number' => $salesOrderData['order_number'],
                    'response' => $response->json()
                ]);
                return $response->json() ?? ['status' => 'success'];
            } else {
                $errorMsg = 'Accounting API error: ' . $response->status() . ' - ' . $response->body();
                Log::error($errorMsg);
                throw new \Exception($errorMsg);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send to Accounting API', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
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

        $query = PoDeposit::with(['projects_data' => function($q) {
            $q->where('is_real', true);
        }, 'projects_data.products_data']);

        if ($poDepositId) {
            $query->where('id', $poDepositId);
        }

        $poDeposits = $query->get();

        foreach ($poDeposits as $poDeposit) {
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

            if ($poDeposit->is_po_deposit) {
                // Deposit: will be split
                $data['sales_orders_count'] = $realProjects->count();
                $data['grouping'] = 'split (each project = 1 SO)';
                $preview['deposits'][] = $data;
            } else {
                // Non-deposit: will be grouped
                $data['sales_orders_count'] = 1;
                $data['grouping'] = 'grouped (all projects = 1 SO)';
                $preview['non_deposits'][] = $data;
            }
        }

        return $preview;
    }
}
