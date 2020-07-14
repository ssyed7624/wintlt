<?php

namespace App\Models\Common;

use App\Models\Model;
use Illuminate\Support\Facades\File;
use DB;

class CurrencyDetails extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.currency_details');
    }

    protected $primaryKey = 'currency_id';
    public $timestamps = false;

    protected $fillable = [
        'currency_code','exchange_rate','country_code','status'
    ];

    public static function getCurrencyDisplayCode($currencyCode){
    	$currency      = DB::table(config('tables.currency_details'))->select('display_code')->where('currency_code', $currencyCode)->first();
    	if(isset($currency->display_code)){    		
    		return $currency->display_code;
    	}else{    		
    		return '';
    	}
    }

    public static function getCurrencyDetails()
    {
        $currencyDetails = CurrencyDetails::select('currency_id','currency_code','exchange_rate','country_code','display_code')->where('status','A')->orderBy('currency_code')->get()->toArray();

        return $currencyDetails;
    }//eof

    public static function currencyDetailsJson()
    {
        $currencyDetails = CurrencyDetails::select('country_code','currency_code','display_code')->where('status','A')->orderBy('currency_code')->get()->toArray();

        $file_path          = storage_path('currency_details.json');
        if(File::exists($file_path)){
            unlink($file_path);            
        }
        if(!File::exists($file_path)) {
            file_put_contents($file_path, json_encode($currencyDetails));
        }

        return $currencyDetails;
    }//eof
}
