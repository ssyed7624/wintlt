<?php

namespace App\Models\BookToRatio;

use App\Models\Model;
use Illuminate\Support\Facades\Redis;
use DB;

class BookToRatio extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.book_to_ratio');
    }

    protected $primaryKey = 'book_to_ratio_id';


    protected $fillable = ['account_id','supplier_id','consumer_id','book_ratio_allow','search_limit','allow_search_exceed', 'currency', 'charges', 'exceed_search_count','available_search_count', 'booking_count', 'total_searches' , 'status' ,'created_by','updated_by','created_at','updated_at'];

    public static function saveOrUpdateBooktoRatio($id){
        $bookingRatio = BookToRatio::find($id);
        $aBookToRatio = array();
        $data = array();
        if($bookingRatio->status == 'A'){
            $aBookToRatio['allowed'] = isset($bookingRatio->book_ratio_allow)?$bookingRatio->book_ratio_allow:'N';
            $aBookToRatio['search_limit'] = isset($bookingRatio->search_limit)?$bookingRatio->search_limit:'';
            $aBookToRatio['allow_search_exceed'] = isset($bookingRatio->allow_search_exceed)?$bookingRatio->allow_search_exceed:'N';
            $aBookToRatio['currency'] = (isset($bookingRatio->currency) && $bookingRatio->currency != null)?$bookingRatio->currency:'';
            $aBookToRatio['charges'] = (isset($bookingRatio->charges) && $bookingRatio->charges != null)?$bookingRatio->charges:'';
            $aBookToRatio['exceed_search_count'] = (isset($bookingRatio->exceed_search_count) && $bookingRatio->exceed_search_count != null)?$bookingRatio->exceed_search_count:'';
    
            $aBookToRatio['available_search_count'] = (isset($bookingRatio->available_search_count) && $bookingRatio->available_search_count != null)?$bookingRatio->available_search_count:0;
            
            $aBookToRatio['booking_count'] = (isset($bookingRatio->booking_count) && $bookingRatio->booking_count != null)?$bookingRatio->booking_count:0;
            $aBookToRatio['total_searches'] = (isset($bookingRatio->total_searches) && $bookingRatio->total_searches != null)?$bookingRatio->total_searches:0;
            $data[$bookingRatio->supplier_id.'_'.$bookingRatio->consumer_id] = $aBookToRatio;
        }

        $redisKey = 'lookToBookRatio';

        $getRedisData = Redis::get($redisKey);
        if(!empty($getRedisData)){
            $decodeRedisData = json_decode($getRedisData, true);            
            if($bookingRatio->status == 'A'){
                $decodeRedisData[$bookingRatio->supplier_id.'_'.$bookingRatio->consumer_id] = $aBookToRatio;
            } else {
                unset($decodeRedisData[$bookingRatio->supplier_id.'_'.$bookingRatio->consumer_id]);
            }
            if(is_array($decodeRedisData)){
                $data = json_encode($decodeRedisData);
            }   
            Redis::set($redisKey, $data);            
        } else {
            if(is_array($data)){
                $data = json_encode($data);
            }   
            Redis::set($redisKey, $data);            
        }        
    }

    public static function updateRedisData($lbtrArr){      
        $redisKey = 'lookToBookRatio';
        $getRedisData = Redis::get($redisKey);
        $decodeRedisData = array();
        if(!empty($getRedisData)){
            $decodeRedisData = json_decode($getRedisData, true);
        }   
        $bookingRatio = BookToRatio::whereIn('book_to_ratio_id', $lbtrArr)->get();
        if(count($bookingRatio) > 0){
            $bookingRatio = $bookingRatio->toArray();
        }
        
        
        foreach($bookingRatio as $lbtr){             
            $aBookToRatio = array();
            $data = array();
            if($lbtr['status'] == 'A'){
                $aBookToRatio['allowed'] = isset($lbtr['book_ratio_allow'])?$lbtr['book_ratio_allow']:'N';
                $aBookToRatio['search_limit'] = isset($lbtr['search_limit'])?$lbtr['search_limit']:'';
                $aBookToRatio['allow_search_exceed'] = isset($lbtr['allow_search_exceed'])?$lbtr['allow_search_exceed']:'N';
                $aBookToRatio['currency'] = (isset($lbtr['currency']) && $lbtr['currency'] != null)?$lbtr['currency']:'';
                $aBookToRatio['charges'] = (isset($lbtr['charges']) && $lbtr['charges'] != null)?$lbtr['charges']:'';
                $aBookToRatio['exceed_search_count'] = (isset($lbtr['exceed_search_count']) && $lbtr['exceed_search_count'] != null)?$lbtr['exceed_search_count']:'';        
                $aBookToRatio['available_search_count'] = (isset($lbtr['available_search_count']) && $lbtr['available_search_count'] != null)?$lbtr['available_search_count']:0;                
                $aBookToRatio['booking_count'] = (isset($lbtr['booking_count']) && $lbtr['booking_count'] != null)?$lbtr['booking_count']:0;
                $aBookToRatio['total_searches'] = (isset($lbtr['total_searches']) && $lbtr['total_searches'] != null)?$lbtr['total_searches']:0;
                $data[$lbtr['supplier_id'].'_'.$lbtr['consumer_id']] = $aBookToRatio;          
                $decodeRedisData[$lbtr['supplier_id'].'_'.$lbtr['consumer_id']] = $aBookToRatio;
            } else {
                unset($decodeRedisData[$lbtr['supplier_id'].'_'.$lbtr['consumer_id']]);
            }
        }              
        if(!empty($getRedisData)){
            if(is_array($decodeRedisData)){
                $data = json_encode($decodeRedisData);
            }               
            Redis::set($redisKey, $data);            
        } else {
            if(is_array($decodeRedisData)){
                $data = json_encode($decodeRedisData);
            }       
            Redis::set($redisKey, $data);            
        }  
        return 'Success';
    }
}