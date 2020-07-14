<?php
namespace App\Models\Flights;
use App\Models\Model;
use App\Http\Middleware\UserAcl;
use App\Models\Bookings\BookingMaster;
use App\Models\Bookings\StatusDetails;
use Auth;
use DB;
use Log;

class SupplierWiseItineraryFareDetails extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.supplier_wise_itinerary_fare_details');
    }

    protected $primaryKey = 'supplier_wise_itinerary_fare_detail_id';
    public $timestamps = false;

    //get pax fare breakup
    public static function getItineraryFareDetails($bookingMasterId){
        $getFareDetails = SupplierWiseItineraryFareDetails::On('mysql2')->select('booking_master_id', 'pax_fare_breakup', 'pos_rule_id', 'booking_status')->where('booking_master_id', $bookingMasterId)->orderBy('supplier_wise_itinerary_fare_detail_id', 'desc')->first();
        return $getFareDetails;

    }
    
    //Get Supplier Wise Itin Fare Details
    public static function getSupplierWiseItineraryFareDetails($bookingIds,$aEquvAccountIds){

        $accessSuppliers    = UserAcl::getAccessSuppliers();
        $loginAcId          = Auth::user()->account_id;
        $isEngine			= UserAcl::isSuperAdmin();
        $statusDetails      = StatusDetails::getStatus();

        //Log::info(print_r($aEquvAccountIds,true));
        //Log::info(print_r($bookingIds,true));

        $aSupItinFareDetails = SupplierWiseItineraryFareDetails::select('supplier_wise_itinerary_fare_detail_id','booking_master_id','supplier_account_id','consumer_account_id','flight_itinerary_id','total_fare','ssr_fare','booking_status')->whereIn('booking_master_id', $bookingIds)->get();
        
        $aReturn= array();
        $aTemp  = array();

        if(isset($aSupItinFareDetails) && !empty($aSupItinFareDetails)){

            $aSupItinFareDetails = $aSupItinFareDetails->toArray();

            //Booking Master & Itin Wise Array Preparation
            foreach($aSupItinFareDetails as $aSKey => $aSVal){
                $aTemp[$aSVal['booking_master_id']][$aSVal['flight_itinerary_id']][] = $aSVal;
            }

            foreach($aTemp as $mKey => $mVal){
				
                foreach($mVal as $iKey => $iVal){
					
					$moved = false;
					
					usort($iVal, function($a, $b) {
						return ($a["supplier_wise_itinerary_fare_detail_id"] >= $b["supplier_wise_itinerary_fare_detail_id"]) ? -1 : 1;
					});
					
                    foreach($iVal as $sKey => $sVal){

                        $supplierAccountId  = $sVal['supplier_account_id'];
                        $consumerAccountId  = $sVal['consumer_account_id'];
                        $bookingMasterId    = $sVal['booking_master_id'];

                        $givenAccountId = $loginAcId;

						if(in_array($supplierAccountId,$accessSuppliers)){
							$givenAccountId = $supplierAccountId;
						}
						else if(in_array($consumerAccountId,$accessSuppliers)){
							$givenAccountId = $consumerAccountId;
						}
                        
                        if($isEngine && $consumerAccountId == $aEquvAccountIds[$bookingMasterId]){
							$givenAccountId = $consumerAccountId;
						}

                        if(!$moved && ($supplierAccountId == $givenAccountId || $consumerAccountId == $givenAccountId)){
                            
                            $moved = true;
                            
                            if(!isset($aReturn[$bookingMasterId])){
								$temp = array();
								$temp['total_fare'] = 0;
								$temp['ssr_fare'] = 0;
								$temp['status'] = array();
								$temp['org_status'] = array();
								
								$aReturn[$bookingMasterId] = $temp;
							}
							
                            $aReturn[$bookingMasterId]['total_fare'] += $sVal['total_fare'];
                            $aReturn[$bookingMasterId]['ssr_fare'] += $sVal['ssr_fare'];
                            $aReturn[$bookingMasterId]['status'][] = $statusDetails[$sVal['booking_status']];
                            $aReturn[$bookingMasterId]['org_status'][] = $sVal['booking_status'];
                        }
                    }

                }
            }
        }

        return $aReturn;

        //Log::info(print_r($aReturn,true));
    }

}
