<?php

namespace App\Http\Controllers\FormOfPayment;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libraries\Common;
use App\Models\FormOfPayment\FormOfPayment;
use App\Models\AccountDetails\AccountDetails;
use App\Models\ContentSource\ContentSourceDetails;
use App\Models\AccountDetails\PartnerMapping;
use App\Models\UserDetails\UserDetails;
use App\Models\Common\AirlinesInfo;
use Validator;
use DB;
use Auth;

class FormOfPaymentController extends Controller
{
    public function index(){
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'form_of_payment_data_retrieved_success';
        $responseData['message']        = __('formOfPayment.form_of_payment_data_retrieved_success');
        
        $status                         = config('common.status');
        $consumerAccount                = AccountDetails::getAccountDetails();
        
        $contentSourceData              = [];
        $contentSourceData              = ContentSourceDetails::select('content_source_id','gds_source','gds_source_version','pcc','in_suffix','default_currency')->where('status','<>','D')->get()->toArray();
        $airlinesInfo                   = AirlinesInfo::getAllAirlinesInfo();
        
        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }

        foreach($airlinesInfo as $aValue){
            $tempAirLineData                                = array();
            $tempAirLineData['airline_code']                = $aValue['airline_code'];
            $tempAirLineData['airline_name']                = $aValue['airline_name'];
            $responseData['data']['validating_airline'][] = $tempAirLineData ;
        }
        $responseData['data']['validating_airline']= array_merge([['airline_code'=>'ALL','airline_name'=>'ALL']],$responseData['data']['validating_airline']);

        foreach($contentSourceData as $cValue){
            $tempcontentData = [];
            $tempcontentData['content_source_id']       =  $cValue['content_source_id'];
            $tempcontentData['content_source_name']     =  $cValue['gds_source'].' '.$cValue['gds_source_version'].' '.$cValue['pcc'].' '.$cValue['in_suffix'].' '.$cValue['default_currency'];
            $responseData['data']['content_source'][]   = $tempcontentData;
        }
        $responseData['data']['content_source']         = array_merge([['content_source_id'=>'ALL','content_source_name'=>'ALL']],$responseData['data']['content_source']);
        
        foreach($consumerAccount as $key => $value){
            $tempData                   = array();
            $tempData['account_id']     = $key;
            $tempData['account_name']   = $value;
            $responseData['data']['account_details'][] = $tempData ;
        }
        $responseData['data']['account_details'] = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$responseData['data']['account_details']);
                
        return response()->json($responseData);
    }

    public function getList(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'form_of_payment_data_retrieve_failed';
        $responseData['message']        = __('formOfPayment.form_of_payment_data_retrieve_failed');
        
        $accountIds                     = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),1, true);
        $fopDetails                     = FormOfPayment::from(config('tables.form_of_payment').' As fp')->select('fp.*','ad.account_name','ca.account_name As consumer_account_name','cs.gds_source As content_source','cs.gds_source_version','cs.pcc','cs.in_suffix','cs.default_currency')->leftjoin(config('tables.account_details').' As ad','ad.account_id','fp.account_id')->leftjoin(config('tables.account_details').' As ca','ca.account_id','fp.consumer_account_id')->leftjoin(config('tables.content_source_details').' As cs','cs.content_source_id','fp.content_source_id')->whereIn('fp.account_id', $accountIds);
        $requestData                    = $request->all();

        //Filter
        if((isset($requestData['query']['content_source_id']) && $requestData['query']['content_source_id'] != ''  && $requestData['query']['content_source_id'] != 'ALL') || (isset($requestData['content_source_id']) && $requestData['content_source_id'] != '' && $requestData['content_source_id'] != 'ALL')){
            $requestData['content_source_id']   = (isset($requestData['query']['content_source_id']) && $requestData['query']['content_source_id'] != '') ? $requestData['query']['content_source_id'] : $requestData['content_source_id'];
            $fopDetails                         = $fopDetails->where('fp.content_source_id',$requestData['content_source_id']);
        }
        if((isset($requestData['query']['validating_airline']) && $requestData['query']['validating_airline'] != '' && $requestData['query']['validating_airline'] != 'ALL') || (isset($requestData['validating_airline']) && $requestData['validating_airline'] != '' && $requestData['validating_airline'] != 'ALL')){
            $requestData['validating_airline']  = (isset($requestData['query']['validating_airline']) && $requestData['query']['validating_airline'] != '') ?$requestData['query']['validating_airline']:$requestData['validating_airline'];
            $fopDetails                         = $fopDetails->where(function($query) use($requestData){
                                                        foreach ([$requestData['validating_airline']] as $key => $value) {
                                                            $query->orWhere(DB::raw("FIND_IN_SET('".$value."',validating_airline)"), '>' ,0);
                                                        }
                                                    });
        }
        if((isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '' &&  $requestData['query']['account_id'] != 'ALL') || (isset($requestData['account_id']) && $requestData['account_id'] != '' &&  $requestData['account_id'] != 'ALL')){
            $requestData['account_id'] = (isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '') ?$requestData['query']['account_id']:$requestData['account_id'];
            $fopDetails = $fopDetails->where('fp.account_id',$requestData['account_id']);
        }
        if((isset($requestData['query']['consumer_account_id']) && $requestData['query']['consumer_account_id'] != '' &&  $requestData['query']['consumer_account_id'] != 'ALL') || (isset($requestData['consumer_account_id']) && $requestData['consumer_account_id'] != '' &&  $requestData['consumer_account_id'] != 'ALL')){
            $requestData['consumer_account_id'] = (isset($requestData['query']['consumer_account_id']) && $requestData['query']['consumer_account_id'] != '') ?$requestData['query']['consumer_account_id']:$requestData['consumer_account_id'];
            $fopDetails = $fopDetails->where(function($query) use($requestData){
                                foreach ([$requestData['consumer_account_id']] as $key => $value) {
                                    $query->orWhere(DB::raw("FIND_IN_SET('".$value."',consumer_account_id)"), '>' ,0);
                                }
                            });
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL') || (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL')){
            $requestData['status'] = (isset($requestData['query']['status']) && $requestData['query']['status'] != '') ?$requestData['query']['status']:$requestData['status'];
            $fopDetails = $fopDetails->where('fp.status',$requestData['status']);
        }else{
            $fopDetails = $fopDetails->where('fp.status','<>','D');
        }

        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $fopDetails = $fopDetails->orderBy($requestData['orderBy'],$sorting);
        }else{
            $fopDetails = $fopDetails->orderBy('fp.updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit'])- $requestData['limit'];                  
        //record count
        $fopDetailsCount  = $fopDetails->take($requestData['limit'])->count();
        // Get Record
        $fopDetails       = $fopDetails->offset($start)->limit($requestData['limit'])->get();
        if(count($fopDetails) > 0){
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'form_of_payment_data_retrieved_success';
            $responseData['message']            = __('formOfPayment.form_of_payment_data_retrieved_success');    
            $responseData['data']['records_total']      = $fopDetailsCount;
            $responseData['data']['records_filtered']   = $fopDetailsCount;

            foreach($fopDetails as $value){
                $fopData                        = array();
                $fopData['si_no']               = ++$start;
                $fopData['id']                  = encryptData($value['fop_id']);
                $fopData['fop_id']              = encryptData($value['fop_id']);
                $fopData['account_id']          = $value['account_id'];
                $fopData['account_name']        = $value['account_name'];
                $fopData['content_source']      = $value['content_source'].' '.$value['gds_source_version'].' '.$value['pcc'].' '.$value['in_suffix'].' '.$value['default_currency'];
                $fopData['validating_airline']  = $value['validating_airline'];
                $fopData['consumer_account_id'] = $value['consumer_account_id'];
                
                if($fopData['consumer_account_id'] != 0){
                    $consumerAccountId              = explode(',',$fopData['consumer_account_id']);
                    $consumerAccountName            = array();
        
                    foreach($consumerAccountId as $cvalue){
                        $consumerAccountName[] = AccountDetails::getAccountName($cvalue);
                    }
    
                    $fopData['consumer_account_name']   = implode(',',$consumerAccountName);
                }else{
                    $fopData['consumer_account_name']   = 'ALL';
                }
                $fopData['status']                  = $value['status'];
                $responseData['data']['records'][]             = $fopData;
            }
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function create(){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'form_of_payment_data_retrieve_failed';
        $responseData['message']            = __('formOfPayment.form_of_payment_data_retrieve_failed');
        $accountDetails                     = AccountDetails::getAccountDetails();
 
        if(count($accountDetails) > 0 ){
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'form_of_payment_data_retrieved_success';
            $responseData['message']            = __('formOfPayment.form_of_payment_data_retrieved_success');    
        
            foreach($accountDetails as $key => $value){
                $tempData                   = array();
                $tempData['account_id']     = $key;
                $tempData['account_name']   = $value;
                $responseData['data']['partner_account'][] = $tempData ;
            }

            $responseData['data']['customer_account']       = [__('common.all')];
            $responseData['data']['validating_airline']     = [__('common.all')];
            $responseData['data']['form_of_payment_types']  = config('common.form_of_payment_types');
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function store(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'form_of_payment_data_store_failed';
        $responseData['message']            = __('formOfPayment.form_of_payment_data_store_failed');
        $requestData                        = $request->all();
        $requestData                        = $requestData['form_of_payment'];

        $storeFormOfPayment                 = self::storeFormOfPayment($requestData,'store');
        
        if($storeFormOfPayment['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $storeFormOfPayment['status_code'];
            $responseData['errors']         = $storeFormOfPayment['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'form_of_payment_data_stored_success';
            $responseData['message']        = __('formOfPayment.form_of_payment_data_stored_success');
            $fopId                          = $storeFormOfPayment['fop_id'];
        }     
        return response()->json($responseData);
    }

    public function edit($fopId){
        $responseData                           = array();
        $responseData['status']                 = 'failed';
        $responseData['status_code']            = config('common.common_status_code.failed');
        $responseData['short_text']             = 'form_of_payment_data_retrieve_failed';
        $responseData['message']                = __('formOfPayment.form_of_payment_data_retrieve_failed');
        $fopId                                  = decryptData($fopId);
        $fopData                                = FormOfPayment::where('fop_id', $fopId)->where('status','<>','D')->first();
        
        if($fopData != null){
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'form_of_payment_data_retrieved_success';
            $responseData['message']            = __('formOfPayment.form_of_payment_data_retrieved_success');   
            
            $fopData                            = $fopData->toArray();
            $accountId                          = Auth::user()->account_id;
            $selectedContentSource              = ContentSourceDetails::select('account_id','content_source_id', 'gds_product','in_suffix', 'gds_source','gds_source_version', 'default_currency','pcc')->where('content_source_id', $fopData['content_source_id'])->where('status', 'A')->first();
            $contentSource                      = ContentSourceDetails::select('content_source_id', 'account_id','in_suffix','gds_product', 'gds_source','gds_source_version', 'default_currency','pcc')->where('account_id', $fopData['account_id'])->where('status', 'A')->get()->toArray();       

            $consumersDetails                   = [];
            $consumersInfo                      = PartnerMapping::consumerList($fopData['account_id']);
           
            if(count($consumersInfo) > 0){
                foreach ($consumersInfo as $key => $value) {
                    $consumersDetails[$value->account_id] = $value->account_name;
                }
            }

            $airlineInfo                                                = AirlinesInfo::getAirlinInfoUsingContent($fopData['content_source_id']);
            $accountData                                                = AccountDetails::getAccountDetails(config('common.partner_account_type_id'), 1);
            
            $fopData['updated_by']                            =   UserDetails::getUserName($fopData['updated_by'],'yes');
            $fopData['created_by']                            =   UserDetails::getUserName($fopData['created_by'],'yes');
            $responseData['data']                             = $fopData;
            $responseData['data']['fob_id']                   = $fopId;
            $responseData['data']['encrypt_fob_id']           = encryptData($fopId);
            $responseData['data']['validating_airline']       = explode(',',$fopData['validating_airline']);
            $responseData['data']['consumer_account_id']      = explode(',',$fopData['consumer_account_id']);
            $responseData['data']['login_account_id']         = $accountId;
            $responseData['data']['login_account_name']       = AccountDetails::getAccountName($accountId);
            $responseData['data']['consumers_details']        = $consumersDetails;
            $responseData['data']['content_source']           = $contentSource;
            $responseData['data']['selected_content_source']  = $selectedContentSource;
            foreach($airlineInfo as $key => $value){
                $tempData                   = array();
                $tempData['airline_code']     = $key;
                $tempData['airline_name']   = $value;
                $responseData['data']['airline_info'][] = $tempData ;
            }
            foreach($accountData as $key => $value){
                $tempData                   = array();
                $tempData['account_id']     = $key;
                $tempData['account_name']   = $value;
                $responseData['data']['account_details'][] = $tempData ;
            }
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function update(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'form_of_payment_data_update_failed';
        $responseData['message']            = __('formOfPayment.form_of_payment_data_update_failed');
        $requestData                        = $request->all();
        $requestData                        = $requestData['form_of_payment'];

        $storeFormOfPayment                 = self::storeFormOfPayment($requestData,'update');
        
        if($storeFormOfPayment['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $storeFormOfPayment['status_code'];
            $responseData['errors']         = $storeFormOfPayment['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'form_of_payment_data_updated_success';
            $responseData['message']        = __('formOfPayment.form_of_payment_data_updated_success');
        }     

        return response()->json($responseData);
    }

    public function delete(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'form_of_payment_data_delete_failed';
        $responseData['message']        = __('formOfPayment.form_of_payment_data_delete_failed');
        $requestData                    = $request->all();
        $deleteStatus                   = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'form_of_payment_data_deleted_success';
            $responseData['message']        = __('formOfPayment.form_of_payment_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'form_of_payment_change_status_failed';
        $responseData['message']        = __('formOfPayment.form_of_payment_change_status_failed');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'form_of_payment_change_status_success';
            $responseData['message']        = __('formOfPayment.form_of_payment_change_status_success');
        }
        return response()->json($responseData);
    }

    public function statusUpadateData( $requestData){
        $requestData                    = $requestData['form_of_payment'];

        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');

        $status                         = 'D';
        $rules                          =   [
                                                'flag'       =>  'required',
                                                'fop_id'     =>  'required'
                                            ];
        $message                        =   [
                                                'flag.required'         =>  __('common.flag_required'),
                                                'fop_id.required'    =>  __('formOfPayment.fop_id_required')
                                            ];
        
        $validator = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
        }else{
            $id                                     = isset($requestData['fop_id'])?decryptData($requestData['fop_id']):'';
            if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                $responseData['status_code']        = config('common.common_status_code.not_found');
                $responseData['erorrs']             =  ['error' => __('common.the_given_data_was_not_found')];
            }else{
                if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus')
                    $status                         = $requestData['status'];

                $updateData                     = array();
                $updateData['status']           = $status;
                $updateData['updated_at']       = Common::getDate();
                $updateData['updated_by']       = Common::getUserID();

        
                $fopDeatils                   = FormOfPayment::where('fop_id',$id);
                $changeStatus                 = $fopDeatils->update($updateData);
                $fopDeatils                   = $fopDeatils->first();
                if($changeStatus){
                    $responseData             = $changeStatus;
                    Common::ERunActionData($fopDeatils->account_id, 'updateFormOfPayment');
                }else{
                    $responseData['status_code']        = config('common.common_status_code.not_found');
                    $responseData['errors']         = ['error'=>__('common.recored_not_found')];
                }
            }
        }       
        return $responseData;
    }

    public function storeFormOfPayment($requestData,$action){
        $rules                      =   [
                                            'account_id'            =>  'required',
                                            'content_source_id'     =>  'required',
                                            'consumer_account_id'   =>  'required',
                                        ];
        if($action != 'store')
            $rules['fop_id']        = 'required';
        
        $contentSourceId            = isset($requestData['content_source_id'])?$requestData['content_source_id'] :'';
        
        if($contentSourceId != ''){
           $productType             = ContentSourceDetails::find($contentSourceId)->toArray()['gds_product'];
           if($productType == 'Flight'){
            $rules['validating_airline']    = 'required';
           }
        }

        $message                    =   [
                                            'fop_id.required'                   =>  __('formOfPayment.fop_id_required'),
                                            'account_id.required'               =>  __('common.account_id_required'),
                                            'content_source_id.required'        =>  __('formOfPayment.content_source_required'),
                                            'consumer_account_id.required'      =>  __('formOfPayment.customer_account_id_required'),
                                            'validating_airline.required'       =>  __('formOfPayment.validating_airline_required'),
                                        ];

        $validator                      = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
        }else{
            $requestData['validating_airline'] = isset($requestData['validating_airline'])?$requestData['validating_airline']:["ALL"];
            $validation = FormOfPayment::where('content_source_id',$requestData['content_source_id'])
                                        ->where(function($query) use($requestData){
                                            foreach ($requestData['consumer_account_id'] as $key => $value) {
                                                $query->orWhere(DB::raw("FIND_IN_SET('".$value."',consumer_account_id)"), '>' ,0);
                                            }
                                        })
                                        ->where(function($query)use($requestData){
                                            foreach ($requestData['validating_airline'] as $key => $value) {
                                                $query->orWhere(DB::raw("FIND_IN_SET('".$value."',validating_airline)"), '>' ,0);
                                            }
    
                                        })
                                    ->whereNotin('status',['D']);

            if($action != 'store'){
                $validation = $validation->where('fop_id', '<>',decryptData($requestData['fop_id']));
            }
            $validation = $validation->first();

            if($validation == null ){
                $id                                 = isset($requestData['fop_id'])?decryptData($requestData['fop_id']) :'';

                if($action == 'store')
                    $formOfPayment                      = new FormOfPayment();
                else
                    $formOfPayment                      = FormOfPayment::where('fop_id', $id)->first();
                
                if($formOfPayment != null){
                    //get old original data
                    $oldOriginalTemplate = '';
                    if($action != 'store'){
                        $oldOriginalTemplate            = $formOfPayment->getOriginal();
                    }

                    $fopDetails                                       = isset($requestData['fop_details'])?$requestData['fop_details']:[];
                    // $fopDetails                                       = Common::validStoreFopFormatData($fopDetails);
                    if($fopDetails){  
                        
                        $requestData['fop_details']         = json_encode($fopDetails);
                        
                        $formOfPayment->account_id          = $requestData['account_id'];
                        $formOfPayment->content_source_id   = $requestData['content_source_id'];
                        $formOfPayment->validating_airline  = implode(',', $requestData['validating_airline']);
                        $formOfPayment->consumer_account_id = implode(',', $requestData['consumer_account_id']);
                        $formOfPayment->fop_details         = $requestData['fop_details'];
                        $formOfPayment->status              = $requestData['status'];
                
                        if($action == 'store'){
                            $formOfPayment->created_at      = Common::getDate();
                            $formOfPayment->created_by      = Common::getUserID();    
                        }
                        $formOfPayment->updated_at          = Common::getDate();
                        $formOfPayment->updated_by          = Common::getUserID();  
                        $stored                             = $formOfPayment->save();
                        if($stored){
                            $responseData        = $formOfPayment->fop_id;
                            //History
                            $newOriginalTemplate = FormOfPayment::find($responseData)->getOriginal();
                            
                            if($action == 'store' ){
                                Common::prepareArrayForLog($responseData,'Form Of Payment Template',(object)$newOriginalTemplate,config('tables.form_of_payment'),'form_of_payment_management');
                            }else{
                                $checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);
                                if(count($checkDiffArray) > 1){
                                    Common::prepareArrayForLog($responseData,'Form Of Payment Template',(object)$newOriginalTemplate,config('tables.form_of_payment'),'form_of_payment_management');
                                } 
                            }
                            //Redis
                            Common::ERunActionData($formOfPayment->account_id, 'updateFormOfPayment');
                        }else{
                            $responseData['status_code']    = config('common.common_status_code.validation_error');
                            $responseData['errors'] 	    = ['error'=>__('common.problem_of_store_data_in_DB')];
                        }
                    }else{
                        $responseData['status_code']        = config('common.common_status_code.validation_error');
                        $responseData['errors'] 	        = ['error'=>__('paymentGatewayConfig.key_mismatch_in_fop')];
                    }
                }else{
                    $responseData['status_code']        = config('common.common_status_code.validation_error');
                    $responseData['errors']             = ['error' => __('common.recored_not_found')];
                }
            }else{
                $responseData['status_code']        = config('common.common_status_code.validation_error');
                $responseData['errors'] 	        = ['error'=>__('formOfPayment.payment_input_already_given')];
            }
        }
        return $responseData;
    }

    public function getHistory($id){
        $id                                 = decryptData($id);
        $requestData['model_primary_id']    = $id;
        $requestData['model_name']          = config('tables.form_of_payment');
        $requestData['activity_flag']       = 'form_of_payment_management';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.form_of_payment');
            $requestData['activity_flag']    = 'form_of_payment_management';
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