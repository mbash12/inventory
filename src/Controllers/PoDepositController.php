<?php

namespace Src\Controllers;

use Illuminate\Support\Facades\Hash;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Src\Models\InvoiceProgress;
use Src\Models\MarketingFollowup;
use Src\Models\PoDeposit;
use Src\Models\Project;
use Src\Models\Product;
use Src\Models\User;
use Src\Models\Notification;
use Src\Models\Thread;
use Src\Models\Inventory;
use Src\Models\Warehouse;

class PoDepositController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('jwt.verify');
    // }

    public function migrate()
    {
        $deps = PoDeposit::get();
        $res = [
            "prj" => [],
            "trd" => []
        ];
        foreach ($deps as $key => $dep) {
            $proj = Project::where('po_deposit', $dep->id)->first();
            if ($proj) {
                if (($proj->is_real && !$proj->is_po_deposit) || ($proj->is_po_deposit && !$proj->is_real)) {
                    $prg = InvoiceProgress::where('po_deposit', $dep->id)->latest()->first();
                    $proj->invoices = $dep->invoices;
                    $proj->invoice_status = $dep->invoice_status;
                    if ($prg) {
                        $proj->invoice_pic = $prg->pic;
                    }
                    $proj->invoiced_amount = 0;
                    $proj->remaining_amount = $proj->total_price;
                    array_push($res['prj'], $proj);
                    $proj->save();
                }
                $prgs = InvoiceProgress::where('po_deposit', $dep->id)->get();
                foreach ($prgs as $key => $prgg) {
                    $thrd = new Thread();
                    $thrd->project_id = $proj->id;
                    $thrd->type = 'invoice';
                    $thrd->notes = $prgg->note;
                    $thrd->created_at = $prgg->created_at;
                    $thrd->updated_at = $prgg->updated_at;
                    $thrd->save();
                    array_push($res['trd'], $thrd);
                }
                $upds = MarketingFollowup::where('po_deposit', $dep->id)->get();
                foreach ($upds as $key => $upd) {
                    $thrd = new Thread();
                    $thrd->project_id = $proj->id;
                    $thrd->type = 'po';
                    $thrd->notes = $upd->note;
                    $thrd->created_at = $prgg->created_at;
                    $thrd->updated_at = $prgg->updated_at;
                    array_push($res['trd'], $thrd);
                    $thrd->save();
                }
            }
        }
        return response()->json(['code' => 200, 'data' => "success"]);
    }
    // public function migrate()
    // {
    //     $projects = Project::whereNull('po_deposit')->get();
    //     foreach ($projects as $key => $project) {
    //         $data = [
    //             "job_number" => $project->job_number,
    //             "client_po_date" => $project->client_po_date,
    //             "client_po_number" => $project->client_po_number,
    //             "client_company" => $project->client_company,
    //             "client_pic_name" => $project->client_pic_name,
    //             "pic_name" => $project->pic_name,
    //             "status" => "open",
    //             "is_po_deposit" => false,
    //         ];
    //         $po_deposit = PoDeposit::create($data);
    //         $project->update([
    //             "po_deposit" => $po_deposit->id
    //         ]);
    //     }
    // }
    private function createProject($request, $podeposit, $real = false, $is_po_deposit)
    {
        $list = $request->po_deposits;
        if ($real) $list = $request->products_group;
        
        foreach ($list as $key => $projectData) {
            if ($real) {
                $deposit = Project::where('client_po_number', $projectData['client_po_number'])->where('is_po_deposit', true)->where('is_real', false)->first();
                if ($deposit) {
                    $deposit_id = $deposit->id;
                }
            } else {
                $deposit_id = null;
            }
            $project = new Project();

            $project->title = $projectData['title'] ?? null;
            $project->job_number = $projectData['job_number'];
            $project->client_po_date = $projectData['client_po_date'] ?? ($deposit ? $deposit->client_po_date : null);
            $project->client_po_number = $projectData['client_po_number'];
            $project->client_pic_name = $real ? $podeposit->client_pic_name : $projectData['client_pic_name'];
            $project->status = $projectData['status'] ?? 'new';
            $project->invoice_status = 'new';
            $project->invoices = $podeposit->invoices;

            $project->pic_name = $podeposit->pic_name;
            $project->client_company = $podeposit->client_company;
            $project->client_code = $podeposit->client_code;
            $project->po_deposit = $podeposit->id;
            $project->is_po_deposit = $is_po_deposit;
            $project->is_real = $real;
            $project->sent_to_del_at = $projectData['sent_to_del_at'] ?? null;
            
            $project->total_price = $projectData['total_price'] ?? null;
            if($is_po_deposit){
                $project->invoiced_amount = $projectData['invoiced_amount'] ?? 0;
                $project->remaining_amount = $projectData['total_price'] - ($projectData['invoiced_amount'] ?? 0);
            }

            $project->deposit_id = $deposit_id ?? null;

            $project->project_type = $projectData['project_type'] ?? 'gimmick';
            $project->bast_files = $projectData['bast_files'] ?? null;
            $project->gr_files = $projectData['gr_files'] ?? null;
            $project->do_files = $projectData['do_files'] ?? null;

            $project->documents = json_encode($projectData['documents']) ?? null;
            $project->save();

            $products = [];
            foreach ($projectData['products'] as $productData) {
                // Ensure quantity is set (default to 1 if not provided)
                $productData['quantity'] = $productData['quantity'] ?? 1;
                $product = new Product($productData);
                $products[] = $product;
            }
            $productss = $project->products_data()->saveMany($products);
        }
    }
    private function updateProject($request, $podeposit, $real = false, $is_po_deposit, $status)
    {
        $existingProjects = $podeposit->projects_data()->get();
        $list = $request->po_deposits;
        if ($real) $list = $request->products_group;
        foreach ($list as $key => $projectData) {
            $project = new Project();
            if (!empty($projectData['id'])) {
                $project = Project::findOrFail($projectData['id']);
            }
            if ($real) {
                $deposit = Project::where('client_po_number', $projectData['client_po_number'])->where('is_po_deposit', true)->where('is_real', false)->first();
                if ($deposit) {
                    $deposit_id = $deposit->id;
                }
            } else {
                $deposit_id = null;
            }
            $project->title = $projectData['title'] ?? null;
            $project->job_number =  $projectData['job_number'];
            $project->client_po_date = $projectData['client_po_date'] ?? ($deposit ? $deposit->client_po_date : null);
            $project->client_po_number = $projectData['client_po_number'];
            $project->client_pic_name = $real ? $podeposit->client_pic_name : $projectData['client_pic_name'];
            
            if($status == 'cancel'){
                $project->status = 'cancel';
            }else{
                $project->status = $projectData['status'] ?? 'new';
            }


            $project->pic_name = $podeposit->pic_name;
            $project->client_company = $podeposit->client_company;
            $project->client_code = $podeposit->client_code;
            $project->po_deposit = $podeposit->id;
            $project->is_po_deposit = $is_po_deposit;
            $project->is_real = $real;
            $project->sent_to_del_at = $projectData['sent_to_del_at'] ?? null;
            $project->total_price = $projectData['total_price'] ?? null;
            if($is_po_deposit){
                $project->invoiced_amount = $projectData['invoiced_amount'] ?? 0;
                $project->remaining_amount = $projectData['total_price'] - ($projectData['invoiced_amount'] ?? 0);
            }

            $project->deposit_id = $deposit_id ?? null;
            $project->invoice_status = $projectData['invoice_status'] ?? 'new';
            $project->invoices = $podeposit->invoices;



            $project->project_type = $projectData['project_type'] ?? 'gimmick';
            $project->bast_files = $projectData['bast_files'] ?? null;
            $project->gr_files = $projectData['gr_files'] ?? null;
            $project->do_files = $projectData['do_files'] ?? null;

            $project->documents = json_encode($projectData['documents']) ?? null;
            
            $project->save();



            $existingProducts = $project->products_data()->get();

            foreach ($projectData['products'] as $childData) {
                // Ensure quantity is set (default to 1 if not provided)
                $childData['quantity'] = $childData['quantity'] ?? 1;
                if (isset($childData['id'])) {
                    $existingProduct = $existingProducts->where('id', $childData['id'])->first();
                    if ($existingProduct) {
                        $existingProduct->update($childData);
                    }
                } else {
                    $project->products_data()->create($childData);
                }
            }
            $missingChildren = $existingProducts->whereNotIn('id', collect($projectData['products'])->pluck('id'));
            foreach ($missingChildren as $missingChild) {
                $missingChild->delete();
            }

            // Recalculate inventory for this project if it has existing inventory records
            $this->recalculateInventoryForSingleProject($project->id);
        }
        $missingProjects = $existingProjects->where("is_real", $real ? 1 : 0)->whereNotIn('id', collect($list)->pluck('id'));
        foreach ($missingProjects as $missingProject) {
            $missingProject->delete();
        }
    }
    public function index(Request $request)
    {
        try {
            $query = PoDeposit::query();
            // $query->with('projects_data','projects_data.products_data');
            
            // Only show PO deposits (not regular projects)
            $query->where('is_po_deposit', true);
            
            // Filter by has_client_code
            if ($request->filled('has_client_code')) {
                $query->whereNotNull('client_code')->where('client_code', '!=', '');
            }
            
            if ($request->has('status')) {
                $statuses = explode(',', $request->input('status'));
                $query->whereIn('status', $statuses);
            }
            if ($request->has('start_date') && $request->has('end_date')) {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
                if ($startDate === $endDate) {
                    $query->whereDate('client_po_date', $startDate);
                } else {
                    $query->whereBetween('client_po_date', [$startDate, $endDate]);
                }
            }
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($query0) use ($search) {
                    $query0->where('job_number', 'like', '%' . $search . '%')
                        ->orWhere('client_po_number', 'like', '%' . $search . '%')
                        ->orWhere('client_company', 'like', '%' . $search . '%')
                        ->orWhere('client_pic_name', 'like', '%' . $search . '%');
                });
            }
            $query->orderBy($request->input('order_by', 'id'), $request->input('sort', 'desc'));

            $totalRecords = $query->count();
            $limit = (int) $request->input('limit', 10);
            $currentPage = (int) $request->input('page', 1);
            $offset = ($currentPage - 1) * $limit;

            $query->offset($offset)->limit($limit);

            $results = $query->get();
            
            // Add last sync status
            $results->each(function($poDeposit) {
                $poDeposit->last_sync = \Src\Models\SyncJob::where('po_deposit_id', $poDeposit->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
            });

            return response()->json([
                'code' => 200,
                'data' => $results,
                'meta' => [
                    'total_records' => $totalRecords,
                    'total_pages' => ceil($totalRecords / $limit),
                    'current_page' => $currentPage,
                    'limit_per_page' => $limit,
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json(['code' => 404, 'data' => []]);
        }
    }
    public function store(Request $request)
    {
        // return response()->json(['code' => 404, 'data' => json_encode($request->all())]);
        $rules = [
            'job_number' => 'required|unique:po_deposits',
            'pic_name' => 'string',
            'client_po_number' => 'nullable|string',
            'client_po_date' => 'nullable|date',
            'client_company' => 'required|string',
            'client_pic_name' => 'required|string',
            // 'budget' => 'required',
            // 'expense' => 'required',
            // 'balance' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'errors' => $validator->errors()
            ]);
        }

        // try {
            $purchase_orders = [];
            if($request->is_po_deposit){
                foreach ($request->po_deposits as $key => $po) {
                    array_push($purchase_orders, [
                        "client_po_number" => $po['client_po_number'],
                        "client_po_date" => $po['client_po_date'],
                        "client_pic_name" => $po['client_pic_name'],
                        "total_price" => $po['total_price']
                    ]);
                }
            }
            
            $podeposit = PoDeposit::create([
                "job_number" => $request->job_number,
                "client_po_date" => $request->client_po_date,
                "client_po_number" => $request->client_po_number,
                "client_company" => $request->client_company,
                "client_code" => $request->client_code ?? null,
                "client_pic_name" => $request->client_pic_name,
                "pic_name" => $request->pic_name,
                "status" => 'open',
                "purchase_ordres" => json_encode($purchase_orders),
                "invoices" => null,
                "is_po_deposit" => $request->is_po_deposit,
                "closed_at" => null,
                "budget" => $request->budget ?? null,
                "expense" => $request->expense ?? null,
                "balance" => $request->balance ?? null,
                "title" => $request->title ?? null,
                "total_price" => $request->total_price ?? null,
                "invoice_status" => "new"
            ]);
            
            // For deposits: Create NON-ACTUAL (deposit) projects FIRST
            // This ensures actual projects can reference them via deposit_id
            if ($request->is_po_deposit) {
                $this->createProject($request, $podeposit, false, true);
            }
            
            // Then create ACTUAL projects (they will lookup deposit_id from non-actual projects)
            $this->createProject($request, $podeposit, true, $request->is_po_deposit);
            
            if ($request->is_po_deposit) {
                notify('New PO Deposit Created', 'PO Deposit #' . $podeposit['job_number'], 'marketing', json_encode(["po_deposit" => $podeposit]), 'deposit');
            }

            return response()->json(['code' => 200, 'data' => $podeposit]);
        // } catch (\Throwable $th) {
        //     return response()->json([
        //         'code' => 422,
        //         'errors' => $th
        //     ]);
        // }
    }

    public function destroy($id)
    {
        try {
            $podeposit = PoDeposit::findOrFail($id);
            $existingProjects = $podeposit->projects_data()->delete();
            $podeposit->delete();
            return response()->json(['code' => 200]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['code' => 404, 'data' => []]);
        }
    }



    public function show($id)
    {
        try {

            $query = PoDeposit::with('projects_data', 'projects_data.products_data');

            $result = $query->findOrFail($id)->toArray();

            return response()->json([
                'code' => 200,
                'data' => $result,
            ]);
        } catch (\Throwable $th) {
            return response()->json(['code' => 404, 'data' => []]);
        }
    }

    public function update($id, Request $request)
    {
        $rules = [
            'job_number' => 'required|string',
            'pic_name' => 'string',
            'client_po_number' => 'nullable|string',
            'client_po_date' => 'required|date',
            'client_company' => 'required|string',
            'client_pic_name' => 'required|string',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'errors' => $validator->errors()
            ]);
        }
        try {
            $purchase_orders = [];
            if($request->is_po_deposit){
                foreach ($request->po_deposits as $key => $po) {
                    array_push($purchase_orders, [
                        "client_po_number" => $po['client_po_number'],
                        "client_po_date" => $po['client_po_date'],
                        "client_pic_name" => $po['client_pic_name'],
                        "total_price" => $po['total_price']
                    ]);
                }
            }
            $podeposit = PoDeposit::findOrFail($id);
            $podeposit->update([
                "job_number" => $request->job_number,
                "client_po_date" => $request->client_po_date,
                "client_po_number" => $request->client_po_number,
                "client_company" => $request->client_company,
                "client_code" => $request->client_code ?? null,
                "client_pic_name" => $request->client_pic_name,
                "pic_name" => $request->pic_name,
                "status" => $request->status,
                "purchase_ordres" => json_encode($purchase_orders),
                "budget" => $request->budget,
                "closed_at" => $request->closed_at,
                "expense" => $request->expense,
                "balance" => $request->balance,
                "title" => $request->title ?? null,
                "total_price" => $request->total_price ?? null,
                "invoice_status" => $request->invoice_status ?? "new"
            ]);

            // For deposits: Update NON-ACTUAL (deposit) projects FIRST
            // This ensures actual projects can reference them via deposit_id
            if ($request->is_po_deposit) {
                $this->updateProject($request, $podeposit, false, true, $request->status);
            }
            
            // Then update ACTUAL projects (they will lookup deposit_id from non-actual projects)
            $this->updateProject($request, $podeposit, true, $request->is_po_deposit, $request->status);
            
            if ($request->is_po_deposit) {
                notify('PO Deposit Updated', 'PO Deposit #' . $podeposit['job_number'], 'marketing', json_encode(["po_deposit" => $podeposit]), 'deposit');
            }
            return response()->json(['code' => 200]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['code' => 404, 'data' => []]);
        }
    }

    /**
     * Recalculate inventory for a single project
     */
    private function recalculateInventoryForSingleProject($projectId)
    {
        $project = Project::with('products_data')->findOrFail($projectId);
        $existingInventories = Inventory::where('project', $projectId)->get();

        // Only recalculate if the project already has some inventory records
        if ($existingInventories->count() > 0) {
            \Log::info("Recalculating inventory for project {$projectId} which has " . $existingInventories->count() . " existing inventory records");

            // Get the manufacture warehouse ID from the project
            $manufactureId = $project->manufacture ?? 1; // Default to warehouse ID 1 if not set
            $wh = Warehouse::find($manufactureId);

            foreach ($project->products_data as $product) {
                \Log::info("Processing product ID: {$product->id}, Name: {$product->name}, Quantity: {$product->quantity} for project {$projectId}");

                // Check if inventory records exist for this product in manufacture warehouse
                $hasManufactureInventory = $existingInventories->where('warehouse', $manufactureId)
                    ->where('product', $product->id)
                    ->first();

                // Check if inventory records exist for this product in client warehouse
                $hasClientInventory = $existingInventories->where('warehouse', 2)
                    ->where('product', $product->id)
                    ->first();

                \Log::info("Product {$product->id} - Manufacture inventory exists: " . ($hasManufactureInventory ? 'YES' : 'NO'));
                \Log::info("Product {$product->id} - Client inventory exists: " . ($hasClientInventory ? 'YES' : 'NO'));

                // Update or create inventory record for the manufacture warehouse
                if ($hasManufactureInventory) {
                    $hasManufactureInventory->update([
                        "quantity" => $product->quantity,
                        "product_name" => $product->name,
                        "warehouse_name" => $wh['name'],
                        "storage" => $wh['storage']
                    ]);
                    \Log::info("Updated manufacture inventory for product {$product->id}");
                } else {
                    // Since the project already has inventory records (overall count > 0),
                    // we should create inventory records for this new product too
                    Inventory::create([
                        "project" => $project->id,
                        "product" => $product->id,
                        "product_name" => $product->name,
                        "quantity" => $product->quantity,
                        "warehouse" => $manufactureId,
                        "warehouse_name" => $wh['name'],
                        "storage" => $wh['storage']
                    ]);
                    \Log::info("Created new manufacture inventory for product {$product->id}");
                }

                // Update or create inventory record for the client warehouse (ID 2)
                if ($hasClientInventory) {
                    $hasClientInventory->update([
                        "quantity" => 0, // Reset client warehouse quantity to 0
                        "product_name" => $product->name,
                        "warehouse_name" => "Client",
                        "storage" => FALSE
                    ]);
                    \Log::info("Updated client inventory for product {$product->id}");
                } else {
                    // Since the project already has inventory records (overall count > 0),
                    // we should create inventory records for this new product in client warehouse too
                    Inventory::create([
                        "project" => $project->id,
                        "product" => $product->id,
                        "product_name" => $product->name,
                        "quantity" => 0,
                        "warehouse" => 2,
                        "warehouse_name" => "Client",
                        "storage" => FALSE
                    ]);
                    \Log::info("Created new client inventory for product {$product->id}");
                }
            }

            \Log::info("Completed inventory recalculation for project {$projectId}");
        } else {
            \Log::info("Project {$projectId} has no existing inventory records, skipping recalculation");
        }
    }
}
