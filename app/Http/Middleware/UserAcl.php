<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use App\Models\AccountDetail\AccountDetails;
use App\Models\UserRoles\UserRoles;

class UserAcl
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        return $next($request);
    }

    public static function canAccess($routeName = '', $roleId = 0 )
    {
		return true;
    }

    public static function isSuperAdmin($roleId = 0){

        if($roleId == 0 && !Auth::Guest())
            $roleId = Auth::user()->role_id;

        if(in_array($roleId, config('common.super_admin_roles')))
            return true;
        else
            return false;
    }

    public static function hasMultiAgencyAccess(){
        return self::hasMultiSupplierAccess(); // for engine
        //return false; // for other users
    }

    public static function hasMultiSupplierAccess(){
        
        if(!Auth::Guest()){
            if(self::isSuperAdmin()){                
                return true;
            }
            else{
                $suppliers = Auth::user()->extendedAccess;

                $accontDetails = array();

                foreach ($suppliers as $key => $value) {
                    $accontDetails[] =$value['account_id'];
                }
                
                if( count($accontDetails) > 0){
                    
                    if(count($accontDetails) == 1){
                        
                        list($firstAccountId) = $accontDetails;
                        
                        if($firstAccountId == Auth::user()->account_id){
                            return false;
                        }
                        else{
                            return true;
                        }
                    }
                    else{
                        return true;
                    }
                    
                }else{
                    return false;
                }
            }
        }
        else{
            return false;
        }
    }

    public static function hasMultipleAccess($accountTypeId = 0){

        if(!Auth::Guest()){

            if(self::isSuperAdmin()){                
                return true;
            }
            else{
                $accontDetails = [Auth::user()->account_id];
                $accessDetails = Auth::user()->extendedAccess;

                foreach ($accessDetails as $key => $value) {
                    $accontDetails[] =$value['account_id'];
                }

                $accontDetails = array_unique(array_filter($accontDetails));

                if(count($accontDetails) > 1){
                    return true;
                }
                else{
                    return false;
                }
            }
        }
        else{
            return false;
        }
    }
    
    public static function getAccessSuppliers(){
        
        if(!Auth::Guest()){
            if(self::isSuperAdmin()){                
                return [];
            }
            else{
                
                $supplierIds = [0];
                $suppliers   = Auth::user()->extendedAccess;

                foreach ($suppliers as $key => $value) {
                    $supplierIds[] =$value['account_id'];
                }
                
                return $supplierIds;
            }
        }
        else{
            return [0];
        }
    }

    public static function isMetaAgent(){

        if(!Auth::Guest()){
            $roleId = Auth::user()->role_id;
            $roles = UserRoles::where('role_id', $roleId)->first();
            if($roles){
                if($roles->role_code == 'MG'){
                   return true; 
                }
            }
        }
            return false;
    }
  
}
