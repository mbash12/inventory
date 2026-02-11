<?php

namespace Src\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Src\Models\PoDeposit;
use Src\Models\InvoiceProgress;
use Src\Models\Project;
use Src\Models\Thread;
use Src\Models\User;
use Src\Services\SyncLockService;
use Illuminate\Support\Facades\Log;

class InvoiceStatusController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.verify', ['except' => ['syncFromAccounting', 'deleteFromAccounting']]);
    }

    /**
     * Receive invoice status updates from Accounting system
     * Note: This method is also accessible via internal.api middleware (no JWT required)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncFromAccounting(Request $request)
    {
        Log::info('Invoice status sync request received', [
            'ip' => $request->ip(),
            'all' => $request->all(),
        ]);
        
        $rules = [
            'job_number' => 'required|string',
            'invoice_number' => 'nullable|string',
            'invoice_date' => 'nullable|date',
            'status' => 'required|string|in:sent',  // 'sent' means invoice issued
            'amount' => 'nullable|numeric',
            'paid_amount' => 'nullable|numeric',
            'note' => 'nullable|string',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            Log::error('Invoice status sync validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'code' => 422,
                'message' => 'Invalid input',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $jobNumber = $request->job_number;
            $status = $request->status;
            
            // Find PoDeposit by job_number
            // First try direct match (PoDeposit job_number)
            $poDeposit = PoDeposit::where('job_number', $jobNumber)->first();
            $targetProject = null; // The specific project that was invoiced
            
            // If not found, try to find a Project with this job_number and get its PoDeposit
            // This handles cases where Accounting sends child project job_numbers like "aaa-CD001"
            if (!$poDeposit) {
                $targetProject = Project::where('job_number', $jobNumber)->first();
                if ($targetProject && $targetProject->po_deposit) {
                    $poDeposit = PoDeposit::find($targetProject->po_deposit);
                }
            }
            
            if (!$poDeposit) {
                Log::warning('PoDeposit not found for invoice status sync', ['job_number' => $jobNumber]);
                return response()->json([
                    'code' => 404,
                    'message' => 'PoDeposit not found with job_number: ' . $jobNumber
                ], 404);
            }

            // No mapping needed - 'sent' from accounting means invoice issued
            $inventoryStatus = $status;
            
            // Get the main project associated with this PoDeposit
            $mainProject = Project::where('po_deposit', $poDeposit->id)
                ->where('is_real', true)
                ->first();
            
            if (!$mainProject) {
                $mainProject = Project::where('po_deposit', $poDeposit->id)->first();
            }

            // Calculate invoice amount
            $invoiceAmount = $request->amount ?? 0;
            
            // Build note for InvoiceProgress
            $note = $request->note ?? '';
            if ($request->invoice_number) {
                $note = "Invoice dari Accounting: " . $request->invoice_number;
                if ($request->invoice_date) {
                    $note .= " (" . $request->invoice_date . ")";
                }
                if ($invoiceAmount > 0) {
                    $note .= " - Amount: " . number_format($invoiceAmount, 2);
                }
            }

            // Add invoice to po_deposit invoices JSON if provided
            $existingInvoices = json_decode($poDeposit->invoices ?? '[]', true) ?? [];
            
            if ($request->invoice_number && $request->invoice_date) {
                // Check if invoice already exists
                $invoiceExists = false;
                foreach ($existingInvoices as &$inv) {
                    if ($inv['invoice_number'] === $request->invoice_number) {
                        $invoiceExists = true;
                        // Update status if already exists
                        $inv['status'] = $status;
                        if ($invoiceAmount > 0) {
                            $inv['amount'] = $invoiceAmount;
                        }
                        break;
                    }
                }
                
                // Add new invoice if not exists
                if (!$invoiceExists) {
                    $existingInvoices[] = [
                        'invoice_number' => $request->invoice_number,
                        'invoice_date' => $request->invoice_date,
                        'invoice_amount' => $invoiceAmount,
                        'amount' => $invoiceAmount,
                        'status' => $status,
                    ];
                }
                
                $poDeposit->invoices = json_encode(array_values($existingInvoices));
            }

            // Update PoDeposit invoice_status
            $poDeposit->invoice_status = $inventoryStatus;
            $poDeposit->save();

            // Create InvoiceProgress record
            // PIC is 'finance' since this sync comes from Accounting system
            $invoiceProgress = InvoiceProgress::create([
                'po_deposit' => $poDeposit->id,
                'pic' => 'finance',
                'status' => $inventoryStatus,
                'note' => $note,
            ]);

            // Update associated projects - create Thread record and update invoice data
            $projects = Project::where('po_deposit', $poDeposit->id)->get();
            
            // Lock entities to prevent circular sync (back-firing to Accounting)
            SyncLockService::lockPoDeposit($poDeposit->id);
            foreach ($projects as $project) {
                SyncLockService::lockProject($project->id);
            }
            
            foreach ($projects as $project) {
                // Update project invoice_status
                $project->invoice_status = $inventoryStatus;
                
                // Save invoice to project if:
                // 1. This is the main project, OR
                // 2. This is the specific target project (child project that was invoiced)
                $isMainProject = $mainProject && $project->id === $mainProject->id;
                $isTargetProject = $targetProject && $project->id === $targetProject->id;
                
                if (($isMainProject || $isTargetProject) && $request->invoice_number) {
                    $projectInvoices = json_decode($project->invoices ?? '[]', true) ?? [];
                    
                    // Check if invoice already exists in project
                    $invoiceExists = false;
                    foreach ($projectInvoices as &$inv) {
                        if ($inv['invoice_number'] === $request->invoice_number) {
                            $invoiceExists = true;
                            $inv['status'] = $status;
                            if ($invoiceAmount > 0) {
                                $inv['invoice_amount'] = $invoiceAmount;
                                $inv['amount'] = $invoiceAmount;
                            }
                            break;
                        }
                    }
                    
                    if (!$invoiceExists && $request->invoice_number && $request->invoice_date) {
                        $projectInvoices[] = [
                            'invoice_number' => $request->invoice_number,
                            'invoice_date' => $request->invoice_date,
                            'invoice_amount' => $invoiceAmount,
                            'amount' => $invoiceAmount,
                            'status' => $status,
                        ];
                        
                        // Update invoiced_amount and remaining_amount
                        // Use precise decimal arithmetic to avoid floating-point errors
                        $currentInvoiced = (float) ($project->invoiced_amount ?? 0);
                        $totalPrice = (float) ($project->total_price ?? 0);
                        $project->invoiced_amount = round($currentInvoiced + $invoiceAmount, 2);
                        $project->remaining_amount = round($totalPrice - $project->invoiced_amount, 2);
                    }
                    
                    $project->invoices = json_encode(array_values($projectInvoices));
                }
                
                $project->save();
                
                // Create Thread record so it appears in thread page
                $this->createInvoiceThread($project, $status, $note, $request->all());
            }

            // Unlock entities after sync is complete
            foreach ($projects as $project) {
                SyncLockService::unlockProject($project->id);
            }
            SyncLockService::unlockPoDeposit($poDeposit->id);

            Log::info('Invoice status synced from accounting', [
                'job_number' => $jobNumber,
                'po_deposit_id' => $poDeposit->id,
                'status' => $inventoryStatus,
                'invoice_number' => $request->invoice_number,
                'project_count' => $projects->count(),
            ]);

            return response()->json([
                'code' => 200,
                'message' => 'Invoice status synced successfully',
                'data' => [
                    'po_deposit_id' => $poDeposit->id,
                    'job_number' => $jobNumber,
                    'status' => $inventoryStatus,
                    'invoice_progress_id' => $invoiceProgress->id,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Invoice status sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'code' => 500,
                'message' => 'Failed to sync invoice status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a Thread record for invoice update
     */
    private function createInvoiceThread(Project $project, string $status, string $note, array $invoiceData): void
    {
        try {
            // Build meta data
            $meta = [
                'invoice_status' => $status,
                'source' => 'accounting_sync',
            ];
            
            if (!empty($invoiceData['invoice_number'])) {
                $meta['invoice_number'] = $invoiceData['invoice_number'];
            }
            if (!empty($invoiceData['invoice_date'])) {
                $meta['invoice_date'] = $invoiceData['invoice_date'];
            }
            if (!empty($invoiceData['amount'])) {
                $meta['invoice_amount'] = $invoiceData['amount'];
                $meta['invoiced_amount'] = $project->invoiced_amount;
                $meta['remaining_amount'] = $project->remaining_amount;
                $meta['total_price'] = $project->total_price;
            }

            // Build thread notes
            $threadNotes = $note;
            if ($status === 'sent') {
                $threadNotes = "Invoice telah dibuat/dikirim dari Accounting System\n" . $note;
            }

            // Create the thread
            Thread::create([
                'project_id' => $project->id,
                'type' => 'invoice',
                'notes' => $threadNotes,
                'meta_data' => json_encode($meta),
                'user_id' => null, // System user
            ]);

            Log::debug('Invoice thread created', [
                'project_id' => $project->id,
                'status' => $status,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create invoice thread', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
            ]);
            // Don't throw - thread creation failure shouldn't break the sync
        }
    }

    /**
     * Get sync status for a PoDeposit
     */
    public function getStatus($jobNumber)
    {
        $poDeposit = PoDeposit::where('job_number', $jobNumber)->first();

        if (!$poDeposit) {
            return response()->json([
                'code' => 404,
                'message' => 'PoDeposit not found'
            ], 404);
        }

        $invoiceProgresses = InvoiceProgress::where('po_deposit', $poDeposit->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'code' => 200,
            'data' => [
                'po_deposit' => $poDeposit,
                'invoice_progresses' => $invoiceProgresses,
            ]
        ]);
    }

    /**
     * Handle invoice deletion from Accounting system
     * Remove the invoice from PoDeposit and Projects
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteFromAccounting(Request $request)
    {
        Log::info('Invoice deletion request received', [
            'ip' => $request->ip(),
            'all' => $request->all(),
        ]);

        $rules = [
            'job_number' => 'required|string',
            'invoice_number' => 'required|string',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            Log::error('Invoice deletion validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'code' => 422,
                'message' => 'Invalid input',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $jobNumber = $request->job_number;
            $invoiceNumber = $request->invoice_number;

            // Find PoDeposit by job_number
            $poDeposit = PoDeposit::where('job_number', $jobNumber)->first();
            $targetProject = null;

            // If not found, try to find a Project with this job_number
            if (!$poDeposit) {
                $targetProject = Project::where('job_number', $jobNumber)->first();
                if ($targetProject && $targetProject->po_deposit) {
                    $poDeposit = PoDeposit::find($targetProject->po_deposit);
                }
            }

            if (!$poDeposit) {
                Log::warning('PoDeposit not found for invoice deletion', ['job_number' => $jobNumber]);
                return response()->json([
                    'code' => 404,
                    'message' => 'PoDeposit not found with job_number: ' . $jobNumber
                ], 404);
            }

            $existingInvoices = json_decode($poDeposit->invoices ?? '[]', true) ?? [];
            $invoiceFound = false;
            $invoiceAmount = 0;

            // Remove invoice from po_deposit invoices JSON
            $existingInvoices = array_filter($existingInvoices, function ($inv) use ($invoiceNumber, &$invoiceFound, &$invoiceAmount) {
                if ($inv['invoice_number'] === $invoiceNumber) {
                    $invoiceFound = true;
                    $invoiceAmount = (float) ($inv['invoice_amount'] ?? $inv['amount'] ?? 0);
                    return false; // Remove this invoice
                }
                return true;
            });
            $existingInvoices = array_values($existingInvoices);

            if ($invoiceFound) {
                $poDeposit->invoices = json_encode($existingInvoices);

                // Update invoice_status based on remaining invoices
                if (empty($existingInvoices)) {
                    // No invoices remaining, set status to 'new'
                    $poDeposit->invoice_status = 'new';
                } else {
                    // Get the last invoice status (most recent by date)
                    $lastInvoice = collect($existingInvoices)->sortByDesc('invoice_date')->first();
                    $poDeposit->invoice_status = $lastInvoice['status'] ?? 'new';
                }
            }

            // Get the main project associated with this PoDeposit
            $mainProject = Project::where('po_deposit', $poDeposit->id)
                ->where('is_real', true)
                ->first();

            if (!$mainProject) {
                $mainProject = Project::where('po_deposit', $poDeposit->id)->first();
            }

            // Update associated projects
            $projects = Project::where('po_deposit', $poDeposit->id)->get();

            // Lock entities to prevent circular sync
            SyncLockService::lockPoDeposit($poDeposit->id);
            foreach ($projects as $project) {
                SyncLockService::lockProject($project->id);
            }

            $projectUpdated = false;
            foreach ($projects as $project) {
                $isMainProject = $mainProject && $project->id === $mainProject->id;
                $isTargetProject = $targetProject && $project->id === $targetProject->id;

                if ($isMainProject || $isTargetProject) {
                    $projectInvoices = json_decode($project->invoices ?? '[]', true) ?? [];

                    // Remove invoice from project invoices
                    $projectInvoices = array_filter($projectInvoices, function ($inv) use ($invoiceNumber) {
                        return $inv['invoice_number'] !== $invoiceNumber;
                    });
                    $projectInvoices = array_values($projectInvoices);

                    $project->invoices = json_encode($projectInvoices);

                    // Update invoice_status based on remaining invoices
                    if (empty($projectInvoices)) {
                        // No invoices remaining, set status to 'new'
                        $project->invoice_status = 'new';
                    } else {
                        // Get the last invoice status (most recent by date)
                        $lastInvoice = collect($projectInvoices)->sortByDesc('invoice_date')->first();
                        $project->invoice_status = $lastInvoice['status'] ?? 'new';
                    }

                    // Update invoiced_amount and remaining_amount
                    $currentInvoiced = (float) ($project->invoiced_amount ?? 0);
                    $totalPrice = (float) ($project->total_price ?? 0);
                    $project->invoiced_amount = round($currentInvoiced - $invoiceAmount, 2);
                    $project->remaining_amount = round($totalPrice - $project->invoiced_amount, 2);

                    $project->save();
                    $projectUpdated = true;

                    // Create Thread record for deletion
                    $this->createDeletionThread($project, $invoiceNumber, $invoiceAmount);
                }
            }

            // Save PoDeposit if invoice was found
            if ($invoiceFound) {
                $poDeposit->save();
            }

            // Unlock entities after sync is complete
            foreach ($projects as $project) {
                SyncLockService::unlockProject($project->id);
            }
            SyncLockService::unlockPoDeposit($poDeposit->id);

            Log::info('Invoice deleted from inventory', [
                'job_number' => $jobNumber,
                'po_deposit_id' => $poDeposit->id,
                'invoice_number' => $invoiceNumber,
                'invoice_found' => $invoiceFound,
                'project_updated' => $projectUpdated,
            ]);

            return response()->json([
                'code' => 200,
                'message' => 'Invoice deleted successfully',
                'data' => [
                    'po_deposit_id' => $poDeposit->id,
                    'job_number' => $jobNumber,
                    'invoice_number' => $invoiceNumber,
                    'invoice_found' => $invoiceFound,
                    'project_updated' => $projectUpdated,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Invoice deletion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'code' => 500,
                'message' => 'Failed to delete invoice',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a Thread record for invoice deletion
     */
    private function createDeletionThread(Project $project, string $invoiceNumber, float $invoiceAmount): void
    {
        try {
            $note = "Invoice {$invoiceNumber} telah dihapus dari Accounting System";
            if ($invoiceAmount > 0) {
                $note .= " - Amount: " . number_format($invoiceAmount, 2);
            }

            $meta = [
                'invoice_status' => 'deleted',
                'source' => 'accounting_sync',
                'invoice_number' => $invoiceNumber,
                'invoice_amount' => $invoiceAmount,
                'invoiced_amount' => $project->invoiced_amount,
                'remaining_amount' => $project->remaining_amount,
                'total_price' => $project->total_price,
            ];

            Thread::create([
                'project_id' => $project->id,
                'type' => 'invoice',
                'notes' => $note,
                'meta_data' => json_encode($meta),
                'user_id' => null, // System user
            ]);

            Log::debug('Invoice deletion thread created', [
                'project_id' => $project->id,
                'invoice_number' => $invoiceNumber,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create invoice deletion thread', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
            ]);
            // Don't throw - thread creation failure shouldn't break the deletion
        }
    }
}
