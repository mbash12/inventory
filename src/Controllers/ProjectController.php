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
            $manufacture = (int) ($request->manufacture ?: ($project->manufacture ?? 1));
            $wh = Warehouse::find($manufacture);

            foreach ($existingProducts as $existingProduct) {
                $inv_org = Inventory::where('project', $project->id)
                    ->where('warehouse', $manufacture)
                    ->where('product', $existingProduct->id)
                    ->first();
                $inv_dst = Inventory::where('project', $project->id)
                    ->where('warehouse', 2)
                    ->where('product', $existingProduct->id)
                    ->first();

                if ($inv_org) {
                    $inv_org->update([
                        "project" => $project->id,
                        "product" => $existingProduct->id,
                        "product_name" => $existingProduct->name,
                        "quantity" => $existingProduct->quantity,
                        "warehouse" => $manufacture,
                        "warehouse_name" => $wh['name'],
                        "storage" => $wh['storage']
                    ]);
                } else {
                    Inventory::create([
                        "project" => $project->id,
                        "product" => $existingProduct->id,
                        "product_name" => $existingProduct->name,
                        "quantity" => $existingProduct->quantity,
                        "warehouse" => $manufacture,
                        "warehouse_name" => $wh['name'],
                        "storage" => $wh['storage']
                    ]);
                }

                if ($inv_dst) {
                    $inv_dst->update([
                        "project" => $project->id,
                        "product" => $existingProduct->id,
                        "product_name" => $existingProduct->name,
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

            // if ($request->has('action')) {
            //     $actions = $request->input('action');
            //     $query->whereHas('po_deposit_data', function ($query0) use ($actions) {
            //         $query0->whereHas('marketing_followups', function ($query1) use ($actions) {
            //             $query1->where(function ($query2) use ($actions) {
            //                 if ($actions == 'overdue') {
            //                     $query2->whereDate('schedule_date', '<', now())->whereNull('followup_date');
            //                 }
            //                 if ($actions == 'today') {
            //                     $query2->whereDate('schedule_date', '=', now())->whereNull('followup_date');
            //                 }
            //                 if ($actions == 'future') {
            //                     $query2->whereDate('schedule_date', '>', now())->whereNull('followup_date');
            //                 }
            //             });
            //         });
            //     });
            // }

            if ($request->has('status')) {
                $statuses = explode(',', $request->input('status'));
                $query->whereIn('status', $statuses);
            }
            if ($request->has('delivery')) {
                $query->whereIn('project_type', ['gimmick','printing']);
            }

            if ($request->has('noinvoice')) {
                $query->where(function ($query) {
                    $query->whereNull('invoice_status')
                          ->orWhere('invoice_status', 'new')
                          ->orWhere('invoice_status', 'progress');
                });
            }

            if ($request->has('client')) {
                $query->where('client_company', $request->input('client'));
            }

            if ($request->has('project_type')) {
                $projectTypes = explode(',', $request->input('project_type'));
                if (in_array('projects', $projectTypes)) {
                    $query->whereIn('project_type', ['project', 'design', 'printing', 'gimmick', 'payment']);
                } else {
                    $query->whereIn('project_type', $projectTypes);
                }
            }

            if ($request->has('type')) {
                $query->where('project_type', $request->input('type'));
            }

            if ($request->has('invoice')) {
                $invoicees = explode(',', $request->input('invoice'));
                $query->whereIn('invoice_status', $invoicees);
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
                          ->orWhereDate('delivery_deadline', $dateField)
                          ->orWhereDate('do_deadline', $dateField)
                          ->orWhereDate('design_deadline', $dateField)
                          ->orWhereDate('bast_deadline', $dateField)
                          ->orWhereDate('gr_deadline', $dateField);
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
                $query->whereNot('status',  'cancel');
                // Deposit projects (is_po_deposit && !is_real) are not physically shipped:
                // they are the down-payment/Paket line. Only actual projects and
                // non-deposit projects appear in the delivery list.
                $query->where(function ($query0) {
                    $query0->where('is_po_deposit', false)
                        ->orWhere('is_real', 1);
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
   
   
    public function indexes(Request $request)
    {
        try {
            $query = Project::with('products_data', 'shipping_vendors_data', 'po_deposit_data', 'po_deposit_data.invoice_progresses_data', 'po_deposit_data.marketing_followups')
                ->withSum(['inventories_data as stored' => fn (Builder $query1) => $query1->where('storage', TRUE)], 'quantity')
                ->withSum(['inventories_data as delivered' => fn (Builder $query1) => $query1->where('warehouse', 2)], 'quantity')
                ->withSum('products_data as total', 'quantity');

            if ($request->has('status')) {
                $statuses = explode(',', $request->input('status'));
                $query->whereIn('status', $statuses);
            }
            
            if ($request->has('project_type')) {
                $projectType = $request->input('project_type');
                
                if ($projectType === 'nondeposits') {
                    // For non-deposits, select only the first project per po_deposit
                    $query->whereIn('id', function($subquery) {
                        $subquery->select(\DB::raw('MIN(id)'))
                            ->from('projects')
                            ->groupBy('po_deposit');
                    })->where('is_po_deposit', false);
                } elseif ($projectType === 'deposits') {
                    // For deposits, return all non-real projects
                    $query->where('is_real', false);
                }
            }

            if ($request->has('invoice')) {
                $invoicees = explode(',', $request->input('invoice'));
                $query->whereIn('invoice_status', $invoicees);
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
            $item['delivered'] = (int) DeliveryItem::where('product', $item['id'])
                ->whereNot('delivered_at', NULL)
                ->where('destination', 2)
                ->sum('actual_quantity');
                
            $item['deliveries'] = DeliveryItem::where('product', $item['id'])
                ->with(['delivery_data' => function($query) {
                    $query->select('id', 'do_number', 'do_file', 'do_files');
                }])
                ->whereHas('delivery_data', function($query) {
                    $query->whereNotNull('do_number');
                })
                ->get()
                ->map(function($deliveryItem) {
                    $do_files = [];
                    
                    if ($deliveryItem->delivery_data->do_file) {
                        $do_files[] = $deliveryItem->delivery_data->do_file;
                    }
                    
                    if ($deliveryItem->delivery_data->do_files) {
                        $additional_files = json_decode($deliveryItem->delivery_data->do_files, true);
                        
                        // Ensure $additional_files is an array
                        if (is_array($additional_files)) {
                            $do_files = array_merge($do_files, $additional_files);
                        } else {
                            // If it's not an array, treat it as a single file path
                            $do_files[] = $deliveryItem->delivery_data->do_files;
                        }
                    }
                    
                    return [
                        'do_number' => $deliveryItem->delivery_data->do_number,
                        'quantity' => $deliveryItem->actual_quantity,
                        'do_files' => array_values(array_unique($do_files))
                    ];
                });
                
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

            $status = $request->status;


            if($request->project_type == 'design') {
                $products = $request->products;
                $hasDesignApproved = false;
                $allHaveDesignFiles = true;

                foreach ($products as $product) {
                    if (isset($product['design_approved']) && $product['design_approved'] === true) {
                        $hasDesignApproved = true;
                    }
                    if (!isset($product['design_files']) || empty($product['design_files'])) {
                        $allHaveDesignFiles = false;
                    }
                }

                if ($hasDesignApproved) {
                    $status = 'production';
                }
                if ($allHaveDesignFiles) {
                    $status = 'ready';
                }
            }

            $documents = json_decode($request->documents);
            error_log($request->bast_files);
            $request->bast_files = $request->bast_files === 'null' ? null : $request->bast_files;
            $request->gr_files = $request->gr_files === 'null'? null : $request->gr_files;
            $request->bast_files = $request->bast_files === 'null'? null : $request->bast_files;
            $request->gr_files = $request->gr_files === 'null'? null : $request->gr_files;
            
            if ($request->project_type == 'design' && $request->bast_files !== null &&  $request->bast_files !== '[]') {
                if (!$documents->gr || ($documents->gr && $request->gr_files !== null &&  $request->gr_files !== '[]')) {
                    $status = 'delivered';
                }
            }
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
                "remaining_amount" => round($request->total_price - $item->invoiced_amount, 2),
                // "client_pic_phone" => $request->client_pic_phone,
                // "shipping_vendor" => $request->shipping_vendor,
                // "manufacture" => $request->manufacture,

                "status" => $status,
                "documents" => $request->documents,
                "project_type" => $request->project_type,
                "bast_files" => $request->bast_files,
                "gr_files" => $request->gr_files,
                "do_files" => $request->do_files,
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


    public function todo(Request $request)
    {
        // try {
            $query = Project::with('products_data', 'shipping_vendors_data', 'po_deposit_data', 'deliveries_data')
                ->withSum('products_data as total', 'quantity')
                // ->where(function($query) {
                //     $query
                //         ->where('client_po_number', null)
                //         ->orWhere(function($q) {
                //             $q->where('project_type', 'gimmick')
                //               ->where(function($q2) {
                //                   $q2->whereNull('do_files')
                //                      ->orWhere('do_files','null')
                //                      ->orWhere(function($q3) {
                //                          $q3->whereRaw("JSON_EXTRACT(documents, '$.bast') = 'true'")
                //                           ->where(function($q4) {
                //                               $q4->whereNull('bast_files')
                //                                  ->orWhere('bast_files', 'null');
                //                           });
                //                      });
                //               });
                //         })
                //         ->orWhere(function($q) {
                //             $q->where('project_type', 'printing')
                //               ->whereNull('do_files')
                //               ->orWhere('do_files','null');
                //         })
                //         ->orWhere(function($q) {
                //             $q->where('project_type', 'design')
                //               ->whereNull('bast_files')
                //               ->orWhere('bast_files','null');
                //         })
                //         ->orWhere(function($q) {
                //             $q->where('project_type', 'payment')
                //               ->where(function($q2) {
                //                   $q2->whereNull('gr_files')
                //                      ->orWhere(function($q3) {
                //                          $q3->whereRaw("JSON_EXTRACT(documents, '$.bast') = 'true'")
                //                           ->where(function($q4) {
                //                               $q4->whereNull('bast_files')
                //                                  ->orWhere('bast_files', 'null');
                //                           });
                //                      });
                //               });
                //         })
                //         ->orWhere(function($q) {
                //             $q->whereRaw("JSON_EXTRACT(documents, '$.gr') IS NULL OR JSON_EXTRACT(documents, '$.gr') = 'false'")
                //               ->whereNull('gr_files');
                //         });
                // })
                ->whereNot('status', 'cancel')->where('is_real', true);
                // ->whereNot('invoice_status', 'sent');

                if ($request->has('filter_date')) {
                    $dateField = $request->input('filter_date');
                    $query->where(function ($query) use ($dateField) {
                        $query->orWhereDate('po_deadline', $dateField)
                              ->orWhereDate('production_deadline', $dateField)
                              ->orWhereDate('delivery_deadline', $dateField)
                              ->orWhereDate('do_deadline', $dateField)
                              ->orWhereDate('design_deadline', $dateField)
                              ->orWhereDate('bast_deadline', $dateField)
                              ->orWhereDate('gr_deadline', $dateField);
                    });
                }

            // if ($request->has('noinvoice')) {
            //     $query->where(function ($query) {
            //         $query->whereNull('invoice_status')
            //               ->orWhere('invoice_status', 'new')
            //               ->orWhere('invoice_status', 'progress');
            //     });
            // }

            if ($request->has('project_type')) {
                $projectTypes = explode(',', $request->input('project_type'));
                $query->whereIn('project_type', $projectTypes);
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

            $query->orderBy($request->input('order_by', 'id'), $request->input('sort', 'desc'));

            $totalRecords = $query->count();
            $limit = (int) $request->input('limit', 10);
            $currentPage = (int) $request->input('page', 1);
            $offset = ($currentPage - 1) * $limit;

            $query->offset($offset)->limit($limit);

            $results = $query->get();

            $transformedData = $results->map(function ($project) {
                $depositStatus = 'non-deposit';
                if ($project->is_po_deposit) {
                    $depositStatus = $project->is_real ? 'actual' : 'non-actual';
                }

                $poStatus = $project->po_deposit_data && $project->po_deposit_data->client_po_number ? 'available' : 'not available';
                
                $doStatus = 'not applicable';
                if (in_array($project->project_type, ['gimmick', 'printing'])) {
                    $deliveries = $project->deliveries_data;
                    if ($deliveries->isEmpty()) {
                        $doStatus = 'not uploaded';
                    } else {
                        $allDeliveriesHaveDO = $deliveries->every(function($delivery) {
                            return (!empty($delivery->do_files) && $delivery->do_files !== '[]') || !empty($delivery->do_file);
                        });
                        $doStatus = $allDeliveriesHaveDO ? 'uploaded' : 'not uploaded';
                    }
                }

                $designApproved = 'not applicable';
                $designFiles = 'not applicable';
                if ($project->project_type === 'design' && $project->products_data->isNotEmpty()) {
                    $designApproved = $project->products_data->contains('design_approved', true) ? 'approved' : 'not approved';
                    $designFiles = $project->products_data->contains(function($product) {
                        return !empty($product->design_files);
                    }) ? 'uploaded' : 'not uploaded';
                }


                $documents = json_decode($project->documents, true);

                $bastStatus = 'not applicable';
                if ($project->project_type === 'design' || ($documents && isset($documents['bast']) && $documents['bast'])) {
                    $bastStatus = $project->bast_files ? 'uploaded' : 'not uploaded';
                }

                $grStatus = 'not applicable';
                if ($documents && isset($documents['gr']) && $documents['gr']) {
                    $grStatus = $project->gr_files ? 'uploaded' : 'not uploaded';
                }

                return [
                    'id' => $project->id,
                    'project_type' => $project->project_type,
                    'title' => $project->title,
                    'client_company' => $project->client_company,
                    'client_pic_name' => $project->client_pic_name,
                    'client_po_number' => $project->client_po_number,
                    'client_po_date' => $project->client_po_date,
                    'job_number' => $project->job_number,
                    'deposit_status' => $depositStatus,
                    'po_status' => $poStatus,
                    'do_status' => $doStatus,
                    'design_approved' => $designApproved,
                    'design_files' => $designFiles,
                    'bast_status' => $bastStatus,
                    'gr_status' => $grStatus,
                    'status' => $project->status,
                    'deadline_meta' => $project->deadline_meta
                ];
            });

            return response()->json([
                'code' => 200,
                'data' => $transformedData,
                'meta' => [
                    'total_records' => $totalRecords,
                    'total_pages' => ceil($totalRecords / $limit),
                    'current_page' => $currentPage,
                    'limit_per_page' => $limit,
                ]
            ]);
        // } catch (\Throwable $th) {
        //     return response()->json(['code' => 404, 'data' => []]);
        // }
    }
}
