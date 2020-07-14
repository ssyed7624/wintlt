<?php

namespace App\Http\Controllers\BenefitContent;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\BenefitContent\BenefitContent;
use App\Models\PortalDetails\PortalDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\UserDetails\UserDetails;
use App\Libraries\Common;
use Validator;

class BenefitContentController extends Controller
{
    public function index(){
        $responseData                           = array();
        $responseData['status']                 = 'success';
        $responseData['status_code']            = config('common.common_status_code.success');
        $responseData['short_text']             = 'benefit_content_data_retrived_success';
        $responseData['message']                = __('benefitContent.benefit_content_data_retrived_success');
        $status                                 = config('common.status');
        $accountIds                             = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $portalDetails                          = PortalDetails::select('portal_name','portal_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get()->toArray();
        $responseData['data']['portal_details'] = array_merge([['portal_id'=>'ALL','portal_name'=>'ALL']],$portalDetails);
        $benefitLogoClass                       =  config('common.benefits_content_logo');
        $userDetails                            = UserDetails::getUserList();
        foreach($userDetails as $key => $value){
            $tempData                       = array();
            $tempData['user_id']            = $value['user_id'];
            $tempData['user_name']          = $value['user_name'];
            $responseData['data']['user_details'][] = $tempData ;
        } 
        foreach($benefitLogoClass as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $value;
            $tempData['value']          = $value;
            $benefitLogo[]              =$tempData;
        }
        $responseData['data']['benefits_content_logo'] = array_merge([['label'=>'ALL','value'=>'ALL']],$benefitLogo) ;
        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }
        return response()->json($responseData);
    }

    public function getList(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'benefit_content_data_retrieve_failed';
        $responseData['message']        = __('benefitContent.benefit_content_data_retrieve_failed');
        $requestData                    = $request->all();
        $accountIds                     = AccountDetails::getAccountDetails(1,0,true);
        $benefitContentList             = BenefitContent::from(config('tables.benefit_content').' As bc')->select('bc.*','pd.portal_name','pd.portal_name','ud.first_name','ud.last_name')->leftjoin(config('tables.portal_details').' As pd','pd.portal_id','bc.portal_id')->leftjoin(config('tables.user_details').' As ud','ud.user_id','bc.created_by')->where('pd.status','A')->where('ud.status','A')->where('pd.status','!=','D')->whereIn('bc.account_id', $accountIds);
        //Filter
        if((isset($requestData['query']) && isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '' && $requestData['query']['portal_id'] != 'ALL') || (isset($requestData['portal_id']) && $requestData['portal_id'] != '' && $requestData['portal_id'] != 'ALL')){
            $portalId                   = (isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '') ? $requestData['query']['portal_id'] : $requestData['portal_id'];
            $benefitContentList         = $benefitContentList->where('bc.portal_id', $portalId);
        }                                                       
        if((isset($requestData['query']) && isset($requestData['query']['content']) && $requestData['query']['content'] != '') || (isset($requestData['content']) && $requestData['content'] != '')){
            $content                    = (isset($requestData['query']['content']) && $requestData['query']['content'] != '') ? $requestData['query']['content'] : $requestData['content'];
            $benefitContentList         = $benefitContentList->where('bc.content','LIKE','%'.$content.'%');
        }
        if((isset($requestData['query']) && isset($requestData['query']['logo_class']) && $requestData['query']['logo_class'] != '' && $requestData['query']['logo_class'] != 'ALL') || (isset($requestData['logo_class']) && $requestData['logo_class'] != ''  && $requestData['logo_class'] != 'ALL')){
            $logoClass                  = (isset($requestData['query']['logo_class'])  && $requestData['query']['logo_class'] != '') ? $requestData['query']['logo_class'] : $requestData['logo_class'];
            $benefitContentList         = $benefitContentList->where('bc.logo_class', $logoClass);
        }
        if((isset($requestData['query']) && isset($requestData['query']['created_by']) && $requestData['query']['created_by'] != '' && $requestData['query']['created_by'] != 'ALL') || (isset($requestData['created_by']) && $requestData['created_by'] != '' && $requestData['created_by'] != 'ALL')){
            $portalId                   = (isset($requestData['query']['created_by']) && $requestData['query']['created_by'] != '') ? $requestData['query']['created_by'] : $requestData['created_by'];
            $benefitContentList         = $benefitContentList->where('bc.created_by', $portalId);
        }   
        if((isset($requestData['query']) && isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL') || (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL')){
            $status                     = (isset($requestData['query']['status'])  && $requestData['query']['status'] != '') ? $requestData['query']['status'] : $requestData['status'];
            $benefitContentList         = $benefitContentList->where('bc.status', $status);
        }else{
            $benefitContentList         = $benefitContentList->where('bc.status','<>','D');
        }

        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
                if($requestData['orderBy'] == 'created_by')
                {
                    $requestData['orderBy']='first_name';
                }
            $benefitContentList = $benefitContentList->orderBy($requestData['orderBy'],$sorting);
        }else{
            $benefitContentList = $benefitContentList->orderBy('updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit'])- $requestData['limit'];                  
        //record count
        $benefitContentListCount  = $benefitContentList->take($requestData['limit'])->count();
        // Get Record
        $benefitContentList       = $benefitContentList->offset($start)->limit($requestData['limit'])->get();

        if(count($benefitContentList) > 0){
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'benefit_content_data_retrived_success';
            $responseData['message']        = __('benefitContent.benefit_content_data_retrived_success');
            $responseData['data']['records_total'] = $benefitContentListCount;
            $responseData['data']['records_filtered'] = $benefitContentListCount;
            foreach ($benefitContentList as $value) {
                $tempData                                 = [];
                $tempData['si_no']                        = ++$start;
                $tempData['id']                           = encryptData($value['benefit_content_id']);
                $tempData['encrypted_benefit_content_id'] = encryptData($value['benefit_content_id']);
                $tempData['portal_id']                    = $value['portal_id'];
                $tempData['portal_name']                  = $value['portal_name'] ;
                $tempData['content']                      = $value['content'];
                $tempData['logo_class']                   = $value['logo_class'];
                $tempData['status']                       = $value['status'];
                $tempData['created_by']                   = $value['first_name'].' '.$value['last_name']; 
                $responseData['data']['records'][]                   = $tempData;
            }
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function create(){
        $responseData                       = array();
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'benefit_content_data_retrieve_success';
        $responseData['message']            = __('benefitContent.benefit_content_data_retrieve_success');
        $benefitLogoClass                   =  config('common.benefits_content_logo');
        $accountIds                                 = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $portalDetails                              = PortalDetails::select('portal_name','portal_id','account_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
        $responseData['data']['portal_details']     = $portalDetails;
        foreach($benefitLogoClass as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $value;
            $tempData['value']          = $value;
            $responseData['data']['benefits_content_logo'][] = $tempData ;
        }
        return response()->json($responseData);
    }

    public function store(Request $request){
        $responseData                      = array();
        $responseData['status']            = 'failed';
        $responseData['status_code']       = config('common.common_status_code.failed');
        $responseData['short_text']        = 'benefit_content_data_store_failed';
        $responseData['message']           = __('benefitContent.benefit_content_data_store_failed');
        $requestData                       = $request->all();
        $requestData                       = $requestData['benefit_content'];
       
        $storeBenefitContent                = self::storeBenefitContent($requestData,'store');
        
        if($storeBenefitContent['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']   = $storeBenefitContent['status_code'];
            $responseData['errors']        = $storeBenefitContent['errors'];
        }else{  
            $responseData['status']        = 'success';
            $responseData['status_code']   = config('common.common_status_code.success');
            $responseData['short_text']    = 'benefit_content_data_stored_success';
            $responseData['message']       = __('benefitContent.benefit_content_data_stored_success');
        }
        return response()->json($responseData);
    }

    public function edit($id){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'benefit_content_data_retrieve_failed';
        $responseData['message']            = __('benefitContent.benefit_content_data_retrieve_failed');
        $id                                 = decryptData($id);
        $benefitContentData                 = BenefitContent::where('benefit_content_id',$id)->where('status','<>','D')->first();
        if($benefitContentData != null){
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'benefit_content_data_retrived_success';
            $responseData['message']        = __('benefitContent.benefit_content_data_retrived_success');
            $benefitContentData['updated_by']      =   UserDetails::getUserName($benefitContentData['updated_by'],'yes');
            $benefitContentData['created_by']      =   UserDetails::getUserName($benefitContentData['created_by'],'yes');
            $responseData['data']           =  $benefitContentData;
            $responseData['data']['encrypted_benefit_content_id']   = encryptData($benefitContentData['benefit_content_id']);
            $accountIds                                 = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
            $portalDetails                              = PortalDetails::select('portal_name','portal_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
            $responseData['data']['portal_details']     = $portalDetails;
            $benefitLogoClass                   =  config('common.benefits_content_logo');
            $benfitLogoData = [];
            foreach($benefitLogoClass as $value){
                $tempData                   = array();
                $tempData['label']          = $value;
                $tempData['value']          = $value;
                $benfitLogoData[] = $tempData ;
            }
            $responseData['data']['benefits_content_logo'] = $benfitLogoData;
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }//eo edit

    public function update(Request $request){
        $responseData                      = array();
        $responseData['status']            = 'failed';
        $responseData['status_code']       = config('common.common_status_code.failed');
        $responseData['short_text']        = 'benefit_content_data_update_failed';
        $responseData['message']           = __('benefitContent.benefit_content_data_update_failed');
        $requestData                       = $request->all();
        $requestData                       = $requestData['benefit_content'];
       
        $storeBenefitContent                = self::storeBenefitContent($requestData,'update');
        
        if($storeBenefitContent['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']   = $storeBenefitContent['status_code'];
            $responseData['errors']        = $storeBenefitContent['errors'];
        }else{  
            $responseData['status']        = 'success';
            $responseData['status_code']   = config('common.common_status_code.success');
            $responseData['short_text']    = 'benefit_content_data_updated_success';
            $responseData['message']       = __('benefitContent.benefit_content_data_updated_success');
        }
        return response()->json($responseData);
    }

    public function delete(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'benefit_content_data_delete_failed';
        $responseData['message']        = __('benefitContent.benefit_content_data_delete_failed');
        $requestData                    = $request->all();
        $deleteStatus                   = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'benefit_content_data_deleted_success';
            $responseData['message']        = __('benefitContent.benefit_content_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'benefit_content_change_status_failed';
        $responseData['message']        = __('benefitContent.benefit_content_change_status_failed');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'benefit_content_change_status_success';
            $responseData['message']        = __('benefitContent.benefit_content_change_status_success');
        }
        return response()->json($responseData);
    }
    
    public function storeBenefitContent($requestData,$action){
        $rules          =   [
            'portal_id'     =>  'required',
            'content'       =>  'required',
            'logo_class'    =>  'required',
        ];

        if($action != 'store')
            $rules['benefit_content_id']  = 'required';

        $message            =     [ 
                                    'benefit_content_id.required'   => __('benefitContent.benefit_content_id_required'),
                                    'portal_id.required'            => __('common.portal_id_required'),
                                    'content.required'              => __('benefitContent.benefit_content_logo_class_required'),
                                    'logo_class.required'           => __('benefitContent.benefit_content_required'),
                                ];

        $validator      = Validator::make($requestData,$rules,$message);
        if($validator->fails()){
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
        }else{
            $benefitContentId                       = isset($requestData['benefit_content_id'])?decryptData($requestData['benefit_content_id']): '';
            
            if($action == 'store')
                $benefitContent                     = new BenefitContent();
            else
                $benefitContent                     = BenefitContent::find($benefitContentId);
           
            if($benefitContent != null){
                $accountDetails                     = AccountDetails::getPortalBassedAccoutDetail($requestData['portal_id']);
                $accountID                          = isset($accountDetails['account_id'])?$accountDetails['account_id']:'';
                
                $benefitContent->account_id         = $accountID;
                $benefitContent->portal_id          = $requestData['portal_id'];
                $benefitContent->logo_class         = $requestData['logo_class'];
                $benefitContent->content            = $requestData['content'];
                $benefitContent->status             = isset($requestData['status']) ? $requestData['status'] : 'IA';
                if($action == 'store'){
                    $benefitContent->created_at     = getDateTime();
                    $benefitContent->created_by     = Common::getUserID();
                }
                $benefitContent->updated_at         = getDateTime();
                $benefitContent->updated_by         = Common::getUserID();
                $storedflag                         = $benefitContent->save();
                if($storedflag){
                    $responseData                   =  $benefitContent->benefit_content_id;
                }else{
                    $responseData['status_code']    = config('common.common_status_code.validation_error');
                    $responseData['errors'] 	    = ['error' => __('common.problem_of_store_data_in_DB')];
                }
            }else{
                $responseData['status_code']        = config('common.common_status_code.validation_error');
                $responseData['errors']             = ['error' => __('common.recored_not_found')];
            }
        }
        return $responseData;
    }

    public function statusUpadateData($requestData){

        $requestData                    = isset($requestData['benefit_content'])?$requestData['benefit_content'] : '';

        if($requestData != ''){
            $status                         = 'D';
            $rules     =[
                'flag'                  =>  'required',
                'benefit_content_id'        =>  'required'
            ];
            $message    =[
                'flag.required'             =>  __('common.flag_required'),
                'benefit_content_id.required'   =>  __('benefitContent.benefit_content_id_required')
            ];
            
            $validator = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                $id                                 = decryptData($requestData['benefit_content_id']);
                if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                    $responseData['status_code']    = config('common.common_status_code.validation_error');
                    $responseData['erorrs']         =  ['error' => __('common.the_given_data_was_not_found')];
                }else{
                    if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus')
                        $status                         = $requestData['status'];

                    $updateData                         = array();
                    $updateData['status']               = $status;
                    $updateData['updated_at']           = getDateTime();
                    $updateData['updated_by']           = Common::getUserID();

                    $changeStatus                       = BenefitContent::where('benefit_content_id',$id)->update($updateData);
                    if($changeStatus){
                        $responseData['status']         = 'success';
                        $responseData['status_code']    = config('common.common_status_code.success');

                    }else{
                        $responseData['status_code']    = config('common.common_status_code.validation_error');
                        $responseData['errors']         = ['error'=>__('common.recored_not_found')];
                    }
                }
            }  
        }else{
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }     
        return $responseData;
    }

    public function getBenefitList(Request $request){
        $responseData               = [];
        $responseData['status']     = 'failed';
        $responseData['data']       = [];
        $accountId                  = isset($request->siteDefaultData['account_id']) ? $request->siteDefaultData['account_id'] : '';
        $portalId                   = isset($request->siteDefaultData['portal_id']) ? $request->siteDefaultData['portal_id'] : '';
        $benefitContentData         = BenefitContent::where('account_id',$accountId)->where('portal_id',$portalId)->where('status','A')->get()->toArray();
        
        if(count($benefitContentData) > 0){
            $responseData['status'] = 'Success';
            $benefitArray           = [];

            // $portalConfigData = PortalConfig::getPortalBasedConfig($portalId);
            // $benefitArray['title'] = (isset($portalConfigData['data']['benefit_data']) && $portalConfigData['data']['benefit_data'] != '' ) ? $portalConfigData['data']['benefit_data'] : config('common.benefit_content_title');
            $benefitArray['title'] = config('common.benefit_content_title');

            foreach ($benefitContentData as $defKey => $value) {
                $benefitArray['all_contents'][] = array('icon'=>$value['logo_class'], 'content'=>$value['content']);
            }//eo foreach
            $responseData['data'] = $benefitArray;
        }
        return response()->json($responseData);
    }//eof

}//eoc