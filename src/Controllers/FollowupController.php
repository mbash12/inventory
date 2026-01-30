<?php

namespace Src\Controllers;

use Illuminate\Support\Facades\Hash;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Src\Models\MarketingFollowup;
use Src\Models\Project;
use Src\Models\PoDeposit;
use Src\Models\User;
use Src\Models\Notification;

class FollowupController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.verify');
    }
    public function get($id)
    {
        $proj = Project::findOrFail($id);
        $items = MarketingFollowup::where('po_deposit', $proj['po_deposit'])->get();
        return response()->json(['code' => 200, 'data' => $items]);
    }

    public function store(Request $request)
    {
        $rules = [
            'project' => 'required|exists:projects,id',
            'schedule_date' => 'required|date',
            'note' => 'nullable|string',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'errors' => $validator->errors()
            ]);
        }
        try {
            $proj = Project::findOrFail($request->get('project'));
            $deposit = PoDeposit::findOrFail($proj['po_deposit']);
            
            if (!empty($request->get('client_po_number')) && !empty($request->get('client_po_date'))) {
                $proj['client_po_number'] = $request->get('client_po_number');
                $proj['client_po_date'] = $request->get('client_po_date');

                $deposit['client_po_number'] = $request->get('client_po_number');
                $deposit['client_po_date'] = $request->get('client_po_date');

                notify('Project PO Updated', 'Project #' . $proj['job_number'] . ' PO #'.$proj['client_po_number'], 'marketing', json_encode(["project" => $proj]), 'followup');
                $proj->save();
                $deposit->save();
                $followup = MarketingFollowup::create([
                    'po_deposit' => $proj['po_deposit'],
                    'schedule_date' => $request->get('schedule_date'),
                    'followup_date' => date("Y-m-d"),
                    'note' => $request->get('note'),
                ]);
            }else{
                $followup = MarketingFollowup::create([
                    'po_deposit' => $proj['po_deposit'],
                    'schedule_date' => $request->get('schedule_date'),
                    'note' => $request->get('note'),
                ]);
            }


            return response()->json(['code' => 200, 'data' => $followup]);
        } catch (\Throwable $th) {
            return response()->json(['code' => 400, 'data' => $th]);
        }
    }

    public function update(Request $request)
    {
        $rules = [
            'project' => 'required|exists:projects,id',
            'id' => 'required||exists:marketing_followups,id',
            'note' => 'nullable|string',

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
        $proj = Project::findOrFail($request->get('project'));
        $deposit = PoDeposit::findOrFail($proj['po_deposit']);

        if (!empty($request->get('client_po_number')) && !empty($request->get('client_po_date'))) {
            $proj['client_po_number'] = $request->get('client_po_number');
            $proj['client_po_date'] = $request->get('client_po_date');

            $deposit['client_po_number'] = $request->get('client_po_number');
            $deposit['client_po_date'] = $request->get('client_po_date');

            notify('Project PO Updated', 'Project #' . $proj['job_number'] . ' PO #'.$proj['client_po_number'], 'marketing', json_encode(["project" => $proj]), 'followup');
        }

        $proj->save();
        $deposit->save();


        $followup = MarketingFollowup::findOrFail($request->get('id'));

        $followup['followup_date'] =  date('Y-m-d');
        $followup['note'] =  $request->get('note');

        $followup->save();


        return response()->json(['code' => 200, 'data' => $followup]);
        } catch (\Throwable $th) {
            return response()->json(['code' => 400, 'data' => $th]);
        }
    }
}
