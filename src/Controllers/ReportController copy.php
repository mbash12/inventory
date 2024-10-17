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
// use Maatwebsite\Excel\Facades\Excel;
// use PhpOffice\PhpSpreadsheet\Spreadsheet;
// use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;



class ReportController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('jwt.verify');
    // }
    public function index(Request $request)
    {
        // try {
            [$start, $end] = explode("_", $request->input('dates'));

            $startDate = new \DateTime($start);
            $endDate = new \DateTime($end);
            
            $interval = $startDate->diff($endDate);
            $isMonthly = $interval->days > 31;
            $po_grand_1 = PoDeposit::select(
                DB::raw('SUM(CASE WHEN is_po_deposit = true THEN budget ELSE 0 END) as total_po_deposit'),
                DB::raw('SUM(CASE WHEN is_po_deposit = false THEN expense ELSE 0 END) as total_po_non_deposit')
            )
            ->whereBetween('po_deposits.client_po_date', [$startDate, $endDate])
            ->first()->toArray();
            
            $po_progress_1 = PoDeposit::select(
                DB::raw("DATE_FORMAT(client_po_date, " . ($isMonthly ? "'%Y-%m'" : "'%Y-%m-%d'") . ") as month_name"),
                DB::raw('SUM(CASE WHEN client_po_number IS NULL THEN expense ELSE 0 END) as po_on_progress')
            )
            ->whereBetween('po_deposits.client_po_date', [$startDate, $endDate])
            ->groupBy(DB::raw("DATE_FORMAT(client_po_date, " . ($isMonthly ? "'%Y-%m'" : "'%Y-%m-%d'") . ")"))
            ->get()->toArray();
            

            $po_deposit_1 = PoDeposit::select(
                DB::raw("DATE_FORMAT(client_po_date, " . ($isMonthly ? "'%Y-%m'" : "'%Y-%m-%d'") . ") as month_name"),
                DB::raw('SUM(budget) as total_budget'),
                DB::raw('SUM(expense) as total_expense'),
                DB::raw('SUM(balance) as total_balance')
            )
            ->where("is_po_deposit", true)
            ->whereBetween('client_po_date', [$startDate, $endDate])
            ->groupBy(DB::raw("DATE_FORMAT(client_po_date, " . ($isMonthly ? "'%Y-%m'" : "'%Y-%m-%d'") . ")"))
            ->get()->toArray();

            // $po_deposit_2 = PoDeposit::select(
            //     DB::raw('SUM(CASE WHEN invoices IS NULL THEN expense ELSE 0 END) as total_invoice_null'),
            //     DB::raw('SUM(CASE WHEN projects.status != "delivered" THEN expense ELSE 0 END) as total_not_delivered'),
            //     // DB::raw('SUM(budget) as total_budget'),
            //     // DB::raw('SUM(expense) as total_expense')
            //     DB::raw('SUM(balance) as total_balance')
            // )
            // ->leftJoin('projects', 'po_deposits.id', '=', 'projects.po_deposit')
            // ->where("po_deposits.is_po_deposit", true)
            // ->whereBetween('po_deposits.client_po_date', [$startDate, $endDate])
            // ->first()->toArray();

            $po_deposit_2 = PoDeposit::select(
                DB::raw('SUM(CASE WHEN invoice_status = "sent" THEN budget ELSE 0 END) as total_invoiced'),
                // DB::raw('SUM(CASE WHEN projects.status != "delivered" THEN expense ELSE 0 END) as total_not_delivered'),
                // DB::raw('SUM(budget) as total_budget'),
                DB::raw('SUM(expense) as total_used'),
                DB::raw('SUM(balance) as total_not_used')
            )
            // ->rightJoin('projects', 'po_deposits.id', '=', 'projects.po_deposit')
            ->where("po_deposits.is_po_deposit", true)
            ->whereBetween('po_deposits.client_po_date', [$startDate, $endDate])
            ->first()->toArray();

            $po_project_1 = PoDeposit::select(
                DB::raw("DATE_FORMAT(po_deposits.client_po_date, " . ($isMonthly ? "'%Y-%m'" : "'%Y-%m-%d'") . ") as month_name"),
                DB::raw('SUM(CASE WHEN projects.status IN ("ready","production") THEN expense ELSE 0 END) as total_not_delivered'),
                DB::raw('SUM(CASE WHEN projects.invoice_status != "sent" THEN expense ELSE 0 END) as total_invoice_null')
            )
            ->where("po_deposits.is_po_deposit", false)
            ->whereBetween('po_deposits.client_po_date', [$startDate, $endDate])
            ->leftJoin('projects', 'po_deposits.id', '=', 'projects.po_deposit')
            ->groupBy(DB::raw("DATE_FORMAT(po_deposits.client_po_date, " . ($isMonthly ? "'%Y-%m'" : "'%Y-%m-%d'") . ")"))
            ->get()->toArray();

                

            $po_project_2 = PoDeposit::select(
                DB::raw('SUM(CASE WHEN projects.invoice_status = "sent" THEN expense ELSE 0 END) as total_invoice'),
                DB::raw('SUM(expense) as total'),
                DB::raw('SUM(CASE WHEN projects.status IN ("ready","production") THEN expense ELSE 0 END) as total_not_delivered')
            )
            ->where("po_deposits.is_po_deposit", false)
            ->whereBetween('po_deposits.client_po_date', [$startDate, $endDate])
            ->leftJoin('projects', 'po_deposits.id', '=', 'projects.po_deposit')
            ->first()->toArray();

                

            $totalExpenses = PoDeposit::whereBetween('client_po_date', [$startDate, $endDate])
            ->where('expense', '>', 0)
            ->sum('expense');

        $top10ClientsByPercentage = PoDeposit::select(
                DB::raw('client_company'),
                DB::raw('SUM(expense) as total_expense'),
                DB::raw("(SUM(CASE WHEN client_po_date BETWEEN '".$start."' AND '".$end."' THEN expense ELSE 0 END) / $totalExpenses * 100) as percentage")
            )
            ->whereBetween('po_deposits.client_po_date', [$startDate, $endDate])
            ->where('expense', '>', 0)
            ->groupBy('client_company')
            ->orderByDesc('percentage')
            ->limit(10)
            ->get()
            ->toArray();

            

            $results = [
                "po_progress_1"=>$po_progress_1,
                "po_progress_2"=>$po_grand_1,
                "po_deposit_1"=>$po_deposit_1,
                "po_deposit_2"=>$po_deposit_2,
                "po_project_1"=>$po_project_1,
                "po_project_2"=>$po_project_2,
                "top_ten"=>$top10ClientsByPercentage,
            ];
            return response()->json([
                'code' => 200,
                'data' => $results,
            ]);
        // } catch (\Throwable $th) {
        //     return response()->json(['code' => 404, 'data' => []]);
        // }
    }
    private function createProject($request, $podeposit, $real = false){
        // dd($request);
        $list = $request['deposits'];
        if($real) $list = $request['products_group'];
        foreach ($list as $key => $projectData) {
            $project = new Project();
            $project->job_number = $projectData['job_number'] ;
            $project->client_po_number = $projectData['client_po_number'];
            $project->client_po_date = $projectData['client_po_date'];
            $project->client_company = $projectData['client_company'];
            $project->client_pic_name = $projectData['client_pic_name'];
            $project->pic_name = $projectData['pic_name'];
            $project->total_price = $projectData['total_price'];
            $project->po_deposit = $podeposit->id;
            $project->is_po_deposit = $podeposit->is_po_deposit == 'yes';
            $project->is_real = $real;
            $project->status = $projectData['status'];
            $project->title = $projectData['title'] ?? null;
            $project->sent_to_del_at = $projectData['sent_to_del_at'] ?? null;
            $project->save();

            $products = [];
            foreach ($projectData['products'] as $productData) {
                $product = new Product($productData);
                $products[] = $product;
            }
            $productss = $project->products_data()->saveMany($products);

        }
    }
    // public function import(Request $request){
    //     $file = $request->file('file');
    //     $spreadsheet = IOFactory::load($file);
    //     $worksheet = $spreadsheet->getActiveSheet();
    //     $data = $worksheet->toArray();
    //     // dd($data);
        
    //     $assoc = [];
    //     $header = null;

    //     foreach ($data as $row) {
    //         if ($header === null) {
    //             // Assume the first row contains headers
    //             $header = $row;
    //         } else {
    //             $associativeRow = [];
    //             foreach ($row as $index => $cellValue) {
    //                 $headerText = $header[$index];
    //                 $associativeRow[$headerText] = $cellValue;
    //             }
    //             $assoc[] = $associativeRow;
    //         }
    //     }
    //     $po_deposits = [];
    //     foreach ($assoc as $key => $value) {
    //         // dd($value);
    //         if(empty($po_deposits[$value['group_number']])){
    //             $po_deposits[$value['group_number']] = [
    //                 "client_pic_name"=> $value['po_pic'],
    //                 "status"=> $value['group_status'],
    //                 "closed_at"=> $value['closed_at'],
    //                 "client_company"=> $value['client_company'],
    //                 "client_po_number"=> $value['po_number'],
    //                 "client_po_date"=> $value['po_date'],
    //                 "is_po_deposit"=> $value['is_po_deposit'] == 'yes',
    //                 "job_number"=> $value['job_number'],
    //                 "pic_name"=> $value['marketing_pic'],
    //                 "budget" => 0,
    //                 "expense" => 0,
    //                 "balance" => 0,
    //                 "deposits" => [],
    //                 "products_group" => []
    //             ];
    //         }
    //         if($value["product_type"] == "deposit"){
    //             if(empty($po_deposits[$value['group_number']]['deposits'])){
    //                 $po_deposits[$value['group_number']]['deposits'] = [];
    //             }
    //             if(empty($po_deposits[$value['group_number']]['deposits'][$value['po_number']])){
    //                 $po_deposits[$value['group_number']]['deposits'][$value['po_number']] = [
    //                     "client_pic_name"=> $value['po_pic'],
    //                     "status"=> "production",
    //                     "client_company"=> $value['client_company'],
    //                     "client_po_number"=> $value['po_number'],
    //                     "client_po_date"=> $value['po_date'],
    //                     "is_po_deposit"=> $value['is_po_deposit'] == 'yes',
    //                     "job_number"=> $value["is_po_deposit"] == 'yes' ? $value["job_number"].'-CD00'.count($po_deposits[$value['group_number']]['deposits'])+1 : $value["job_number"],
    //                     "pic_name"=> $value['marketing_pic'],
    //                     "title"=> $value['project_title'],
    //                     "is_real"=> false,
    //                     "total_price" => 0
    //                 ];
    //             }
    //             if(empty($po_deposits[$value['group_number']]['deposits'][$value['po_number']]['products'])){
    //                 $po_deposits[$value['group_number']]['deposits'][$value['po_number']]['products'] = [];
    //             }
    //             array_push($po_deposits[$value['group_number']]['deposits'][$value['po_number']]['products'],[
    //                 "name"=> $value['product_name'],
    //                 "description"=> $value['product_description'],
    //                 "price"=> $value['product_price'],
    //                 "quantity"=> $value['product_quantity'],
    //                 "total_price"=> $value['product_subtotal'],
    //                 "is_production"=> $value['is_production'] == 'yes',
    //             ]);
    //             $po_deposits[$value['group_number']]['deposits'][$value['po_number']]['total_price'] = $po_deposits[$value['group_number']]['deposits'][$value['po_number']]['total_price'] + $value['product_subtotal'];

    //             $po_deposits[$value['group_number']]['budget'] = $po_deposits[$value['group_number']]['budget'] + $value['product_subtotal'];
    //             $po_deposits[$value['group_number']]['balance'] = $po_deposits[$value['group_number']]['budget'] - $po_deposits[$value['group_number']]['expense'];
    //         }else{
    //             if(empty($po_deposits[$value['group_number']]['products_group'])){
    //                 $po_deposits[$value['group_number']]['products_group'][0] = [
    //                     "client_pic_name"=> $value['po_pic'],
    //                     "status"=> $value["is_po_deposit"] == 'yes' ? 'production' : 'production',
    //                     "client_company"=> $value['client_company'],
    //                     "client_po_number"=> $value['po_number'],
    //                     "client_po_date"=> $value['po_date'],
    //                     "is_po_deposit"=> $value['is_po_deposit'] == 'yes',
    //                     "job_number"=> $value["is_po_deposit"] == 'yes' ? $value["job_number"].'-001' : $value["job_number"],
    //                     "pic_name"=> $value['marketing_pic'],
    //                     "title"=> $value['project_title'],
    //                     "is_real"=> true,
    //                     "total_price" => 0
    //                 ];
    //             }
    //             if(empty($po_deposits[$value['group_number']]['products_group'][0]['products'])){
    //                 $po_deposits[$value['group_number']]['products_group'][0]['products'] = [];
    //             }
    //             array_push($po_deposits[$value['group_number']]['products_group'][0]['products'],
    //             [    "name"=> $value['product_name'],
    //                 "description"=> $value['product_description'],
    //                 "price"=> $value['product_price'],
    //                 "quantity"=> $value['product_quantity'],
    //                 "total_price"=> $value['product_subtotal'],
    //                 "is_production"=> $value['is_production'] == 'yes',
    //             ]
    //             );
    //             $po_deposits[$value['group_number']]['products_group'][0]['total_price'] = $po_deposits[$value['group_number']]['products_group'][0]['total_price'] + $value['product_subtotal'];
                
    //             $po_deposits[$value['group_number']]['expense'] = $po_deposits[$value['group_number']]['expense'] + $value['product_subtotal'];
    //             if($value['is_po_deposit'] == 'yes'){
    //                 $po_deposits[$value['group_number']]['balance'] = $po_deposits[$value['group_number']]['budget'] - $po_deposits[$value['group_number']]['expense'];
    //             }
    //         }
    //     }

    //     foreach ($po_deposits as $key => $item) {
    //         $item['deposits'] = array_values($item['deposits']);
    //         $purchase_orders = null;
    //         if(!empty($item->deposits)){
    //             foreach ($item->deposits as $key => $po) {
    //                 array_push($purchase_orders, [
    //                     "client_po_number" => $po['client_po_number'],
    //                     "client_po_date" => $po['client_po_date'],
    //                     "client_pic_name" => $po['client_pic_name'],
    //                     "total_price" => $po['total_price']
    //                 ]);
    //             }
    //             $purchase_orders = json_encode($purchase_orders);
    //         }
    //         $podeposit = PoDeposit::create([
    //             "job_number" => $item['job_number'],
    //             "pic_name" => $item['pic_name'],
    //             "client_po_number" => $item['client_po_number'],
    //             "client_po_date" => $item['client_po_date'],
    //             "client_company" => $item['client_company'],
    //             "client_pic_name" => $item['client_pic_name'],
    //             "purchase_ordres" => $purchase_orders,
    //             "is_po_deposit" => $item['is_po_deposit'] == 'yes',
    //             "budget" => $item['budget'],
    //             "expense" => $item['expense'],
    //             "balance" => $item['balance'],
    //             "status" => $item['status'],
    //         ]);

    //         $this->createProject($item, $podeposit, false);
    //         $this->createProject($item, $podeposit, true);
    //     }

    //     // Print the resulting JSON
    //     return $po_deposits;
    // }
    


    public function import(Request $request){
        $csvFile = $request->file('file');
        $data = $this->convertCSVToArray($csvFile);
        $dd = [];
        foreach ($data as $key => $row) {
            $po = PoDeposit::where("job_number",$row['job_number'])->first();
            if(!empty($po)){
                if(!empty($row['price'])){
                    $po->expense = intval($row['price']);
                }
                if(!empty($row['invoice_status'])){
                    $po->invoice_status = $row['invoice_status'];
                }
                if(!empty($row['invoice_number'])){
                    $po->invoices = json_encode([["invoice_number"=>$row['invoice_number'],"invoice_date"=>date_format(date_create($row['invoice_date']),"Y-m-d")]]);
                    InvoiceProgress::create([
                        "po_deposit"=>$po->id,
                    "pic"=>$row['pic'],
                    "status"=>$row['invoice_status'],
                    "note"=>"Import data"
                ]);
                $po->save();
            }
        }
        $pr = Project::where("job_number",$row['job_number'])->first();
        if(!empty($pr)){
                if(!empty($row['price'])){
                $pr->total_price = intval($row['price']);
                }
                $pr->save();
            }
            // $dd[] = ["po"=>$po,"pr"=>$pr];
        }
        return response()->json([
            'code' => 200,
            'data' => $dd,
        ]);
    }
    private function convertCSVToArray($csvFile)
    {
        $csvData = file_get_contents($csvFile->getRealPath());
        $rows = explode("\n", $csvData);
        $data = [];
        $header = str_getcsv(array_shift($rows));
        foreach ($rows as $row) {
            $rowData = str_getcsv($row);
            if (count($rowData) === count($header)) {
                $data[] = array_combine($header, $rowData);
            }
        }
        return $data;
    }

}
