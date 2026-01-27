<?php

namespace Src\Controllers;

use Illuminate\Support\Facades\Hash;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Src\Models\InvoiceProgress;
use Src\Models\PoDeposit;
use Src\Models\Project;
use Src\Models\User;
use Src\Models\Notification;

class InvoiceProgressController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.verify');
    }

   
    public function get($id)
    {
        $proj = Project::findOrFail($id);
        $items = InvoiceProgress::where('po_deposit', $proj['po_deposit'])->get();
        return response()->json(['code' => 200, 'data' => $items]);
    }

    public function store(Request $request)
    {
        $rules = [
            'project' => 'required|exists:projects,id',
            'pic' => 'required|string',
            'status' => 'required|string',
            'note' => 'nullable|string',

            'invoice_number' => 'nullable|string',
            'invoice_date' => 'nullable|date',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'errors' => $validator->errors()
            ]);
        }
        // try {
        $proj = Project::findOrFail($request->get('project'));

        $deposit = PoDeposit::findOrFail($proj['po_deposit']);

        $note = $request->get('note');
        if (!empty($request->get('invoice_number')) && !empty($request->get('invoice_date'))) {
            $inv = json_decode($deposit['invoices']) ?? [];
            array_push($inv, [
                "invoice_number" => $request->get('invoice_number'),
                "invoice_date" => $request->get('invoice_date'),
            ]);
            $deposit['invoices'] = json_encode($inv);
            $note = $request->get('note') . "\r\nInvoice : " . $request->get('invoice_number') . " (" . $request->get('invoice_date') . ")";
            
            $this->notify('Project Invoice Updated', 'Project #' . $proj['job_number'] . ' Invoice #' . $request->get('invoice_number'), 'finance', json_encode(["project" => $proj]));
        }
        $deposit['invoice_status'] = $request->get('status');
        $deposit->save();
        $user = InvoiceProgress::create([
            'po_deposit' => $proj['po_deposit'],
            'pic' => $request->get('pic'),
            'status' => $request->get('status'),
            'note' => $note,
        ]);

        return response()->json(['code' => 200, 'data' => $user]);
        // } catch (\Throwable $th) {
        //     return response()->json(['code' => 400, 'data' => $th]);
        // }
    }
}
