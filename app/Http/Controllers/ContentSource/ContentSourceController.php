<?php

namespace App\Http\Controllers\ContentSource;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ContentSource\ContentSourceDetails;
use App\Models\ContentSource\ContentSourceApiCredential;
use App\Models\SupplierPosTemplate\SupplierPosContentsourceMapping;
use App\Models\MapPosGds\PortalContentsourceMapping;
use App\Models\ContentSource\SupplierProducts;
use App\Models\PortalDetails\PortalDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\Common\CurrencyDetails;
use App\Libraries\Common;
use App\Libraries\Oauth;
use Validator;
use Auth;
use DB;



class ContentSourceController extends Controller
{
    public function index()
    {
        $returnData = [];
        $responseData = [];
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'contract_index_form_data';
        $responseData['message']        = 'contract index form data success';
        $returnData['account_details']  = AccountDetails::getAccountDetails(config('common.partner_account_type_id'), 0, false);
        $apiMode                        = config('common.content_source_api_mode');
        foreach ($apiMode as $key => $value) {
            $tempArray = [];
            $tempArray['value'] = $key;
            $tempArray['label'] = $value;
            $returnData['api_mode_filter'][] = $tempArray;
        }
        $returnData['api_mode_filter'] = array_merge([['value'=>'','label'=>'ALL']],$returnData['api_mode_filter']);
        $responseData['data']           = $returnData;
        return response()->json($responseData);
    }

    public function list(Request $request)
    {
        $returnResponse = array();
        $inputArray = $request->all();
        $accountIds = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),0, true);
        $contentSourceList = ContentSourceDetails::on(config('common.slave_connection'))->select('content_source_details.*','ad.account_name', 'sp.fare_types')->join(config('tables.supplier_products').' As sp', 'sp.content_source_id', '=', 'content_source_details.content_source_id')->join('account_details As ad', 'ad.account_id', 'content_source_details.account_id')
            ->whereIn('content_source_details.account_id', $accountIds)
            ->whereNotIn('ad.status',['D'])
            ->whereNotIn('content_source_details.status',['D']);

        if((isset($inputArray['agency_id']) && $inputArray['agency_id'] != '') || (isset($inputArray['query']['agency_id']) && $inputArray['query']['agency_id'] != '')){
            $accountId = (isset($inputArray['agency_id']) && $inputArray['agency_id'] != '') ? $inputArray['agency_id'] : $inputArray['query']['agency_id'];
            $contentSourceList = $contentSourceList->where('content_source_details.account_id',$accountId);
        }
        if((isset($inputArray['in_suffix']) && $inputArray['in_suffix'] != '') || (isset($inputArray['query']['in_suffix']) && $inputArray['query']['in_suffix'] != '')){
            $insufix = ((isset($inputArray['in_suffix']) && $inputArray['in_suffix'] != '') ? $inputArray['in_suffix'] : $inputArray['query']['in_suffix']) ; 
            $contentSourceList = $contentSourceList->where('content_source_details.in_suffix','LIKE','%'.$insufix.'%');
        }
         if((isset($inputArray['gds_source']) && $inputArray['gds_source'] != '') || (isset($inputArray['query']['gds_source']) && $inputArray['query']['gds_source'] != '')){
            $gds = ((isset($inputArray['gds_source']) && $inputArray['gds_source'] != '') ? $inputArray['gds_source'] : $inputArray['query']['gds_source']);
            $contentSourceList = $contentSourceList->where('content_source_details.gds_source','LIKE','%'.$gds.'%');
        }
        if((isset($inputArray['currency']) && $inputArray['currency'] != '') || (isset($inputArray['query']['currency']) && $inputArray['query']['currency'] != '')){
            $currency = ((isset($inputArray['currency']) && $inputArray['currency'] != '') ? $inputArray['currency'] : $inputArray['query']['currency']);
            $contentSourceList = $contentSourceList->where('content_source_details.default_currency','like','%'.$currency.'%');
        }
        if((isset($inputArray['pcc']) && $inputArray['pcc'] != '') || (isset($inputArray['query']['pcc']) && $inputArray['query']['pcc'] != '')){
            $pcc = (isset($inputArray['pcc']) && $inputArray['pcc'] != '') ? $inputArray['pcc'] : $inputArray['query']['pcc'];
            $contentSourceList = $contentSourceList->where(function($query) use($pcc){
                $query->where('content_source_details.pcc','like',"%".$pcc."%")
                        ->orwhere('sp.fare_types','Like','%'.$pcc.'%');
                });
        }
        if((isset($inputArray['content_source_ref_key']) && $inputArray['content_source_ref_key'] != '') || (isset($inputArray['query']['content_source_ref_key']) && $inputArray['query']['content_source_ref_key'] != '')){
            $refKey = (isset($inputArray['content_source_ref_key']) && $inputArray['content_source_ref_key'] != '') ? $inputArray['content_source_ref_key'] : $inputArray['query']['content_source_ref_key'];
            $contentSourceList = $contentSourceList->where('content_source_details.content_source_ref_key','LIKE','%'.$refKey.'%');
        }
        if((isset($inputArray['api_mode']) && $inputArray['api_mode'] != '') || (isset($inputArray['query']['api_mode']) && $inputArray['query']['api_mode'] != '')){
            $api = (isset($inputArray['api_mode']) && $inputArray['api_mode'] != '') ? $inputArray['api_mode'] : $inputArray['query']['api_mode'];
            $contentSourceList = $contentSourceList->where('content_source_details.api_mode','like','%'.$api.'%');
        }
        if((isset($inputArray['account_name']) && $inputArray['account_name'] != '') || (isset($inputArray['query']['account_name']) && $inputArray['query']['account_name'] != '')){
            $accountName = (isset($inputArray['account_name']) && $inputArray['account_name'] != '') ? $inputArray['account_name'] : $inputArray['query']['account_name'];
            $contentSourceList = $contentSourceList->where('ad.account_name','LIKE','%'.$accountName.'%');
        }
        if((isset($inputArray['gds_product']) && $inputArray['gds_product'] != '') || (isset($inputArray['query']['gds_product']) && $inputArray['query']['gds_product'] != '')){
            $gdsProduct = (isset($inputArray['gds_product']) && $inputArray['gds_product'] != '') ? $inputArray['gds_product'] : $inputArray['query']['gds_product'];
            $contentSourceList = $contentSourceList->where('content_source_details.gds_product','LIKE','%'.$gdsProduct.'%');
        }
        if((isset($inputArray['status']) && $inputArray['status'] != '') || (isset($inputArray['query']['status']) && $inputArray['query']['status'] != '')){
            $contentSourceList = $contentSourceList->where('content_source_details.status',(isset($inputArray['status']) && $inputArray['status'] != '') ? $inputArray['status'] : $inputArray['query']['status']);
        }

        //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            switch($inputArray['orderBy']) {
                case 'account_name':
                    $contentSourceList    = $contentSourceList->orderBy('ad.account_name',$sortColumn);
                    break;
                default:
                    $contentSourceList    = $contentSourceList->orderBy($inputArray['orderBy'],$sortColumn);
                    break;
            }
        }else{
            $contentSourceList    = $contentSourceList->orderBy('content_source_id','desc');
        }
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        //prepare for listing counts
        $contentSourceListCount               = $contentSourceList->take($inputArray['limit'])->count();
        $returnData['recordsTotal']     = $contentSourceListCount;
        $returnData['recordsFiltered']  = $contentSourceListCount;
        //finally get data
        $contentSourceList              = $contentSourceList->offset($start)->limit($inputArray['limit'])->get();
        $i = 0;
        $count = $start;
        $apiMode = config('common.Products.product_type.Flight.Sabre.api_mode');
        if($contentSourceList->count() > 0){
            $contentSourceList = json_decode($contentSourceList,true);
            foreach ($contentSourceList as $listData) {
                $returnData['data'][$i]['si_no']        = ++$count;
                $returnData['data'][$i]['id']   = $listData['content_source_id'];
                $returnData['data'][$i]['content_source_id']   = encryptData($listData['content_source_id']);
                $returnData['data'][$i]['default_currency'] = $listData['default_currency'];
                $returnData['data'][$i]['in_suffix'] = $listData['in_suffix'];
                $returnData['data'][$i]['fare_types'] = $listData['fare_types'];
                $returnData['data'][$i]['pcc'] = $listData['pcc'];
                $returnData['data'][$i]['content_source_ref_key'] = $listData['content_source_ref_key'];
                $returnData['data'][$i]['gds_source'] = $listData['gds_source'];
                $returnData['data'][$i]['account_name'] = $listData['account_name'];
                $returnData['data'][$i]['gds_product'] = __('common.'.$listData['gds_product']);
                $returnData['data'][$i]['api_mode'] = (isset($apiMode[$listData['api_mode']]) ? $apiMode[$listData['api_mode']] : '-');
                $returnData['data'][$i]['status']       = $listData['status'];
                $i++;
            }
        }
        if($i > 0){
            $responseData['status'] = 'success';
            $responseData['status_code'] = config('common.common_status_code.success');
            $responseData['message'] = 'list data success';
            $responseData['short_text'] = 'list_data_success';
            $responseData['data']['records'] = $returnData['data'];
            $responseData['data']['records_filtered'] = $returnData['recordsFiltered'];
            $responseData['data']['records_total'] = $returnData['recordsTotal'];
        }
        else
        {
            $responseData['status'] = 'failed';
            $responseData['status_code'] = config('common.common_status_code.empty_data');
            $responseData['message'] = 'list data failed';
            $responseData['short_text'] = 'list_data_failed';
        }
        return response()->json($responseData);
    }

    public function create()
    {
        $outputArray = [];
        $data = [];
        $data['suppliers'] = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1);
        $data['product_details'] = config('common.Products.product_type');
        $data['currencies'] = CurrencyDetails::getCurrencyDetails();
        $data['timeZoneList']  =  Common::timeZoneList();
        $outputArray['status_code']     =  config('common.common_status_code.success');
        $outputArray['message']         =  'content source create data';
        $outputArray['short_text']     =  'content_source_create_data';
        $outputArray['data']           =  $data;
        $outputArray['status']      = 'success';
        return response()->json($outputArray);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $inputArray = $request->all();
        $rules  =   [
            'account_id'            => 'required',
            'gds_product'           => 'required',
            'gds_source'            => 'required',
            'gds_source_version'    => 'required',
            'default_currency'      => 'required',
            'in_suffix'             => 'required',
            'pcc'                   => 'required',
            'status'                => 'required',
            'api_mode'              => 'required',
            'pcc_type'              => 'required',
        ];
        $message    =   [
            'account_id.required'           =>  __('common.account_id_required'),
            'gds_product.required'          =>  __('contentSource.gds_product_required'),
            'gds_source.required'           =>  __('contentSource.gds_source_required'),
            'gds_source_version.required'   =>  __('contentSource.gds_source_version_required'),
            'default_currency.required'     =>  __('common.currency_required'),
            'in_suffix.required'            =>  __('contentSource.in_suffix_required'),
            'pcc.required'                  =>  __('contentSource.pcc_required'),
            'status.required'               =>  __('common.status_required'),
            'api_mode.required'             =>  __('contentSource.api_mode_required'),
            'pcc_type.required'             =>  __('contentSource.pcc_type_required'),
        ];
        $validator = Validator::make($inputArray, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $outputArray = [];
        $serviceDataArrayBuild = array(); 
        $serviceDataTemp       = array(); 
        $aaaToPcc              = '';       
        $setData = $inputArray['services'];
        foreach ($setData as $key => $value) {
            if(isset($value[0]) && $inputArray['gds_product'] == 'Flight'){
               if($value['aaa_to_pcc'] == 'PNR_TO_BE_TRANSFERED'){
                    $aaaToPcc  = $value['aaa_to_pcc'];
               }else{
                    if(isset($value['cs_ref_key']) && !empty($value['cs_ref_key'])){
                    $aaaToPcc = $value['cs_ref_key'];
                    }else{
                        $aaaToPcc = isset($value['aaa_to_pcc']['text']) ? $value['aaa_to_pcc']['text'] : '';
                    }
               }
                $serviceDataArrayBuild[$value[0]] = array(
                    'type'       => (isset($inputArray['pcc_type']) && $inputArray['pcc_type'] == 'terminal_pcc') ? 'OFF' : $value['type'], 
                    'cs_ref_key' => isset($value['cs_ref_key']) ? $value['cs_ref_key'] : '',//First priority check cs_ref_key
                    'aaa_to_pcc' => ($value['cs_ref_key'] == '') ? $aaaToPcc : '',//second prioity aaa_to_pcc
                );            
            }else{//gds product is not Flight
                $serviceDataTemp[$key] = array(
                    'type'       => 'ON', 
                    'cs_ref_key' => '',
                    'aaa_to_pcc' => '',
                ); 
            }
        }

        $onewaySetting = [];
        if(isset($inputArray['one_way_fare_enabled']))
        {
            $onewaySetting['one_way_fare_enabled'] = 'Y';
            if($inputArray['round'] == 'round')
            {
                $onewaySetting['ROUND'] = true;
                $onewaySetting['allowRtOneway'] = false;
            }
            else
            {
                $onewaySetting['ROUND'] = false;
                $onewaySetting['allowRtOneway'] = true;
            }
            if($inputArray['multi'] == 'multi')
            {
                $onewaySetting['MULTI'] = true;
                $onewaySetting['OPENJAW'] = true;
                $onewaySetting['allowMlOneway'] = false;
            }
            else
            {
                $onewaySetting['MULTI'] = false;
                $onewaySetting['OPENJAW'] = false;
                $onewaySetting['allowMlOneway'] = true;
            }
            $onewaySetting = json_encode($onewaySetting);
        }
        else
        {
            $onewaySetting = '{"oneway_fare_enabled":"N","ROUND":false,"MULTI":false,"OPENJAW":false,"allowRtOneway":false,"allowMlOneway":false}';
        }
               

        $configProductArr   = config('common.Products.product_type');
        $hasOffline         = isset($configProductArr[$inputArray['gds_product']][$inputArray['gds_source']]['has_offline']) ? $configProductArr[$inputArray['gds_product']][$inputArray['gds_source']]['has_offline'] : '';
        if($hasOffline == 'N'){
            $inputArray['pcc_type']    = 'webservice_pcc';
        }

        $contentSource                          = new ContentSourceDetails();
        $contentSource->account_id              = $inputArray['account_id'];
        $contentSource->gds_product             = $inputArray['gds_product'];
        $contentSource->gds_source              = $inputArray['gds_source'];
        $contentSource->gds_source_version      = isset($inputArray['gds_source_version']) ? $inputArray['gds_source_version'] : '';
        $contentSource->provide_code            = isset($inputArray['provider_code']) ? $inputArray['provider_code'] : '';
        $contentSource->default_currency        = isset($inputArray['default_currency']) ? $inputArray['default_currency'] : '';
        $contentSource->allowed_currencies      = isset($inputArray['allowed_currencies']) ? implode(',',$inputArray['allowed_currencies']) : '';
        $contentSource->gds_timezone            = isset($inputArray['gds_timezone']) ? $inputArray['gds_timezone'] : '';
        $contentSource->gds_country_code        = isset($inputArray['gds_country_code']) ? $inputArray['gds_country_code'] : '';
        $contentSource->in_suffix               = $inputArray['in_suffix'];
        $contentSource->dk_number               = isset($inputArray['dk_number']) ? $inputArray['dk_number'] : '';
        $contentSource->pcc                     = isset($inputArray['pcc']) ? $inputArray['pcc'] : '';
        $contentSource->default_queue_no        = isset($inputArray['default_queue_no']) ? $inputArray['default_queue_no'] : '';
        if(isset($inputArray['edit_id'] ) && $inputArray['edit_id'] != '')
        {
            $contentSource->parent_id   = $inputArray['edit_id'] ;
        }
        $contentSource->card_payment_queue_no   = isset($inputArray['card_payment_queue_no']) ? $inputArray['card_payment_queue_no'] : '';
        $contentSource->cheque_payment_queue_no = isset($inputArray['cheque_payment_queue_no']) ? $inputArray['cheque_payment_queue_no'] : '';
        $contentSource->paylater_queue_no       = isset($inputArray['paylater_queue_no']) ? $inputArray['paylater_queue_no'] : '';
        //$contentSource->content_source_ref_key  = $inputArray['content_source_ref_key;
        $contentSource->pcc_type                = $inputArray['pcc_type'];
        $contentSource->api_mode                = $inputArray['api_mode'];
        $contentSource->ptr_lniata              = isset($inputArray['ptr_lniata']) ? $inputArray['ptr_lniata'] : '';
        $contentSource->office_id               = isset($inputArray['office_id']) ? $inputArray['office_id'] : '';
        $contentSource->organization_id         = isset($inputArray['organization_id']) ? $inputArray['organization_id'] : '';
        $contentSource->status                  = isset($inputArray['status']) ? $inputArray['status'] : 'IA';
        $contentSource->content_source_settings = $onewaySetting;
        $contentSource->created_at              = Common::getDate();
        $contentSource->updated_at              = Common::getDate();
        $contentSource->created_by              = Common::getUserID();
        $contentSource->updated_by              = Common::getUserID();        

        if($contentSource->save()){
            //New content Source Details
            $newContentSourceDetailsGetOriginal = ContentSourceDetails::find($contentSource->content_source_id)->getOriginal();
            //Insert content source ref key
            $randomKey  = Common::random_num(8);
            ContentSourceDetails::where('content_source_id',$contentSource->content_source_id)->update(['content_source_ref_key' => $randomKey]);

            //travelport credential - provider value change to uppercase
            $credentialArr  = array();
            $credentialArr  = $inputArray['credentials'];
            $credentialMode = isset($configProductArr[$inputArray['gds_product']][$inputArray['gds_source']]['api_mode']) ? $configProductArr[$inputArray['gds_product']][$inputArray['gds_source']]['api_mode'] : '';
            if($credentialMode != ''){
                foreach ($credentialMode as $cKey => $cVal) {
                    if(isset($credentialArr[$cKey]['provider']) && $credentialArr[$cKey]['provider'] != ''){
                        $credentialArr[$cKey]['provider']   = strtoupper($credentialArr[$cKey]['provider']);
                    }            
                }
            }            

            $apiCredential                      = new ContentSourceApiCredential();
            $apiCredential->content_source_id   = $contentSource->content_source_id;
            if($inputArray['pcc_type'] == 'webservice_pcc'){
                $apiCredential->credentials     = json_encode($credentialArr);
            }else{
                $apiCredential->credentials     = '{}';
            }
            $apiCredential->created_at          = Common::getDate();
            $apiCredential->updated_at          = Common::getDate();
            $apiCredential->created_by          = Common::getUserID();
            $apiCredential->updated_by          = Common::getUserID();
            $apiCredential->save();
            //New Api Credential
            $newContentSourceApiCredentialGetOriginal = ContentSourceApiCredential::find($apiCredential->api_credential_id)->getOriginal();
            $supplierProducts                    = new SupplierProducts();
            $supplierProducts->content_source_id = $contentSource->content_source_id;
            $supplierProducts->products          = $inputArray['gds_product'];
            $supplierProducts->fare_types        = isset($inputArray['fare_types']) ? implode(',',$inputArray['fare_types']) : 'PUB';
            $supplierProducts->aggregaters       = isset($inputArray['aggregaters']) ? implode(',',$inputArray['aggregaters']) : '';
            if($inputArray['gds_product'] == 'Flight'){
                $supplierProducts->services          = json_encode($serviceDataArrayBuild);
            }else{
                $supplierProducts->services          = json_encode($serviceDataTemp);
            }
            
            $supplierProducts->created_at        = Common::getDate();
            $supplierProducts->updated_at        = Common::getDate();
            $supplierProducts->created_by        = Common::getUserID();
            $supplierProducts->updated_by        = Common::getUserID();

            $supplierProducts->save();

            //New Api Credential
            $newSourceProductGetOriginal = SupplierProducts::find($supplierProducts->supplier_product_id)->getOriginal();
            $newGetOriginal = [
                        'contentSource' => (isset($newContentSourceDetailsGetOriginal) && count($newContentSourceDetailsGetOriginal))?$newContentSourceDetailsGetOriginal:[],
                        'contentSourceApiCredential' => (isset($newContentSourceApiCredentialGetOriginal) && count($newContentSourceApiCredentialGetOriginal))?$newContentSourceApiCredentialGetOriginal:[],
                        'sourceProducts' => (isset($newSourceProductGetOriginal) && count($newSourceProductGetOriginal))?$newSourceProductGetOriginal:[],
                    ];
            $newMergedArrayValues = array_merge((isset($newContentSourceDetailsGetOriginal) && count($newContentSourceDetailsGetOriginal))?$newContentSourceDetailsGetOriginal:[],(isset($newContentSourceApiCredentialGetOriginal) && count($newContentSourceApiCredentialGetOriginal))?$newContentSourceApiCredentialGetOriginal:[],(isset($newSourceProductGetOriginal) && count($newSourceProductGetOriginal))?$newSourceProductGetOriginal:[]);
            Common::prepareArrayForLog($contentSource->content_source_id,'Content Source Updated',(object)$newMergedArrayValues,config('tables.content_source_details'),'content_source_management');

            // redis data update
            Common::ERunActionData($inputArray['account_id'], 'updateContentSources');

            $outputArray['status_code'] =  config('common.common_status_code.success');
            $outputArray['message']     =  'content source create data';
            $outputArray['short_text']  =  'content_source_create_data';
            $outputArray['status']      = 'success';

        }else{
        $outputArray['status_code'] =  config('common.common_status_code.success');
        $outputArray['message']     =  'content source store failed';
        $outputArray['short_text']  =  'content_source_store_failed';
        $outputArray['status']      = 'failed';
        }
        return response()->json($outputArray);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($flag,$id)
    { 
        $contentSource = array();
        $outputArray = [];
        $id = decryptData($id);        
        $contentSourceDbData = ContentSourceDetails::where('content_source_details.content_source_id','=', $id)
                ->join('content_source_api_credential','content_source_api_credential.content_source_id','=','content_source_details.content_source_id')
                ->join('supplier_products', 'supplier_products.content_source_id', '=', 'content_source_details.content_source_id')
                ->first();
        if($contentSourceDbData)
        {
            $contentSourceDbData->credentials        = json_decode($contentSourceDbData['credentials']);
            $contentSourceDbData->allowed_currencies = isset($contentSourceDbData['allowed_currencies']) ? explode(',',$contentSourceDbData['allowed_currencies']) : '';
            $contentSourceDbData->fare_types         = explode(',',$contentSourceDbData['fare_types']);
            $contentSourceDbData->aggregaters        = isset($contentSourceDbData['aggregaters']) ? explode(',',$contentSourceDbData['aggregaters']) : array();
            $contentSourceDbData->services           = json_decode($contentSourceDbData['services']);
            $contentSourceDbData                     = Common::getCommonDetails($contentSourceDbData);
            $contentSource['contentSourceGet']       = $contentSourceDbData;   
            $contentSource['id']                     = $id;

            $contentSource['roleId']         = Auth::user()->role_id;
            $contentSource['loginAccountId'] = Auth::user()->account_id;
            $contentSource['suppliers'] = AccountDetails::getAccountDetails(config('common.agency_account_type_id'), $isSupplier = 1);
            //content source ref key - start
                $accountId  = $contentSource['contentSourceGet']['account_id'];
                $gdsProduct = $contentSource['contentSourceGet']['gds_product'];
                $gdsSource  = $contentSource['contentSourceGet']['gds_source'];
                $contentSource['getCsArray'] = ContentSourceDetails::getAllCsRefKey($accountId, $gdsProduct, $gdsSource);
            //content source ref key - end
            $contentSource['timeZoneList']    =  Common::timeZoneList(); 
            $contentSource['currencies']      = CurrencyDetails::getCurrencyDetails();
            $contentSource['product_details'] = config('common.Products.product_type');
            $contentSource['action_flag']     =  $flag;
            $outputArray['status_code'] =  config('common.common_status_code.success');
            $outputArray['message']     =  'content source create data';
            $outputArray['short_text']  =  'content_source_create_data';
            $outputArray['status']      = 'success';
            $outputArray['data']        = $contentSource;
        }
        else
        {
            $outputArray['status_code'] =  config('common.common_status_code.success');
            $outputArray['message']     =  'content source data not found';
            $outputArray['short_text']  =  'content_source_data_not_found';
            $outputArray['status']      =  'failed';           
        }
        return response()->json($outputArray);

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $inputArray = $request->all();
        $rules  =   [
            'account_id'            => 'required',
            'gds_product'           => 'required',
            'gds_source'            => 'required',
            'gds_source_version'    => 'required',
            'default_currency'      => 'required',
            'in_suffix'             => 'required',
            'pcc'                   => 'required',
            'status'                => 'required',
            'api_mode'              => 'required',
            'pcc_type'              => 'required',
        ];
        $message    =   [
            'account_id.required'           =>  __('common.account_id_required'),
            'gds_product.required'          =>  __('contentSource.gds_product_required'),
            'gds_source.required'           =>  __('contentSource.gds_source_required'),
            'gds_source_version.required'   =>  __('contentSource.gds_source_version_required'),
            'default_currency.required'     =>  __('common.currency_required'),
            'in_suffix.required'            =>  __('contentSource.in_suffix_required'),
            'pcc.required'                  =>  __('contentSource.pcc_required'),
            'status.required'               =>  __('common.status_required'),
            'api_mode.required'             =>  __('contentSource.api_mode_required'),
            'pcc_type.required'             =>  __('contentSource.pcc_type_required'),
        ];
        $validator = Validator::make($inputArray, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $outputArray = [];
        $id = decryptData($id);        
        $contentSource          = ContentSourceDetails::find($id);
        $prevContentSourceStatus= $contentSource->status;

        //get old original data
        $oldContentSoureceGetOriginal = $contentSource->getOriginal();

   
        //validation for currency changing
        if($inputArray['default_currency'] != $contentSource['default_currency']){
            $validate = SupplierPosContentsourceMapping::where('content_source_id', '=', $id)->first();

            if($validate){
                $outputArray['status_code'] =  config('common.common_status_code.success');
                $outputArray['message']     =  'already mapped with suppliers';
                $outputArray['short_text']  =  'already_mapped_with_suppliers';
                $outputArray['status']      = 'failed';
                return response()->json($outputArray);
            }
        }
        $onewaySetting = [];
        if(isset($inputArray['one_way_fare_enabled']))
        {
            $onewaySetting['one_way_fare_enabled'] = 'Y';
            if($inputArray['round'] == 'round')
            {
                $onewaySetting['ROUND'] = true;
                $onewaySetting['allowRtOneway'] = false;
            }
            else
            {
                $onewaySetting['ROUND'] = false;
                $onewaySetting['allowRtOneway'] = true;
            }
            if($inputArray['multi'] == 'multi')
            {
                $onewaySetting['MULTI'] = true;
                $onewaySetting['OPENJAW'] = true;
                $onewaySetting['allowMlOneway'] = false;
            }
            else
            {
                $onewaySetting['MULTI'] = false;
                $onewaySetting['OPENJAW'] = false;
                $onewaySetting['allowMlOneway'] = true;
            }
            $onewaySetting = json_encode($onewaySetting); 
        }
        else
        {
            $onewaySetting = '{"oneway_fare_enabled":"N","ROUND":false,"MULTI":false,"OPENJAW":false,"allowRtOneway":false,"allowMlOneway":false}';
        }
        $serviceDataArrayBuild = array();        
        $setData = $inputArray['services'];
        foreach ($setData as $key => $value) {
            if(isset($value[0])){
               if($value['aaa_to_pcc'] == 'PNR_TO_BE_TRANSFERED'){
                    $aaaToPcc  = $value['aaa_to_pcc'];
               }else{
                    if(isset($value['cs_ref_key']) && !empty($value['cs_ref_key'])){
                        $aaaToPcc = $value['cs_ref_key'];
                    }else{
                        $aaaToPcc = isset($value['aaa_to_pcc']['text']) ? $value['aaa_to_pcc']['text'] : '';
                    }
               }                
                $serviceDataArrayBuild[$value[0]] = array(
                    'type'       => (isset($inputArray['pcc_type']) && $inputArray['pcc_type'] == 'terminal_pcc') ? 'OFF' : $value['type'],                    
                    'cs_ref_key' => isset($value['cs_ref_key']) ? $value['cs_ref_key'] : '',//First priority check cs_ref_key
                    'aaa_to_pcc' => ($value['cs_ref_key'] == '') ? $aaaToPcc : '',//second prioity aaa_to_pcc
                );            
            }
        }

        $configProductArr   = config('common.Products.product_type');
        $hasOffline         = isset($configProductArr[$inputArray['gds_product']][$inputArray['gds_source']]['has_offline']) ? $configProductArr[$inputArray['gds_product']][$inputArray['gds_source']]['has_offline'] : '';
        if($hasOffline == 'N'){
            $inputArray['pcc_type']    = 'webservice_pcc';
        }

        $contentSource->account_id                  = $inputArray['account_id'];
       /* $contentSource->gds_product                 = $inputArray['gds_product'];
        $contentSource->gds_source                  = $inputArray['gds_source'];
        $contentSource->gds_source_version          = $inputArray['isset($inputArray['gds_source_version']) ? $inputArray['gds_source_version : '';;*/
        $contentSource->default_currency            = isset($inputArray['default_currency']) ? $inputArray['default_currency'] : '';
        $contentSource->allowed_currencies          = isset($inputArray['allowed_currencies']) ? implode(',',$inputArray['allowed_currencies']) : '';
        $contentSource->gds_timezone                = isset($inputArray['gds_timezone']) ? $inputArray['gds_timezone'] : '';
        $contentSource->provide_code      = isset($inputArray['provider_code']) ? $inputArray['provider_code'] : '';
        $contentSource->gds_country_code            = isset($inputArray['gds_country_code']) ? $inputArray['gds_country_code'] : '';
        $contentSource->in_suffix                   = $inputArray['in_suffix'];
        $contentSource->dk_number                   = isset($inputArray['dk_number']) ? $inputArray['dk_number'] : '';
        $contentSource->pcc                         = isset($inputArray['pcc']) ? $inputArray['pcc'] : '';
        $contentSource->default_queue_no            = isset($inputArray['default_queue_no']) ? $inputArray['default_queue_no'] : '';
        $contentSource->card_payment_queue_no       = isset($inputArray['card_payment_queue_no']) ? $inputArray['card_payment_queue_no'] : '';
        $contentSource->cheque_payment_queue_no     = isset($inputArray['cheque_payment_queue_no']) ? $inputArray['cheque_payment_queue_no'] : '';
        $contentSource->paylater_queue_no           = isset($inputArray['paylater_queue_no']) ? $inputArray['paylater_queue_no'] : '';
        //$contentSource->content_source_ref_key      = $inputArray['content_source_ref_key;
        $contentSource->pcc_type                    = $inputArray['pcc_type'];
        $contentSource->api_mode                    = $inputArray['api_mode'];
        $contentSource->ptr_lniata                  = isset($inputArray['ptr_lniata']) ? $inputArray['ptr_lniata'] : '';
        $contentSource->office_id                   = isset($inputArray['office_id']) ? $inputArray['office_id'] : '';
        $contentSource->organization_id             = isset($inputArray['organization_id']) ? $inputArray['organization_id'] : '';
        $contentSource->content_source_settings     = $onewaySetting;
        $contentSource->status                      = isset($inputArray['status']) ? $inputArray['status'] : 'IA';
        $contentSource->updated_at                  = Common::getDate();
        $contentSource->updated_by                  = Common::getUserID();       

        if($contentSource->save()){

            //New content Source Details
        $newContentSourceDetailsGetOriginal = ContentSourceDetails::find($id)->getOriginal();


            //content source inactive inform mail for partners
            $accountId = $contentSource->account_id;
            $contentSourceName = $inputArray['in_suffix'];
            //for content source partner mail
            $url = url('/').'/sendEmail';
            if($accountId){
                if($contentSource->status != $prevContentSourceStatus){
                    $status = ($inputArray['status'] == 'A') ? __('common.activated') : __('common.inactivated');
                    $accountDetails = AccountDetails::where('account_id',$accountId)->first();
                    //send agency registered email
                    $parent_account_id = ($accountDetails['parent_account_id'] != 0 && $accountDetails['parent_account_id'] != '') ? $accountDetails['parent_account_id'] : $accountId;
                    $parentAccountName = Common::getAccountName($parent_account_id);

                    //select accounts uses this content source
                    $partnerAccountIds = [];
                    $content_source_id = $contentSource->content_source_id;
                    $partnerLists = PortalContentsourceMapping::select('supplier_account_id','partner_account_id')
                        ->where(function($query) use ($content_source_id){
                            return $query->where('content_source_id',$content_source_id)
                                ->orWhere('published_booking_cs_id',$content_source_id)
                                ->orWhere('special_booking_cs_id',$content_source_id)
                                ->orWhere('published_ticketing_cs_id',$content_source_id)
                                ->orWhere('special_ticketing_cs_id',$content_source_id);
                        })
                            ->whereNotIn('status',['D'])->first();
                    if(isset($partnerLists['supplier_account_id']) && $partnerLists['supplier_account_id'] != '')
                    {
                        $partnerAccountIds = array($partnerLists['supplier_account_id'], $partnerLists['partner_account_id']);
                    }
                    else
                    {
                        $partnerAccountIds = [$accountId];
                    }
                    $agencyEmails = AccountDetails::select(DB::raw('GROUP_CONCAT(agency_email) as agency_emails'))->whereIn('account_id',$partnerAccountIds)->whereNotIn('status',['D'])->first()->agency_emails;
                    $userEmails = DB::table(config('tables.user_details'). ' as ud')
                        ->join(config('tables.user_roles'). ' as ur', 'ur.role_id', '=', 'ud.role_id')
                        ->select(DB::raw('GROUP_CONCAT(ud.email_id) as user_emails'))
                        ->whereIn('ud.account_id',$partnerAccountIds)
                        ->whereNotin('ud.status',['D'])
                        ->where('ur.role_code','SO')->first()->user_emails;
                    $agencyEmails = explode(',', $agencyEmails);
                    $userEmails = explode(',', $userEmails);
                    $toEmails = implode(',', array_merge($agencyEmails,$userEmails));
                    $postArray = array('_token' => csrf_token(),'mailType' => 'contentSourceEnableDisableMailTrigger', 'toMail'=>$toEmails,'content_name'=>$contentSourceName, 'parentAccountName' => $parentAccountName, 'account_id' => $accountId,'status'=>$status);
                    //ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");

                }
            }//eoif

            //travelport credential - provider value change to uppercase
            $credentialArr  = array();
            $credentialArr  = $inputArray['credentials'];
            $credentialMode = isset($configProductArr[$contentSource->gds_product][$contentSource->gds_source]['api_mode']) ? $configProductArr[$contentSource->gds_product][$contentSource->gds_source]['api_mode'] : '';
            if($credentialMode != ''){
               foreach ($credentialMode as $cKey => $cVal) {
                    if(isset($credentialArr[$cKey]['provider']) && $credentialArr[$cKey]['provider'] != ''){
                        $credentialArr[$cKey]['provider']   = strtoupper($credentialArr[$cKey]['provider']);
                    }            
                } 
            }            

            $apiCredential = ContentSourceApiCredential::where('content_source_id', '=', $contentSource->content_source_id)->first();
            if($apiCredential){
                  //Old Api Credential
                  $oldApiCredentialGetOriginal = $apiCredential->getOriginal();  


                $apiCredential->content_source_id = $contentSource->content_source_id;
                if($inputArray['pcc_type'] == 'webservice_pcc'){
                    $apiCredential->credentials       = json_encode($credentialArr);
                }else{
                    $apiCredential->credentials       = '{}';
                }                
                $apiCredential->updated_at        = Common::getDate();
                $apiCredential->updated_by        = Common::getUserID();
                $apiCredential->save();

                 //New Api Credential
                 $newContentSourceApiCredentialGetOriginal = ContentSourceApiCredential::find($apiCredential->api_credential_id)->getOriginal();

            }           

            $supplierProducts = SupplierProducts::where('content_source_id', '=', $contentSource->content_source_id)->first();
            if($supplierProducts){
                 //Old Supplier Product Get original
                 $oldSupplierProductsGetOriginal = $supplierProducts->getOriginal();

                 
                $supplierProducts->content_source_id = $contentSource->content_source_id;
                //$supplierProducts->products          = $inputArray['gds_product;
                $supplierProducts->fare_types        = isset($inputArray['fare_types']) ? implode(',',$inputArray['fare_types']) : 'PUB';
                $supplierProducts->aggregaters       = isset($inputArray['aggregaters']) ? implode(',',$inputArray['aggregaters']) : '';
                //$supplierProducts->services          = json_encode($inputArray['services);
                if(isset($inputArray['gds_product_type']) && $inputArray['gds_product_type'] == 'Flight'){
                    $supplierProducts->services          = json_encode($serviceDataArrayBuild);
                }                
                $supplierProducts->updated_at        = Common::getDate();
                $supplierProducts->updated_by        = Common::getUserID();
                $supplierProducts->save();

                //New Api Credential
                $newSourceProductGetOriginal = SupplierProducts::find($supplierProducts->supplier_product_id)->getOriginal();

            }  


            $oldGetOriginal=[];
            $newGetOriginal=[];
            $oldGetOriginal = [
                'contentSource' => (isset($oldContentSoureceGetOriginal) && count($oldContentSoureceGetOriginal))?$oldContentSoureceGetOriginal:[],
                'contentSourceApiCredential' => (isset($oldApiCredentialGetOriginal) && count($oldApiCredentialGetOriginal))?$oldApiCredentialGetOriginal:[],
                'sourceProducts' => (isset($oldSupplierProductsGetOriginal) && count($oldSupplierProductsGetOriginal))?$oldSupplierProductsGetOriginal:[],
            ];
            
            $newGetOriginal = [
                'contentSource' => (isset($newContentSourceDetailsGetOriginal) && count($newContentSourceDetailsGetOriginal))?$newContentSourceDetailsGetOriginal:[],
                'contentSourceApiCredential' => (isset($newContentSourceApiCredentialGetOriginal) && count($newContentSourceApiCredentialGetOriginal))?$newContentSourceApiCredentialGetOriginal:[],
                'sourceProducts' => (isset($newSourceProductGetOriginal) && count($newSourceProductGetOriginal))?$newSourceProductGetOriginal:[],
            ];

            $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
            $newGetOriginal['actionFlag'] = $inputArray['action_flag'];

            if(count($checkDiffArray) > 1){
                $newMergedArrayValues = array_merge((isset($newContentSourceDetailsGetOriginal) && count($newContentSourceDetailsGetOriginal))?$newContentSourceDetailsGetOriginal:[],(isset($newContentSourceApiCredentialGetOriginal) && count($newContentSourceApiCredentialGetOriginal))?$newContentSourceApiCredentialGetOriginal:[],(isset($newSourceProductGetOriginal) && count($newSourceProductGetOriginal))?$newSourceProductGetOriginal:[]);
                Common::prepareArrayForLog($contentSource->content_source_id,'Content Source Updated',(object)$newMergedArrayValues,config('tables.content_source_details'),'content_source_management');
            }           

            //redis data update
            Common::ERunActionData($inputArray['account_id'], 'updateContentSources');         

            $outputArray['status_code'] =  config('common.common_status_code.success');
            $outputArray['message']     =  'content source update data';
            $outputArray['short_text']  =  'content_source_update_data';
            $outputArray['status']      = 'success';

        }else{
        $outputArray['status_code'] =  config('common.common_status_code.success');
        $outputArray['message']     =  'content source update failed';
        $outputArray['short_text']  =  'content_source_update_failed';
        $outputArray['status']      = 'failed';
        }
        return response()->json($outputArray);
    }

    public function changeStatus(Request $request)
    {
        $inputArray = $request->all();
        $rules     =[
            'status'      =>  'required',
            'id'        =>  'required'
        ];

        $message    =[
            'id.required'       =>  __('common.id_required'),
            'status.required'   =>  __('common.status_required')
        ];

        $validator = Validator::make($request->all(), $rules, $message);
   
        if ($validator->fails()) {
           $responseData['status_code']         = config('common.common_status_code.validation_error');
           $responseData['message']             = 'The given data was invalid';
           $responseData['errors']              = $validator->errors();
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }
        $responseData = array();
        
        $inputArray['id'] = decryptData($inputArray['id']);        
        $responseData = self::updateStatus($inputArray,'changeStatus');
        return response()->json($responseData);     
    }

    public function delete(Request $request)
    {
        $inputArray = $request->all();
        $rules     =[
            'id'        =>  'required'
        ];

        $message    =[
            'id.required'       =>  __('common.id_required')
        ];

        $validator = Validator::make($request->all(), $rules, $message);
   
        if ($validator->fails()) {
           $responseData['status_code']         = config('common.common_status_code.validation_error');
           $responseData['message']             = 'The given data was invalid';
           $responseData['errors']              = $validator->errors();
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }
        $responseData = array();
        
        $inputArray['id'] = decryptData($inputArray['id']);        
        $responseData = self::updateStatus($inputArray,'delete');
        return response()->json($responseData);     
    }

    public static function updateStatus($inputArray,$flag)
    {
        if($flag == 'delete')
        {
            $deleteCheckPcsm = PortalContentsourceMapping::select('supplier_account_id')->where('content_source_id', $inputArray['id'])->orWhere('published_booking_cs_id',$inputArray['id'])->orWhere('special_booking_cs_id',$inputArray['id'])->orWhere('published_ticketing_cs_id',$inputArray['id'])->orWhere('special_ticketing_cs_id',$inputArray['id'])->first();

            $deleteCheckSpcsm = SupplierPosContentsourceMapping::select([DB::raw('GROUP_CONCAT(pos_template_id) as pos_template_ids')])->where('content_source_id', '=', $inputArray['id'])->first();

            if($deleteCheckPcsm['supplier_account_id'] != '' || $deleteCheckSpcsm->count() > 0){
                $title = __('contentSource.error_delete_content_source');
                if(isset($deleteCheckPcsm['supplier_account_id']) && $deleteCheckPcsm['supplier_account_id'] != ''){
                    $getAccountName = AccountDetails::getAccountName($deleteCheckPcsm['supplier_account_id']);
                    $title = __('contentSource.already_mapped_content_source_error',['accountName'=>$getAccountName]);     
                }

                if($deleteCheckSpcsm->count() > 0 && $deleteCheckSpcsm->pos_template_ids != ''){
                    $SupplierPosTemplate = SupplierPosTemplate::select([DB::raw('GROUP_CONCAT(template_name) as template_names')])->whereIn('pos_template_id', explode(',',$deleteCheckSpcsm->pos_template_ids))->first();
                    $title = __('contentSource.already_mapped_template_error',['templateName'=>$SupplierPosTemplate->template_names]);
                }
                $responseData['status_code']         = config('common.common_status_code.failed');
                $responseData['message']             = 'The given data was invalid';
                $responseData['status']              = 'failed';
                $responseData['short_text']          = 'not_deleted';
                return [$failledResponse,$title];
            }
            $data= ContentSourceDetails::where('content_source_id', $inputArray['id'])->whereIn('status',['A','IA'])->update(["status" => 'D']);
            if($data){
                $contentSource = ContentSourceDetails::find($inputArray['id']);
                $title = $contentSource->gds_source;
                // redis data update
                $accountId = $contentSource->account_id;
                Common::ERunActionData($accountId, 'updateContentSources');
                
                $responseData['status_code']         = config('common.common_status_code.success');
                $responseData['message']             = 'deleted sucessfully';
                $responseData['status']              = 'success';
                $responseData['short_text']          = 'deleted_successfully';
                return $responseData;
            }else{
                $responseData['status_code']         = config('common.common_status_code.empty_data');
                $responseData['message']             = 'not found';
                $responseData['status']              = 'failed';
                $responseData['short_text']          = 'not_found';
                return $responseData;
            }
        }
        else
        {
            $status = isset($inputArray['status']) ? $inputArray['status'] : 'IA';
            $data = ContentSourceDetails::where('content_source_id', $inputArray['id'])->where('status','!=','D')->update(["status" => $status]);
            if($data)
            {
                // redis data update
                $accountId = ContentSourceDetails::find($inputArray['id'])->account_id;
                Common::ERunActionData($accountId, 'updateContentSources');

                $responseData['status_code']         = config('common.common_status_code.success');
                $responseData['message']             = 'status updated sucessfully';
                $responseData['status']              = 'success';
                $responseData['short_text']          = 'status_updated_successfully';
            }
            else
            {
                $responseData['status_code']         = config('common.common_status_code.empty_data');
                $responseData['message']             = 'not found';
                $responseData['status']              = 'failed';
                $responseData['short_text']          = 'not_found';
            }
        }
        return $responseData;
    }

    //get mapped content source ref key
    public function getContentSourceRefKey(Request $request)
    {
        $inputArray = $request->all();
        $rules     =[
            'gds_supplier'      =>  'required',
            'gds_product'       =>  'required',
            'gds_source'        =>  'required',
        ];

        $message    =[
            'gds_supplier.required'     =>  __('contentSource.gds_supplier_required'),
            'gds_product.required'      =>  __('contentSource.gds_product_required'),
            'gds_source.required'       =>  __('contentSource.gds_source_required')
        ];

        $validator = Validator::make($request->all(), $rules, $message);
   
        if ($validator->fails()) {
           $responseData['status_code']         = config('common.common_status_code.validation_error');
           $responseData['message']             = 'The given data was invalid';
           $responseData['errors']              = $validator->errors();
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }
        $getCsArray = ContentSourceDetails::getAllCsRefKey($inputArray['gds_supplier'], $inputArray['gds_product'], $inputArray['gds_source']);
        $responseData['status_code']         = config('common.common_status_code.success');
        $responseData['message']             = 'status updated sucessfully';
        $responseData['status']              = 'success';
        $responseData['short_text']          = 'status_updated_successfully';
        $responseData['data']                = $getCsArray;
        return response()->json($responseData);
    }  
    /**
    *Check AAAToPCC ref key exists or not - If exists condtion is true 
    */
    public function checkAAAtoPCCcsrefkeyExist(Request $request)
    {
        $inputArray = $request->all();
        $rules     =[
            //'content_source_id'     =>  'required',
            'field'                 =>  'required',
            //'services'                =>  'required',
        ];

        $message    =[
            'content_source_id.required'    =>  __('contentSource.content_source_id_required'),
            'field.required'                =>  __('contentSource.field_required'),
            'services.required'             =>  __('contentSource.services_required'),
        ];

        $validator = Validator::make($request->all(), $rules, $message);
   
        if ($validator->fails()) {
           $responseData['status_code']         = config('common.common_status_code.validation_error');
           $responseData['message']             = 'The given data was invalid';
           $responseData['errors']              = $validator->errors();
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }
        $csId = self::checkKeyExist($inputArray);       

        $isAvailable = false;

        if($csId){
            $isAvailable = true;

            $checkTypeOnorOff = ContentSourceDetails::checkTypeOnorOff($csId);

                if($checkTypeOnorOff){

                    $services = json_decode($checkTypeOnorOff->services);                       
                            
                    foreach ($services as $key => $value) {
                        if($key == $request->field){                               
                            if($value->type == 'ON'){
                                $isAvailable = true;
                            }else{
                                $isAvailable = false;
                            }
                        }                      

                    } 
                }
        }

        $responseData['status_code']         = config('common.common_status_code.success');
        $responseData['message']             = 'validation done';
        $responseData['status']              = 'success';
        $responseData['short_text']          = 'validation_done';
        $responseData['valid']               = $isAvailable;
        return response()->json($responseData);
    }  

    public static function checkKeyExist($requestData){ 
        $field          = $requestData['field'];
        $value          = isset($requestData['services'][$field]['aaa_to_pcc']['text']) ? $requestData['services'][$field]['aaa_to_pcc']['text'] : '';

        $checkKeyExist  = ContentSourceDetails::where('content_source_ref_key', $value)->whereNotIn('status',['D']);

        if($requestData['content_source_id']){
            $checkKeyExist->where('content_source_id', '!=', $requestData['content_source_id']);            
        }
        $checkKeyExist  =  $checkKeyExist->first();

        $csId           = '';
        if(isset($checkKeyExist['content_source_id']) && !empty($checkKeyExist['content_source_id'])){
             $csId      = $checkKeyExist['content_source_id'];
        }

        return $csId;
    }

    public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.content_source_details');
        $inputArray['activity_flag']    = 'content_source_management';
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
            $inputArray['model_name']       = config('tables.content_source_details');
            $inputArray['activity_flag']    = 'content_source_management';
            $inputArray['count']            = isset($requestData['count']) ? $requestData['count']: 0;
            $responseData                   = Common::showDiffHistory($inputArray);
            if(isset($responseData['status']) && $responseData['status'] == 'success')
            {
                $returnData['roleId']         = Auth::user()->role_id;
                $returnData['loginAccountId'] = Auth::user()->account_id;
                $returnData['suppliers'] = AccountDetails::getAccountDetails(config('common.agency_account_type_id'), $isSupplier = 1);
                //content source ref key - end
                $returnData['timeZoneList']  =  Common::timeZoneList(); 
                $returnData['currencies']    = CurrencyDetails::getCurrencyDetails();
                $returnData['action_flag']    =  'history';
                $responseData['data']['form_data']  = $returnData;
            }
            
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