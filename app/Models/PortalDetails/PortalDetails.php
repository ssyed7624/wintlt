<?php

namespace App\Models\PortalDetails;


use App\Models\AccountDetails\AgencyPermissions;
use App\Models\PortalDetails\PortalCredentials;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalConfig;
use App\Models\UserDetails\UserDetails;
use App\Models\UserRoles\UserRoles;
use App\Http\Middleware\UserAcl;
use App\Libraries\Common;
use App\Models\Model;
use DB;


class PortalDetails extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.portal_details');
    }

    public function getTableColumns() {
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

    protected $primaryKey = 'portal_id';

    protected $fillable = [
        'b2b_portal_id','parent_portal_id','portal_name','portal_short_name','portal_url','prime_country','business_type','portal_default_currency','portal_selling_currencies','portal_settlement_currencies','notification_url','mrms_notification_url','portal_notify_url','ptr_lniata','dk_number','default_queue_no','card_payment_queue_no','cheque_payment_queue_no','pay_later_queue_no','misc_bcc_email','booking_bcc_email','ticketing_bcc_email','agency_name','iata_code','agency_address1','agency_address2','agency_city','agency_state','agency_country','agency_zipcode','agency_mobile_code','agency_mobile','agency_phone','agency_fax','agency_email','agency_contact_title','agency_contact_fname','agency_contact_lname','agency_contact_mobile_code','agency_contact_mobile','agency_contact_phone','agency_contact_extn','agency_contact_email','products','product_rsource','is_meta_search','max_itins_meta_user','status','created_by','created_at','updated_by','updated_at','agency_mobile_code_country','agency_contact_mobile_code_country','account_id','send_dk_number','send_queue_number','allow_seat_mapping','allow_hotel',
    ];

    public function portalCredentials(){

        return $this->hasMany('App\Models\PortalDetails\PortalCredentials','portal_id')->where('is_meta','N')->where('status','A');
    }

    public function getAllPortalDetails(){
        return PortalDetails::whereIn('Status',array('A','IA'))->orderBy('updated_at','DESC')->get();
    }


    //get portal detail from portal id
    public static function getPortalDatas($portalId,$flag = 0){
        $returnArray = [];
        $portalDetails = PortalDetails::where('portal_id',$portalId)->first();
        if($portalDetails && !empty($portalDetails) && $portalDetails->business_type == 'META' && $portalDetails->parent_portal_id != 0){
             $portalDetails = PortalDetails::where('portal_id',$portalDetails->parent_portal_id)->first();
        }
        if($portalDetails){
            $returnArray = $portalDetails->toArray();
        }
        if(empty($returnArray) && $flag == 1)
        {
            $returnArray = config('common.portal_details_config');
        }
        return $returnArray;
    }//eof

    //get portalconfig data by portal id
    public static function getPortalConfigData($portal_id){
        $returnData = '';

        $portalDetails = PortalDetails::where('portal_id',$portal_id)->first();
        if($portalDetails && !empty($portalDetails) && $portalDetails->business_type == 'META' && $portalDetails->parent_portal_id != 0){
             $portal_id = $portalDetails->parent_portal_id;
        }

        $portalConfigData = PortalConfig::where('portal_id',$portal_id)->where('status','A')->value('config_data');
        if(isset($portalConfigData) && $portalConfigData != ''){
            $unserialize = unserialize($portalConfigData);
            $returnData = $unserialize['data'];
        }
        return $returnData;
    }//eof
    public static function getAllPortalDetailsByUser($metaPortalId = 0 ,$requestData = []){
        
        $returnData     = array();
        $accountIds = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1, true);
        $portalDetails = DB::table(config('tables.portal_details').' AS pd')
                            ->select(
                                'pd.*',
                                'ad.account_name'
                            )
                            ->leftJoin(config('tables.account_details').' AS ad', 'ad.account_id' ,'=','pd.account_id')
                            ->leftJoin(config('tables.agency_permissions').' AS ap', 'ap.account_id' ,'=','pd.account_id')
                            ->whereIn('pd.status',['A','IA'])->whereIn('pd.account_id', $accountIds);

        if($metaPortalId != 0){
            $portalDetails = $portalDetails->where('pd.business_type','META')->where('pd.parent_portal_id',$metaPortalId);
        }
 
        if($metaPortalId == 0){
            $portalDetails = $portalDetails->where('pd.business_type','!=','META');
            // Filter apply For Portal
            if((isset($requestData['query']['business_type']) && $requestData['query']['business_type'] != '' && $requestData['query']['business_type'] != 'ALL' && $requestData['query']['business_type'] != '0')||(isset($requestData['business_type']) && $requestData['business_type'] != '' && $requestData['business_type'] != 'ALL' && $requestData['business_type'] != '0')){
                $requestData['business_type']   = (isset($requestData['query']['business_type']) && $requestData['query']['business_type'] != '')?$requestData['query']['business_type'] :$requestData['business_type'];
                $portalDetails            = $portalDetails->where('pd.business_type',$requestData['business_type']);
            }
            if((isset($requestData['query']['portal_url']) && $requestData['query']['portal_url'] != '') || (isset($requestData['portal_url']) && $requestData['portal_url'] != '')){
                $requestData['portal_url']   = (isset($requestData['query']['portal_url']) && $requestData['query']['portal_url'] != '')?$requestData['query']['portal_url'] :$requestData['portal_url'];
                $portalDetails = $portalDetails->where('pd.portal_url','like','%'.$requestData['portal_url'].'%');
            }
        }
        
        if((isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '' && $requestData['query']['account_id'] != 'ALL' && $requestData['query']['account_id'] != '0')||(isset($requestData['account_id']) && $requestData['account_id'] != '' && $requestData['account_id'] != 'ALL' && $requestData['account_id'] != '0')){
            $requestData['account_id']   = (isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '')?$requestData['query']['account_id'] :$requestData['account_id'];
            $portalDetails            = $portalDetails->where('pd.account_id',$requestData['account_id']);
        }

        if((isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '' && $requestData['query']['portal_id'] != 'ALL' && $requestData['query']['portal_id'] != '0')||(isset($requestData['portal_id']) && $requestData['portal_id'] != '' && $requestData['portal_id'] != 'ALL' && $requestData['portal_id'] != '0')){
            $requestData['portal_id']   = (isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '')?$requestData['query']['portal_id'] :$requestData['portal_id'];
            $portalDetails            = $portalDetails->where('pd.portal_id',$requestData['portal_id']);
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL')||(isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL')){
            $requestData['status']   = (isset($requestData['query']['status']) && $requestData['query']['status'] != '')?$requestData['query']['status'] :$requestData['status'];
            $portalDetails            = $portalDetails->where('pd.status',$requestData['status']);
        }
        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            switch($requestData['orderBy']) {
                case 'portal_name':
                    $portalDetails    = $portalDetails->orderBy('pd.portal_name',$sorting);
                    break;
                case 'account_name':
                    $portalDetails    = $portalDetails->orderBy('ad.account_name',$sorting);
                    break;
                default:
                    $portalDetails    = $portalDetails->orderBy($requestData['orderBy'],$sorting);
                    break;
            }
        }else{
            $portalDetails = $portalDetails->orderBy('updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit'])- $requestData['limit']; 
        //record count
        $portalDetails = $portalDetails->groupBy('portal_id');
        $portalDetailsCount  = $portalDetails->get()->count();
      
        $returnData['recordsTotal']     = $portalDetailsCount;
        $returnData['recordsFiltered']  = $portalDetailsCount;
        $returnData['start']            = $start;
        // Get Record
        $portalDetails                  = $portalDetails->offset($start)->limit($requestData['limit']);
        $returnData['portaldata']       = $portalDetails->get();
        $returnData['portaldata']       = json_decode($returnData['portaldata'],true);
        return $returnData;
    }//eof

    public function user(){
        return $this->belongsTo('App\Models\UserDetails\UserDetails','created_by');
    }

    public function accountDetails(){
        return $this->belongsTo('App\Models\AccountDetails\AccountDetails','account_id');
    }

    public function agencyPermissions(){
        return $this->belongsTo('App\Models\AccountDetails\AgencyPermissions','account_id');
    }

    public static function getPortalInfo($portalId = 0){

        $portalDetails = self::where('portal_id', $portalId)->first();

        if($portalDetails){
            $portalDetails['accountDetails']    = AccountDetails::where('account_id', $portalDetails['account_id'])->first();
            $ownerRoleId                        = UserRoles::where('role_code','AO')->value('role_id');
            $portalDetails['userDetails']       = UserDetails::where('account_id', $portalDetails['account_id'])->where('role_id', $ownerRoleId)->first();
            $portalDetails['portalCredential']  = PortalCredentials::where('portal_id', $portalId)->get();
        }

        return $portalDetails;
    }

    //Get allowded Meta Portal Count
    public static function getAccountBasedMetaPortalCount($portalId)
    {
        $returnFlag = 0;
        $accountId = self::where('portal_id',$portalId)->whereIn('status',['A','IA'])->value('account_id');
        $allowdedMetaPortalCount = AgencyPermissions::where('account_id',$accountId)->value('no_of_meta_connection_allowed');
        $portalIds = self::where('account_id',$accountId)->whereIn('status',['A','IA'])->pluck('portal_id');
        $portalCredentialCount = PortalCredentials::whereIn('portal_id',$portalIds)->where('is_meta','Y')->whereIn('status',['A','IA'])->count();
        if($allowdedMetaPortalCount > $portalCredentialCount)
        {
            $returnFlag = 1;
        }
        return $returnFlag;
    }
    public static function getAccountPortal($accountId = 0){
        return PortalDetails::where('status','A')->where('account_id', $accountId)->orderBy('portal_name','ASC')->get();
    }

    public static function getPortalList($partnerAccountId, $requiredAll = 'true')
    {
        $portalDataObj = PortalDetails::where('status','A');            

        $portalIds  = [];
        // if(!UserAcl::isSuperAdmin()){
        //     $accessDetails = Auth::user()->extendedAccess['accessDetails'];        
        //     if(count($accessDetails)){
        //         foreach ($accessDetails as $accountTypeId => $accountDetails) {
        //             foreach ($accountDetails as $accountId => $details) {
        //                 foreach ($details as $portalId => $roleId) {
        //                     $portalIds[]=$portalId;
        //                 }
        //             }
        //         }
        //     }
        //     $portalIds = array_unique(array_filter($portalIds));
        // }
        $portalDataObj->where('account_id',$partnerAccountId);
 
        $portalDataObj->orderBy('portal_name','ASC');

        $portalData = $portalDataObj->pluck('portal_name','portal_id')->toArray();

        $aReturn['Status'] = 'FAILED';

        $portalSet = [];               
        if(count($portalData) > 0){
            if($requiredAll == 'true'){
                $portalSet[0] = __('common.all');
                $portalData = $portalSet+$portalData;
            }
        }
        $aReturn['data']        = [];
        if(count($portalData) > 0){
            $aReturn['Status']  = 'SUCCESS';
            $aReturn['data']    = $portalData;
        }
        return $aReturn;
    }

    public static function getAllPortalList($businessType = array('B2C'),$requiredAll = 'true'){
        $accountIds             = AccountDetails::getAccountDetails(1,0,true);
        $portalDetails          = PortalDetails::select('portal_id','portal_name')->whereIn('Status',array('A'))->whereIn('business_type', $businessType)->whereIn('account_id', $accountIds)->orderBy('portal_name','ASC')->get()->toArray();
       
        $responseData           = [];
        $responseData['Status'] = 'failed';
        if(count($portalDetails) > 0){
            if($requiredAll == 'true'){
                $temp                   = [];
                $temp['portal_id']      = 0;
                $temp['portal_name']    = __('common.all');
                array_unshift($portalDetails,$temp);
            }
        }
        $responseData['data']        = [];
        if(count($portalDetails) > 0){
            $responseData['Status']  = 'success';
            $responseData['data']    = $portalDetails;
        }
        return $responseData;
    }

    public static function getPortalListForConfig($requiredAll = 'true'){
        $accountIds             = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),0, true);
        $b2cPortalDetails          = PortalDetails::select('portal_id','portal_name')->whereIn('Status',array('A'))->where('business_type', 'B2C')->whereIn('account_id', $accountIds)->orderBy('portal_name','ASC')->get()->toArray();
        // $b2bPortalDetails          = PortalDetails::select('portal_id','portal_name')->whereIn('Status',array('A'))->where('business_type', 'B2C')->whereIn('account_id', $accountIds)->orderBy('portal_name','ASC')->get()->toArray();
       
        $responseData           = [];
        if(count($b2cPortalDetails) > 0){
            if($requiredAll == 'true'){
                $temp                   = [];
                $temp['portal_id']      = 0;
                $temp['portal_name']    = __('common.all');
                array_unshift($b2cPortalDetails,$temp);
            }
        }
        if(count($b2cPortalDetails) > 0){
            $responseData    = $b2cPortalDetails;
        }
        return $responseData;
    }

    public static function getPortalDetailsBasedByAccountId($accountId,$requiredAll = true){
        $portalData             = [];
        $portalDetails          = PortalDetails::where('status','A')->where('account_id',$accountId)->orderBy('portal_name','ASC')->get();            

        if($portalDetails != null){

            foreach($portalDetails as $value){
                if($requiredAll){
                    $value['portal_name']   =  $value['portal_name'].' ('.$value['business_type'].')';
                }
                $portalData []  = $value;
            }
        }
        return $portalData;
    }

    //get portal details by ac ids
    public static function getPortalsByAcIds($acIds){
        $getPortals     = PortalDetails::On('mysql2')->select('portal_id','portal_name')->whereIn('Status',array('A'))->whereIn('account_id', $acIds)->orderBy('portal_name','ASC')->get()->toArray();
        if(count($getPortals) > 0){
           return $getPortals; 
        }else{
            return '';
        }        
    }

    // get portal name
    public static function getPortalName($portalId){
        return PortalDetails::where('portal_id',$portalId)->value('portal_name');
    }

}
