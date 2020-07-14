<?php  

namespace App\Http\Controllers\LookToBookRatio;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccountDetails\PartnerMapping;
use App\Models\AccountDetails\AccountDetails;
use App\Models\Common\CurrencyDetails;
use App\Models\AgencyCreditManagement\AgencyCreditManagement;
use App\Models\LookToBookRatio\LookToBookRatio;
use App\Models\UserDetails\UserDetails;
use App\Libraries\Common;
use Auth;
use Validator;

class LookToBookRatioController extends Controller
{
    public function index(){
        $responseData                       = [];
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'look_to_book_ratio_data_retrieved_success';
        $responseData['message']        = __('lookToBookRatio.look_to_book_ratio_data_retrieved_success');
        $consumerAccount                = AccountDetails::getAccountDetails();
        $status                         = config('common.status');
        $allowedRatioType               = config('common.allowed_ratio_type');

        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }

        foreach($consumerAccount as $key => $value){
            $tempData                   = array();
            $tempData['account_id']     = $key;
            $tempData['account_name']   = $value;
            $responseData['data']['account_details'][] = $tempData ;
        }
        $responseData['data']['account_details'] = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$responseData['data']['account_details']);
        foreach($allowedRatioType as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $value;
            $tempData['value']          = $key;
            $responseData['data']['allowed_ratio_type'][] = $tempData ;
        }
        return response()->json($responseData);
    }

    public function getList(Request $request){
        $responseData                       = [];
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'look_to_book_ratio_data_retrieve_failed';
        $responseData['message']            = __('lookToBookRatio.look_to_book_ratio_data_retrieve_failed');
        $accountIds = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $accountIds[] = 0;
        $lookToBookRatio                    = LookToBookRatio::from(config('tables.book_to_ratio').' As lb')->select('lb.*','ad.account_name As supplier_name','ac.account_name As consumer_name')->leftjoin(config('tables.account_details').' As ac','ac.account_id','lb.consumer_id')->leftjoin(config('tables.account_details').' As ad','ad.account_id','lb.supplier_id')->where('lb.supplier_id','!=',1)->whereIn('ad.account_id',$accountIds);
        $requestData                        = $request->all();
        // Filter
        if((isset($requestData['query']['supplier_id']) && $requestData['query']['supplier_id'] != ''&&  $requestData['query']['supplier_id'] != 'ALL') || (isset($requestData['supplier_id']) && $requestData['supplier_id'] != ''&&  $requestData['supplier_id'] != 'ALL')){
            $requestData['supplier_id']     = (isset($requestData['query']['supplier_id']) && $requestData['query']['supplier_id'] != '') ? $requestData['query']['supplier_id'] : $requestData['supplier_id'];
            $lookToBookRatio                = $lookToBookRatio->where('lb.supplier_id',$requestData['supplier_id']);
        }
        if((isset($requestData['query']['consumer_id']) && $requestData['query']['consumer_id'] != ''&&  $requestData['query']['consumer_id'] != 'ALL') || (isset($requestData['consumer_id']) && $requestData['consumer_id'] != ''&&  $requestData['consumer_id'] != 'ALL')){
            $requestData['consumer_id']     = (isset($requestData['query']['consumer_id']) && $requestData['query']['consumer_id'] != '') ? $requestData['query']['consumer_id'] : $requestData['consumer_id'];
            $lookToBookRatio                = $lookToBookRatio->where('lb.consumer_id',$requestData['consumer_id']);
        }
        if((isset($requestData['query']['book_ratio_allow']) && $requestData['query']['book_ratio_allow'] != ''&&  $requestData['query']['book_ratio_allow'] != 'ALL') || (isset($requestData['book_ratio_allow']) && $requestData['book_ratio_allow'] != ''&&  $requestData['book_ratio_allow'] != 'ALL')){
            $requestData['book_ratio_allow']= (isset($requestData['query']['book_ratio_allow']) && $requestData['query']['book_ratio_allow'] != '') ? $requestData['query']['book_ratio_allow'] : $requestData['book_ratio_allow'];
            $lookToBookRatio                = $lookToBookRatio->where('lb.book_ratio_allow',$requestData['book_ratio_allow']);
        }
        if((isset($requestData['query']['search_limit']) && $requestData['query']['search_limit'] != '') || (isset($requestData['search_limit']) && $requestData['search_limit'] != '')){
            $requestData['search_limit']    = (isset($requestData['query']['search_limit']) && $requestData['query']['search_limit'] != '') ? $requestData['query']['search_limit'] : $requestData['search_limit'];
            $lookToBookRatio                = $lookToBookRatio->where('lb.search_limit',$requestData['search_limit']);
        }
        if((isset($requestData['query']['available_search_count']) && $requestData['query']['available_search_count'] != '') || (isset($requestData['available_search_count']) && $requestData['available_search_count'] != '')){
            $requestData['available_search_count'] = (isset($requestData['query']['available_search_count']) && $requestData['query']['available_search_count'] != '') ? $requestData['query']['available_search_count'] : $requestData['available_search_count'];
            $lookToBookRatio                = $lookToBookRatio->where('lb.available_search_count',$requestData['available_search_count']);
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != ''&&  $requestData['query']['status'] != 'ALL') || (isset($requestData['status']) && $requestData['status'] != ''&&  $requestData['status'] != 'ALL')){
            $requestData['status']     = (isset($requestData['query']['status']) && $requestData['query']['status'] != '') ? $requestData['query']['status'] : $requestData['status'];
            $lookToBookRatio                = $lookToBookRatio->where('lb.status',$requestData['status']);
        }else{
            $lookToBookRatio = $lookToBookRatio->where('lb.status','<>','D');
        }
        
         //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $lookToBookRatio = $lookToBookRatio->orderBy($requestData['orderBy'],$sorting);
        }else{
            $lookToBookRatio = $lookToBookRatio->orderBy('updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit'])- $requestData['limit'];                  
        //record count
        $lookToBookRatioCount  = $lookToBookRatio->take($requestData['limit'])->count();
        // Get Record
        $lookToBookRatio       = $lookToBookRatio->offset($start)->limit($requestData['limit'])->get();

        if(count($lookToBookRatio) > 0){
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'look_to_book_ratio_data_retrieved_success';
            $responseData['message']            = __('lookToBookRatio.look_to_book_ratio_data_retrieved_success'); 
            $responseData['data']['records_total']       = $lookToBookRatioCount;
            $responseData['data']['records_filtered']    = $lookToBookRatioCount;
            foreach($lookToBookRatio as $value){
                $tempData                               = array();
                $tempData['si_no']                      = ++$start;
                $tempData['id']                         = encryptData($value['book_to_ratio_id']);
                $tempData['book_to_ratio_id']           = encryptData($value['book_to_ratio_id']);
                $tempData['supplier_id']                = $value['supplier_id'];
                $tempData['consumer_id']                = $value['consumer_id'];
                $tempData['supplier_name']              = $value['supplier_name'];
                $tempData['consumer_name']              = $value['consumer_name'];
                $tempData['book_ratio_allow']           = ($value['book_ratio_allow'] == 'Y' && $value['book_ratio_allow'] != '') ? 'YES':'NO';
                $tempData['search_limit']               = ($value['search_limit'] != '' && $value['search_limit'] != null) ? $value['search_limit']:0;
                $tempData['available_search_count']     = ($value['available_search_count'] != '' && $value['available_search_count'] != null) ? $value['available_search_count']:0;
                $tempData['currency']                   = ($value['currency'] != '' && $value['currency'] != null) ? $value['currency']:'Not Set';
                $tempData['charges']                    = ($value['charges'] != '' && $value['charges'] != null) ? $value['charges']:0;
                $tempData['exceed_search_count']        = ($value['exceed_search_count'] != '' && $value['exceed_search_count'] != null) ? $value['exceed_search_count']:0;
                $tempData['booking_count']              = ($value['booking_count'] != '' && $value['booking_count'] != null) ? $value['booking_count']:0;
                $tempData['total_searches']             = ($value['total_searches'] != '' && $value['total_searches'] != null) ? $value['total_searches']:0;
                $tempData['status']                     = ($value['status'] != '') ? $value['status']:'IA';
                $responseData['data']['records'][]      = $tempData;
            }
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }
        
        return response()->json($responseData);
    }

    public function create(){
        $responseData                       = [];
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'look_to_book_ratio_data_retrieved_success';
        $responseData['message']            = __('lookToBookRatio.look_to_book_ratio_data_retrieved_success');
       
        $accountId                          = Auth::user()->account_id;
        $consumersDetails                   = [];
        $accountDetails                     = [];
        $consumersInfo                      = PartnerMapping::consumerList($accountId);
        
        if(count($consumersInfo) > 0){
            foreach ($consumersInfo as $key => $value) {
               $tempData                    = [];
               $tempData['account_id']      = $value->account_id;
               $tempData['account_name']    = $value->account_name;
               $consumersDetails[]          = $tempData;
            }
        }

        $accountData                        = AccountDetails::getAccountDetails(config('common.partner_account_type_id'), 1);
        foreach($accountData as $key => $value){
            $tempData                       = array();
            $tempData['account_id']         = $key;
            $tempData['account_name']       = $value;
            $accountDetails[]               = $tempData ;
        }

        $responseData['data']['login_account_id']           = $accountId;
        $responseData['data']['login_account_name']         = AccountDetails::getAccountName($accountId);
        $responseData['data']['consumers_account_details']  = $consumersDetails;
        $responseData['data']['supplier_account_details']   = $accountDetails;
        
        return response()->json($responseData);
    }
    
    public function store(Request $request) {
        $responseData                       = [];
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'look_to_book_ratio_data_store_failed';
        $responseData['message']            = __('lookToBookRatio.look_to_book_ratio_data_store_failed');
        $requestData                        = $request->all();        
        $requestData                        = $requestData['book_to_ratio'];        
        
        $rules                              =   [
                                                    'supplier_id'   => 'required', 
                                                    'consumer_id'   =>'required',
                                                ];
        
        $requestData['allow_search_exceed'] = (isset($requestData['allow_search_exceed']) && $requestData['allow_search_exceed'] == 'yes' && isset($requestData['book_ratio_allow']) && $requestData['book_ratio_allow'] == 'Y')?'Y':'N';
        $requestData['book_ratio_allow']    = (isset($requestData['book_ratio_allow']) && $requestData['book_ratio_allow'] == 'Y')?'Y':'N';
       
        if($requestData['allow_search_exceed'] == 'Y' && $requestData['book_ratio_allow'] == 'Y'){
            $rules['search_limit']          = 'required';
            $rules['currency']              = 'required';
            $rules['charges']               = 'required';
            $rules['exceed_search_count']   = 'required';
        }
        else if($requestData['book_ratio_allow'] == 'Y' && $requestData['allow_search_exceed'] == 'N')
        {
            $rules['search_limit']          = 'required';
        }

        $message                            =   [
                                                    'supplier_id.required'               =>  __('lookToBookRatio.supplier_id_required'),
                                                    'consumer_id.required'      =>  __('lookToBookRatio.consumer_account_id_required'),
                                                    'search_limit.required'             =>  __('lookToBookRatio.search_limit_required'),
                                                    'currency.required'                 =>  __('lookToBookRatio.currency_required'),
                                                    'charges.required'                  =>  __('lookToBookRatio.charges_required'),
                                                    'exceed_search_count.required'      =>  __('lookToBookRatio.exceed_search_count_required'),
                                                ];

        $validator                                  = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
            return response()->json($responseData);   
        }

        $requestData['supplier_id']         = isset($requestData['supplier_id'])?$requestData['supplier_id']:0;
        $requestData['consumer_id']         = isset($requestData['consumer_id'])?$requestData['consumer_id']:0;
        
        $alreadyExsists     =   LookToBookRatio::where('supplier_id',$requestData['supplier_id'])->where('consumer_id',$requestData['consumer_id'])->where('status','<>','D')->first();
        
        if($alreadyExsists != null){
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = ['error'  =>  __('lookToBookRatio.already_exists_validation')];
            return response()->json($responseData);   
        }

        if(!isset($requestData['book_ratio_allow']) || $requestData['book_ratio_allow'] == 'N'){
            unset($requestData['search_limit']);            
            unset($requestData['exceed_search_count']);
            unset($requestData['charges']);
            unset($requestData['currency']);
        } else {
            $requestData['available_search_count'] = (isset($requestData['search_limit']) && $requestData['search_limit'] != null)?$requestData['search_limit']:0;
        }
        if(!isset($requestData['status'])){
            $requestData['status'] = 'IA';
        }

        $requestData['created_at'] = Common::getDate();
        $requestData['updated_at'] = Common::getDate();
        $requestData['created_by'] = Common::getUserID();
        $requestData['updated_by'] = Common::getUserID();    

        $model = new LookToBookRatio;                 
        $bookToRatio =  $model->create($requestData);  
        if($bookToRatio->book_to_ratio_id){
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'look_to_book_ratio_data_stored_success';
            $responseData['message']        = __('lookToBookRatio.look_to_book_ratio_data_stored_success'); 
            //Redis Update    
            LookToBookRatio::saveOrUpdateBooktoRatio($bookToRatio->book_to_ratio_id);
            //History
            $newOriginalTemplate = LookToBookRatio::find($bookToRatio->book_to_ratio_id)->getOriginal();
            Common::prepareArrayForLog($bookToRatio->book_to_ratio_id,'Book Ratio Template',(object)$newOriginalTemplate,config('tables.book_to_ratio'),'book_to_ratio_management');
        } else{
            $responseData['errors']            = ['error' =>  __('common.problem_of_store_data_in_DB')];
        }
       
        return response()->json($responseData);        
    }

    public function edit($id) {
        $responseData                   = [];
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'look_to_book_ratio_data_retrieve_failed';
        $responseData['message']        = __('lookToBookRatio.look_to_book_ratio_data_retrieve_failed');
        $id                             = isset($id)?decryptData($id):'';
        $lookTobookingRatio             = LookToBookRatio::where('book_to_ratio_id',$id)->where('status','<>','D')->first();

        if($lookTobookingRatio != null){
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'look_to_book_ratio_data_retrieved_success';
            $responseData['message']        = __('lookToBookRatio.look_to_book_ratio_data_retrieved_success'); 
      
            $accountId                      = Auth::user()->account_id;
            $consumersDetails               = [];     
            $accountDetails                 = [];   
            $consumersInfo                  = PartnerMapping::consumerList($lookTobookingRatio->supplier_id);
            $accountData                    = AccountDetails::getAccountDetails(config('common.partner_account_type_id'), 1);
            $currencyList                   = CurrencyDetails::getCurrencyDetails();
            
            if(count($consumersInfo) > 0){
                foreach ($consumersInfo as $key => $value) {
                $tempData                    = [];
                $tempData['account_id']      = $value->account_id;
                $tempData['account_name']    = $value->account_name;
                $consumersDetails[]          = $tempData;
                }
            }

            foreach($accountData as $key => $value){
                $tempData                   = array();
                $tempData['account_id']     = $key;
                $tempData['account_name']   = $value;
                $accountDetails[]           = $tempData ;
            }
            $lookTobookingRatio['updated_by']                   = UserDetails::getUserName($lookTobookingRatio['updated_by'],'yes');
            $lookTobookingRatio['created_by']                   = UserDetails::getUserName($lookTobookingRatio['created_by'],'yes');
            $responseData['data']                               = $lookTobookingRatio;
            $responseData['data']['encrypt_book_to_ratio_id']   = encryptData($lookTobookingRatio['book_to_ratio_id']);
            $responseData['data']['login_account_id']           = $accountId;
            $responseData['data']['login_account_name']         = AccountDetails::getAccountName($accountId);
            $responseData['data']['consumers_account_details']  = $consumersDetails;
            $responseData['data']['supplier_account_details']   = $accountDetails;
            $responseData['data']['currencyL_lst']              = $currencyList;            
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }
    
    public function update(Request $request){
        $responseData                       = [];
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'look_to_book_ratio_data_update_failed';
        $responseData['message']            = __('lookToBookRatio.look_to_book_ratio_data_update_failed');
        
        $requestData                        = $request->all();
        $requestData                        = $request->all();        
        $requestData                        = $requestData['book_to_ratio'];     
        $id                                 = isset($requestData['book_to_ratio_id'])?decryptData($requestData['book_to_ratio_id']):'';     
        $bookingRatio                       = LookToBookRatio::find($id); 
        
        if( $bookingRatio != null){

            $rules                              =   [
                'supplier_id'   => 'required', 
                'consumer_id'   =>'required',
            ];

            $requestData['allow_search_exceed'] = (isset($requestData['allow_search_exceed']) && $requestData['allow_search_exceed'] == 'yes' && isset($requestData['book_ratio_allow']) && $requestData['book_ratio_allow'] == 'Y')?'Y':'N';
            $requestData['book_ratio_allow']    = (isset($requestData['book_ratio_allow']) && $requestData['book_ratio_allow'] == 'Y')?'Y':'N';

            if($requestData['allow_search_exceed'] == 'Y' && $requestData['book_ratio_allow'] == 'Y'){
                $rules['search_limit']          = 'required';
                $rules['currency']              = 'required';
                $rules['charges']               = 'required';
                $rules['exceed_search_count']   = 'required';
            }
            else if($requestData['book_ratio_allow'] == 'Y' && $requestData['allow_search_exceed'] == 'N')
            {
                $rules['search_limit']          = 'required';
            }

            $message                            =   [
                                                        'supplier_id.required'              =>  __('lookToBookRatio.supplier_id_required'),
                                                        'consumer_id.required'              =>  __('lookToBookRatio.consumer_account_id_required'),
                                                        'search_limit.required'             =>  __('lookToBookRatio.search_limit_required'),
                                                        'currency.required'                 =>  __('lookToBookRatio.currency_required'),
                                                        'charges.required'                  =>  __('lookToBookRatio.charges_required'),
                                                        'exceed_search_count.required'      =>  __('lookToBookRatio.exceed_search_count_required'),
                                                    ];

            $validator                      = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
                return response()->json($responseData);   
            }

            if($requestData['search_limit'] < $bookingRatio->search_limit){
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = __('lookToBookRatio.search_limit_validation');
                return response()->json($responseData);   
            }

            //Old Route Blocking Template
            $oldOriginalTemplate = $bookingRatio->getOriginal();
            
            $requestData['supplier_id']         = isset($requestData['supplier_id'])?$requestData['supplier_id']:0;
            $requestData['consumer_id']         = isset($requestData['consumer_id'])?$requestData['consumer_id']:0;
            $alreadyExsists     =   LookToBookRatio::where('supplier_id',$requestData['supplier_id'])->where('consumer_id',$requestData['consumer_id'])->where('book_to_ratio_id','!=',$id)->where('status','<>','D')->first();
        
            if($alreadyExsists != null){
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = ['error'  =>  __('lookToBookRatio.already_exists_validation')];
                return response()->json($responseData);   
            }

            if(!isset($requestData['book_ratio_allow']) || $requestData['book_ratio_allow'] == 'N'){
                unset($requestData['search_limit']);            
                unset($requestData['exceed_search_count']);
                unset($requestData['charges']);
                unset($requestData['currency']);
            }
            
            if(isset($requestData['search_limit']) && $requestData['search_limit'] != $bookingRatio->search_limit){
                $searchLimit = $requestData['search_limit'] - $bookingRatio->search_limit;
                $avalilableLimit = $bookingRatio->available_search_count + $searchLimit;
                $requestData['available_search_count'] = $avalilableLimit;
            }

            if(!isset($requestData['status'])){
                $requestData['status'] = 'IA';
            }

            $requestData['updated_at'] = Common::getDate();
            $requestData['updated_by'] = Common::getUserID();       
                    
            $updated    = $bookingRatio->update($requestData);
            if($updated){
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['short_text']     = 'look_to_book_ratio_data_updated_success';
                $responseData['message']        = __('lookToBookRatio.look_to_book_ratio_data_updated_success'); 
            
                //History
                $newOriginalTemplate = LookToBookRatio::find($id)->getOriginal();
                $checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);
                if(count($checkDiffArray) > 1){
                    Common::prepareArrayForLog($id,'Book Ratio Template',(object)$newOriginalTemplate,config('tables.book_to_ratio'),'book_to_ratio_management');
                }        
                LookToBookRatio::saveOrUpdateBooktoRatio($id);
            }else{
                $responseData['errors']    = ['error' =>  __('common.problem_of_store_data_in_DB')];
            }
        }else{
            $responseData['errors']         = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function delete(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'look_to_book_ratio_data_delete_failed';
        $responseData['message']        = __('lookToBookRatio.look_to_book_ratio_data_delete_failed');
        $requestData                    = $request->all();
        $deleteStatus                   = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'look_to_book_ratio_data_deleted_success';
            $responseData['message']        = __('lookToBookRatio.look_to_book_ratio_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'look_to_book_ratio_change_status_failed';
        $responseData['message']        = __('lookToBookRatio.look_to_book_ratio_change_status_failed');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'look_to_book_ratio_change_status_success';
            $responseData['message']        = __('lookToBookRatio.look_to_book_ratio_change_status_success');
        }
        return response()->json($responseData);
    }

    public function statusUpadateData($requestData){

        $requestData                    = $requestData['book_to_ratio'];

        $status                         = 'D';
        $rules                          =   [
                                                'flag'                  =>  'required',
                                                'book_to_ratio_id'      =>  'required'
                                            ];
        $message                        =   [
                                                'flag.required'                 =>  __('common.flag_required'),
                                                'book_to_ratio_id.required'     =>  __('lookToBookRatio.book_to_ratio_id_required')
                                            ];
        
        $validator = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']        = config('common.common_status_code.validation_error');
            $responseData['errors'] 	        = $validator->errors();
        }else{
            $id                                 = decryptData($requestData['book_to_ratio_id']);

            if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                $responseData['status_code']    = config('common.common_status_code.validation_error');
                $responseData['erorrs']         =  ['error' => __('common.the_given_data_was_not_found')];
            }else{
                if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus')
                    $status                         = $requestData['status'];

                $updateData                     = array();
                $updateData['status']           = $status;
                $updateData['updated_at']       = Common::getDate();
                $updateData['updated_by']       = Common::getUserID();

                $bookingRatio =LookToBookRatio::where('book_to_ratio_id',$id)->first();
        
                $changeStatus                       = LookToBookRatio::where('book_to_ratio_id',$id)->update($updateData);
                if($changeStatus){
                    $responseData['status']         = 'success';
                    $responseData['status_code']    = config('common.common_status_code.success');
                    
                    $newOriginalTemplate = LookToBookRatio::find($id)->getOriginal();
                    Common::prepareArrayForLog($id,'Book Ratio Template',(object)$newOriginalTemplate,config('tables.book_to_ratio'),'book_to_ratio_management');
                    LookToBookRatio::saveOrUpdateBooktoRatio($bookingRatio->book_to_ratio_id);
                    Common::ERunActionData($bookingRatio['account_id'], 'updateBookingRatio');

                }else{
                $responseData['status_code']        = config('common.common_status_code.validation_error');
                $responseData['errors']             = ['error'=>__('common.recored_not_found')];
                }
            }
        }       
        return $responseData;
    }

    public function getSupplierConsumerCurrency(Request $request){
        $responseData               = [];
        $responseData['status']     = 'failed';
        $requestData                = $request->all();   
        $currency                   = ""; 
        if(isset($requestData['supplier_account_id']) && $requestData['supplier_account_id'] != '' && isset($requestData['consumer_account_id']) && $requestData['consumer_account_id'] != ''){

            $currency = AgencyCreditManagement::where('supplier_account_id',$requestData['supplier_account_id'])->where('account_id',$requestData['consumer_account_id'])->value('currency');

            // $currency = AccountDetails::where('account_id',$requestData['supplier_account_id'])->value('agency_currency');   
             
        }else{
            $responseData['errors'] = ['error'=>__('common.please_check_request_data')];
        }    

        if(!empty($currency)){
            $responseData['status']                      = 'success';
            $responseData['data']['consumer_currency']   = $currency;
        }else{
            $responseData['data']   = [];
        }
        return response()->json($responseData);
    }

    public function getHistory($id){
        $id                             = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.book_to_ratio');
        $inputArray['activity_flag']    = 'book_to_ratio_management';
        $responseData                   = Common::showHistory($inputArray);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $inputArray['id']               = $id;
            $inputArray['model_name']       = config('tables.book_to_ratio');
            $inputArray['activity_flag']    = 'book_to_ratio_management';
            $inputArray['count']            = isset($requestData['count']) ? $requestData['count']: 0;
            $responseData                   = Common::showDiffHistory($inputArray);
        }
        else{
            $responseData['status_code']    = config('common.common_status_code.failed');
            $responseData['status']         = 'failed';
            $responseData['message']        = 'get history difference failed';
            $responseData['errors']         = 'id required';
            $responseData['short_text']     = 'get_history_diff_error';
        }
        return response()->json($responseData);
    }

    public function getLookToBookRatioCount($consumerId){                
    
        $getLookBookRatio = LookToBookRatio::where('consumer_id', $consumerId)->where('status', 'A')->get();
        $supplierBookingRatio = [];
        if(!empty($getLookBookRatio)){            
            $getLookBookRatio = $getLookBookRatio->toArray();          
            foreach($getLookBookRatio as $bookingRatio){                
                $supplierBookingRatio[] = [
                    'supplier_id' => $bookingRatio['supplier_id'],
                    'supplier_name' => AccountDetails::getAccountName($bookingRatio['supplier_id']),
                    'available_search' => $bookingRatio['available_search_count']
                ];
            }            
        }  
        if(count($supplierBookingRatio) > 0){
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'look_to_book_ratio_search_count_success';
            $responseData['message']            = 'look to book ratio search count get success';
            $responseData['data']               = $supplierBookingRatio;
        }
        else {
            $responseData['status']             = 'failed';
            $responseData['status_code']        = config('common.common_status_code.failed');
            $responseData['short_text']         = 'look_to_book_ratio_search_count_failed';
            $responseData['message']            = 'look to book ratio search count get failed';
        }       
        
        return response()->json($responseData);
    }

}