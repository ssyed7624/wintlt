<?php 

namespace App\Libraries;

use Illuminate\Support\Facades\Redis;
use App\Models\FormOfPayment\FormOfPayment;
use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Models\Common\StateDetails;
use App\Libraries\Common;
use Log;
use DB;
use URL;

class RedisUpdate 
{
	public static function updateRedisData($requestData)
	{

		//Redis::set('ramu', json_encode($data), 'EX', 60); -- 60=> 1 min
		//Log::info(print_r($requestData->all(),true));
		$actionName = isset($requestData['actionName']) ? $requestData['actionName'] : '';
		
		switch($actionName) {
			
			case 'updatePortalCsMapping':
			
				self::updatePortalCsMapping($requestData);
				break;

			case 'updateAirlineGroup':
				self::updateAirlineGroup($requestData);
				break;

			case 'updateAirportGroup':
				self::updateAirportGroup($requestData);
				break;

			case 'updatePortalAirlineBlocking':
				self::updatePortalAirlineBlocking($requestData);
				break;	
			
			case 'updatePortalRouteBlocking':
				self::updatePortalRouteBlocking($requestData);
				break;

			case 'updatePortalAirlineMasking':
				self::updatePortalAirlineMasking($requestData);
				break;

			case 'updateSupplierAirlineBlocking':
				self::updateSupplierAirlineBlocking($requestData);
				break;	
			
			case 'updateSupplierRouteBlocking':
				self::updateSupplierRouteBlocking($requestData);
				break;

			case 'updateSupplierAirlineMasking':
				self::updateSupplierAirlineMasking($requestData);
				break;

			case 'updateContentSources':
				self::updateContentSources($requestData);
				break;

			case 'updatePortalSurcharge':
				self::updatePortalSurcharge($requestData);
				break;

			case 'updateSupplierSurcharge':
				self::updateSupplierSurcharge($requestData);
				break;	

			case 'updatePortalMarkups':
				self::updatePortalMarkups($requestData);				
				break;

			case 'updateSupplierPosMarkupRules':
				self::updateSupplierPosMarkupRules($requestData);				
				break;

			case 'updateAggregationProfiles':
				self::updateAggregationProfiles($requestData);				
				break;
			case 'updateFormOfPayment':
				self::updateFormOfPayment($requestData);				
				break;
			case 'updateCurrencyExchangeRate':
				self::updateCurrencyExchangeRate($requestData);				
				break;
			case 'updateSectorMapping':
				self::updateSectorMapping($requestData);				
				break;
			case 'updatePortalInfoCredentials':
				self::updatePortalInfoCredentials($requestData);
				break;

			case 'accountAggregationMapping';
				self::updateAccountAggregationMapping($requestData);
				break;

			case 'portalAggregationMapping';
				self::updatePortalAggregationMapping($requestData);
				break;

			case 'updateTicketingRules';
				self::updateTicketingRules($requestData);
				break;

			case 'updateSupplierLowfareTemplate';
				self::updateSupplierLowfareTemplate($requestData);
				break;

			case 'updateQualityCheckTemplate';
				self::updateQualityCheckTemplate($requestData);
				break;

			case 'updateRiskAnalysisTemplate';
				self::updateRiskAnalysisTemplate($requestData);
				break;

			case 'remarkTemplate';
				self::updateRemarkTemplate($requestData);
				break;

			case 'updateUpsaleManagement';
				self::updateUpsaleManagement($requestData);
				break;

			case 'updateSupplierFeeRules';
				self::updateSupplierFeeRules($requestData);
				break;
			case 'updateSeatMapping';
				self::updateSeatMapping($requestData);
				break;

			default:				
				break;
		}
	}
	
	public static function updatePortalCsMapping($requestData)
	{       
		if(isset($requestData['accountId'])){
			
			$accountId 		= $requestData['accountId'];
			$productType 	= $requestData['productType'];
			
			$pcsmObj = DB::table(config('tables.portal_contentsource_mapping').' As pcsm')->select
						(
							'pcsm.portal_csm_id',
							'pcsm.supplier_account_id',
							'pcsm.partner_account_id',
							'pcsm.partner_portal_id',
							'pcsm.pos_template_id',
							'pcsm.content_source_id',
							'pcsm.published_booking_cs_id',
							'pcsm.special_booking_cs_id',
							'pcsm.published_ticketing_cs_id',
							'pcsm.special_ticketing_cs_id',
							'pcsm.supplier_default_markup_template_id',
							'pcsm.supplier_markup_template_id',
							'pcsm.parent_portal_csm_id',
							'pcsm.fare_types',
							'pcsm.aggregaters',
							'pcsm.criterias',
							'spt.template_name as pos_template_name',
							'spt.currency_type as pos_currency_type',
							'sdm.default_markup_name',
							'sdm.markup_json_data as pos_global_markup',
							'pcsm.status'
						)
						->join(config('tables.supplier_pos_templates').' As spt', 'spt.pos_template_id', '=', 'pcsm.pos_template_id')
						->leftjoin(config('tables.supplier_default_markup').' As sdm', function($join) 
						{
							$join->on('sdm.default_markup_id', '=', 'pcsm.supplier_default_markup_template_id')->where('sdm.status', '=', 'A'); 
						});
						
			$pcsmObj->where('pcsm.status','A');			
			$pcsmObj->where('spt.status','A');			
			$pcsmObj->where('pcsm.partner_account_id',$accountId);
			$pcsmObj->where('pcsm.product_type',$productType);
			$pcsmObj->where('spt.product_type',$productType);
			
			$pcsmData		= $pcsmObj->get()->toArray();
			$psCsMapping	= array();
			
			if(count($pcsmData) > 0){
				
				$pcsmData = json_decode(json_encode($pcsmData),true);
				
				for($i=0;$i<count($pcsmData);$i++){
					
					$tempMapping			= $pcsmData[$i];
					$portalCsmId			= $tempMapping['portal_csm_id'];
					$parentPortalCsmId		= $tempMapping['parent_portal_csm_id'];
					
					if(is_null($tempMapping['pos_global_markup']) || empty($tempMapping['pos_global_markup'])){
						$tempMapping['pos_global_markup'] = '{}';
					}
					
					$tempMapping['criterias']				= json_decode($tempMapping['criterias'],true);
					$tempMapping['pos_global_markup']		= json_decode($tempMapping['pos_global_markup'],true);
					$tempMapping['parent_content_source']	= array();
					
					if($parentPortalCsmId != 0){
								
						$checkParentPortalCsmId = $parentPortalCsmId;
						
						while($checkParentPortalCsmId != 0){
							
							$pcsmObjLoop = DB::table(config('tables.portal_contentsource_mapping').' As pcsm')->select
											(
												'pcsm.portal_csm_id',
												'pcsm.supplier_account_id',
												'pcsm.partner_account_id',
												'pcsm.partner_portal_id',
												'pcsm.pos_template_id',
												'pcsm.content_source_id',
												'pcsm.published_booking_cs_id',
												'pcsm.special_booking_cs_id',
												'pcsm.published_ticketing_cs_id',
												'pcsm.special_ticketing_cs_id',
												'pcsm.supplier_default_markup_template_id',
												'pcsm.supplier_markup_template_id',
												'pcsm.parent_portal_csm_id',
												'pcsm.fare_types',
												'pcsm.aggregaters',
												'pcsm.criterias',
												'spt.template_name as pos_template_name',
												'spt.currency_type as pos_currency_type',
												'spt.status as pos_status',
												'sdm.default_markup_name',
												'sdm.markup_json_data as pos_global_markup',
												'pcsm.status'
											)
											->join(config('tables.supplier_pos_templates').' As spt', 'spt.pos_template_id', '=', 'pcsm.pos_template_id')
											->leftjoin(config('tables.supplier_default_markup').' As sdm', function($join) 
											{
												$join->on('sdm.default_markup_id', '=', 'pcsm.supplier_default_markup_template_id')->where('sdm.status', '=', 'A'); 
											});
							
							$pcsmObjLoop->where('pcsm.portal_csm_id',$checkParentPortalCsmId);
							$pcsmObjLoop->where('pcsm.product_type',$productType);							
							$pcsmObjLoop->where('spt.product_type',$productType);
							
							$parentCsmData = $pcsmObjLoop->get()->toArray();
							
							if(count($parentCsmData) > 0){
				
								$parentCsmData = json_decode(json_encode($parentCsmData),true);
								
								if(is_null($parentCsmData[0]['pos_global_markup']) || empty($parentCsmData[0]['pos_global_markup'])){
									$parentCsmData[0]['pos_global_markup'] = '{}';
								}
					
								$parentCsmData[0]['criterias']			= json_decode($parentCsmData[0]['criterias'],true);
								$parentCsmData[0]['pos_global_markup']	= json_decode($parentCsmData[0]['pos_global_markup'],true);
								
								$checkParentPortalCsmId = $parentCsmData[0]['parent_portal_csm_id'];
								
								$tempMapping['parent_content_source'][$parentCsmData[0]['portal_csm_id']]  = $parentCsmData[0];								
							}
							else{
								$checkParentPortalCsmId = 0;
							}
						}
					}
					
					$psCsMapping[$portalCsmId] = $tempMapping;
				}
			}
			
			$redisKeyByProductType 	= config('common.redis_key_by_product_type');
			$redisKey 				= $redisKeyByProductType[$productType];
			if($productType == 'F'){
				Redis::set('portalContentSourceMapping_'.$accountId, json_encode($psCsMapping), 'EX', config('common.redisSetTime'));
			}else{
				Redis::set($redisKey.'PortalContentSourceMapping_'.$accountId, json_encode($psCsMapping), 'EX', config('common.redisSetTime'));
			}
			
			self::updatePortalSuplierMapping($accountId);
		}
	}

	public static function updatePortalSuplierMapping($accountId){

		$csMapping 	= DB::table(config('tables.portal_contentsource_mapping'))
							->select('supplier_account_id', 'parent_portal_csm_id')
							->whereIn('status', ['A'])
							->where('partner_account_id', $accountId)
							->get()->toArray();

		$csMapping 	= json_decode(json_encode($csMapping),true);

		$suppliers 	= array();

		for($i=0;$i<count($csMapping);$i++){

			$supplierAccountId = $csMapping[$i]['supplier_account_id'];

			$parentPortalCsmId = $csMapping[$i]['parent_portal_csm_id'];

			array_push($suppliers, $supplierAccountId);

			if($parentPortalCsmId != 0){

				$checkParentPortalCsmId = $parentPortalCsmId;
				
				while($checkParentPortalCsmId != 0){

					$parentCsmData 	= DB::table(config('tables.portal_contentsource_mapping'))
							->select('supplier_account_id', 'parent_portal_csm_id')
							->where('portal_csm_id', $checkParentPortalCsmId)
							->get()->toArray();

					$parentCsmData 	= json_decode(json_encode($parentCsmData),true);
					
					if(isset($parentCsmData[0]['supplier_account_id'])){
						
						array_push($suppliers, $parentCsmData[0]['supplier_account_id']);

						$checkParentPortalCsmId = $parentCsmData[0]['parent_portal_csm_id'];
					}
					else{
						$checkParentPortalCsmId = 0;
					}
				}
			}
		}

		$suppliers = array_values(array_unique($suppliers));		
		
		Redis::set('portalSupplierMapping_'.$accountId, json_encode($suppliers), 'EX', config('common.redisSetTime'));
		
	}

	public static function updateAirlineGroup($requestData){	

		if(isset($requestData['accountId'])){

			$accountId = $requestData['accountId'];			

			$airlineData = DB::table(config('tables.airline_groups'))->select('*')->where('account_id', $accountId)->whereIn('status', ['A'])->get()->toArray();
			$airlineGroups = array();
			foreach ($airlineData as $value) {
				$airlineGroupId  = $value->airline_group_id;
				$airlineGroups[$airlineGroupId] = $value;
			}
			Redis::set('airlineGroups_'.$accountId, json_encode($airlineGroups), 'EX', config('common.redisSetTime'));
		}
		
	}

	public static function updateAirportGroup($requestData){	

		if(isset($requestData['accountId'])){

			$accountId = $requestData['accountId'];			

			$airportData = DB::table(config('tables.airport_groups'))->select('*')->where('account_id', $accountId)->whereIn('status', ['A'])->get();
			$airportGroups = array();
			foreach ($airportData as $key => $value) {
				$airportGroupId  = $value->airport_group_id;
				$airportGroups[$airportGroupId] = $value;
			}
			Redis::set('airportGroups_'.$accountId, json_encode($airportGroups), 'EX', config('common.redisSetTime'));
		}
		
	}

	public static function updatePortalAirlineBlocking($requestData){	
		if(isset($requestData['accountId'])){
			$accountId = $requestData['accountId'];		

			$airlineBlockingData = DB::table(config('tables.portal_airline_blocking_templates'). ' As pabt')
				->join(config('tables.portal_airline_blocking_rules'). ' As pabr', 'pabr.airline_blocking_template_id', '=', 'pabt.airline_blocking_template_id')
				->select('pabt.airline_blocking_template_id',
						'pabt.account_id',
						'pabt.portal_id',
						'pabt.template_name',
						'pabt.template_type',
						'pabr.airline_blocking_rule_id',
						'pabr.validating_carrier',
						'pabr.public_fare_search',
						'pabr.public_fare_allow_restricted',
						'pabr.public_fare_booking',
						'pabr.private_fare_search',
						'pabr.private_fare_allow_restricted',
						'pabr.private_fare_booking',
						'pabr.block_details',
						'pabr.criterias'
					)
				->where('pabt.account_id', $accountId)
				->whereIn('pabt.status', ['A'])
				->whereIn('pabr.status', ['A'])
				->orderBy('pabt.updated_at', 'DESC')
				->orderBy('pabr.updated_at', 'DESC')
				->get();		

			$portalAirlineBlockingRules = array();
			foreach ($airlineBlockingData as $key => $value) {
				$tempMapping 			= $value;
				$abTemplateId			= $tempMapping->airline_blocking_template_id;
				$abPortalId				= $tempMapping->portal_id;
				$validatingCarrier		= $tempMapping->validating_carrier;				
				$tempMapping->criterias = json_decode($tempMapping->criterias, true);	
				$tempMapping->block_details = json_decode($tempMapping->block_details, true);			
				$abPortalIdArr 			= explode(",", $abPortalId);
				for($j=0;$j<count($abPortalIdArr);$j++){
					$loopPortalId = $abPortalIdArr[$j];
					$temp									= array();
					$temp['airline_blocking_template_id']	= $tempMapping->airline_blocking_template_id;
					$temp['airline_blocking_rule_id']		= $tempMapping->airline_blocking_rule_id;
					$temp['template_name']					= $tempMapping->template_name;
					$temp['template_type']					= $tempMapping->template_type;
					$temp['account_id']						= $tempMapping->account_id;
					$temp['portal_id']						= $tempMapping->portal_id;
					$temp['public_fare_search']				= $tempMapping->public_fare_search;
					$temp['public_fare_allow_restricted']	= $tempMapping->public_fare_allow_restricted;
					$temp['public_fare_booking']			= $tempMapping->public_fare_booking;
					$temp['private_fare_search']			= $tempMapping->private_fare_search;
					$temp['private_fare_allow_restricted']	= $tempMapping->private_fare_allow_restricted;
					$temp['private_fare_booking']			= $tempMapping->private_fare_booking;
					$temp['block_details']					= $tempMapping->block_details;
					$temp['criterias']						= $tempMapping->criterias;
					
					if(!isset($portalAirlineBlockingRules[$loopPortalId][$validatingCarrier][$abTemplateId]['rules'])){
						$portalAirlineBlockingRules[$loopPortalId][$validatingCarrier][$abTemplateId]['rules'] = array();
				}
				array_push($portalAirlineBlockingRules[$loopPortalId][$validatingCarrier][$abTemplateId]['rules'], $temp);
					 
				}				
			}			
			Redis::set('portalAirlineBlockingRules_'.$accountId, json_encode($portalAirlineBlockingRules), 'EX', config('common.redisSetTime'));
		}
		
	}

	public static function updatePortalRouteBlocking($requestData){
		if(isset($requestData['accountId'])){
			$accountId = $requestData['accountId'];		

			$routeBlockingData = DB::table(config('tables.portal_route_blocking_templates'). ' As prbt')
				->join(config('tables.portal_route_blocking_rules'). ' As prbr', 'prbr.route_blocking_template_id', '=', 'prbt.route_blocking_template_id')
				->select('prbt.route_blocking_template_id',
						'prbt.account_id',
						'prbt.portal_id',
						'prbt.template_name',
						'prbt.template_type',
						'prbr.route_blocking_rule_id',
						'prbr.criterias'
					)
				->where('prbt.account_id', $accountId)
				->whereIn('prbt.status', ['A'])
				->whereIn('prbr.status', ['A'])
				->orderBy('prbt.updated_at', 'DESC')
				->orderBy('prbr.updated_at', 'DESC')
				->get();		

			$portalRouteBlockingRules = array();
			foreach ($routeBlockingData as $key => $value) {
				$tempMapping			= $value;
				$rbTemplateId			= $tempMapping->route_blocking_template_id;
				$rbPortalId				= $tempMapping->portal_id;				
				$tempMapping->criterias = json_decode($tempMapping->criterias);				
				$rbPortalIdArr 			= explode(",", $rbPortalId);

				for($j=0;$j<count($rbPortalIdArr);$j++){									
					$loopPortalId 						= $rbPortalIdArr[$j];				
					$temp								= array();
					$temp['route_blocking_template_id']	= $tempMapping->route_blocking_template_id;
					$temp['template_name']				= $tempMapping->template_name;
					$temp['template_type']				= $tempMapping->template_type;
					$temp['account_id']					= $tempMapping->account_id;
					$temp['portal_id']					= $tempMapping->portal_id;
					$temp['criterias']					= $tempMapping->criterias;
					
					if(!isset($portalRouteBlockingRules[$loopPortalId][$rbTemplateId]['rules'])){
						$portalRouteBlockingRules[$loopPortalId][$rbTemplateId]['rules'] = array();
					}

					array_push($portalRouteBlockingRules[$loopPortalId][$rbTemplateId]['rules'], $temp);
				}

			}			
			Redis::set('portalRouteBlockingRules_'.$accountId, json_encode($portalRouteBlockingRules), 'EX', config('common.redisSetTime'));
		}
	}

	public static function updatePortalAirlineMasking($requestData){
		if(isset($requestData['accountId'])){
			$accountId = $requestData['accountId'];		

			$airlineMaskingData = DB::table(config('tables.portal_airline_masking_templates'). ' As pamt')
				->join(config('tables.portal_airline_masking_rules'). ' As pamr', 'pamr.airline_masking_template_id', '=', 'pamt.airline_masking_template_id')
				->select('pamt.airline_masking_template_id',
						'pamt.account_id',
						'pamt.portal_id',
						'pamt.template_name',
						'pamr.airline_masking_rule_id',
						'pamr.airline_code',
						'pamr.mask_airline_code',
						'pamr.mask_airline_name',
						'pamr.mask_validating',
						'pamr.mask_marketing',
						'pamr.mask_operating',
						'pamr.criterias'
					)
				->where('pamt.account_id', $accountId)
				->whereIn('pamt.status', ['A'])
				->whereIn('pamr.status', ['A'])
				->orderBy('pamt.updated_at', 'DESC')
				->orderBy('pamr.updated_at', 'DESC')
				->get();		

			$portalAirlineMaskingRules = array();
			foreach ($airlineMaskingData as $key => $value) {
				$tempMapping 			= $value;
				$amTemplateId			= $tempMapping->airline_masking_template_id;
				$abPortalId				= $tempMapping->portal_id;
				$airlineCode			= $tempMapping->airline_code;				
				$tempMapping->criterias = json_decode($tempMapping->criterias);				
				$abPortalIdArr 			= explode(",", $abPortalId);
				for($j=0;$j<count($abPortalIdArr);$j++){
									
					$loopPortalId = $abPortalIdArr[$j];				
					
					$temp									= array();
					$temp['airline_masking_template_id']	= $tempMapping->airline_masking_template_id;
					$temp['airline_masking_rule_id']		= $tempMapping->airline_masking_rule_id;
					$temp['template_name']					= $tempMapping->template_name;				
					$temp['account_id']						= $tempMapping->account_id;
					$temp['portal_id']						= $tempMapping->portal_id;
					$temp['mask_airline_code']				= $tempMapping->mask_airline_code;
					$temp['mask_airline_name']				= $tempMapping->mask_airline_name;
					$temp['mask_validating']				= $tempMapping->mask_validating;
					$temp['mask_marketing']					= $tempMapping->mask_marketing;
					$temp['mask_operating']					= $tempMapping->mask_operating;
					$temp['criterias']						= $tempMapping->criterias;
					
					if(!isset($portalAirlineMaskingRules[$loopPortalId][$airlineCode][$amTemplateId]['rules'])){
						$portalAirlineMaskingRules[$loopPortalId][$airlineCode][$amTemplateId]['rules'] = array();
					}

					array_push($portalAirlineMaskingRules[$loopPortalId][$airlineCode][$amTemplateId]['rules'], $temp);
				}

			}					
			Redis::set('portalAirlineMaskingRules_'.$accountId, json_encode($portalAirlineMaskingRules), 'EX', config('common.redisSetTime'));
		}		
	}

	public static function updateSupplierAirlineBlocking($requestData){
		if(isset($requestData['accountId'])){
			$accountId = $requestData['accountId'];	

			$airlineBlockingData = DB::table(config('tables.supplier_airline_blocking_templates'). ' As sabt')
				->join(config('tables.supplier_airline_blocking_rules'). ' As sabr', 'sabr.airline_blocking_template_id', '=', 'sabt.airline_blocking_template_id')
				->join(config('tables.supplier_airline_blocking_partner_mapping'). ' As sabtm', 'sabtm.airline_blocking_template_id', '=', 'sabt.airline_blocking_template_id')
				->select('sabt.airline_blocking_template_id',
						'sabt.template_name',
						'sabt.template_type',
						'sabt.account_id',
						'sabr.airline_blocking_rule_id',
						'sabr.validating_carrier',
						'sabr.fare_selection',
						'sabr.block_details',
						'sabr.criterias',
						'sabtm.partner_account_id',
						'sabtm.partner_portal_id'
					)
				->where('sabt.account_id', $accountId)
				->whereIn('sabt.status', ['A'])
				->whereIn('sabr.status', ['A'])
				->orderBy('sabt.updated_at', 'DESC')
				->orderBy('sabr.updated_at', 'DESC')
				->get();		

			$allAbRules = array();
			$checkArr   = array();
			foreach ($airlineBlockingData as $key => $value) {

				$tempMapping 		= $value;
				$validatingCarrier	= $tempMapping->validating_carrier;
				$supplierId			= $tempMapping->account_id;
				$abTemplateId		= $tempMapping->airline_blocking_template_id;
				$abRuleId			= $tempMapping->airline_blocking_rule_id;
				$supplierId			= $tempMapping->account_id;
				$partnerAccountId	= $tempMapping->partner_account_id;
				$partnerPortalId	= $tempMapping->partner_portal_id;

				$partnerPortalIdArr = explode(",", $partnerPortalId);

				for($j=0;$j<count($partnerPortalIdArr);$j++){
									
					$loopPortalId = $partnerPortalIdArr[$j];
					
					$chkKey = $supplierId.'_'.$partnerAccountId.'_'.$loopPortalId.'_'.$abTemplateId.'_'.$abRuleId;
					
					if(!in_array($chkKey, $checkArr)){
						
						$rulesTemp									= array();
						$rulesTemp['account_id']					= $supplierId;
						$rulesTemp['airline_blocking_template_id']	= $tempMapping->airline_blocking_template_id;
						$rulesTemp['template_name']					= $tempMapping->template_name;
						$rulesTemp['template_type']					= $tempMapping->template_type;
						$rulesTemp['partner_account_id']			= $partnerAccountId;
						$rulesTemp['partner_portal_id']				= $loopPortalId;
						$rulesTemp['airline_blocking_rule_id'] 		= $abRuleId;
						$rulesTemp['block_details'] 				= json_decode($tempMapping->block_details, true);
						$rulesTemp['criterias'] 					= json_decode($tempMapping->criterias);
						$rulesTemp['fare_selection'] 				= json_decode($tempMapping->fare_selection);
						
						if(!isset($allAbRules[$partnerAccountId][$loopPortalId][$validatingCarrier][$abTemplateId]['rules'])){
							$allAbRules[$partnerAccountId][$loopPortalId][$validatingCarrier][$abTemplateId]['rules'] = array();
						}
						
						array_push($allAbRules[$partnerAccountId][$loopPortalId][$validatingCarrier][$abTemplateId]['rules'], $rulesTemp);

						array_push($checkArr, $chkKey);
					}
				}
			}			
			Redis::set('supplierAirlineBlockingRules_'.$accountId, json_encode($allAbRules), 'EX', config('common.redisSetTime'));
		}		
	}

	public static function updateSupplierRouteBlocking($requestData){
		if(isset($requestData['accountId'])){
			$accountId = $requestData['accountId'];	

			$routeBlockingData = DB::table(config('tables.supplier_route_blocking_templates'). ' As srbt')
				->join(config('tables.supplier_route_blocking_rules'). ' As srbr', 'srbr.route_blocking_template_id', '=', 'srbt.route_blocking_template_id')
				->join(config('tables.supplier_route_blocking_partner_mapping'). ' As srbpm', 'srbpm.route_blocking_template_id', '=', 'srbt.route_blocking_template_id')
				->select('srbt.route_blocking_template_id',
						'srbt.template_name',
						'srbt.template_type',
						'srbt.account_id',
						'srbr.route_blocking_rule_id',
						'srbr.criterias',
						'srbpm.partner_account_id',
						'srbpm.partner_portal_id'
					)
				->where('srbt.account_id', $accountId)
				->whereIn('srbt.status', ['A'])
				->whereIn('srbr.status', ['A'])
				->orderBy('srbt.updated_at', 'DESC')
				->orderBy('srbr.updated_at', 'DESC')
				->get();		

			$allRbRules = array();
			$checkArr   = array();
			foreach ($routeBlockingData as $key => $value) {
				$tempMapping 		= $value;
				$rbTemplateId		= $tempMapping->route_blocking_template_id;
				$rbRuleId			= $tempMapping->route_blocking_rule_id;
				$supplierId			= $tempMapping->account_id;
				$partnerAccountId	= $tempMapping->partner_account_id;
				$partnerPortalId	= $tempMapping->partner_portal_id;				
				
				$partnerPortalIdArr = explode(",", $partnerPortalId);

				for($j=0;$j<count($partnerPortalIdArr);$j++){
									
					$loopPortalId = $partnerPortalIdArr[$j];
					
					$chkKey = $supplierId.'_'.$partnerAccountId.'_'.$loopPortalId.'_'.$rbTemplateId.'_'.$rbRuleId;
					
					if(!in_array($chkKey, $checkArr)){
						
						$rulesTemp									= array();
						$rulesTemp['account_id']					= $supplierId;
						$rulesTemp['route_blocking_template_id']	= $tempMapping->route_blocking_template_id;
						$rulesTemp['template_name']					= $tempMapping->template_name;
						$rulesTemp['template_type']					= $tempMapping->template_type;
						$rulesTemp['partner_account_id']			= $partnerAccountId;
						$rulesTemp['partner_portal_id']				= $loopPortalId;
						$rulesTemp['route_blocking_rule_id'] 		= $rbRuleId;
						$rulesTemp['criterias'] 					= json_decode($tempMapping->criterias);
						
						if(!isset($allRbRules[$partnerAccountId][$loopPortalId][$rbTemplateId]['rules'])){
							$allRbRules[$partnerAccountId][$loopPortalId][$rbTemplateId]['rules'] = array();
						}
						array_push($allRbRules[$partnerAccountId][$loopPortalId][$rbTemplateId]['rules'], $rulesTemp);
						array_push($checkArr, $chkKey);
					}
				}
				
			}				
			Redis::set('supplierRouteBlockingRules_'.$accountId, json_encode($allRbRules), 'EX', config('common.redisSetTime'));
		}
	}

	public static function updateSupplierAirlineMasking($requestData){
		if(isset($requestData['accountId'])){
			$accountId = $requestData['accountId'];		

			$airlineMaskingData = DB::table(config('tables.supplier_airline_masking_templates'). ' As samt')
				->join(config('tables.supplier_airline_masking_rules'). ' As samr', 'samr.airline_masking_template_id', '=', 'samt.airline_masking_template_id')
				->join(config('tables.supplier_airline_masking_partner_mapping'). ' As sampm', 'sampm.airline_masking_template_id', '=', 'samt.airline_masking_template_id')
				->select('samt.airline_masking_template_id',
						'samt.template_name',
						'samt.account_id',
						'samr.airline_masking_rule_id',
						'samr.airline_code',
						'samr.mask_airline_code',
						'samr.mask_airline_name',
						'samr.mask_validating',
						'samr.mask_marketing',
						'samr.mask_operating',
						'samr.criterias',
						'sampm.partner_account_id',
						'sampm.partner_portal_id'
					)
				->where('samt.account_id', $accountId)
				->whereIn('samt.status', ['A'])
				->whereIn('samr.status', ['A'])
				->orderBy('samt.updated_at', 'DESC')
				->orderBy('samr.updated_at', 'DESC')
				->get();		

			$allAmRules = array();
			$checkArr 	= array();
			foreach ($airlineMaskingData as $key => $value) {
				$tempMapping 			= $value;
				$airlineCode			= $tempMapping->airline_code;
				$supplierId				= $tempMapping->account_id;
				$amTemplateId			= $tempMapping->airline_masking_template_id;
				$amRuleId				= $tempMapping->airline_masking_rule_id;
				$supplierId				= $tempMapping->account_id;
				$partnerAccountId		= $tempMapping->partner_account_id;
				$partnerPortalId		= $tempMapping->partner_portal_id;
				
				$partnerPortalIdArr 	= explode(",", $partnerPortalId);

				for($j=0;$j<count($partnerPortalIdArr);$j++){
									
					$loopPortalId = $partnerPortalIdArr[$j];				
					
					$chkKey = $supplierId.'_'.$partnerAccountId.'_'.$loopPortalId.'_'.$amTemplateId.'_'.$amRuleId;
					
					if(!in_array($chkKey, $checkArr)){
						
						$rulesTemp									= array();
						$rulesTemp['account_id']					= $supplierId;
						$rulesTemp['airline_masking_template_id']	= $tempMapping->airline_masking_template_id;
						$rulesTemp['template_name']					= $tempMapping->template_name;		
						$rulesTemp['partner_account_id']			= $partnerAccountId;
						$rulesTemp['partner_portal_id']				= $loopPortalId;
						$rulesTemp['airline_masking_rule_id'] 		= $amRuleId;
						$rulesTemp['criterias'] 					= json_decode($tempMapping->criterias);
						$rulesTemp['mask_airline_code']				= $tempMapping->mask_airline_code;
						$rulesTemp['mask_airline_name']				= $tempMapping->mask_airline_name;
						$rulesTemp['mask_validating']				= $tempMapping->mask_validating;
						$rulesTemp['mask_marketing']				= $tempMapping->mask_marketing;
						$rulesTemp['mask_operating']				= $tempMapping->mask_operating;
						
						if(!isset($allAmRules[$partnerAccountId][$loopPortalId][$airlineCode][$amTemplateId]['rules'])){
							$allAmRules[$partnerAccountId][$loopPortalId][$airlineCode][$amTemplateId]['rules'] = array();
						}

						array_push($allAmRules[$partnerAccountId][$loopPortalId][$airlineCode][$amTemplateId]['rules'], $rulesTemp);
					
						array_push($checkArr, $chkKey);
					}
				}
			}			
			Redis::set('supplierAirlineMaskingRules_'.$accountId, json_encode($allAmRules), 'EX', config('common.redisSetTime'));
		}
	}

	public static function updateContentSources($requestData){
		if(isset($requestData['accountId'])){
			$accountId 		= $requestData['accountId'];				

			$getCsData = DB::table(config('tables.content_source_details').' As csd')
				->join(config('tables.content_source_api_credential').' As csac', 'csd.content_source_id', '=', 'csac.content_source_id')
				->join(config('tables.supplier_products').' As sp', 'csd.content_source_id', '=', 'sp.content_source_id')
				->select('csd.*', 'csac.credentials', 'sp.fare_types', 'sp.aggregaters', 'sp.services')
				->where('csd.account_id', $accountId)
				->whereIn('csd.status', ['A'])
				->orderBy('csd.updated_at', 'DESC')
				->get()->toArray();

			$allContentSource = array();
			foreach ($getCsData as $value) {
				$contentSourceId	= $value->content_source_id;

				$sessKey = 'sabreSessionKey_XML_'.$contentSourceId;
                
                Redis::del($sessKey);
                
                $sessKey = 'sabreSessionKey_JSON_'.$contentSourceId;
                
                Redis::del($sessKey);
                
                $sessKey = 'tboSessionKey_'.$contentSourceId;
                
                Redis::del($sessKey);
					
				if(isset($value->credentials) && $value->credentials == ''){
                    $value->credentials = '{}';
                }					
				$allContentSource[$contentSourceId] = $value;
			}
			Redis::set('contentSources_'.$accountId, json_encode($allContentSource), 'EX', config('common.redisSetTime'));			
		}	
		
	}

	public static function updatePortalSurcharge($requestData){
		if(isset($requestData['accountId'])){
			$accountId 		= $requestData['accountId'];	
			$productType 	= $requestData['productType'];
			$getPortalSurchargeData = DB::table(config('tables.portal_surcharge_details'))
				->select('surcharge_id',
						'account_id',
						'surcharge_name',
						'surcharge_code',
						'surcharge_type',
						'currency_type',
						'calculation_on',
						'surcharge_amount',
						'criterias'
						)
				->where('account_id', $accountId)
				->where('product_type', $productType)
				->whereIn('status', ['A'])
				->orderBy('updated_at', 'DESC')
				->get()->toArray();

			$portalSurcharge = array();
			foreach ($getPortalSurchargeData as $key => $value) {
				$tempMapping 						= $value;
				$indexVal							= $tempMapping->surcharge_id;				
				$tempMapping->surcharge_amount 		= ($tempMapping->surcharge_amount != '') ? json_decode($tempMapping->surcharge_amount) : '{}';
				$tempMapping->criterias 		 	= json_decode($tempMapping->criterias);				
				$portalSurcharge[$indexVal] 		= $tempMapping;
			}

			$redisKeyByProductType 	= config('common.redis_key_by_product_type');
			$redisKey 				= $redisKeyByProductType[$productType];				
			if($productType == 'F'){
				Redis::set('portalSurcharge_'.$accountId, json_encode($portalSurcharge), 'EX', config('common.redisSetTime'));
			}else{
				Redis::set($redisKey.'PortalSurcharge_'.$accountId, json_encode($portalSurcharge), 'EX', config('common.redisSetTime'));				
			}			
			
		}	

	}

	public static function updateSupplierSurcharge($requestData){
		if(isset($requestData['accountId'])){
			$accountId 		= $requestData['accountId'];
			$productType 	= $requestData['productType'];	

			$getSupplierSurchargeData = DB::table(config('tables.supplier_surcharge_details'))
				->select('surcharge_id',
						'account_id',
						'product_type',
						'surcharge_name',
						'surcharge_code',
						'surcharge_type',
						'currency_type',
						'calculation_on',
						'surcharge_amount',
						'criterias'
						)
				->where('account_id', $accountId)
				->whereIn('status', ['A'])
				->orderBy('updated_at', 'DESC')
				->get();
		}	
		$allSurcharge = array();
		foreach ($getSupplierSurchargeData as $key => $value) {
			$tempMapping 							= $value;							
			$indexVal								= $tempMapping->surcharge_id;			
			$tempMapping->surcharge_amount 			= ($tempMapping->surcharge_amount != '') ? json_decode($tempMapping->surcharge_amount) : '{}';
			$tempMapping->criterias 				= json_decode($tempMapping->criterias);				
			$allSurcharge[$indexVal] 	= $tempMapping;
		}

		Redis::set('supplierSurcharge_'.$accountId, json_encode($allSurcharge), 'EX', config('common.redisSetTime'));		
	}

	public static function updatePortalMarkups($requestData){
		if(isset($requestData['accountId'])){
			$accountId 		= $requestData['accountId'];
			$productType 	= $requestData['productType'];

			$getPortalMarkupData = DB::table(config('tables.portal_markup_templates').' As pmt')
				->join(config('tables.portal_markup_rules').' As pmr', 'pmr.markup_template_id', '=', 'pmt.markup_template_id')
				->leftJoin(config('tables.portal_markup_rule_surcharges').' As pmrs', 'pmrs.markup_rule_id', '=', 'pmr.markup_rule_id')
				->select('pmt.markup_template_id',
						 'pmt.account_id',
						 'pmt.product_type',
						 'pmt.portal_id',
						 'pmt.template_name',
						 'pmt.currency_type',
						 'pmr.markup_rule_id',
						 'pmr.rule_name',
						 'pmr.rule_code',
						 'pmr.rule_type',
						 'pmr.trip_type',
						 'pmr.fare_type',
						 'pmr.calculation_on',
						 'pmr.markup_details',
						 'pmr.criterias',
						 'pmr.updated_at',
						 'pmr.rule_group',
						 'pmrs.surcharge_id')
				->where('pmt.account_id', $accountId)
				->where('pmt.product_type', $productType)
				->whereIn('pmt.status', ['A'])
				->whereIn('pmr.status', ['A'])
				->orderBy('pmr.updated_at', 'DESC')
				->orderBy('pmt.updated_at', 'DESC')
				->get()->toArray();

				$portalMarkups = array();
				$surchargeChk  = array();

				foreach ($getPortalMarkupData as $key => $value) {
					$temp = $value;
									
					$templateId		= 'MT_'.$temp->markup_template_id;
					$temPortalId	= $temp->portal_id;
					$markupRuleId	= 'MR_'.$temp->markup_rule_id;
					$ruleType		= $temp->rule_type;
					$tripType		= $temp->trip_type;
					$fareType		= $temp->fare_type;
					$portalUserGroup= $temp->rule_group;
					
					$temp->criterias = json_decode($temp->criterias);
					
					if($temp->surcharge_id == '' || $temp->surcharge_id == null){
						$temp->surcharge_id = 0;
					}
						
					if($temp->markup_details != ''){
						$temp->markup_details = json_decode($temp->markup_details);
					}
					else{
						$temp->markup_details = '{}';
					}						
				
					$temPortalIdArr	= explode(",", $temPortalId);					

					for($j=0;$j<count($temPortalIdArr);$j++){
										
						$loopPortalId = $temPortalIdArr[$j];

						if(!isset($portalMarkups[$loopPortalId])){
							$portalMarkups[$loopPortalId] = array();
						}
						
						if(!isset($portalMarkups[$loopPortalId][$templateId])){
							$portalMarkups[$loopPortalId][$templateId] = array();
						}						
						
						if(!isset($portalMarkups[$loopPortalId][$templateId][$tripType])){
							$portalMarkups[$loopPortalId][$templateId][$tripType] = array();
						}
						
						if(!isset($portalMarkups[$loopPortalId][$templateId][$tripType][$fareType])){
							$portalMarkups[$loopPortalId][$templateId][$tripType][$fareType] = array();
						}

						if(!isset($portalMarkups[$loopPortalId][$templateId][$tripType][$fareType][$portalUserGroup])){
							$portalMarkups[$loopPortalId][$templateId][$tripType][$fareType][$portalUserGroup] = array();
						}
						
						if(!isset($portalMarkups[$loopPortalId][$templateId][$tripType][$fareType][$portalUserGroup][$markupRuleId])){
							$portalMarkups[$loopPortalId][$templateId][$tripType][$fareType][$portalUserGroup][$markupRuleId]				  = $temp;
							$portalMarkups[$loopPortalId][$templateId][$tripType][$fareType][$portalUserGroup][$markupRuleId]->surcharges = array();
						}
						
						$checkIndex 		= $loopPortalId.'.'.$templateId.'.'.$tripType.'.'.$fareType.'.'.$portalUserGroup.'.'.$markupRuleId;
						$surChargeChkIdx 	= $checkIndex.'.'.$temp->surcharge_id;
						
						if($temp->surcharge_id != 0 && (!in_array($surChargeChkIdx,$surchargeChk))){
							array_push($portalMarkups[$loopPortalId][$templateId][$tripType][$fareType][$portalUserGroup][$markupRuleId]->surcharges, $temp->surcharge_id);
						}						
						
						array_push($surchargeChk, $surChargeChkIdx);
					}
				}

				$portalMarkups = (object)$portalMarkups;
				
				$redisKeyByProductType 	= config('common.redis_key_by_product_type');
				$redisKey 				= $redisKeyByProductType[$productType];
				if($productType == 'F'){					
					Redis::set('portalMarkups_'.$accountId, json_encode($portalMarkups), 'EX', config('common.redisSetTime'));
				}else{
					Redis::set($redisKey.'PortalMarkups_'.$accountId, json_encode($portalMarkups), 'EX', config('common.redisSetTime'));
				}
				
		}
		
	}

	public static function updateSupplierPosMarkupRules($requestData){

		if(isset($requestData['accountId'])){
			$accountId 		= $requestData['accountId'];
			$productType 	= $requestData['productType'];	

			//delete disabled markup template from redis data
			$getDisableMarkupData  = DB::table(config('tables.supplier_markup_templates'))->select('markup_template_id')->where('account_id', $accountId)->get();
			if(count($getDisableMarkupData) > 0){
				foreach ($getDisableMarkupData as $disaledData) {
					$disabledMarkupTemplateId 	= $disaledData->markup_template_id;
					Redis::del('supplierPosMarkupRules_'.$disabledMarkupTemplateId);
				}
			}		

			$getSupplierMarkupData = DB::table(config('tables.supplier_markup_templates'). ' As smt')
				->leftJoin(config('tables.supplier_markup_contracts'). ' As smc', function($join){
		            $join->on('smc.markup_template_id', '=', 'smt.markup_template_id')
		            	 ->where('smc.status', '=', 'A'); 
		        })
	            ->leftJoin(config('tables.supplier_markup_rules'). ' As smr', function($join){
		            $join->on('smr.markup_template_id', '=', 'smt.markup_template_id')
		            	 ->on('smr.markup_contract_id', '=', 'smc.markup_contract_id')
		            	 ->where('smr.status', '=', 'A');
		        })
	            ->leftJoin(config('tables.supplier_pos_contracts'). ' As spc', function($join){
		            $join->on('spc.pos_contract_id', '=', 'smc.pos_contract_id')
		            	 ->where('spc.status', '=', 'A');
		        })
	            ->leftJoin(config('tables.supplier_pos_rules'). ' As spr', function($join){
		            $join->on('spr.pos_rule_id', '=', 'smr.pos_rule_id')
		            	 ->on('spr.pos_contract_id', '=', 'spc.pos_contract_id');
		        })
		        ->select('smt.markup_template_id',
						'smt.template_name as markup_template_name',
						'smt.default_markup_json_data',
						'smt.surcharge_ids as default_surcharge_ids',
						'smt.priority',
						'smt.currency_type as smt_currency_type',
						'smt.product_type',
						'smt.updated_at as smt_updated_at',
						'smc.markup_contract_id',
						'smc.pos_contract_id',
						'smc.markup_contract_name',
						'smc.markup_contract_code',
						'smc.validating_carrier',
						'smc.fare_type',
						'smc.trip_type',
						'smc.rule_type',
						'smc.contract_remarks',
						'smc.calculation_on',
						'smc.criterias as markup_contact_criterias',
						'smr.markup_rule_id',
						'smr.pos_rule_id',
						'smr.rule_name',
						'smr.rule_code',
						'smr.trip_type as rule_trip_type',
						'smr.route_info',
						'smr.markup_details',
						'smr.fare_comparission',
						'smr.agency_commision',
						'smr.agency_yq_commision',
						'smr.override_rule_info',
						'smr.segment_benefit',
						'smr.segment_benefit_percentage',
						'smr.segment_benefit_fixed',
						'smr.criterias',
						'smr.surcharge_id',
						'smr.fop_details',
						'smr.updated_at as smr_updated_at',
						'smr.rule_group as rule_group',
						'smr.rule_type as smr_rule_type',
						'smr.calculation_on as smr_calculation_on',
						'spc.pos_contract_name',
						'spc.pos_contract_code',
						'spc.status as pos_contract_status',
						'spc.contract_remarks as pos_contract_remarks',
						'spc.validating_carrier as pos_contract_validating_carrier',	
						'spc.fare_type as pos_contract_fare_type',	
						'spc.rule_type as pos_contract_rule_type',	
						'spc.trip_type as pos_contract_trip_type',	
						'spc.calculation_on as pos_contract_calculation_on',	
						'spc.segment_benefit as pos_contract_segment_benefit',	
						'spc.segment_benefit_percentage as pos_contract_segment_benefit_percentage',	
						'spc.segment_benefit_fixed as pos_contract_segment_benefit_fixed',	
						'spc.criterias as pos_contract_criterias',
						'spr.rule_name as pos_rule_name',
						'spr.rule_code as pos_rule_code',
						'spr.status as pos_rule_status',
						'spr.rule_type as pos_rule_rule_type',
						'spr.trip_type as pos_rule_trip_type',
						'spr.route_info as pos_rule_route_info',
						'spr.airline_commission as pos_rule_airline_commission',
						'spr.airline_yq_commision as pos_rule_airline_yq_commision',
						'spr.criterias as pos_rule_criterias',
						'spr.fop_details as pos_fop_details'
						
	            	)
		        ->where('smt.account_id', $accountId)
				->whereIn('smt.status', ['A'])
				/*->where(function($subCon1) {
			          $subCon1->whereNull('spr.status')
			            ->orWhereIn('spr.status', ['A']);
			    })
				->where(function($subCon2) {
			          $subCon2->whereNull('spc.status')
			            ->orWhereIn('spc.status', ['A']);
			    })*/
				->orderBy('smr.updated_at', 'DESC')
				->orderBy('smc.updated_at', 'DESC')
				->orderBy('smt.updated_at', 'DESC')
				->get()->toArray();
				
			$allPosTemplateRules = array();
			foreach ($getSupplierMarkupData as $key => $value) {
				$tempMapping 		= $value;
				$markupTemplateId 	= $tempMapping->markup_template_id;
						
				if($tempMapping->product_type == 'F'){
					//$tempMapping->rule_name = $tempMapping->markup_contract_name;
					//$tempMapping->rule_code = $tempMapping->markup_contract_code;
				}							
				
				if($tempMapping->pos_contract_status != 'A' || $tempMapping->pos_rule_status != 'A'){
						
					$tempMapping->pos_contract_id 		= 0;
					$tempMapping->pos_contract_name 	= '';
					$tempMapping->pos_contract_code 	= '';
					$tempMapping->pos_rule_name 		= '';
					$tempMapping->pos_rule_code 		= '';
					$tempMapping->pos_contract_remarks	= '';
					$tempMapping->pos_fop_details		= '';

					$tempMapping->pos_contract_validating_carrier			= '';
					$tempMapping->pos_contract_fare_type					= '';
					$tempMapping->pos_contract_trip_type					= '';
					$tempMapping->pos_contract_rule_type					= '';
					$tempMapping->pos_contract_calculation_on				= '';
					$tempMapping->pos_contract_segment_benefit				= '';
					$tempMapping->pos_contract_segment_benefit_percentage	= '';
					$tempMapping->pos_contract_segment_benefit_fixed		= '';
					$tempMapping->pos_contract_criterias					= '';
					$tempMapping->pos_rule_rule_type						= '';
					$tempMapping->pos_rule_trip_type						= '';
					$tempMapping->pos_rule_route_info						= '';
					$tempMapping->pos_rule_airline_commission				= '';
					$tempMapping->pos_rule_airline_yq_commision				= '';
					$tempMapping->pos_rule_criterias						= '';	


					if($tempMapping->pos_rule_id != null && $tempMapping->pos_rule_id != ''){
						$tempMapping->markup_rule_id = 0;
					}
					
					$tempMapping->pos_rule_id			= 0;
				}

				$tempMapping->pos_contract_remarks			= ($tempMapping->pos_contract_remarks != null && $tempMapping->pos_contract_remarks != '') ? json_decode($tempMapping->pos_contract_remarks) : '{}';	

				$tempMapping->pos_fop_details				= ($tempMapping->pos_fop_details != null && $tempMapping->pos_fop_details != '') ? json_decode($tempMapping->pos_fop_details) : [];

				$tempMapping->surcharges				= ($tempMapping->surcharge_id != '' && $tempMapping->surcharge_id != null) ? explode(',', $tempMapping->surcharge_id) : [];

				$tempMapping->markup_details			= ($tempMapping->markup_details != '' && $tempMapping->markup_details != null) ? json_decode($tempMapping->markup_details) : '{}';

				$tempMapping->default_markup_json_data	= ($tempMapping->default_markup_json_data != '' && $tempMapping->default_markup_json_data != null) ? json_decode($tempMapping->default_markup_json_data) : '{}';

				$tempMapping->default_surcharge_ids		= ($tempMapping->default_surcharge_ids != '' && $tempMapping->default_surcharge_ids != null) ? explode(',', $tempMapping->default_surcharge_ids) : [];

				$tempMapping->fare_comparission			= ($tempMapping->fare_comparission != '' && $tempMapping->fare_comparission != null) ? json_decode($tempMapping->fare_comparission) : '{}';

				$tempMapping->agency_commision			= ($tempMapping->agency_commision != '' && $tempMapping->agency_commision != null) ? json_decode($tempMapping->agency_commision) : '{}';

				$tempMapping->agency_yq_commision		= ($tempMapping->agency_yq_commision != '' && $tempMapping->agency_yq_commision != null) ? json_decode($tempMapping->agency_yq_commision) : '{}';

				$tempMapping->override_rule_info		= ($tempMapping->override_rule_info != '' && $tempMapping->override_rule_info != null) ? json_decode($tempMapping->override_rule_info) : '{}';

				$tempMapping->contract_remarks			= ($tempMapping->contract_remarks != '' && $tempMapping->contract_remarks != null) ? json_decode($tempMapping->contract_remarks) : '{}';

				$tempMapping->fop_details				= ($tempMapping->fop_details != '' && $tempMapping->fop_details != null) ? json_decode($tempMapping->fop_details) : [];

				$tempMapping->route_info				= ($tempMapping->route_info != '' && $tempMapping->route_info != null) ? json_decode($tempMapping->route_info) : [];
				
				$tempMapping->markup_contact_criterias 	= ($tempMapping->markup_contact_criterias != '' && $tempMapping->markup_contact_criterias != null) ? json_decode($tempMapping->markup_contact_criterias) : [];

				$tempMapping->criterias 				= ($tempMapping->criterias != '' && $tempMapping->criterias != null) ? json_decode($tempMapping->criterias) : [];

				$tempMapping->pos_contract_criterias 	= ($tempMapping->pos_contract_criterias != '' && $tempMapping->pos_contract_criterias != null) ? json_decode($tempMapping->pos_contract_criterias) : [];								
				$tempMapping->pos_rule_route_info 		= ($tempMapping->pos_rule_route_info != '' && $tempMapping->pos_rule_route_info != null) ? json_decode($tempMapping->pos_rule_route_info) : [];	

				$tempMapping->pos_rule_airline_commission 	= ($tempMapping->pos_rule_airline_commission != '' && $tempMapping->pos_rule_airline_commission != null) ? json_decode($tempMapping->pos_rule_airline_commission) : [];

				$tempMapping->pos_rule_airline_yq_commision = ($tempMapping->pos_rule_airline_yq_commision != '' && $tempMapping->pos_rule_airline_yq_commision != null) ? json_decode($tempMapping->pos_rule_airline_yq_commision) : [];

				$tempMapping->pos_rule_criterias 			= ($tempMapping->pos_rule_criterias != '' && $tempMapping->pos_rule_criterias != null) ? json_decode($tempMapping->pos_rule_criterias) : [];


				if(!isset($allPosTemplateRules[$markupTemplateId])){
								
					$allPosTemplateRules[$markupTemplateId] = [];
					$allPosTemplateRules[$markupTemplateId]['markup_template_id']		= $tempMapping->markup_template_id;
					$allPosTemplateRules[$markupTemplateId]['template_name']			= $tempMapping->markup_template_name;
					$allPosTemplateRules[$markupTemplateId]['default_markup_json_data'] = $tempMapping->default_markup_json_data;
					$allPosTemplateRules[$markupTemplateId]['default_surcharge_ids'] 	= $tempMapping->default_surcharge_ids;
					$allPosTemplateRules[$markupTemplateId]['priority'] 				= floatval($tempMapping->priority);
					$allPosTemplateRules[$markupTemplateId]['smt_currency_type'] 		= $tempMapping->smt_currency_type;
					$allPosTemplateRules[$markupTemplateId]['smt_updated_at'] 			= $tempMapping->smt_updated_at;
					$allPosTemplateRules[$markupTemplateId]['rules'] 					= [];
					
					unset($tempMapping->default_markup_json_data);
					unset($tempMapping->default_surcharge_ids);
				}


				if($tempMapping->markup_rule_id != '' && $tempMapping->markup_rule_id != null){

					array_push($allPosTemplateRules[$markupTemplateId]['rules'], $tempMapping);
				}										

			}

			foreach ($allPosTemplateRules as $loopKey => $loopVal) { //loopkey is markuptemplateid
				
				Redis::set('supplierPosMarkupRules_'.$loopKey, json_encode($loopVal), 'EX', config('common.redisSetTime'));						
			}					
			
		}
	}

	public static function updateAggregationProfiles($requestData){		
		if(isset($requestData['accountId'])){
			$accountId 	= $requestData['accountId'];

			$getAllAggData = DB::table(config('tables.profile_aggregation'))
			        ->select("profile_aggregation_id")->where('account_id', $accountId)->get()->toArray();

			$getAllAggData 	= json_decode(json_encode($getAllAggData), true);
			foreach ($getAllAggData as $aggVal) {
				Redis::del('aggregationData_'.$aggVal['profile_aggregation_id']);
			}


			$getAggregationProfileData = DB::table(config('tables.profile_aggregation'). ' As pa')
				->Join(config('tables.profile_aggregation_contentsource'). ' As pac', function($join){
		            $join->on('pac.profile_aggregation_id', '=', 'pa.profile_aggregation_id')
		            	 ->whereIn('pac.status', ['A']);
		        })
		        ->select("pa.profile_aggregation_id",
						"pa.account_id",
						"pa.profile_name",
						"pa.low_fare_type",
						"pa.criterias as ag_criterias",
						"pa.product_type",
						"pac.profile_aggregation_cs_id",
						"pac.searching",
						"pac.content_type",
						"pac.booking_public",
						"pac.booking_private",
						"pac.ticketing_public",
						"pac.ticketing_private",
						"pac.currency_type",							
						"pac.markup_template_id",
						"pac.fare_types",
						"pac.market_info",
						"pac.criterias as cs_criterias",
						"pac.shopping_type"
		        )
		        ->where('pa.account_id', $accountId)
				->whereIn('pa.status', ['A'])					
				->orderBy('pa.profile_aggregation_id', 'ASC')
				->get()->toArray();

			$profiles = array();

			foreach ($getAggregationProfileData as $key => $value) {

				$profileAggregationId 	= $value->profile_aggregation_id;	

				$value->market_info 	= json_decode($value->market_info);

				if(!isset($profiles[$profileAggregationId])){
					$profiles[$profileAggregationId] 	= [];
				}

				array_push($profiles[$profileAggregationId], $value);
			}

			foreach($profiles as $tempAggId => $aggData){
				Redis::set('aggregationData_'.$tempAggId, json_encode($aggData), 'EX', config('common.redisSetTime'));
			}
		}
		
	}

	public static function updateFormOfPayment($requestData){

		if(isset($requestData['accountId'])){
			$accountId = $requestData['accountId'];
			$fopDetails = FormOfPayment::where('account_id', $accountId)->where('status', 'A')->get();
			$fopArray = [];
			$fopArray[$accountId] = [];
			if($fopDetails){
				foreach ($fopDetails as $key => $fopDetails) {
					$fopDetails['fop_details'] = json_decode($fopDetails['fop_details']);

					if(!isset($fopArray[$accountId][$fopDetails['content_source_id']])){
						$fopArray[$accountId][$fopDetails['content_source_id']] = [];
					}

					$consumerIds = explode(',', $fopDetails['consumer_account_id']);
					foreach ($consumerIds as $idx => $consumerId) {

						if(!isset($fopArray[$accountId][$fopDetails['content_source_id']][$consumerId])){
							$fopArray[$accountId][$fopDetails['content_source_id']][$consumerId] = [];
						}

						$validatingAirline = explode(',', $fopDetails['validating_airline']);
						foreach ($validatingAirline as $airIdx => $airlineCode) {
							if(!isset($fopArray[$accountId][$fopDetails['content_source_id']][$consumerId][$airlineCode])){
								$fopArray[$accountId][$fopDetails['content_source_id']][$consumerId][$airlineCode] = $fopDetails['fop_details'];
							}else{
								$fopArray[$accountId][$fopDetails['content_source_id']][$consumerId][$airlineCode] = $fopDetails['fop_details'];
							}

						}
					}
				}
			}
		}
		
		Redis::del('supplierFopRules_'.$accountId);
		
		if(count($fopArray[$accountId]) > 0){
			Redis::set('supplierFopRules_'.$accountId, json_encode($fopArray[$accountId]), 'EX', config('common.redisSetTime'));
		}
	}

	public static function updateCurrencyExchangeRate($requestData){

		$accountId = $requestData['accountId'];
		$exchangeRateDetails = CurrencyExchangeRate::whereIn('supplier_account_id', [$accountId,0])->where('status', 'A')->where('type','AS')->where('portal_id',0)->get();
		$exchangeDetails = [];
		
		Redis::del('exchangeRates_'.$accountId);
		Redis::del('exchangeRates_0');

		if($exchangeRateDetails){
			foreach ($exchangeRateDetails as $key => $exchangeData) {
				$supplierId			= $exchangeData['supplier_account_id'];
				$consumerAccountId	= str_replace(',', '_', $exchangeData['consumer_account_id']);
				$fromCurrency		= $exchangeData['exchange_rate_from_currency'];
				$toCurrency			= $exchangeData['exchange_rate_to_currency'];
				$currencyKey		= $fromCurrency.'_'.$toCurrency;

				$exchangeRateEquivalentValue= $exchangeData['exchange_rate_equivalent_value'];
				$exchangeRatePercentage		= $exchangeData['exchange_rate_percentage'];
				$exchangeRateFixed			= $exchangeData['exchange_rate_fixed'];

				$finalExchangeRate	= ($exchangeRateEquivalentValue + ($exchangeRateEquivalentValue*($exchangeRatePercentage/100)) + $exchangeRateFixed);

				$exchageRateArray = [];
				$exchageRateArray['exchange_rate_equivalent_value']	= $exchangeRateEquivalentValue;
				$exchageRateArray['exchange_rate_percentage']		= $exchangeRatePercentage;
				$exchageRateArray['exchange_rate_fixed']			= $exchangeRateFixed;
				$exchageRateArray['exchange_rate']					= $finalExchangeRate;
				
				$exchangeDetails[$supplierId][$currencyKey][$consumerAccountId] = $exchageRateArray;				
			}
			foreach ($exchangeDetails as $supplierId => $exchangeDataVal) {
				Redis::set('exchangeRates_'.$supplierId, json_encode($exchangeDataVal), 'EX', config('common.redisSetTime'));
			}
		}
	}

	public static function updateSectorMapping($requestData)
	{
		if(isset($requestData['accountId'])){
			$csKey = $requestData['accountId'];	// Content source ID assigned to account ID in common Controller
			$sectorMapping = DB::table(config('tables.sector_mapping'))->where('content_source',$csKey)->whereIn('status',['A'])->get();	
			$sectorMappingRedis = [];
			foreach ($sectorMapping as $key => $value) {
				$tempData = [];
				$tempData['origin'] = $value->origin;
				$tempData['destination'] = $value->destination;
				$tempData['airline'] = $value->airline;
				$tempData['content_source'] = $value->content_source;
				$tempData['currency'] = $value->currency;
				array_push($sectorMappingRedis, $tempData);
			}			
			Redis::set('sectorMapping_'.$csKey, json_encode($sectorMappingRedis), 'EX', config('common.redisSetTimeForSM'));
		}	
	}

	public static function updatePortalInfoCredentials($requestData){
		if(isset($requestData['accountId'])){

			$moduleType 	= '';
			if(isset($requestData['moduleType']) && $requestData['moduleType'] != ''){
				$moduleType = $requestData['moduleType'];
			}			
			
			//Delete existing data
			$getCredentials = DB::table(config('tables.portal_details'). ' As pd')
					->join(config('tables.account_details'). ' As ad', 'ad.account_id', '=', 'pd.account_id')
					->join(config('tables.portal_credentials'). ' As pcd', 'pcd.portal_id', '=', 'pd.portal_id');

					if($moduleType == 'account_details'){//account_id
						$getCredentials 	= $getCredentials->where('ad.account_id', $requestData['accountId']);
					}else if($moduleType == 'portal_details'){//portal_id
						$getCredentials 	= $getCredentials->where('pd.portal_id', $requestData['accountId']);
					}else if($moduleType == 'portal_credentials'){//portal_credential_id
						$getCredentials 	= $getCredentials->where('pcd.portal_credential_id', $requestData['accountId']);
					}

            $getCredentials 	= $getCredentials->get()->toArray();
			$getCredentials 	= json_decode(json_encode($getCredentials), true);
			if(count($getCredentials) > 0){
				foreach ($getCredentials as $crendentialVal) {
					Redis::del('portalInfoCredentials_'.$crendentialVal['auth_key']);
				}
			}			

			//Set New Redis Data
			$getPortalDetailsData = DB::table(config('tables.portal_details'). ' As pd')
					->join(config('tables.account_details'). ' As ad', 'ad.account_id', '=', 'pd.account_id')
					->join(config('tables.portal_credentials'). ' As pcd', 'pcd.portal_id', '=', 'pd.portal_id')      
		           	->leftJoin(config('tables.state_details'). ' As sd', 'sd.state_id', '=', 'ad.agency_state')
		           	->leftJoin(config('tables.country_details'). ' As cd', 'cd.country_code', '=', 'ad.agency_country')
		           	->select('ad.account_id',
							'ad.parent_account_id',
							'ad.account_name',
							'ad.status as account_status',
							'ad.agency_address1 as ag_agency_address1',
							'ad.agency_address2 as ag_agency_address2',
							'ad.agency_city as ag_agency_city',
							'ad.agency_state as ag_agency_state',
							'ad.agency_country as ag_agency_country',
							'ad.agency_pincode as ag_agency_pincode',
							'pd.portal_id',
							'pd.account_id',
							'pd.parent_portal_id',
							'pd.portal_name',
							'pd.portal_short_name',
							'pd.prime_country',
							'pd.business_type',
							'pd.portal_default_currency',
							'pd.portal_selling_currencies',
							'pd.portal_settlement_currencies',
							'pd.notification_url',
							'pd.mrms_notification_url',
							'pd.portal_notify_url',
							'pd.ptr_lniata',
							'pd.dk_number',
							'pd.default_queue_no',
							'pd.card_payment_queue_no',
							'pd.cheque_payment_queue_no',
							'pd.pay_later_queue_no',
							'pd.misc_bcc_email',
							'pd.booking_bcc_email',
							'pd.ticketing_bcc_email',
							'pd.agency_email',
							'pd.agency_contact_email',
							'pd.product_rsource',
							'pd.is_meta_search',
							'pd.max_itins_meta_user',
							'pd.status as portal_status',
							'pd.agency_name',
							'pd.agency_address1',
							'pd.agency_address2',
							'pd.agency_city',
							'pd.agency_state',
							'pd.agency_country',
							'pd.agency_zipcode',
							'pcd.portal_credential_id',
							'pcd.user_name',
							'pcd.password',
							'pcd.auth_key',
							'pcd.session_expiry_time',
							'pcd.allow_ip_restriction',
							'pcd.allowed_ip',
							'pcd.block as credentials_blocked',
							'pcd.max_itins',
							'pcd.is_meta as is_meta_credential',
							'pcd.product_rsource as meta_rsource',
							'pcd.status as credentials_status',
							'pcd.is_upsale as is_upsale_portal',
							'pcd.is_branded_fare as is_branded_fare',
							'pcd.oneway_fares as oneway_fares',
							'pcd.default_exclude_airlines as default_exclude_airlines',
							'pcd.external_api as external_api'
		            	);

		           	if($moduleType == 'account_details'){//account_id
						$getPortalDetailsData 	= $getPortalDetailsData->where('ad.account_id', $requestData['accountId']);
					}else if($moduleType == 'portal_details'){//portal_id
						$getPortalDetailsData 	= $getPortalDetailsData->where('pd.portal_id', $requestData['accountId']);
					}else if($moduleType == 'portal_credentials'){//portal_credential_id
						$getPortalDetailsData 	= $getPortalDetailsData->where('pcd.portal_credential_id', $requestData['accountId']);
					}

			$getPortalDetailsData 	= $getPortalDetailsData->whereIn('ad.status', ['A'])->whereIn('pd.status', ['A'])->whereIn('pcd.status', ['A'])->get()->toArray();
			
			$getPortalDetailsData  	= json_decode(json_encode($getPortalDetailsData), true); 
			if(count($getPortalDetailsData) > 0) {
				foreach($getPortalDetailsData as $key => $val){					

					$val['ag_agency_state']			= (isset($val['ag_agency_state']) && !empty($val['ag_agency_state'])) ? $val['ag_agency_state'] : '';
					$val['ag_agency_state_code']	= '';
					$val['ag_agency_state_id']		= (isset($val['ag_agency_state']) && !empty($val['ag_agency_state'])) ? $val['ag_agency_state'] : 0;

					$stateDetails = StateDetails::getStateById($val['ag_agency_state']);

					if(!empty($stateDetails)){
						$val['ag_agency_state']			= $stateDetails['name'];
						$val['ag_agency_state_code']	= $stateDetails['state_code'];
						$val['ag_agency_state_id']		= $stateDetails['state_id'];
					}

					Redis::set('portalInfoCredentials_'.$val['auth_key'], json_encode($val), 'EX', config('common.redisSetTime'));
				}	
			}		
		}
	}

	public static function updateAccountAggregationMapping($requestData)
	{
		if(isset($requestData['accountId'])){

			$accountId = $requestData['accountId'];
			
			Redis::del('accountAggregationMapping_'.$accountId);

			$accAggMapping	= [];

			$getAcAggMapping = DB::table(config('tables.account_aggregation_mapping'))
				->select(
						'supplier_account_id',
						'partner_account_id',
						'profile_aggregation_id',
						'ticketing_authority',
						're_distribute',
						'status'
						)
				->where('partner_account_id', $accountId)
				->whereIn('status', ['A'])
				->get();
				
				$getAcAggMapping 	= json_decode(json_encode($getAcAggMapping),true);

			if($getAcAggMapping){

				foreach ($getAcAggMapping as $key => $value) {
					$partnerAccountId 	= $value['partner_account_id'];
					$appendId		 	= $value['supplier_account_id'].'_'.$value['partner_account_id'].'_'.$value['profile_aggregation_id'];
					
					if(!isset($accAggMapping[$partnerAccountId])){
						$accAggMapping[$partnerAccountId] = [];
					}
					
					$accAggMapping[$partnerAccountId][$appendId] = $value;
				}

				foreach ($accAggMapping as $tempAccId => $tempAccVal) {
					Redis::set('accountAggregationMapping_'.$tempAccId, json_encode($accAggMapping[$tempAccId]), 'EX', config('common.redisSetTime'));
				}
			}
		}		
	}

	public static function updatePortalAggregationMapping($requestData)
	{
		if(isset($requestData['accountId'])){

			$accountId = $requestData['accountId'];
			
			$portalInfo = DB::table(config('tables.portal_details'))
				->select(
						'portal_id',
						'account_id'
						)
				->where('account_id', $accountId)
				->get();
				
			if($portalInfo){

				$portalInfo = json_decode(json_encode($portalInfo),true);
				
				foreach ($portalInfo as $key => $value) {
			
					$mappingPortalId = $value['portal_id'];
					
					Redis::del('portalAggregations_'.$mappingPortalId);

					$getPortalAggMapping = DB::table(config('tables.portal_aggregation_mapping'))
						->select(
								'profile_aggregation_id'
								)
						->where('portal_id', $mappingPortalId)
						->whereIn('status', ['A'])
						->get();
						
						$getPortalAggMapping 	= json_decode(json_encode($getPortalAggMapping),true);

					if($getPortalAggMapping){

						foreach ($getPortalAggMapping as $key => $value) {

							$aggregationIds		= explode(',', $value['profile_aggregation_id']);

							$aggregationIds		= array_unique($aggregationIds);
						
							Redis::set('portalAggregations_'.$mappingPortalId, json_encode($aggregationIds), 'EX', config('common.redisSetTime'));
						}
						
					}
				}
			}
		}		
	}

	public static function updateTicketingRules($requestData)
	{	
		if(isset($requestData['accountId'])){

			$accountId 			= $requestData['accountId'];

			Redis::del('ticketingRules_'.$accountId);

			$ticketingRuleData  = DB::table(config('tables.ticketing_rules'))
								->select('ticketing_rule_id',
									'account_id',
									'marketing_airlines',
									'rule_name',
									'rule_code',
									'trip_type',
									'supplier_template_id',
									'qc_template_id',
									'risk_analaysis_template_id',
									'criterias',
									'ticketing_fare_types',
									'ticketing_action',
									'updated_at'
			            		)
			            	->where('account_id', $accountId)
			            	->where('status', 'A')->get();

			$ticketingRuleData 	= json_decode(json_encode($ticketingRuleData), true);
			$ticketingRules 	= array();			            	

			if(count($ticketingRuleData) > 0){

				foreach ($ticketingRuleData as $key => $value) {

					$value['criterias']				= ($value['criterias'] != '' && $value['criterias'] != null) ? json_decode($value['criterias']) : [];

					$value['ticketing_fare_types']	= ($value['ticketing_fare_types'] != '' && $value['ticketing_fare_types'] != null) ? json_decode($value['ticketing_fare_types']) : [];

					$value['ticketing_action']		= ($value['ticketing_action'] != '' && $value['ticketing_action'] != null) ? json_decode($value['ticketing_action']) : [];
					
					$partnerAccountId	= $value['account_id'];
					$ticketingRuleId	= $value['ticketing_rule_id'];

					$ticketingRules[$ticketingRuleId] = $value;
					
				} 

				Redis::set('ticketingRules_'.$accountId, json_encode($ticketingRules), 'EX', config('common.redisSetTime'));
			}		  	

        }
		
	}

	public static function updateSupplierLowfareTemplate($requestData)
	{
		if(isset($requestData['accountId'])){

			$accountId 	= $requestData['accountId'];

			Redis::del('lowFareTemplateRules_'.$accountId);

			$supLowFareTmpData  = DB::table(config('tables.supplier_lowfare_template'))
								->select('lowfare_template_id',
									'account_id',
									'template_name',
									'marketing_airline',
									'lowfare_template_settings',
									'criterias',
									'updated_at'
			            		)
			            	->where('account_id', $accountId)
			            	->where('status', 'A')->get();

			$supLowFareTmpData 	= json_decode(json_encode($supLowFareTmpData), true);

			$lowFareRules 		= array();

			if(count($supLowFareTmpData) > 0){

				foreach ($supLowFareTmpData as $key => $value) {

					$value['criterias']					= ($value['criterias'] != '' && $value['criterias'] != null) ? json_decode($value['criterias']) : [];

					$value['lowfare_template_settings']	= ($value['lowfare_template_settings'] != '' && $value['lowfare_template_settings'] != null) ? json_decode($value['lowfare_template_settings']) : [];
					
					$partnerAccountId	= $value['account_id'];
					$lowfareTemplateId	= $value['lowfare_template_id'];
					
					$lowFareRules[$lowfareTemplateId] = $value;
				} 

				Redis::set('lowFareTemplateRules_'.$accountId, json_encode($lowFareRules), 'EX', config('common.redisSetTime'));
				
			}
		}
		
	}

	public static function updateRiskAnalysisTemplate($requestData)
	{
		if(isset($requestData['accountId'])){

			$accountId 	= $requestData['accountId'];

			Redis::del('riskAnalysisTemplates_'.$accountId);

			$riskAnalysisTmpData  = DB::table(config('tables.risk_analysis_template'))
								->select('risk_template_id',
									'account_id',
									'template_name',
									'criterias',
									'other_info'
			            		)
			            	->where('account_id', $accountId)
			            	->where('status', 'A')->get();

			$riskAnalysisTmpData 	= json_decode(json_encode($riskAnalysisTmpData), true);

			$riskAnalysisTemplates 	= array();

			if(count($riskAnalysisTmpData) > 0){

				foreach ($riskAnalysisTmpData as $key => $value) {

					$value['criterias']		= ($value['criterias'] != '' && $value['criterias'] != null) ? json_decode($value['criterias']) : [];

					$value['other_info'] 	= ($value['other_info'] != '' && $value['other_info'] != null) ? json_decode($value['other_info']) : [];
					
					$partnerAccountId		= $value['account_id'];
					$riskTemplateId			= $value['risk_template_id'];
					
					$riskAnalysisTemplates[$riskTemplateId] = $value;
				} 			

				Redis::set('riskAnalysisTemplates_'.$accountId, json_encode($riskAnalysisTemplates), 'EX', config('common.redisSetTime'));
			}
		}
		
	}

	public static function updateQualityCheckTemplate($requestData)
	{
		if(isset($requestData['accountId'])){

			$accountId 			= $requestData['accountId'];

			Redis::del('qualityCheckTemplates_'.$accountId);

			$qualityCheckTempData  = DB::table(config('tables.quality_check_template'))
								->select('qc_template_id',
									'account_id',
									'template_name',
									'template_settings',
									'other_info'
			            		)
			            	->where('account_id', $accountId)
			            	->where('status', 'A')->get();

			$qualityCheckTempData 	= json_decode(json_encode($qualityCheckTempData), true);

			$qualityCheckTemplates 	= array();

			if(count($qualityCheckTempData) > 0){

				foreach ($qualityCheckTempData as $key => $value) {

					$value['template_settings']				= ($value['template_settings'] != '' && $value['template_settings'] != null) ? json_decode($value['template_settings']) : [];

					$value['other_info']			= ($value['other_info'] != '' && $value['other_info'] != null) ? json_decode($value['other_info']) : [];
					
					$partnerAccountId	= $value['account_id'];
					$qcTemplateId		= $value['qc_template_id'];
					
					$qualityCheckTemplates[$qcTemplateId] = $value;
				} 		

				Redis::set('qualityCheckTemplates_'.$accountId, json_encode($qualityCheckTemplates), 'EX', config('common.redisSetTime'));
			}
		}
		
	}

	public static function updateRemarkTemplate($requestData)
	{
		
	}

	public static function updateUpsaleManagement($requestData){	

		if(isset($requestData['accountId'])){
			$accountId 	= $requestData['accountId'];		

			$upsaleData = DB::table(config('tables.upsale_details'))->where('account_id', $accountId)->whereIn('status', ['A'])->get()->toArray();
			$upsaleData 		= json_decode(json_encode($upsaleData), true);

			$currencyTempData 	= array();			
			$mainArrData  		= array();
			if(count($upsaleData) > 0){
				foreach ($upsaleData as $value) {
					$portalId 		= $value['portal_id'];
					$portalCreId 	= $value['portal_credential_id'];
					$amount_details = json_decode($value['amount_details'], true);

					foreach($amount_details as $currencyKey => $amountVal)
					{	
						$currencyTempData['Diff'][$currencyKey] 	= ($amountVal['amount_diff'] != null) ? $amountVal['amount_diff'] : 0;
						$currencyTempData['Discount'][$currencyKey] = ($amountVal['percentage'] != null) ? $amountVal['percentage'] : 0;

					}
					$mainArrData[$portalId][$portalCreId] 	= $currencyTempData;
				}
			}
			Redis::set('upSaleFareConfig_'.$accountId, json_encode($mainArrData), 'EX', config('common.redisSetTime'));		
		}		
	}

	public static function updateSupplierFeeRules($requestData)
	{ 
		if(isset($requestData['accountId'])){
			
			$allFeeRules	= [];
			$accountId 				= $requestData['accountId'];		
			$supplierFeeRulesData 	= DB::table(config('tables.agency_fee_details'))->where('account_id', $accountId)->whereIn('status', ['A'])->get()->toArray();

			Redis::del('supplierFeeRules_'.$accountId);
			
			if(count($supplierFeeRulesData) > 0){

				foreach($supplierFeeRulesData as $key => $value){

					$tempMapping 		= $value;
					$validatingAirline	= $tempMapping->validating_carrier;
					$supplierId			= $tempMapping->account_id;
					$agencyFeeId		= $tempMapping->agency_fee_id;
					$feeDetails			= $tempMapping->fee_details;
					$consumerAccountId	= $tempMapping->consumer_account_id;
					$contentSourceId	= $tempMapping->content_source_id;
				
					if($feeDetails == '' || $feeDetails == null){
						$feeDetails = [];
					}
					else{
						$feeDetails = json_decode($feeDetails);
					}

					if(!isset($allFeeRules[$supplierId])){
						$allFeeRules[$supplierId] = [];
					}

					if(!isset($allFeeRules[$supplierId][$contentSourceId])){
						$allFeeRules[$supplierId][$contentSourceId] = [];
					}
					
					$consumerAccountIdArr =explode(",", $consumerAccountId);

					foreach($consumerAccountIdArr as $cval){
											
						$loopConsumerId = $cval;
						
						if(!isset($allFeeRules[$supplierId][$contentSourceId][$loopConsumerId])){
							$allFeeRules[$supplierId][$contentSourceId][$loopConsumerId] = [];
						}
					
						$validatingAirlineArr = explode(",", $validatingAirline);
					
						foreach($validatingAirlineArr as $vVal){
						
							$airlineCode = $vVal;
							
							if(!isset($allFeeRules[$supplierId][$contentSourceId][$loopConsumerId][$airlineCode])){
							
								$allFeeRules[$supplierId][$contentSourceId][$loopConsumerId][$airlineCode] = [];
							}
							
							$allFeeRules[$supplierId][$contentSourceId][$loopConsumerId][$airlineCode] = $feeDetails;
						}

					}
				}
				Redis::set('supplierFeeRules_'.$accountId, json_encode($allFeeRules[$accountId]), 'EX', config('common.redisSetTime'));
			}

		}
	}
	public static function updateSeatMapping($requestData)
	{ 
		if(isset($requestData['accountId'])){
			
			$seatMapping	= [];
			$accountId 				= $requestData['accountId'];		
			$seatMappingData 	= DB::table(config('tables.seat_map_markup_details'))->where('account_id', $accountId)->whereIn('status', ['A'])->get()->toArray();

			Redis::del('seatMapMarkups_'.$accountId);
			
			if(count($seatMappingData) > 0){

				foreach($seatMappingData as $key => $value){
					$seatMappingId  				= explode(',',$value->consumer_account_id);
					$temp['seat_map_markup_id']		= $value->seat_map_markup_id;
					$temp['account_id']				= $value->account_id;
					$temp['consumer_account_id']	= $value->consumer_account_id;
					$temp['markup_details']			= json_decode($value->markup_details);
					$temp['status']					= $value->status;
					foreach ($seatMappingId as $value) {
						$seatMapping[$value] 	= $temp;
					}
					
				}
				Redis::set('seatMapMarkups_'.$accountId, json_encode($seatMapping), 'EX', config('common.redisSetTime'));
			}

		}
	}

}
