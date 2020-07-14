<?php
namespace App\Http\Controllers\ApiPgCommon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libraries\ApiPaymentGateway\ApiPgCommon;
use Log;

class ApiPgCommonController extends Controller
{
    public function apiPgBookingPayment(Request $request){

        $paymentInput 	= $request->all();  

        $paymentRes     = ApiPgCommon::initiatePayment($paymentInput);  

        return response()->json($paymentRes);
    }
}