<?php
//use Storage;

function encryptData($value)
{
	if($value==''){
		return $value;
	}

	$encodedValue=bin2hex(Encode($value));
		
	if(strlen($encodedValue) < 8){
		$encodedValue = substr(mt_rand(),0,(8-(strlen($encodedValue)+2))).'!!'.$encodedValue;
	}

	$encodedValue=base64_encode($encodedValue);

	return $encodedValue;
	
}

function decryptData($encodedValue)
{
	if($encodedValue==''){
	 	return $encodedValue;
	}

	$encodedValue=base64_decode($encodedValue);	

	$encodedValue=explode("!!",$encodedValue);

	$encodedValue=isset($encodedValue[1]) ? $encodedValue[1] : $encodedValue[0];		
	
	$decodedValue=Encode(_hex2bin($encodedValue));
	return $decodedValue;
	
}

//Encoder function	
	function Encode($data)
	{
		
		$pwd	=config('app.encryption_key');
		$Zcrypt = '';
		$x		= 0;
		$key	= array();
		$counter= array();
		$a		= 0;
		$j		= 0;
		
		$pwd_length = strlen($pwd);
	    for ($i = 0; $i < 256; $i++) 
        {
		    $key[$i] = ord(substr($pwd, ($i % $pwd_length)+1, 1));
		    $counter[$i] = $i;
	    }
	    
	    
	    for ($i = 0; $i < 256; $i++) 
        {
		    $x = ($x + $counter[$i] + $key[$i]) % 256;
		    $temp_swap = $counter[$i];
		    $counter[$i] = $counter[$x];
		    $counter[$x] = $temp_swap;
        

	    }
	    for ($i = 0; $i < strlen($data); $i++) 
        {
		    $a = ($a + 1) % 256;
		    $j = ($j + $counter[$a]) % 256;
		    $temp = $counter[$a];
		    $counter[$a] = $counter[$j];
		    $counter[$j] = $temp;
		    $k = $counter[(($counter[$a] + $counter[$j]) % 256)];
		    $Zcipher = ord(substr($data, $i, 1)) ^ $k;
		    $Zcrypt .= chr($Zcipher);
	    }
	    
	    return $Zcrypt;
	}

	function _hex2bin($hexdata) 
	{
		$bindata = '';
		
		for($i=0;$i<strlen($hexdata);$i+=2) 
	    {
   			$bindata.=chr(hexdec(substr($hexdata,$i,2)));
		}  
		return $bindata;
	}


    function getBrowser() 
    {   
        $u_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''; 
        $bname = 'Unknown';
        $ub = 'Unknown';
        $platform = 'Unknown';
        $version= "";

        //First get the platform?
        if (preg_match('/linux/i', $u_agent)) {
            $platform = 'Linux';
        }
        elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
            $platform = 'Mac';
        }
        elseif (preg_match('/windows|win32/i', $u_agent)) {
            $platform = 'Windows';
        }
        
        // Next get the name of the useragent yes seperately and for good reason
        if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent)) 
        { 
            $bname = 'Internet Explorer'; 
            $ub = "MSIE"; 
        } 
        elseif(preg_match('/Firefox/i',$u_agent)) 
        { 
            $bname = 'Mozilla Firefox'; 
            $ub = "Firefox"; 
        }
        elseif(preg_match('/Edge/i',$u_agent)) 
        { 
            $bname = 'Internet Explorer'; 
            $ub = "Edge"; 
        }
        elseif(preg_match('/Opera/i',$u_agent)) 
        { 
            $bname = 'Opera'; 
            $ub = "Opera"; 
        }
        elseif(preg_match('/OPR/i',$u_agent)) 
        { 
            $bname = 'Opera'; 
            $ub = "OPR"; 
        } 
        elseif(preg_match('/Chrome/i',$u_agent)) 
        { 
            $bname = 'Google Chrome'; 
            $ub = "Chrome"; 
        } 
        elseif(preg_match('/Safari/i',$u_agent)) 
        { 
            $bname = 'Apple Safari'; 
            $ub = "Safari"; 
        } 
        elseif(preg_match('/Netscape/i',$u_agent)) 
        { 
            $bname = 'Netscape'; 
            $ub = "Netscape"; 
        }elseif(preg_match("/(Trident\/(\d{2,}|7|8|9)(.*)rv:(\d{2,}))|(MSIE\ (\d{2,}|8|9)(.*)Tablet\ PC)|(Trident\/(\d{2,}|7|8|9))/", $u_agent, $match) != 0) {
            $bname = 'Internet Explorer'; 
            $ub = "rv";
        }
        
        // finally get the correct version number
        $known = array('Version', $ub, 'other');
        $pattern = '#(?<browser>' . join('|', $known) .
        ')[/: ]+(?<version>[0-9.|a-zA-Z.]*)#';
        if (!preg_match_all($pattern, $u_agent, $matches)) {
            // we have no matching number just continue
        }
        
        // see how many we have
        $i = count($matches['browser']);
        if ($i != 1) {
            //we will have two since we are not using 'other' argument yet
            //see if version is before or after the name
            if (strripos($u_agent,"Version") < strripos($u_agent,$ub)){
                if(isset($matches['version'][0])){
                    $version= $matches['version'][0];
                }
            }
            else {
                if(isset($matches['version'][1])){
                    $version= $matches['version'][1];
                }
            }
        }
        else {
            if(isset($matches['version'][0])){
                $version= $matches['version'][0];
            }
        }
        
        // check if we have a number
        if ($version==null || $version=="") {$version="?";}
        
        return array(
            'userAgent' 	=> $u_agent,
            'name'      	=> $bname,
            'version'   	=> $version,
            'platform'  	=> $platform,
            'pattern'    	=> $pattern,
            'userBrowser'	=> $ub,
        );
    }

    function browserCompatibility(){
    	$checkFlag = false;
    	$browser = getBrowser();
        $compatibility = config('common.browser_compatibility');
        if(isset($compatibility[$browser['platform']]) && isset($browser['userBrowser'])){
            if(!in_array($browser['userBrowser'], $compatibility[$browser['platform']])){
                $checkFlag = true;
            }else{
                $checkFlag = false;
            }
        }    
        // if($checkFlag && !isset($_COOKIE['browser_notification_count'])){
        //     setcookie('browser_notification_count', '1', time() + (86400 * config('common.browser_cookie_expiry_days')), "/");
        // }else{
        //     $checkFlag = false;
        // }
        return $checkFlag;
    } 

    //function to get browser or mobile
    function systemInfo()
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $os_platform    = "Unknown OS Platform";
        $os_array       = array('/windows phone 8/i'    =>  'Windows Phone 8',
                                '/windows phone os 7/i' =>  'Windows Phone 7',
                                '/windows nt 6.3/i'     =>  'Windows 8.1',
                                '/windows nt 6.2/i'     =>  'Windows 8',
                                '/windows nt 6.1/i'     =>  'Windows 7',
                                '/windows nt 6.0/i'     =>  'Windows Vista',
                                '/windows nt 5.2/i'     =>  'Windows Server 2003/XP x64',
                                '/windows nt 5.1/i'     =>  'Windows XP',
                                '/windows xp/i'         =>  'Windows XP',
                                '/windows nt 5.0/i'     =>  'Windows 2000',
                                '/windows me/i'         =>  'Windows ME',
                                '/win98/i'              =>  'Windows 98',
                                '/win95/i'              =>  'Windows 95',
                                '/win16/i'              =>  'Windows 3.11',
                                '/macintosh|mac os x/i' =>  'Mac OS X',
                                '/mac_powerpc/i'        =>  'Mac OS 9',
                                '/linux/i'              =>  'Linux',
                                '/ubuntu/i'             =>  'Ubuntu',
                                '/iphone/i'             =>  'iPhone',
                                '/ipod/i'               =>  'iPod',
                                '/ipad/i'               =>  'iPad',
                                '/android/i'            =>  'Android',
                                '/blackberry/i'         =>  'BlackBerry',
                                '/webos/i'              =>  'Mobile');
        $found = false;
        //$addr = new RemoteAddress;
        $device = '';
        foreach ($os_array as $regex => $value) 
        { 
            if($found)
             break;
            else if (preg_match($regex, $user_agent)) 
            {
                $os_platform    =   $value;
                $device = !preg_match('/(windows|mac|linux|ubuntu)/i',$os_platform)
                          ?'MOBILE':(preg_match('/phone/i', $os_platform)?'MOBILE':'SYSTEM');
            }
        }
        $device = !$device? 'SYSTEM':$device;
        return array('os'=>$os_platform,'device'=>$device);
    }//eof

?>