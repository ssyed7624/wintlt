<?php

namespace App\Http\Controllers\Common;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Models\ImportPnr\ImportPnrMapping;
use Log;

class OpenController extends Controller
{

    public function getAirports(Request $request)
    {

        $start = microtime(true);

        $inputArray                     = $request->all();        
        $code                           = isset($inputArray['term']) ? $inputArray['term'] : '';
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'recored_not_found';
        $responseData['message']        = __('airportManagement.airport_data_retreive_failed');
        
        $airportListData                = [];

        if(strlen($code) >= 3 && $code != ''){
            
            $code = strtoupper($code);
           
            $airportListData                = self::getAirportList($code); 
                        
            if(count($airportListData) > 0){
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['short_text']     = 'airport_data_retrieved_successfully';
                $responseData['message']        = __('airportManagement.airport_data_retrieved_successfully');
                $responseData['data']           = $airportListData;
            }else{
                $responseData['errors']         = ["error" => __('common.recored_not_found')];
            }
        }
        else{
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['short_text']     = 'airport_Code_length_validation';
            $responseData['errors']         = ["error" => __('airportManagement.airport_Code_length_validation')];
        }
        
        if(config('common.add_res_time')){
            $responseData['resTime']                            = (microtimeFloat()-START_TIME);
        }
           
        return response()->json($responseData);
    }

    public static function getAirportList($code = '') {

        $content = File::get(storage_path('airportcitycode.json'));
        $airport = json_decode($content, true);
        
        $res = [];
        $isCode = [];

        $list = explode(',', $code);
        if (count($list) > 1) {
            foreach($list as $airCode) {
                if(!isset($airport[$airCode]))continue;
                $air = explode("|",$airport[$airCode]);
                $res[$airCode] = array(
                    'value' => ( $air[2] ? $air[2] : $air[1] ) .' ('. $air[0] .')',
                    'label' => ( $air[2] ? $air[2] : $air[1] ) .' ('. $air[0] .')',
                    'airport_code' => $air[0],
                    'airport_name' => $air[1],
                    'city' => $air[2],
                    'state_code' => $air[3],
                    'state' => $air[4],
                    'country_code' => $air[5],
                    'country' => $air[6],
                );
            }
            return $res;
        }

        if(strlen($code) == 3 && isset($airport[$code])) {
            $air = explode('|',$airport[$code]);
            // $result = [];
            $isCode = $res[] = array(
                'value' => ( $air[2] ? $air[2] : $air[1] ) .' ('. $air[0] .')',
                'airport_code' => $air[0],
                'airport_name' => $air[1],
                'city' => $air[2],
                'state_code' => $air[3],
                'state' => $air[4],
                'country_code' => $air[5],
                'country' => $air[6],
            );
        }
        
        foreach( $airport as $key => $value ){
            if( stripos( $value, $code ) !== false && sizeof($res) < 25 ) {
                $air = explode('|',$value);
                $temp = array(
                    'value' => ( $air[2] ? $air[2] : $air[1] ) .' ('. $air[0] .')',
                    'airport_code' => $air[0],
                    'airport_name' => $air[1],
                    'city' => $air[2],
                    'state_code' => $air[3],
                    'state' => $air[4],
                    'country_code' => $air[5],
                    'country' => $air[6],
                );
                if($isCode != $temp) {
                    $res[] = $temp;
                }
            }
            else if(sizeof($res) > 10) {
                break;
            }
        }
        return $res;
        
    }



    public function getPnrPccDetails(Request $request){
        $responseData           = [];
        $responseData['status'] = 'failed';
        $requestData            = $request->all();        

        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'retrived_supplier_list';
        $responseData['message']            = 'GDS Data Retrived';
        
        $responseData['data']   = [];


        $accountId              = isset($requestData['account_id']) ? $requestData['account_id'] : '';
        $supplierAccountId      = isset($requestData['supplier_account_id']) ? $requestData['supplier_account_id'] : '';         

        $supplierDetails        = ImportPnrMapping::select('gds_source', 'pcc', 'content_source_id')->where('account_id',$accountId)->where('supplier_account_id',$supplierAccountId)->get();

        if(count($supplierDetails) >0){
            $responseData['status'] = 'success';
            $responseData['data']   = $supplierDetails;
        }
        
        return response()->json($responseData);
    }



}
