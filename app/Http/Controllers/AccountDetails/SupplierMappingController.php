<?php
namespace App\Http\Controllers\AccountDetails;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\SupplierDetails\SupplierDetails;
use App\Models\AccountDetails\PartnerMapping;
use App\Models\AccountDetails\AccountDetails;
use App\Libraries\Common;
use DB;

class SupplierMappingController extends Controller
{   
    public function index($accountId){
        $responseData                       = array();
        $accountId                          = decryptData($accountId);
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'supplier_mapping_data_retrieved_success';
        $responseData['message']            = __('accountDetails.supplier_mapping_data_retrieved_success');
        $consumerAccount                    = AccountDetails::getAccountDetails();
        foreach($consumerAccount as $key => $value){
            $tempData                   = array();
            $tempData['account_id']     = $key;
            $tempData['account_name']   = $value;
            $responseData['data']['account_details'][] = $tempData ;
        }
        $responseData['data']['account_name'] = AccountDetails::where('account_id',$accountId)->value('account_name');

        $responseData['data']['account_details'] = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$responseData['data']['account_details']);
                
        return response()->json($responseData);
    }

    public function supplierList(Request $request){  
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'supplier_mapping_data_retrieve_failed';
        $responseData['message']        = __('accountDetails.supplier_mapping_data_retrieve_failed');

        $requestData    = $request->all();
        $accountId      = isset($requestData['account_id'])? decryptData($requestData['account_id']):''; 
        $data = array(); 

        //get mapped supplier      
        $partnerMappingList = DB::table(config('tables.agency_mapping').' AS am')
                                ->join('account_details As ad', 'ad.account_id', '=', 'am.supplier_account_id')
                                ->where('am.account_id',$accountId)
                                ->where('am.supplier_account_id','!=',$accountId);
        //filter
        if((isset($requestData['query']['supplier_account_id']) && $requestData['query']['supplier_account_id'] != '') || (isset($requestData['supplier_account_id']) && $requestData['supplier_account_id'] != '')){
            $supplierAccountName = (isset($requestData['query']['supplier_account_id']) && $requestData['query']['supplier_account_id'] != '') ?$requestData['query']['supplier_account_id']:$requestData['supplier_account_id'];
            $partnerMappingList = $partnerMappingList->where('ad.account_name','like','%'.$supplierAccountName.'%');
        }

        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            if($requestData['orderBy'] == 'supplier_acount_name')
            {
                $partnerMappingList = $partnerMappingList->orderBy('ad.account_name',$sorting);
            }
            else
            {
                $partnerMappingList = $partnerMappingList->orderBy($requestData['orderBy'],$sorting);
            }
        }else{
            $partnerMappingList = $partnerMappingList->orderBy('am.updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit'])- $requestData['limit'];                  
        //record count
        $partnerMappingListCount  = $partnerMappingList->take($requestData['limit'])->count();
        // Get Record
        $partnerMappingList       = $partnerMappingList->offset($start)->limit($requestData['limit'])->get();    

        if(count($partnerMappingList) > 0){
            $responseData['status']                     = 'success';
            $responseData['status_code']                = config('common.common_status_code.success');
            $responseData['short_text']                 = 'supplier_mapping_data_retrieved_success';
            $responseData['message']                    = __('accountDetails.supplier_mapping_data_retrieved_success');
            $partnerMappingList = json_decode($partnerMappingList,true);

            $responseData['data']['records_total']      = $partnerMappingListCount;
            $responseData['data']['records_filtered']   = $partnerMappingListCount;
            foreach($partnerMappingList as $value){
                $tempData                               = array();
                $tempData['si_no']                      = ++$start;
                $tempData['id']                         = encryptData($value['agency_mapping_id']);
                $tempData['agency_mapping_id']          = encryptData($value['agency_mapping_id']);
                $tempData['account_id']                 = $value['account_id'];
                $tempData['supplier_account_id']        = $value['supplier_account_id'];
                $tempData['supplier_acount_name']       = $value['account_name'];
                $responseData['data']['records'] []     = $tempData;
            }
        }else{
            $responseData['errors']                     = ["error" => __('common.recored_not_found')];
        }

        return response()->json($responseData);
    }

    public function create($accountId){  
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'supplier_mapping_data_retrieved_success';
        $responseData['message']        = __('accountDetails.supplier_mapping_data_retrieved_success');
       
        $accountId                      = isset($accountId)? decryptData($accountId):''; 
        //get mapped supplier      
        $partnerMapping                 = PartnerMapping::partnerMappingList($accountId);
        $mappingIds     = array();
        foreach ($partnerMapping as $mappingData) {
                $mappingIds[] = $mappingData->supplier_account_id;
        } 

        //get all supplier       
        $partnerAll = AccountDetails::getAccountDetails(config('common.partner_account_type_id'));        
        $partnerArray = array();
        foreach ($partnerAll as $key => $value) {
            $partnerLoopData = array();
            if($key != $accountId){ //login account not include the list                
                if(!in_array($key, $mappingIds)){
                    $partnerLoopData  = array(
                    'account_id'     => $key,
                    'account_name'   => $value
                    );
                } 
                if(!empty($partnerLoopData)){
                    array_push($partnerArray, $partnerLoopData);
                }
            }                      
            
        }
        $responseData['data']['partner_details']        = $partnerArray;
        $responseData['data']['supplier_account_name']  = AccountDetails::getAccountName($accountId);
        $responseData['data']['supplier_account_id']    = $accountId;
        
        return response()->json($responseData);
    }

    public function store(Request $request){    
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'supplier_mapping_data_store_failed';
        $responseData['message']        = __('accountDetails.supplier_mapping_data_store_failed');

        $requestData                    = $request->all();    
        $requestData                    = isset($requestData['agency_mapping']) ? $requestData['agency_mapping'] : '';  
        if($requestData != ''){
            $requestData['created_at']      = Common::getDate();
            $requestData['updated_at']      = Common::getDate();
            $requestData['created_by']      = Common::getUserID();
            $requestData['updated_by']      = Common::getUserID(); 
            $requestData['account_id']      = $requestData['account_id'];
            $supplierMappingId              = PartnerMapping::create($requestData)->agency_mapping_id;

            if($supplierMappingId){
                
                $responseData['status']             = 'success';
                $responseData['status_code']        = config('common.common_status_code.success');
                $responseData['short_text']         = 'supplier_mapping_data_stored_success';
                $responseData['message']            = __('accountDetails.supplier_mapping_data_stored_success');

                //to process log entry
                $newGetOriginal = PartnerMapping::find($supplierMappingId)->getOriginal();
                Common::prepareArrayForLog($requestData['account_id'],'Supplier Mapping Created',(object)$newGetOriginal,config('tables.agency_mapping'),'supplier_mapping');

            }else{
                $responseData['errors'] = ["error" => __('accountDetails.problem_saving_data')];
            }
        }else{
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }

        return response()->json($responseData);              
    }
  
    public function delete(Request $request){  
        $responseData                   = array(); 
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'supplier_mapping_data_delete_failed';
        $responseData['message']        = __('accountDetails.supplier_mapping_data_delete_failed');

        $requestData                    = $request->all();    
        $requestData                    = isset($requestData['agency_mapping'])?$requestData['agency_mapping']:'';
        $agencyMappingId                = decryptData($requestData['agency_mapping_id']);
        if($requestData != ''){
            $partnerMappingData         = PartnerMapping::where('agency_mapping_id', $agencyMappingId )->first(); 
            $delete                     = PartnerMapping::where('agency_mapping_id', $agencyMappingId )->delete(); 
            if($delete){
                $responseData['status']             = 'success';
                $responseData['status_code']        = config('common.common_status_code.success');
                $responseData['short_text']         = 'supplier_mapping_data_deleted_success';
                $responseData['message']            = __('accountDetails.supplier_mapping_data_deleted_success');
                //to process log entry
                $newGetOriginal = array('account_id' => $partnerMappingData['account_id'], 'supplier_account_id' => $partnerMappingData['supplier_account_id']);
                Common::prepareArrayForLog($partnerMappingData['account_id'],'Supplier Mapping Deleted',(object)$newGetOriginal,config('tables.agency_mapping'),'supplier_mapping');
            }else{
                $responseData['errors'] = ['error'=>__('common.recored_not_found')];
            }
        }else{
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }
        return response()->json($responseData);              
    }

    public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.agency_mapping');
        $inputArray['activity_flag']    = 'supplier_mapping';
        $responseData = Common::showHistory($inputArray);
        return response()->json($responseData);
    }

}//eoc