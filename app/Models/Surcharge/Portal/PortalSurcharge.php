<?php

namespace App\Models\Surcharge\Portal;
use App\Models\Model;
use App\Libraries\Common;
use App\Models\PortalMarkupRules\PortalMarkupRuleSurcharges;

class PortalSurcharge extends Model
{
    //use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    public function getTable()
    { 
       return $this->table = config('tables.portal_surcharge_details');
    }

    protected $primaryKey = 'surcharge_id';

    public static function getAllActiveSurchargeDetails(){
        $acIds = Common::getAccountDetails(config('common.partner_account_type_id'), 0, true);
        return portalSurcharge::on(config('common.slave_connection'))->with(['user'])->with(['accountDetails'])
            ->whereHas('accountDetails' , function($query) { $query->whereNotIn('status', ['D']); })->whereIn('account_id',$acIds)
            ->whereNotin('status',['D'])->orderBy('updated_at','desc')->get()->toArray();
    }

    public static function getSurchargeDetails($id){
        $aSurcharge = portalSurcharge::where('surcharge_id',$id)->get()->toArray();
        return $aSurcharge[0];
    }

    public function user(){
        return $this->belongsTo('App\Models\UserDetails\UserDetails','created_by');
    }
    public function accountDetails(){
        return $this->belongsTo('App\Models\Common\AccountDetails','account_id');
    }

    public static function checkSurchargeMappingToMarkuprules($surchargeId){
        $checkSurchargeMapping = PortalMarkupRuleSurcharges::where('surcharge_id', $surchargeId)->first();

        return $checkSurchargeMapping;
    }
}
