<?php

namespace App\Models\Common;

use App\Models\Model;
use Illuminate\Support\Facades\File;

class StateDetails extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.state_details');
    }

    protected $primaryKey = 'state_id';
    public $timestamps = false;

    protected $fillable = [
        'name','state_code','country_code','status'
    ];
    /*
	*Get state details by country code
    */
    public static function getStateListByCountryCode($countryCode){
    	$stateArr 	= array();
    	$getStates 	= StateDetails::where('country_code', $countryCode)->get()->toArray();
    	if(count($getStates) > 0){
    		foreach($getStates as $val){
    			$stateArr[$val['state_id']] = $val['name'];
    		}
    		return $stateArr;
    	}
    	return $stateArr;
    }

    public static function getStateByCodeListByCountryCode($countryCode){
    	$stateArr 	= array();
    	$getStates 	= StateDetails::where('country_code', $countryCode)->get()->toArray();
    	if(count($getStates) > 0){
    		foreach($getStates as $val){
    			$stateArr[$val['state_code']] = $val['name'];
    		}
    		return $stateArr;
    	}
    	return $stateArr;
    }

    //to get all state datas
    public static function getAllStateDetails()
    {
        $stateDetails = StateDetails::select('state_id','name','state_code')->where('status','A')->orderBy('name')->get()->toArray();
        return $stateDetails;
    }//eof


    public static function getState(){
        return StateDetails::all()->keyBy('state_id')->toArray();
    }

    public static function getStateById($stateId = null){
        $stateData = StateDetails::where('status','A')->where('state_id', $stateId)->first();
        if($stateData){
            return $stateData->toArray();
        }else{
            return [];
        }
    }

    public static function getConfigStateDetails($givenCountry = ''){  

        if($givenCountry == ''){
            $configDefaultCountryCode   = strtoupper(config('common.mobile_default_country_code'));
        }else{
            $configDefaultCountryCode   = $givenCountry;
        }
        
        $stateDetails = StateDetails::select('state_id','name','state_code')->where('country_code',$configDefaultCountryCode)->where('status','A')->orderBy('name')->get()->toArray();
        return $stateDetails;
    }//eof

    public static function getStateName($stateId)
    {
        $stateName = StateDetails::where('state_id',$stateId)->value('name');
        if($stateName)
            return $stateName;
        else
            return '';
    }


    //to get all state datas
    public static function stateDetailsJson()
    {
        $stateDetails = StateDetails::select('state_id','name','state_code','status','country_code')->where('status','A')->orderBy('name')->get()->toArray();

        $file_path          = storage_path('state.json');
        if(File::exists($file_path)){
            unlink($file_path);            
        }
        if(!File::exists($file_path)) {
            file_put_contents($file_path, json_encode($stateDetails));
        }

        return $stateDetails;
    }//eof

}
