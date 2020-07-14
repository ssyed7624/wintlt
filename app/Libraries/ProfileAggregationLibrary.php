<?php 
namespace App\Libraries;
use App\Models\ProfileAggregation\ProfileAggregation;
use App\Models\ProfileAggregation\ProfileAggregationCs;
use App\Models\AccountDetails\AccountAggregationMapping;
use App\Models\ProfileAggregation\PortalAggregationMapping;
use App\Models\ContentSource\ContentSourceDetails;
use App\Models\SupplierMarkupTemplate\SupplierMarkupTemplate;
use App\Models\SupplierMarkupTemplate\SupplierMarkupContract;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalDetails;
use Log;
use DB;
class ProfileAggregationLibrary 
{
	public static function getProfileAggregationContentSource($requestData) 
	{	
		$aggregationChecking = array();

		$accountId    	= $requestData['account_id'];
		$productType 	= $requestData['product_type'];

		$productTypeText = 'Flight';

		if($productType == 'H'){
			$productTypeText = 'Hotel';
		}else if($productType == 'I'){
			$productTypeText = 'Insurance';
		}

		$aContentList   = array();

		//Get Content Source
		$aContentSource = ContentSourceDetails::select('content_source_details.content_source_id','content_source_details.gds_source','content_source_details.gds_product','content_source_details.pcc','content_source_details.default_currency','content_source_details.allowed_currencies','content_source_details.in_suffix','supplier_products.fare_types')
												->join('supplier_products', 'content_source_details.content_source_id', '=', 'supplier_products.content_source_id')
												->where('content_source_details.account_id',$accountId)
												->where('content_source_details.gds_product',$productTypeText)
												->where('content_source_details.status', 'A')
												->get();
												

		if(!empty($aContentSource)){
			$aContentSource = $aContentSource->toArray();

			foreach($aContentSource as $csKey => $csVal){

				$aTemp = array();
				$aTemp['content_type'] 	= 'CS';
				$aTemp['content_id'] 	= $csVal['content_source_id'];
				$aTemp['content_name'] 	= $csVal['gds_source'].'_'.$csVal['pcc'].'_'.$csVal['in_suffix'];
				$aTemp['gds_type'] 		= $csVal['gds_product'].'_'.$csVal['gds_source'];
				$aTemp['currency_list'] = implode(',',array_unique(explode(',', $csVal['default_currency'].','.$csVal['allowed_currencies'])));
				$aTemp['fare_types'] 	= $csVal['fare_types'];

				$aContentList[] = $aTemp;
			}
			
		}

		//Get Supplier Aggregation
		$aSupAggregation  =  DB::table(config('tables.account_aggregation_mapping').' AS aam')
											->select(DB::raw('pa.profile_name, pa.profile_aggregation_id, (select account_name from '.config('tables.account_details').' where account_details.account_id = pa.account_id) as account_name, ac.agency_currency,acm.currency, acm.settlement_currency'))
											->join(config('tables.profile_aggregation').' AS pa', 'aam.profile_aggregation_id', '=', 'pa.profile_aggregation_id')
											->join(config('tables.account_details').' AS ac', 'ac.account_id', '=', 'aam.partner_account_id')
											->leftjoin(config('tables.agency_credit_management'). ' As acm', function($join){
												$join->on("acm.account_id","=","aam.partner_account_id")
													->on("acm.supplier_account_id","=","aam.partner_account_id");
											})
											->where('aam.partner_account_id', $accountId)
											->where('pa.product_type', $productType)
											->where('pa.status', 'A')
											->where('aam.status', 'A');

											if(isset($requestData['profile_aggregation_id'])){
												$aSupAggregation = $aSupAggregation->where('pa.profile_aggregation_id', '!=' , $requestData['profile_aggregation_id']);
											}

											$aSupAggregation = $aSupAggregation->get();

											

		if(!empty($aSupAggregation)){

			$aSupAggregation = $aSupAggregation->toArray();

			foreach($aSupAggregation as $saKey => $saVal){

				if(!in_array($saVal->profile_aggregation_id,$aggregationChecking)){
					$aTemp = array();
					$aTemp['content_type'] 	= 'AG';
					$aTemp['content_id'] 	= $saVal->profile_aggregation_id;
					$aTemp['content_name'] 	= $saVal->profile_name.' ('. $saVal->account_name .')';

					if(isset($saVal->currency) && !empty($saVal->currency)){
						$aTemp['currency_list'] = implode(',',array_unique(explode(',', $saVal->currency.','.$saVal->settlement_currency)));
					}else{
						$aTemp['currency_list'] = implode(',',array_unique(explode(',', $saVal->currency.','.$saVal->agency_currency)));
					}
					
					$aTemp['fare_types'] 	= '';
					$aTemp['gds_type'] 		= '';

					$aContentList[] = $aTemp;
					$aggregationChecking[] = $saVal->profile_aggregation_id;
				}
			}
		}

		//Get Own Aggregation
		$aOwnAggregation  =  DB::table(config('tables.profile_aggregation').' AS pa')
											->select(DB::raw('pa.profile_name, pa.profile_aggregation_id, ac.account_name, ac.agency_currency,acm.currency, acm.settlement_currency'))
											->join(config('tables.account_details').' AS ac', 'ac.account_id', '=', 'pa.account_id')
											->leftjoin(config('tables.agency_credit_management'). ' As acm', function($join){
												$join->on("acm.account_id","=","pa.account_id")
													->on("acm.supplier_account_id","=","pa.account_id");
											})
											->where('pa.account_id', $accountId)
											->where('pa.product_type', $productType)
											->where('pa.status', 'A');

											if(isset($requestData['profile_aggregation_id'])){
												$aOwnAggregation = $aOwnAggregation->where('pa.profile_aggregation_id', '!=' , $requestData['profile_aggregation_id']);
											}

											$aOwnAggregation = $aOwnAggregation->get();

		if(!empty($aOwnAggregation)){

			$aOwnAggregation = $aOwnAggregation->toArray();

			foreach($aOwnAggregation as $oaKey => $oaVal){

				if(!in_array($oaVal->profile_aggregation_id,$aggregationChecking)){
					$aTemp = array();
					$aTemp['content_type'] 	= 'AG';
					$aTemp['content_id'] 	= $oaVal->profile_aggregation_id;
					$aTemp['content_name'] 	= $oaVal->profile_name.' ('. $oaVal->account_name .')';
					
					if(isset($oaVal->currency) && !empty($oaVal->currency)){
						$aTemp['currency_list'] = implode(',',array_unique(explode(',', $oaVal->currency.','.$oaVal->settlement_currency)));
					}else{
						$aTemp['currency_list'] = implode(',',array_unique(explode(',',$oaVal->agency_currency)));
					}

					$aTemp['fare_types'] 	= '';
					$aTemp['gds_type'] 		= '';
					$aContentList[] = $aTemp;
					$aggregationChecking[] = $oaVal->profile_aggregation_id;
				}
			}
		}

		$aReturn = array();
		if(isset($aContentList) && !empty($aContentList)){
			$aReturn['status'] 	= 'Success';
			$aReturn['content'] = $aContentList;
		}else{
			$aReturn['status'] = 'Failed';
			$aReturn['msg'] = 'No gds details found';
		}

		return $aReturn;
	}

	public static function getMarkupTemplate($requestData, $mode = 'create') 
	{

		$accountId    	= $requestData['account_id'];
		$productType 	= $requestData['product_type'];
		$currency 		= $requestData['currency'];

		if(!is_array($currency)){
			$currency = array($currency);
		}

		$aMarkupTemplateList = array();

		//Get Markup Template
		$aMarkupTemplate = SupplierMarkupTemplate::where('account_id',$accountId)->where('product_type',$productType)->whereIn('currency_type',$currency)->where('status','A')->get();
		if(!empty($aMarkupTemplate)){
			$aMarkupTemplate = $aMarkupTemplate->toArray();

			foreach($aMarkupTemplate as $mtKey => $mtVal){

				$aTemp = array();
				$aTemp['template_id'] 		= $mtVal['markup_template_id'];
				$aTemp['template_name'] 	= $mtVal['template_name'];
				$aTemp['currency_type'] 	= $mtVal['currency_type'];

				$aMarkupTemplateList[] = $aTemp;
			}
		}

		$aReturn = array();
		if(isset($aMarkupTemplateList) && !empty($aMarkupTemplateList)){
			$aReturn['status'] 	= 'Success';
			$aReturn['content'] = $aMarkupTemplateList;
		}else{

			if($mode == 'create' && config('common.pa_dynamic_markup_template_creation')){

				$accountDetails  	= AccountDetails::where('account_id', $accountId)->first();

				$accountName = isset($accountDetails['account_name']) ? $accountDetails['account_name'] : 'Dynamic Markup';
				
				$uniqueId 	= time().'-'.rand(999,9999);
				$markupName = $accountName.' '.$uniqueId;

				$supplierMarkupTemp = new SupplierMarkupTemplate();
				$supplierMarkupTemp->account_id			= $accountId;
				$supplierMarkupTemp->product_type 		= $productType;
				$supplierMarkupTemp->template_name   	= $markupName;
				$supplierMarkupTemp->currency_type   	= $currency[0];
				$supplierMarkupTemp->status          	= 'A';
				$supplierMarkupTemp->created_by      	= Common::getUserID();
				$supplierMarkupTemp->updated_by      	= Common::getUserID();
				$supplierMarkupTemp->created_at      	= Common::getDate();
				$supplierMarkupTemp->updated_at      	= Common::getDate();
				$supplierMarkupTemp->save();

				$markupTemplateId = $supplierMarkupTemp->markup_template_id;


				//Hidden insert for SupplierMarkupContract table - For product_type Hotel & Insurance
				if(isset($productType) && $productType != 'F'){

					if($productType == 'H'){
						$markupContractName = 'Hotel_Group_'.$markupTemplateId;
					}else if($productType == 'I'){
						$markupContractName = 'Insurance_Group_'.$markupTemplateId;
					} 

					$supplierMarkupConract = new SupplierMarkupContract;
					$supplierMarkupConract->markup_template_id   = $markupTemplateId;
					$supplierMarkupConract->markup_contract_name = $markupContractName;      
					$supplierMarkupConract->validating_carrier   = 'ALL';
					$supplierMarkupConract->parent_id            = 0;
					$supplierMarkupConract->pos_contract_id      = 0;
					$supplierMarkupConract->account_id           = $accountId;
					$supplierMarkupConract->currency_type        = $currency[0];
					$supplierMarkupConract->fare_type            = 'PUB';
					$supplierMarkupConract->trip_type            = 'ALL';
					$supplierMarkupConract->criterias            = '[]';
					$supplierMarkupConract->status               = 'A';
					$supplierMarkupConract->created_by           = Common::getUserID();
					$supplierMarkupConract->updated_by           = Common::getUserID();
					$supplierMarkupConract->created_at           = Common::getDate();
					$supplierMarkupConract->updated_at           = Common::getDate();
					$supplierMarkupConract->save();
				}

				//Dynamic Markup
				$aTemp = array();
				$aTemp['template_id'] 		= $markupTemplateId;
				$aTemp['template_name'] 	= $markupName;

				$aMarkupTemplateList[] = $aTemp;

				$aReturn['status'] 	= 'Success';
				$aReturn['content'] = $aMarkupTemplateList;

			}else{
				$aReturn['status'] = 'Failed';
				$aReturn['msg'] = 'No markup template found';
			}
		}

		return $aReturn;

	}

	public static function storeDynamicAggregation($accountId = 0, $accountAggregation = []){

		$accAggMappin 			= AccountAggregationMapping::where('partner_account_id','=',$accountId)->whereIn('status',['A','IA'])->first();
		$accProfilAggegation 	= ProfileAggregation::where('account_id', $accountId)->whereIn('status',['A','IA'])->first();

		if($accAggMappin || $accProfilAggegation || !config('common.dynamic_aggregation_creation')){
			return true;
		}

		$accountDetails  	= AccountDetails::where('account_id', $accountId)->first();
		$portalDetails  	= PortalDetails::where('account_id', $accountId)->where('business_type', 'B2B')->first();
		$agencyCurrency 	= $accountDetails['agency_currency'];

		$aggTypeArray 	= array();		

        // Contente Souce Storing

        foreach ($accountAggregation as $agKey => $aggDetails) {


        	if(!isset($aggDetails['supplier_account_id']) || $aggDetails['supplier_account_id'] == '' )continue;
    		if(!isset($aggDetails['profile_aggregation_id']) || $aggDetails['profile_aggregation_id'] == '' )continue;

        	$aggID = $aggDetails['profile_aggregation_id'];

	        $getProfileAggregation = ProfileAggregation::where('profile_aggregation_id', $aggID)->whereIn('status',['A','IA'])->first();

	        if($getProfileAggregation){


	        	if(!isset($aggTypeArray[$getProfileAggregation['product_type']])){

	        		$accountName = isset($accountDetails['account_name']) ? $accountDetails['account_name'] : 'Dynamic Aggregation';

		        	$uniqueId 			= time().'-'.rand(999,9999);
					$aggregationName 	= $accountName.' '.$uniqueId;

					//Profile Aggregation Insert
			        $profileAggregation                     = new ProfileAggregation();
			        $profileAggregation->account_id         = $accountId;
			        $profileAggregation->profile_name       = $aggregationName;
			        $profileAggregation->product_type       = $getProfileAggregation['product_type'];
			        $profileAggregation->profile_description= $aggregationName;
			        $profileAggregation->low_fare_type      = 'A';
			        $profileAggregation->status             = 'A';  
			        $profileAggregation->created_by         = Common::getUserID();
			        $profileAggregation->updated_by         = Common::getUserID();
			        $profileAggregation->created_at         = Common::getDate();
			        $profileAggregation->updated_at         = Common::getDate();
			        $profileAggregation->save();
			        $profileAggregationInsertedId   = $profileAggregation->profile_aggregation_id;

			        $aggTypeArray[$getProfileAggregation['product_type']] = $profileAggregationInsertedId;
			    }





		        $requestData = [];
		        $requestData['accountId'] 	= $accountId;
				$requestData['productType'] = $getProfileAggregation['product_type'];
				$requestData['currency'] 	= $agencyCurrency;
		        $getMarkupTemplate = self::getMarkupTemplate($requestData);

		        $markupTemplateId = 0;
		        if(isset($getMarkupTemplate['Status']) && $getMarkupTemplate['Status'] == 'Success'){
		        	$markupTemplateId = isset($getMarkupTemplate['Content'][0]['template_id']) ? $getMarkupTemplate['Content'][0]['template_id'] : 0;
		        }

		         $profileAggregationCs = new ProfileAggregationCs();
		        $profileAggregationCs->profile_aggregation_id   = $aggTypeArray[$getProfileAggregation['product_type']];
		        $profileAggregationCs->searching                = $getProfileAggregation['profile_aggregation_id'];
		        $profileAggregationCs->content_type             = 'AG';
		        
		        $profileAggregationCs->booking_public       	= 0;
		        $profileAggregationCs->booking_private      	= 0;
		        
		        $profileAggregationCs->currency_type            = $agencyCurrency;
		        $profileAggregationCs->markup_template_id       = $markupTemplateId;
		        $profileAggregationCs->fare_types               = 'PUB';
		        $profileAggregationCs->market_info              = '{"B2B":"ALL","B2C":"ALL","B2C_META":"ALL"}';
		        $profileAggregationCs->shopping_type            = 'N';   
		        $profileAggregationCs->status                   = 'A';
		        $profileAggregationCs->created_by               = Common::getUserID();
		        $profileAggregationCs->updated_by               = Common::getUserID();
		        $profileAggregationCs->created_at               = Common::getDate();
		        $profileAggregationCs->updated_at               = Common::getDate();
		        
		        $profileAggregationCs->save();
		        $profileAggregationCsInsertedId = $profileAggregationCs->profile_aggregation_cs_id;
		    }
	    }

	    if($portalDetails){
		    $portalAggregationModel = new PortalAggregationMapping();
		    $portalAggregationModel->account_id              = $accountId;
			$portalAggregationModel->portal_id               = isset($portalDetails['portal_id']) ? $portalDetails['portal_id'] : '';
			$portalAggregationModel->profile_aggregation_id  = implode(',', $aggTypeArray);
			$portalAggregationModel->status                  = 'A';
			$portalAggregationModel->created_by 			 = Common::getUserID();
			$portalAggregationModel->updated_by 			 = Common::getUserID();
			$portalAggregationModel->updated_at              = Common::getDate();
			$portalAggregationModel->created_at 		     = Common::getDate();

	    	$portalAggregationModel->save();
	    }

	}
	
}
