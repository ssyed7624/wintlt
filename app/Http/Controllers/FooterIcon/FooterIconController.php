<?php

namespace App\Http\Controllers\FooterIcon;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use App\Models\FooterIcon\FooterIcon;
use App\Models\AccountDetails\AccountDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Libraries\Common;
use Auth;
use Validator;
use Storage;

class FooterIconController extends Controller
{
    public $followUsTitle    = '';
    public function __construct(Request $request)
    {
        $this->followUsTitle = config('common.footer_icons.follow_us');
    }
    public function index(){
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'footer_icons_data_retrived_success';
        $responseData['message']        = __('footerIcons.footer_icons_data_retrived_success');
        $status                         = config('common.status');
        $portalDetails                  = PortalDetails::getAllPortalList();
        $userDetails                    = UserDetails::getUserList();

        $responseData['data']['portal_details'] = isset($portalDetails['data'])?$portalDetails['data']:[];
        foreach($userDetails as $key => $value){
            $tempData                       = array();
            $tempData['user_id']            = $value['user_id'];
            $tempData['user_name']          = $value['user_name'];
            $responseData['data']['user_details'][] = $tempData ;
        }   
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
        $responseData['short_text']     = 'footer_icons_data_retrieve_failed';
        $responseData['message']        = __('footerIcons.footer_icons_data_retrieve_failed');
        $requestData                    = $request->all();
        $accountIds                     = AccountDetails::getAccountDetails(1,0,true);
        $footerIconsList                = FooterIcon::from(config('tables.footer_icons').' As fi')->select('fi.*','ad.account_name','ad.account_id','pd.portal_name','pd.portal_name','ud.first_name','ud.last_name')->leftjoin(config('tables.account_details').' As ad','ad.account_id','fi.account_id')->leftjoin(config('tables.portal_details').' As pd','pd.portal_id','fi.portal_id')->leftjoin(config('tables.user_details').' As ud','ud.user_id','fi.created_by')->whereIn('fi.account_id', $accountIds);
        
        //Filter
        if((isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '' &&  $requestData['query']['portal_id'] != 'ALL' && $requestData['query']['portal_id'] != 0) || (isset($requestData['portal_id']) && $requestData['portal_id'] != '' &&  $requestData['portal_id'] != 'ALL' &&  $requestData['portal_id'] != 0)){
            $requestData['portal_id'] = (isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '') ?$requestData['query']['portal_id']:$requestData['portal_id'];
            $footerIconsList = $footerIconsList->where('fi.portal_id',$requestData['portal_id']);
        }
        if((isset($requestData['query']) && isset($requestData['query']['title']) && $requestData['query']['title'] != '') || (isset($requestData['title']) && $requestData['title'] != '')){
            $title                    = (isset($requestData['query']['title']) && $requestData['query']['title'] != '') ? $requestData['query']['title'] : $requestData['title'];
            $footerIconsList         = $footerIconsList->where('fi.title','LIKE','%'.$title.'%');
        }
        if((isset($requestData['query']) && isset($requestData['query']['name']) && $requestData['query']['name'] != '') || (isset($requestData['name']) && $requestData['name'] != '')){
            $name                    = (isset($requestData['query']['name']) && $requestData['query']['name'] != '') ? $requestData['query']['name'] : $requestData['name'];
            $footerIconsList         = $footerIconsList->where('fi.name','LIKE','%'.$name.'%');
        }
        if((isset($requestData['query']) && isset($requestData['query']['link']) && $requestData['query']['link'] != '') || (isset($requestData['link']) && $requestData['link'] != '')){
            $link                    = (isset($requestData['query']['link']) && $requestData['query']['link'] != '') ? $requestData['query']['link'] : $requestData['link'];
            $footerIconsList         = $footerIconsList->where('fi.link','LIKE','%'.$link.'%');
        }
        if((isset($requestData['query']) && isset($requestData['query']['icon']) && $requestData['query']['icon'] != '') || (isset($requestData['icon']) && $requestData['icon'] != '')){
            $icon                    = (isset($requestData['query']['icon']) && $requestData['query']['icon'] != '') ? $requestData['query']['icon'] : $requestData['icon'];
            $footerIconsList         = $footerIconsList->where('fi.icon','LIKE','%'.$icon.'%');
        }
        if((isset($requestData['query']['created_by']) && $requestData['query']['created_by'] != '' &&  $requestData['query']['created_by'] != 'ALL' && $requestData['query']['created_by'] != 0) || (isset($requestData['created_by']) && $requestData['created_by'] != '' &&  $requestData['created_by'] != 'ALL' &&  $requestData['created_by'] != 0)){
            $requestData['created_by'] = (isset($requestData['query']['created_by']) && $requestData['query']['created_by'] != '') ?$requestData['query']['created_by']:$requestData['created_by'];
            $footerIconsList = $footerIconsList->where('fi.created_by',$requestData['created_by']);
        }
        if((isset($requestData['query']) && isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL') || (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL')){
            $status                     = (isset($requestData['query']['status'])  && $requestData['query']['status'] != '') ? $requestData['query']['status'] : $requestData['status'];
            $footerIconsList         = $footerIconsList->where('fi.status', $status);
        }else{
            $footerIconsList         = $footerIconsList->where('fi.status','<>','D');
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
            $footerIconsList = $footerIconsList->orderBy($requestData['orderBy'],$sorting);
        }else{
            $footerIconsList = $footerIconsList->orderBy('updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit'])- $requestData['limit'];                  
        //record count
        $footerIconsListCount          = $footerIconsList->take($requestData['limit'])->count();
        // Get Record
        $footerIconsList               = $footerIconsList->offset($start)->limit($requestData['limit'])->get()->toArray();
        
        if(count($footerIconsList) > 0){
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'footer_icons_data_retrived_success';
            $responseData['message']            = __('footerIcons.footer_icons_data_retrived_success');
            $responseData['data']['records_total']      = $footerIconsListCount;
            $responseData['data']['records_filtered']   = $footerIconsListCount;
            foreach($footerIconsList as $value){
                $tempData                       = array();
                $tempData['si_no']              = ++$start;
                $tempData['id']                 = encryptData($value['footer_icon_id']);
                $tempData['footer_icon_id']     = encryptData($value['footer_icon_id']);
                $tempData['account_id']         = $value['account_id'];
                $tempData['account_name']       = $value['account_name'];
                $tempData['portal_id']          = $value['portal_id'];
                $tempData['portal_name']        = $value['portal_name'];
                $tempData['title']              = $value['title'];
                $tempData['name']               = $value['name'];
                $tempData['link']               = $value['link'];
                if($value['title'] == $this->followUsTitle)
                {
                    $tempData['icon']               =  $value['icon'];
                }
                else
                {
                    $tempData['icon']               = url(config('common.footer_icon_save_path').$value['icon']);
                }
                $tempData['created_by']         = $value['first_name'].' '.$value['last_name'];
                $tempData['status']             = $value['status'];
                $responseData['data']['records'][]         = $tempData;
            }
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function create(){
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'footer_icons_data_retrived_success';
        $responseData['message']        = __('footerIcons.footer_icons_data_retrived_success');
        $responseData['data']           = self::getCommonData();                
        return response()->json($responseData);
    }

    public function store(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'footer_icons_data_store_failed';
        $responseData['message']        = __('footerIcons.footer_icons_data_store_failed');

        $storeFooterIcon                = self::storeFooterIcon($request,'store');
        if($storeFooterIcon['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $storeFooterIcon['status_code'];
            $responseData['errors']         = $storeFooterIcon['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'footer_icons_data_stored_success';
            $responseData['message']        = __('footerIcons.footer_icons_data_stored_success');
        }
        return response()->json($responseData);
    }

    public function edit($id){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'footer_icons_data_retrieve_failed';
        $responseData['message']        = __('footerIcons.footer_icons_data_retrieve_failed');
        $id                             = decryptData($id);
        $footerIcon                     = FooterIcon::where('footer_icon_id',$id)->where('status','<>','D')->first();
        if($footerIcon != null){
            $responseData['status']                         = 'success';
            $responseData['status_code']                    = config('common.common_status_code.success');
            $responseData['short_text']                     = 'footer_icons_data_retrived_success';
            $responseData['message']                        = __('footerIcons.footer_icons_data_retrived_success');

            if($footerIcon['title'] != $this->followUsTitle)
            {                
                $footerIcon['image']                        = url(config('common.footer_icon_save_path').$footerIcon['icon']);
            }
            $footerIcon['updated_by']      =   UserDetails::getUserName($footerIcon['updated_by'],'yes');
            $footerIcon['created_by']      =   UserDetails::getUserName($footerIcon['created_by'],'yes');
            $responseData['data']                           = $footerIcon;            
            $responseData['data']['encrypt_footer_icon_id'] = encryptData($footerIcon['footer_icon_id']);
            $getCommonData                                 = self::getCommonData();                
            $responseData['data']['portal_details']         = $getCommonData['portal_details']; 
            $responseData['data']['footer_icons_titles_details']= $getCommonData['footer_icons_titles_details'];
            $responseData['data']['footer_icons_details']   = $getCommonData['footer_icons_details'];
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }

        return response()->json($responseData);
    }

    public function update(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'footer_icons_data_update_failed';
        $responseData['message']        = __('footerIcons.footer_icons_data_update_failed');        
        $storeFooterIcon                = self::storeFooterIcon($request,'update');
        if($storeFooterIcon['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $storeFooterIcon['status_code'];
            $responseData['errors']         = $storeFooterIcon['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'footer_icons_data_updated_success';
            $responseData['message']        = __('footerIcons.footer_icons_data_updated_success');
        }
        return response()->json($responseData);
    }

    public function delete(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'footer_icons_data_delete_failed';
        $responseData['message']        = __('footerIcons.footer_icons_data_delete_failed');
        $requestData                    = $request->all();
        $deleteStatus                   = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'footer_icons_data_deleted_success';
            $responseData['message']        = __('footerIcons.footer_icons_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'footer_icons_change_status_failed';
        $responseData['message']        = __('footerIcons.footer_icons_change_status_failed');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'footer_icons_change_status_success';
            $responseData['message']        = __('footerIcons.footer_icons_change_status_success');
        }
        return response()->json($responseData);
    }

    public function statusUpadateData($requestData){

        $requestData                    = isset($requestData['footer_icons'])?$requestData['footer_icons'] : '';

        if($requestData != ''){
            $status                         = 'D';
            $rules     =[
                'flag'                  =>  'required',
                'footer_icon_id'        =>  'required'
            ];
            $message    =[
                'flag.required'         =>  __('common.flag_required'),
                'footer_icon_id.required'    =>  __('footerIcons.footer_icon_id_required')
            ];
            
            $validator = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                $id                             = decryptData($requestData['footer_icon_id']);
                if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                    $responseData['status_code']    = config('common.common_status_code.validation_error');
                    $responseData['erorrs']         =  ['error' => __('common.the_given_data_was_not_found')];

                }else{
                    if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus')
                        $status                         = $requestData['status'];

                    $updateData                         = array();
                    $updateData['status']               = $status;
                    $updateData['updated_at']           = Common::getDate();
                    $updateData['updated_by']           = Common::getUserID();

                    $changeStatus                       = FooterIcon::where('footer_icon_id',$id)->update($updateData);
                    if($changeStatus){
                        $responseData['status']         = 'success';
                        $responseData['status_code']    = config('common.common_status_code.success');

                    }else{
                    $responseData['status_code']        = config('common.common_status_code.validation_error');
                    $responseData['errors']             = ['error'=>__('common.recored_not_found')];
                    }
                }
            }  
        }else{
            $responseData['status_code']                = config('common.common_status_code.validation_error');
            $responseData['errors']                     = ['error'=>__('common.invalid_input_request_data')];
        }     
        return $responseData;
    }

    public function storeFooterIcon($request,$action){
        $requestData        = $request->all();
        $requestData        = isset($requestData['footer_icons'])?$requestData['footer_icons'] : '';
        if($requestData != ''){
            $requestData        = json_decode($requestData,true);
            $rules              =   [
                                        'portal_id'     => 'required',
                                        'title'         => 'required',
                                        'name'          => 'required',
                                        'link'          => 'required',
                                    ];
            if($action != 'store')
                $rules['footer_icon_id']       = 'required';          

            $message          =   [
                                    'footer_icon_id.required'   => __('footerIcons.footer_icon_id_required'),
                                    'portal_id.required'        => __('common.portal_id_required'),
                                    'title.required'            => __('footerIcons.footer_icons_title_required'),
                                    'name.required'             => __('footerIcons.footer_icons_name_required'),
                                    'link.required'             => __('footerIcons.footer_icons_link_required'),
                                ];

            $validator        = Validator::make($requestData, $rules, $message);
            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                $footerIconsTitles  = config('common.footer_icons');
                $accountDetails     = AccountDetails::getPortalBassedAccoutDetail($requestData['portal_id']);
                $accountID          = isset($accountDetails['account_id'])?$accountDetails['account_id']:'';
                $footerIconId       = isset($requestData['footer_icon_id'])?decryptData($requestData['footer_icon_id']):'';
                
                if($action == 'store')
                    $footerIconData     = new FooterIcon();
                else
                    $footerIconData     = FooterIcon::where('footer_icon_id',$footerIconId)->first();

                if($footerIconData != null){
                    if($requestData['title'] == $footerIconsTitles['follow_us']){
                        $footerIcon     = strtolower($requestData['icon']);
                        $footerIconData->icon           = $footerIcon;
                    }else{
                        $footerIcon = '';
                        if($request->file('icon')){
                            $footerIcons                = $request->file('icon');
                            $footerIcon                 = $accountID.'_'.time().'_al.'.$footerIcons->extension();
                            $footerIconOriginalName     = $footerIcons->getClientOriginalName();
                            $logFilesStorageLocation    = config('common.footer_icon_storage_loaction');
                            if($logFilesStorageLocation == 'local'){
                                    $storagePath =  config('common.footer_icon_save_path');
                                    if(!File::exists($storagePath)) {
                                    File::makeDirectory($storagePath, 0777, true, true);            
                                }
                            }               
                            $disk = Storage::disk($logFilesStorageLocation)->put($storagePath.$footerIcon, file_get_contents($footerIcons),'public');
                            $footerIconData->icon           = $footerIcon;
                        }
                    }
                    $footerIconData->account_id     = $accountID;
                    $footerIconData->portal_id      = $requestData['portal_id'];
                    $footerIconData->title          = $requestData['title'];
                    $footerIconData->name           = $requestData['name'];
                    $footerIconData->link           = $requestData['link'];
                    $footerIconData->status         = $requestData['status'];

                    if($action == 'store'){
                        $footerIconData->created_by = Common::getUserID();
                        $footerIconData->created_at = getDateTime();
                    }
                    $footerIconData->updated_by     = Common::getUserID();
                    $footerIconData->updated_at     = getDateTime();
                    $stored                         = $footerIconData->save();

                    if($stored){
                        $responseData = $footerIconData->footer_icon_id;
                    }else{
                        $responseData['status_code']            = config('common.common_status_code.validation_error');
                        $responseData['errors'] 	            = ['error' => __('common.problem_of_store_data_in_DB')];
                    }
                }else{
                    $responseData['status_code']            = config('common.common_status_code.validation_error');
                    $responseData['errors']         = ['error' => __('common.recored_not_found')];
                }
            }
        }else{
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        } 
        return $responseData;
    }

    public static function getCommonData(){
        $getCommonData                      = [];
        $accountID                          = Auth::user()->account_id;
        $accountIds                         =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $portalDetails                      = PortalDetails::select('portal_name','portal_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
        $getCommonData['account_id']        = $accountID;
        $getCommonData['account_name']      = AccountDetails::getAccountName($accountID);
        $getCommonData['portal_details']    = $portalDetails;
        $footerIconsTitles                  = config('common.footer_icons');
        $footerIconsDetails                 = config('common.footer_icons_icon');
        foreach($footerIconsTitles as $key => $value){
            $tempData                       = array();
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $getCommonData['footer_icons_titles_details'][] = $tempData ;
        }
        foreach($footerIconsDetails as $key => $value){
            $tempData                       = array();
            $tempData['label']              = $key  ;
            $tempData['value']              = $value;
            $getCommonData['footer_icons_details'][] = $tempData ;
        }
        return $getCommonData;
    }

    public function getFooterIcons(Request $request){
        $footerIconsTitles              = config('common.footer_icons');
        $responseData                   = [];
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'footer_icons_data_retrieve_failed';
        $responseData['message']        = __('footerIcons.footer_icons_data_retrieve_failed');
       
        $portalId = $request->siteDefaultData['portal_id'];
        $accountId = $request->siteDefaultData['account_id'];

		$footerIcon = FooterIcon::whereNotIn('status',['IA','D'])->where([
				    		['account_id',$accountId],
				    		['portal_id',$portalId],
				    	    ])->get()->toArray();
		
		if(count($footerIcon) > 0)
		{
            $responseData['status']                         = 'success';
            $responseData['status_code']                    = config('common.common_status_code.success');
            $responseData['short_text']                     = 'footer_icons_data_retrived_success';
            $responseData['message']                        = __('footerIcons.footer_icons_data_retrived_success');
            $footerIcons                                    = [];
            foreach ($footerIcon as $key => $value) {

            	if($value['title'] != $this->followUsTitle){
                    $logFilesStorageLocation = config('common.footer_icon_storage_loaction');
                    
	                if($logFilesStorageLocation == 'local'){
	                    $storagePath = url(config('common.footer_icon_save_path').$value['icon']);
	                }
            	}
            	
                if($value['title'] == $footerIconsTitles['member_of'])
                {
                	$footerIcons['member_of'][] = array('name'=>$value['name'],'link'=>$value['link'],'icon'=>$storagePath);
                }
                if($value['title'] == $footerIconsTitles['we_accept'])
                {
                	$footerIcons['we_accept'][] = array('name'=>$value['name'],'link'=>$value['link'],'icon'=>$storagePath);
                }
                if($value['title'] == $footerIconsTitles['follow_us'])
                {
                	$icons = $value['icon'];

                	$footerIcons['follow_us'][] = array('name'=>$value['name'],'link'=>$value['link'],'icon'=>$icons);
                }
            }
            $responseData['data'] = $footerIcons;
		}
		else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }
		return response()->json($responseData);
 	}

}
