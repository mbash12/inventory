<?php

namespace Src\Controllers;

use Illuminate\Support\Facades\Hash;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Src\Models\PoDeposit;
use Src\Models\Project;
use Src\Models\Product;
use Src\Models\Delivery;
use Src\Models\User;
use Src\Models\Notification;
use Src\Models\InvoiceProgress;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ReportController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('jwt.verify');
    // }
    public function index(Request $request)
    {
        try {
            // Get year filter from request if provided
            $year = $request->input('year', date('Y'));
            
            // Base queries for different report sections
            $poDepositQuery = PoDeposit::query()->where('status', '!=', 'cancel');
            $projectQuery = Project::query()->where('status', '!=', 'cancel');
            
            // Apply year filter
            $poDepositQuery->whereYear('client_po_date', $year);
            $projectQuery->whereYear('client_po_date', $year);
            
            // 1. Grand total PO by month
            $grandTotalByMonth = DB::table(
                DB::raw('(SELECT 1 AS month UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) AS months')
            )
                ->leftJoinSub($poDepositQuery->clone()
                    ->selectRaw('MONTH(client_po_date) as month, SUM(total_price) as total')
                    ->groupBy('month'),
                    'po_data',
                    'months.month',
                    '=',
                    'po_data.month'
                )
                ->selectRaw('months.month, COALESCE(po_data.total, 0) as total')
                ->orderBy('months.month')
                ->get()
                ->pluck('total', 'month');
            
            // 2. Top 5 clients by total price
            $top5Clients = $poDepositQuery->clone()
                ->selectRaw('client_company, SUM(total_price) as total')
                ->groupBy('client_company')
                ->orderByDesc('total')
                ->limit(5)
                ->get();
            
            // 3. PO deposits with is_deposit true by month
            $depositTrueByMonth = DB::table(
                DB::raw('(SELECT 1 AS month UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) AS months')
            )
                ->leftJoinSub($poDepositQuery->clone()
                    ->where('is_po_deposit', true)
                    ->selectRaw('MONTH(client_po_date) as month, SUM(total_price) as total')
                    ->groupBy('month'),
                    'po_data',
                    'months.month',
                    '=',
                    'po_data.month'
                )
                ->selectRaw('months.month, COALESCE(po_data.total, 0) as total')
                ->orderBy('months.month')
                ->get()
                ->pluck('total', 'month');
            
            // 4. PO deposits with is_deposit false by month
            $depositFalseByMonth = DB::table(
                DB::raw('(SELECT 1 AS month UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) AS months')
            )
                ->leftJoinSub($poDepositQuery->clone()
                    ->where('is_po_deposit', false)
                    ->selectRaw('MONTH(client_po_date) as month, SUM(total_price) as total')
                    ->groupBy('month'),
                    'po_data',
                    'months.month',
                    '=',
                    'po_data.month'
                )
                ->selectRaw('months.month, COALESCE(po_data.total, 0) as total')
                ->orderBy('months.month')
                ->get()
                ->pluck('total', 'month');
            
            // 5. PO deposits with is_deposit true and invoice_status not sent
            $depositTrueNotSentByMonth = DB::table(
                DB::raw('(SELECT 1 AS month UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) AS months')
            )
                ->leftJoinSub($poDepositQuery->clone()
                    ->where('is_po_deposit', true)
                    ->whereIn('invoice_status', ['progress', 'new'])
                    ->selectRaw('MONTH(client_po_date) as month, SUM(total_price) as total')
                    ->groupBy('month'),
                    'po_data',
                    'months.month',
                    '=',
                    'po_data.month'
                )
                ->selectRaw('months.month, COALESCE(po_data.total, 0) as total')
                ->orderBy('months.month')
                ->get()
                ->pluck('total', 'month');
            
            // 6. PO deposits with is_deposit false and invoice_status not sent
            $depositFalseNotSentByMonth = DB::table(
                DB::raw('(SELECT 1 AS month UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) AS months')
            )
                ->leftJoinSub($poDepositQuery->clone()
                    ->where('is_po_deposit', false)
                    ->whereIn('invoice_status', ['progress', 'new'])
                    ->whereNotNull('client_po_number')
                    ->selectRaw('MONTH(client_po_date) as month, SUM(total_price) as total')
                    ->groupBy('month'),
                    'po_data',
                    'months.month',
                    '=',
                    'po_data.month'
                )
                ->selectRaw('months.month, COALESCE(po_data.total, 0) as total')
                ->orderBy('months.month')
                ->get()
                ->pluck('total', 'month');
            
            // 7. Projects with status new by month
            $newProjectsByMonth = DB::table(
                DB::raw('(SELECT 1 AS month UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) AS months')
            )
                ->leftJoinSub($projectQuery->clone()
                    ->where('status', 'new')
                    ->selectRaw('MONTH(client_po_date) as month, SUM(total_price) as total')
                    ->groupBy('month'),
                    'project_data',
                    'months.month',
                    '=',
                    'project_data.month'
                )
                ->selectRaw('months.month, COALESCE(project_data.total, 0) as total')
                ->orderBy('months.month')
                ->get()
                ->pluck('total', 'month');
            
            // 8. Projects with status production by month
            $productionProjectsByMonth = DB::table(
                DB::raw('(SELECT 1 AS month UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) AS months')
            )
                ->leftJoinSub($projectQuery->clone()
                    ->where('status', 'production')
                    ->selectRaw('MONTH(client_po_date) as month, SUM(total_price) as total')
                    ->groupBy('month'),
                    'project_data',
                    'months.month',
                    '=',
                    'project_data.month'
                )
                ->selectRaw('months.month, COALESCE(project_data.total, 0) as total')
                ->orderBy('months.month')
                ->get()
                ->pluck('total', 'month');

            $readyProjectsByMonth = DB::table(
                DB::raw('(SELECT 1 AS month UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) AS months')
            )
                ->leftJoinSub($projectQuery->clone()
                    ->where('status', 'ready')
                    ->selectRaw('MONTH(client_po_date) as month, SUM(total_price) as total')
                    ->groupBy('month'),
                    'project_data',
                    'months.month',
                    '=',
                    'project_data.month'
                )
                ->selectRaw('months.month, COALESCE(project_data.total, 0) as total')
                ->orderBy('months.month')
                ->get()
                ->pluck('total', 'month');
            
            // 9. Projects with status partial by month
            $partialProjectsByMonth = DB::table(
                DB::raw('(SELECT 1 AS month UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) AS months')
            )
                ->leftJoinSub($projectQuery->clone()
                    ->where('status', 'partial')
                    ->selectRaw('MONTH(client_po_date) as month, SUM(total_price) as total')
                    ->groupBy('month'),
                    'project_data',
                    'months.month',
                    '=',
                    'project_data.month'
                )
                ->selectRaw('months.month, COALESCE(project_data.total, 0) as total')
                ->orderBy('months.month')
                ->get()
                ->pluck('total', 'month');
            
            // 10. Remaining amount of PO deposits by month
            $remainingAmountByMonth = DB::table(
                DB::raw('(SELECT 1 AS month UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) AS months')
            )
                ->leftJoinSub($poDepositQuery->clone()
                    ->selectRaw('MONTH(client_po_date) as month, SUM(balance) as total')
                    ->groupBy('month'),
                    'po_data',
                    'months.month',
                    '=',
                    'po_data.month'
                )
                ->selectRaw('months.month, COALESCE(po_data.total, 0) as total')
                ->orderBy('months.month')
                ->get()
                ->pluck('total', 'month');
            
            // 11. Projects with null client_po_number by month
            $nullPoNumberByMonth = DB::table(
                DB::raw('(SELECT 1 AS month UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) AS months')
            )
                ->leftJoinSub($projectQuery->clone()
                    ->whereNull('client_po_number')
                    ->selectRaw('MONTH(client_po_date) as month, SUM(total_price) as total')
                    ->groupBy('month'),
                    'project_data',
                    'months.month',
                    '=',
                    'project_data.month'
                )
                ->selectRaw('months.month, COALESCE(project_data.total, 0) as total')
                ->orderBy('months.month')
                ->get()
                ->pluck('total', 'month');
            
            // 12. PO breakdown by month
            $poBreakdown = DB::table('po_deposits')
            ->select('client_company')
            ->selectRaw('SUM(CASE WHEN invoice_status IN ("progress", "new") THEN total_price ELSE 0 END) as not_issued')
            ->selectRaw('SUM(CASE WHEN invoice_status = "sent" THEN total_price ELSE 0 END) as issued')
            ->selectRaw('SUM(total_price) as total')
            ->whereYear('client_po_date', $year)
            ->where('status', '!=', 'cancel')
            ->whereNotNull('client_po_number')
            ->groupBy('client_company')
            ->havingRaw('SUM(total_price) > 0')
            ->orderByRaw('SUM(total_price) DESC')
            ->get();
            
            // Prepare response data
            $results = [
                'grand_total_by_month' => $grandTotalByMonth,
                'top_5_clients' => $top5Clients,
                'deposit_true_by_month' => $depositTrueByMonth,
                'deposit_false_by_month' => $depositFalseByMonth,
                'deposit_true_not_sent_by_month' => $depositTrueNotSentByMonth,
                'deposit_false_not_sent_by_month' => $depositFalseNotSentByMonth,
                'new_projects_by_month' => $newProjectsByMonth,
                'production_projects_by_month' => $productionProjectsByMonth,
                'ready_projects_by_month' => $readyProjectsByMonth,
                'partial_projects_by_month' => $partialProjectsByMonth,
                'remaining_amount_by_month' => $remainingAmountByMonth,
                'null_po_number_by_month' => $nullPoNumberByMonth,
                'po_breakdown' => $poBreakdown
            ];
            
            return response()->json([
                'code' => 200,
                'data' => $results
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'code' => 500, 
                'message' => 'An error occurred while generating the report',
                'error' => $th->getMessage()
            ]);
        }
    }
}
