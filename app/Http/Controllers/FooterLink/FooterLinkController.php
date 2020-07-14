<?php

namespace App\Http\Controllers\FooterLink;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\Controller;
use App\Models\AccountDetails\AccountDetails;
use App\Models\FooterLink\FooterLink;
use App\Models\UserDetails\UserDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Libraries\Common;
use URL;
use Auth;
use Validator;
use Storage;

class FooterLinkController extends Controller
{
    public function index(){
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'footer_link_data_retrived_success';
        $responseData['message']        = __('footerLink.footer_link_data_retrived_success');
        $status                         = config('common.status');
        $userDetails                    = UserDetails::getUserList();
        $portalDetails                  = self::getCommonData()['portal_details'];
        $responseData['data']['portal_details'] = array_merge([['portal_id'=>'ALL','portal_name'=>'ALL']],$portalDetails);
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
        $responseData                           = array();
        $responseData['status']                 = 'failed';
        $responseData['status_code']            = config('common.common_status_code.failed');
        $responseData['short_text']             = 'footer_link_data_retrieve_failed';
        $responseData['message']                = __('footerLink.footer_link_data_retrieve_failed');
        $requestData                            = $request->all();
        $accountIds                             = AccountDetails::getAccountDetails(1,0,true);
        $footerLinksList                        = FooterLink::from(config('tables.footer_links_and_pages').' As fl')->select('fl.*','ad.account_name','ad.account_id','pd.portal_name','pd.portal_name','ud.first_name','ud.last_name')->leftjoin(config('tables.account_details').' As ad','ad.account_id','fl.account_id')->leftjoin(config('tables.portal_details').' As pd','pd.portal_id','fl.portal_id')->leftjoin(config('tables.user_details').' As ud','ud.user_id','fl.created_by')->whereIn('fl.account_id', $accountIds);
        
        //Filter
        if((isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '' &&  $requestData['query']['portal_id'] != 'ALL' && $requestData['query']['portal_id'] != 0) || (isset($requestData['portal_id']) && $requestData['portal_id'] != '' &&  $requestData['portal_id'] != 'ALL' &&  $requestData['portal_id'] != 0)){
            $requestData['portal_id']   = (isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '') ?$requestData['query']['portal_id']:$requestData['portal_id'];
            $footerLinksList            = $footerLinksList->where('fl.portal_id',$requestData['portal_id']);
        }
        if((isset($requestData['query']) && isset($requestData['query']['title']) && $requestData['query']['title'] != '') || (isset($requestData['title']) && $requestData['title'] != '')){
            $title                      = (isset($requestData['query']['title']) && $requestData['query']['title'] != '') ? $requestData['query']['title'] : $requestData['title'];
            $footerLinksList            = $footerLinksList->where('fl.title','LIKE','%'.$title.'%');
        }
        if((isset($requestData['query']['created_by']) && $requestData['query']['created_by'] != '' &&  $requestData['query']['created_by'] != 'ALL' && $requestData['query']['created_by'] != 0) || (isset($requestData['created_by']) && $requestData['created_by'] != '' &&  $requestData['created_by'] != 'ALL' &&  $requestData['created_by'] != 0)){
            $requestData['created_by']  = (isset($requestData['query']['created_by']) && $requestData['query']['created_by'] != '') ?$requestData['query']['created_by']:$requestData['created_by'];
            $footerLinksList            = $footerLinksList->where('fl.created_by',$requestData['created_by']);
        }
        if((isset($requestData['query']) && isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL') || (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL')){
            $status                     = (isset($requestData['query']['status'])  && $requestData['query']['status'] != '') ? $requestData['query']['status'] : $requestData['status'];
            $footerLinksList            = $footerLinksList->where('fl.status', $status);
        }else{
            $footerLinksList            = $footerLinksList->where('fl.status','<>','D');
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
            $footerLinksList = $footerLinksList->orderBy($requestData['orderBy'],$sorting);
        }else{
            $footerLinksList = $footerLinksList->orderBy('updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit'])- $requestData['limit'];                  
        //record count
        $footerLinksListCount          = $footerLinksList->take($requestData['limit'])->count();
        // Get Record
        $footerLinksList               = $footerLinksList->offset($start)->limit($requestData['limit'])->get()->toArray();
        
        if(count($footerLinksList) > 0){
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'footer_link_data_retrived_success';
            $responseData['message']            = __('footerLink.footer_link_data_retrived_success');
            $responseData['data']['records_total']      = $footerLinksListCount;
            $responseData['data']['records_filtered']   = $footerLinksListCount;
            foreach($footerLinksList as $value){
                $imageData['imagePath'] ='';
                if($value['image'] != '' && $value['image'] != null)
                {
                    $imageData['image_storage_location']        = config('common.footer_link_background_storage_location');
                    $gcs                                        = Storage::disk($imageData['image_storage_location']);
                    if($value['image_saved_location'] == 'local'){
                        $imageData['imagePath']            = URL::to('/').config('common.footer_link_background_storage_image').'/'.$value['image'];
                    }else{
                        $imageData['imagePath']            = $gcs->url('uploadFiles/footerLinkBackGround/'.$value['image']);
                    }
                }
                $tempData                       = array();
                $tempData['si_no']              = ++$start;
                $tempData['id']                 = encryptData($value['footer_link_id']);
                $tempData['footer_link_id']     = encryptData($value['footer_link_id']);
                $tempData['account_id']         = $value['account_id'];
                $tempData['account_name']       = $value['account_name'];
                $tempData['portal_id']          = $value['portal_id'];
                $tempData['portal_name']        = $value['portal_name'];
                $tempData['title']              = $value['title'];
                $tempData['link']               = $value['link'];
                $tempData['image']              = $imageData['imagePath'];
                $tempData['content']            = $value['content'];
                $tempData['subject']            = $value['subject'];
                $tempData['created_by']         = $value['first_name'].' '.$value['last_name'];
                $tempData['status']             = $value['status'];
                $responseData['data']['records'][]         = $tempData;
            }
        }else{
            $responseData['errors']             = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }
    
    public function create(){
        $responseData                       = array();
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'footer_link_data_retrived_success';
        $responseData['message']            = __('footerLink.footer_link_data_retrived_success');
        $responseData['data']               = self::getCommonData();
        return response()->json($responseData);
    }

    public function store(Request $request){
        $responseData                           = array();
        $responseData['status']                 = 'failed';
        $responseData['status_code']            = config('common.common_status_code.failed');
        $responseData['short_text']             = 'footer_link_data_store_failed';
        $responseData['message']                = __('footerLink.footer_link_data_store_failed');
        $storeFooterLinks                       = self::storeFooterLinks($request,'store');
        if($storeFooterLinks['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']        = $storeFooterLinks['status_code'];
            $responseData['errors']             = $storeFooterLinks['errors'];
        }else{  
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'footer_link_data_stored_success';
            $responseData['message']            = __('footerLink.footer_link_data_stored_success');
        }
        return response()->json($responseData);
    }

    public function edit($id){
        $responseData                           = array();
        $responseData['status']                 = 'failed';
        $responseData['status_code']            = config('common.common_status_code.failed');
        $responseData['short_text']             = 'footer_link_data_retrieve_failed';
        $responseData['message']                = __('footerLink.footer_link_data_retrieve_failed');
        $id                                     = decryptData($id);
        $footerLink                             = FooterLink::where('footer_link_id','=',$id)->where('status','<>','D')->first();
        
        if($footerLink != null){
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'footer_link_data_retrived_success';
            $responseData['message']            = __('footerLink.footer_link_data_retrived_success');
            $footerTitles = FooterLink::where([
                                ['portal_id',$footerLink->portal_id],
                                ['status','NOT LIKE','D'],
                                ['footer_link_id','!=',$id],          
                            ])->pluck('title')->toArray();

            $footersLinkTitles  = config('common.footer_links');
            $footerLinkTitle    = [];

            foreach ($footersLinkTitles as $value) {
                $footerLinkTitle = array_merge($footerLinkTitle,$value);
            }
            foreach ($footerLinkTitle as $key => $value) {
                foreach ($footerTitles as $find) {
                    if($value == $find)
                    {
                        unset($footerLinkTitle[$key]);
                    }
                }
            }      
            if($footerLink['image'] != '')
            {
                $footerLink['image_storage_location']       = config('common.footer_link_background_storage_location');
                $gcs                                        = Storage::disk($footerLink['image_storage_location']);
                if($footerLink['image_saved_location'] == 'local'){
                    $footerLink['imagePath']                =  URL::to('/').config('common.footer_link_background_storage_image').'/'.$footerLink['image'];
                }else{
                    $footerLink['imagePath']                = $gcs->url('uploadFiles/footerLinkBackGround/'.$footerLink['image']);
                }
            }    
            //Portal ID Implementation
            $accountIds                                     =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
            $portalDetails                                  = PortalDetails::select('portal_name','portal_id','account_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
            $footerLink['updated_by']                       =   UserDetails::getUserName($footerLink['updated_by'],'yes');
            $footerLink['created_by']                       =   UserDetails::getUserName($footerLink['created_by'],'yes');
            $responseData['data']                           =   $footerLink;
            $responseData['data']['encrypt_footer_link_id'] =   encryptData($footerLink['footer_link_id']);
            $responseData['data']['portal_details']         =   $portalDetails;
            foreach($footerLinkTitle as $value){
                $tempData                    = [];
                $tempData['label']           = $value;
                $tempData['value']           = $value;
                $footerLinkTitleData[]     =  $tempData ;
            }
            $responseData['data']['footer_link_title'] = $footerLinkTitleData;

        }else{
            $responseData['errors']             = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function update(Request $request){
        $responseData                           = array();
        $responseData['status']                 = 'failed';
        $responseData['status_code']            = config('common.common_status_code.failed');
        $responseData['short_text']             = 'footer_link_data_update_failed';
        $responseData['message']                = __('footerLink.footer_link_data_update_failed');
        $storeFooterLinks                       = self::storeFooterLinks($request,'update');
        if($storeFooterLinks['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']        = $storeFooterLinks['status_code'];
            $responseData['errors']             = $storeFooterLinks['errors'];
        }else{  
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'footer_link_data_updated_success';
            $responseData['message']            = __('footerLink.footer_link_data_updated_success');
        }
        return response()->json($responseData);
    }
    
    public function delete(Request $request){
        $responseData   = self::statusUpadateData($request);
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData   = self::statusUpadateData($request);
        return response()->json($responseData);
    }
    
    public function statusUpadateData($request){

        $requestData                    = $request->all();
        $requestData                    = isset($requestData['footer_links_and_pages'])?$requestData['footer_links_and_pages'] : '';

        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        if($requestData != ''){
            $status                         = 'D';
            $rules     =[
                'flag'                  =>  'required',
                'footer_link_id'        =>  'required'
            ];
            $message    =[
                'flag.required'             =>  __('common.flag_required'),
                'footer_link_id.required'   =>  __('footerLink.footer_link_id_required')
            ];
            
            $validator = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                $id                             = decryptData($requestData['footer_link_id']);
                if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                    $responseData['status_code']    = config('common.common_status_code.not_found');
                    $responseData['short_text']     = 'the_given_data_was_not_found';
                    $responseData['message']        =  __('common.the_given_data_was_not_found');
                }else{
                    if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus'){
                        $status                         = $requestData['status'];
                        $responseData['short_text']     = 'footer_link_change_status_failed';
                        $responseData['message']        = __('footerLink.footer_link_change_status_failed');
                    }else{
                        $responseData['short_text']     = 'footer_link_data_delete_failed';
                        $responseData['message']        = __('footerLink.footer_link_data_delete_failed');
                    }

                    $updateData                         = array();
                    $updateData['status']               = $status;
                    $updateData['updated_at']           = getDateTime();
                    $updateData['updated_by']           = Common::getUserID();

                    $changeStatus                       = FooterLink::where('footer_link_id',$id)->update($updateData);
                    if($changeStatus){
                        $responseData['status']         = 'success';
                        $responseData['status_code']    = config('common.common_status_code.success');

                        if($status == 'D'){
                            $responseData['short_text']     = 'footer_link_data_deleted_success';
                            $responseData['message']        = __('footerLink.footer_link_data_deleted_success');
                        }else{
                            $responseData['short_text']     = 'footer_link_change_status_success';
                            $responseData['message']        = __('footerLink.footer_link_change_status_success');
                        }
                    }else{
                        $responseData['errors']         = ['error'=>__('common.recored_not_found')];
                    }
                }
            }  
        }else{
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }     
        return $responseData;
    }

    public function storeFooterLinks($request,$action = ''){
        $requestData    = $request->all();
        $requestData    = isset($requestData['footer_links_and_pages'])?$requestData['footer_links_and_pages'] : '';
        if($requestData != ''){
            $requestData    = json_decode($requestData,true);
            $rules          =   [
                                    'portal_id' =>  'required',
                                    'title'     =>  'required',
                                    'content'   =>  'required',
                                ];

            if($action != 'store')
                $rules['footer_link_id']  = 'required';

            $message          =   [ 
                                    'footer_link_id.required'   => __('footerLink.footer_link_id_required'),
                                    'portal_id.required'        => __('common.portal_id_required'),
                                    'title.required'            => __('footerLink.footer_link_title_required'),
                                    'content.required'          => __('footerLink.footer_link_content_required'),
                                ];
            
            $validator      = Validator::make($requestData,$rules,$message);

            if($validator->fails()){
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                $footerLinksId      = isset($requestData['footer_link_id'])?decryptData($requestData['footer_link_id']): '';

                if($action == 'store')
                    $footerLink     = new FooterLink();
                else
                    $footerLink     = FooterLink::find($footerLinksId);

                if($footerLink != null)
                {
                    $accountDetails         = AccountDetails::getPortalBassedAccoutDetail($requestData['portal_id']);
                    $accountID              = isset($accountDetails['account_id'])?$accountDetails['account_id']:'';
                    $imageOriginalImage     = '';
                    $imageName              = '';
                    $imageSavedLocation     = '';

                    if($request->file('image')){
                        $imageSavedLocation = config('common.footer_link_background_storage_location');
                        $image              = $request->file('image');
                        $imageName          = $accountID.'_'.time().'_image.'.$image->extension();
                        $imageOriginalImage = $image->getClientOriginalName();
                        
                        $logFilesStorageLocation = config('common.footer_link_background_storage_location');
                        
                        if($logFilesStorageLocation == 'local'){
                            $storagePath    = public_path().config('common.footer_link_background_storage_image');
                            if(!File::exists($storagePath)) {
                                File::makeDirectory($storagePath, $mode = 0777, true, true);            
                            }
                        }
                        $changeFileName                     = $imageName;
                        $fileGet                            = $image;
                        $disk                               = Storage::disk($logFilesStorageLocation)->put('uploadFiles/footerLinkBackGround/'.$changeFileName, file_get_contents($fileGet),'public');
                        $footerLink->image                  = $imageName;
                        $footerLink->image_original_name    = $imageOriginalImage;
                        $footerLink->image_saved_location   = $imageSavedLocation;
                    }
                    // store or update process
                    $footerLink->account_id             = $accountID;
                    $footerLink->portal_id              = $requestData['portal_id'];
                    $footerLink->title                  = $requestData['title'];
                    $footerLink->link                   = null;
                    $footerLink->subject                = isset($requestData['subject'])?$requestData['subject']:'';
                    $footerLink->content                = $requestData['content'];
                    $footerLink->status                 = (isset($requestData['status']) && $requestData['status'] != '')?$requestData['status'] : 'IA';
                    if($action == 'store'){
                        $footerLink->image                  = $imageName;
                        $footerLink->image_original_name    = $imageOriginalImage;
                        $footerLink->image_saved_location   = $imageSavedLocation;
                        $footerLink->created_by         = Common::getUserID();
                        $footerLink->created_at         = getDateTime();
                    }
                    $footerLink->updated_by             = Common::getUserID();
                    $footerLink->updated_at             = getDateTime();
                    $storedflag                         = $footerLink->save();
                    if($storedflag){
                        $responseData =  $footerLink->footer_link_id;
                    }else{
                        $responseData['status_code']    = config('common.common_status_code.validation_error');
                        $responseData['errors'] 	    = ['error' => __('common.problem_of_store_data_in_DB')];
                    }
                }else{
                    $responseData['status_code']        = config('common.common_status_code.validation_error');
                    $responseData['errors']             = ['error' => __('common.recored_not_found')];
                }
            }   
        }else{
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }
        return $responseData;
    }

    public function footerLinkTitleSelect(Request $request){
        $responseData                           = array();
        $responseData['status']                 = 'failed';
        $requestData                            = $request->all();
        $requestData['portal_id']               = isset($requestData['portal_id'])?$requestData['portal_id']:'';
        $requestData['footer_link_id']          = isset($requestData['footer_link_id'])?$requestData['footer_link_id']:'';
        
        if(isset($requestData['footer_link_id']) && $requestData['footer_link_id'] != '')
        {
            $footerTitles = FooterLink::where([
                                                ['portal_id',$requestData['portal_id']],
                                                ['status','<>','D'],
                                                ['footer_link_id','!=',$requestData['footer_link_id']],          
                                            ])->pluck('title')->toArray();
        }
        else
        {
            $footerTitles = FooterLink::where('portal_id',$requestData['portal_id'])->where('status','<>','D')->pluck('title')->toArray();
        }
        $footersLinkTitles = config('common.footer_links');
        $footerLinkTitle = [];
        foreach ($footersLinkTitles as $value) {
            $footerLinkTitle = array_merge($footerLinkTitle,$value);
        }
        foreach ($footerLinkTitle as $key => $value) {
            foreach ($footerTitles as $find) {
                if($value == $find)
                {
                    unset($footerLinkTitle[$key]);
                }
            }
        }

        if(count($footerLinkTitle) > 0){
            $responseData['status']                 = 'success';
            foreach($footerLinkTitle as $key => $value){
                    $tempData           = [];
                    $tempData['label']  = $value;
                    $tempData['value']  = $value;
                    $responseData['data'][] = $tempData;
            }
        }else{
            $responseData['errors']             = ['error' => __('common.recored_not_found')];
        }
        return  response()->json($responseData);
    }
    
    public function getFooterLinks(Request $request){
		$footerLinks        = $responseData = [];
		$responseData['status']     = 'failed';

		$index              = 0;
		$footerLink         = FooterLink::whereNotIn('status',['IA','D'])->where([
                                    ['account_id','=',$request->siteDefaultData['account_id']],
                                    ['portal_id','=',$request->siteDefaultData['portal_id']],
                                ]) ->get()->toArray();
        
        $footerLinkColumn   = config('common.footer_links');
        $responseData['data']     = [];
        $responseData['data']['footerSection1'] = [["displayName"=>"View Booking","urls"=>"viewbooking", "targets"=>"_blank"],["displayName"=>"Print Booking","urls"=>"printbooking", "targets"=>"_blank"]];
        $responseData['data']['footerSection2'] = [["displayName"=>"Contact Us","urls"=>"contactus", "targets"=>"_blank"]];
        $responseData['data']['footerSection3'] = [];

		if(count($footerLink) > 0)
		{
			$responseData['status']     = 'success';
            foreach ($footerLinkColumn as $key => $column) {
                
            	foreach ($footerLink as $value) {
                    
	            	if(in_array($value['title'],$column))
	            	{
                        $path = 'page/'.str_replace(' ','-',strtolower($value['title']));
                        $tempData                   = [];
                        $tempData['displayName']    = $value['title'];
                        $tempData['urls']           = $path;
                        $tempData['targets']        = "_blank";
                        $footerSectionKey = 'footerSection'.($key+1);
                        if(isset($responseData['data'][$footerSectionKey])){
                            $responseData['data'][$footerSectionKey] = array_merge($responseData['data'][$footerSectionKey],[$tempData]) ;
                        }
                        else{                            
                            $responseData['data'][$footerSectionKey] = $tempData;
                        }
	            	}
	            }	
            }
        }
 
		return response()->json($responseData);
 	}
     
    public function getFooterLinkContent(Request $request, $slug){
 		$portalId = $request->siteDefaultData['portal_id'];
 		$accountId = $request->siteDefaultData['account_id'];

 		$responseData = [];
 		$logFilesStorageLocation = config('common.footer_link_background_storage_location');	                
        $footerContent = FooterLink::whereRaw("LOWER(REPLACE(title ,' ', '-')) LIKE '".$slug."'")->where('portal_id',$portalId)->where('account_id',$accountId)->where('status','A')->first();
 		if(isset($footerContent))
 		{
 			$storagePath = '';
 			$responseData['status'] = 'success';
            $responseData['data']['title'] = $footerContent->title;
            $responseData['data']['subject'] = $footerContent->subject;
            $responseData['data']['content'] = $footerContent->content;
            if($logFilesStorageLocation == 'local' && isset($footerContent->image) && !empty($footerContent->image) ){
	                    $storagePath = URL::to('/').config('common.footer_link_background_storage_image').'/'.$footerContent->image;
	                }
	        $responseData['data']['background_image'] = $storagePath;
 		}
 		else{
            $responseData['status'] = 'failed';
            $responseData['data']   = [];
        }
 		return response()->json($responseData);
    }
     
     public static function getCommonData(){
        $getCommonData                      = [];
        $accountID                          = Auth::user()->account_id;
        $accountIds                         =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $portalDetails                      = PortalDetails::select('portal_name','portal_id','account_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get()->toArray();
        $getCommonData['account_name']      = AccountDetails::getAccountName($accountID);
        $getCommonData['portal_details']    = $portalDetails;
       
        return $getCommonData;
    }

}

