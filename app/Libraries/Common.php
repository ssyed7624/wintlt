<?php 
namespace App\Libraries;

use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Models\ContentSource\ContentSourceDetails;
use App\Models\CustomerDetails\CustomerDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\AccountDetails\AgencySettings;
use App\Models\PortalDetails\PortalDetails;
use App\Libraries\ERunActions\ERunActions;
use App\Models\PortalDetails\PortalConfig;
use Illuminate\Support\Facades\Storage;
use App\Models\UserDetails\UserDetails;
use Illuminate\Support\Facades\Redis;
use App\Models\Common\LogActivities;
use Illuminate\Support\Facades\File;

use App\Models\Common\AirlinesInfo;
use App\Models\Common\AirportMaster;
use App\Models\Common\CountryDetails;
use App\Models\Common\StateDetails;
use App\Models\Common\CurrencyDetails;

use App\Libraries\RedisUpdate;
use Illuminate\Http\Request;
use App\Libraries\History;
use DateTimeZone;
use DateTime;
use Auth;
use DB;

class Common
{
	public static function getDate(){
		return date('Y-m-d H:i:s');
    }

    public static function getUtcDate(){
        return date("Y-m-d H:i:s", time() - date("Z"));
    }

    public static function getFormatPhoneNumber($phoneNumber = ''){
        if($phoneNumber != ''){
            return str_replace("(","",str_replace(")","",str_replace("-","",str_replace("+","0",str_replace(" ","",$phoneNumber)))));
        }
        return $phoneNumber;        

    }

    public static function randomPassword($length = 9, $add_dashes = false, $available_sets = 'luds')
    {
        $sets = array();
        if(strpos($available_sets, 'l') !== false)
            $sets[] = 'abcdefghjkmnpqrstuvwxyz';
        if(strpos($available_sets, 'u') !== false)
            $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        if(strpos($available_sets, 'd') !== false)
            $sets[] = '23456789';
        if(strpos($available_sets, 's') !== false)
            $sets[] = '!@#$%&*?';
        $all = '';
        $password = '';
        foreach($sets as $set)
        {
            $password .= $set[array_rand(str_split($set))];
            $all .= $set;
        }
        $all = str_split($all);
        for($i = 0; $i < $length - count($sets); $i++)
            $password .= $all[array_rand($all)];
        $password = str_shuffle($password);
        if(!$add_dashes)
            return $password;
        $dash_len = floor(sqrt($length));
        $dash_str = '';
        while(strlen($password) > $dash_len)
        {
            $dash_str .= substr($password, 0, $dash_len) . '-';
            $password = substr($password, $dash_len);
        }
        $dash_str .= $password;

        return $dash_str;
    }

    //prepare auth key for portal details
    public static function getPortalCredentialsAuthKey($hidden_portal_id){
        return md5($hidden_portal_id).strtotime(Common::getDate());
    }//eof

    //get formatted phone number to view purpose
    public static function getFormatPhoneNumberView($countryCode='',$phoneNumber=''){
        if($phoneNumber != ''){
            $phoneNumber = preg_replace('/[^0-9]/','',$phoneNumber);
            if(strlen($phoneNumber) == 7) {
                $nextThree = substr($phoneNumber, 0, 3);
                $lastFour = substr($phoneNumber, 3, 4);

                $customPhoneNumber = $nextThree.' '.$lastFour;
            }else{
                $firstSet = substr($phoneNumber, 0, 3);
                $secondSet = substr($phoneNumber, 3, 3);
                $thirdSet = substr($phoneNumber, 6, 4);
                $fourthSet = substr($phoneNumber, 10, 5);

                $customPhoneNumber = $firstSet.' '.$secondSet.' '.$thirdSet.' '.$fourthSet;
            }
            $phoneNumber = '('.$countryCode.') '. $customPhoneNumber;
        }
        return $phoneNumber;
    }//eof

    public static function getDateFormat($format,$date)
    {
        return date($format,strtotime($date));
    }
    public static function getTimeZoneDateFormat($giveDate,$offsetDisp='N',$timezone=null,$format=null){
        $serverTimezone = config('common.server_timezone');
        //to display timezone
        if(is_null($timezone) || $timezone == '')
            // $timezone = Auth::user()->account_time_zone;
            $timezone = 'America/Toronto';
        //to display format
        if(is_null($format) || $format == '')
            $format = config('common.user_display_date_time_format');

        //Bad timezone fix and all timezone list
        $allTimeZoneList = DateTimeZone::listIdentifiers();
        if(!in_array($timezone, $allTimeZoneList))
            $timezone = config('common.registration_time_zone');

        $given = new DateTime($giveDate);
        $setTimeZone = new DateTimeZone($timezone);
        $given->setTimezone($setTimeZone);
        
        /*$given = new \DateTime($giveDate,new \DateTimeZone($serverTimezone));
        $given->setTimezone(new \DateTimeZone($timezone));*/

        $offsetIs = '';
        if($offsetDisp == 'Y'){
            $offset     = $given->getOffset();
            $offsetIs   = self::formatGmtOffset($offset);
            $offsetIs   = ' ('.$offsetIs.') ';
        }
        return $given->format($format).$offsetIs;
    }//eof

    //get Datetime format based on config;
    public static function globalDateTimeFormat($dateTime, $format = ''){    
        if(!$format){
            $format = config('common.date_time_format');
        } 

        $dateTimeFormat =  \Carbon\Carbon::parse($dateTime)->format($format);
        return $dateTimeFormat;
    }

    public static function formatGMTOffset($offset) {
        $hours = intval($offset / 3600);
        $minutes = abs(intval($offset % 3600 / 60));
        return 'GMT' . ($offset ? sprintf('%+03d:%02d', $hours, $minutes) : '');
    }
    
    public static function getUserID(){
        if(Auth::id()){
            return Auth::id();
        }else{
            return 0;
        }
    }
    
    // Get rounded fare
    public static function getRoundedFare($amt=0,$roundTo=2,$currency='')
    {
        return  number_format((float)$amt, $roundTo, '.', '');
        //return round($amt,$roundTo);
    }

    public static function getAccountTimezone(){
        // return Auth::user()->account_time_zone;
        return 'America/Toronto';
    }

    //funtion to get all timezone list
    public static function timeZoneList(){
        static $timezones = null;
        if ($timezones === null) {
        $timezones = [];
        $offsets = [];
        $countries = CountryDetails::where('status','A')->get();
        $now = new DateTime('now', new DateTimeZone('UTC'));
        foreach ($countries as $key => $countryDetails) {
            foreach (DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $countryDetails['country_code']) as $timezone) {
                $now->setTimezone(new DateTimeZone($timezone));
                $offsets[] = $offset = $now->getOffset();
                $abbreviation = $now->format('T');
                
                if(is_numeric($abbreviation)){
                    $abbreviation = '';
                }else {
                    $abbreviation = ' - '.$abbreviation;
                }
                $timezones[$timezone] = $countryDetails['country_name'].' ('.$countryDetails['country_code'].') '.self::formatTimeZoneName($timezone) . '(' . self::formatGMTOffset($offset) . ') '.$abbreviation;
                
            }
        }
    }
    asort($timezones);
    return $timezones;
    }//eof

    public static function formatTimeZoneName($name) {
        $name = str_replace('/', ', ', $name);
        $name = str_replace('_', ' ', $name);
        $name = str_replace('St ', 'St. ', $name);
        return $name;
    }

    //History Activity Management
    public static function logActivities($requestArray)
    {
        $log = [];
        $requestClass = new Request();
        $log['model_primary_id'] = $requestArray['model_primary_id'];
        $log['model_name'] = $requestArray['model_name'];
        $log['activity_flag'] = $requestArray['activity_flag'];
        $log['method'] = $requestClass->method();
        $log['subject'] = $requestArray['subject'];
        $log['log_data'] = $requestArray['log_data'];
        $log['url'] = $requestClass->fullUrl();
        $log['ip'] = !is_null($requestClass->ip()) ? $requestClass->ip() : '';
        $log['agent'] = !is_null($requestClass->header('user-agent')) ? $requestClass->header('user-agent') : '';
        $log['created_at'] = Common::getDate();
        $log['created_by'] = Common::getUserID();
        LogActivities::create($log);
    }//eof

    public static function prepareArrayForLog($modelPrimaryId,$subject,$logData,$modelName,$activityFlag){
        $prepareArrayForLog['model_primary_id'] = $modelPrimaryId;
        $prepareArrayForLog['model_name'] = $modelName;
        $prepareArrayForLog['activity_flag'] = $activityFlag;
        $prepareArrayForLog['subject'] = $subject; 
        $prepareArrayForLog['log_data'] = History::commonHistoryData($logData);
        self::logActivities($prepareArrayForLog);
    }//eof

    //to check recursively
    public static function arrayRecursiveDiff($aArray1, $aArray2) {
      $aReturn = array();

      foreach ($aArray1 as $mKey => $mValue) {
        if (array_key_exists($mKey, $aArray2)) {
          if (is_array($mValue)) {

            if(is_string($aArray2[$mKey]))
                $aArray2[$mKey] = (array)$aArray2[$mKey];

            $aRecursiveDiff = self::arrayRecursiveDiff($mValue, $aArray2[$mKey]);
            if (count($aRecursiveDiff)) { $aReturn[$mKey] = $aRecursiveDiff; }
          } else {
            if ($mValue != $aArray2[$mKey]) {
              $aReturn[$mKey] = $mValue;
            }
          }
        } else {
          $aReturn[$mKey] = $mValue;
        }
      }
      return $aReturn;
    }//eof

    public static function httpRequest($url, $postData = [], $headers = [], $queryBuild = false){

        $postData = json_encode($postData);

        $ch2  = curl_init();

        curl_setopt($ch2, CURLOPT_URL, $url);

        curl_setopt($ch2, CURLOPT_TIMEOUT, 180);

        curl_setopt($ch2, CURLOPT_HEADER, 0);

        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch2, CURLOPT_POST, 1);

        $httpHeader2 = array();

        if($queryBuild){
           $postData = http_build_query(json_decode($postData,true));
        }
        else{

            $httpHeader2 = array
            (

                "Content-Type: application/json",
                "Content-length: " . strlen($postData),
                "Accept-Encoding: gzip,deflate"
            );
        }

        curl_setopt($ch2, CURLOPT_POSTFIELDS, $postData); 

        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false); 
            
        if(count($headers) > 0){
            $httpHeader2 = array_merge($httpHeader2,$headers);
        }

        curl_setopt($ch2, CURLOPT_HTTPHEADER, $httpHeader2);

        curl_setopt ($ch2, CURLOPT_ENCODING, "gzip,deflate");

        $response = curl_exec($ch2);

        return $response;

    }

    public static function getHttpRq($url, $headers = []){

        $cURLConnection = curl_init();

        curl_setopt($cURLConnection, CURLOPT_URL, $url);
        curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);

        if(count($headers) > 0){

            $headerArray = array();

            foreach ($headers as $key => $value) {
                $headerArray[] = $key.":".$value;
            }

            curl_setopt($cURLConnection, CURLOPT_HTTPHEADER, $headerArray);
        }

        curl_setopt ($cURLConnection, CURLOPT_ENCODING, "gzip,deflate");

        $response = curl_exec($cURLConnection);
        curl_close($cURLConnection);

        return $response;

    }

    //alpha numeric with number random key generate
    public static function random_num($size) {
        $alpha_key = '';
        $keys = range('A', 'Z');

        for ($i = 0; $i < 4; $i++) {
            $alpha_key .= $keys[array_rand($keys)];
        }

        $length = $size - 4;

        $key = '';
        $keys = range(0, 9);

        for ($i = 0; $i < $length; $i++) {
            $key .= $keys[array_rand($keys)];
        }

        return $alpha_key . $key;
    }

     //ERunAction calling function - background calling - From EMS Common function
    public static function ERunActionData($accountId, $actionName, $productType = 'F', $moduleType = ''){
        $postArray = array('actionName' => $actionName,'accountId' => $accountId, 'productType' => $productType, 'moduleType' => $moduleType); 
        if($moduleType == ''){
            $url = url('/').'/api/updateRedisData';
            ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
        }else{
            RedisUpdate::updateRedisData($postArray);
        }        
    }

    public static function setRedis($key, $data, $redis_expire = 0){
        
        if(is_array($data)){
            $data = json_encode($data);
        }

        if($redis_expire != 0){
            Redis::set($key, $data,'EX',$redis_expire);
        }else{
            Redis::set($key, $data);
        }
    }

    public static function getRedis($key){
        return Redis::get($key);
    }

    public static function commonCriteriasValidation($inputCriteriaArray = [])
    {
        return true;        
        if(isset($inputCriteriaArray['criteria']))
        {
            $criteriaArray = $inputCriteriaArray['criteria'];
            foreach ($criteriaArray as $outerKey => $outerValue) { // for new criterias implementation validation
                foreach ($outerValue as $key => $value) {
                    if(count($value) == 5)
                    {
                        if(!isset($value['criteria_code']) || !isset($value['from_value']) || !isset($value['to_value']) || !isset($value['operator']) || !isset($value['value_type']))
                            return false;
                    }
                    else
                        return false;
                }
            }
            return true;
        }
        return false;
    }

    public static function getContractName($inputArray)
    {
        $returnData = '';
        $providerCode = '';
        $pcc = '';
        $validatingAirline = str_replace(',', '-', isset($inputArray['validating_carrier']) && is_array($inputArray['validating_carrier']) ? implode(',', $inputArray['validating_carrier']) : (isset($inputArray['validating_carrier']) ? $inputArray['validating_carrier'] : '') );
        $fareTypes = implode('-', isset($inputArray['fare_type']) ? $inputArray['fare_type'] : []);
        $agencyShortName = self::getAgencyShortName($inputArray['account_id']);

        $contentSourceId = array_merge(isset($inputArray['criteria']['contentSource'][0]['from_value']) ? explode(',', $inputArray['criteria']['contentSource'][0]['from_value']) : [],isset($inputArray['criteria']['excludeContentSource'][0]['from_value']) ? explode(',', $inputArray['criteria']['excludeContentSource'][0]['from_value']) : [] );
        $contentsSourceDetails = ContentSourceDetails::select('pcc','provide_code')->whereIn('content_source_id',$contentSourceId)->get();

        if(!empty($contentsSourceDetails)){
            $contentsSourceDetails = $contentsSourceDetails->toArray();
            foreach ($contentsSourceDetails as $key => $value) {
                $providerCode .= $value['provide_code'].'/';
                $pcc .= $value['pcc'].'/';
            }
            $providerCode = substr($providerCode, 0,strlen($providerCode)-1);
            $pcc = substr($pcc, 0,strlen($pcc)-1);
        }
        $contractName = isset($inputArray['pos_contract_name'])? $inputArray['pos_contract_name'] : (isset($inputArray['markup_contract_name']) ? $inputArray['markup_contract_name'] : '');
        $returnData = $validatingAirline.'_'.$fareTypes;
        if($agencyShortName != '')
            $returnData .= '_'.$agencyShortName;
        if($providerCode != '')
             $returnData .= '_'.$providerCode;
        if($pcc != '')
             $returnData .= '_'.$pcc;
        if($contractName != '')
             $returnData .= '_'.$contractName;
        return $returnData ;  
    }

    public static function getAgencyShortName($accountId)
    {
        $shortName = AccountDetails::where('account_id',$accountId)->value('short_name');
        return $shortName;
    }
    public static function getUserName($userId = 0,$fullNameFlag = '')
    {
        $returnName = '';
        $userData = UserDetails::find($userId);

        if($userData){
            if($fullNameFlag == 'yes'){
                $returnName = $userData->first_name .' '. $userData->last_name;
            }else{
                $returnName = $userData->first_name;
            }//eo else
        }else{
            $returnName = 'Not Set';
        }

        return $returnName;
    }//eof

    //Age Calculation
    public static function getAgeCalculation($fromDate,$toDate=''){

        if($toDate == ''){
            $toDate = date("Y-m-d");
        }
        
        $years  = date_diff(date_create($fromDate), date_create($toDate))->y;
        $months = date_diff(date_create($fromDate), date_create($toDate))->m;
        $days   = date_diff(date_create($fromDate), date_create($toDate))->d;

        if($years > 0){
            $dispText = 'Year';
            if($years > 1){
                $dispText = 'Years';
            }
            return $years.' '.$dispText;
        }else if($months > 0){

            $dispText = 'Month';
            if($months > 1){
                $dispText = 'Months';
            }

            return $months.' '.$dispText;
        }else{
            return $days.' Days';
        }
    }

    public static function getAirportList($code='') {

        $content = File::get(storage_path('airportcitycode.json'));
        $airport = json_decode($content, true);

        if($code != ''){
            $res = [];
            $isCode = [];

            $list = explode(',', $code);
            if (count($list) > 1) {
                foreach($list as $airCode) {
                    if(!isset($airport[$airCode]))continue;
                    $air = explode("|",$airport[$airCode]);
                    $res[$airCode] = array(
                        'value' => ( $air[2] ? $air[2] : $air[1] ) .' ('. $air[0] .')',
                        'label' => ( $air[2] ? $air[2] : $air[1] ) .' ('. $air[0] .')',
                        'airport_code' => $air[0],
                        'airport_name' => $air[1],
                        'city' => $air[2],
                        'state_code' => $air[3],
                        'state' => $air[4],
                        'country_code' => $air[5],
                        'country' => $air[6],
                    );
                }
                return $res;
            }

            if(strlen($code) == 3 && isset($airport[$code])) {
                $air = explode('|',$airport[$code]);
                // $result = [];
                $isCode = $res[] = array(
                    'value' => ( $air[2] ? $air[2] : $air[1] ) .' ('. $air[0] .')',
                    'airport_code' => $air[0],
                    'airport_name' => $air[1],
                    'city' => $air[2],
                    'state_code' => $air[3],
                    'state' => $air[4],
                    'country_code' => $air[5],
                    'country' => $air[6],
                );
            }
            
            foreach( $airport as $key => $value ){
                if( stripos( $value, $code ) !== false && sizeof($res) < 25 ) {
                    $air = explode('|',$value);
                    $temp = array(
                        'value' => ( $air[2] ? $air[2] : $air[1] ) .' ('. $air[0] .')',
                        'airport_code' => $air[0],
                        'airport_name' => $air[1],
                        'city' => $air[2],
                        'state_code' => $air[3],
                        'state' => $air[4],
                        'country_code' => $air[5],
                        'country' => $air[6],
                    );
                    if($isCode != $temp) {
                        $res[] = $temp;
                    }
                }
                else if(sizeof($res) > 10) {
                    break;
                }
            }
            return $res;
        }else{
            $aReturn = array();
            foreach( $airport as $key => $value ){
                $air = explode('|',$value);
                $temp = array(
                    'value' => ( $air[2] ? $air[2] : $air[1] ) .' ( '. $air[0] .' )',
                    'airport_code' => $air[0],
                    'airport_name' => $air[1],
                    'city' => $air[2],
                    'state_code' => $air[3],
                    'state' => $air[4],
                    'country_code' => $air[5],
                    'country' => $air[6],
                );
                $aReturn[$air[0]] = $temp;
            }
            return $aReturn;
        }
    
    }

    public static function validStoreFopFormatData($fopDetails = []){
        
        $configFormofPayment    = config('common.form_of_payment_types');
        $fopDataFormat          = [];

        foreach($fopDetails as $fopKey => $fopValue){
            if(!isset($configFormofPayment[$fopKey])){
                return false;
            }else if($configFormofPayment[$fopKey]['is_allwed']){
                
               if($fopValue['Allowed'] == 'Y'){
                   if(isset($fopValue['Types'])){
                        $fopTypesData       = $fopValue['Types'];
                        $configfopTypesData = $configFormofPayment[$fopKey]['types'];
                        $fopDataFormat[$fopKey]                              = [];
                        $fopDataFormat[$fopKey]['Allowed']                   = 'Y';
                        $fopDataFormat[$fopKey]['Types']                     = [];
                        
                        foreach($fopTypesData as $tKey => $tValue){
                            if(!isset($configfopTypesData[$tKey])){
                                return false;
                            }else if(isset($tValue['P']) && isset($tValue['F'])){
                                $fopDataFormat[$fopKey]['Types'][$tKey]         = [];
                                // $fopDataFormat[$fopKey]['Types'][$tKey]['selected']    = $tValue['selected'];
                                $fopDataFormat[$fopKey]['Types'][$tKey]['P']    = $tValue['P'];
                                $fopDataFormat[$fopKey]['Types'][$tKey]['F']    = $tValue['F'];
                            }else{
                                return false;
                            }
                        }
                   }else{
                        $fopDataFormat[$fopKey]                 = [];
                        $fopDataFormat[$fopKey]['Allowed']      = 'Y';
                   }
                }else{
                    $fopDataFormat[$fopKey]                 = [];
                    $fopDataFormat[$fopKey]['Allowed']      = 'N';
                }
            }else{
                return false;
            }
        }
        return $fopDataFormat;
    }
    
    //get travel time form departure date and arrival date
    public static function getTwoDateTimeDiff($fromdate, $toDate){
        $datetime1 = new DateTime($fromdate);
        $datetime2 = new DateTime($toDate);
        $interval  = $datetime1->diff($datetime2);
        
        if(isset($interval->d) && isset($interval->h) && $interval->d > 0){
            $interval->h = $interval->h+($interval->d*24);
        }
        
        return $interval->format('%h Hrs %i Min');
    }

    //to get list of history for record
    public static function showHistory($inputArray)
    {   
        $returnResponse = [];
        $returnResponse['message']             = 'history details get success';
        $returnResponse['status_code']         = config('common.common_status_code.success');
        $returnResponse['short_text']          = 'get_history_details_success';
        $returnResponse['status']              = 'success';        
        //common queries
        $logEntryData = LogActivities::where('model_primary_id',$inputArray['model_primary_id'])->where('model_name',$inputArray['model_name'])->where('activity_flag',$inputArray['activity_flag'])->limit(config('common.view_history_record_limit'))->orderBy('created_at','DESC')->get();
        //get old data from record
        $logEntryCount = count($logEntryData);
        if($logEntryCount > 0){
            $logEntryData = $logEntryData->toArray();
            $userDetails = UserDetails::select(DB::raw("CONCAT(first_name,' ',last_name) as user_name"),'user_id')->whereIn('status',['A','IA'])->pluck('user_name','user_id')->toArray();
            $returnData = [];
            foreach ($logEntryData as $key => $value) {
                $tempHistorydata = [];
                $tempHistorydata = $value;
                $tempHistorydata['log_activities_id'] = encryptData($value['log_activities_id']);
                $value['log_data'] = json_decode($value['log_data'],true);
                $tempLogData = [];
                foreach ($value['log_data'] as $innerKey => $innerValue) {
                                    
                    if(!is_numeric($innerValue) && !is_array($innerValue))
                        $decodedData = json_decode($innerValue,true);
                    else
                        $decodedData  = false;

                    if($decodedData)
                        $tempLogData[$innerKey] = $decodedData;
                    else
                        $tempLogData[$innerKey] = $innerValue;
                }
                $tempHistorydata['log_data'] = History::getCommonHistoryUpdation($tempLogData,$inputArray['model_name']);
                $tempHistorydata['created_by'] = isset($userDetails[$value['created_by']]) ? $userDetails[$value['created_by']] : " - ";
                $returnData[] = $tempHistorydata;
            }
            $returnResponse['data']['records'] = $returnData;
            $returnResponse['data']['count'] = $logEntryCount;
        }else{
            $returnResponse['message']             = 'history details get failed';
            $returnResponse['status_code']         = config('common.common_status_code.empty_data');
            $returnResponse['short_text']          = 'get_history_details_failed';
            $returnResponse['status']              = 'failed';
        }
        return $returnResponse;
    }//eof

    public static function showDiffHistory($inputArray)
    {
        $returnResponse = [];
        $returnData = [];
        $returnResponse['message']             = 'history difference details get success';
        $returnResponse['status_code']         = config('common.common_status_code.success');
        $returnResponse['short_text']          = 'get_history_difference_details_success';
        $returnResponse['status']              = 'success';
        //get current log data
        $logData = LogActivities::find($inputArray['id']);
        if(!$logData)
        {
            $returnResponse['message']             = 'history difference details get not found';
            $returnResponse['status_code']         = config('common.common_status_code.empty_data');
            $returnResponse['short_text']          = 'get_history_difference_details_not_found';
            $returnResponse['status']              = 'failed';
        }
        $currentLogRecord = $logData->log_data;
        $recentRecord = json_decode($currentLogRecord,true);
        $isNewRecord = 'yes';
        $previousRecord = [];
        $oldDataCount = $inputArray['count'];
        if($oldDataCount > 1){        //get previous record
            $previousLogRecord = LogActivities::where('model_primary_id',$logData->model_primary_id)->where('model_name',$inputArray['model_name'])->where('model_name',$inputArray['model_name'])->where('activity_flag',$inputArray['activity_flag'])->where('log_activities_id','<',$inputArray['id'])->orderBy('log_activities_id','DESC')->first();
            if($previousLogRecord){
                $previousLogRecord = json_decode($previousLogRecord->log_data,true);
                $isNewRecord = 'no';
            }else{
                $previousLogRecord = [];
                $isNewRecord = 'yes';
            }

            //diff between old and new records
            $previousRecord = Common::arrayRecursiveDiff($previousLogRecord,$recentRecord);
            
        }//eof

        foreach ($recentRecord as $innerKey => $innerValue) 
        {
            if(!is_numeric($innerValue) && !is_array($innerValue))
                $decodedData = json_decode($innerValue,true);
            else
                $decodedData  = false;
                
            if($decodedData)
                $recentRecord[$innerKey] = $decodedData;
            else
                $recentRecord[$innerKey] = $innerValue;
        }

        foreach ($previousRecord as $innerKey => $innerValue) 
        {
            if(!is_numeric($innerValue) && !is_array($innerValue))
                $decodedData = json_decode($innerValue,true);
            else
                $decodedData  = false;

            if($decodedData)
                $previousRecord[$innerKey] = $decodedData;
            else
                $previousRecord[$innerKey] = $innerValue;
        }

        $returnData['recent_data']  = History::getCommonHistoryUpdation($recentRecord,$inputArray['model_name']);
        $returnData['changed_data'] = History::getCommonHistoryUpdation($previousRecord,$inputArray['model_name']);
        $returnData['is_new_record'] = $isNewRecord;
        $returnResponse['data']    = $returnData;
        return $returnResponse;
    }
    public static function convertMinDays($mins){                
        $hours = str_pad(floor($mins /60),2,"0",STR_PAD_LEFT);
        $mins  = str_pad($mins %60,2,"0",STR_PAD_LEFT);
        if((int)$hours > 24){
            $days = str_pad(floor($hours /24),2,"0",STR_PAD_LEFT);
            $hours = str_pad($hours %24,2,"0",STR_PAD_LEFT);
        }
        $time = '';
        if(isset($days)) { $time .= (int)$days." day(s) ";}        
	    if(isset($hours) && (int)$hours > 0) { $time .= (int)$hours. " hr(s) "; }
	    if(isset($mins) && (int)$mins > 0) { $time .= (int)$mins. " min(s)"; }
        return $time;
    }

    //get Date format based on config;
    public static function globalDateFormat($date,$format=''){
        if(!$format)
            $format = config('common.date_format');
        $dateFormat =  \Carbon\Carbon::parse($date)->format($format);
        return $dateFormat;
    }

    public static function getOwnerUserId($accountID)
    {
        $accountOwnerUserId = 0;
        $UserId = AccountDetails::where('account_id',$accountID)->value('account_id');
        if(!empty($UserId))
            return $UserId;
        else
            return $accountOwnerUserId;
    }

    public static function getExchangeRate($fromCurrency,$toCurrency)
    {

        $exchangeRate       = 1;

        $aCurrencyExchangeRateDetails = CurrencyExchangeRate::select('supplier_account_id',
                                                                            'consumer_account_id',
                                                                            'exchange_rate_from_currency',
                                                                            'exchange_rate_to_currency',
                                                                            'exchange_rate_equivalent_value', 
                                                                            'exchange_rate_percentage',
                                                                            'exchange_rate_fixed')
                                                                        ->where('supplier_account_id', 0)
                                                                        ->where('consumer_account_id', 0)
                                                                        ->where('exchange_rate_from_currency', $fromCurrency)
                                                                        ->where('exchange_rate_to_currency', $toCurrency)
                                                                        ->where('status', 'A')
                                                                        ->get();

        if(isset($aCurrencyExchangeRateDetails) && !empty($aCurrencyExchangeRateDetails)){
            $aCurrencyExchangeRateDetails   = $aCurrencyExchangeRateDetails->toArray();
 
            if(isset($aCurrencyExchangeRateDetails[0]['exchange_rate_equivalent_value'])){
                
                $exchnageRate       = $aCurrencyExchangeRateDetails[0]['exchange_rate_equivalent_value'];
                $exchnageRatePer    = $aCurrencyExchangeRateDetails[0]['exchange_rate_percentage'];
                $exchnageRateFix    = $aCurrencyExchangeRateDetails[0]['exchange_rate_fixed'];

                $exchangeRate   = $exchnageRate + $exchnageRateFix + (($exchnageRate / 100) * $exchnageRatePer);
            }
        }

        return $exchangeRate;
    }

     public static function convertAmount($fromCurrency,$toCurrency,$amount)
    {
        $exchangeRate = self::getExchangeRate($fromCurrency,$toCurrency);
        
        $returnAmount  = self::getRoundedFare($amount*$exchangeRate);
        
        return array('returnAmount' => $returnAmount, 'exchangeRate' => $exchangeRate);
    }

    //Date Parse
    public static function dateParseDatepicker($date){
        return date('m/d/Y h:i A',strtotime($date));
    }

    public static function mysqlDateParse($date){
        return date('Y-m-d',strtotime($date));
    }

    public static function phpDateParse($date){
        return date('m/d/Y',strtotime($date));
    }

    // Get rounded fare
    public static function getTicketRoundedFare($amt=0,$roundTo=2,$currency='')
    {
        return round($amt,$roundTo);
        //return round($amt,$roundTo);
    }

    // Get rounded fare
    public static function formatFopDetails($fopDetails)
    {
        
        if(isset($fopDetails['FopKey'])){
            
            unset($fopDetails['FopKey']);
        }
        
        if(!isset($fopDetails['CC'])){
            
            $fopDetails['CC'] = array
                                (
                                    'Allowed' => 'N',
                                    'Types' => array()
                                );
        }
        
        if(!isset($fopDetails['DC'])){
            
            $fopDetails['DC'] = array
                                (
                                    'Allowed' => 'N',
                                    'Types' => array()
                                );
        }
        
        if(!isset($fopDetails['CHEQUE'])){
            
            $fopDetails['CHEQUE'] = array
                                    (
                                        'Allowed' => 'Y',
                                        'Types' => array()
                                    );
        }
        
        if(!isset($fopDetails['CASH'])){
            
            $fopDetails['CASH'] = array
                                    (
                                        'Allowed' => 'Y',
                                        'Types' => array()
                                    );
        }
        
        foreach($fopDetails as $fopMainKey=>$fopMainVal){
            
            if(isset($fopMainVal['Types'])){
                
                foreach($fopMainVal['Types'] as $fopTypeKey=>$fopTypeVal){
                
                    $fopDetails[$fopMainKey]['Types'][$fopTypeKey]['F']['BookingCurrencyPrice'] = isset($fopDetails[$fopMainKey]['Types'][$fopTypeKey]['F']['BookingCurrencyPrice']) ? self::getTicketRoundedFare($fopDetails[$fopMainKey]['Types'][$fopTypeKey]['F']['BookingCurrencyPrice']) : 0;
                    
                    $fopDetails[$fopMainKey]['Types'][$fopTypeKey]['F']['EquivCurrencyPrice'] = isset($fopDetails[$fopMainKey]['Types'][$fopTypeKey]['F']['EquivCurrencyPrice']) ? self::getTicketRoundedFare($fopDetails[$fopMainKey]['Types'][$fopTypeKey]['F']['EquivCurrencyPrice']) : 0;
                    
                    $fopDetails[$fopMainKey]['Types'][$fopTypeKey]['P'] = isset($fopDetails[$fopMainKey]['Types'][$fopTypeKey]['P']) ? self::getTicketRoundedFare($fopDetails[$fopMainKey]['Types'][$fopTypeKey]['P']) : 0;
                }
            }
            else{
                $fopDetails[$fopMainKey]['Types'] = array();
            }
            
            $fopDetails[$fopMainKey]['Types'] = (Object)$fopDetails[$fopMainKey]['Types'];
        }
        
        return $fopDetails;
    }
    
    public static function generateInvoice($str='inv_', $m='000', $n){
        return $str.$m.$n;
    }

    public static function userBasedGetTimeZone($inputArray)
    {
        //Get Time Zone
        $userID = CustomerDetails::getCustomerUserId($inputArray);
        if(isset($userID) && $userID != 0)
        {
            $TimeZone = self::getViewTimeZone($userID,'user');
        }
        else
        {
            $TimeZone = self::getViewTimeZone(0,'portal'); 
        }
        return $TimeZone;
    }

    //common function to view timezone
    public static function getViewTimeZone($id=0,$type=''){
        $timezone = config('common.registration_time_zone');
        //get user timezone        
        if($type == 'user'){
            $userId = $id;
            $userDetails = CustomerDetails::select('portal_id','timezone')->where('user_id',$userId)->first();
            if(isset($userDetails) && !empty($userDetails)){
                //timezone for user
                if(isset($userDetails->timezone) && !empty($userDetails->timezone)){
                    $timezone = $userDetails->timezone;
                }
                else{
                    //get portal timezone
                    $getPortalConfigData = PortalDetails::getPortalConfigData($userDetails->portal_id);
                    if(isset($getPortalConfigData['timezone']) && !empty($getPortalConfigData['timezone']))
                        $timezone = $getPortalConfigData['timezone'];
                }//eo if
            }//eo if
        }else{
            //get portal timezone
            $portalId = $id;
            $getPortalConfigData = PortalDetails::getPortalConfigData($portalId);
            if(isset($getPortalConfigData['timezone']) && !empty($getPortalConfigData['timezone']))
                $timezone =  $getPortalConfigData['timezone']; 
        }//eo else
        
        return $timezone;
    }//eof


    public static function getExchangeRateGroup($aSupIds = array(),$aConId){

        $aTempSupIds = array_merge($aSupIds,array(0));

        //Get PG Exchange Rate
        $aCurrencyExchangeRateDetails = CurrencyExchangeRate::select('supplier_account_id',
                                                                    'consumer_account_id',
                                                                    'exchange_rate_from_currency',
                                                                    'exchange_rate_to_currency',
                                                                    'exchange_rate_equivalent_value', 
                                                                    'exchange_rate_percentage',
                                                                    'exchange_rate_fixed')
                                                                ->whereIn('supplier_account_id', $aTempSupIds)
                                                                //->where('exchange_rate_from_currency', $convertedCurrency)
                                                                ->where('status', 'A')
                                                                ->where(function ($query) use ($aConId) {
                                                                            $query->where('consumer_account_id', 0)
                                                                                ->orWhere(DB::raw("FIND_IN_SET('".$aConId."',consumer_account_id)"),'>',0);
                                                                        })
                                                                ->orderBy('supplier_account_id', 'asc')
                                                                ->get();

        if(isset($aCurrencyExchangeRateDetails) && !empty($aCurrencyExchangeRateDetails)){
            $aCurrencyExchangeRateDetails = $aCurrencyExchangeRateDetails->toArray();

            //Log::info(print_r($aCurrencyExchangeRateDetails,true));

            $aExchangeRateList      = array();
            $aBookingCurrencyChk    = array();
            $consumerChecking[]     = array();
            $exchangeRateAry        = array();

            foreach($aCurrencyExchangeRateDetails as $exchangeKey => $exchangeValue) {
                if($exchangeValue['supplier_account_id'] == 0 && $exchangeValue['consumer_account_id'] == 0 ){

                    $exchnageRate       = $exchangeValue['exchange_rate_equivalent_value'];
                    $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                    $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                    $calcExchangeRate   = $exchnageRate + $exchnageRateFix + (($exchnageRate / 100) * $exchnageRatePer);

                    $fromCurrency   = $exchangeValue['exchange_rate_from_currency'];
                    $toCurrency     = $exchangeValue['exchange_rate_to_currency'];
                    $currencyIndex  = $fromCurrency.'_'.$toCurrency;

                    $exchangeRateAry[$currencyIndex] = $calcExchangeRate;

                    foreach ($aSupIds as $subIdkey => $subIdvalue) {
                        $aExchangeRateList[$subIdvalue][$currencyIndex] = $calcExchangeRate; 
                    } 
                }else if($exchangeValue['supplier_account_id'] > 0){

                    $emsAccountId   = $exchangeValue['supplier_account_id'];
                    $fromCurrency   = $exchangeValue['exchange_rate_from_currency'];
                    $toCurrency     = $exchangeValue['exchange_rate_to_currency'];
                    $currencyIndex  = $fromCurrency.'_'.$toCurrency;
                    $curChkIndex    = $fromCurrency.'_'.$toCurrency.'_'.$emsAccountId;

                    if(isset($exchangeRateAry[$currencyIndex])){
                        
                        $exchnageRate       = $exchangeRateAry[$currencyIndex];
                        $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                        $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                        $calcExchangeRate   = $exchnageRate + $exchnageRateFix + (($exchnageRate / 100) * $exchnageRatePer);

                        $aExchangeRateList[$emsAccountId][$currencyIndex] = $calcExchangeRate;
                    }
                    else{
                        $aExchangeRateList[$emsAccountId][$currencyIndex] = 1;
                    }
 
            }
        }

        return $aExchangeRateList;
    }
    
    return [];

    }

    public static function getPassengerIdForTicket($passengersArray,$engineTicketResponse)
    {
        $passengerID = 0;
        if(!empty($passengersArray))
        {
            foreach ($passengersArray as $key => $value) {

                $paxName = (isset($value['first_name']) && $value['first_name'] != '' ? strtoupper($value['first_name']) : '').(isset($value['middle_name']) && $value['middle_name'] != '' ? ' '.strtoupper($value['middle_name']) : '').(isset($value['salutation']) && $value['salutation'] != '' ? ' '.strtoupper($value['salutation']) : '');

                if(strtoupper($engineTicketResponse['FirstName']) == $paxName && strtoupper($engineTicketResponse['LastName']) == strtoupper($value['last_name']))
                    return $value['flight_passenger_id'];
            }
        }
        return $passengerID;
    }
    
    public static function sentenceCase($string) {
        $sentences = preg_split('/([.?!]+)/', $string, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE);
        $new_string = '';
        foreach ($sentences as $key => $sentence) {
            $new_string .= ($key & 1) == 0?
                ucfirst(strtolower(trim($sentence))) :
                $sentence.' ';
        }
        return trim($new_string);
    }//eof

    public static function getTokenUser($request){

        $requestHeaders     = $request->headers->all();

        $url = url('/').'/api/users';

        $headers = array();
        $headers['Authorization'] = isset($requestHeaders['authorization'][0]) ? $requestHeaders['authorization'][0] : '';
        $headers['portal-origin'] = isset($requestHeaders['portal-origin'][0]) ? $requestHeaders['portal-origin'][0] : '';

        // $bType = 'B2B';

        // $reqData = $request->all();

        // if(isset($reqData['siteDefaultData'])){
        //     $bType = $request->siteDefaultData['business_type'];
        // }

        if($request->siteDefaultData['business_type'] == 'B2B'){
            $checkUser = Common::getHttpRq($url, $headers);
        }else{
            $url = url('/').'/api/customers';
            $checkUser = Common::getHttpRq($url, $headers);
        }

        $checkUser = json_decode($checkUser,true);

        return $checkUser;

    }

    public static function getCommonDetails($data)
    {
        $data['created_by']             = UserDetails::getUserName($data['created_by'],'yes');
        $data['updated_by']             = UserDetails::getUserName($data['updated_by'],'yes');
        return $data;
    }

    public static function getStateNamebyCode($stateCode, $countryCode){
        $stateDetails = StateDetails::where('state_code', $stateCode)->where('country_code', $countryCode)->where('status', 'A')->value('name');
        $stateName = '';
        if(!empty($stateDetails)){
            $stateName = preg_replace('/[^A-Za-z ]/', '', $stateDetails);
        } 
        return $stateName;
    }

    public static function getPortalConfig($portalId = 0){
        $portalConfigData = [];

        $portalConfigData['account_id']                = 3 ;#Common::getApiAccountId();
        $portalConfigData['b2b_account_id']            = 3 ; #Common::getApiAccountId();
        $portalConfigData['portal_id']                 = 10 ; #Common::getPortalId();
        $portalConfigData['b2b_portal_id']             = 10 ; #Common::getPortalId();
        $portalConfigData['portal_url']                = config('common.default_portal_url');
        $portalConfigData['default_payment_gateway']   = config('common.default_payment_gateway');
        $portalConfigData['portal_fop_type']           = config('common.portal_fop_type');

        $portalConfigData['portal_default_currency']   = 'CAD';
        $portalConfigData['prime_country']             = 'CA';
        $portalConfigData['allow_hold']                = config('common.allow_hold');

        //get portal timezone
        $portalConfigData['portal_timezone']           = config('common.server_timezone');
        $portalConfigData['promo_max_discount_price']           = config('limit.promo_max_discount_price');
        
        $portalDetails = PortalDetails::where('portal_id',$portalId )->first();

        if($portalDetails && !empty($portalDetails) && $portalDetails->business_type == 'META' && $portalDetails->parent_portal_id != 0){
             $portalDetails = PortalDetails::where('portal_id',$portalDetails->parent_portal_id)->first();
        }

        if($portalDetails){
            $accountDetails = AccountDetails::where('account_id', $portalDetails->account_id)->first();
            $portalConfigData['account_id']                = $accountDetails->account_id;
            $portalConfigData['b2b_account_id']            = $accountDetails->b2b_account_id;
            $portalConfigData['portal_id']                 = $portalDetails->portal_id;
            $portalConfigData['portal_name']               = $portalDetails->portal_name;
            $portalConfigData['b2b_portal_id']             = $portalDetails->b2b_portal_id;
            $portalConfigData['portal_url']                = $portalDetails->portal_url;
            $portalConfigData['portal_default_currency']   = $portalDetails->portal_default_currency;
            $portalConfigData['prime_country']             = $portalDetails->prime_country;

            //get portal timezone
            $getPortalConfigData = self::getPortalConfigData($portalId);
            
            $portalConfigData['portal_timezone']           = (isset($getPortalConfigData['timezone']) && $getPortalConfigData['timezone'] != '') ? $getPortalConfigData['timezone'] : config('common.server_timezone');
            $portalConfigData['promo_max_discount_price']          = (isset($getPortalConfigData['promo_max_discount_price']) && $getPortalConfigData['promo_max_discount_price'] != '') ? $getPortalConfigData['promo_max_discount_price'] : config('limit.promo_max_discount_price');

            $portalConfigData['default_payment_gateway']   = isset($getPortalConfigData['default_payment_gateway']) ? $getPortalConfigData['default_payment_gateway'] : config('common.default_payment_gateway');
            $portalConfigData['portal_fop_type']           = isset($getPortalConfigData['fop_type']) ? $getPortalConfigData['fop_type'] : config('common.portal_fop_type');
            $portalConfigData['allow_hold']                = isset($getPortalConfigData['allow_hold']) ? $getPortalConfigData['allow_hold'] : config('common.allow_hold');

            //get user for portal's account
            $portalConfigData['allCustomers'] = CustomerDetails::getAllCustomerByAccount($accountDetails->account_id);
        } 

        return $portalConfigData;      
    }//eof


    public static function getMrmsConfig($accountId){
        $returnArray = [];
        if($accountId != 0)
        {
           
            $mrmsConfig = self::getAgencySetting($accountId);

            if(empty($mrmsConfig)){

                $ownAccount = AccountDetails::where('account_id', $accountId)->first();
                $agencyB2BAccessUrl = isset($_SERVER['HTTP_HOST'])?$_SERVER['HTTP_HOST']:'';
                
                if($ownAccount){
                    $agencyB2BAccessUrl = $ownAccount->agency_b2b_access_url;
                }

                if($agencyB2BAccessUrl != ''){
                    $parentAccount = AccountDetails::where('agency_b2b_access_url', $agencyB2BAccessUrl)->where('parent_account_id', 0)->where('account_id','!=',1)->first();
                    if($parentAccount){
                        $parentAccountId = $parentAccount->account_id;
                        $mrmsConfig = self::getAgencySetting($parentAccountId);
                    }            
                }

            }
           
           if(isset($mrmsConfig) && !empty($mrmsConfig) && isset($mrmsConfig['mrms_config']) && !empty($mrmsConfig['mrms_config']))
           {
                $returnArray = json_decode($mrmsConfig['mrms_config'],true);
           }
           else
            {
                $returnArray = config('common.mrms_api_config');
            } 
        }
        else
        {
            $returnArray = config('common.mrms_api_config');
        }
        return $returnArray;
    }//eof


    public static function getAgencySetting($accountId){
        $agencySetting = AgencySettings::where('agency_id', $accountId)->first();
        if($agencySetting){
            return $agencySetting->toArray();
        }else{
            return [];
        }

    }

    public static function stringTruncate($string, $length)
    {
         return (strlen($string) > $length) ? substr($string, 0, $length): $string;
    }
    public static function isValidXml($content)
    {
        $content = trim($content);
        if (empty($content)) {
            return false;
        }

        if (stripos($content, '<!DOCTYPE html>') !== false) {
            return false;
        }

        libxml_use_internal_errors(true);
        simplexml_load_string($content);
        $errors = libxml_get_errors();          
        libxml_clear_errors();  

        return empty($errors);
    }

    public static function getMRSMSessionId(){
        return md5(session()->getId('clarity_otp_b2b_sess'));
    }

    public static function removeNamespaceFromXML( $xml ){
        // Because I know all of the the namespaces that will possibly appear in 
        // in the XML string I can just hard code them and check for 
        // them to remove them
        $toRemove = ['rap', 'turss', 'crim', 'cred', 'j', 'rap-code', 'evic'];
        // This is part of a regex I will use to remove the namespace declaration from string
        $nameSpaceDefRegEx = '(\S+)=["\']?((?:.(?!["\']?\s+(?:\S+)=|[>"\']))+.)["\']?';

        // Cycle through each namespace and remove it from the XML string
       foreach( $toRemove as $remove ) {
            // First remove the namespace from the opening of the tag
            $xml = str_replace('<' . $remove . ':', '<', $xml);
            // Now remove the namespace from the closing of the tag
            $xml = str_replace('</' . $remove . ':', '</', $xml);
            // This XML uses the name space with CommentText, so remove that too
            $xml = str_replace($remove . ':commentText', 'commentText', $xml);
            // Complete the pattern for RegEx to remove this namespace declaration
            $pattern = "/xmlns:{$remove}{$nameSpaceDefRegEx}/";
            // Remove the actual namespace declaration using the Pattern
            $xml = preg_replace($pattern, '', $xml, 1);
        }
        // Return sanitized and cleaned up XML with no namespaces
        return $xml;
    }

    public static function xmlToArray($xml)
    {
        // One function to both clean the XML string and return an array
        return json_decode(json_encode(simplexml_load_string(self::removeNamespaceFromXML($xml))), true);
    }

    public static function httpRequestForSoapXml($url, $postData = [], $headers = [], $reqType = 'SOAP'){

        // if(count($headers) > 0){
        //  $httpHeader = array_merge($httpHeader,$headers);
        // }

        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        curl_setopt($ch, CURLOPT_POST, true);

        if($reqType == 'XML'){
            curl_setopt($ch, CURLOPT_POSTFIELDS, "xmlstring=".$postData);
        }else{
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
            //curl_setopt($ch, CURLOPT_USERPWD, $soapUser.":".$soapPassword); // username and password - declared at the top of the doc
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData); // the SOAP request
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        curl_close($ch);

        return $response;
    }

    //get portalconfig data by portal id
    public static function getPortalConfigData($portal_id){
        $returnData = '';

        $portalDetails = PortalDetails::where('portal_id',$portal_id)->first();
        if($portalDetails && !empty($portalDetails) && $portalDetails->business_type == 'META' && $portalDetails->parent_portal_id != 0){
             $portal_id = $portalDetails->parent_portal_id;
        }

        $portalConfigData = PortalConfig::where('portal_id',$portal_id)->where('status','A')->value('config_data');
        if(isset($portalConfigData) && $portalConfigData != ''){
            $unserialize = unserialize($portalConfigData);
            $returnData = $unserialize['data'];
        }
        return $returnData;
    }//eof


    //XML to Array
    public static function xmlstrToArray($xmlstr)
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xmlstr);
        return self::domNodeToArray($doc->documentElement);
    }

    public static function domNodeToArray($node) 
    {
        $output = array();
        switch ($node->nodeType) {
            case XML_CDATA_SECTION_NODE:
            case XML_TEXT_NODE:
                $output = trim($node->textContent);
                break;
            case XML_ELEMENT_NODE:
                for ($i=0, $m=$node->childNodes->length; $i<$m; $i++) { 
                    $child = $node->childNodes->item($i);
                    $v = self::domNodeToArray($child);
                    if(isset($child->tagName)) {
                        $t = $child->tagName;
                        $t1 = explode(":",$t);
                        if(isset($t1[1])){
                            $t = $t1[1];
                        }
                        if(!isset($output[$t])) {
                            $output[$t] = array();
                        }
                        $output[$t][] = $v;
                    }
                    elseif($v) {
                        $output = (string) $v;
                    }
                }
                if($node->attributes->length && !is_array($output)) {
                    $output = array('content'=>$output);
                }
                if(is_array($output)) {
                    if($node->attributes->length) {
                        $a = array();
                        foreach($node->attributes as $attrName => $attrNode) {
                            $a[$attrName] = (string) $attrNode->value;
                        }
                        $output['attributes'] = $a;
                    }
                    foreach ($output as $t => $v) {
                        if(is_array($v) && count($v)==1 && $t!='attributes') {
                            $output[$t] = $v[0];
                        }
                    }
                }
            break;
        }
        return $output;
    }

    public static function getChequeNumber($chequeNumber = ''){
        if($chequeNumber != ''){
            return str_pad($chequeNumber, config('flight.lpad_min_length_cheque_number'), "0", STR_PAD_LEFT);
        }
        return $chequeNumber;        

    }

    public static function getAgencyTitle( $accountId = 0){ 

        $outputArray = [];
        $outputArray['appName']         = config('app.name');
        $outputArray['appTitle']        = config('app.name');
        $outputArray['appShortName']    = config('app.name');
        $outputArray['touchIcon']       = 'images/apple-icon.png';
        $outputArray['favicons']        = 'images/favicon.png';
        $outputArray['miniLogo']        = 'images/logo-mini.png';
        $outputArray['largeLogo']       = 'images/logo-mini.png';
        $outputArray['loginBg']         = 'images/bg3.jpg';
        $outputArray['companyUrl']      = config('common.company_url');
        $outputArray['footerName']      = __('footer.copyright_company');
        $outputArray['b2bAccessUrl']    = config('app.url');
        $outputArray['appAccountId']    = 0;
        $outputArray['agencyPhoneNo']   = '';
        $outputArray['regardsAgencyPhoneNo']   = '';

        $agencyB2BAccessUrl = isset($_SERVER['HTTP_HOST'])?$_SERVER['HTTP_HOST']:'';
        if($agencyB2BAccessUrl != '' && $accountId == 0 ){

            if($agencyB2BAccessUrl == 'netfareshub.com'){
                $outputArray['loginBg']     = 'images/bg3.jpg';
            }
            $accountDetails = AccountDetails::where('agency_b2b_access_url', $agencyB2BAccessUrl)->where('parent_account_id', 0)->where('status','A')->first();
        }
        
        if($accountId != 0){
            $accountDetails = AccountDetails::where('account_id', $accountId)->first();
        }          

        if(isset($accountDetails) && $accountDetails){
            $outputArray['agencyPhoneNo']   = $accountDetails->agency_phone;
            $outputArray['emailAddress'] = $accountDetails->agency_email;
            $outputArray['appName'] = config('app.name');
            //to get formated phone number in regards content
            if((isset($accountDetails->agency_mobile_code) && $accountDetails->agency_mobile_code !='') && (isset($accountDetails->agency_phone) && $accountDetails->agency_phone !='')) {
                $outputArray['regardsAgencyPhoneNo']   =  Common::getFormatPhoneNumberView($accountDetails->agency_mobile_code,$accountDetails->agency_phone);
            }else{
                $outputArray['regardsAgencyPhoneNo']   = '';
            }//eo else

            $agencySettings = AgencySettings::where('agency_id',$accountDetails->account_id)->first();

            if($agencySettings && !empty($agencySettings) && isset($agencySettings->email_configuration_default) && $agencySettings->email_configuration_default == 1) {
                $outputArray['mailConfigUserName']      = config('portal.email_config_username');
                $outputArray['mailConfigPassword']      = config('portal.email_config_password');
                $outputArray['mailConfigHost']          = config('portal.email_config_host');
                $outputArray['mailConfigPort']          = config('portal.email_config_port');
                $outputArray['mailConfigEncryption']    = config('portal.email_config_encryption');
            }
            elseif ($agencySettings && !empty($agencySettings)) {
                $outputArray['mailConfigUserName']      = $agencySettings->email_config_username;
                $outputArray['mailConfigPassword']      = $agencySettings->email_config_password;
                $outputArray['mailConfigHost']          = $agencySettings->email_config_host;
                $outputArray['mailConfigPort']          = $agencySettings->email_config_port;
                $outputArray['mailConfigEncryption']    = $agencySettings->email_config_encryption;
            }

            $outputArray['appName']         = $accountDetails->agency_name;
            $outputArray['appTitle']        = $accountDetails->agency_name;
            $outputArray['appShortName']    = $accountDetails->short_name;
            // $outputArray['footerName']   = $accountDetails->agency_name;
            $outputArray['appAccountId']    = $accountDetails->account_id;

            // if($accountDetails->agency_url != ''){
            //     $outputArray['companyUrl'] = $accountDetails->agency_url;
            // }

            $agencyLogoSavedLocation        = config('common.agency_logo_storage_location');
            $gcs                            = Storage::disk($agencyLogoSavedLocation);

            if($accountDetails->agency_mini_logo != ''){
                if($accountDetails->agency_logo_saved_location == 'gcs'){
                    $outputArray['touchIcon']  =  $gcs->url('uploadFiles/agency/'.$accountDetails->agency_mini_logo);
                    $outputArray['favicons']   =  $gcs->url('uploadFiles/agency/'.$accountDetails->agency_mini_logo);
                    $outputArray['miniLogo']   =  $gcs->url('uploadFiles/agency/'.$accountDetails->agency_mini_logo);                        
                }else{
                    $outputArray['touchIcon']   = 'uploadFiles/agency/'.$accountDetails->agency_mini_logo;
                    $outputArray['favicons']    = 'uploadFiles/agency/'.$accountDetails->agency_mini_logo;
                    $outputArray['miniLogo']    = 'uploadFiles/agency/'.$accountDetails->agency_mini_logo;
                }
                                    
            }else{
               $outputArray['miniLogo'] = ''; 
            }

            if($accountDetails->agency_logo != ''){
                if($accountDetails->agency_logo_saved_location == 'gcs'){
                    $outputArray['largeLogo'] =  $gcs->url('uploadFiles/agency/'.$accountDetails->agency_logo);
                }else{
                    $outputArray['largeLogo'] = 'uploadFiles/agency/'.$accountDetails->agency_logo;
                }
            }

            if($accountDetails->agency_b2b_access_url != ''){
                if(config('app.secure_https')){
                    $outputArray['b2bAccessUrl'] = 'https://'.$accountDetails->agency_b2b_access_url;
                }else{
                    $outputArray['b2bAccessUrl'] = 'http://'.$accountDetails->agency_b2b_access_url;
                }
            }
        } 
        return $outputArray;
    }


    //globalDayDateFormat
    public static function globalDayDateFormat($dateTime){
        $dayDateFormat =  \Carbon\Carbon::parse($dateTime)->format(config('common.day_and_date_format'));
        return $dayDateFormat;
    }

    //globalDayWithDateFormat
    public static function globalDayWithDateFormat($dateTime){
        $dayDateFormat =  \Carbon\Carbon::parse($dateTime)->format(config('common.day_with_date_format'));
        return $dayDateFormat;
    }

    public static function getParentAccountDetails($accountId){

        $getAccount = AccountDetails::where('account_id',$accountId)->first();
        $returnData = [];
        if($getAccount){
            if(isset($getAccount['parent_account_id']) && ( $getAccount['parent_account_id'] == 0 || $getAccount['parent_account_id'] == '')){
                $returnData = $getAccount->toArray();
            }
            elseif(isset($getAccount['parent_account_id']) && $getAccount['parent_account_id'] != 0){        
                $returnData = AccountDetails::where('account_id',$getAccount['parent_account_id'])->first()->toArray();
            }
        }
        if(count($returnData) == 0){
            $returnData = AccountDetails::where('account_id',config('common.supper_admin_account_id'))->first()->toArray();
        }
        return $returnData;

        
    }//eof

    public static function handelRouteInfo($routeInfo)
    {
        if(!empty($routeInfo)) {
            $returnData = [];
            foreach ($routeInfo as $key => $value) 
            {
                $outerArray = [];
                $outerArray['full_routing'] = (isset($value['full_routing']) && $value['full_routing'] != '' && !is_null($value['full_routing'])) ? $value['full_routing'] : 'N'; 
                if(isset($value['route_info'])) {
                    foreach ($value['route_info'] as $innerKey => $innerValue) {
                        $innerArray = [];
                        $innerArray['origin'] = (isset($innerValue['origin']) && $innerValue['origin'] != '' && !is_null($innerValue['origin'])) ? ( is_array($innerValue['origin']) ? implode(',',$innerValue['origin']) : $innerValue['origin']) : null ; 
                        $innerArray['destination'] = (isset($innerValue['destination']) && $innerValue['destination'] != '' && !is_null($innerValue['destination'])) ? ( is_array($innerValue['destination']) ? implode(',',$innerValue['destination']) : $innerValue['destination']) : null ; 
                        $innerArray['marketing_airline'] = (isset($innerValue['marketing_airline']) && $innerValue['marketing_airline'] != '' && !is_null($innerValue['marketing_airline'])) ? ( is_array($innerValue['marketing_airline']) ? implode(',',$innerValue['marketing_airline']) : $innerValue['marketing_airline']) : null ; 
                        $innerArray['operating_airline'] = (isset($innerValue['operating_airline']) && $innerValue['operating_airline'] != '' && !is_null($innerValue['operating_airline'])) ? ( is_array($innerValue['operating_airline']) ? implode(',',$innerValue['operating_airline']) : $innerValue['operating_airline']) : null ; 
                        $innerArray['booking_class'] = (isset($innerValue['booking_class']) && $innerValue['booking_class'] != '' && !is_null($innerValue['booking_class'])) ? ( is_array($innerValue['booking_class']) ? implode(',',$innerValue['booking_class']) : $innerValue['booking_class']) : null ; 
                        $innerArray['operating_flight_number'] = (isset($innerValue['operating_flight_number']) && $innerValue['operating_flight_number'] != '' && !is_null($innerValue['operating_flight_number'])) ? ( is_array($innerValue['operating_flight_number']) ? implode(',',$innerValue['operating_flight_number']) : $innerValue['operating_flight_number']) : null ; 
                        $innerArray['marketing_flight_number'] = (isset($innerValue['marketing_flight_number']) && $innerValue['marketing_flight_number'] != '' && !is_null($innerValue['marketing_flight_number'])) ? ( is_array($innerValue['marketing_flight_number']) ? implode(',',$innerValue['marketing_flight_number']) : $innerValue['marketing_flight_number']) : null ;
                        $outerArray['route_info'][] = $innerArray;
                    }
                }
                else
                {
                    $innerArray = [];
                    $innerArray['origin'] = null; 
                    $innerArray['destination'] = null; 
                    $innerArray['marketing_airline'] = null; 
                    $innerArray['operating_airline'] = null; 
                    $innerArray['booking_class'] = null; 
                    $innerArray['operating_flight_number'] = null; 
                    $innerArray['marketing_flight_number'] = null;
                    $outerArray['route_info'][] = $innerArray;
                }
                $returnData[] = $outerArray;
            }
            $returnData = json_encode($returnData);
        }
        else
        {
            $returnData = '[{"full_routing":"N","route_info":[{"origin":null,"destination":null,"marketing_airline":null,"operating_airline":null,"booking_class":null,"operating_flight_number":null,"marketing_flight_number":null}]}]';
        }
        return $returnData;   
    }
    public static function airportDataBuild()
    {
        $countryArr         = array();
        $stateArr           = array();
        $buildAirportArr    = array();

        //get country details array
        $getCountryDetails  = CountryDetails::whereIn('status', ['A'])->get()->toArray();
         if(count($getCountryDetails) > 0){
            foreach ($getCountryDetails as $countrykey => $countryVal) {
                $countryArr[$countryVal['country_code']]    = $countryVal['country_name'];
            }
        }       

        //get state details array
        $getStateDetails    = DB::table(config('tables.country_details').' As cd')
        ->select('sd.state_code', 'sd.name as state_name', 'sd.country_code', 'cd.country_name')
        ->join(config('tables.state_details').' As sd', 'sd.country_code', '=', 'cd.country_code')
        ->get()->toArray();
        if(count($getStateDetails) > 0){
            foreach ($getStateDetails as $stateKey => $stateVal) {
                $sCode          = $stateVal->state_code;
                $cCode          = $stateVal->country_code;
                $stateArr[$cCode.'-'.$sCode]   = $stateVal->state_name;
            }
        }            

        //build airport master json
        $getAllAirports     = AirportMaster::whereIn('status', ['A'])->orderBy('airport_iata_code', 'ASC')->get()->toArray();
        if(count($getAllAirports) > 0){
            foreach ($getAllAirports as $key => $value) {
                $isoRegionCode  = isset($value['iso_region_code']) ? explode('-', $value['iso_region_code']) : array();
                $countryCode    = isset($isoRegionCode[0]) ? $isoRegionCode[0] : '';
                $stateCode      = isset($isoRegionCode[1]) ? $isoRegionCode[1] : '';
                $stateName      = isset($stateArr[$countryCode.'-'.$stateCode]) ? $stateArr[$countryCode.'-'.$stateCode] : '';
                $countryName    = isset($countryArr[$countryCode]) ? $countryArr[$countryCode] : '';

                $buildAirportArr[$value['airport_iata_code']] = $value['airport_iata_code'].'|'.$value['airport_name'].'|'.$value['city_name'].'|'.$stateCode.'|'.$stateName.'|'.$countryCode.'|'.$countryName;
            }
        }

        $file_path          = storage_path('airportcitycode.json');
        if(File::exists($file_path)){
            unlink($file_path);            
        }
        if(!File::exists($file_path)) {
            file_put_contents($file_path, json_encode($buildAirportArr));
        }

        return true;
    }

    public static function getPortalOsTicketConfig($id,$bookingInfo = [],$flag = 'portal')
    {
        $returnArray = array();
        
        if($flag == 'portal')
        {
            $returnArray = [];
            $portalId = $id;
            $portalDetails = PortalDetails::where('portal_id',$portalId)->first();
            if($portalDetails && !empty($portalDetails) && $portalDetails->business_type == 'META' && $portalDetails->parent_portal_id != 0){
                 $portalId = $portalDetails->parent_portal_id;
            }
            
            $getPortalConfig = self::getPortalConfig($portalId);
            $accountId = $getPortalConfig['account_id'];
            $redisKey = 'portal_based_config_'.$portalId;

            $redisData = Common::getRedis($redisKey);
            if($redisData && !empty($redisData)){
                $portalData = json_decode($redisData,true);
            }else{
                $portalData = PortalConfig::getPortalBasedConfig($portalId, $accountId); 
                $redisData = Common::setRedis($redisKey, $portalData, config('flights.portal_config_redis_expire'));
            }
            $returnArray[$portalId] = isset($portalData['data']['osticket']) ? $portalData['data']['osticket'] : [];        
        }
        else
        {
            $accountId = $id;
            $osConfigFromAgency = AgencySettings::where('agency_id',$accountId)->value('osticket_config_data');
            if(isset($osConfigFromAgency) && !empty($osConfigFromAgency))
            {
                $osConfig[$accountId] = json_decode($osConfigFromAgency,true);
            }
            else
            {
                $osConfig[$accountId] = config('common.osticket');
            }
            if(isset($bookingInfo['supplier_wise_booking_total']) && !empty($bookingInfo['supplier_wise_booking_total'])){
                foreach ($bookingInfo['supplier_wise_booking_total'] as $sKey => $supplierTotal) {

                    if(isset($osConfig[$supplierTotal['supplier_account_id']]))continue;

                    $osConfigFromAgency = AgencySettings::where('agency_id',$supplierTotal['supplier_account_id'])->value('osticket_config_data');
                    if(isset($osConfigFromAgency) && !empty($osConfigFromAgency))
                    {
                        $configData = json_decode($osConfigFromAgency,true);
                        $uniqueFlag = true;
                        foreach ($osConfig as $aID => $details) {
                            if($configData == $details){
                                $uniqueFlag = false;
                            }
                        }
                        if($uniqueFlag){
                            $osConfig[$supplierTotal['supplier_account_id']] = $configData;
                        }                    
                    }
                }
                $returnArray = $osConfig;           
            }
        }
        return $returnArray;
    }

    public static function idFormatting($id,$configType = 'invoice'){
        if($configType == 'invoice'){
            $zero       = config('common.zeroval');
            $mainString = config('common.invoiceformat');
        }else{
            $zero       = config('flights.paymentzeroval');
            $mainString = config('flights.paymentinvoiceformat');
        }
       
        $idCount    = strlen($id);  
        $returnId   = $mainString.substr($zero,$idCount).$id;
        return $returnId;
    }

    public static function uploadMasterFiles(){
        $airline = AirlinesInfo::getAirlinesDetails();
        $airlineFile = Storage::disk('storage')->get('airlines.json');
        Storage::disk('custom-ftp')->put('airlines.json', $airlineFile);

        $buildAirport = AirportMaster::airportDataBuild();
        $airportFile = Storage::disk('storage')->get('airportcitycode.json');
        Storage::disk('custom-ftp')->put('airport.json', $airportFile);

        $countryDetailsJson     = CountryDetails::countryDetailsJson();
        $countryFile            = Storage::disk('storage')->get('country.json');
        Storage::disk('custom-ftp')->put('country.json', $countryFile);

        $stateDetailsJson       = StateDetails::stateDetailsJson();
        $stateFile            = Storage::disk('storage')->get('state.json');
        Storage::disk('custom-ftp')->put('state.json', $stateFile);

        $currencyDetailsJson     = CurrencyDetails::currencyDetailsJson();
        $currencyFile            = Storage::disk('storage')->get('currency_details.json');
        Storage::disk('custom-ftp')->put('currency_details.json', $currencyFile);
    }

}