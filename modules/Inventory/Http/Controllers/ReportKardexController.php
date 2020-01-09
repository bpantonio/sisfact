<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade as PDF;
use Modules\Inventory\Exports\KardexExport;
use Illuminate\Http\Request;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Company;
use App\Models\Tenant\Kardex;
use App\Models\Tenant\Item;
use Carbon\Carbon;
use Modules\Inventory\Models\InventoryKardex;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Http\Resources\ReportKardexCollection;


class ReportKardexController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    protected $models = [
        "App\Models\Tenant\Document", 
        "App\Models\Tenant\Purchase", 
        "App\Models\Tenant\SaleNote", 
        "Modules\Inventory\Models\Inventory"
    ];

    public function index() {
         
            
        return view('inventory::reports.kardex.index');
    }
    

    public function filter() {

        $items = Item::query()->whereNotIsSet()
            ->where([['item_type_id', '01'], ['unit_type_id', '!=','ZZ']])
            ->latest()
            ->get()->transform(function($row) {
                $full_description = $this->getFullDescription($row);
                return [
                    'id' => $row->id,
                    'full_description' => $full_description,
                    'internal_id' => $row->internal_id,
                    'description' => $row->description, 
                ];
            });
        
        return compact('items');
    }
      

    public function records(Request $request)
    {
        $records = $this->getRecords($request->all());

        return new ReportKardexCollection($records->paginate(config('tenant.items_per_page')));
    }
 


    public function getRecords($request){
 
        $item_id = $request['item_id']; 
        $date_start = $request['date_start'];
        $date_end = $request['date_end']; 
 
        $records = $this->data($item_id, $date_start, $date_end);

        return $records;

    }


    private function data($item_id, $date_start, $date_end)
    {
         
        $warehouse = Warehouse::where('establishment_id', auth()->user()->establishment_id)->first();
 
        if($date_start && $date_end){

            $data = InventoryKardex::with(['inventory_kardexable'])
                        ->where([['item_id', $item_id],['warehouse_id', $warehouse->id]])      
                        ->whereBetween('date_of_issue', [$date_start, $date_end])  
                        ->orderBy('id');

        }else{

            $data = InventoryKardex::with(['inventory_kardexable'])
                        ->where([['item_id', $item_id],['warehouse_id', $warehouse->id]])                                         
                        ->orderBy('id');
        }
       
        return $data;
        
    }



    public function getFullDescription($row){

        $desc = ($row->internal_id)?$row->internal_id.' - '.$row->description : $row->description;
        $category = ($row->category) ? " - {$row->category->name}" : "";
        $brand = ($row->brand) ? " - {$row->brand->name}" : "";

        $desc = "{$desc} {$category} {$brand}";

        return $desc;
    }



    /**
     * PDF
     * @param  Request $request
     * @return \Illuminate\Http\Response
     */
    public function pdf(Request $request) {

        $balance = 0;
        $company = Company::first();
        $establishment = Establishment::first();
        $d = $request->date_start;
        $a = $request->date_end;
        $item_selected = $request->item_id;

        $warehouse = Warehouse::where('establishment_id', auth()->user()->establishment_id)->first();

        if($d && $a){

            $reports = InventoryKardex::with(['inventory_kardexable'])
                                        ->where([['item_id', $request->item_id],['warehouse_id', $warehouse->id]])      
                                        ->whereBetween('date_of_issue', [$d, $a])                                   
                                        ->orderBy('id')                                  
                                        ->get();

        }else{

            $reports = InventoryKardex::with(['inventory_kardexable'])
                                        ->where([['item_id', $request->item_id],['warehouse_id', $warehouse->id]])  
                                        ->orderBy('id')     
                                        ->get();
        }

        $models = $this->models;
        
        $pdf = PDF::loadView('inventory::reports.kardex.report_pdf', compact("reports", "company", "establishment", "balance","models", 'a', 'd',"item_selected"));
        $filename = 'Reporte_Kardex'.date('YmdHis');
        
        return $pdf->download($filename.'.pdf');
    }
    
    /**
     * Excel
     * @param  Request $request
     * @return \Illuminate\Http\Response
     */
    public function excel(Request $request) {

        $balance = 0;
        $company = Company::first();
        $establishment = Establishment::first();
        $d = $request->date_start;
        $a = $request->date_end;
       
        $warehouse = Warehouse::where('establishment_id', auth()->user()->establishment_id)->first();

        if($d && $a){

            $records = InventoryKardex::with(['inventory_kardexable'])
                                        ->where([['item_id', $request->item_id],['warehouse_id', $warehouse->id]])      
                                        ->whereBetween('date_of_issue', [$d, $a])                                   
                                        ->orderBy('id')                                  
                                        ->get();

        }else{

            $records = InventoryKardex::with(['inventory_kardexable'])
                                        ->where([['item_id', $request->item_id],['warehouse_id', $warehouse->id]])  
                                        ->orderBy('id')     
                                        ->get();
        }

        $models = $this->models;
        
        return (new KardexExport)
            ->balance($balance)
            ->records($records)
            ->models($models)
            ->company($company)
            ->establishment($establishment)
            ->download('ReporteKar'.Carbon::now().'.xlsx');
    }


    
 
    // public function search(Request $request) { 
    //     //return $request->item_selected;
    //     $balance = 0;
    //     $d = $request->d;
    //     $a = $request->a;
    //     $item_selected = $request->item_selected;

    //     $items = Item::query()->whereNotIsSet()
    //         ->where([['item_type_id', '01'], ['unit_type_id', '!=','ZZ']])
    //         ->latest()
    //         ->get();
        
    //     $warehouse = Warehouse::where('establishment_id', auth()->user()->establishment_id)->first();

    //     if($d && $a){

    //         $reports = InventoryKardex::with(['inventory_kardexable'])
    //                     ->where([['item_id', $request->item_selected],['warehouse_id', $warehouse->id]])      
    //                     ->whereBetween('date_of_issue', [$d, $a])                                   
    //                     ->orderBy('id')                                  
    //                     ->paginate(config('tenant.items_per_page'));

    //     }else{

    //         $reports = InventoryKardex::with(['inventory_kardexable'])
    //                     ->where([['item_id', $request->item_selected],['warehouse_id', $warehouse->id]])                                         
    //                     ->orderBy('id')                                  
    //                     ->paginate(config('tenant.items_per_page'));

    //     }

    //     //return json_encode($reports);
        
    //     $models = $this->models;
        
    //     return view('inventory::reports.kardex.index', compact('items', 'reports', 'balance','models', 'a', 'd','item_selected'));
    // }
    
}
