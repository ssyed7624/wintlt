<?php 
namespace App\Libraries;

use App\Models\ProfileAggregation\ProfileAggregation;
use App\Models\PaymentGateway\PaymentGatewayDetails;
use App\Models\ContentSource\ContentSourceDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\Common\LogActivities;
use App\Models\Common\StateDetails;
use App\Models\Common\AirlinesInfo;
use App\Models\UserRoles\UserRoles;
use App\Http\Middleware\UserAcl;
use App\Models\Model;
use DateTimeZone;
use DateTime;
use Request;
use Storage;
use Lang;
use Auth;
use File;
use Log;
use DB;

class History
{
	public static function commonHistoryData($inputArray)
	{
		$givenArray = (array)$inputArray;
		$accountDetails = AccountDetails::whereIn('status',['A','IA'])->pluck('account_name','account_id')->toArray();
        $userDetails = UserDetails::select(DB::raw("CONCAT(first_name,' ',last_name) as user_name"),'user_id')->whereIn('status',['A','IA'])->pluck('user_name','user_id')->toArray();
		$portalDetails = PortalDetails::whereIn('status',['A','IA'])->pluck('portal_name','portal_id')->toArray();
		if(isset($givenArray['account_id']) && $givenArray['account_id'] != '')
        {
            $accountNames = '';
            if($givenArray['account_id'] == 0)
            {
                $accountNames = 'ALL';
            }
            elseif(is_numeric($givenArray['account_id'])){
                $accountNames = isset($accountDetails[$givenArray['account_id']]) ? $accountDetails[$givenArray['account_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['account_id']))
                {
                    $tempAccountIDs = $givenArray['account_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['account_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($accountDetails[$givenArray['account_id']]) ? $accountDetails[$givenArray['account_id']] : 'Not Set';
                        if($accountNames == '')
                        {
                            $accountNames = $tempAccountName;
                        }
                        else{
                            $accountNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['account_id']);
            $givenArray['account_name'] = $accountNames;
        }
        if(isset($givenArray['portal_id']) && $givenArray['portal_id'] != '')
        {
            $portaltNames = '';
            if($givenArray['portal_id'] == 0)
            {
                $portaltNames = 'ALL';
            }
            elseif(is_numeric($givenArray['portal_id'])){
                $portaltNames = isset($portalDetails[$givenArray['portal_id']]) ? $portalDetails[$givenArray['portal_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['portal_id']))
                {
                    $tempAccountIDs = $givenArray['portal_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['portal_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($portalDetails[$givenArray['portal_id']]) ? $portalDetails[$givenArray['portal_id']] : 'Not Set';
                        if($portaltNames == '')
                        {
                            $portaltNames = $tempAccountName;
                        }
                        else{
                            $portaltNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['portal_id']);
            $givenArray['portal_name'] = $portaltNames;
        }
        if(isset($givenArray['parent_portal_id']) && $givenArray['parent_portal_id'] != '')
        {
            $portaltNames = '';
            if($givenArray['parent_portal_id'] == 0)
            {
                $portaltNames = 'ALL';
            }
            elseif(is_numeric($givenArray['parent_portal_id'])){
                $portaltNames = isset($portalDetails[$givenArray['parent_portal_id']]) ? $portalDetails[$givenArray['parent_portal_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['parent_portal_id']))
                {
                    $tempAccountIDs = $givenArray['parent_portal_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['parent_portal_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($portalDetails[$givenArray['parent_portal_id']]) ? $portalDetails[$givenArray['parent_portal_id']] : 'Not Set';
                        if($portaltNames == '')
                        {
                            $portaltNames = $tempAccountName;
                        }
                        else{
                            $portaltNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['parent_portal_id']);
            $givenArray['parent_portal_name'] = $portaltNames;
        }
        if(isset($givenArray['consumer_account_id']) && $givenArray['consumer_account_id'] != '')
        {
            $accountNames = '';
            if($givenArray['consumer_account_id'] == 0)
            {
                $accountNames = 'ALL';
            }
            elseif(is_numeric($givenArray['consumer_account_id'])){
                $accountNames = isset($accountDetails[$givenArray['consumer_account_id']]) ? $accountDetails[$givenArray['consumer_account_id']] : 'Not Set';;
            }
            else
            {
                if(is_array($givenArray['consumer_account_id']))
                {
                    $tempAccountIDs = $givenArray['consumer_account_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['consumer_account_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($accountDetails[$accountValue]) ? $accountDetails[$accountValue] : 'Not Set';
                        if($accountNames == '')
                        {
                            $accountNames = $tempAccountName;
                        }
                        else{
                            $accountNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['consumer_account_id']);
            $givenArray['consumer_account_name'] = $accountNames;
        }
        if(isset($givenArray['supplier_account_id']) && $givenArray['supplier_account_id'] != '')
        {
            $accountNames = '';
            if($givenArray['supplier_account_id'] == 0)
            {
                $accountNames = 'ALL';
            }
            elseif(is_numeric($givenArray['supplier_account_id'])){
                $accountNames = isset($accountDetails[$givenArray['supplier_account_id']]) ? $accountDetails[$givenArray['supplier_account_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['supplier_account_id']))
                {
                    $tempAccountIDs = $givenArray['supplier_account_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['supplier_account_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($accountDetails[$accountValue]) ? $accountDetails[$accountValue] : 'Not Set';
                        if($accountNames == '')
                        {
                            $accountNames = $tempAccountName;
                        }
                        else{
                            $accountNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['supplier_account_id']);
            $givenArray['consumer_account_name'] = $accountNames;
        }
        if(isset($givenArray['content_source_id']) && $givenArray['content_source_id'] != '')
        {
        	$contentSource = ContentSourceDetails::select(DB::raw("CONCAT(gds_source,' ',gds_source_version,' ',pcc,' ',in_suffix,' (',default_currency,')') as content_source_name"),'content_source_id')->whereIn('status',['A','IA'])->pluck('content_source_name','content_source_id')->toArray();
            $accountNames = '';
            if($givenArray['content_source_id'] == 0)
            {
                $accountNames = 'ALL';
            }
            elseif(is_numeric($givenArray['content_source_id'])){
                $accountNames = isset($contentSource[$givenArray['content_source_id']]) ? $contentSource[$givenArray['content_source_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['content_source_id']))
                {
                    $tempAccountIDs = $givenArray['content_source_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['content_source_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($contentSource[$accountValue]) ? $contentSource[$accountValue] : 'Not Set';
                        if($accountNames == '')
                        {
                            $accountNames = $tempAccountName;
                        }
                        else{
                            $accountNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['content_source_id']);
            $givenArray['content_source_name'] = $accountNames;
        }
        if(isset($givenArray['payment_gateway_ids']) && isset($givenArray['payment_gateway_ids']) != '')
        {
            $gatewayNames = '';
            $tempExplode = [];
            if(is_array($givenArray['payment_gateway_ids']))
            {
                $tempExplode = $givenArray['payment_gateway_ids'];
            }
            else
            {
                $tempExplode = explode(',', $givenArray['payment_gateway_ids']);
            }
            if(count($tempExplode) > 0)
            {
                foreach ($tempExplode as $key => $value) {
                    if(is_numeric($value)){
                        if($gatewayNames == '')
                        {
                            $gatewayNames = PaymentGatewayDetails::getPaymentGateWayName($value);
                        }
                        else{
                            $gatewayNames .= ' , '.PaymentGatewayDetails::getPaymentGateWayName($value);
                        }
                    }
                }
            }
            unset($givenArray['payment_gateway_ids']);
            $givenArray['payment_gateway_names'] = $gatewayNames ;
        }
        if(isset($givenArray['supplier_id']) && $givenArray['supplier_id'] != '')
        {
            $accountNames = '';
            if($givenArray['supplier_id'] == 0)
            {
                $accountNames = 'ALL';
            }
            elseif(is_numeric($givenArray['supplier_id'])){
                $accountNames = isset($accountDetails[$givenArray['supplier_id']]) ? $accountDetails[$givenArray['supplier_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['supplier_id']))
                {
                    $tempAccountIDs = $givenArray['supplier_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['supplier_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($accountDetails[$givenArray['supplier_id']]) ? $accountDetails[$givenArray['supplier_id']] : 'Not Set';
                        if($accountNames == '')
                        {
                            $accountNames = $tempAccountName;
                        }
                        else{
                            $accountNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['supplier_id']);
            $givenArray['supplier_name'] = $accountNames;
        }
        if(isset($givenArray['consumer_id']) && $givenArray['consumer_id'] != '')
        {
            $accountNames = '';
            if($givenArray['consumer_id'] == 0)
            {
                $accountNames = 'ALL';
            }
            elseif(is_numeric($givenArray['consumer_id'])){
                $accountNames = isset($accountDetails[$givenArray['consumer_id']]) ? $accountDetails[$givenArray['consumer_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['consumer_id']))
                {
                    $tempAccountIDs = $givenArray['consumer_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['consumer_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($accountDetails[$givenArray['consumer_id']]) ? $accountDetails[$givenArray['consumer_id']] : 'Not Set';
                        if($accountNames == '')
                        {
                            $accountNames = $tempAccountName;
                        }
                        else{
                            $accountNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['consumer_id']);
            $givenArray['consumer_name'] = $accountNames;
        }
        if(isset($givenArray['account_type_id']))
        {
            unset($givenArray['account_type_id']);
        }
        if(isset($givenArray['password']))
        {
            unset($givenArray['password']);
        }
        if(isset($givenArray['is_b2c_login_link']))
        {
            if($givenArray['is_b2c_login_link'] == 0){
                $givenArray['is_b2c_login_link'] = 'No';
            }
            if($givenArray['is_b2c_login_link'] == 1){
                $givenArray['is_b2c_login_link'] = 'Yes';
            }
        }
        if(isset($givenArray['user_extended_access']))
        {
            $tempExtendedAccess = json_decode($givenArray['user_extended_access'],true);
            $tempValue = [];
            if(!empty($tempExtendedAccess) && count($tempExtendedAccess) > 0)
            {
                foreach ($tempExtendedAccess as $key => $value) {
                    $tempExtendedAccess[$key]['account_name'] = isset($accountDetails[$value['account_id']]) ? $accountDetails[$value['account_id']] : 'Not Set';
                    $tempExtendedAccess[$key]['role_name'] = UserRoles::getUserRoleName($value['role_id']);
                    if($value['is_primary'] == 0)
                        $tempExtendedAccess[$key]['is_primary'] = 'No';
                    if($value['is_primary'] == 1)
                        $tempExtendedAccess[$key]['is_primary'] = 'Yes';
                    unset($tempExtendedAccess[$key]['account_id']);
                    unset($tempExtendedAccess[$key]['role_id']);
                }
            }
            $givenArray['user_extended_access'] = json_encode($tempExtendedAccess);
            
        }
        if(isset($givenArray['supplier_partner_mapping'])){
            $tempArray = [];            
            $supplierPartnerMapping = $givenArray['supplier_partner_mapping'];            
            foreach($supplierPartnerMapping as $account => $portalId){
                $portalName = '';
                $portalIds = explode(',', $portalId);                
                foreach($portalIds as $key => $id){                    
                    $portalName = ($id > 0)?(isset($portalDetails[$id]) ? $portalDetails[$id] : 'Not Set'):'ALL';
                    if(count($portalIds) < ($key -1)){
                        $portalName = $portalName.', ';
                    }
                }                
                $tempArray[] = [
                    'accountName' => isset($accountDetails[$account]) ? $accountDetails[$account] : 'Not Set',
                    'portalName' => $portalName
                ]; 
            }
            $givenArray['supplier_partner_mapping'] = $tempArray;
        }
        if(isset($givenArray['airport_check'])){
            unset($givenArray['airport_check']);
        }
        if(isset($givenArray['profileAggregationContentSource'])){
            if(count($givenArray['profileAggregationContentSource']) > 0){
                $contentType =[];                
                foreach($givenArray['profileAggregationContentSource'] as $key => $profileAggregationContentSource){                    
                    if(isset($profileAggregationContentSource['searching']) && isset($profileAggregationContentSource['content_type'])){
                        $contentType[$profileAggregationContentSource['content_type']][] = $profileAggregationContentSource['searching'];
                    }
                }
                $tempArray = [];
                if(count($contentType) > 0){
                    foreach($contentType as $key => $val){
                        if($key == 'CS'){
                            $tempArray[$key] = ContentSourceDetails::whereIn('content_source_id', $val)->select('content_source_id', DB::raw("CONCAT(gds_source,'_',pcc) as name"))->pluck('name', 'content_source_id')->toArray();
                        } else {
                            $tempArray[$key] = ProfileAggregation::whereIn('profile_aggregation_id', $val)->pluck('profile_name', 'profile_aggregation_id')->toArray();
                        }
                    }                                
                }
                foreach($givenArray['profileAggregationContentSource'] as $key => $profileAggregationContentSource){
                    if(isset($profileAggregationContentSource['booking_public'])){
                        $profileAggregationContentSource['content_type'] = isset($profileAggregationContentSource['content_type']) ? $profileAggregationContentSource['content_type'] : 'CS';
                        $givenArray['profileAggregationContentSource'][$key]['booking_public'] = isset($tempArray[$profileAggregationContentSource['content_type']][$profileAggregationContentSource['booking_public']]) ? $tempArray[$profileAggregationContentSource['content_type']][$profileAggregationContentSource['booking_public']] : '-';
                    }
                    if(isset($profileAggregationContentSource['booking_private'])){
                        $profileAggregationContentSource['content_type'] = isset($profileAggregationContentSource['content_type']) ? $profileAggregationContentSource['content_type'] : 'CS';

                        $givenArray['profileAggregationContentSource'][$key]['booking_private'] = isset($tempArray[$profileAggregationContentSource['content_type']][$profileAggregationContentSource['booking_private']]) ? $tempArray[$profileAggregationContentSource['content_type']][$profileAggregationContentSource['booking_private']] : '-';
                    }
                    if(isset($profileAggregationContentSource['ticketing_public'])){
                        $profileAggregationContentSource['content_type'] = isset($profileAggregationContentSource['content_type']) ? $profileAggregationContentSource['content_type'] : 'CS';

                        $givenArray['profileAggregationContentSource'][$key]['ticketing_public'] = isset($tempArray[$profileAggregationContentSource['content_type']][$profileAggregationContentSource['ticketing_public']]) ? $tempArray[$profileAggregationContentSource['content_type']][$profileAggregationContentSource['ticketing_public']] : '-';
                    }
                    if(isset($profileAggregationContentSource['ticketing_private'])){
                        $profileAggregationContentSource['content_type'] = isset($profileAggregationContentSource['content_type']) ? $profileAggregationContentSource['content_type'] : 'CS';

                        $givenArray['profileAggregationContentSource'][$key]['ticketing_private'] = isset($tempArray[$profileAggregationContentSource['content_type']][$profileAggregationContentSource['ticketing_private']]) ? $tempArray[$profileAggregationContentSource['content_type']][$profileAggregationContentSource['ticketing_private']] : '-';
                    }
                    if(isset($profileAggregationContentSource['content_type'])){
                        $givenArray['profileAggregationContentSource'][$key]['content_type'] = ($profileAggregationContentSource['content_type'] == 'CS')?'Content Source':'Aggreagation Profile';
                    }
                    if(isset($profileAggregationContentSource['searching'])){
                        $profileAggregationContentSource['content_type'] = isset($profileAggregationContentSource['content_type']) ? $profileAggregationContentSource['content_type'] : 'CS';
                        
                        $givenArray['profileAggregationContentSource'][$key]['searching'] = isset($tempArray[$profileAggregationContentSource['content_type']][$profileAggregationContentSource['searching']]) ? $tempArray[$profileAggregationContentSource['content_type']][$profileAggregationContentSource['searching']] : '-';
                    }
                    if(isset($profileAggregationContentSource['fare_types'])){
                        $fareTypes = explode(',', $profileAggregationContentSource['fare_types']);                    
                        $fareTypeName = '';
                        if(count($fareTypes) > 0){
                            foreach($fareTypes as $fareKey => $fareType){                                       
                                $fareTypeName .= ', '. __('common.'.$fareType);
                                if($fareKey == 0){
                                    $fareTypeName = __('common.'.$fareType);
                                }
                            }
                        }
                        $givenArray['profileAggregationContentSource'][$key]['fare_types'] =  $fareTypeName;
                    }
                }                
                
            }
            $givenArray['profile_aggregation_content_source'] = $givenArray['profileAggregationContentSource'];
            unset($givenArray['profileAggregationContentSource']);           
        }
        if(isset($givenArray['created_by']) && is_numeric($givenArray['created_by']))
        {
            $givenArray['created_by'] = isset($userDetails[$givenArray['created_by']]) ? $userDetails[$givenArray['created_by']] : 'Not Set';
        }
        if(isset($givenArray['updated_by']) && is_numeric($givenArray['updated_by']))
        {
            $givenArray['updated_by'] = isset($userDetails[$givenArray['updated_by']]) ? $userDetails[$givenArray['updated_by']] : 'Not Set';
        }
        if(isset($givenArray['created_at']) && is_numeric($givenArray['created_at']))
        {
            $givenArray['created_at'] = Common::getTimeZoneDateFormat($givenArray['created_at']);
        }
        if(isset($givenArray['updated_at']) && is_numeric($givenArray['updated_at']))
        {
            $givenArray['updated_at'] = Common::getTimeZoneDateFormat($givenArray['updated_at']);
        }
        if(isset($givenArray['status']) && strlen($givenArray['status']) <= 2)
        {
            $givenArray['status'] = __('common.status.'.$givenArray['status']);
        }
        if(isset($givenArray['product_type']) && strlen($givenArray['product_type']) <= 2)
        {
            $types = config('common.product_type');
            $searchProductTpe = config('common.search_product_type');
            $givenArray['product_type'] = isset($types[$givenArray['product_type']]) ? $types[$givenArray['product_type']] : (isset($searchProductTpe[$givenArray['product_type']]) ? $searchProductTpe[$givenArray['product_type']] : '-');
        }
        if(isset($givenArray['agency_own_content']))
        {
            if ($givenArray['agency_own_content'] == 0) {
                $givenArray['agency_own_content'] = 'No';
            }
            else
            {
                $givenArray['agency_own_content'] = 'Yes';
            }
        }
        if(isset($givenArray['use_content_from_other_agency']))
        {
            if ($givenArray['use_content_from_other_agency'] == 0) {
                $givenArray['use_content_from_other_agency'] = 'No';
            }
            else
            {
                $givenArray['use_content_from_other_agency'] = 'Yes';
            }
        }
        if(isset($givenArray['supply_content_to_other_agency']))
        {
            if ($givenArray['supply_content_to_other_agency'] == 0) {
                $givenArray['supply_content_to_other_agency'] = 'No';
            }
            else
            {
                $givenArray['supply_content_to_other_agency'] = 'Yes';
            }
        }
        if(isset($givenArray['allow_sub_agency']))
        {
            if ($givenArray['allow_sub_agency'] == 0) {
                $givenArray['allow_sub_agency'] = 'No';
            }
            else
            {
                $givenArray['allow_sub_agency'] = 'Yes';
            }
        }
        if(isset($givenArray['allow_ticket_plugin_api']))
        {
            if ($givenArray['allow_ticket_plugin_api'] == 0) {
                $givenArray['allow_ticket_plugin_api'] = 'No';
            }
            else
            {
                $givenArray['allow_ticket_plugin_api'] = 'Yes';
            }
        }
        if(isset($givenArray['allow_b2c_portal']))
        {
            if ($givenArray['allow_b2c_portal'] == 0) {
                $givenArray['allow_b2c_portal'] = 'No';
            }
            else
            {
                $givenArray['allow_b2c_portal'] = 'Yes';
            }
        }
        if(isset($givenArray['allow_mlm']))
        {
            if ($givenArray['allow_mlm'] == 0) {
                $givenArray['allow_mlm'] = 'No';
            }
            else
            {
                $givenArray['allow_mlm'] = 'Yes';
            }
        }
        if(isset($givenArray['allow_corporate_portal']))
        {
            if ($givenArray['allow_corporate_portal'] == 0) {
                $givenArray['allow_corporate_portal'] = 'No';
            }
            else
            {
                $givenArray['allow_corporate_portal'] = 'Yes';
            }
        }
        if(isset($givenArray['allow_b2b_api']))
        {
            if ($givenArray['allow_b2b_api'] == 0) {
                $givenArray['allow_b2b_api'] = 'No';
            }
            else
            {
                $givenArray['allow_b2b_api'] = 'Yes';
            }
        }
        if(isset($givenArray['allow_b2c_api']))
        {
            if ($givenArray['allow_b2c_api'] == 0) {
                $givenArray['allow_b2c_api'] = 'No';
            }
            else
            {
                $givenArray['allow_b2c_api'] = 'Yes';
            }
        }
        if(isset($givenArray['allow_b2c_meta_api']))
        {
            if ($givenArray['allow_b2c_meta_api'] == 0) {
                $givenArray['allow_b2c_meta_api'] = 'No';
            }
            else
            {
                $givenArray['allow_b2c_meta_api'] = 'Yes';
            }
        }
        if(isset($givenArray['allow_corporate_api']))
        {
            if ($givenArray['allow_corporate_api'] == 0) {
                $givenArray['allow_corporate_api'] = 'No';
            }
            else
            {
                $givenArray['allow_corporate_api'] = 'Yes';
            }
        }
        if(isset($givenArray['allow_hold_booking']))
        {
            if ($givenArray['allow_hold_booking'] == 0) {
                $givenArray['allow_hold_booking'] = 'No';
            }
            else
            {
                $givenArray['allow_hold_booking'] = 'Yes';
            }
        }
        if(isset($givenArray['display_recommend_fare']))
        {
            if ($givenArray['display_recommend_fare'] == 0) {
                $givenArray['display_recommend_fare'] = 'No';
            }
            else
            {
                $givenArray['display_recommend_fare'] = 'Yes';
            }
        }

        if(isset($givenArray['up_sale']))
        {
            if ($givenArray['up_sale'] == 0) {
                $givenArray['up_sale'] = 'No';
            }
            else
            {
                $givenArray['up_sale'] = 'Yes';
            }
        }

        if(isset($givenArray['payment_mode']))
        {
            $tempArray = [];
            $returnData = 'Not Set';
            if(!is_array($givenArray['payment_mode']))
            {
                $tempArray = explode(',', $givenArray['payment_mode']);
            }
            else
            {
                $tempArray = $givenArray['payment_mode'];
            }
            foreach ($tempArray as $key => $value) {
                if(strlen($value) >= 2)
                {
                    if($returnData == 'Not Set')
                        $returnData = $value;
                    else
                        $returnData .= ' , '.$value;
                }
            }
            $givenArray['payment_mode'] = $returnData;
        }
        
        if(isset($givenArray['operating_country']) && $givenArray['operating_country'] != '')
            $givenArray['operating_country'] = __('country.'.$givenArray['operating_country']);
        if(isset($givenArray['mailing_state']) && $givenArray['mailing_state'] != '')
            $givenArray['mailing_state'] = StateDetails::where('state_id',$givenArray['mailing_state'])->value('name');
        if(isset($givenArray['portal_aggregation_mapping']))
        {
            $returnData = [];
            $tempArray = json_decode($givenArray['portal_aggregation_mapping'],true);
            foreach ($tempArray as $key => $value) {
                $returnData[$key]['account_name'] = isset($accountDetails[$value['account_id']]) ? $accountDetails[$value['account_id']] : 'Not Set';
                $returnData[$key]['portal_name'] = isset($accountDetails[$value['portal_id']]) ? $accountDetails[$value['portal_id']] : 'Not Set';
                $returnData[$key]['profile_aggregation'] = ProfileAggregation::where('profile_aggregation_id',$value['profile_aggregation_id'])->value('profile_name');
            }
            $givenArray['portal_aggregation_mapping'] = json_encode($returnData);
        }
        if(isset($givenArray['account_aggregation_mapping']))
        {
            $returnData = [];
            $tempArray = json_decode($givenArray['account_aggregation_mapping'],true);
            foreach ($tempArray as $key => $value) {
                $returnData[$key]['supplier_account_name'] = isset($accountDetails[$value['supplier_account_id']]) ? $accountDetails[$value['supplier_account_id']] : 'Not Set';
                $returnData[$key]['partner_name'] = isset($accountDetails[$value['partner_account_id']]) ? $accountDetails[$value['partner_account_id']] : 'Not Set';
                $returnData[$key]['profile_aggregation'] = ProfileAggregation::where('profile_aggregation_id',$value['profile_aggregation_id'])->value('profile_name');
            }
            $givenArray['account_aggregation_mapping'] = json_encode($returnData);
        }
        if(isset($givenArray['supplier_template_id']) && $givenArray['supplier_template_id'] != '')
        {
            $supplierTemplateDetails = DB::table(config('tables.supplier_lowfare_template'))->whereIn('status',['A','IA'])->pluck('template_name','lowfare_template_id')->toArray();
            $supplierTemplateNames = '';
            if($givenArray['supplier_template_id'] == 0)
            {
                $supplierTemplateNames = 'ALL';
            }
            elseif(is_numeric($givenArray['supplier_template_id'])){
                $supplierTemplateNames = isset($supplierTemplateDetails[$givenArray['supplier_template_id']]) ? $supplierTemplateDetails[$givenArray['supplier_template_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['supplier_template_id']))
                {
                    $tempAccountIDs = $givenArray['supplier_template_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['supplier_template_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($supplierTemplateDetails[$givenArray['supplier_template_id']]) ? $supplierTemplateDetails[$givenArray['supplier_template_id']] : 'Not Set';
                        if($supplierTemplateNames == '')
                        {
                            $supplierTemplateNames = $tempAccountName;
                        }
                        else{
                            $supplierTemplateNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['supplier_template_id']);
            $givenArray['supplier_template_name'] = $supplierTemplateNames;
        }
        if(isset($givenArray['qc_template_id']) && $givenArray['qc_template_id'] != '')
        {
            $qcTemplateDetails = DB::table(config('tables.quality_check_template'))->whereIn('status',['A','IA'])->pluck('template_name','qc_template_id')->toArray();
            $qcTemplateNames = '';
            if($givenArray['qc_template_id'] == 0)
            {
                $qcTemplateNames = 'ALL';
            }
            elseif(is_numeric($givenArray['qc_template_id'])){
                $qcTemplateNames = isset($qcTemplateDetails[$givenArray['qc_template_id']]) ? $qcTemplateDetails[$givenArray['qc_template_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['qc_template_id']))
                {
                    $tempAccountIDs = $givenArray['qc_template_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['qc_template_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($qcTemplateDetails[$givenArray['qc_template_id']]) ? $qcTemplateDetails[$givenArray['qc_template_id']] : 'Not Set';
                        if($qcTemplateNames == '')
                        {
                            $qcTemplateNames = $tempAccountName;
                        }
                        else{
                            $qcTemplateNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['qc_template_id']);
            $givenArray['qc_template_name'] = $qcTemplateNames;
        }
        if(isset($givenArray['risk_analaysis_template_id']) && $givenArray['risk_analaysis_template_id'] != '')
        {
            $riskTemplateDetails = DB::table(config('tables.risk_analysis_template'))->whereIn('status',['A','IA'])->pluck('template_name','risk_template_id')->toArray();
            $riskAnalaysisTemplateNames = '';
            if($givenArray['risk_analaysis_template_id'] == 0)
            {
                $riskAnalaysisTemplateNames = 'ALL';
            }
            elseif(is_numeric($givenArray['risk_analaysis_template_id'])){
                $riskAnalaysisTemplateNames = isset($riskTemplateDetails[$givenArray['risk_analaysis_template_id']]) ? $riskTemplateDetails[$givenArray['risk_analaysis_template_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['risk_analaysis_template_id']))
                {
                    $tempAccountIDs = $givenArray['risk_analaysis_template_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['risk_analaysis_template_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($riskTemplateDetails[$givenArray['risk_analaysis_template_id']]) ? $riskTemplateDetails[$givenArray['risk_analaysis_template_id']] : 'Not Set';
                        if($riskAnalaysisTemplateNames == '')
                        {
                            $riskAnalaysisTemplateNames = $tempAccountName;
                        }
                        else{
                            $riskAnalaysisTemplateNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['risk_analaysis_template_id']);
            $givenArray['risk_analaysis_template_name'] = $riskAnalaysisTemplateNames;
        }
		return json_encode($givenArray);
	}

    public static function getCommonHistoryUpdation($givenArray,$modelName)
    {
        $accountDetails = AccountDetails::whereIn('status',['A','IA'])->pluck('account_name','account_id')->toArray();
        $userDetails = UserDetails::select(DB::raw("CONCAT(first_name,' ',last_name) as user_name"),'user_id')->whereIn('status',['A','IA'])->pluck('user_name','user_id')->toArray();
        $portalDetails = PortalDetails::whereIn('status',['A','IA'])->pluck('portal_name','portal_id')->toArray();
        if($modelName == config('tables.booking_fee_templates'))
        {
            if(isset($givenArray['fee_details']) && $givenArray['fee_details'] != '') 
            {
                $givenArray['booking_fee_details'] = $givenArray['fee_details'];
                unset($givenArray['fee_details']);
            }
        }
        if(isset($givenArray['account_id']) && $givenArray['account_id'] != '')
        {
            $accountNames = '';
            if($givenArray['account_id'] == 0)
            {
                $accountNames = 'ALL';
            }
            elseif(is_numeric($givenArray['account_id'])){
                $accountNames = isset($accountDetails[$givenArray['account_id']]) ? $accountDetails[$givenArray['account_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['account_id']))
                {
                    $tempAccountIDs = $givenArray['account_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['account_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($accountDetails[$givenArray['account_id']]) ? $accountDetails[$givenArray['account_id']] : 'Not Set';
                        if($accountNames == '')
                        {
                            $accountNames = $tempAccountName;
                        }
                        else{
                            $accountNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['account_id']);
            $givenArray['account_name'] = $accountNames;
        }
        if(isset($givenArray['portal_id']) && $givenArray['portal_id'] != '')
        {
            $portaltNames = '';
            if($givenArray['portal_id'] == 0)
            {
                $portaltNames = 'ALL';
            }
            elseif(is_numeric($givenArray['portal_id'])){
                $portaltNames = isset($portalDetails[$givenArray['portal_id']]) ? $portalDetails[$givenArray['portal_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['portal_id']))
                {
                    $tempAccountIDs = $givenArray['portal_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['portal_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($portalDetails[$givenArray['portal_id']]) ? $portalDetails[$givenArray['portal_id']] : 'Not Set';
                        if($portaltNames == '')
                        {
                            $portaltNames = $tempAccountName;
                        }
                        else{
                            $portaltNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['portal_id']);
            $givenArray['portal_name'] = $portaltNames;
        }
        if(isset($givenArray['parent_portal_id']) && $givenArray['parent_portal_id'] != '')
        {
            $portaltNames = '';
            if($givenArray['parent_portal_id'] == 0)
            {
                $portaltNames = 'ALL';
            }
            elseif(is_numeric($givenArray['parent_portal_id'])){
                $portaltNames = isset($portalDetails[$givenArray['parent_portal_id']]) ? $portalDetails[$givenArray['parent_portal_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['parent_portal_id']))
                {
                    $tempAccountIDs = $givenArray['parent_portal_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['parent_portal_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($portalDetails[$givenArray['parent_portal_id']]) ? $portalDetails[$givenArray['parent_portal_id']] : 'Not Set';
                        if($portaltNames == '')
                        {
                            $portaltNames = $tempAccountName;
                        }
                        else{
                            $portaltNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['parent_portal_id']);
            $givenArray['parent_portal_name'] = $portaltNames;
        }
        if(isset($givenArray['consumer_account_id']) && $givenArray['consumer_account_id'] != '')
        {
            $accountNames = '';
            if($givenArray['consumer_account_id'] == 0)
            {
                $accountNames = 'ALL';
            }
            elseif(is_numeric($givenArray['consumer_account_id'])){
                $accountNames = isset($accountDetails[$givenArray['consumer_account_id']]) ? $accountDetails[$givenArray['consumer_account_id']] : 'Not Set';;
            }
            else
            {
                if(is_array($givenArray['consumer_account_id']))
                {
                    $tempAccountIDs = $givenArray['consumer_account_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['consumer_account_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($accountDetails[$accountValue]) ? $accountDetails[$accountValue] : 'Not Set';
                        if($accountNames == '')
                        {
                            $accountNames = $tempAccountName;
                        }
                        else{
                            $accountNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['consumer_account_id']);
            $givenArray['consumer_account_name'] = $accountNames;
        }
        if(isset($givenArray['supplier_account_id']) && $givenArray['supplier_account_id'] != '')
        {
            $accountNames = '';
            if($givenArray['supplier_account_id'] == 0)
            {
                $accountNames = 'ALL';
            }
            elseif(is_numeric($givenArray['supplier_account_id'])){
                $accountNames = isset($accountDetails[$givenArray['supplier_account_id']]) ? $accountDetails[$givenArray['supplier_account_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['supplier_account_id']))
                {
                    $tempAccountIDs = $givenArray['supplier_account_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['supplier_account_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($accountDetails[$accountValue]) ? $accountDetails[$accountValue] : 'Not Set';
                        if($accountNames == '')
                        {
                            $accountNames = $tempAccountName;
                        }
                        else{
                            $accountNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['supplier_account_id']);
            $givenArray['consumer_account_name'] = $accountNames;
        }
        if(isset($givenArray['content_source_id']) && $givenArray['content_source_id'] != '')
        {
            $contentSource = ContentSourceDetails::select(DB::raw("CONCAT(gds_source,' ',gds_source_version,' ',pcc,' ',in_suffix,' (',default_currency,')') as content_source_name"),'content_source_id')->whereIn('status',['A','IA'])->pluck('content_source_name','content_source_id')->toArray();
            $accountNames = '';
            if($givenArray['content_source_id'] == 0)
            {
                $accountNames = 'ALL';
            }
            elseif(is_numeric($givenArray['content_source_id'])){
                $accountNames = isset($accountDetails[$givenArray['content_source_id']]) ? $accountDetails[$givenArray['content_source_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['content_source_id']))
                {
                    $tempAccountIDs = $givenArray['content_source_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['content_source_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($accountDetails[$accountValue]) ? $accountDetails[$accountValue] : 'Not Set';
                        if($accountNames == '')
                        {
                            $accountNames = $tempAccountName;
                        }
                        else{
                            $accountNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['content_source_id']);
            $givenArray['content_source_name'] = $accountNames;
        }
        if(isset($givenArray['payment_gateway_ids']) && isset($givenArray['payment_gateway_ids']) != '')
        {
            $gatewayNames = '';
            $tempExplode = [];
            if(!is_array($givenArray['payment_gateway_ids']))
            {
                $tempExplode = explode(',', $givenArray['payment_gateway_ids']);
            }
            else
            {
                $tempExplode = $givenArray['payment_gateway_ids'];
            }
            if(count($tempExplode) > 0)
            {
                foreach ($tempExplode as $key => $value) {
                    if(is_numeric($value)){
                        if($gatewayNames == '')
                        {
                            $gatewayNames = PaymentGatewayDetails::getPaymentGateWayName($value);
                        }
                        else{
                            $gatewayNames .= ' , '.PaymentGatewayDetails::getPaymentGateWayName($value);
                        }
                    }
                }
            }
            unset($givenArray['payment_gateway_ids']);
            $givenArray['payment_gateway_names'] = $gatewayNames ;
        }
        if(isset($givenArray['supplier_id']) && $givenArray['supplier_id'] != '')
        {
            $accountNames = '';
            if($givenArray['supplier_id'] == 0)
            {
                $accountNames = 'ALL';
            }
            elseif(is_numeric($givenArray['supplier_id'])){
                $accountNames = isset($accountDetails[$givenArray['supplier_id']]) ? $accountDetails[$givenArray['supplier_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['supplier_id']))
                {
                    $tempAccountIDs = $givenArray['supplier_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['supplier_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($accountDetails[$givenArray['supplier_id']]) ? $accountDetails[$givenArray['supplier_id']] : 'Not Set';
                        if($accountNames == '')
                        {
                            $accountNames = $tempAccountName;
                        }
                        else{
                            $accountNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['supplier_id']);
            $givenArray['supplier_name'] = $accountNames;
        }
        if(isset($givenArray['consumer_id']) && $givenArray['consumer_id'] != '')
        {
            $accountNames = '';
            if($givenArray['consumer_id'] == 0)
            {
                $accountNames = 'ALL';
            }
            elseif(is_numeric($givenArray['consumer_id'])){
                $accountNames = isset($accountDetails[$givenArray['consumer_id']]) ? $accountDetails[$givenArray['consumer_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['consumer_id']))
                {
                    $tempAccountIDs = $givenArray['consumer_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['consumer_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($accountDetails[$givenArray['consumer_id']]) ? $accountDetails[$givenArray['consumer_id']] : 'Not Set';
                        if($accountNames == '')
                        {
                            $accountNames = $tempAccountName;
                        }
                        else{
                            $accountNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['consumer_id']);
            $givenArray['consumer_name'] = $accountNames;
        }
        if(isset($givenArray['created_by']) && is_numeric($givenArray['created_by']))
        {
            $givenArray['created_by'] = isset($userDetails[$givenArray['created_by']]) ? $userDetails[$givenArray['created_by']] : 'Not Set';
        }
        if(isset($givenArray['updated_by']) && is_numeric($givenArray['updated_by']))
        {
            $givenArray['updated_by'] = isset($userDetails[$givenArray['updated_by']]) ? $userDetails[$givenArray['updated_by']] : 'Not Set';
        }
        if(isset($givenArray['created_at']) && is_numeric($givenArray['created_at']))
        {
            $givenArray['created_at'] = Common::getTimeZoneDateFormat($givenArray['created_at']);
        }
        if(isset($givenArray['updated_at']) && is_numeric($givenArray['updated_at']))
        {
            $givenArray['updated_at'] = Common::getTimeZoneDateFormat($givenArray['updated_at']);
        }
        if(isset($givenArray['status']) && strlen($givenArray['status']) <= 2)
        {
            $givenArray['status'] = __('common.status.'.$givenArray['status']);
        }
        if(isset($givenArray['product_type']) && strlen($givenArray['product_type']) <= 2)
        {
            $types = config('common.product_type');
            $searchProductTpe = config('common.search_product_type');
            $givenArray['product_type'] = isset($types[$givenArray['product_type']]) ? $types[$givenArray['product_type']] : (isset($searchProductTpe[$givenArray['product_type']]) ? $searchProductTpe[$givenArray['product_type']] : '-');
        }
        if(isset($givenArray['supplier_template_id']) && $givenArray['supplier_template_id'] != '')
        {
            $supplierTemplateDetails = DB::table(config('tables.supplier_lowfare_template'))->whereIn('status',['A','IA'])->pluck('template_name','lowfare_template_id')->toArray();
            $supplierTemplateNames = '';
            if($givenArray['supplier_template_id'] == 0)
            {
                $supplierTemplateNames = 'ALL';
            }
            elseif(is_numeric($givenArray['supplier_template_id'])){
                $supplierTemplateNames = isset($supplierTemplateDetails[$givenArray['supplier_template_id']]) ? $supplierTemplateDetails[$givenArray['supplier_template_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['supplier_template_id']))
                {
                    $tempAccountIDs = $givenArray['supplier_template_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['supplier_template_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($supplierTemplateDetails[$givenArray['supplier_template_id']]) ? $supplierTemplateDetails[$givenArray['supplier_template_id']] : 'Not Set';
                        if($supplierTemplateNames == '')
                        {
                            $supplierTemplateNames = $tempAccountName;
                        }
                        else{
                            $supplierTemplateNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['supplier_template_id']);
            $givenArray['supplier_template_name'] = $supplierTemplateNames;
        }
        if(isset($givenArray['qc_template_id']) && $givenArray['qc_template_id'] != '')
        {
            $qcTemplateDetails = DB::table(config('tables.quality_check_template'))->whereIn('status',['A','IA'])->pluck('template_name','qc_template_id')->toArray();
            $qcTemplateNames = '';
            if($givenArray['qc_template_id'] == 0)
            {
                $qcTemplateNames = 'ALL';
            }
            elseif(is_numeric($givenArray['qc_template_id'])){
                $qcTemplateNames = isset($qcTemplateDetails[$givenArray['qc_template_id']]) ? $qcTemplateDetails[$givenArray['qc_template_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['qc_template_id']))
                {
                    $tempAccountIDs = $givenArray['qc_template_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['qc_template_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($qcTemplateDetails[$givenArray['qc_template_id']]) ? $qcTemplateDetails[$givenArray['qc_template_id']] : 'Not Set';
                        if($qcTemplateNames == '')
                        {
                            $qcTemplateNames = $tempAccountName;
                        }
                        else{
                            $qcTemplateNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['qc_template_id']);
            $givenArray['qc_template_name'] = $qcTemplateNames;
        }
        if(isset($givenArray['risk_analaysis_template_id']) && $givenArray['risk_analaysis_template_id'] != '')
        {
            $riskTemplateDetails = DB::table(config('tables.risk_analysis_template'))->whereIn('status',['A','IA'])->pluck('template_name','risk_template_id')->toArray();
            $riskAnalaysisTemplateNames = '';
            if($givenArray['risk_analaysis_template_id'] == 0)
            {
                $riskAnalaysisTemplateNames = 'ALL';
            }
            elseif(is_numeric($givenArray['risk_analaysis_template_id'])){
                $riskAnalaysisTemplateNames = isset($riskTemplateDetails[$givenArray['risk_analaysis_template_id']]) ? $riskTemplateDetails[$givenArray['risk_analaysis_template_id']] : 'Not Set';
            }
            else
            {
                if(is_array($givenArray['risk_analaysis_template_id']))
                {
                    $tempAccountIDs = $givenArray['risk_analaysis_template_id'];
                }
                else
                {
                    $tempAccountIDs = explode(',', $givenArray['risk_analaysis_template_id']);
                }
                $tempAccountName = '';
                if(!empty($tempAccountIDs) && count($tempAccountIDs) > 0)
                {
                    foreach ($tempAccountIDs as $key => $accountValue) {
                        $tempAccountName = isset($riskTemplateDetails[$givenArray['risk_analaysis_template_id']]) ? $riskTemplateDetails[$givenArray['risk_analaysis_template_id']] : 'Not Set';
                        if($riskAnalaysisTemplateNames == '')
                        {
                            $riskAnalaysisTemplateNames = $tempAccountName;
                        }
                        else{
                            $riskAnalaysisTemplateNames .= ','.$tempAccountName;
                        }
                    }
                }                
            }
            unset($givenArray['risk_analaysis_template_id']);
            $givenArray['risk_analaysis_template_name'] = $riskAnalaysisTemplateNames;
        }
        if(isset($givenArray['config_data']))
        {
            $givenArray['config_data'] = unserialize($givenArray['config_data']);
        }
        if(isset($givenArray['selected_criterias']))
        {
            unset($givenArray['selected_criterias']);
        }
        if(isset($givenArray['actionFlag']))
        {
            unset($givenArray['actionFlag']);
        }
        return $givenArray;
    }
}