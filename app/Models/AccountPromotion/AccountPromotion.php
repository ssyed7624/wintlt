<?php

namespace App\Models\AccountPromotion;
use App\Models\Model;
use App\Models\AccountDetails\AccountDetails;
use Storage;


class AccountPromotion extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.account_promotion');
    }
    
    protected $primaryKey = 'promotion_id';
    
    protected $fillable = [
    	'account_id','image','image_saved_location','image_original_name','timeout','status','created_by','updated_by','created_at','updated_at'
    ];

    public static function getAccountPromotion($accountId)
    {
        $portalPromotion = self::where('account_id', $accountId)->where('status', 'A')->get();        
        if(count($portalPromotion) == 0){
            $getAccount = AccountDetails::where('account_id',$accountId)->first();
            $parentAccountId = ($getAccount->parent_account_id != 0)? $getAccount->parent_account_id:$getAccount->account_id;        
            $portalPromotion = self::where('account_id', $parentAccountId)->where('status', 'A')->get();
        }       
        $returnData = []; 
        if(!empty($portalPromotion) && count($portalPromotion) > 0)
        {
            foreach ($portalPromotion as $key => $value) {
              $gcs  = Storage::disk($value['image_storage_location']);
              if($value['image_saved_location'] == 'local'){
                  $value['image']        = "uploadFiles/promotionImages/".$value['image'];
                  $returnData[$key]['image'] = '<img src="'.asset($value['image']).'" class="img-fluid" />';
              }else{
                  $value['image']        = $gcs->url('uploadFiles/promotionImages/'.$value['image']);
                  $returnData[$key]['image'] = '<img src="'.$value['image'].'" class="img-fluid" />';
              }
              $returnData[$key]['timeout'] = $value['timeout'];
            }
        }
        return $returnData;
    }
    
}
