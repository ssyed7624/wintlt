<?php

namespace App\Models\AccountDetails;

use App\Models\ContentSource\ContentSourceDetails;
use App\Models\AccountDetails\PartnerMapping;
use App\Models\PortalDetails\PortalDetails;
use App\Http\Middleware\UserAcl;
use App\Libraries\Common;
use App\Models\Model;
use Storage;
use Auth;
use DB;

class AccountDetails extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.account_details');
    }

    public function getTableColumns() {
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

    public function parentAccount(){

        return $this->belongsTo('App\Models\AccountDetails\AccountDetails','parent_account_id','account_id');
    }

    public function agencyPermissions(){

        return $this->hasOne('App\Models\AccountDetails\AgencyPermissions','account_id','account_id');
    }

    public function getIsSupplierAttribute()
    {   
        $supplierCheck = AgencyPermissions::where('account_id',$this->account_id)->first();
        if($supplierCheck && $supplierCheck->supply_content_to_other_agency == 1){
            return $supplierCheck->supply_content_to_other_agency;
        }
        return 0;
    }

    protected $appends = array('is_supplier');

    protected $primaryKey = 'account_id';

    protected $fillable = [
        'account_type_id','business_type','primary_user_id', 'b2b_account_id','parent_account_id','account_name','short_name', 'agency_currency','agency_name',
        'agency_address1','agency_address2','agency_city','agency_state','agency_country','agency_pincode',
        'agency_mobile_code','agency_mobile','agency_phone','agency_fax','agency_website','agency_email','agency_url','agency_logo','agency_mini_logo','agency_product','agency_fare','arc','iata','authority_registration_number','clia','tax_registration_number','gds_details','same_as_agency_address','mailing_title','mailing_first_name','mailing_last_name','mailing_address1','mailing_address2','mailing_country','mailing_state','mailing_city','mailing_extn','mailing_mobile_code','mailing_mobile','mailing_phone','mailing_email','memberships','operating_currency','operating_time_zone','operating_country','other_info','send_activation_email','status','created_at','updated_at','created_by', 'updated_by','mailing_mobile_code_country','agency_mobile_code_country','agency_b2b_access_url','agency_logo_original_name','agency_mini_logo_original_name','payment_gateway_ids','available_country_language',
    ];

    public static function getAccountName($accountId){
    	$accountDetails = self::find($accountId);
        if($accountDetails){
            return $accountDetails->account_name;
        }else{
            return "Not Set";
        }
    }

    //function to get parentAccountDetails
    public static function getAccountAndParentAccountDetails($account_id, $requiredParent = 'Y'){
        $returnAccountDatas = [];
        $accountDetailsCount = self::where('account_id',$account_id)->count();
        if($accountDetailsCount > 0){
            //get current account details
            $accountDetails = self::where('account_id',$account_id)->first();
            if($accountDetails){
                $returnAccountDatas['agency_email'] = $accountDetails->agency_email;
                $returnAccountDatas['agency_name'] = $accountDetails->agency_name;
                $returnAccountDatas['agency_phone'] = $accountDetails->agency_phone;
                //get parent account details
                if($accountDetails->parent_account_id == 0 || $requiredParent == 'N'){ //for main agency
                    $returnAccountDatas['parent_account_id'] = $accountDetails->account_id;
                    $returnAccountDatas['parent_account_name'] = $accountDetails->agency_name;
                    $returnAccountDatas['parent_account_phone_no'] = Common::getFormatPhoneNumberView($accountDetails->agency_mobile_code,$accountDetails->agency_phone);
                }else{ //for sub agency
                   $parentAccountDetails = self::where('account_id',$accountDetails->parent_account_id)->first();
                   if($parentAccountDetails){
                    $returnAccountDatas['parent_account_id'] = $parentAccountDetails->account_id;
                    $returnAccountDatas['parent_account_name'] = $parentAccountDetails->agency_name;
                    $returnAccountDatas['parent_account_phone_no'] = Common::getFormatPhoneNumberView($parentAccountDetails->agency_mobile_code,$parentAccountDetails->agency_phone);
                   }
                }
            }//eo if
        }//eo if
        return $returnAccountDatas;
    }//eof

        public static function getAgencyTitle( $accountId = 0){ 

        $outputArray = [];
        $outputArray['appName']         = config('app.name');
        $outputArray['appTitle']        = config('app.name');
        $outputArray['appShortName']    = config('app.name');
        $outputArray['touchIcon']       = 'images/apple-icon.png';
        $outputArray['favicons']        = 'images/favicon.png';
        $outputArray['miniLogo']        = 'images/logo-mini.png';
        $outputArray['largeLogo']       = 'images/logo-mini.png';
        $outputArray['loginBg']         = 'images/bg3.jpg';
        $outputArray['companyUrl']      = config('common.company_url');
        $outputArray['footerName']      = __('footer.copyright_company');
        $outputArray['b2bAccessUrl']    = config('app.url');
        $outputArray['appAccountId']    = 0;
        $outputArray['agencyPhoneNo']   = '';
        $outputArray['regardsAgencyPhoneNo']   = '';

        $agencyB2BAccessUrl = isset($_SERVER['HTTP_HOST'])?$_SERVER['HTTP_HOST']:'';
        if($agencyB2BAccessUrl != '' && $accountId == 0 ){

            if($agencyB2BAccessUrl == 'netfareshub.com'){
                $outputArray['loginBg']     = 'images/bg3.jpg';
            }
            $accountDetails = self::where('agency_b2b_access_url', $agencyB2BAccessUrl)->where('parent_account_id', 0)->where('status','A')->first();
        }
        
        if($accountId != 0){
            $accountDetails = self::where('account_id', $accountId)->first();
        }          

        if(isset($accountDetails) && $accountDetails){
            $outputArray['agencyPhoneNo']   = $accountDetails->agency_phone;
            $outputArray['emailAddress'] = $accountDetails->agency_email;
            $outputArray['appName'] = config('app.name');
            //to get formated phone number in regards content
            if((isset($accountDetails->agency_mobile_code) && $accountDetails->agency_mobile_code !='') && (isset($accountDetails->agency_phone) && $accountDetails->agency_phone !='')) {
                $outputArray['regardsAgencyPhoneNo']   =  Common::getFormatPhoneNumberView($accountDetails->agency_mobile_code,$accountDetails->agency_phone);
            }else{
                $outputArray['regardsAgencyPhoneNo']   = '';
            }//eo else

            $agencySettings = AgencySettings::where('agency_id',$accountDetails->account_id)->first();

            if($agencySettings && !empty($agencySettings) && isset($agencySettings->email_configuration_default) && $agencySettings->email_configuration_default == 1) {
                $outputArray['mailConfigUserName']      = config('portal.email_config_username');
                $outputArray['mailConfigPassword']      = config('portal.email_config_password');
                $outputArray['mailConfigHost']          = config('portal.email_config_host');
                $outputArray['mailConfigPort']          = config('portal.email_config_port');
                $outputArray['mailConfigEncryption']    = config('portal.email_config_encryption');
            }
            elseif ($agencySettings && !empty($agencySettings)) {
                $outputArray['mailConfigUserName']      = $agencySettings->email_config_username;
                $outputArray['mailConfigPassword']      = $agencySettings->email_config_password;
                $outputArray['mailConfigHost']          = $agencySettings->email_config_host;
                $outputArray['mailConfigPort']          = $agencySettings->email_config_port;
                $outputArray['mailConfigEncryption']    = $agencySettings->email_config_encryption;
            }

            $outputArray['appName']         = $accountDetails->agency_name;
            $outputArray['appTitle']        = $accountDetails->agency_name;
            $outputArray['appShortName']    = $accountDetails->short_name;
            // $outputArray['footerName']   = $accountDetails->agency_name;
            $outputArray['appAccountId']    = $accountDetails->account_id;

            // if($accountDetails->agency_url != ''){
            //     $outputArray['companyUrl'] = $accountDetails->agency_url;
            // }

            $agencyLogoSavedLocation        = config('common.agency_logo_storage_location');
            $gcs                            = Storage::disk($agencyLogoSavedLocation);

            if($accountDetails->agency_mini_logo != ''){
                if($accountDetails->agency_logo_saved_location == 'gcs'){
                    $outputArray['touchIcon']  =  $gcs->url('uploadFiles/agency/'.$accountDetails->agency_mini_logo);
                    $outputArray['favicons']   =  $gcs->url('uploadFiles/agency/'.$accountDetails->agency_mini_logo);
                    $outputArray['miniLogo']   =  $gcs->url('uploadFiles/agency/'.$accountDetails->agency_mini_logo);                        
                }else{
                    $outputArray['touchIcon']   = 'uploadFiles/agency/'.$accountDetails->agency_mini_logo;
                    $outputArray['favicons']    = 'uploadFiles/agency/'.$accountDetails->agency_mini_logo;
                    $outputArray['miniLogo']    = 'uploadFiles/agency/'.$accountDetails->agency_mini_logo;
                }
                                    
            }else{
               $outputArray['miniLogo'] = ''; 
            }

            if($accountDetails->agency_logo != ''){
                if($accountDetails->agency_logo_saved_location == 'gcs'){
                    $outputArray['largeLogo'] =  $gcs->url('uploadFiles/agency/'.$accountDetails->agency_logo);
                }else{
                    $outputArray['largeLogo'] = 'uploadFiles/agency/'.$accountDetails->agency_logo;
                }
            }

            if($accountDetails->agency_b2b_access_url != ''){
                if(config('app.secure_https')){
                    $outputArray['b2bAccessUrl'] = 'https://'.$accountDetails->agency_b2b_access_url;
                }else{
                    $outputArray['b2bAccessUrl'] = 'http://'.$accountDetails->agency_b2b_access_url;
                }
            }
        } 
        return $outputArray;
    }
    

    public static function getAccountDetails($accountTypeId = 0, $isSupplier = 0,  $returnIds = false ){

        $accountDataObj = AccountDetails::where('status','A');
        $accontDetails  = [];
        $accountDataObj->where('account_id','!=', 1);

        $multipleFlag = UserAcl::hasMultiSupplierAccess();
      
        if($multipleFlag){
            $accessSuppliers = UserAcl::getAccessSuppliers();            
            if(count($accessSuppliers) > 0){
                $accessSuppliers[] = Auth::user()->account_id;
                //$accountDataObj->whereIn('account_id', $accessSuppliers)->orWhere('parent_account_id', Auth::user()->account_id);
                $accountDataObj->where(function($query) use($accessSuppliers){$query->whereIn('account_id', $accessSuppliers)->orWhere('parent_account_id', Auth::user()->account_id);});
            }
        }else{
            // $accountDataObj->where('account_id', Auth::user()->account_id)->orWhere('parent_account_id', Auth::user()->account_id);
            $accountDataObj->where(function($query){$query->where('account_id', Auth::user()->account_id)->orWhere('parent_account_id', Auth::user()->account_id);});
        }       
              
 
        $accountDataObj = $accountDataObj->orderBy('account_name', 'ASC');
        $accountData = $accountDataObj->pluck('account_name','account_id')->toArray();

        if($returnIds){
            $accountIds = [];
            if(count($accountData) > 0){
                foreach ($accountData as $accountID => $accountName) {
                    $accountIds[]=$accountID;
                }
            }
            return $accountIds;
        }
    	return $accountData;
    }

    public static function getAccountId(){
        if(Auth::user()){
            return Auth::user()->account_id;
        }else{
            return 0;
        }
    }

    //Stored to google cloud
    public static function uploadAgencyLogoToGoogleCloud($request)
    {   
        // dd($request
        $logFilesStorageLocation = config('common.agency_logo_storage_location');
        if($logFilesStorageLocation == 'local'){
            $storagePath = public_path().config('common.agency_logo_save_path');
            if(!File::exists($storagePath)) {
                File::makeDirectory($storagePath, $mode = 0777, true, true);            
            }
        }       

        $changeFileName = $request['changeFileName'];
        $fileGet        = file_get_contents($request['fileGet']);
        $disk           = $request->file('image')->move($storagePath, $changeFileName);
        // $disk           = Storage::disk($logFilesStorageLocation)->put('uploadFiles/agency/'.$changeFileName, $fileGet,'public');        
    }

    public function country(){

        return $this->hasOne('App\Models\Common\CountryDetails','country_code','agency_country');
    }

    public function state(){

        return $this->hasOne('App\Models\Common\StateDetails','state_id','agency_state');
    }

    public static function getDomainAccountIds($parentAccId = 0,$requestHeader = []){
        $agencyB2BAccessUrl = isset($requestHeader['portal-origin'][0])?$requestHeader['portal-origin'][0]:'';
        $agencyB2BAccessUrl = str_replace('http://', '', $agencyB2BAccessUrl);
        $agencyB2BAccessUrl = str_replace('https://', '', $agencyB2BAccessUrl);
        if($parentAccId != 0){
            $agencyB2BAccessUrl = AccountDetails::where('account_id', $parentAccId)->value('agency_b2b_access_url');
        }
        $parentAccountId = [];
        if($agencyB2BAccessUrl != ''){
            $parentAccountId = AccountDetails::where('agency_b2b_access_url', $agencyB2BAccessUrl)->pluck('account_id')->toArray();
        }
        return $parentAccountId;
    }

    public static function getAccountContentSource($accountId = 0, $gdsProduct = '', $gdsSource = ''){
        
        $contentSource  =    ContentSourceDetails::select('content_source_id', 'account_id','in_suffix', 'gds_source','gds_source_version', 'default_currency','pcc','gds_product')->where('account_id', $accountId);

        if($gdsProduct != '' && $gdsProduct != 'ALL' ){
            $contentSource->where('gds_product', $gdsProduct);
        }

        if($gdsSource != ''){
            $contentSource->where('gds_source', $gdsSource);
        }

        $contentSource                  = $contentSource->where('status', 'A')->get()->toArray();

        $consumerList                   = PartnerMapping::consumerList($accountId);
        $outputArray['contentSource']   = $contentSource;
        $outputArray['consumerList']    = $consumerList;               
        return $outputArray;
    }

    public static function getPortalBassedAccoutDetail($portalId=0){
        $portalDetails = PortalDetails::where('portal_id',$portalId )->first();
        $accountDetails = [];
        if($portalDetails != null){
            $accountDetails = AccountDetails::where('account_id', $portalDetails->account_id)->first();
        }
        return $accountDetails;
    }


    //Get First Parent Account Details
    public static function getFirstParentAccountDetails($accountId) {

        $accountDetails = array(); 
        $getAccountIs   = true;

        while($getAccountIs) {
            $tmpAccountDetails = AccountDetails::where('account_id', '=', $accountId)->first();
            if(isset($tmpAccountDetails) && !empty($tmpAccountDetails)){
                $tmpAccountDetails = $tmpAccountDetails->toArray();

                if($tmpAccountDetails['parent_account_id'] == 0 || $tmpAccountDetails['parent_account_id'] == $accountId){
                    $accountDetails = $tmpAccountDetails;
                    $getAccountIs   = false; 
                }else{
                    $accountId = $tmpAccountDetails['parent_account_id'];
                }

            }else{
                $getAccountIs  = false; 
            }
        }

        return $accountDetails;
    }

    public static function getSupplierOptions($accountId){
        $data = array();
        //get mapped partners     
        $partnerMapping = PartnerMapping::partnerMappingList($accountId);
        $mappingIds     = array();
        foreach ($partnerMapping as $mappingData) {
                $mappingIds[] = $mappingData->supplier_account_id;
        }
        array_push( $mappingIds,$accountId);
        //get all partners      
        $partnerAll = AccountDetails::select('account_name','account_id')->where('status','A')->get()->toArray();       
        $option =[];
        $i=0;
        foreach ($partnerAll as $key => $value) {
            if(in_array($value['account_id'], $mappingIds)){
                $option[$i]['account_id']=$value['account_id'];
                $option[$i]['account_name']=$value['account_name'];
                $i++;
            }
        }                    
        return $option;
     }

    public static function getSupplierList(){
        $accountDetails =  AccountDetails::where('status','A')->get()->keyBy('account_id')->toArray();
        $supplierList = [];
        foreach ($accountDetails as $accountId => $accountInfo) {
            if($accountInfo['is_supplier'] == 0)continue;
            $supplierList[$accountId] = $accountInfo;
        }
        return $supplierList;
    }

    public static function getParentAccountDetails($accountId){

        $getAccount = AccountDetails::where('account_id',$accountId)->first();
        $returnData = [];
        if($getAccount){
            if(isset($getAccount['parent_account_id']) && ( $getAccount['parent_account_id'] == 0 || $getAccount['parent_account_id'] == '')){
                $returnData = $getAccount->toArray();
            }
            elseif(isset($getAccount['parent_account_id']) && $getAccount['parent_account_id'] != 0){        
                $returnData = AccountDetails::where('account_id',$getAccount['parent_account_id'])->first()->toArray();
            }
        }
        if(count($returnData) == 0){
            $returnData = AccountDetails::where('account_id',config('common.supper_admin_account_id'))->first()->toArray();
        }
        return $returnData;

        
    }//eof

    public static function getAccounts(){

        $loginAccountId     = Auth::user()->account_id;
        $primaryAccountId   = $loginAccountId;
        $loginUserId        = Auth::user()->user_id;
        $defaultAccountId   = [];

        //Get Primary Account Id
        $aPrimaryAcc = DB::table(config('tables.user_extended_access'))->select('user_id','account_id','is_primary')->where('user_id', $loginUserId)->where('is_primary','1')->first();
        if(isset($aPrimaryAcc) && !empty($aPrimaryAcc)){
            $primaryAccountId = $aPrimaryAcc->account_id;
        }

        $results = DB::table(config('tables.account_details'))
            ->join(config('tables.portal_details'), 'portal_details.account_id', '=', 'account_details.account_id')
            ->join(config('tables.portal_credentials'), 'portal_credentials.portal_id', '=', 'portal_details.portal_id')
            ->select('account_details.account_id','account_details.account_name', 'portal_details.portal_id','account_details.agency_product')
            ->where('account_details.status', 'A' )
            ->where('portal_details.status', 'A' )
            ->where('portal_credentials.status','A')
            ->where('portal_details.business_type', 'B2B' )
            ->where('account_details.account_type_id', '>=', 1);
            
        $multipleFlag = UserAcl::hasMultiSupplierAccess();
        
        if($multipleFlag){
            $accountIds     = UserAcl::getAccessSuppliers();
            if(count($accountIds) > 0){
                $accountIds[]   = $loginAccountId;            
                $results->whereIn('portal_details.account_id',$accountIds);
            }
        }else{
            $results->where('portal_details.account_id',$loginAccountId);
        }
        
        $results->groupBy('account_details.account_id', 'portal_details.portal_id');
        $results->orderBy('account_details.account_name','ASC');
        $results = $results->get()->toArray();

        $aTemp = array();
        foreach($results as $aKey => $aVal){
            $checkProduct = json_decode($aVal->agency_product,true);

            if(isset($checkProduct['Flights']) && $checkProduct['Flights'] == 'Y'){
                $aTempVal = [];
                $aTempVal['value'] = $aVal->account_id;
                $aTempVal['label'] = $aVal->account_name;
                
                $aTemp[] = $aTempVal;

                if($aVal->account_id == $primaryAccountId && empty($defaultAccountId)){
                    $defaultAccountId['value']   = $aVal->account_id;
                    $defaultAccountId['label']   = $aVal->account_name;
                }
            }
        }

        $aReturn = array();
        $aReturn['accountDetails']      = $aTemp;
        $aReturn['defaultAccountId']    = $defaultAccountId;

        return $aReturn;

    }

    public static function agencyParentPortalUrl($accountId)
    {
        $account['parent_account_id'] = $accountId ;
        do {
            $accountId = $account['parent_account_id'];
            $account = AccountDetails::select('account_id','parent_account_id')->where('account_id',$accountId)->first()->toArray();
        } while ( $account['parent_account_id'] != 0);

        $portalUrl = PortalDetails::where('account_id',$account['account_id'])->where('business_type','B2B')->value('portal_url');
        return $portalUrl;
    }
    
}