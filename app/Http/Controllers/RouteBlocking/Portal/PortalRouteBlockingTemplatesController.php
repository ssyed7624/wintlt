<?php
namespace App\Http\Controllers\RouteBlocking\Portal;

use Auth;
use Validator;
use App\Libraries\Common;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Models\UserDetails\UserDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\RouteBlocking\Portal\PortalRouteBlockingTemplates;

class PortalRouteBlockingTemplatesController extends Controller
{
    public function index(){
        $responseData                           = array();
        $responseData['status']                 = 'success';
        $responseData['status_code']            = config('common.common_status_code.success');
        $responseData['short_text']             = 'portal_route_blocking_template_data_retrieved_success';
        $responseData['message']                = __('routeBlocking.portal_route_blocking_template_data_retrieved_success');
        $status                                 =  config('common.status');
        $accountId                              = AccountDetails::getAccountId();
        $getCommonFormatData                    = self::getCommonFormatData();
        $responseData['data']['account_details']        = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$getCommonFormatData['all_account_details']);
        $portalDetails                                  = PortalDetails::getAllPortalList(['B2B','B2C']);
        $portalDetails                                  = isset($portalDetails['data'])?$portalDetails['data']:[];
        $responseData['data']['portal_details']         = $portalDetails;
        $responseData['data']['template_type_details']= $getCommonFormatData['template_type_details'];

        foreach($status as $key => $value){
            $tempData                           = array();
            $tempData['label']                  = $key;
            $tempData['value']                  = $value;
            $responseData['data']['status'][]   = $tempData ;
        }

        return response()->json($responseData);
    }

    public function getList(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'portal_route_blocking_template_data_retrieve_failed';
        $responseData['message']            = __('routeBlocking.portal_route_blocking_template_data_retrieve_failed');
        // $accountId                       = AccountDetails::getAccountId();
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
        $portalRouteBlockingTemplates     = PortalRouteBlockingTemplates::from(config('tables.portal_route_blocking_templates').' As pb')->select('pb.*','pd.portal_name','ad.account_name')->leftjoin(config('tables.portal_details').' As pd','pd.portal_id','pb.portal_id')->leftjoin(config('tables.account_details').' As ad','ad.account_id','pb.account_id')->whereIn('ad.account_id',$accountIds);
        
        //Filter
        if((isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '' && $requestData['query']['portal_id'] != 'ALL' && $requestData['query']['portal_id'] != '0')|| (isset($requestData['portal_id']) && $requestData['portal_id'] != '' && $requestData['portal_id'] != 'ALL' && $requestData['portal_id'] != '0'))
        {
            $requestData['portal_id']           = (isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '')?$requestData['query']['portal_id'] : $requestData['portal_id'];
            $portalRouteBlockingTemplates     =   $portalRouteBlockingTemplates->where('pb.portal_id',$requestData['portal_id']);
        }
        if((isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '' && $requestData['query']['account_id'] != 'ALL')|| (isset($requestData['account_id']) && $requestData['account_id'] != '' && $requestData['account_id'] != 'ALL'))
        {
            $requestData['account_id'] = (isset($requestData['query']['account_id'])&& $requestData['query']['account_id'] != '') ?$requestData['query']['account_id'] : $requestData['account_id'];
            $portalRouteBlockingTemplates   =   $portalRouteBlockingTemplates->where('pb.account_id',$requestData['account_id']);
        }
        if((isset($requestData['query']['template_name']) && $requestData['query']['template_name'] != '')|| (isset($requestData['template_name']) && $requestData['template_name'] != ''))
        {
            $requestData['template_name'] = (isset($requestData['query']['template_name'])&& $requestData['query']['template_name'] != '') ?$requestData['query']['template_name'] : $requestData['template_name'];
            $portalRouteBlockingTemplates   =   $portalRouteBlockingTemplates->where('pb.template_name','LIKE','%'.$requestData['template_name'].'%');
        }
        if((isset($requestData['query']['template_type']) && $requestData['query']['template_type'] != '')|| (isset($requestData['template_type']) && $requestData['template_type'] != ''))
        {
            $requestData['template_type'] = (isset($requestData['query']['template_type'])&& $requestData['query']['template_type'] != '') ?$requestData['query']['template_type'] : $requestData['template_type'];
            $portalRouteBlockingTemplates   =   $portalRouteBlockingTemplates->where('pb.template_type','LIKE','%'.$requestData['template_type'].'%');
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL')|| (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL'))
        {
            $requestData['status'] = (isset($requestData['query']['status'])&& $requestData['query']['status'] != '') ?$requestData['query']['status'] : $requestData['status'];
            $portalRouteBlockingTemplates   =   $portalRouteBlockingTemplates->where('pb.status',$requestData['status']);
        }else{
            $portalRouteBlockingTemplates   =   $portalRouteBlockingTemplates->where('pb.status','<>','D');
        }
        
       //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $portalRouteBlockingTemplates = $portalRouteBlockingTemplates->orderBy($requestData['orderBy'],$sorting);
        }else{
            $portalRouteBlockingTemplates = $portalRouteBlockingTemplates->orderBy('updated_at','DESC');
        }
        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];                  
        //record count
        $portalRouteBlockingTemplatesCount  = $portalRouteBlockingTemplates->take($requestData['limit'])->count();
        // Get Record
        $portalRouteBlockingTemplates       = $portalRouteBlockingTemplates->offset($start)->limit($requestData['limit'])->get()->toArray();

       
        if(count($portalRouteBlockingTemplates) > 0){
            $responseData['status']                     = 'success';
            $responseData['status_code']                = config('common.common_status_code.success');
            $responseData['short_text']                 = 'portal_route_blocking_template_data_retrieved_success';
            $responseData['message']                    = __('routeBlocking.portal_route_blocking_template_data_retrieved_success');
            $responseData['data']['records_total']      = $portalRouteBlockingTemplatesCount;
            $responseData['data']['records_filtered']   = $portalRouteBlockingTemplatesCount;
            $templateTypeData                           = config('common.airline_booking_template_type');
            $portalDetails = PortalDetails::whereIn('status',['A','IA'])->pluck('portal_name','portal_id')->toArray();
            foreach($portalRouteBlockingTemplates as $value){
                if($value['portal_id'] == 0)
                {
                    $portalName = 'All';
                }
                else
                {
                    $portalIds = explode(',', $value['portal_id']);
                    $portalName = '';
                    foreach ($portalIds as $key => $portalId) {
                        if($portalName == '')
                            $portalName = isset($portalDetails[$portalId]) ? $portalDetails[$portalId] : '';
                        else
                            $portalName .= isset($portalDetails[$portalId]) ? ','.$portalDetails[$portalId] : '';

                    }
                }
                $tempData                                   = [];
                $tempData['si_no']                          = ++$start;
                $tempData['id']                             = encryptData($value['route_blocking_template_id']);
                $tempData['route_blocking_template_id']     = encryptData($value['route_blocking_template_id']);
                $tempData['account_id']                     = $value['account_id'];
                $tempData['account_name']                   = $value['account_name'];
                $tempData['portal_id']                      = $value['portal_id'];
                $tempData['portal_name']                    = $portalName;
                $tempData['template_name']                  = $value['template_name'];
                $tempData['template_type']                  = $templateTypeData[$value['template_type']];
                $tempData['status']                         = $value['status'];
                $responseData['data']['records'][]          = $tempData;
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
        $responseData['short_text']         = 'portal_route_blocking_template_data_retrieved_success';
        $responseData['message']            = __('routeBlocking.portal_route_blocking_template_data_retrieved_success');
        $getFormatData                      = [];
        $getFormatData                      = self::getCommonFormatData();
        $responseData['data']               = $getFormatData;
        return response()->json($responseData); 
    }

    public function store(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_route_blocking_template_data_store_failed';
        $responseData['message']        = __('routeBlocking.portal_route_blocking_template_data_store_failed');
        $requestData                    = $request->all();  
        $saveRouteBlockingDatas         = self::savePortalRouteBlockingTemplatesData($requestData,'store');
        
        if($saveRouteBlockingDatas['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code'] = $saveRouteBlockingDatas['status_code'];
            $responseData['errors'] 	 = $saveRouteBlockingDatas['errors'];
        }else{
            $responseData['status']      = 'success';
            $responseData['status_code'] = config('common.common_status_code.success');
            $responseData['short_text']  = 'portal_route_blocking_template_data_stored_success';
            $responseData['message']     = __('routeBlocking.portal_route_blocking_template_data_stored_success');
        } 
        
        return response()->json($responseData); 
    }
    
    public function edit($id){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'portal_route_blocking_template_data_retrieve_failed';
        $responseData['message']            = __('routeBlocking.portal_route_blocking_template_data_retrieve_failed');
        $id                                 = isset($id)?decryptData($id):'';
  
        $portalRouteBlockingTemplates       = PortalRouteBlockingTemplates::where('route_blocking_template_id',$id)->where('status','<>','D')->first();
        
        if($portalRouteBlockingTemplates != null){
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_route_blocking_template_data_retrieved_success';
            $responseData['message']        = __('routeBlocking.portal_route_blocking_template_data_retrieved_success');
            $responseData['data']           = $portalRouteBlockingTemplates;
            $getFormatData                  = self::getCommonFormatData($portalRouteBlockingTemplates->account_id);
            $responseData['data']['encrypt_route_blocking_template_id']    = encryptData($portalRouteBlockingTemplates->route_blocking_template_id);
            $responseData['data']['portal_id']               =explode(',',$portalRouteBlockingTemplates['portal_id']);
            $responseData['data']['created_by']              = UserDetails::getUserName($portalRouteBlockingTemplates['created_by'],'yes');
            $responseData['data']['updated_by']              = UserDetails::getUserName($portalRouteBlockingTemplates['updated_by'],'yes');
            $responseData['data']['all_portal_user_list']    = $getFormatData['all_portal_user_list'];
            $responseData['data']['all_account_details']     = $getFormatData['all_account_details'];
            $responseData['data']['template_type_details']   = $getFormatData['template_type_details'];
            
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData); 
    }

    public function update(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_route_blocking_template_data_update_failed';
        $responseData['message']        = __('routeBlocking.portal_route_blocking_template_data_update_failed');
        $requestData                    = $request->all();
        $saveRouteBlockingDatas         = self::savePortalRouteBlockingTemplatesData($requestData,'update');
        
        if($saveRouteBlockingDatas['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code'] = $saveRouteBlockingDatas['status_code'];
            $responseData['errors'] 	 = $saveRouteBlockingDatas['errors'];
        }else{
            $responseData['status']      = 'success';
            $responseData['status_code'] = config('common.common_status_code.success');
            $responseData['short_text']  = 'portal_route_blocking_template_data_updated_success';
            $responseData['message']     = __('routeBlocking.portal_route_blocking_template_data_updated_success');
        }
        return response()->json($responseData); 
    }

    public function delete(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'portal_route_blocking_template_data_delete_failed';
        $responseData['message']            = __('routeBlocking.portal_route_blocking_template_data_delete_failed');
        $requestData                        = $request->all();
        $deleteStatus                       = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_route_blocking_template_data_deleted_success';
            $responseData['message']        = __('routeBlocking.portal_route_blocking_template_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_route_blocking_template_change_status_failed';
        $responseData['message']        = __('routeBlocking.portal_route_blocking_template_change_status_failed');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_route_blocking_template_change_status_success';
            $responseData['message']        = __('routeBlocking.portal_route_blocking_template_change_status_success');
        }
        return response()->json($responseData);
    }

    public function statusUpadateData($requestData){

        $requestData                    = isset($requestData['portal_route_blocking_templates'])?$requestData['portal_route_blocking_templates'] : '';

        if($requestData != ''){
            $status                         = 'D';
            $rules     =[
                'flag'                  =>  'required',
                'route_blocking_template_id' =>  'required'
            ];
            $message    =[
                'flag.required'         =>  __('common.flag_required'),
                'route_blocking_template_id.required'    =>  __('routeBlocking.route_blocking_template_id_required')
            ];
            
            $validator = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                $id                                     = decryptData($requestData['route_blocking_template_id']);
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

            
                    $portalBlockingTemp                  = PortalRouteBlockingTemplates::where('route_blocking_template_id',$id);
                    $changeStatus                       = $portalBlockingTemp->update($updateData);
                    $portalBlockingTempData              = $portalBlockingTemp->first();
                    if($changeStatus){
                        $responseData                   =  $changeStatus;
                        //Redis
                        Common::ERunActionData($portalBlockingTempData['account_id'], 'updatePortalRouteBlocking');

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
    
    public function getCommonFormatData($accountId = ''){
        $routeBlockingBlockingTemplates     = array();
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
        $routeBlockingBlockingTemplates['all_portal_user_list']     = $portalData;
        $routeBlockingBlockingTemplates['all_account_details']      = $accountDetails;
        $routeBlockingBlockingTemplates['template_type_details']    = $templateTypeDetails;
        return $routeBlockingBlockingTemplates;
    }

    public function saveportalRouteBlockingTemplatesData($requestData,$action=''){
        
        $requestData                         = isset($requestData['portal_route_blocking_templates'])?$requestData['portal_route_blocking_templates']:'';
        
        if($requestData != ''){
            $requestData['template_name']         = isset($requestData['template_name'])?$requestData['template_name']:'';
            $accountId                          = isset($requestData['account_id']) ? $requestData['account_id']:0;
            if($action!='store'){
                $id         = isset($requestData['route_blocking_template_id'])?decryptData($requestData['route_blocking_template_id']):'';
                
                $nameUnique =  Rule::unique(config('tables.portal_route_blocking_templates'))->where('template_name', $requestData['template_name'])
                    ->where('route_blocking_template_id','<>', $id)
                    ->where('account_id','=',$accountId )
                    ->where('status','<>', 'D');
            }else{
                $nameUnique =  'unique:'.config('tables.portal_route_blocking_templates').',template_name,D,status,account_id,'.$accountId ;
            }

            $rules=[
                'template_name'                   =>  ['required',$nameUnique],
                'template_type'                   =>  'required',
            ];

            if($action != 'store')
                $rules['route_blocking_template_id']   =  'required';

            $message=[
                'route_blocking_template_id.required'     =>  __('routeBlocking.route_blocking_template_id_required'),
                'template_name.required'                    =>  __('routeBlocking.template_name_required'),
                'template_name.unique'                      =>  __('routeBlocking.template_name_unique'),
                'template_type.required'                    =>  __('routeBlocking.template_type_required'),
            ];

            $validator = Validator::make($requestData, $rules, $message);

            if($validator->fails()){
                $responseData                           = array();
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                if($action == 'store'){
                    $portalRouteBlockingTemplates = new PortalRouteBlockingTemplates();
                }
                else{
                    $portalRouteBlockingTemplates = PortalRouteBlockingTemplates::find($id);
                }

                if( $portalRouteBlockingTemplates != null){
                   
                    //Old Route Blocking Template
                    if($action != 'store')
                        $oldOriginalTemplate = $portalRouteBlockingTemplates->getOriginal();

                    $accountId                                    = (isset($requestData['account_id']) ? $requestData['account_id'] : Auth::user()->account_id);
                    $portalRouteBlockingTemplates->account_id     = $accountId;
                    $portalRouteBlockingTemplates->portal_id      = (isset($requestData['portal_id'])) ? implode(',',$requestData['portal_id']) : 0;
                    $portalRouteBlockingTemplates->template_name  = $requestData['template_name'];
                    $portalRouteBlockingTemplates->template_type  = $requestData['template_type'];
                    $portalRouteBlockingTemplates->status         = (isset($requestData['status'])) ? $requestData['status'] : 'IA';
                    
                    if($action == 'store') {
                        $portalRouteBlockingTemplates->created_by = Common::getUserID();
                        $portalRouteBlockingTemplates->created_at = getDateTime();
                    }

                    $portalRouteBlockingTemplates->updated_by     = Common::getUserID();
                    $portalRouteBlockingTemplates->updated_at     = getDateTime();

                    if($portalRouteBlockingTemplates->save()){
                        $portalRouteBlockingTemplateId                       = $portalRouteBlockingTemplates->route_blocking_template_id;
                        
                        //redis data update
                        Common::ERunActionData($accountId, 'updatePortalRouteBlocking');
                        
                        //History
                        $newOriginalTemplate = PortalRouteBlockingTemplates::find($portalRouteBlockingTemplateId)->getOriginal();
                        $historyFalg         = true;
                        if($action != 'store'){
                            $checkDiffArray     = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);

                            if(count($checkDiffArray) < 1){
                                $historyFalg         = false;
                            }
                        }
                        if($historyFalg)
                            Common::prepareArrayForLog($portalRouteBlockingTemplateId,'Portal Route Blocking Template',(object)$newOriginalTemplate,config('tables.portal_route_blocking_templates'),'portal_route_blocking_template_management');
                        
                        $responseData['status_code'] = config('common.common_status_code.success');
                    }else{
                        $responseData['status_code']        = config('common.common_status_code.validation_error');
                        $responseData['errors']             = ['error'=>__('common.problem_of_store_data_in_DB')];
                    }
                }else{
                    $responseData['status_code']            = config('common.common_status_code.validation_error');
                    $responseData['errors']                 = ['error'=>__('common.recored_not_found')];
                }  
            }
        }else{
            $responseData['status_code']        = config('common.common_status_code.validation_error');
            $responseData['errors']             = ['error'=>__('common.invalid_input_request_data')];
        }
        return   $responseData;   
    }//eof

    public function getHistory($id){
        $id                                 = decryptData($id);
        $requestData['model_primary_id']    = $id;
        $requestData['model_name']          = config('tables.portal_route_blocking_templates');
        $requestData['activity_flag']       = 'portal_route_blocking_template_management';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.portal_route_blocking_templates');
            $requestData['activity_flag']    = 'portal_route_blocking_template_management';
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
