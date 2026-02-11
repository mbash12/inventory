<?php

namespace Src\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Src\Jobs\ProjectSyncJob;
use Src\Models\PoDeposit;
use Src\Services\ProjectSyncService;

class ProjectSyncController extends Controller
{
    private $syncService;

    public function __construct(ProjectSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Get list of PO Deposits with sync status for monitoring
     */
    public function index(Request $request)
    {
        $query = PoDeposit::whereNotNull('client_code');

        if ($request->filled('sync_status')) {
            $query->where('sync_status', $request->input('sync_status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('job_number', 'like', "%{$search}%")
                  ->orWhere('client_company', 'like', "%{$search}%");
            });
        }

        $poDeposits = $query->with('projects_data')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'code' => 200,
            'data' => $poDeposits->map(function($pd) {
                return [
                    'id' => $pd->id,
                    'job_number' => $pd->job_number,
                    'client_company' => $pd->client_company,
                    'client_code' => $pd->client_code,
                    'is_po_deposit' => $pd->is_po_deposit,
                    'project_count' => $pd->projects_data->count(),
                    'sync_status' => $pd->sync_status,
                    'last_synced_at' => $pd->last_synced_at,
                    'sync_error' => $pd->sync_error,
                    'sync_retry_count' => $pd->sync_retry_count,
                    'can_retry' => in_array($pd->sync_status, ['failed', null]),
                ];
            }),
            'meta' => [
                'current_page' => $poDeposits->currentPage(),
                'total' => $poDeposits->total(),
                'per_page' => $poDeposits->perPage(),
            ]
        ]);
    }

    /**
     * Immediate retry for failed sync (no queue, runs immediately)
     */
    public function retrySync($poDepositId, Request $request)
    {
        $poDeposit = PoDeposit::find($poDepositId);

        if (!$poDeposit) {
            return response()->json([
                'code' => 404,
                'message' => 'PO Deposit not found'
            ], 404);
        }

        // Get company_id from request or determine from ppn_type if not provided
        $companyId = $request->input('company_id');
        if ($companyId === null) {
            $ppnType = $poDeposit->ppn_type ?? 'ppn';
            $companyId = $this->getCompanyIdFromPpnType($ppnType);
        } else {
            $companyId = (int)$companyId;
        }

        try {
            // Update status
            $poDeposit->update([
                'sync_status' => 'syncing',
                'sync_error' => null,
            ]);

            // Run immediately
            $result = $this->syncService->syncSinglePoDeposit($poDeposit, $companyId);

            // Update based on result
            if (empty($result['errors'])) {
                $poDeposit->update([
                    'sync_status' => 'success',
                    'last_synced_at' => now(),
                    'sync_error' => null,
                    'sync_retry_count' => 0,
                ]);
            } else {
                $poDeposit->update([
                    'sync_status' => 'failed',
                    'sync_error' => json_encode($result['errors']),
                    'sync_retry_count' => $poDeposit->sync_retry_count + 1,
                ]);
            }

            return response()->json([
                'code' => 200,
                'message' => empty($result['errors']) ? 'Sync completed successfully' : 'Sync completed with errors',
                'data' => [
                    'po_deposit_id' => $poDeposit->id,
                    'sync_status' => $poDeposit->sync_status,
                    'synced_count' => count($result['synced'] ?? []),
                    'error_count' => count($result['errors'] ?? []),
                    'errors' => $result['errors'] ?? [],
                ]
            ]);

        } catch (\Exception $e) {
            $poDeposit->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
                'sync_retry_count' => $poDeposit->sync_retry_count + 1,
            ]);

            return response()->json([
                'code' => 500,
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync status for specific PO Deposit
     */
    public function status($poDepositId)
    {
        $poDeposit = PoDeposit::with('projects_data')->find($poDepositId);

        if (!$poDeposit) {
            return response()->json([
                'code' => 404,
                'message' => 'PO Deposit not found'
            ], 404);
        }

        return response()->json([
            'code' => 200,
            'data' => [
                'id' => $poDeposit->id,
                'job_number' => $poDeposit->job_number,
                'client_company' => $poDeposit->client_company,
                'is_po_deposit' => $poDeposit->is_po_deposit,
                'project_count' => $poDeposit->projects_data->count(),
                'sync_status' => $poDeposit->sync_status,
                'last_synced_at' => $poDeposit->last_synced_at,
                'sync_error' => $poDeposit->sync_error ? json_decode($poDeposit->sync_error, true) : null,
                'sync_retry_count' => $poDeposit->sync_retry_count,
                'can_retry' => in_array($poDeposit->sync_status, ['failed', null]),
            ]
        ]);
    }

    /**
     * Clear old completed PO Deposit sync records
     */
    public function clearData(Request $request)
    {
        $days = $request->input('days', 7);
        
        // Validate days parameter
        if ($days < 1 || $days > 365) {
            return response()->json([
                'code' => 400,
                'message' => 'Days parameter must be between 1 and 365'
            ], 400);
        }

        // Count records that would be cleared
        $deleteBeforeDate = now()->subDays($days);
        $countToClear = \Src\Models\PoDeposit::whereNotNull('sync_status')
            ->where('last_synced_at', '<=', $deleteBeforeDate)
            ->count();

        if ($countToClear === 0) {
            return response()->json([
                'code' => 200,
                'message' => 'No records to clear',
                'data' => [
                    'records_to_clear' => 0,
                    'days_old' => $days
                ]
            ]);
        }

        // Perform the update to reset sync status for old records
        $clearedCount = \Src\Models\PoDeposit::whereNotNull('sync_status')
            ->where('last_synced_at', '<=', $deleteBeforeDate)
            ->update([
                'sync_status' => null,
                'sync_error' => null,
                'sync_retry_count' => 0,
                'last_synced_at' => null
            ]);

        \Illuminate\Support\Facades\Log::info('Cleared old PO Deposit sync records', [
            'days_old' => $days,
            'records_cleared' => $clearedCount,
            'cleared_before' => $deleteBeforeDate->toISOString()
        ]);

        return response()->json([
            'code' => 200,
            'message' => "Successfully cleared sync status for {$clearedCount} old PO Deposit records older than {$days} days",
            'data' => [
                'records_cleared' => $clearedCount,
                'days_old' => $days
            ]
        ]);
    }
    
    /**
     * Convert ppn_type to company_id
     */
    private function getCompanyIdFromPpnType($ppnType)
    {
        $ppnTypeCompanyIdMap = [
            'ppn' => env('PPN_COMPANY_ID', 12),
            'non_ppn' => env('NON_PPN_COMPANY_ID', 1),
        ];
        
        return $ppnTypeCompanyIdMap[$ppnType] ?? $ppnTypeCompanyIdMap['ppn'];
    }
}
