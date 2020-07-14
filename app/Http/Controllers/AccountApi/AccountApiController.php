<?php

namespace App\Http\Controllers\AccountApi;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Libraries\AccountApi\PenAir;

class AccountApiController extends Controller
{
	public function index(Request $request){
                switch($request->reqType){
                        case 'doFolderCreate': 
                                $bookingMasterId = $request->bookingMasterId;
                                $aResponse = PenAir::doFolderCreate($request->all());

                                if($request->addPayment){
                                        $aResponse = PenAir::doFolderReceipt($request->all());
                                }

                                break;
                        case 'doFolderReceipt': 
                                $bookingMasterId = $request->bookingMasterId;
                                $aResponse = PenAir::doFolderReceipt($request->all());
                                break;
                }
	}
        
        //For Test Purpose Only
        public function callAccountApi(Request $request){
                $aResponse = PenAir::doFolderCreate($request->all());
                echo "<pre>";
                print_r($aResponse);
                die();
        }
}
