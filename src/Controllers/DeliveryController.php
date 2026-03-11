<?php

namespace Src\Controllers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Src\Models\Project;
use Src\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Src\Models\Delivery;
use Src\Models\DeliveryItem;
use Src\Models\Inventory;
use Src\Models\Notification;
use Src\Models\Warehouse;
use Src\Models\User;

class DeliveryController extends Controller
{

    public function __construct()
    {
        $this->middleware('jwt.verify');
    }
    
    public function index($id, Request $request)
    {
        try {
            $query = Delivery::with(['default_origin_data', 'destination_data', 'shipping_vendor_data'])->where('project', $id)
                ->withSum(['delivery_items_data as delivered' => fn (Builder $query) => $query->whereNot('delivered_at', NULL)], 'actual_quantity')
                ->withSum('delivery_items_data as total', 'quantity')->get();
            $project = Project::find($id);
            return response()->json(['code' => 200, 'data' => ['deliveries' => $query, 'project' => $project]]);
        } catch (\Throwable $th) {
            return response()->json(['code' => 404, 'data' => []]);
        }
    }
    public function store(Request $request)
    {
        $rules = [
            'project' => 'required|exists:projects,id',
            'default_origin' => 'required|exists:warehouses,id',
            'destination' => 'required|exists:warehouses,id',
            // 'shipping_vendor' => 'required|exists:shipping_vendors,id',
            'delivery_date' => 'required|date',
            'do_number' => 'required|string',
            // 'do_file' => 'required|string',
            'delivery_items' => 'required',
            'delivery_items.*.origin' => 'required|exists:warehouses,id',
            'delivery_items.*.destination' => 'required|exists:warehouses,id',
            'delivery_items.*.product' => 'required|exists:products,id',
            'delivery_items.*.quantity' => 'required|integer',
            'delivery_items.*.delivery_date' => 'required|date',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'errors' => $validator->errors()
            ]);
        }
        try {
            $delivery_items = [];
            foreach ($request->delivery_items as $itemData) {
                $item = new DeliveryItem($itemData);
                $item['actual_quantity'] = $itemData['quantity'];
                $delivery_items[] = $item;
            }

            $delivery = Delivery::create([
                "project" => $request->project,
                "default_origin" => $request->default_origin,
                "destination" => $request->destination,
                "shipping_vendor" => $request->shipping_vendor,
                "delivery_date" => $request->delivery_date,
                "do_number" => $request->do_number,
                // "do_file" => $request->do_file,
                "do_files" => $request->do_files,
                "receipt_files" => $request->receipt_files,
                "status" => 'ready',
            ]);
            $productss = $delivery->delivery_items_data()->saveMany($delivery_items);

            return response()->json(['code' => 200]);
        } catch (\Throwable $th) {
            return response()->json([
                'code' => 422,
                'errors' => $th
            ]);
        }
    }
    public function show($id, Request $request)
    {
        try {
            $query = Delivery::with(['delivery_items_data', 'default_origin_data', 'destination_data', 'delivery_items_data.origin_data', 'delivery_items_data.product_data', 'project_data', 'shipping_vendor_data'])->withSum(['delivery_items_data as delivered' => fn (Builder $query) => $query->whereNot('delivered_at', NULL)], 'actual_quantity')
                ->withSum('delivery_items_data as total', 'quantity')->findOrFail($id);
            return response()->json(['code' => 200, 'data' => $query]);
        } catch (\Throwable $th) {
            return response()->json(['code' => 404, 'data' => []]);
        }
    }
    public function misc($id, Request $request)
    {
        $products =  Product::where('project', $id)->get();
        $inventories = Inventory::where('project', $id)->get();
        $warehouses = Warehouse::all();
        return response()->json(['code' => 200, 'data' => [
            'products' => $products,
            'inventories' => $inventories,
            'warehouses' => $warehouses
        ]]);
    }
    public function update($id, Request $request)
    {
        $rules = [
            'project' => 'required|exists:projects,id',
            'default_origin' => 'required|exists:warehouses,id',
            'destination' => 'required|exists:warehouses,id',
            // 'shipping_vendor' => 'required|exists:shipping_vendors,id',
            'delivery_date' => 'required|date',
            'do_number' => 'required|string',
            // 'do_file' => 'required|string',
            'delivery_items' => 'required',
            'delivery_items.*.origin' => 'required|exists:warehouses,id',
            'delivery_items.*.destination' => 'required|exists:warehouses,id',
            'delivery_items.*.product' => 'required|exists:products,id',
            'delivery_items.*.quantity' => 'required|integer',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'errors' => $validator->errors()
            ]);
        }
        try {
            DB::beginTransaction();
            $delivery = Delivery::findOrFail($id);
            $existingItems = $delivery->delivery_items_data()->get();
            $alldelivery_items_dataDelivered = true;
            
            foreach ($request->delivery_items as $childData) {
                if (isset($childData['id'])) {
                    $existingItem = $existingItems->where('id', $childData['id'])->first();
                    if ($existingItem) {
                        // Case: Marking as delivered
                        if ($existingItem['delivered_at'] === null && $childData['delivered_at'] !== null) {
                            $inv_org = Inventory::where('warehouse', $childData['origin'])
                                ->where('project', $delivery->project)
                                ->where('product', $childData['product'])->first();
                            $inv_dst = Inventory::where('warehouse', $childData['destination'])
                                ->where('project', $delivery->project)
                                ->where('product', $childData['product'])->first();

                            if ($inv_org) {
                                $inv_org->update(['quantity' => $inv_org->quantity - $childData['actual_quantity']]);
                            }

                            if ($inv_dst) {
                                $inv_dst->update(['quantity' => $inv_dst->quantity + $childData['actual_quantity']]);
                            } else {
                                $warehouse = Warehouse::find($childData['destination']);
                                $product = Product::find($childData['product']);
                                Inventory::create([
                                    "project" => $delivery->project,
                                    "product" => $childData['product'],
                                    "product_name" => $product->name,
                                    "quantity" => $childData['actual_quantity'],
                                    "warehouse" => $childData['destination'],
                                    "warehouse_name" => $warehouse->name,
                                    "storage" => $warehouse->storage
                                ]);
                            }
                        }
                        // Case: Unmarking as delivered
                        elseif ($existingItem['delivered_at'] !== null && $childData['delivered_at'] === null) {
                            $inv_org = Inventory::where('warehouse', $existingItem->origin)
                                ->where('project', $delivery->project)
                                ->where('product', $existingItem->product)->first();
                            $inv_dst = Inventory::where('warehouse', $existingItem->destination)
                                ->where('project', $delivery->project)
                                ->where('product', $existingItem->product)->first();

                            if ($inv_org) {
                                $inv_org->update(['quantity' => $inv_org->quantity + $existingItem->actual_quantity]);
                            }
                            if ($inv_dst) {
                                $inv_dst->update(['quantity' => $inv_dst->quantity - $existingItem->actual_quantity]);
                            }
                        }

                        $existingItem->update($childData);

                        if ($existingItem['delivered_at'] === null) {
                            $alldelivery_items_dataDelivered = false;
                        }
                    }
                } else {
                    $delivery->delivery_items_data()->create($childData);
                    $alldelivery_items_dataDelivered = false;
                }
            }

            $missingChildren = $existingItems->whereNotIn('id', collect($request->delivery_items)->pluck('id'));
            foreach ($missingChildren as $missingChild) {
                // If the item being deleted was delivered, revert inventory
                if ($missingChild->delivered_at !== null) {
                    $inv_org = Inventory::where('warehouse', $missingChild->origin)
                        ->where('project', $delivery->project)
                        ->where('product', $missingChild->product)->first();
                    $inv_dst = Inventory::where('warehouse', $missingChild->destination)
                        ->where('project', $delivery->project)
                        ->where('product', $missingChild->product)->first();

                    if ($inv_org) {
                        $inv_org->update(['quantity' => $inv_org->quantity + $missingChild->actual_quantity]);
                    }
                    if ($inv_dst) {
                        $inv_dst->update(['quantity' => $inv_dst->quantity - $missingChild->actual_quantity]);
                    }
                }
                $missingChild->delete();
            }

            $del_status = $delivery['status'];
            if ($alldelivery_items_dataDelivered && $delivery->delivery_items_data()->exists()) {
                $del_status = 'delivered';
            } elseif ($delivery->delivery_items_data()->whereNotNull('delivered_at')->exists()) {
                $del_status = 'partial';
            } else {
                $del_status = 'ready';
            }

            $delivery->update([
                "project" => $request->project,
                "default_origin" => $request->default_origin,
                "destination" => $request->destination,
                "shipping_vendor" => $request->shipping_vendor,
                "delivery_date" => $request->delivery_date,
                "do_number" => $request->do_number,
                "do_file" => $request->do_file,
                "do_files" => $request->do_files,
                "receipt_files" => $request->receipt_files,
                "status" => $del_status
            ]);

            // Re-evaluate project status
            $this->updateProjectStatus($delivery->project);

            DB::commit();
            return response()->json(['code' => 200]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'code' => 422,
                'errors' => $th->getMessage()
            ]);
        }
    }

    public function destroy($id, Request $request)
    {
        try {
            DB::beginTransaction();
            $delivery = Delivery::with('delivery_items_data')->findOrFail($id);
            $projectId = $delivery->project;

            // Revert inventory for delivered items
            foreach ($delivery->delivery_items_data as $item) {
                if ($item->delivered_at !== null) {
                    $inv_org = Inventory::where('warehouse', $item->origin)
                        ->where('project', $projectId)
                        ->where('product', $item->product)->first();
                    $inv_dst = Inventory::where('warehouse', $item->destination)
                        ->where('project', $projectId)
                        ->where('product', $item->product)->first();

                    if ($inv_org) {
                        $inv_org->update(['quantity' => $inv_org->quantity + $item->actual_quantity]);
                    }
                    if ($inv_dst) {
                        $inv_dst->update(['quantity' => $inv_dst->quantity - $item->actual_quantity]);
                    }
                }
            }

            $delivery->delete();

            // Re-evaluate project status
            $this->updateProjectStatus($projectId);

            DB::commit();
            return response()->json(['code' => 200]);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['code' => 404, 'data' => []]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'code' => 422,
                'errors' => $th->getMessage()
            ]);
        }
    }

    private function updateProjectStatus($projectId)
    {
        $project = Project::find($projectId);
        if (!$project) return;

        $products = Product::where('project', $projectId)->get();
        $projectDelivered = $products->isNotEmpty();
        
        foreach ($products as $product) {
            $inventory = Inventory::where('project', $projectId)
                ->where('warehouse', 2)
                ->where('product', $product->id)
                ->first();
            
            if (!$inventory || $inventory->quantity < $product->quantity) {
                $projectDelivered = false;
                break;
            }
        }

        if ($projectDelivered) {
            $project->update(["status" => "delivered"]);
            notify('Project Delivered', 'Project #' . $project['job_number'] . ' PO #' . $project['client_po_number'], 'marketing', json_encode(["project" => $project]), "delivery");
        } elseif ($project->deliveries_data()->where('status', 'delivered')->where('destination', 2)->exists()) {
            $project->update(["status" => "partial"]);
            notify('Project Partial Delivered', 'Project #' . $project['job_number'] . ' PO #' . $project['client_po_number'], 'marketing', json_encode(["project" => $project]), "delivery");
        } else {
            // Check if there are any deliveries at all
            if ($project->deliveries_data()->exists()) {
                $project->update(["status" => "ready"]);
            } else {
                // If no deliveries, might still be 'ready' or 'production' or 'new'
                // For now, defaulting back to 'ready' if it was already in delivery flow
                $project->update(["status" => "ready"]);
            }
        }
    }
    public function inventories($id, Request $request)
    {
        try {
            $query = Inventory::with('warehouse_data')->where('project', $id)->where('quantity', '>', 0)->get();
            return response()->json(['code' => 200, 'data' => $query]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['code' => 404, 'data' => []]);
        }
    }
    public function inventory(Request $request)
    {
        try {
            $query = Inventory::with('project_data', 'warehouse_data', 'product_data')
                ->where('storage', TRUE)->where('quantity', '>', 0);

            if ($request->has('warehouse')) {
                $warehouses = explode(',', $request->input('warehouse'));
                $query->whereIn('warehouse', $warehouses);
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($query0) use ($search) {
                    $query0->orWhereHas('project_data', function ($query1) use ($search) {
                        $query1->where('job_number', 'like', '%' . $search . '%')
                            ->orWhere('client_po_number', 'like', '%' . $search . '%')
                            ->orWhere('client_company', 'like', '%' . $search . '%')
                            ->orWhere('client_pic_name', 'like', '%' . $search . '%');
                    })->orWhereHas('product_data', function ($query1) use ($search) {
                        $query1->where('name', 'like', '%' . $search . '%');
                    })->orWhereHas('warehouse_data', function ($query1) use ($search) {
                        $query1->where('name', 'like', '%' . $search . '%');
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
    public function logss($id, Request $request)
    {
        try {

            $ids = [];
            $dels = [];
            $deliverys = Delivery::with('default_origin_data', 'destination_data', 'shipping_vendor_data')->where('project', $id)->get()->toArray();
            foreach ($deliverys as $key => $value) {
                array_push($ids, $value['id']);
                array_push($dels, $value);
            }
            $query = DeliveryItem::with('product_data', 'origin_data', 'destination_data', 'delivery_data')->whereIn('delivery', $ids)->whereNot('delivered_at', NULL)->get();
            return response()->json(['code' => 200, 'data' => $query, 'ids' => $ids, 'dels' => $dels]);
        } catch (\Throwable $th) {
            return response()->json(['code' => 404, 'data' => []]);
        }
    }
    
    public function logs($id, Request $request)
    {
        try {
            $inventory = Inventory::findOrFail($id);
            $query = DeliveryItem::with('product_data', 'origin_data', 'destination_data')->where(
                fn (Builder $query) =>
                $query->where('destination', $inventory['warehouse'])->orWhere('origin', $inventory['warehouse'])
            )->where('product', $inventory['product'])->whereNot('delivered_at', NULL)->get();
            $query = $query->toArray();
            $query = array_map(function ($item) use ($inventory) {
                $item['direction'] = $item['destination'] === $inventory['warehouse'] ? 'in' : 'out';
                return $item;
            }, $query);
            return response()->json(['code' => 200, 'data' => $query]);
        } catch (\Throwable $th) {
            return response()->json(['code' => 404, 'data' => []]);
        }
    }
    public function log($id, Request $request)
    {
        try {
            $query = DeliveryItem::with('delivery_data.project_data', 'product_data', 'origin_data', 'destination_data')->findOrFail($id);
            return response()->json(['code' => 200, 'data' => $query]);
        } catch (\Throwable $th) {
            return response()->json(['code' => 404, 'data' => []]);
        }
    }
}
