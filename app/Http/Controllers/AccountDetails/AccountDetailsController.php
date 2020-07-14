<?php
namespace App\Http\Controllers\AccountDetails;

use App\Models\ProfileAggregation\PortalAggregationMapping;
use App\Models\AccountDetails\AccountAggregationMapping;
use App\Models\ProfileAggregation\ProfileAggregation;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Validator;

class AccountDetailsController extends Controller
{
    public function getAccountDetails(){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'account_detail_retrieve_failed';
        $responseData['message']        = __('accountDetails.account_detail_retrieve_failed');
        $accountIds                             = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $accountDetails = AccountDetails::select('account_id','account_name')->whereIn('account_id',$accountIds)->where('status','A')->get()->toArray();

        if(count($accountDetails) > 0 ){
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'account_detail_retrieved_success';
            $responseData['message']        = __('accountDetails.account_detail_retrieved_success');
            $responseData['data']           = $accountDetails;
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }

        return response()->json($responseData);
       
    }

    public function portalAggregationView($id){

        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_aggregation_data_retrieve_failed';
        $responseData['message']        = __('accountDetails.portal_aggregation_data_retrieve_failed');
        $id = decryptData($id);
        $getPortalDetails   = PortalDetails::where('account_id', $id)->where('status', 'A')->get();
        $portalIds = [];
        if($getPortalDetails){
            foreach ($getPortalDetails as $pKey => $portalData) {
                $portalIds[] = $portalData;
            }
        }

        $portalAggregationViewData                                  = array();
        $portalAggregationViewData['account_aggregation_mapping']   = AccountAggregationMapping::getAccountAggregation($id);
        $portalAggregationViewData['account_portal_list']           = $portalIds;
        $portalAggregationViewData['portal_aggregation']            = PortalAggregationMapping::getPortalAggregation($id);
        $portalAggregationViewData['account_id']                    = $id;
        $portalAggregationViewData['account_name']                  = AccountDetails::getAccountName($id);
        $portalAggregationViewData['agency_details']                = AccountDetails::whereIn('status',['A','IA'])->where('account_id',$id)->first();

        $portalAggregationViewData['profile_aggregation_details']   =  ProfileAggregation::select('profile_aggregation_id', 'profile_name')
                                                                                            ->where('status','A')->where('account_id',$id)->get()->toArray();
        
        if(count($getPortalDetails) > 0){
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_aggregation_data_retrieved_success';
            $responseData['message']        = __('accountDetails.portal_aggregation_data_retrieved_success');
            $responseData['data']           = $portalAggregationViewData;
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function updatePortalAggregation(Request $request, $id){

        $input = $request->all();
        $rules  =   [
            'portal_aggregation_mapping'                =>  'required',
        ];

        $message    =   [
            'portal_aggregation_mapping.required'       =>  __('agency.parent_account_id_required')
        ];

        $validator = Validator::make($input, $rules, $message);
               
        if ($validator->fails()) {
            $responseData['message']             = 'The given data was invalid';
            $responseData['errors']              = $validator->errors();
            $responseData['status_code']         = config('common.common_status_code.success');
            $responseData['short_text']          = 'validation_error';
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }
        $id = decryptData($id);
        $agency   = AccountDetails::whereIn('status',['A','IA'])->where('account_id',$id)->first();
        if(!$agency)
        {
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['short_text']     = 'agency_not_found';
            $responseData['message']        = 'agency not found';
            return response()->json($responseData);
        }

        $output['status'] = 'success';
        $output['message'] = 'Portal Aggregation Mapped Successfully For - '.$agency['account_name'];
        $output['short_text'] = 'portal_aggregation_updated_successfully';
        $output['status_code'] = config('common.common_status_code.success');

        if(isset($input['portal_aggregation_mapping']) && !empty($input['portal_aggregation_mapping'])){
            $portalAggregation = $input['portal_aggregation_mapping'];
            $storeAccountAggregation  = PortalAggregationMapping::storePortalAggregation($id, $portalAggregation);

            if(empty($storeAccountAggregation)){
                $output['status'] = 'warning';
                $output['message'] = 'No Portal Aggregation Mapped For - '.$agency['account_name'];
                $output['short_text'] = 'agency_not_found';
                $output['status_code'] = config('common.common_status_code.failed');
            }
        }

        Common::ERunActionData($id, 'portalAggregationMapping');

        return response()->json($output);
    } 
  public function  getCurrecyDetails(Request $request)
    {
        $reqData = $request->all();
        $currecyDetails     =   AccountDetails::where('account_id',$reqData['id'])->value('agency_currency');
        
        if(isset($reqData['type']) && ($reqData['type']=='portal'))
        {
            $currecyDetails =   PortalDetails::where('portal_id',$reqData['id'])->value('portal_default_currency');
        }

        return $currecyDetails;
    }
}