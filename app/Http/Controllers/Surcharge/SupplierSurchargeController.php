<?php

namespace App\Http\Controllers\Surcharge;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Common\CurrencyDetails;
use App\Models\Surcharge\Supplier\SupplierSurcharge;
use App\Models\Surcharge\Supplier\SupplierSurchargeCriterias;
use App\Models\UserDetails\UserDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Libraries\Common;
use Validator;

class SupplierSurchargeController extends Controller
{
    public function index(Request $request)
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('supplierSurcharge.retrive_success');
        $surchargeType                          =   config('common.surcharge_type');
        $partnerInfo                            =   AccountDetails::getAccountDetails();
        $productType                            =   config('common.product_type');
        $status                                 =   config('common.status');
        $currencyDetails                        = CurrencyDetails::select('currency_code','display_code')->where('status','A')->orderBy('currency_code')->get()->toArray();
        foreach($status as $key => $value){
          $tempData                       = [];
          $tempData['label']              = $key;
          $tempData['value']              = $value;
          $responseData['data']['status'][] = $tempData;
        }
        foreach($surchargeType as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['data']['surcharge_type'][] = $tempData;
          }
          $responseData['data']['surcharge_type'] = array_merge([['label'=>'ALL','value'=>'ALL']],$responseData['data']['surcharge_type']);

          foreach($productType as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['data']['product_type'][] = $tempData;
        }
        $responseData['data']['product_type'] = array_merge([['label'=> 'ALL','value'=>'ALL']],$responseData['data']['product_type']);
          foreach($partnerInfo as $key => $value){
            $tempData                   = array();
            $tempData['account_id']     = $key;
            $tempData['account_name']   = $value;
            $responseData['data']['account_details'][]  = $tempData ;
        }
          $responseData['data']['currency_details']       = array_merge([['currency_code'=>'ALL','display_code'=>'ALL']],$currencyDetails);
          $responseData['data']['account_details']        = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$responseData['data']['account_details']);

        return response()->json($responseData);

    }
    public function list(Request $request)
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('supplierSurcharge.retrive_success');
        $accountIds                             = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $accountIds[]                           = 0;
        $surchargeData                          =   SupplierSurcharge::from(config('tables.supplier_surcharge_details').' As sd')->select('sd.*','ad.account_id','ad.account_name')->leftjoin(config('tables.account_details').' As ad','ad.account_id','sd.account_id')->where('sd.status','!=','D')->whereIn('ad.account_id',$accountIds);

            $reqData    =   $request->all();
            
            if(isset($reqData['surcharge_name']) && $reqData['surcharge_name'] != '' && $reqData['surcharge_name'] != 'ALL' || isset($reqData['query']['surcharge_name']) && $reqData['query']['surcharge_name'] != '' && $reqData['query']['surcharge_name'] != 'ALL')
            {
                $surchargeData  =   $surchargeData->where('sd.surcharge_name','like','%'.(!empty($reqData['surcharge_name']) ? $reqData['surcharge_name'] : $reqData['query']['surcharge_name']).'%');
            }
            if(isset($reqData['surcharge_code']) && $reqData['surcharge_code'] != '' && $reqData['surcharge_code'] != 'ALL' || isset($reqData['query']['surcharge_code']) && $reqData['query']['surcharge_code'] != '' && $reqData['query']['surcharge_code'] != 'ALL')
            {
                $surchargeData  =   $surchargeData->where('sd.surcharge_code','like','%'.(!empty($reqData['surcharge_code']) ? $reqData['surcharge_code'] : $reqData['query']['surcharge_code']).'%');
            }
            if(isset($reqData['surcharge_type']) && $reqData['surcharge_type'] != '' && $reqData['surcharge_type'] != 'ALL' || isset($reqData['query']['surcharge_type']) && $reqData['query']['surcharge_type'] != '' && $reqData['query']['surcharge_type'] != 'ALL')
            {
                $surchargeData  =   $surchargeData->where('sd.surcharge_type',!empty($reqData['surcharge_type']) ? $reqData['surcharge_type'] : $reqData['query']['surcharge_type']);
            }
            if(isset($reqData['product_type']) && $reqData['product_type'] != '' && $reqData['product_type'] != 'ALL' || isset($reqData['query']['product_type']) && $reqData['query']['product_type'] != '' && $reqData['query']['product_type'] != 'ALL')
            {
                $surchargeData  =   $surchargeData->where('sd.product_type',!empty($reqData['product_type']) ? $reqData['product_type'] : $reqData['query']['product_type']);
            }
            if(isset($reqData['currency_type']) && $reqData['currency_type'] != '' && $reqData['currency_type'] != 'ALL' || isset($reqData['query']['currency_type']) && $reqData['query']['currency_type'] != '' && $reqData['query']['currency_type'] != 'ALL')
            {
                $surchargeData  =   $surchargeData->where('sd.currency_type',!empty($reqData['currency_type']) ? $reqData['currency_type'] : $reqData['query']['currency_type']);
            }
            if(isset($reqData['account_id']) && $reqData['account_id'] != '' && $reqData['account_id'] != 'ALL' || isset($reqData['query']['account_id']) && $reqData['query']['account_id'] != '' && $reqData['query']['account_id'] != 'ALL')
            {
                $surchargeData  =   $surchargeData->where('ad.account_id',!empty($reqData['account_id']) ? $reqData['account_id'] : $reqData['query']['account_id']);
            }
            if(isset($reqData['status']) && $reqData['status'] != '' && $reqData['status'] != 'ALL' || isset($reqData['query']['status']) && $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL' )
            {
                $surchargeData  =   $surchargeData->where('sd.status',!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status'] );
            }

                if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
                    $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
                    $surchargeData  =   $surchargeData->orderBy($reqData['orderBy'],$sorting);
                }else{
                   $surchargeData    =$surchargeData->orderBy('surcharge_id','DESC');
                }
                $surchargeDataCount                      = $surchargeData->take($reqData['limit'])->count();
                if($surchargeDataCount > 0)
                {
                    $responseData['data']['records_total']      = $surchargeDataCount;
                    $responseData['data']['records_filtered']   = $surchargeDataCount;
                    $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
                    $count                                      = $start;
                    $surchargeData                              = $surchargeData->offset($start)->limit($reqData['limit'])->get();
                    $productType                                =   config('common.product_type');
                    $surchargeType                              =   config('common.surcharge_type');
                    foreach($surchargeData as $key => $listData)
                    {
                        $tempArray = array();
                        $tempArray['si_no']                  =   ++$count;
                        $tempArray['id']                     =   $listData['surcharge_id'];
                        $tempArray['surcharge_id']           =   encryptData($listData['surcharge_id']);
                        $tempArray['account_name']           =   $listData['account_name'];
                        $tempArray['product_type']           =   $productType[$listData['product_type']];
                        $tempArray['surcharge_name']         =   $listData['surcharge_name'];
                        $tempArray['surcharge_code']         =   $listData['surcharge_code'];
                        $tempArray['surcharge_type']         =   $surchargeType[$listData['surcharge_type']];
                        $tempArray['currency_type']          =   $listData['currency_type'];
                        $tempArray['status']                 =   $listData['status'];
                        $responseData['data']['records'][]   =   $tempArray;
                    }
                    $responseData['status'] 		         = 'success';
                }
                else
                {
                    $responseData['status_code']            =   config('common.common_status_code.failed');
                    $responseData['message']                =   __('supplierSurcharge.retrive_failed');
                    $responseData['errors']                 =   ["error" => __('common.recored_not_found')]; 
                    $responseData['status'] 		        =   'failed';

                }
       
        return response()->json($responseData);

    }

    public function create(Request $request)
    {
        $responseData                           =   array();
        $responseData['status_code'] 	        =   config('common.common_status_code.success');
        $responseData['message'] 		        =   __('supplierSurcharge.retrive_success');
        $calculationOn                          =   config('common.calculation_on');
        $optionalCriterias                      =   config('criterias.supplier_surcharge_criterias');
        $surchargeType                          =   config('common.surcharge_type');
        $partnerInfo                            =   AccountDetails::getAccountDetails();
        $productType                            =   config('common.product_type');
        $status                                 =   config('common.status');
       
        foreach($status as $key => $value){
          $tempData                       = [];
          $tempData['label']              = $key;
          $tempData['value']              = $value;
          $responseData['data']['status_info'][] = $tempData;
        }
        foreach($surchargeType as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['data']['surcharge_type'][] = $tempData;
          }
         
          foreach($calculationOn['flight'] as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['data']['calculation_on']['flight'][] = $tempData;
          }
          foreach($calculationOn['hotel'] as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['data']['calculation_on']['hotel'][] = $tempData;
          }
          foreach($calculationOn['insurance'] as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['data']['calculation_on']['insurance'][] = $tempData;
          }
          foreach($productType as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['data']['product_type'][] = $tempData;
          }
          foreach($partnerInfo as $key => $value){
            $tempData                   = array();
            $tempData['account_id']     = $key;
            $tempData['account_name']   = $value;
            $responseData['data']['account_details'][]  = $tempData ;
        }
          $responseData['criteria']     = $optionalCriterias;

        $responseData['status']                         =   'success';
        return response()->json($responseData);

    }

    public function store(Request $request)
    {
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('supplierSurcharge.store_failed');
        $responseData['status'] 		=   'failed';
        $reqData                        =   $request->all();
        $reqData                        =   $reqData['supplier_surcharge'];
        $surchargeData       =   self::commonStore($reqData,'store');
        if($surchargeData)
        {
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('supplierSurcharge.store_success');
            $responseData['data']           =   $surchargeData;
            $responseData['status'] 		=   'success';
        }
        return response()->json($responseData);

    }

    public function edit($id)
    {
        $responseData                                   =   array();
        $responseData['status_code'] 	                =   config('common.common_status_code.failed');
        $responseData['message'] 		                =   __('supplierSurcharge.retrive_failed');
        $responseData['status']                         =   'failed';
        $id                                             =   decryptData($id);
        $surchargeData                                  =   SupplierSurcharge::find($id);
        if($surchargeData)
        {
        $responseData['status_code'] 	                =   config('common.common_status_code.success');
        $responseData['message'] 		                =   __('supplierSurcharge.retrive_success');
        $surchargeData['encrypt_surcharge_id']          =   encryptData($surchargeData['surcharge_id']);
        $surchargeData['surcharge_amount']              =   json_decode($surchargeData['surcharge_amount']);
        $surchargeData['criterias']                     =   json_decode($surchargeData['criterias']);
        $surchargeData['selected_criterias']            =   json_decode($surchargeData['selected_criterias']);
        $surchargeData['updated_by']                    =   UserDetails::getUserName($surchargeData['updated_by'],'yes');
        $surchargeData['created_by']                    =   UserDetails::getUserName($surchargeData['created_by'],'yes');
        $responseData['data']                           =   $surchargeData;
        $calculationOn                                  =   config('common.calculation_on');
        $optionalCriterias                              =   config('criterias.supplier_surcharge_criterias');
        $surchargeType                                  =   config('common.surcharge_type');
        $partnerInfo                                    =   AccountDetails::getAccountDetails();
        $productType                                    =   config('common.product_type');
        $status                                         =   config('common.status');
       
        foreach($status as $key => $value){
          $tempData                       = [];
          $tempData['label']              = $key;
          $tempData['value']              = $value;
          $responseData['status_info'][] = $tempData;
        }
        foreach($surchargeType as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['surcharge_type'][] = $tempData;
          }
         
          foreach($calculationOn['flight'] as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['calculation_on']['flight'][] = $tempData;
          }
          foreach($calculationOn['hotel'] as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['calculation_on']['hotel'][] = $tempData;
          }
          foreach($calculationOn['insurance'] as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['calculation_on']['insurance'][] = $tempData;
          }
          foreach($productType as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['product_type'][] = $tempData;
          }
          foreach($partnerInfo as $key => $value){
            $tempData                   = array();
            $tempData['account_id']     = $key;
            $tempData['account_name']   = $value;
            $responseData['account_details'][]  = $tempData ;
        }
          $responseData['criteria']     = $optionalCriterias;
          $responseData['status']                         =   'success';
    }
        return response()->json($responseData);

    }

    public function update(Request $request)
    {
        $reqData                            =   $request->all();
        $reqData                            =   $reqData['supplier_surcharge'];
        $responseData                       =   array();
        $reqData['id']                      =   decryptData($reqData['surcharge_id']);
        $responseData['status_code']        =   config('common.common_status_code.failed');
        $responseData['message']            =   __('supplierSurcharge.updated_failed');
        $responseData['status']             =   'failed';
        $surchargeData                      =   self::commonStore($reqData,'update');
        if($surchargeData)
        {
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['message']            = __('supplierSurcharge.updated_success');
            $responseData['status']             =   'success';
            $responseData['data']               =   $surchargeData;
        }
        return response()->json($responseData);
    }
    public function commonStore($reqData , $flag)
    {
        $rules          =   [
            'account_id'        =>  'required',
            'surcharge_name'    =>  'required',
            'surcharge_code'    =>  'required',
            'currency_type'     =>  'required'
        ]; 
    
    $message        =   [
        'account_id.required'           =>  __('supplierSurcharge.account_id_required'),   
        'surcharge_name.required'       =>  __('supplierSurcharge.surcharge_name_required'),
        'surcharge_code.required'       =>  __('supplierSurcharge.surcharge_code_required'),
        'currency_type.required'        =>  __('supplierSurcharge.currency_type_required'),
    ];
    $validator = Validator::make($reqData, $rules, $message);
    $responseData                       = array();
     if ($validator->fails()) {
        $responseData['status_code']        =  config('common.common_status_code.validation_error');
        $responseData['message']             =  'The given data was invalid';
        $responseData['errors']              =  $validator->errors();
        $responseData['status']              =  'failed';
         return response()->json($responseData);
     }
        $data['account_id']                 =   $reqData['account_id'];
        $data['product_type']               =   $reqData['product_type'];
        $data['surcharge_name']             =   $reqData['surcharge_name'];
        $data['surcharge_code']             =   $reqData['surcharge_code'];
        $data['surcharge_type']             =   $reqData['surcharge_type'];
        $data['currency_type']              =   $reqData['currency_type'];
        $data['calculation_on']             =   $reqData['calculation_on'];
        $data['surcharge_amount']           =   json_encode($reqData['surcharge_amount']);
        $data['criterias']                  =   json_encode($reqData['criterias']);
        $data['selected_criterias']         =   (isset($reqData['selected_criterias'])) ? json_encode($reqData['selected_criterias']) : '';
        $data['status']                     =   $reqData['status'];
        if($flag == 'store')
        {
            $data['created_at']     =   Common::getDate();
            $data['updated_at']     =   Common::getDate();
            $data['created_by']     =   Common::getUserId();
            $data['updated_by']     =   Common::getUserId();
            $data   =   SupplierSurcharge::create($data);  
            if($data)          
            {
                 // Log Create
                $id =   $data->surcharge_id;
                $newOriginalTemplate = SupplierSurcharge::find($id)->getOriginal();
                Common::prepareArrayForLog($id,'Supplier Surcharge Updated',(object)$newOriginalTemplate,config('tables.supplier_surcharge_details'),'supplier_surcharge_details');
                //redis data update
                Common::ERunActionData($newOriginalTemplate['account_id'], 'updateSupplierSurcharge', $newOriginalTemplate['product_type']);
                return $data;
            }
        }
        if($flag == 'update')
        {
            $data['updated_at']     =   Common::getDate();
            $data['updated_by']     =   Common::getUserId();
            $id      =   $reqData['id'];
            $oldOriginalTemplate = SupplierSurcharge::find($id)->getOriginal();
            $data   =   SupplierSurcharge::where('surcharge_id',$id)->update($data);  
            if($data)          
            {
                // Log create
                $newOriginalTemplate = SupplierSurcharge::find($id)->getOriginal();
                $checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);
                if(count($checkDiffArray) > 0){
                    Common::prepareArrayForLog($id,'Supplier Surcharge Updated',(object)$newOriginalTemplate,config('tables.supplier_surcharge_details'),'supplier_surcharge_details');
                }
                //redis data update
                Common::ERunActionData($oldOriginalTemplate['account_id'], 'updateSupplierSurcharge', $newOriginalTemplate['product_type']);
                return $data;
            }
        }
    }
    public function delete(Request $request)
    {
        $reqData        =   $request->all();
        $deleteData     =   self::changeStatusData($reqData,'delete');
        if($deleteData)
        {
            return $deleteData;
        }
    }
 
    public function changeStatus(Request $request)
    {
        $reqData            =   $request->all();
        $changeStatus       =   self::changeStatusData($reqData,'changeStatus');
        if($changeStatus)
        {
            return $changeStatus;
        }
    }

    public function changeStatusData($reqData , $flag)
    {
        $responseData                   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('supplierSurcharge.delete_success');
        $responseData['status'] 		= 'success';
        $id     =   decryptData($reqData['id']);
        $rules =[
            'id' => 'required'
        ];
        
        $message =[
            'id.required' => __('common.id_required')
        ];
        
        $validator = Validator::make($reqData, $rules, $message);

    if ($validator->fails()) {
        $responseData['status_code'] = config('common.common_status_code.validation_error');
        $responseData['message'] = 'The given data was invalid';
        $responseData['errors'] = $validator->errors();
        $responseData['status'] = 'failed';
        return response()->json($responseData);
    }
    
    $status = 'D';
    if(isset($flag) && $flag == 'changeStatus'){
        $status = isset($reqData['status']) ? $reqData['status'] : 'IA';
        $responseData['message']        =   __('supplierSurcharge.status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status' => $status,
        'updated_at' => Common::getDate(),
        'updated_by' => Common::getUserID() 
    ];
    $changeStatus = SupplierSurcharge::where('surcharge_id',$id)->update($data);
    $newOriginalTemplate = SupplierSurcharge::find($id)->getOriginal();
    Common::prepareArrayForLog($id,'Supplier Surcharge Updated',(object)$newOriginalTemplate,config('tables.supplier_surcharge_details'),'supplier_surcharge_details');
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
        return response()->json($responseData);
    }

    public function getHistory($id)
        {
            $id = decryptData($id);
            $inputArray['model_primary_id'] = $id;
            $inputArray['model_name']       = config('tables.supplier_surcharge_details');
            $inputArray['activity_flag']    = 'supplier_surcharge_details';
            $responseData = Common::showHistory($inputArray);
            return response()->json($responseData);
        }
    
        public function getHistoryDiff(Request $request)
        {
            $requestData = $request->all();
            $id = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
            if($id != 0)
            {
                $inputArray['id']               = $id;
                $inputArray['model_name']       = config('tables.supplier_surcharge_details');
                $inputArray['activity_flag']    = 'supplier_surcharge_details';
                $inputArray['count']            = isset($requestData['count']) ? $requestData['count']: 0;
                $responseData                   = Common::showDiffHistory($inputArray);
            }
            else
            {
                $responseData['status_code'] = config('common.common_status_code.failed');
                $responseData['status'] = 'failed';
                $responseData['message'] = 'get history difference failed';
                $responseData['errors'] = 'id required';
                $responseData['short_text'] = 'get_history_diff_error';
            }
            return response()->json($responseData);
        }
}
