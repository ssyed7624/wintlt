<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Flights\ExtraPayment;
use App\Libraries\OsClient\OsClient;
use App\Models\Flights\BookingMaster;
use App\Models\PortalDetails\PortalDetails;
use App\Libraries\ApiEmail;
use App\Libraries\Common;
use App\Libraries\Flights;
use Log;
use DB;


class HoldBookingPaymentMail extends Command
{    
    protected $signature    = 'PaymentHoldBooking:linkGenerator';

    protected $description  = 'Hold Bookings Direct Payment Link Mail Generator';

    public function __construct()
    {
        parent::__construct();
    }
    
    public function handle()
    {
        $holdBookingMailTriggerTime = config('common.hold_booking_mail_trigger_time');  
        $currentDate                = strtotime(Common::getDate());
        $pastTime                   = $currentDate -(60*$holdBookingMailTriggerTime);
        $pastFormatDate             = date("Y-m-d H:i:s", $pastTime);
        
        $getBookingDetails             = DB::table(config('tables.booking_master').' AS bm')->leftJoin(config('tables.extra_payments').' AS ep', 'bm.booking_master_id','=','ep.booking_master_id')->select('bm.*','ep.status AS extra_payment_status')->where('bm.booking_status',107)->where('bm.created_at','>=',$pastFormatDate)->get()->toArray();

        if(!empty($getBookingDetails) && count($getBookingDetails)>0)
        {
            foreach ($getBookingDetails as $key => $bookingMasterDetails) {
                $bookingMasterDetails = (array)$bookingMasterDetails;

                if(empty($bookingMasterDetails['extra_payment_status']))
                {
                    $bookingDetails = [];
                    if(isset($bookingMasterDetails['booking_type']) && $bookingMasterDetails['booking_type'] == 1)
                    {
                        $bookingDetails = BookingMaster::getBookingInfo($bookingMasterDetails['booking_master_id']);
                    }
                    elseif(isset($bookingMasterDetails['booking_type']) && $bookingMasterDetails['booking_type'] == 2)
                    {
                        $bookingDetails = BookingMaster::getHotelBookingInfo($bookingMasterDetails['booking_master_id']);
                    }
                    elseif(isset($bookingMasterDetails['booking_type']) && $bookingMasterDetails['booking_type'] == 3)
                    {
                        $bookingDetails = BookingMaster::getInsuranceBookingInfo($bookingMasterDetails['booking_master_id']);
                    }

                    $convertedCurrency      = isset($bookingDetails['booking_total_fare_details'][0]['converted_currency']) ?  $bookingDetails['booking_total_fare_details'][0]['converted_currency'] : 'CAD';
                    $convertedExchangeRate = isset($bookingDetails['booking_total_fare_details'][0]['converted_exchange_rate']) ?  $bookingDetails['booking_total_fare_details'][0]['converted_exchange_rate'] : 1;
                    $bookingTotalFare = 0;
                    $totalFare = isset($bookingDetails['booking_total_fare_details'][0]['total_fare']) ?  $bookingDetails['booking_total_fare_details'][0]['total_fare'] : 0;
                    $onflyHst = isset($bookingDetails['booking_total_fare_details'][0]['onfly_hst']) ?  $bookingDetails['booking_total_fare_details'][0]['onfly_hst'] : 0;
                    $promoDiscount = isset($bookingDetails['booking_total_fare_details'][0]['promo_discount']) ?  $bookingDetails['booking_total_fare_details'][0]['promo_discount'] : 0;
                    $ssrFare = isset($bookingDetails['booking_total_fare_details'][0]['ssr_fare']) ?  $bookingDetails['booking_total_fare_details'][0]['ssr_fare'] : 0;

                    $insuranceFare = 0;
                    foreach ($bookingDetails['insuranceDetails'] as $insuranceKey => $insuranceValue) {
                        if(isset($insuranceValue->policy_number) && $insuranceValue->policy_number != '')
                        {
                            $insuranceFare = $insuranceFare + ($insuranceValue->total_fare * $insuranceValue->converted_exchange_rate);
                        }
                    }
                    $bookingTotalFare = ((($totalFare + $onflyHst +$ssrFare) - $promoDiscount) * $convertedExchangeRate)+$insuranceFare;

                    if(!empty($bookingDetails)){
                        $extraModel = new ExtraPayment;
                        $inputParam = [];
                        $inputParam['account_id']           = $bookingDetails['account_id'];
                        $inputParam['portal_id']            = $bookingDetails['portal_id'];
                        $inputParam['booking_master_id']    = $bookingDetails['booking_master_id'];
                        $inputParam['booking_req_id']       = $bookingDetails['booking_req_id'];
                        $inputParam['payment_charges']      = 0;
                        $inputParam['payment_amount']       = $bookingTotalFare;
                        $inputParam['remark']               = 'Hold Booking Confirmation Payment';            
                        $inputParam['status']               = 'I';            
                        $inputParam['retry_count']          = '0';            
                        $inputParam['reference_email']      = $bookingDetails['booking_passanger_email'];
                        $inputParam['booking_type']         = 'HOLD_BOOKING_CONFIRMATION';
                        $inputParam['created_at']           = Common::getDate();
                        $inputParam['updated_at']           = Common::getDate();
                        $inputParam['created_by']           = Common::getUserID();
                        $inputParam['updated_by']           = Common::getUserID();
                        $extraPaymentId = $extraModel->create($inputParam)->extra_payment_id;
                        $bookingDetails['payment_remark']   = 'Hold Booking Confirmation Payment';
                        $bookingDetails['payment_amount']   = $bookingTotalFare;;
                        $portalDetails = PortalDetails::where('portal_id', $bookingDetails['portal_id'])->first();

                        if($portalDetails && !empty($portalDetails) && $portalDetails->business_type == 'META' && $portalDetails->parent_portal_id != 0){
                             $portalDetails = PortalDetails::where('portal_id',$portalDetails->parent_portal_id)->first();
                        }
                        
                        $portalUrl = '';
                        if($portalDetails){
                           $portalUrl =  $portalDetails['portal_url'];
                        }            
                        $bookingDetails['payment_url']      = $portalUrl.'/makePayment/'.encryptData($bookingDetails['booking_master_id']).'/'.encryptData($extraPaymentId);
                        $bookingDetails['toMail']           = $bookingDetails['booking_passanger_email'];
                        $bookingDetails['payment_currency'] = $convertedCurrency;
                        //send email
                        ApiEmail::holdBookingConfirmationPaymentMail($bookingDetails);
                        
                        //create os ticket
                        $mailContent = [];
                        $getPortalDatas = PortalDetails::getPortalDatas($bookingDetails['portal_id']);
                        //get portal config
                        $getPortalConfig          = PortalDetails::getPortalConfigData($bookingDetails['portal_id']);
                        //add portal related datas
                        $bookingDetails['portalName'] = $getPortalDatas['portal_name'];
                        $bookingDetails['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
                        $bookingDetails['portalMobileNo'] = Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']);
                        $bookingDetails['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
                        $bookingDetails['mailLogo']     = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';

                        $mailContent['inputData'] = $bookingDetails;


                        $viewHtml = view('mail.extraPyamentMail', $mailContent); // Include html 
                        $osConfigArray = Common::getPortalOsTicketConfig($bookingDetails['portal_id']);
                        $requestData = array(
                                           "request_type" => 'extraPayment',
                                           "portal_id"    => $bookingDetails['portal_id'],
                                           "osConfig" => $osConfigArray,
                                           "name" => $bookingDetails['booking_passanger_email'],
                                           "email" => $bookingDetails['booking_passanger_email'],
                                           "subject" => $bookingDetails['booking_req_id'].'-'.$bookingDetails['booking_passanger_email'],
                                           "message"=>"data:text/html;charset=utf-8,$viewHtml"
                                       );
                        OsClient::addOsTicket($requestData);

                        
                    }else{
                        
                    }
                }
            }
        }
    }
}