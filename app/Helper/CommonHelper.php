<?php

// use Storage;
use App\Libraries\ERunActions\ERunActions;
    
    if (! function_exists('logWrite')) {

        function logWrite($storagePath='logs', $fileName='app', $aData='', $initText='', $type = '')
        {
            if($initText == ''){
                $logHeading = 'TTSAPI Loging System - '.date('Y-m-d H:i:s');
            }else{
                $logHeading = 'TTSAPI Loging System - '.$initText.' - '.date('Y-m-d H:i:s');
            }
        	
        	switch ($type) {
        		case 'D':
        			$date = date('Y-m-d');
        			$fileName = $fileName.'_'.$date;
        			break;
        		case 'T':
        			$time = date('Y-m-d H:i:s');
        			$fileName = $fileName.'_'.$time;
        			break;		
        		default:
        			break;
        	}
        	if (!file_exists(storage_path($storagePath))) {
        	    mkdir(storage_path($storagePath), 0777, true);
        	}
        	$headingSeparator   = '---------------------------------------------';
            // $fileName           = storage_path($storagePath.'/'.$fileName.'.log');
            $fileName           = $storagePath.'/'.$fileName.'.txt';

            $flightReqResContent    = $logHeading.PHP_EOL.$headingSeparator.PHP_EOL.$aData.PHP_EOL.PHP_EOL;

            $flightLogStoreLocation = config('common.log_disk');

            // file_put_contents($fileName, $flightReqResContent , FILE_APPEND);
            // Storage::disk($flightLogStoreLocation)->append($fileName, $flightReqResContent);
            
            $postArray = array('fileName' => $fileName,'logContent' => $flightReqResContent, 'location' => $flightLogStoreLocation );
            $url = url('/').'/api/minioLog';
                                                
            ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");            

            return true;
        }

    }


    if (! function_exists('asset')) {

        function asset($path, $secure = null)
        {
            return app('url')->asset($path, $secure);
        }

    }

    if (! function_exists('public_path')) {

        function public_path($path = null)
        {
            return rtrim(app()->basePath('public/' . $path), '/');  

        }

    }


    if (! function_exists('getSearchId')) {
        function getSearchId(){
            return  time().mt_rand(10,99);
        }
    }

    if (! function_exists('getBookingReqId')) {
        function getBookingReqId(){

            return date('Ymd').time().mt_rand(10,99);
            $uniqString = md5(uniqid(rand(), true));
            return strtoupper(substr($uniqString, 0, 8));
        }
    }

    if (! function_exists('encryptor')) {

        function encryptor($_Action, $_String) {
            $_Output = '';
            if( $_Action == 'encrypt' ) {
                $_Output = base64_encode($_String);
            }
            else if( $_Action == 'decrypt' ){
                $_Output = base64_decode($_String);
            }

            return $_Output;
        }
    }

    if (! function_exists('getDateTime')) {
        function getDateTime(){
            return date('Y-m-d H:i:s');
        }
    }

    if (! function_exists('microtimeFloat')) {

        function microtimeFloat()
        {
            
            return (microtime(true) * 1000);
        }
    }

?>