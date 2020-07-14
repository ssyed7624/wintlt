<?php
namespace App\Http\Controllers\RouteBlocking\Supplier;

use Auth;
use Validator;
use App\Libraries\Common;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Models\UserDetails\UserDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\AccountDetails\PartnerMapping;
use App\Models\RouteBlocking\Supplier\SupplierRouteBlockingRules;
use App\Models\RouteBlocking\Supplier\SupplierRouteBlockingTemplates;
use App\Models\RouteBlocking\Supplier\SupplierRouteBlockingPartnerMapping;

class SupplierRouteBlockingTemplatesController extends Controller
{
    public function index(){
        $responseData                                   = array();
        $responseData['status']                         = 'success';
        $responseData['status_code']                    = config('common.common_status_code.success');
        $responseData['short_text']                     = 'supplier_route_blocking_template_data_retrive_success';
        $responseData['message']                        = __('routeBlocking.supplier_route_blocking_template_data_retrive_success');
        $status                                         =  config('common.status');
        $getCommonData                                  = self::getCommonData();
        $responseData['data']['account_details']        = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$getCommonData['all_account_details']);
        $responseData['data']['template_type_details']  = array_merge([['label'=>'ALL','value'=>'ALL']],$getCommonData['template_type_details']);
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
        $responseData['short_text']         = 'supplier_route_blocking_template_data_retrive_failed';
        $responseData['message']            = __('routeBlocking.supplier_route_blocking_template_data_retrive_failed');
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
        $supplierRouteBlockingTemplates     = SupplierRouteBlockingTemplates::from(config('tables.supplier_route_blocking_templates').' As sb')->select('sb.*','ad.account_name')->leftjoin(config('tables.account_details').' As ad','ad.account_id','sb.account_id')->whereIn('ad.account_id',$accountIds);
        
        //Filter
        if((isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '' && $requestData['query']['account_id'] != 'ALL')|| (isset($requestData['account_id']) && $requestData['account_id'] != '' && $requestData['account_id'] != 'ALL'))
        {
            $requestData['account_id'] = (isset($requestData['query']['account_id'])&& $requestData['query']['account_id'] != '') ?$requestData['query']['account_id'] : $requestData['account_id'];
            $supplierRouteBlockingTemplates   =   $supplierRouteBlockingTemplates->where('sb.account_id',$requestData['account_id']);
        }
        if((isset($requestData['query']['template_name']) && $requestData['query']['template_name'] != '')|| (isset($requestData['template_name']) && $requestData['template_name'] != ''))
        {
            $requestData['template_name'] = (isset($requestData['query']['template_name'])&& $requestData['query']['template_name'] != '') ?$requestData['query']['template_name'] : $requestData['template_name'];
            $supplierRouteBlockingTemplates   =   $supplierRouteBlockingTemplates->where('sb.template_name','LIKE','%'.$requestData['template_name'].'%');
        }
        if((isset($requestData['query']['template_type']) && $requestData['query']['template_type'] != '')|| (isset($requestData['template_type']) && $requestData['template_type'] != ''))
        {
            $requestData['template_type'] = (isset($requestData['query']['template_type'])&& $requestData['query']['template_type'] != '') ?$requestData['query']['template_type'] : $requestData['template_type'];
            $supplierRouteBlockingTemplates   =   $supplierRouteBlockingTemplates->where('sb.template_type','LIKE','%'.$requestData['template_type'].'%');
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL')|| (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL'))
        {
            $requestData['status'] = (isset($requestData['query']['status'])&& $requestData['query']['status'] != '') ?$requestData['query']['status'] : $requestData['status'];
            $supplierRouteBlockingTemplates   =   $supplierRouteBlockingTemplates->where('sb.status',$requestData['status']);
        }else{
            $supplierRouteBlockingTemplates   =   $supplierRouteBlockingTemplates->where('sb.status','<>','D');
        }
        
       //sort
       if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $supplierRouteBlockingTemplates = $supplierRouteBlockingTemplates->orderBy($requestData['orderBy'],$sorting);
        }else{
            $supplierRouteBlockingTemplates = $supplierRouteBlockingTemplates->orderBy('updated_at','DESC');
        }
        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];                  
        //record count
        $supplierRouteBlockingTemplatesCount  = $supplierRouteBlockingTemplates->take($requestData['limit'])->count();
        // Get Record
        $supplierRouteBlockingTemplates       = $supplierRouteBlockingTemplates->offset($start)->limit($requestData['limit'])->get();

       
        if(count($supplierRouteBlockingTemplates) > 0){
            $responseData['status']                     = 'success';
            $responseData['status_code']                = config('common.common_status_code.success');
            $responseData['short_text']                 = 'supplier_route_blocking_template_data_retrive_success';
            $responseData['message']                    = __('routeBlocking.supplier_route_blocking_template_data_retrive_success');
            $responseData['data']['records_total']      = $supplierRouteBlockingTemplatesCount;
            $responseData['data']['records_filtered']   = $supplierRouteBlockingTemplatesCount;
            $templateTypeData                           = config('common.airline_booking_template_type');
            foreach($supplierRouteBlockingTemplates as $value){
                $tempData                                   = [];
                $tempData['si_no']                          = ++$start;
                $tempData['id']                             = encryptData($value['route_blocking_template_id']);
                $tempData['route_blocking_template_id']     = encryptData($value['route_blocking_template_id']);
                $tempData['account_id']                     = $value['account_id'];
                $tempData['account_name']                   = $value['account_name'];
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
        $responseData['short_text']         = 'supplier_route_blocking_template_data_retrive_success';
        $responseData['message']            = __('routeBlocking.supplier_route_blocking_template_data_retrive_success');
        $getFormatData                      = [];
        $getFormatData                      = self::getCommonData();
        $responseData['data']               = $getFormatData;
        return response()->json($responseData); 
    }

    public function store(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'supplier_route_blocking_template_data_store_failed';
        $responseData['message']        = __('routeBlocking.supplier_route_blocking_template_data_store_failed');
        $requestData                    = $request->all();  
        $saveRouteBlockingDatas         = self::saveSupplierRouteBlockingTemplates($requestData,'store');
        
        if($saveRouteBlockingDatas['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code'] = $saveRouteBlockingDatas['status_code'];
            $responseData['errors'] 	 = $saveRouteBlockingDatas['errors'];
        }else{
            $responseData['status']      = 'success';
            $responseData['status_code'] = config('common.common_status_code.success');
            $responseData['short_text']  = 'supplier_route_blocking_template_data_stored_success';
            $responseData['message']     = __('routeBlocking.supplier_route_blocking_template_data_stored_success');
        } 
        return response()->json($responseData); 
    }

    public function update(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'supplier_route_blocking_template_data_update_failed';
        $responseData['message']        = __('routeBlocking.supplier_route_blocking_template_data_update_failed');
        $requestData                    = $request->all();  
        $saveRouteBlockingDatas         = self::saveSupplierRouteBlockingTemplates($requestData,'update');
        
        if($saveRouteBlockingDatas['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code'] = $saveRouteBlockingDatas['status_code'];
            $responseData['errors'] 	 = $saveRouteBlockingDatas['errors'];
        }else{
            $responseData['status']      = 'success';
            $responseData['status_code'] = config('common.common_status_code.success');
            $responseData['short_text']  = 'supplier_route_blocking_template_data_updated_success';
            $responseData['message']     = __('routeBlocking.supplier_route_blocking_template_data_updated_success');
        } 
        return response()->json($responseData); 
    }

    public function delete(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'supplier_route_blocking_template_data_delete_failed';
        $responseData['message']            = __('routeBlocking.supplier_route_blocking_template_data_delete_failed');
        $requestData                        = $request->all();
        $deleteStatus                       = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'supplier_route_blocking_template_data_delete_success';
            $responseData['message']        = __('routeBlocking.supplier_route_blocking_template_data_delete_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'supplier_route_blocking_template_change_status_failed';
        $responseData['message']        = __('routeBlocking.supplier_route_blocking_template_change_status_failed');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'supplier_route_blocking_template_change_status_success';
            $responseData['message']        = __('routeBlocking.supplier_route_blocking_template_change_status_success');
        }
        return response()->json($responseData);
    }

    public function edit($id){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'supplier_route_blocking_template_data_retrive_failed';
        $responseData['message']            = __('routeBlocking.supplier_route_blocking_template_data_retrive_failed');
        $id                                 = isset($id)?decryptData($id):'';
  
        $supplierRouteBlockingTemplates     = SupplierRouteBlockingTemplates::where('route_blocking_template_id',$id)->where('status','<>','D')->first();
        $supplierRouteBlockingPartnerMapping = SupplierRouteBlockingPartnerMapping::where('route_blocking_template_id',$id)->get()->toArray();

        if($supplierRouteBlockingTemplates != null){
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'supplier_route_blocking_template_data_retrive_success';
            $responseData['message']        = __('routeBlocking.supplier_route_blocking_template_data_retrive_success');
            $responseData['data']           = $supplierRouteBlockingTemplates;
            $getFormatData                  = self::getCommonData($supplierRouteBlockingTemplates->account_id);
            $responseData['data']['encrypt_route_blocking_template_id']    = encryptData($supplierRouteBlockingTemplates->route_blocking_template_id);
            $responseData['data']['created_by']              = UserDetails::getUserName($supplierRouteBlockingTemplates['created_by'],'yes');
            $responseData['data']['updated_by']              = UserDetails::getUserName($supplierRouteBlockingTemplates['updated_by'],'yes');
            $responseData['data']['all_portal_user_list']    = $getFormatData['all_portal_user_list'];
            $responseData['data']['all_account_details']     = $getFormatData['all_account_details'];
            $responseData['data']['template_type_details']   = $getFormatData['template_type_details'];
            $responseData['data']['all_partner_details']    = $getFormatData['all_partner_details'];
            $responseData['data']['partner_type_details']    = $getFormatData['partner_type_details'];
            $responseData['data']['supplier_route_blocking_partner_mapping']    = $supplierRouteBlockingPartnerMapping;
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData); 
    }

    public function getCommonData($accountId = ''){
        $routeBlockingTemplates             = array();
        $allAccountDetails                  = [];
        $allPortalDetails                   = [];
        $allPartnersDetails                 = [];
        $allAccountDetails                  = AccountDetails::getAccountDetails(config('common.partner_account_type_id'));
        $accountId                          = ($accountId == '')?Auth::user()->account_id:$accountId;
        
        $accountDetails                     = array();
        foreach($allAccountDetails as $key => $value){
            $data                           = array();
            $data['account_id']             = $key;     
            $data['account_name']           = $value;
            $accountDetails[]               = $data;     
        }

        if(config('common.supper_admin_user_id') != $accountId) {
            $allPortalUserList = PortalDetails::getPortalList($accountId);
            $allPortalDetails = (isset($allPortalUserList['data'])) ? $allPortalUserList['data'] : [];
        }
        if(config('common.supper_admin_user_id') != $accountId) {
            $getPartners = PartnerMapping::consumerList($accountId,1);
            $allPartnersDetails = $getPartners;
        }
        $templateType                       = config('common.airline_booking_template_type');
        $partnerType                        = config('common.Supplier_Route_blocking_partner_type');
        foreach($templateType as $key => $value){
            $data                           = array();
            $data['label']                  = $value;     
            $data['value']                  = $key;
            $templateTypeDetails[]          = $data;     
        }

        foreach($partnerType as $key => $value){
            $data                           = array();
            $data['label']                  = $key;     
            $data['value']                  = $value;
            $partnerTypeDetails[]          = $data;     
        }
        $routeBlockingTemplates['all_account_details']      = $accountDetails;
        $routeBlockingTemplates['all_portal_user_list']     = $allPortalDetails;
        $routeBlockingTemplates['all_partner_details']      = $allPartnersDetails;
        $routeBlockingTemplates['template_type_details']    = $templateTypeDetails;
        $routeBlockingTemplates['partner_type_details']     = $partnerTypeDetails;
        return $routeBlockingTemplates;
    }

    public function saveSupplierRouteBlockingTemplates($requestData,$action){
        $requestData        = isset($requestData['supplier_route_blocking_templates'])?$requestData['supplier_route_blocking_templates']:'';
       
        if($requestData != ''){
            $requestData['template_name']         = isset($requestData['template_name'])?$requestData['template_name']:'';
            $accountId                            = isset($requestData['account_id'])?$requestData['account_id']:Auth::user()->account_id;
            $id         = isset($requestData['route_blocking_template_id'])?decryptData($requestData['route_blocking_template_id']):'';
            
            if($id!='' ){
                $nameUnique =  Rule::unique(config('tables.supplier_route_blocking_templates'))->where(function ($query) use($id,$requestData) {
                    return $query->where('template_name', $requestData['template_name'])
                    ->where('route_blocking_template_id','<>', $id)
                    ->where('account_id','<>', $requestData['account_id'])
                    ->where('status','<>', 'D');
                });
            }else{
                $nameUnique =  Rule::unique(config('tables.supplier_route_blocking_templates'))->where(function ($query) use($requestData) {
                    return $query->where('template_name', $requestData['template_name'])
                    ->where('account_id','<>', $requestData['account_id'])
                    ->where('status','<>', 'D');
                });
            }
            $rules                          =   [
                                                    'template_name'                   =>  ['required',$nameUnique],
                                                    'template_type'                   =>  'required',
                                                    'account_id'                      =>  'required',
                                                    'partner_type'                    =>  'required',
                                                ];
            if($action != 'store')
                $rules['route_blocking_template_id']   =  'required';
            
            $message                        =   [
                                                    'route_blocking_template_id.required'       =>  __('routeBlocking.route_blocking_template_id_required'),
                                                    'template_name.required'                    =>  __('routeBlocking.template_name_required'),
                                                    'template_name.unique'                      =>  __('routeBlocking.template_name_unique'),
                                                    'template_type.required'                    =>  __('routeBlocking.template_type_required'),
                                                    'partner_type.required'                     =>  __('routeBlocking.supplier_route_blocking_partner_type_required'),
                                                    'account_id.required'                       =>  __('common.account_id_required'),
                                                ];
            $validator = Validator::make($requestData, $rules, $message);
            if($validator->fails()){
                $responseData                           = array();
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                if($action == 'store')
                    $supplierRouteBlockingTemplates = new SupplierRouteBlockingTemplates();
                else
                    $supplierRouteBlockingTemplates = SupplierRouteBlockingTemplates::find($id);
                
                if( $supplierRouteBlockingTemplates != null){

                    //Old Route Blocking Template
                    if($action != 'store'){
                        $oldOriginalTemplate = $supplierRouteBlockingTemplates->getOriginal();
                        $oldOriginalBlockingPartnerMapping = SupplierRouteBlockingPartnerMapping::where('route_blocking_template_id', $id)->get()->pluck('partner_portal_id', 'partner_account_id');
                        $oldOriginalTemplate['supplier_partner_mapping'] = $oldOriginalBlockingPartnerMapping;
                    }
                    $supplierRouteBlockingTemplates->account_id     = $accountId;
                    $supplierRouteBlockingTemplates->template_name  = $requestData['template_name'];
                    $supplierRouteBlockingTemplates->template_type  = $requestData['template_type'];
                    $supplierRouteBlockingTemplates->partner_type   = $requestData['partner_type'];
                    $supplierRouteBlockingTemplates->status         = (isset($requestData['status'])) ? $requestData['status'] : 'IA';
                    if($action == 'store') {
                        $supplierRouteBlockingTemplates->created_by = Common::getUserID();
                        $supplierRouteBlockingTemplates->created_at = getDateTime();
                    }
                    $supplierRouteBlockingTemplates->updated_by     = Common::getUserID();
                    $supplierRouteBlockingTemplates->updated_at     = getDateTime();
                    $storeFlag                                      = $supplierRouteBlockingTemplates->save();
                    
                    if($storeFlag){
                        $routeblockingtemplateId = $supplierRouteBlockingTemplates->route_blocking_template_id;
                        if($action != "store"){
                            SupplierRouteBlockingPartnerMapping::where('route_blocking_template_id', $routeblockingtemplateId)->delete();
                        }

                        if($requestData['partner_type'] == "ALLPARTNER"){
                            $model                              = new SupplierRouteBlockingPartnerMapping();
                            $model->route_blocking_template_id  = $routeblockingtemplateId;
                            $model->partner_account_id          = '0';
                            $model->partner_portal_id           = '0';
                            $model->created_by                  = Common::getUserID();
                            $model->updated_by                  = Common::getUserID();
                            $model->created_at                  = getDateTime();
                            $model->updated_at                  = getDateTime();
                            $model->save();
                        }

                        if($requestData['partner_type'] == "SPECIFICPARTNER"){
                            if(isset($requestData['partner_mapping']))
                            {
                                foreach ($requestData['partner_mapping'] as $key => $value) {
                                    if(isset($value['partner_account_id']) && isset($value['partner_account_id']))
                                    {
                                        $model                              = new SupplierRouteBlockingPartnerMapping();
                                        $model->route_blocking_template_id  = $routeblockingtemplateId;
                                        $model->partner_account_id          = $value['partner_account_id'];
                                        $model->partner_portal_id           = implode(',', $value['partner_portal_id']);
                                        $model->created_by                  = Common::getUserID();
                                        $model->updated_by                  = Common::getUserID();
                                        $model->created_at                  = getDateTime();
                                        $model->updated_at                  = getDateTime();
                                        $model->save();
                                    }
                                }
                            }
                        }
                        //redis data update
                        Common::ERunActionData($accountId, 'updateSupplierRouteBlocking');
                        //History
                        $historyFlag                        = true;
                        $newOriginalTemplate                = SupplierRouteBlockingTemplates::find($routeblockingtemplateId)->getOriginal();
                        $newOriginalBlockingPartnerMapping  = SupplierRouteBlockingPartnerMapping::where('route_blocking_template_id', $routeblockingtemplateId)->get()->pluck('partner_portal_id', 'partner_account_id');            
                        $newOriginalTemplate['supplier_partner_mapping'] = $newOriginalBlockingPartnerMapping;
                        
                        if($action != 'store'){
                            $checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);
                            if(count($checkDiffArray) < 1)
                                $historyFlag    = false;
                        }

                        if($historyFlag)
                            Common::prepareArrayForLog($routeblockingtemplateId,'Supplier Route Blocking Template',(object)$newOriginalTemplate,config('tables.supplier_route_blocking_templates'),'supplier_route_blocking_template_management');
                        //Success
                        $responseData['status_code'] = config('common.common_status_code.success');
                        }else{
                        $responseData['status_code'] = config('common.common_status_code.validation_error');
                        $responseData['errors']      = ['error'=>__('common.recored_not_found')];
                    }  
                }else{
                    $responseData['status_code']    = config('common.common_status_code.validation_error');
                    $responseData['errors']         = ['error'=>__('common.recored_not_found')];
                }
            }   
        }else{
                $responseData['status_code']        = config('common.common_status_code.validation_error');
                $responseData['errors']             = ['error'=>__('common.invalid_input_request_data')];
        }
        return $responseData;
    }

    public function statusUpadateData($requestData){

        $requestData                    = isset($requestData['supplier_route_blocking_templates'])?$requestData['supplier_route_blocking_templates'] : '';

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
                    $oldOriginalTemplate = SupplierRouteBlockingTemplates::find($id)->getOriginal();
                    $oldOriginalBlockingPartnerMapping = SupplierRouteBlockingPartnerMapping::where('route_blocking_template_id', $id)->get()->pluck('partner_portal_id', 'partner_account_id');
                    $oldOriginalTemplate['supplier_partner_mapping'] = $oldOriginalBlockingPartnerMapping;
                    $updateData                     = array();
                    $updateData['status']           = $status;
                    $updateData['updated_at']       = Common::getDate();
                    $updateData['updated_by']       = Common::getUserID();

            
                    $supplierBlockingTemp           = SupplierRouteBlockingTemplates::where('route_blocking_template_id',$id);
                    SupplierRouteBlockingRules::where('route_blocking_template_id',$id)->update($updateData);
                    
                    $changeStatus                   = $supplierBlockingTemp->update($updateData);
                    $supplierBlockingTempData       = $supplierBlockingTemp->first();

                    if($changeStatus){
                        $newOriginalTemplate = SupplierRouteBlockingTemplates::find($id)->getOriginal();
                        $newOriginalBlockingPartnerMapping = SupplierRouteBlockingPartnerMapping::where('route_blocking_template_id', $id)->get()->pluck('partner_portal_id', 'partner_account_id');
                        $newOriginalTemplate['supplier_partner_mapping'] = $newOriginalBlockingPartnerMapping;
                        $checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);
                        if($checkDiffArray > 0)
                        {
                            Common::prepareArrayForLog($id,'Supplier Route Blocking Template',(object)$newOriginalTemplate,config('tables.supplier_route_blocking_templates'),'supplier_route_blocking_template_management');
                        }
                        $responseData               =  $changeStatus;
                        //Redis
                        Common::ERunActionData($supplierBlockingTempData['account_id'], 'updateSupplierRouteBlocking');

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

    public function getHistory($id){
        $id                                 = decryptData($id);
        $requestData['model_primary_id']    = $id;
        $requestData['model_name']          = config('tables.supplier_route_blocking_templates');
        $requestData['activity_flag']       = 'supplier_route_blocking_template_management';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.supplier_route_blocking_templates');
            $requestData['activity_flag']    = 'supplier_route_blocking_template_management';
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
