<?php

namespace App\Http\Controllers\AirlineBlocking;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CommonController;
use Illuminate\Http\Request;
use App\Models\AirlineBlocking\SupplierAirlineBlockingPartnerMapping;
use App\Models\AirlineBlocking\SupplierAirlineBlockingTemplates;
use App\Models\AirlineBlocking\SupplierAirlineBlockingRules;
use App\Models\UserDetails\UserDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\AccountDetails\PartnerMapping;
use App\Models\PortalDetails\PortalDetails;
use App\Libraries\RedisUpdate;
use App\Libraries\Common;
use Validator;

class SupplierAirlineBlockingTemplatesController extends Controller
{
    public  function index()
    {
        $responseData                       = array();
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['message']            = __('airlineBlocking.supplier_airline_blocking_template_data_retrive_success');
        $data['status']                     = config('common.status');
        $data['template_type']              =   config('common.airline_booking_template_type');
        $data['account_details']            =   AccountDetails::getAccountDetails();
        $responseData['data']               = $data;
        return response()->json($responseData);

    }
    public  function getList(Request $request)
    {
        $responseData                 = array();
        $responseData['status_code']  = config('common.common_status_code.success');
        $responseData['message']      = __('airlineBlocking.supplier_airline_blocking_template_data_retrive_success');
        $accountIds = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $accountIds[] = 0;
        $airlineBlockingData          =   SupplierAirlineBlockingTemplates::from(config('tables.supplier_airline_blocking_templates').' As sa')->select('sa.*','ad.account_name')->where('sa.status','!=','D')->leftjoin(config('tables.account_details').' As ad','ad.account_id','sa.account_id')->whereIn('ad.account_id',$accountIds);

        $reqData    =   $request->all();

        if(isset($reqData['template_name']) && $reqData['template_name'] != '' && $reqData['template_name'] != 'ALL'|| isset($reqData['query']['template_name'])  && $reqData['query']['template_name'] != '' && $reqData['query']['template_name'] != 'ALL')
        {
            $airlineBlockingData   =   $airlineBlockingData->where('sa.template_name','like','%'.(!empty($reqData['template_name']) ? $reqData['template_name'] : $reqData['query']['template_name'] ).'%');
        }
        if(isset($reqData['template_type']) && !empty($reqData['template_type']) && $reqData['template_type'] != 'ALL' || isset($reqData['query']['template_type']) && !empty($reqData['query']['template_type']) && $reqData['query']['template_type'] != 'ALL' )
        {
            $airlineBlockingData   =   $airlineBlockingData->where('sa.template_type',!empty($reqData['template_type']) ? $reqData['template_type'] : $reqData['query']['template_type']);
        }
        if(isset($reqData['account_id']) && !empty($reqData['account_id']) && $reqData['account_id'] != 'ALL' || isset($reqData['query']['account_id']) && !empty($reqData['query']['account_id']) && $reqData['query']['account_id'] != 'ALL' )
        {
            $airlineBlockingData   =   $airlineBlockingData->whereIN('sa.account_id',(!empty($reqData['account_id']) ? $reqData['account_id'] : $reqData['query']['account_id']));
        }
        if(isset($reqData['status'] ) && $reqData['status'] != '' && $reqData['status'] != 'ALL' || isset($reqData['query']['status']) &&  $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL')
        {
            $airlineBlockingData   =   $airlineBlockingData->where('sa.status',(!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']));
        }

        if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
            $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
            $airlineBlockingData   =   $airlineBlockingData->orderBy($reqData['orderBy'],$sorting);
        }else{
           $airlineBlockingData    =$airlineBlockingData->orderBy('airline_blocking_template_id','DESC');
        }
        $airlineBlockingDataCount                   = $airlineBlockingData->take($reqData['limit'])->count();
        if($airlineBlockingDataCount > 0)
        {
        $responseData['data']['records_total']      = $airlineBlockingDataCount;
        $responseData['data']['records_filtered']   = $airlineBlockingDataCount;
        $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
        $count                                      = $start;
        $airlineBlockingData                        = $airlineBlockingData->offset($start)->limit($reqData['limit'])->get();
        
        foreach($airlineBlockingData as $key => $listData)
            {
                $tempArray = array();

                $tempArray['si_no']                         =   ++$count;
                $tempArray['id']                            =   $listData['airline_blocking_template_id'];
                $tempArray['airline_blocking_template_id']  =   encryptData($listData['airline_blocking_template_id']);
                $tempArray['template_name']                 =   $listData['template_name'];
                $tempArray['template_type']                 =   config('common.airline_booking_template_type.'.$listData['template_type']);
                $tempArray['partner_name']                  =   $listData['accountDetails']['account_name'];
                $tempArray['account_id']                    =   $listData['account_id'];
                $tempArray['status']                        =   $listData['status'];
                $responseData['data']['records'][]          =   $tempArray;
            }

            $responseData['status']         =   'success';
        }
        else
        {
            $responseData['status_code']    = config('common.common_status_code.failed');
            $responseData['message']        = __('airlineBlocking.supplier_airline_blocking_template_data_retrive_failed');
            $responseData['errors']         =   ["error" => __('common.recored_not_found')];
            $responseData['status']         =   'failed';
        }
        return response()->json($responseData);


    }

    public function create()
    {
        $responseData                       = array();
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['message']            = __('airlineBlocking.supplier_airline_blocking_template_data_retrive_success');
        $responseData['template_type']      =   config('common.airline_booking_template_type');
        $responseData['account_details']    =   AccountDetails::getAccountDetails();
        return response()->json($responseData);
    }
    
    public function store(Request $request)
    {
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['message']        = __('airlineBlocking.supplier_airline_blocking_template_data_store_failed');
        $reqData                    = $request->all();
        $reqData                    = isset($reqData['supplier_airline_blocking_templates'])?$reqData['supplier_airline_blocking_templates']:'';
        
        $rules          =   [
            'account_id'        =>  'required',
            'template_name'     =>  'required',
            'template_type'     =>  'required',
            'partner_type'      =>  'required',
        ];
        if(isset($reqData['partner_type']) && $reqData['partner_type'] == "SPECIFICPARTNER")
        {
            $rules          +=   [
                'partner_mapping'        =>  'required',
            ]; 
        }


        $message        =   [
            'account_id.required'            => __('airlineBlocking.supplier_airline_blocking_account_id_required'),          
            'template_name.required'         => __('airlineBlocking.supplier_airline_blocking_template_name_required'),           
            'template_type.required'         => __('airlineBlocking.supplier_airline_blocking_template_type_required'),           
            'partner_type.required'          => __('airlineBlocking.supplier_airline_blocking_partner_type_required'),        
            'partner_mapping.required'    => __('common.this_field_is_required'),                
        ];
        $validator = Validator::make($reqData, $rules, $message);
        
         if ($validator->fails()) {
             $responseData['status_code']        =  config('common.common_status_code.validation_error');
            $responseData['message']             =  'The given data was invalid';
            $responseData['errors']              =  $validator->errors();
            $responseData['status']              =  'failed';
             return response()->json($responseData);
         }

        $supplierAirlineBlockingTemplates = new SupplierAirlineBlockingTemplates();
        $saveAirlineBlockingDatas = $this->saveSupplierAirlineBlockingTemplatesData($reqData,$supplierAirlineBlockingTemplates,'store');

        if($saveAirlineBlockingDatas)
        {
            $newSupplierGetOriginal = SupplierAirlineBlockingTemplates::find($saveAirlineBlockingDatas)->getOriginal();
            Common::prepareArrayForLog($saveAirlineBlockingDatas,'Supplier Airline Blocking Template',(object)$newSupplierGetOriginal,config('tables.supplier_airline_blocking_templates'),'supplier_airtline_blocking_template_management');

            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['message']        = __('airlineBlocking.supplier_airline_blocking_template_data_stored_success');
            $responseData['status']         = 'success';
         }
        return response()->json($responseData);

    }

    public function edit($id)
    {
        $id     =   decryptData($id);
        $responseData                                   =   array();
        $responseData['status_code']                    =   config('common.common_status_code.success');
        $responseData['message']                        =   __('airlineBlocking.supplier_airline_blocking_template_data_retrived_success');
        $airlineBlockingData                            =   SupplierAirlineBlockingTemplates::find($id);
        if($airlineBlockingData)
        {
            
        $airlineMappingData                             =   SupplierAirlineBlockingPartnerMapping::where('airline_blocking_template_id',$id)->get();
        $airlineBlockingData['encrypt_airline_blocking_template_id'] =   encryptData($airlineBlockingData['airline_blocking_template_id']);
        $airlineBlockingData['created_by']              =   UserDetails::getUserName($airlineBlockingData['created_by'],'yes');
        $airlineBlockingData['updated_by']              =   UserDetails::getUserName($airlineBlockingData['updated_by'],'yes');
        $responseData['data']['airline_blocking_data']  =   $airlineBlockingData;
        $responseData['data']['airline_mapping_data']   =   $airlineMappingData;
        $partnerDetails                                 =   PartnerMapping::consumerList($airlineBlockingData->account_id);
        $responseData['data']['partner_details']        =   $partnerDetails;
        foreach($airlineMappingData as $portalData)
        {
            $portalId   =   $portalData->partner_account_id;
            $responseData['data']['portal_details'] []  =   PortalDetails::getPortalList($portalId);
        }
        $responseData['data']['template_type']          =   config('common.airline_booking_template_type');
        $responseData['data']['account_details']        =   AccountDetails::getAccountDetails();
        $responseData['status']                         = 'success';
    }
    else
    {
        $responseData['status_code']                    =   config('common.common_status_code.failed');
        $responseData['message']                        =   __('airlineBlocking.supplier_airline_blocking_template_data_retrieve_failed');
        $responseData['status']                         = 'failed';

    }
        return response()->json($responseData);
    }
    public function update(Request $request)
    {
        $responseData                   = array();
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['message']        = __('airlineBlocking.supplier_airline_blocking_template_data_update_failed');
        $responseData['status']         = 'failed';
        $reqData                        = $request->all();
        $reqData                        = isset($reqData['supplier_airline_blocking_templates'])?$reqData['supplier_airline_blocking_templates']:'';
        $id                             =  decryptData($reqData['airline_blocking_template_id']);
        $update                         =   SupplierAirlineBlockingTemplates::find($id);
        if(!$update)
        {
            $responseData['status_code']        = config('common.common_status_code.failed');
            $responseData['message']            = __('airlineBlocking.supplier_airline_blocking_rules_data_update_failed');
            $responseData['status']             =   'failed';
            return  $responseData;
        }
        $oldOriginalTemplate = SupplierAirlineBlockingTemplates::find($id)->getOriginal();

        $rules          =   [
            'account_id'        =>  'required',
            'template_name'     =>  'required',
            'template_type'     =>  'required',
            'partner_type'      =>  'required',
        ];
        if($reqData['partner_type'] == "SPECIFICPARTNER")
        {
            $rules          +=   [
                'partner_mapping'        =>  'required',
            ]; 
        }


        $message        =   [
            'account_id.required'            => __('airlineBlocking.supplier_airline_blocking_account_id_required'),          
            'template_name.required'         => __('airlineBlocking.supplier_airline_blocking_template_name_required'),           
            'template_type.required'         => __('airlineBlocking.supplier_airline_blocking_template_type_required'),           
            'partner_type.required'          => __('airlineBlocking.supplier_airline_blocking_partner_type_required'),        
            'partner_mapping.required'    => __('common.this_field_is_required'),                
        ];
        
        $validator = Validator::make($reqData, $rules, $message);
        
         if ($validator->fails()) {
             $responseData['status_code']        =  config('common.common_status_code.validation_error');
            $responseData['message']             =  'The given data was invalid';
            $responseData['errors']              =  $validator->errors();
            $responseData['status']              =  'failed';
             return response()->json($responseData);
         }

        $supplierAirlineBlockingTemplates = SupplierAirlineBlockingTemplates::find($id);

        $oldSupplierGetOriginal = $supplierAirlineBlockingTemplates->getOriginal();

        $saveGroupDatas = $this->saveSupplierAirlineBlockingTemplatesData($reqData,$supplierAirlineBlockingTemplates,'update');
        $supplierAirlineBlockingTemplatesCall = new SupplierAirlineBlockingTemplates();
        $this->aData['supplierAirlineBlockingDatas'] = $supplierAirlineBlockingTemplatesCall->getAllAirlineBlockingTemplates();
        if($saveGroupDatas){
            $newSupplierGetOriginal = SupplierAirlineBlockingTemplates::find($id)->getOriginal();

            $checkDiffArray = Common::arrayRecursiveDiff($oldSupplierGetOriginal,$newSupplierGetOriginal);

            if(count($checkDiffArray) > 1){
                Common::prepareArrayForLog($id,'Supplier Airline Blocking Template',(object)$newSupplierGetOriginal,config('tables.supplier_airline_blocking_templates'),'supplier_airtline_blocking_template_management');
            }
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['message']        = __('airlineBlocking.supplier_airline_blocking_template_data_updated_success');
            $responseData['status']         = 'success';
         }
        return response()->json($responseData);

    }

    public function delete(Request $request)
    {
        $reqData        =   $request->all();
        $deleteData     =   self::changeStatusData($reqData,'delete');
        if($deleteData)
        {
            return $deleteData;
        }
    }

    public function changeStatus(Request $request)
    {
        $reqData            =   $request->all();
        $changeStatus       =   self::changeStatusData($reqData,'changeStatus');
        if($changeStatus)
        {
            return $changeStatus;
        }
    }


    public function changeStatusData($reqData , $flag)
    {
        $responseData                   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('airlineBlocking.supplier_airline_blocking_template_data_delete_success');
        $responseData['status'] 		= 'success';
        $id     = decryptData($reqData['id']);
        $rules =[
            'id' => 'required'
        ];
        
        $message =[
            'id.required' => __('common.id_required')
        ];
        
        $validator = Validator::make($reqData, $rules, $message);

    if ($validator->fails()) {
        $responseData['status_code'] = config('common.common_status_code.validation_error');
        $responseData['message'] = 'The given data was invalid';
        $responseData['errors'] = $validator->errors();
        $responseData['status'] = 'failed';
        return response()->json($responseData);
    }
    

    $status = 'D';
    if(isset($flag) && $flag == 'changeStatus'){
        $status = isset($reqData['status']) ? $reqData['status'] : 'IA';
        $responseData['message']        =   __('airlineBlocking.supplier_airline_blocking_template_change_status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status' => $status,
        'updated_at' => Common::getDate(),
        'updated_by' => Common::getUserID() 
    ];
    $changeStatus = SupplierAirlineBlockingTemplates::where('airline_blocking_template_id',$id)->update($data);
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
        return response()->json($responseData);
    }
    public function getHistory($id){
        $id                                 = decryptData($id);
        $requestData['model_primary_id']    = $id;
        $requestData['model_name']          = config('tables.supplier_airline_blocking_templates');
        $requestData['activity_flag']       = 'supplier_airtline_blocking_template_management';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.supplier_airline_blocking_templates');
            $requestData['activity_flag']    = 'supplier_airtline_blocking_template_management';
            $requestData['count']            = isset($requestData['count']) ? $requestData['count']: 0;
            $responseData                   = Common::showDiffHistory($requestData);
        }
        else{
            $responseData['status_code']    = config('common.common_status_code.failed');
            $responseData['status']         = 'failed';
            $responseData['short_text']     = 'get_history_diff_error';
            $responseData['message']        = __('common.get_history_diff_error');
            $responseData['errors']         = ['error'=> __('common.id_required')];
        }
        return response()->json($responseData);
    }

    public static function saveSupplierAirlineBlockingTemplatesData($requestData,$supplierAirlineBlockingTemplates,$action=''){
        if($requestData['partner_type'] == "SPECIFICPARTNER")
            $requestData['partner_mapping'] = array_values($requestData['partner_mapping']);
        $accountId = (isset($requestData['account_id']) ? $requestData['account_id'] : Auth::user()->account_id);
        $supplierAirlineBlockingTemplates->account_id = $accountId;
        //$supplierAirlineBlockingTemplates->portal_id = (isset($requestData['portal_user_selection'])) ? implode(',',$requestData['portal_user_selection']) : 0;
        $supplierAirlineBlockingTemplates->template_name = $requestData['template_name'];
        $supplierAirlineBlockingTemplates->template_type = $requestData['template_type'];
        $supplierAirlineBlockingTemplates->partner_type  = $requestData['partner_type'];
        $supplierAirlineBlockingTemplates->status = (isset($requestData['status'])) ? $requestData['status'] : 'IA';
        if($action == 'store') {
            $supplierAirlineBlockingTemplates->created_by = Common::getUserID();
            $supplierAirlineBlockingTemplates->created_at = Common::getDate();
        }
        $supplierAirlineBlockingTemplates->updated_by = Common::getUserID();
        $supplierAirlineBlockingTemplates->updated_at = Common::getDate();
        if($supplierAirlineBlockingTemplates->save()){

            if($action == "update"){
                SupplierAirlineBlockingPartnerMapping::where('airline_blocking_template_id', $supplierAirlineBlockingTemplates->airline_blocking_template_id)->delete();
            }


            
            if($requestData['partner_type'] == "ALLPARTNER"){
                $model = new SupplierAirlineBlockingPartnerMapping();
                $model->airline_blocking_template_id = $supplierAirlineBlockingTemplates->airline_blocking_template_id;
                $model->partner_account_id = '0';
                $model->partner_portal_id  = '0';
                $model->created_by         = Common::getUserID();
                $model->updated_by         = Common::getUserID();
                $model->created_at         = Common::getDate();
                $model->updated_at         = Common::getDate();
                $model->save();
            }
            if($requestData['partner_type'] == "SPECIFICPARTNER"){

                foreach ($requestData['partner_mapping'] as $key => $value) {
                    $model = new SupplierAirlineBlockingPartnerMapping();
                        $model->airline_blocking_template_id = $supplierAirlineBlockingTemplates->airline_blocking_template_id;
                        $model->partner_account_id = $value['partner_account_id'];
                        $model->partner_portal_id  = implode(',', $value['partner_portal_id']);
                        $model->created_by         = Common::getUserID();
                        $model->updated_by         = Common::getUserID();
                        $model->created_at         = Common::getDate();
                        $model->updated_at         = Common::getDate();
                        $model->save();
                }
                
            }

            //redis data update
            Common::ERunActionData($accountId, 'updateSupplierAirlineBlocking');

            return $supplierAirlineBlockingTemplates->airline_blocking_template_id;
        }
    }//eof
}
