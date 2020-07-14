<?php
namespace App\Http\Controllers\AirlineBlocking;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libraries\Common;
use App\Models\AirlineBlocking\PortalAirlineBlockingTemplates;
use App\Models\PortalDetails\PortalDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Http\Middleware\UserAcl;
use Illuminate\Validation\Rule;
use Auth;
use Validator;

class PortalAirlineBlockingTemplatesController extends Controller
{
    public function index(){
        $responseData                                   = array();
        $responseData['status']                         = 'success';
        $responseData['status_code']                    = config('common.common_status_code.success');
        $responseData['short_text']                     = 'portal_airline_blocking_template_data_retrived_success';
        $responseData['message']                        = __('airlineBlocking.portal_airline_blocking_template_data_retrived_success');
       
        $status                                         =  config('common.status');
        $accountId                                      = AccountDetails::getAccountId();
        $allFilterData                                  = self::createAndEditFormatData();
        $responseData['data']['account_details']        = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$allFilterData['all_account_details']);        
        $portalDetails                                  = PortalDetails::getAllPortalList(['B2B','B2C']);
        $portalDetails                                  = isset($portalDetails['data'])?$portalDetails['data']:[];
        $responseData['data']['portal_details']         = $portalDetails;
        $responseData['data']['template_type_details']  = array_merge([['label'=>'ALL','value'=>'ALL']],$allFilterData['template_type_details']);
        
        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }
        return response()->json($responseData);
    }

    public function getList(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'portal_airline_blocking_rules_data_retrive_failed';
        $responseData['message']            = __('airlineBlocking.portal_airline_blocking_rules_data_retrive_failed');
        // $accountId                          = AccountDetails::getAccountId();
        // $multipleFlag = UserAcl::hasMultiSupplierAccess();      
        // if($multipleFlag){                        
        //     $accessSuppliers = UserAcl::getAccessSuppliers();

        //     if(count($accessSuppliers) > 0){
        //         $dataQuery = $dataQuery->whereIn('account_id',$accessSuppliers);
        //     }
        // }
        // else{
        //     $dataQuery = $dataQuery->where('account_id',$account_id);
        // }
        $requestData                        = $request->all();

        $accountIds = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $accountIds[] = 0;
        $portalAirlineBlockingTemplates     = PortalAirlineBlockingTemplates::from(config('tables.portal_airline_blocking_templates').' As pa')->select('pa.*','pd.portal_name','ad.account_name')->leftjoin(config('tables.portal_details').' As pd','pd.portal_id','pa.portal_id')->leftjoin(config('tables.account_details').' As ad','ad.account_id','pa.account_id')->whereIn('ad.account_id',$accountIds);
        
        //Filter
        if((isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '' && $requestData['query']['portal_id'] != 'ALL' && $requestData['query']['portal_id'] != '0')|| (isset($requestData['portal_id']) && $requestData['portal_id'] != '' && $requestData['portal_id'] != 'ALL' && $requestData['portal_id'] != '0'))
        {
            $requestData['portal_id']           = (isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '')?$requestData['query']['portal_id'] : $requestData['portal_id'];
            $portalAirlineBlockingTemplates     =   $portalAirlineBlockingTemplates->where('pa.portal_id',$requestData['portal_id']);
        }
        if((isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '' && $requestData['query']['account_id'] != 'ALL')|| (isset($requestData['account_id']) && $requestData['account_id'] != '' && $requestData['account_id'] != 'ALL'))
        {
            $requestData['account_id'] = (isset($requestData['query']['account_id'])&& $requestData['query']['account_id'] != '') ?$requestData['query']['account_id'] : $requestData['account_id'];
            $portalAirlineBlockingTemplates   =   $portalAirlineBlockingTemplates->where('pa.account_id',$requestData['account_id']);
        }
        if((isset($requestData['query']['template_name']) && $requestData['query']['template_name'] != '')|| (isset($requestData['template_name']) && $requestData['template_name'] != ''))
        {
            $requestData['template_name'] = (isset($requestData['query']['template_name'])&& $requestData['query']['template_name'] != '') ?$requestData['query']['template_name'] : $requestData['template_name'];
            $portalAirlineBlockingTemplates   =   $portalAirlineBlockingTemplates->where('pa.template_name','LIKE','%'.$requestData['template_name'].'%');
        }
        if((isset($requestData['query']['template_type']) && $requestData['query']['template_type'] != '' && $requestData['query']['template_type'] != 'ALL')|| (isset($requestData['template_type']) && $requestData['template_type'] != '' && $requestData['template_type'] != 'ALL'))
        {
            $requestData['template_type'] = (isset($requestData['query']['template_type'])&& $requestData['query']['template_type'] != '') ?$requestData['query']['template_type'] : $requestData['template_type'];
            $portalAirlineBlockingTemplates   =   $portalAirlineBlockingTemplates->where('pa.template_type','LIKE','%'.$requestData['template_type'].'%');
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL')|| (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL'))
        {
            $requestData['status'] = (isset($requestData['query']['status'])&& $requestData['query']['status'] != '') ?$requestData['query']['status'] : $requestData['status'];
            $portalAirlineBlockingTemplates   =   $portalAirlineBlockingTemplates->where('pa.status',$requestData['status']);
        }else{
            $portalAirlineBlockingTemplates   =   $portalAirlineBlockingTemplates->where('pa.status','<>','D');
        }
        
       //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $portalAirlineBlockingTemplates = $portalAirlineBlockingTemplates->orderBy($requestData['orderBy'],$sorting);
        }else{
            $portalAirlineBlockingTemplates = $portalAirlineBlockingTemplates->orderBy('updated_at','DESC');
        }
        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];                  
        //record count
        $portalAirlineBlockingTemplatesCount  = $portalAirlineBlockingTemplates->take($requestData['limit'])->count();
        // Get Record
        $portalAirlineBlockingTemplates       = $portalAirlineBlockingTemplates->offset($start)->limit($requestData['limit'])->get()->toArray();

       
        if(count($portalAirlineBlockingTemplates) > 0){
            $responseData['status']                     = 'success';
            $responseData['status_code']                = config('common.common_status_code.success');
            $responseData['short_text']                 = 'portal_airline_blocking_template_data_retrived_success';
            $responseData['message']                    = __('airlineBlocking.portal_airline_blocking_template_data_retrived_success');
            $responseData['data']['records_total']      = $portalAirlineBlockingTemplatesCount;
            $responseData['data']['records_filtered']   = $portalAirlineBlockingTemplatesCount;
            $templateTypeData                           = config('common.airline_booking_template_type');
            foreach($portalAirlineBlockingTemplates as $value){        
                $tempData                                   = [];
                $tempData['si_no']                          = ++$start;
                $tempData['id']                             = $value['airline_blocking_template_id'];
                $tempData['airline_blocking_template_id']   = encryptData($value['airline_blocking_template_id']);
                $tempData['account_id']                     = $value['account_id'];
                $tempData['account_name']                   = isset($value['account_name']) ? $value['account_name'] : 'Not Set';
                $tempData['portal_id']                      = $value['portal_id'];
                $tempData['portal_name']                    = isset($value['portal_name']) ? $value['portal_name'] : 'ALL';
                $tempData['template_name']                  = $value['template_name'];
                $tempData['template_type']                  = $templateTypeData[$value['template_type']];
                $tempData['status']                         = $value['status'];
                $responseData['data']['records'][]                     = $tempData;
            }

        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }  
        return response()->json($responseData);
    }

    public function create(){
        $responseData                       = array();
        $responseData['status']            = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'portal_airline_blocking_template_data_retrived_success';
        $responseData['message']            = __('airlineBlocking.portal_airline_blocking_template_data_retrived_success');
        $getFormatData                      = [];
        $getFormatData                      = self::createAndEditFormatData();
        $responseData['data']               = $getFormatData;
        return response()->json($responseData); 
    }

    public function store(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_airline_blocking_template_data_store_failed';
        $responseData['message']        = __('airlineBlocking.portal_airline_blocking_template_data_store_failed');
        $requestData                    = $request->all();
        $requestData                    = isset($requestData['portal_airline_blocking_templates'])?$requestData['portal_airline_blocking_templates']:'';
        
        if($requestData != ''){
            $saveAirlineBlockingDatas       = self::savePortalAirlineBlockingTemplatesData($requestData,'store');
            
            if($saveAirlineBlockingDatas['status_code'] == config('common.common_status_code.validation_error')){
                $responseData['status_code'] = $saveAirlineBlockingDatas['status_code'];
                $responseData['errors'] 	 = $saveAirlineBlockingDatas['errors'];
            }else{
                $responseData['status']      = 'success';
                $responseData['status_code'] = config('common.common_status_code.success');
                $responseData['short_text']  = 'portal_airline_blocking_template_data_stored_success';
                $responseData['message']     = __('airlineBlocking.portal_airline_blocking_template_data_stored_success');
                
                //History
                $newOriginalTemplate         = PortalAirlineBlockingTemplates::find($saveAirlineBlockingDatas)->getOriginal();
                Common::prepareArrayForLog($saveAirlineBlockingDatas,'Airline Blocking Template',(object)$newOriginalTemplate,config('tables.portal_airline_blocking_templates'),'portal_airtline_blocking_template_management');
            } 
        }else{
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }
        return response()->json($responseData); 
    }//eof

    public function edit($id){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'portal_airline_blocking_rules_data_retrive_failed';
        $responseData['message']            = __('airlineBlocking.portal_airline_blocking_rules_data_retrive_failed');
        $id                                 = isset($id)?decryptData($id):'';
  
        $portalAirlineBlockingTemplates     = PortalAirlineBlockingTemplates::where('airline_blocking_template_id',$id)->where('status','<>','D')->first();
        
        if($portalAirlineBlockingTemplates != null){
            $portalAirlineBlockingTemplates = $portalAirlineBlockingTemplates->toArray();
            $portalAirlineBlockingTemplates = Common::getCommonDetails($portalAirlineBlockingTemplates);

            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_airline_blocking_template_data_retrived_success';
            $responseData['message']        = __('airlineBlocking.portal_airline_blocking_template_data_retrived_success');
            $responseData['data']           = $portalAirlineBlockingTemplates;
            $getFormatData                  = self::createAndEditFormatData($portalAirlineBlockingTemplates['account_id']);
            $responseData['data']['encrypt_airline_blocking_template_id']    = encryptData($portalAirlineBlockingTemplates['airline_blocking_template_id']);
            $responseData['data']['all_portal_user_list']    = $getFormatData['all_portal_user_list'];
            $responseData['data']['all_account_details']     = $getFormatData['all_account_details'];
            $responseData['data']['template_type_details']   = $getFormatData['template_type_details'];
            
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData); 
    }//eof

    public function update(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_airline_blocking_template_data_update_failed';
        $responseData['message']        = __('airlineBlocking.portal_airline_blocking_template_data_update_failed');
        
        $requestData                    = $request->all();
        $requestData                    = isset($requestData['portal_airline_blocking_templates'])?$requestData['portal_airline_blocking_templates']:'';

        if($requestData != ''){
            $requestData['airline_blocking_template_id']  = isset($requestData['airline_blocking_template_id'])?decryptData($requestData['airline_blocking_template_id']):'';
            $portalAirlineBlockingTemplates = PortalAirlineBlockingTemplates::find($requestData['airline_blocking_template_id']);
            if($portalAirlineBlockingTemplates != null){
                //Old Airline Blocking Templage
                $oldOriginalTemplate            = $portalAirlineBlockingTemplates->getOriginal();
                $saveAirlineBlockingDatas       = self::savePortalAirlineBlockingTemplatesData($requestData,'update');

                if($saveAirlineBlockingDatas['status_code'] == config('common.common_status_code.validation_error')){
                    $responseData['status_code'] = $saveAirlineBlockingDatas['status_code'];
                    $responseData['errors'] 	 = $saveAirlineBlockingDatas['errors'];
                }else{
                    $responseData['status']      = 'success';
                    $responseData['status_code'] = config('common.common_status_code.success');
                    $responseData['short_text']  = 'portal_airline_blocking_template_data_updated_success';
                    $responseData['message']     = __('airlineBlocking.portal_airline_blocking_template_data_updated_success');
                    
                    //History
                    $newOriginalTemplate         = PortalAirlineBlockingTemplates::find($saveAirlineBlockingDatas)->getOriginal();
                    Common::prepareArrayForLog($saveAirlineBlockingDatas,'Airline Blocking Template',(object)$newOriginalTemplate,config('tables.portal_airline_blocking_templates'),'portal_airtline_blocking_template_management');
                }
            }else{
                $responseData['errors'] = ['error'=>__('common.recored_not_found')];
            }
        }else{
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }
        return response()->json($responseData); 
    }//eof
    
    public function delete(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_airline_blocking_template_data_delete_failed';
        $responseData['message']        = __('airlineBlocking.portal_airline_blocking_template_data_delete_failed');
        $requestData                    = $request->all();
        $deleteStatus                   = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_airline_blocking_template_data_deleted_success';
            $responseData['message']        = __('airlineBlocking.portal_airline_blocking_template_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_airline_blocking_template_change_status_failed';
        $responseData['message']        = __('airlineBlocking.portal_airline_blocking_template_change_status_failed');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_airline_blocking_template_change_status_success';
            $responseData['message']        = __('airlineBlocking.portal_airline_blocking_template_change_status_success');
        }
        return response()->json($responseData);
    }

    public function statusUpadateData($requestData){

        $requestData                    = isset($requestData['portal_airline_blocking_templates'])?$requestData['portal_airline_blocking_templates'] : '';

        if($requestData != ''){
            $status                         = 'D';
            $rules     =[
                'flag'                  =>  'required',
                'airline_blocking_template_id' =>  'required'
            ];
            $message    =[
                'flag.required'         =>  __('common.flag_required'),
                'airline_blocking_template_id.required'    =>  __('airlineBlocking.airline_blocking_template_id_required')
            ];
            
            $validator = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                $id                                     = decryptData($requestData['airline_blocking_template_id']);
                if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                    $responseData['status_code']        = config('common.common_status_code.validation_error');
                    $responseData['erorrs']             =  ['error' => __('common.the_given_data_was_not_found')];
                }else{
                    if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus')
                        $status                         = $requestData['status'];

                    $updateData                     = array();
                    $updateData['status']           = $status;
                    $updateData['updated_at']       = Common::getDate();
                    $updateData['updated_by']       = Common::getUserID();

            
                    $portalAirlineTemp                  = PortalAirlineBlockingTemplates::where('airline_blocking_template_id',$id);
                    $changeStatus                       = $portalAirlineTemp->update($updateData);
                    $portalAirlineTempData              = $portalAirlineTemp->first();

                    if($changeStatus){
                        $responseData                   =  $changeStatus;
                        //Redis
                        Common::ERunActionData($portalAirlineTempData['account_id'], 'updatePortalAirlineBlocking');

                    }else{
                        $responseData['status_code']        = config('common.common_status_code.validation_error');
                        $responseData['errors']         = ['error'=>__('common.recored_not_found')];
                    }
                }
            }  
        }else{
            $responseData['status_code']        = config('common.common_status_code.validation_error');
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }     
        return $responseData;
    }

    public function savePortalAirlineBlockingTemplatesData($requestData,$action=''){
        $requestData['template_name']         = isset($requestData['template_name'])?$requestData['template_name']:'';
        $accountId         = isset($requestData['account_id']) ? $requestData['account_id']:Auth::user()->account_id;
        if($action!='store'){
            $id         = isset($requestData['airline_blocking_template_id']) ? $requestData['airline_blocking_template_id']:'';
            
            $nameUnique =  Rule::unique(config('tables.portal_airline_blocking_templates'))->where('template_name', $requestData['template_name'])
                ->where('airline_blocking_template_id','<>', $id)
                ->where('account_id','=',$accountId )
                ->where('status','<>', 'D');
        }else{
            $nameUnique =  'unique:'.config('tables.portal_airline_blocking_templates').',template_name,D,status,account_id,'.$accountId;
        }
        $rules=[
            'template_name'                   =>  ['required',$nameUnique],
            'template_type'                   =>  'required',
        ];

        if($action != 'store')
            $rules['airline_blocking_template_id']   =  'required';

        $message=[
            'airline_blocking_template_id.required'     =>  __('airlineBlocking.airline_blocking_template_id_required'),
            'template_name.required'                    =>  __('airlineBlocking.template_name_required'),
            'template_name.unique'                      =>  __('airlineBlocking.template_name_unique'),
            'template_type.required'                    =>  __('airlineBlocking.template_type_required'),
        ];

        $validator = Validator::make($requestData, $rules, $message);

        if($validator->fails()){
            $responseData                           = array();
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
        }else{
            if($action == 'store'){
                $portalAirlineBlockingTemplates = new PortalAirlineBlockingTemplates();
            }
            else{
                $portalAirlineBlockingTemplates = PortalAirlineBlockingTemplates::find($id);
            }

            $portalAirlineBlockingTemplates->account_id     = $accountId;
            $portalAirlineBlockingTemplates->portal_id      = (isset($requestData['portal_id'])) ? implode(',',$requestData['portal_id']) : 0;
            $portalAirlineBlockingTemplates->template_name  = $requestData['template_name'];
            $portalAirlineBlockingTemplates->template_type  = $requestData['template_type'];
            $portalAirlineBlockingTemplates->status         = (isset($requestData['status'])) ? $requestData['status'] : 'IA';
            
            if($action == 'store') {
                $portalAirlineBlockingTemplates->created_by = Common::getUserID();
                $portalAirlineBlockingTemplates->created_at = Common::getDate();
            }

            $portalAirlineBlockingTemplates->updated_by     = Common::getUserID();
            $portalAirlineBlockingTemplates->updated_at     = Common::getDate();

            if($portalAirlineBlockingTemplates->save()){
                //redis data update
                Common::ERunActionData($accountId, 'updatePortalAirlineBlocking');
                $responseData                       = $portalAirlineBlockingTemplates->airline_blocking_template_id;
            }else{
                $responseData['status_code']        = config('common.common_status_code.validation_error');
                $responseData['errors']             = ['error'=>__('common.problem_of_store_data_in_DB')];
            }
        }   
        return   $responseData;   
    }//eof

    public function createAndEditFormatData($accountId = ''){
        $portalAirlineBlockingTemplates     = array();
        $accountId                          = ($accountId == '')?Auth::user()->account_id:$accountId;
        $allPortalUserList                  = [];
        $allAccountDetails                  = [];

        if($accountId != config('common.supper_admin_user_id')) {
            $allPortalUserList              = PortalDetails::getPortalList($accountId);
            $allPortalUserList              = isset($allPortalUserList['data'])?$allPortalUserList['data']:[];
        }
        
        $allAccountDetails                  = AccountDetails::getAccountDetails(config('common.partner_account_type_id'));

        $portalData                         = array();
        foreach($allPortalUserList as $key => $value){
            $data                           = array();
            $data['portal_id']              = $key;     
            $data['portal_name']            = $value;
            $portalData[]                   = $data;     
        }

        $accountDetails                     = array();
        foreach($allAccountDetails as $key => $value){
            $data                           = array();
            $data['account_id']             = $key;     
            $data['account_name']           = $value;
            $accountDetails[]               = $data;     
        }
        $templateType                       =config('common.airline_booking_template_type');
        foreach($templateType as $key => $value){
            $data                           = array();
            $data['label']                  = $value;     
            $data['value']                  = $key;
            $templateTypeDetails[]          = $data;     
        }
        $portalAirlineBlockingTemplates['all_portal_user_list']     = $portalData;
        $portalAirlineBlockingTemplates['all_account_details']      = $accountDetails;
        $portalAirlineBlockingTemplates['template_type_details']    = $templateTypeDetails;
        return $portalAirlineBlockingTemplates;
    }

    public function getPortalList($accountId){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $allPortalUserList                  = PortalDetails::select('portal_id','portal_name')->where('account_id',$accountId)->where('business_type','!=','META')->get()->toArray();
         if(count($allPortalUserList) > 0){
            $responseData['status']           = 'success';
            $responseData['data']             = array_merge([['portal_id'=>'0','portal_name'=>'ALL']],$allPortalUserList);
        }else{
            $responseData['data']             = [];
        }
        return $responseData;
    }

    public function getHistory($id){
        $id                                 = decryptData($id);
        $requestData['model_primary_id']    = $id;
        $requestData['model_name']          = config('tables.portal_airline_blocking_templates');
        $requestData['activity_flag']       = 'portal_airtline_blocking_template_management';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.portal_airline_blocking_templates');
            $requestData['activity_flag']    = 'portal_airtline_blocking_template_management';
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
}



