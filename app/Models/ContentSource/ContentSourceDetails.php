<?php

namespace App\Models\ContentSource;

use App\Models\Model;
use DB;

class ContentSourceDetails extends Model
{
    public function getTable(){
        return $this->table = config('tables.content_source_details');
    }

    protected $primaryKey = 'content_source_id'; //Changed default primary key into our supplier_details table supplier_id.

    protected $fillable = [
        'gds_product', 'created_by', 'updated_by','gds_country_code','content_source_settings'
    ];

    public static function getContentSourceData($contentSourceId)
    {
        $getContentSourceData = ContentSourceDetails::select('content_source_details.gds_product', 'content_source_details.gds_source', 'content_source_details.gds_source_version', 'content_source_details.default_currency', 'content_source_details.allowed_currencies', 'content_source_details.gds_timezone', 'content_source_details.pcc_type', 'content_source_details.pcc', 
            'supplier_products.fare_types', 'supplier_products.services')
                ->join('supplier_products', 'supplier_products.content_source_id', '=', 'content_source_details.content_source_id')
                ->where('content_source_details.content_source_id', '=', $contentSourceId)
                ->first();

        return $getContentSourceData;
    }

    //get mapped content source ref key
    public static function getAllCsRefKey($gdsSupplier, $gdsProduct, $gdsSource){
        $contentSourceData = ContentSourceDetails::select('content_source_details.content_source_ref_key', 'sp.content_source_id', 'sp.services')
            ->join('supplier_products As sp', 'sp.content_source_id', '=', 'content_source_details.content_source_id')
            ->where('content_source_details.account_id', $gdsSupplier)
            ->where('content_source_details.gds_product', $gdsProduct)
            ->where('content_source_details.gds_source', $gdsSource)
            ->whereIn('content_source_details.status',['A'])
            ->get()->toArray();
        
        $getCsArray = array();
        foreach(config('common.common_services') as $serviceKey => $serviceVal){        
            foreach ($contentSourceData as $key => $value) {
            $status = self::checkServiceTypeOn(json_decode($value['services']), $serviceKey);
                if($status){
                    $getCsArray[$serviceKey][] = $value['content_source_ref_key'];                    
                }
            }
        }

        return $getCsArray;;
    }

    public static function checkServiceTypeOn($services, $service)
    {   
        foreach ($services as $key => $value) {  
            if($key == $service){
                if($value->type == 'ON'){
                    return true;
                }else{
                    return false;
                }
            }
        }        
    }

    public static function checkTypeOnorOff($csId){
        $checkDb = DB::table(config('tables.content_source_details').' As csd')
                ->join(config('tables.supplier_products').' As sp', 'sp.content_source_id', '=', 'csd.content_source_id')
                ->select('sp.services')
                ->where('csd.content_source_id', $csId)
                ->first();
        return $checkDb;
    }
    
}