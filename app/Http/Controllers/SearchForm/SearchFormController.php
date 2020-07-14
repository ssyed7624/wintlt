<?php

namespace App\Http\Controllers\SearchForm;

use App\Http\Controllers\Common\Controller;
use App\Models\PortalDetails\PortalDetails;
use App\Models\PortalDetails\PortalConfig;
use App\Models\AccountDetails\AccountDetails;
use App\Models\AccountDetails\AgencyPermissions;
use App\Http\Middleware\UserAcl;
use App\Libraries\Common;
use Illuminate\Http\Request;
use Auth;
use Log;

class SearchFormController extends Controller
{
    public function getSearchFormData(Request $request){

        $requestData = $request->all();        

        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'search_form_view_data_retrieved_successfully';
        $responseData['message']        = __('searchForm.search_form_view_data_retrieved_successfully');

        $siteData       = $request->siteDefaultData;

        $businessType                   = isset($siteData['business_type']) ? $siteData['business_type'] : 'none';

        if(isset($requestData['account_id']) && $businessType == 'B2B'){

            $accountId = (isset($requestData['account_id']) && $requestData['account_id'] != '') ? encryptor('decrypt', $requestData['account_id']) : $siteData['account_id'];

            $getPortal = PortalDetails::where('account_id', $accountId)->where('status', 'A')->where('business_type', 'B2B')->first();

            if($getPortal){
                $portalId               = $getPortal->portal_id;
                
                $siteData['portal_id']  = $portalId;
            }

            $siteData['account_id'] = $accountId;
            

        }

        $searchFormData                 = [] ;
        $portalId                       = $siteData['portal_id'];
        $portalDetails                  = PortalDetails::getPortalInfo($portalId);
        $currencylist                   = [];
        $accountIds                     = [];
        if($portalDetails != null){
            $currencylist               = array_unique(array_merge(explode(',',$portalDetails['portal_default_currency']),explode(',',$portalDetails['portal_selling_currencies'])));
        }
        if(!is_null(Auth::user()))
        {
            $accountDetails                 = AccountDetails::getAccounts();
            if(isset($accountDetails['accountDetails']) && !empty($accountDetails['accountDetails']))
                $accountIds                 = array_column($accountDetails['accountDetails'], 'value');
            else
            {
                $accountIds                 = [];
            }
        }
        
        $recomendedFares                = AgencyPermissions::whereIn('account_id',$accountIds)->pluck('display_recommend_fare','account_id');
        $searchFormData['default_account'] = isset($accountDetails['defaultAccountId']) ? $accountDetails['defaultAccountId'] : [];
        $diplayAccountId = 'N';
        if(UserAcl::isSuperAdmin())
            $diplayAccountId = 'Y';

        $getPortalConfigData = [];

        if($businessType == 'B2C'){
            $getPortalConfigData = PortalConfig::getPortalBasedConfig($siteData['portal_id'],$siteData['account_id']);
            $getPortalConfigData = isset($getPortalConfigData['data']) ? $getPortalConfigData['data']:[];
            $getPortalConfigData['allowed_cabin_types'] = isset($getPortalConfigData['allowed_cabin_types']) && $getPortalConfigData['allowed_cabin_types'] != null && $getPortalConfigData['allowed_cabin_types'] != '' && !empty($getPortalConfigData['allowed_cabin_types'])? $getPortalConfigData['allowed_cabin_types'] :config('flight.flight_classes');
            $getPortalConfigData['allowed_passenger_types'] = isset($getPortalConfigData['allowed_passenger_types']) && $getPortalConfigData['allowed_passenger_types'] != null && $getPortalConfigData['allowed_passenger_types'] != ''? $getPortalConfigData['allowed_passenger_types'] :config('flight.search_passanger_type');
        }
        $searchCabinType = $businessType == 'B2C' ? $getPortalConfigData['allowed_cabin_types']:config('flight.flight_classes');
        $searchPaxType = $businessType == 'B2C' ? $getPortalConfigData['allowed_passenger_types']:config('flight.search_passanger_type');

        $searchFormData['trip_type']                        = [] ;
        $searchFormData['trip_type']['display']             = 'yes';
        foreach(config('flight.search_original_trip_type') as $value){
            $searchFormData['trip_type']['value'][] = $value;
        }
        $searchFormData['account_details'][] = isset($accountDetails['accountDetails']) ? $accountDetails['accountDetails'] : [];
        $searchFormData['recomended_fares_details'] = $recomendedFares;
        $searchFormData['show_account_details'] = $diplayAccountId;

        $searchFormData['passanger_type']                   = [];
        $searchFormData['insurance_passanger_type']         = [];
        $searchFormData['passanger_type']['display']        = 'yes';
        foreach($searchPaxType as $key => $value){
            $searchFormData['passanger_type']['value'][] = $key;
            $insurancePax = [];
            $setPax = config('flight.pax_type');
            $insurancePax['label']  = __('searchForm.'.$key);
            $insurancePax['value']  = $value!=null && $value!=''?$value:$setPax[$key];
            $searchFormData['insurance_passanger_type'][] = $insurancePax;
        }

        $searchFormData['cabin_type']                       = [];
        $searchFormData['cabin_type']['display']            = 'yes';
        foreach($searchCabinType as $key => $value){
            if($value != 'ALL')
                $searchFormData['cabin_type']['value'][] = $key;
        }

        $searchFormData['alternate_dates']                  = [];
        $searchFormData['alternate_dates']['display']       = 'yes';
        $searchFormData['alternate_dates']['value']         = config('flight.search_alternate_dates');

        $searchFormData['airline_preference']               = [];
        $searchFormData['airline_preference']['display']    = 'yes';
        $searchFormData['stop_preference']                  = [];
        $searchFormData['stop_preference']['display']       = 'yes';
        foreach(config('flight.flight_criteria') as $key => $value){
            $searchFormData['airline_preference']['value'][]    = $key;
            $searchFormData['stop_preference']['value'][]       = $key;
        }
        foreach($currencylist as $value){
            $searchFormData['currency_list'][]                  = $value;
        }
        
        $searchFormData['direct_flights']                   = [];
        $searchFormData['direct_flights']['display']        = 'yes';
        
        $searchFormData['refundable_fares_only']            = [];
        $searchFormData['refundable_fares_only']['display'] = 'yes';
        
        $searchFormData['near_by_airports']                 = [];
        $searchFormData['near_by_airports']['display']      = 'yes';
        
        $searchFormData['avoid_us']                         = [];
        $searchFormData['avoid_us']['display']              = 'yes';
        
        $searchFormData['with_baggage']                     = [];
        $searchFormData['with_baggage']['display']          = 'yes';

        $searchFormData['recent_search']                     = [];
        $searchFormData['recent_search']['display']          = config('flight.recent_search_required') ? 'yes' : 'no';

        if(config('flight.recent_search_required')){
            $authUserId = isset(Auth::user()->user_id) ? Auth::user()->user_id : 0;

            if($authUserId != 0){

                $recentSearchData = Common::getRedis('AgentWiseSearchRequest_'.$authUserId);

                $searchFormData['recent_search']['data']  = !empty($recentSearchData) ? json_decode($recentSearchData,true) : [];
                $searchFormData['recent_search']['data'] = array_reverse($searchFormData['recent_search']['data']);
            }
        }

        $searchFormData['recent_search_hotel']                     = [];
        $searchFormData['recent_search_hotel']['display']          = config('flight.hotel_recent_search_required') ? 'yes' : 'no';
        if(config('flight.hotel_recent_search_required')){
            $authUserId = isset(Auth::user()->user_id) ? Auth::user()->user_id : 0;

            if($authUserId != 0){

                $recentSearchData = Common::getRedis('AgentWiseHotelSearchRequest_'.$authUserId);

                $searchFormData['recent_search_hotel']['data']  = !empty($recentSearchData) ? json_decode($recentSearchData,true) : [];
                $searchFormData['recent_search_hotel']['data'] = array_reverse($searchFormData['recent_search_hotel']['data']);
            }
        }

        $searchFormData['recent_search_pacakge']                     = [];
        $searchFormData['recent_search_pacakge']['display']          = config('flight.package_recent_search_required') ? 'yes' : 'no';
        if(config('flight.package_recent_search_required')){
            $authUserId = isset(Auth::user()->user_id) ? Auth::user()->user_id : 0;

            if($authUserId != 0){

                $recentSearchData = Common::getRedis('AgentWisePackageSearchRequest_'.$authUserId);

                $searchFormData['recent_search_pacakge']['data']  = !empty($recentSearchData) ? json_decode($recentSearchData,true) : [];
                $searchFormData['recent_search_pacakge']['data'] = array_reverse($searchFormData['recent_search_pacakge']['data']);
            }
        }

        $searchFormData['recent_search_insurance']                     = [];
        $searchFormData['recent_search_insurance']['display']          = config('flight.insurance_recent_search_required') ? 'yes' : 'no';
        if(config('flight.insurance_recent_search_required')){
            $authUserId = isset(Auth::user()->user_id) ? Auth::user()->user_id : 0;

            if($authUserId != 0){

                $recentSearchData = Common::getRedis('AgentWiseInsuranceSearchRequest_'.$authUserId);

                $searchFormData['recent_search_insurance']['data']  = !empty($recentSearchData) ? json_decode($recentSearchData,true) : [];
                $searchFormData['recent_search_insurance']['data'] = array_reverse($searchFormData['recent_search_insurance']['data']);
            }
        }

        $responseData['data']                               = $searchFormData;
        if(config('common.add_res_time')){
            $responseData['resTime']                            = (microtimeFloat()-START_TIME);
        }

        return response()->json($responseData);
    }


    public function getPackageFormData(){

        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'recored_not_found';
        $responseData['message']        = __('common.recored_not_found');

        $searchFormData                                     = [] ;

        $searchFormData['passanger_type']                   = [];
        $searchFormData['passanger_type']['display']        = 'yes';
        $searchFormData['passanger_type']['value']          = config('flight.passanger_type');

        $searchFormData['cabin_type']                       = [];
        $searchFormData['cabin_type']['display']            = 'yes';
        $searchFormData['cabin_type']['value']              = config('flight.flight_classes');

        $searchFormData['no_of_rooms_allowed']              = config('flight.no_of_rooms_allowed');

        $searchFormData['flight_preferences']               = array(
                                                                "direct_flights" => true,
                                                                "with_baggage" => true,
                                                                "near_by_airports" => true,
                                                                "refundable_fares_only" => false
                                                            );

        $searchFormData['required_hotel_part_of_my_stay']   = true;


        if(count($searchFormData) > 0){
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'search_form_view_data_retrieved_successfully';
            $responseData['message']        = __('searchForm.search_form_view_data_retrieved_successfully');
            $responseData['data']           = $searchFormData;
        }else{
            $responseData['errors'] = ["error" => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }
}