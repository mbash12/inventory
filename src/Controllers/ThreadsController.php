<?php

namespace Src\Controllers;

use Illuminate\Support\Facades\Hash;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Src\Models\Thread;
use Src\Models\Project;
use Src\Models\PoDeposit;
use Src\Models\User;
use Src\Models\Notification;
use Carbon\Carbon;

class ThreadsController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.verify');
    }
    public function show($id)
    {
        $items = Thread::where('project_id', $id)->orderBy('created_at', 'desc')->get();
        return response()->json(['code' => 200, 'data' => $items]);
    }
    public function index(Request $request)
    {
        $beginningOfLastMonth = Carbon::now()->startOfMonth()->subMonth();
        $endOfNextMonth = Carbon::now()->endOfMonth()->addMonth();

        // Fetch all deadline dates within the specified range from the three columns
        $projects = Project::select('production_deadline', 'delivery_deadline', 'po_deadline','do_deadline','design_deadline','bast_deadline','gr_deadline')
            ->where(function ($query) use ($beginningOfLastMonth, $endOfNextMonth) {
                $query->whereBetween('production_deadline', [$beginningOfLastMonth, $endOfNextMonth])
                    ->orWhereBetween('delivery_deadline', [$beginningOfLastMonth, $endOfNextMonth])
                    ->orWhereBetween('po_deadline', [$beginningOfLastMonth, $endOfNextMonth])
                    ->orWhereBetween('do_deadline', [$beginningOfLastMonth, $endOfNextMonth])
                    ->orWhereBetween('design_deadline', [$beginningOfLastMonth, $endOfNextMonth])
                    ->orWhereBetween('bast_deadline', [$beginningOfLastMonth, $endOfNextMonth])
                    ->orWhereBetween('gr_deadline', [$beginningOfLastMonth, $endOfNextMonth]);

            })
            ->where(function($query) {
                $query
                    ->where('client_po_number', null)
                    ->orWhere(function($q) {
                        $q->where('project_type', 'gimmick')
                          ->whereNull('do_files');
                    })
                    ->orWhere(function($q) {
                        $q->where('project_type', 'printing')
                          ->whereNull('do_files');
                    })
                    ->orWhere(function($q) {
                        $q->where('project_type', 'design')
                          ->whereNull('bast_files');
                    })
                    ->orWhere(function($q) {
                        $q->whereRaw("JSON_EXTRACT(documents, '$.gr') IS NULL OR JSON_EXTRACT(documents, '$.gr') = 'false'")
                          ->whereNull('gr_files');
                    });
            })
            ->where(function ($query) {
                $query->whereNull('invoice_status')
                    ->orWhere('invoice_status', 'new')
                    ->orWhere('invoice_status', 'progress');
            })
            ->get();

        // Initialize collections to store separated deadline counts
        $productionDeadlines = collect();
        $deliveryDeadlines = collect();
        $poDeadlines = collect();
        $doDeadlines = collect();
        $designDeadlines = collect();
        $bastDeadlines = collect();
        $grDeadlines = collect();

        // Loop through projects to collect deadlines into separate collections
        foreach ($projects as $project) {
            if ($project->production_deadline !== null && Carbon::parse($project->production_deadline)->between($beginningOfLastMonth, $endOfNextMonth)) {
                $productionDeadlines->add($project->production_deadline);
            }
            if ($project->delivery_deadline !== null && Carbon::parse($project->delivery_deadline)->between($beginningOfLastMonth, $endOfNextMonth)) {
                $deliveryDeadlines->add($project->delivery_deadline);
            }
            if ($project->po_deadline !== null && Carbon::parse($project->po_deadline)->between($beginningOfLastMonth, $endOfNextMonth)) {
                $poDeadlines->add($project->po_deadline);
            }
            if ($project->do_deadline !== null && Carbon::parse($project->do_deadline)->between($beginningOfLastMonth, $endOfNextMonth)) {
                $doDeadlines->add($project->do_deadline);
            }
            if ($project->design_deadline !== null && Carbon::parse($project->design_deadline)->between($beginningOfLastMonth, $endOfNextMonth)) {
                $designDeadlines->add($project->design_deadline);
            }
            if ($project->bast_deadline !== null && Carbon::parse($project->bast_deadline)->between($beginningOfLastMonth, $endOfNextMonth)) {
                $bastDeadlines->add($project->bast_deadline);
            }
            if ($project->gr_deadline !== null && Carbon::parse($project->gr_deadline)->between($beginningOfLastMonth, $endOfNextMonth)) {
                $grDeadlines->add($project->gr_deadline);
            }

        }

        // Count occurrences of each unique deadline date and format the result
        $productionCounts = $productionDeadlines->countBy()->map(function ($count, $date) {
            return ['deadline_date' => $date, 'count' => $count];
        })->values();
        $deliveryCounts = $deliveryDeadlines->countBy()->map(function ($count, $date) {
            return ['deadline_date' => $date, 'count' => $count];
        })->values();
        $poCounts = $poDeadlines->countBy()->map(function ($count, $date) {
            return ['deadline_date' => $date, 'count' => $count];
        })->values();

        $doCounts = $doDeadlines->countBy()->map(function ($count, $date) {
            return ['deadline_date' => $date, 'count' => $count];
        })->values();

        $designCounts = $designDeadlines->countBy()->map(function ($count, $date) {
            return ['deadline_date' => $date, 'count' => $count];
        })->values();

        $bastCounts = $bastDeadlines->countBy()->map(function ($count, $date) {
            return ['deadline_date' => $date, 'count' => $count];
        })->values();

        $grCounts = $grDeadlines->countBy()->map(function ($count, $date) {
            return ['deadline_date' => $date, 'count' => $count];
        })->values();


        // Combine separate deadline counts into a single response
        $deadlines = [
            'production' => $productionCounts,
            'delivery' => $deliveryCounts,
            'po' => $poCounts,
            'do' => $doCounts,
            'design' => $designCounts,
            'bast' => $bastCounts,
            'gr' => $grCounts
        ];

        return response()->json(['code' => 200, 'data' => $deadlines]);
    }

    private function updateProjectAndDepositOnly($proj, $deposit, $request, &$meta, &$deadlinemeta)
    {
        if (!empty($request->get('client_po_number')) && !empty($request->get('client_po_date'))) {
            $proj['client_po_number'] = $request->get('client_po_number');
            $proj['client_po_date'] = $request->get('client_po_date');

            $deposit['client_po_number'] = $request->get('client_po_number');
            $deposit['client_po_date'] = $request->get('client_po_date');

            $meta["client_po_number"] = $request->get('client_po_number');
            $meta["client_po_date"] = $request->get('client_po_date');
        }

        $reminder = null;
        $deadline = null;

        if (!empty($request->get('production_deadline'))) {
            if ($proj['status'] == "new") {
                $proj['status'] = "production";
            }
            $proj['production_deadline'] = $request->get('production_deadline');
            $reminder = date('Y-m-d', strtotime($request->get('production_deadline')));
            $deadline = $request->get('production_deadline');

            $meta["production_deadline"] = $request->get('production_deadline');
            $meta["production_deadline_alert"] = date('Y-m-d', strtotime($request->get('production_deadline')));
            $deadlinemeta['production_deadline'] = [
                "date" => $request->get('production_deadline'),
                "reminder" => $reminder
            ];
        }

        if (!empty($request->get('delivery_deadline'))) {
            $proj['delivery_deadline'] = $request->get('delivery_deadline');
            $reminder = date('Y-m-d', strtotime($request->get('delivery_deadline')));
            $deadline = $request->get('delivery_deadline');

            $meta["delivery_deadline"] = $request->get('delivery_deadline');
            $meta["delivery_deadline_alert"] = date('Y-m-d', strtotime($request->get('delivery_deadline')));
            $deadlinemeta['delivery_deadline'] = [
                "date" => $request->get('delivery_deadline'),
                "reminder" => $reminder
            ];
        }

        if (!empty($request->get('po_deadline'))) {
            $proj['po_deadline'] = $request->get('po_deadline');
            $reminder = date('Y-m-d', strtotime($request->get('po_deadline')));
            $deadline = $request->get('po_deadline');

            $meta["po_deadline"] = $request->get('po_deadline');
            $meta["po_deadline_alert"] = date('Y-m-d', strtotime($request->get('po_deadline')));
            $deadlinemeta['po_deadline'] = [
                "date" => $request->get('po_deadline'),
                "reminder" => $reminder
            ];
        }

        if (!empty($request->get('do_deadline'))) {
            $proj['do_deadline'] = $request->get('do_deadline');
            $reminder = date('Y-m-d', strtotime($request->get('do_deadline')));
            $deadline = $request->get('do_deadline');

            $meta["do_deadline"] = $request->get('do_deadline');
            $deadlinemeta['do_deadline'] = [
                "date" => $request->get('do_deadline'),
                "reminder" => $reminder
            ];
        }

        if (!empty($request->get('design_deadline'))) {
            $proj['design_deadline'] = $request->get('design_deadline');
            $reminder = date('Y-m-d', strtotime($request->get('design_deadline')));
            $deadline = $request->get('design_deadline');

            $meta["design_deadline"] = $request->get('design_deadline');
            $deadlinemeta['design_deadline'] = [
                "date" => $request->get('design_deadline'),
                "reminder" => $reminder
            ];
        }

        if (!empty($request->get('bast_deadline'))) {
            $proj['bast_deadline'] = $request->get('bast_deadline');
            $reminder = date('Y-m-d', strtotime($request->get('bast_deadline')));
            $deadline = $request->get('bast_deadline');

            $meta["bast_deadline"] = $request->get('bast_deadline');
            $deadlinemeta['bast_deadline'] = [
                "date" => $request->get('bast_deadline'),
                "reminder" => $reminder
            ];
        }

        if (!empty($request->get('gr_deadline'))) {
            $proj['gr_deadline'] = $request->get('gr_deadline');
            $reminder = date('Y-m-d', strtotime($request->get('gr_deadline')));
            $deadline = $request->get('gr_deadline');

            $meta["gr_deadline"] = $request->get('gr_deadline');
            $deadlinemeta['gr_deadline'] = [
                "date" => $request->get('gr_deadline'),
                "reminder" => $reminder
            ];
        }
    }

    private function updateProjectAndAllRelated($proj, $deposit, $relatedProjects, $request, &$meta, &$deadlinemeta)
    {
        if (!empty($request->get('invoice_number')) && !empty($request->get('invoice_date')) && !empty($request->get('invoiced_amount'))) {
            // Update Project invoice data
            $inv = json_decode($proj['invoices'] ?? "[]", true);
            array_push($inv, [
                "invoice_number" => $request->get('invoice_number'),
                "invoice_date" => $request->get('invoice_date'),
                "invoice_amount" => $request->get('invoiced_amount'),
            ]);
            $proj['invoiced_amount'] = ($proj['invoiced_amount'] + $request->get('invoiced_amount'));
            $proj['remaining_amount'] = $proj['total_price'] - $proj['invoiced_amount'];
            $proj['invoices'] = json_encode($inv);

            // Set invoice status to progress if not set
            if (empty($request->get('invoice_status'))) {
                $proj['invoice_status'] = 'progress';
                $deposit['invoice_status'] = 'progress';
                if (!$deposit['is_po_deposit']) {
                    foreach ($relatedProjects as $relatedProj) {
                        $relatedProj['invoice_status'] = 'progress';
                        $relatedProj->save();
                    }
                }
                $meta["invoice_status"] = 'progress';
            }

            // Update PoDeposit invoice data
            $deposit_inv = json_decode($deposit['invoices'] ?? "[]", true);
            array_push($deposit_inv, [
                "invoice_number" => $request->get('invoice_number'),
                "invoice_date" => $request->get('invoice_date'),
                "invoice_amount" => $request->get('invoiced_amount'),
            ]);
            $deposit['invoices'] = json_encode($deposit_inv);

            // Update related projects if not a deposit
            if (!$deposit['is_po_deposit']) {
                foreach ($relatedProjects as $relatedProj) {
                    $relatedInv = json_decode($relatedProj['invoices'] ?? "[]", true);
                    array_push($relatedInv, [
                        "invoice_number" => $request->get('invoice_number'),
                        "invoice_date" => $request->get('invoice_date'),
                        "invoice_amount" => $request->get('invoiced_amount'),
                    ]);
                    $relatedProj['invoiced_amount'] = ($relatedProj['invoiced_amount'] + $request->get('invoiced_amount'));
                    $relatedProj['remaining_amount'] = $relatedProj['total_price'] - $relatedProj['invoiced_amount'];
                    $relatedProj['invoices'] = json_encode($relatedInv);
                    $relatedProj->save();
                }
            }

            $meta["invoice_number"] = $request->get('invoice_number');
            $meta["invoice_date"] = $request->get('invoice_date');
            $meta["invoiced_amount"] = $request->get('invoiced_amount');
            $meta["remaining_amount"] = $proj['remaining_amount'];
            $meta["total_price"] = $proj['total_price'];
        }

        if (!empty($request->get('invoice_number')) && !empty($request->get('delete_invoice'))) {
            $invoiceNumber = $request->get('invoice_number');
            
            // Update Project invoice data
            $inv = json_decode($proj['invoices'] ?? "[]", true);
            $invoiceDetails = array_values(array_filter($inv, function ($item) use ($invoiceNumber) {
                return $item['invoice_number'] === $invoiceNumber;
            }));

            $inv_amount = (!empty($invoiceDetails) && isset($invoiceDetails[0]['invoice_amount'])) ? $invoiceDetails[0]['invoice_amount'] : 0;

            $proj['invoiced_amount'] = $proj['invoiced_amount'] - $inv_amount;
            $proj['remaining_amount'] = $proj['total_price'] - $proj['invoiced_amount'];

            $inv = array_filter($inv, function ($invoice) use ($invoiceNumber) {
                return $invoice['invoice_number'] !== $invoiceNumber;
            });
            $proj['invoices'] = json_encode($inv);

            // Update PoDeposit invoice data
            $deposit_inv = json_decode($deposit['invoices'] ?? "[]", true);
            $deposit_inv = array_filter($deposit_inv, function ($invoice) use ($invoiceNumber) {
                return $invoice['invoice_number'] !== $invoiceNumber;
            });
            $deposit['invoices'] = json_encode($deposit_inv);

            // Update related projects if not a deposit
            if (!$deposit['is_po_deposit']) {
                foreach ($relatedProjects as $relatedProj) {
                    $relatedInv = json_decode($relatedProj['invoices'] ?? "[]", true);
                    $relatedInv = array_filter($relatedInv, function ($invoice) use ($invoiceNumber) {
                        return $invoice['invoice_number'] !== $invoiceNumber;
                    });
                    $relatedProj['invoiced_amount'] = $relatedProj['invoiced_amount'] - $inv_amount;
                    $relatedProj['remaining_amount'] = $relatedProj['total_price'] - $relatedProj['invoiced_amount'];
                    $relatedProj['invoices'] = json_encode($relatedInv);
                    $relatedProj->save();
                }
            }

            $meta["delete_invoice"] = TRUE;
            $meta["invoice_number"] = $request->get('invoice_number');
            $meta["invoiced_amount"] = $inv_amount;
            $meta["remaining_amount"] = $proj['remaining_amount'];
            $meta["total_price"] = $proj['total_price'];
        }

        if (!empty($request->get('invoice_pic'))) {
            $proj['invoice_pic'] = $request->get('invoice_pic');
            $deposit['invoice_pic'] = $request->get('invoice_pic');
            if (!$deposit['is_po_deposit']) {
                foreach ($relatedProjects as $relatedProj) {
                    $relatedProj['invoice_pic'] = $request->get('invoice_pic');
                    $relatedProj->save();
                }
            }
            $meta["invoice_pic"] = $request->get('invoice_pic');
        }
        if (!empty($request->get('invoice_status'))) {
            $proj['invoice_status'] = $request->get('invoice_status');
            $deposit['invoice_status'] = $request->get('invoice_status');
            if (!$deposit['is_po_deposit']) {
                foreach ($relatedProjects as $relatedProj) {
                    $relatedProj['invoice_status'] = $request->get('invoice_status');
                    $relatedProj->save();
                }
            }
            $meta["invoice_status"] = $request->get('invoice_status');
        }
    }

    public function store(Request $request)
    {
        $rules = [
            'project' => 'required|exists:projects,id',
            'note' => 'nullable|string',
            'thread_type' => 'required|string',

            'production_deadline' => 'nullable|date',
            'delivery_deadline' => 'nullable|date',
            'po_deadline' => 'nullable|date',

            'invoice_number' => 'nullable|string',
            'invoice_date' => 'nullable|date',
            'invoice_pic' => 'nullable|string',
            'invoice_status' => 'nullable|string',

            'client_po_number' => 'nullable|string',
            'client_po_date' => 'nullable|date',

        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'errors' => $validator->errors()
            ]);
        }
        try {
            $note = $request->get('note') . "\r\n";
            $meta = [];

            $proj = Project::findOrFail($request->get('project'));
            $deposit = PoDeposit::findOrFail($proj['po_deposit']);

            // Get related projects if po_deposit is not marked as deposit
            $relatedProjects = [];
            if (!$deposit['is_po_deposit']) {
                $relatedProjects = Project::where('po_deposit', $proj['po_deposit'])
                    ->where('id', '!=', $proj['id'])
                    ->get();
            }

            $deadlinemeta = json_decode($proj['deadline_meta'], true) ?? [];
            $reminder = null;
            $deadline = null;

            // Invoice-related actions (affect all related projects)
            if (
                (!empty($request->get('invoice_number')) && !empty($request->get('invoice_date')) && !empty($request->get('invoiced_amount')))
                || (!empty($request->get('invoice_number')) && !empty($request->get('delete_invoice')))
                || !empty($request->get('invoice_pic'))
                || !empty($request->get('invoice_status'))
            ) {
                $this->updateProjectAndAllRelated($proj, $deposit, $relatedProjects, $request, $meta, $deadlinemeta);
            } else {
                // All other actions (affect only main project and deposit)
                $this->updateProjectAndDepositOnly($proj, $deposit, $request, $meta, $deadlinemeta);
            }

            if ($request->get('thread_type') == 'po') {
                notify('Project PO Updated', 'Project #' . $proj['job_number'] . ' thread updated', 'marketing', json_encode(["project" => $proj]), "threads");
                $deadlinemeta['po_deadline']['notes'] = $note;
                $deadlinemeta['po_deadline']['updated_at'] = date('Y-m-d H:i:s');
            }
            if ($request->get('thread_type') == 'invoice') {
                notify('Project Invoice Updated', 'Project #' . $proj['job_number'] . ' thread updated', 'finance', json_encode(["project" => $proj]), "threads");
                $deadlinemeta['invoice_deadline']['notes'] = $note;
                $deadlinemeta['invoice_deadline']['updated_at'] = date('Y-m-d H:i:s');
            }
            if ($request->get('thread_type') == 'do') {
                notify('Project DO Updated', 'Project #' . $proj['job_number'] . ' thread updated', 'delivery', json_encode(["project" => $proj]), "threads");
                $deadlinemeta['do_deadline']['notes'] = $note;
                $deadlinemeta['do_deadline']['updated_at'] = date('Y-m-d H:i:s');
            }
            if ($request->get('thread_type') == 'design') {
                notify('Project Design Updated', 'Project #' . $proj['job_number'] . ' thread updated', 'design', json_encode(["project" => $proj]), "threads");
                $deadlinemeta['design_deadline']['notes'] = $note;
                $deadlinemeta['design_deadline']['updated_at'] = date('Y-m-d H:i:s');
            }
            if ($request->get('thread_type') == 'bast') {
                notify('Project BAST Updated', 'Project #' . $proj['job_number'] . ' thread updated', 'design', json_encode(["project" => $proj]), "threads");
                $deadlinemeta['bast_deadline']['notes'] = $note;
                $deadlinemeta['bast_deadline']['updated_at'] = date('Y-m-d H:i:s');
            }
            if ($request->get('thread_type') == 'gr') {
                notify('Project GR Updated', 'Project #' . $proj['job_number'] . ' thread updated', 'design', json_encode(["project" => $proj]), "threads");
                $deadlinemeta['gr_deadline']['notes'] = $note;
                $deadlinemeta['gr_deadline']['updated_at'] = date('Y-m-d H:i:s');
            }

            if ($request->get('thread_type') == 'logistic') {
                notify('Project Logistic Updated', 'Project #' . $proj['job_number'] . ' thread updated', 'delivery', json_encode(["project" => $proj]), "threads");
                if (!empty($request->get('delivery_deadline'))) {
                    $deadlinemeta['delivery_deadline']['notes'] = $note;
                    $deadlinemeta['delivery_deadline']['updated_at'] = date('Y-m-d H:i:s');
                }
                if (!empty($request->get('production_deadline'))) {
                    $deadlinemeta['production_deadline']['notes'] = $note;
                    $deadlinemeta['production_deadline']['updated_at'] = date('Y-m-d H:i:s');
                }
            }

            $follupdata =[
                'project_id' => $proj['id'],
                'type' => $request->get('thread_type'),
                'notes' => $note,
                "reminder" => $reminder,
                "deadline" => $deadline,
                'meta_data' => json_encode($meta),
                "user_id" => auth()->user()->id,
            ];

            if(!empty($request->get('cancel_files'))){
                $proj['status'] = 'cancel';
                $follupdata['files'] = json_encode($request->get('cancel_files'));
                $follupdata['notes'] = 'Project cancelled';
                $follupdata['meta_data'] = json_encode(["Project Cancelled"]);
                $deposit['status'] = 'cancel';
                // Update related projects status if not a deposit
                if (!$deposit['is_po_deposit']) {
                    foreach ($relatedProjects as $relatedProj) {
                        $relatedProj['status'] = 'cancel';
                        $relatedProj->save();
                    }
                }
            }

            $proj['deadline_meta'] = json_encode($deadlinemeta);

            $proj->save();
            $deposit->save();

            $followup = Thread::create($follupdata);

            return response()->json(['code' => 200, 'data' => $followup]);
        } catch (\Throwable $th) {
            return response()->json(['code' => 400, 'data' => $th]);
        }
    }
}
