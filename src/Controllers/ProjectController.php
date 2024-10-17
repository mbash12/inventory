<?php

namespace Src\Controllers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Src\Models\Project;
use Src\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Src\Models\Inventory;
use Src\Models\Warehouse;
use Src\Models\DeliveryItem;
use Src\Models\User;
use Src\Models\Notification;
use Src\Models\PoDeposit;

class ProjectController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.verify');
    }
    public function delivery($id, Request $request)
    {
        $rules = [
            // 'shipping_vendor' => 'required|exists:shipping_vendors,id',
            'manufacture' => 'required|exists:warehouses,id',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'errors' => $validator->errors()
            ]);
        }
        try {
            $project = Project::findOrFail($id);
            $project->update([
                // "shipping_vendor" => $request->shipping_vendor,
                "manufacture" => $request->manufacture,
                "status" => 'ready',
            ]);
            $existingProducts = Product::where('project', $id)->get();
            $existingInventories = Inventory::query();
            $manufacture = $item['default_origin'] ?? 1;
            if (!empty($request->manufacture)) {
                $manufacture = $request->manufacture;
            }
            $wh = Warehouse::find($manufacture);

            foreach ($existingProducts as $existingProduct) {

                $inv_org = $existingInventories->where('warehouse', $manufacture)
                    ->where('product', $existingProduct['id'])->first();
                $inv_dst = $existingInventories->where('warehouse', 2)
                    ->where('product', $existingProduct['id'])->first();
                if ($inv_org) {
                    $inv_org->update([
                        "project" => $project->id,
                        "product" => $existingProduct['id'],
                        "product_name" => $existingProduct['name'],
                        "quantity" => $existingProduct['quantity'],
                        "warehouse" => $manufacture,
                        "warehouse_name" => $wh['name'],
                        "storage" => $wh['storage']
                    ]);
                } else {
                    Inventory::create([
                        "project" => $project->id,
                        "product" => $existingProduct->id,
                        "product_name" => $existingProduct->name,
                        "quantity" => $existingProduct['quantity'],
                        "warehouse" => $manufacture,
                        "warehouse_name" => $wh['name'],
                        "storage" => $wh['storage']
                    ]);
                }

                if ($inv_dst) {
                    $inv_dst->update([
                        "project" => $project->id,
                        "product" => $existingProduct['id'],
                        "product_name" => $existingProduct['name'],
                        "quantity" => 0,
                        "warehouse" => 2,
                        "warehouse_name" => "Client",
                        "storage" => FALSE
                    ]);
                } else {
                    Inventory::create([
                        "project" => $project->id,
                        "product" => $existingProduct->id,
                        "product_name" => $existingProduct->name,
                        "quantity" => 0,
                        "warehouse" => 2,
                        "warehouse_name" => "Client",
                        "storage" => FALSE
                    ]);
                }
            }

            notify('Project Delivery Updated', 'Project #' . $project['job_number'] . ' Ready to Deliver', 'delivery', json_encode(["project" => $project]), "delivery");

            return response()->json(['code' => 200]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['code' => 404, 'data' => []]);
        }
    }
    public function snippet($id)
    {
        try {
            $result = Project::findOrFail($id);
            return response()->json([
                'code' => 200,
                'data' => $result,
            ]);
        } catch (\Throwable $th) {
            return response()->json(['code' => 404, 'data' => []]);
        }
    }
    public function index(Request $request)
    {
        try {
            $query = Project::with('products_data', 'shipping_vendors_data', 'po_deposit_data', 'po_deposit_data.invoice_progresses_data', 'po_deposit_data.marketing_followups')
                ->withSum(['inventories_data as stored' => fn (Builder $query1) => $query1->where('storage', TRUE)], 'quantity')
                ->withSum(['inventories_data as delivered' => fn (Builder $query1) => $query1->where('warehouse', 2)], 'quantity')
                ->withSum('products_data as total', 'quantity');

            if ($request->has('action')) {
                $actions = $request->input('action');
                $query->whereHas('po_deposit_data', function ($query0) use ($actions) {
                    $query0->whereHas('marketing_followups', function ($query1) use ($actions) {
                        $query1->where(function ($query2) use ($actions) {
                            if ($actions == 'overdue') {
                                $query2->whereDate('schedule_date', '<', now())->whereNull('followup_date');
                            }
                            if ($actions == 'today') {
                                $query2->whereDate('schedule_date', '=', now())->whereNull('followup_date');
                            }
                            if ($actions == 'future') {
                                $query2->whereDate('schedule_date', '>', now())->whereNull('followup_date');
                            }
                        });
                    });
                });
            }


            if ($request->has('status')) {
                $statuses = explode(',', $request->input('status'));
                $query->whereIn('status', $statuses);
            }

            if ($request->has('invoice')) {
                $invoicees = $request->input('invoice');
                $query->where('invoice_status', $invoicees);
                // $query->whereHas('po_deposit_data', function ($query0) use ($invoicees) {
                //     $query0->where('invoice_status', $invoicees);
                // });
            }
            if ($request->has('deposit')) {
                $deposit = explode(',', $request->input('deposit'));
                if (in_array('deposit', $deposit) && in_array('nondeposit', $deposit)) {
                } else {
                    $query->where('is_po_deposit', $deposit[0] == 'deposit' ? 1 : 0);
                }
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
            if ($request->has('filter_date')) {
                $dateField = $request->input('filter_date');
                $query->where(function ($query) use ($dateField) {
                    $query->orWhereDate('po_deadline', $dateField)
                          ->orWhereDate('production_deadline', $dateField)
                          ->orWhereDate('delivery_deadline', $dateField);
                });
            }
            if ($request->has('po')) {
                $poStatus = $request->input('po');
                if ($poStatus == 'true') {
                    $query->whereNotNull('client_po_number');
                } else {
                    $query->whereNull('client_po_number');
                }
            }
            if ($request->has('is_real')) {
                $query->where('is_real',  $request->input('is_real') ? 1 : 0);
            }
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($query0) use ($search) {
                    $query0->where('job_number', 'like', '%' . $search . '%')
                        ->orWhere('title', 'like', '%' . $search . '%')
                        ->orWhere('client_po_number', 'like', '%' . $search . '%')
                        ->orWhere('client_company', 'like', '%' . $search . '%')
                        ->orWhere('client_pic_name', 'like', '%' . $search . '%')
                        ->orWhereHas('products_data', function ($query1) use ($search) {
                            $query1->where('name', 'like', '%' . $search . '%');
                        });
                });
            }
            if ($request->has('delivery')) {
                $query->where(function ($query0) {
                    $query0->where('is_po_deposit', false)
                        ->orWhere(function ($query1) {
                            $query1->where('is_po_deposit', true)
                                ->whereNotNull('sent_to_del_at');
                        });
                });
            }

            $query->orderBy($request->input('order_by', 'id'), $request->input('sort', 'desc'));

            $totalRecords = $query->count();
            $limit = (int) $request->input('limit', 10);
            $currentPage = (int) $request->input('page', 1);
            $offset = ($currentPage - 1) * $limit;

            $query->offset($offset)->limit($limit);

            $results = $query->get();

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
        $rules = [
            'job_number' => 'required|unique:projects',
            'pic_name' => 'string',
            // 'pic_phone' => 'string',
            // 'client_po_number' => 'string',
            'client_po_date' => 'nullable|date',
            'client_company' => 'required|string',
            'client_pic_name' => 'required|string',
            // 'client_pic_phone' => 'required|string',
            // 'shipping_vendor' => 'required|exists:shipping_vendors,id',
            // 'manufacture' => 'required|exists:warehouses,id',
            'products' => 'required',
            'products.*.name' => 'required|string',
            'products.*.quantity' => 'required|integer',
            // 'products.*.description' => 'string',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'errors' => $validator->errors()
            ]);
        }
        // try {
        $products = [];
        foreach ($request->products as $productData) {
            $product = new Product($productData);
            $products[] = $product;
        }
        $podeposit = PoDeposit::create([
            "job_number" => $request->job_number,
            "client_po_date" => $request->client_po_date,
            "client_po_number" => $request->client_po_number,
            "client_company" => $request->client_company,
            "client_pic_name" => $request->client_pic_name,
            "pic_name" => $request->pic_name,
            "expense" => $request->total_price,
            "status" => "open",
            "is_po_deposit" => false,
        ]);

        $project = Project::create([
            "title" => $request->title,
            "job_number" => $request->job_number,
            "pic_name" => $request->pic_name,
            "client_po_number" => $request->client_po_number,
            "client_po_date" => $request->client_po_date,
            "client_company" => $request->client_company,
            "client_pic_name" => $request->client_pic_name,
            "total_price" => $request->total_price,
            "remaining_amount" => $request->total_price,
            "po_deposit" => $podeposit->id,
            "status" => 'new',
        ]);


        $productss = $project->products_data()->saveMany($products);

        notify('New Project Created', 'Project #' . $project['job_number'], 'marketing', json_encode(["project" => $project]), "project");

        return response()->json(['code' => 200, 'data' => $project]);
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
            $item = Project::findOrFail($id);
            $dep = PoDeposit::findOrFail($item->po_deposit);
            $item->delete();
            $dep->delete();
            return response()->json(['code' => 200]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['code' => 404, 'data' => []]);
        }
    }

    public function show($id)
    {
        try {
            $query = Project::with('products_data', 'po_deposit_data')
                ->withSum(['inventories_data as stored' => fn (Builder $query) => $query->where('storage', TRUE)], 'quantity')
                ->withSum(['inventories_data as delivered' => fn (Builder $query) => $query->where('warehouse', 2)], 'quantity')
                ->withSum('products_data as total', 'quantity');

            $result = $query->findOrFail($id)->toArray();


            $result['products_data'] = array_map(function ($item) {
                $item['delivered'] =  (int) DeliveryItem::where('product', $item['id'])->whereNot('delivered_at', NULL)->where('destination', 2)->sum('actual_quantity');
                return $item;
            }, $result['products_data']);



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
            // 'pic_phone' => 'string',
            'client_po_number' => 'nullable|string',
            'client_po_date' => 'required|date',
            'client_company' => 'required|string',
            'client_pic_name' => 'required|string',
            // 'client_pic_phone' => 'required|string',
            // 'shipping_vendor' => 'required|exists:shipping_vendors,id',
            // 'manufacture' => 'required|exists:warehouses,id',
            'products' => 'required',
            'products.*.name' => 'required|string',
            'products.*.quantity' => 'required|integer',
            // 'products.*.description' => 'string',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'errors' => $validator->errors()
            ]);
        }
        try {
            $item = Project::findOrFail($id);

            $podeposit = PoDeposit::find($item->po_deposit)->update([
                "job_number" => $request->job_number,
                "client_po_date" => $request->client_po_date,
                "client_po_number" => $request->client_po_number,
                "client_company" => $request->client_company,
                "client_pic_name" => $request->client_pic_name,
                "pic_name" => $request->pic_name,
                "expense" => $request->total_price,
            ]);

            $item->update([
                "title" => $request->title,
                "job_number" => $request->job_number,
                "pic_name" => $request->pic_name,
                // "pic_phone" => $request->pic_phone,
                "client_po_number" => $request->client_po_number,
                "client_po_date" => $request->client_po_date,
                "client_company" => $request->client_company,
                "client_pic_name" => $request->client_pic_name,
                "total_price" => $request->total_price,
                "remaining_amount" => $request->total_price - $item->invoiced_amount,
                // "client_pic_phone" => $request->client_pic_phone,
                // "shipping_vendor" => $request->shipping_vendor,
                // "manufacture" => $request->manufacture,
                "status" => $request->status,
            ]);

            $existingProducts = $item->products_data()->get();

            foreach ($request->products as $childData) {
                if (isset($childData['id'])) {
                    $existingProduct = $existingProducts->where('id', $childData['id'])->first();
                    if ($existingProduct) {
                        $existingProduct->update($childData);
                    }
                } else {
                    $item->products_data()->create($childData);
                }
            }
            $missingChildren = $existingProducts->whereNotIn('id', collect($request->products)->pluck('id'));
            foreach ($missingChildren as $missingChild) {
                $missingChild->delete();
            }

            notify('Project Updated', 'Project #' . $item['job_number'] . ' updated', 'marketing', json_encode(["project" => $item]), "project");

            return response()->json(['code' => 200]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['code' => 404, 'data' => []]);
        }
    }
}
