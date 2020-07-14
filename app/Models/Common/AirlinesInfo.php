<?php

namespace App\Models\Common;

use App\Models\Model;
use Illuminate\Support\Facades\File;
use App\Models\ContentSource\ContentSourceDetails;

class AirlinesInfo extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.airlines_info');
    }

    protected $primaryKey = 'airline_id';
    public $timestamps = false;

    protected $fillable = [
        'airline_code','airline_name','airline_country','is_active','is_enabled','pref_enabled','add_info','is_deleted'
    ];

    public static function getAirlinesDetails()
    {	
    	$airlineCode   = array();
        $airlineDetail = AirlinesInfo::where('status','A')->get();
        
    	foreach ($airlineDetail as $key => $value) {
    		$airlineCode[$value['airline_code']] = $value['airline_name'];
        }
        
        $file_path          = storage_path('airlines.json');
        if(File::exists($file_path)){
            unlink($file_path);            
        }
        if(!File::exists($file_path)) {
            file_put_contents($file_path, json_encode($airlineCode));
        }
    	return $airlineCode;
    }

    public static function getAllAirlinesInfo(){
        return AirlinesInfo::select('airline_code','airline_name','airline_country','airline_country_code')->where('status','A')->orderBy('airline_name','ASC')->get();
    }
    public static function getAirlinInfoUsingContent($contentSourceId = 0){
        $airlineInfo = [];

        $contentSource = ContentSourceDetails::select('account_id','content_source_id','in_suffix', 'gds_product', 'gds_source','gds_source_version', 'default_currency','pcc')->where('content_source_id', $contentSourceId)->where('status', 'A')->first();

        if($contentSource != null){
            //Check Hotel and Insurance - Return Empty
            if(isset($contentSource->gds_product) && ($contentSource->gds_product == 'Hotel' || $contentSource->gds_product == 'Insurance')){
                return $airlineInfo;
            }

            if($contentSource){
                $configProduct = config('common.Products.product_type');
                if(!isset($configProduct[$contentSource->gds_product][$contentSource->gds_source]['aggregaters']['ALL'])){
                    if(isset($configProduct[$contentSource->gds_product][$contentSource->gds_source]['aggregaters'])){
                        $airlineData = $configProduct[$contentSource->gds_product][$contentSource->gds_source]['aggregaters'];
                        foreach ($airlineData as $code => $airlineName) {
                            $airlineInfo[$code] = $airlineName.' ('.$code.')';
                        }
                    }                
                }
            }
            if(count($airlineInfo) == 0){
                $airlineData = AirlinesInfo::getAllAirlinesInfo();
                foreach ($airlineData as $key => $value) {
                    $airlineInfo[$value['airline_code']] = $value['airline_name'].' ('.$value['airline_code'].')';
                }
            }
        }
        return $airlineInfo;
    }
}
