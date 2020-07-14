<?php

namespace App\Models\UserACL;

use App\Models\Model;
use App\Models\Common\AccountDetails;
use Auth;
use DB;

class UserExtendedAccess extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.user_extended_access');
    }

    protected $primaryKey = 'user_extended_access_id';
    public $timestamps = false;
    protected $fillable = [
        'user_id', 'account_id', 'reference_id','role_id','account_type_id','access_type','is_primary'
    ];

    public static function getAgentRoles()
    {
        $userId = Auth::user()->user_id;
        $userExtendedDetails = [];
        $userExtendedDetails = UserExtendedAccess::where('user_id',$userId)->pluck('role_id','role_id');
        if(!empty($userExtendedDetails))
        {
            $userExtendedDetails = $userExtendedDetails->toArray();
        }
        return $userExtendedDetails;
    }


    public static function getUserExtendedAccess( $userId = 0 ){

        $accessInfo = UserExtendedAccess::where( config('tables.user_extended_access').'.user_id', $userId )
                        ->select(config('tables.user_extended_access').'.account_id', 'ad.account_name', 'ur.role_id', 'ur.role_code', 'ur.role_name',config('tables.user_extended_access').'.reference_id', config('tables.user_extended_access').'.is_primary')
                        ->join(config('tables.account_details').' as ad', 'ad.account_id', '=', config('tables.user_extended_access').'.account_id')
                        ->join(config('tables.user_roles').' as ur', 'ur.role_id', '=', config('tables.user_extended_access').'.role_id')
                        ->join(config('tables.user_details').' as ud', 'ud.user_id', '=', config('tables.user_extended_access').'.user_id')
                        ->where('ad.status', '=', 'A')
                        ->where('ud.status', '=', 'A')
                        ->where('ur.status', '=', 'A')
                        ->get()->toArray();

        return $accessInfo;


    }

}
