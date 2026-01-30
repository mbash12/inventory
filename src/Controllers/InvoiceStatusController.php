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
        $this->middleware('jwt.verify', ['except' => ['syncFromAccounting']]);
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
        $rules = [
            'job_number' => 'required|string',
            'invoice_number' => 'nullable|string',
            'invoice_date' => 'nullable|date',
            'status' => 'required|string|in:process,sent,paid',  // 'process' will be mapped to 'progress' in DB
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
            $poDeposit = PoDeposit::where('job_number', $jobNumber)->first();
            
            if (!$poDeposit) {
                Log::warning('PoDeposit not found for invoice status sync', ['job_number' => $jobNumber]);
                return response()->json([
                    'code' => 404,
                    'message' => 'PoDeposit not found with job_number: ' . $jobNumber
                ], 404);
            }

            // Map accounting status to inventory status
            // Accounting: 'process' -> Inventory: 'progress'
            // Accounting: 'sent'/'paid' -> Inventory: 'sent'
            $inventoryStatus = match($status) {
                'process' => 'progress',
                'paid' => 'sent',
                default => $status,
            };
            
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
                if ($status === 'sent' || $status === 'paid') {
                    $note .= " - Status: LUNAS";
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
                
                // If this is the main project and we have invoice details, update invoice data
                if ($mainProject && $project->id === $mainProject->id && $request->invoice_number) {
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
                        $currentInvoiced = $project->invoiced_amount ?? 0;
                        $project->invoiced_amount = $currentInvoiced + $invoiceAmount;
                        $project->remaining_amount = $project->total_price - $project->invoiced_amount;
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
            if ($status === 'process') {
                $threadNotes = "Invoice dibuat di Accounting System (menunggu pembayaran)\n" . $note;
            } elseif ($status === 'sent' || $status === 'paid') {
                $threadNotes = "Invoice telah dibayar / LUNAS\n" . $note;
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
}
