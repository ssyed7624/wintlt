<?php

namespace App\Models\UserRoles;

use App\Models\Model;
use Auth;

class UserRoles extends Model 
{
    public function getTable()
    {
       return $this->table = config('tables.user_roles');
    }

    protected $primaryKey = 'role_id';

    protected $fillable = [
        'role_id', 'role_name', 'role_code', 'account_type_id', 'account_id', 'access_type', 'is_sign_up', 'status', 'created_by', 'created_at'
    ];

    public static function getRoleIdBasedCode($roleCode)
    {
        $roleId = self::where('role_code',$roleCode)->value('role_id');
        return $roleId;
    }

    public static function getUserRoleName($userRoleId)
    {
        $roleName = UserRoles::where('role_id',$userRoleId)->value('role_name');
        if(!empty($roleName))
        {
            return $roleName;
        }else{
            return '';
        }
    }
    
    //get user detail based on role
    public static function getUserDetailBasedOnRole(){

        $getRoles = [];

        $getRoleID = UserRoles::where('role_id',Auth::user()->role_id)->first();

        if($getRoleID){
            $roleCode = $getRoleID['role_code'];
        }

        if($roleCode == 'AO'){
            $getRoles = config('common.owner_allowed_roles');
        }else if($roleCode == 'MA'){
            $getRoles = config('common.manager_allowed_roles');
        }else if($roleCode == 'AG'){
            $getRoles = config('common.agent_allowed_roles');
        }

        $getRoleIds = UserRoles::whereIn('status',['A','IA']);
        if(!empty($getRoles)){
           $getRoleIds = $getRoleIds->whereIn('role_code',$getRoles); 
        }
        $getRoleIds = $getRoleIds->pluck('role_id');

        return $getRoleIds;
    }//eof

    public static function agentRoleChecking($agentBasedRoles,$loggedUserRoles)
    {
        $returnArray = false;
        $result = [];
        foreach ($agentBasedRoles as $key => $value) {
            if(isset($loggedUserRoles[$value]))
            {
                unset($loggedUserRoles[$value]);
            }
        }
        if(empty($loggedUserRoles))
        {            
            $returnArray = true;
        }
        return $returnArray;
    }

    public static function getRoleDetailRecord($whereColumn,$whereValue,$columnName){
        return UserRoles::where($whereColumn,$whereValue)->value($columnName);
    }//eof

    public static function getRoleDetails(){
        return UserRoles::select('role_name', 'role_id')->where('status', 'A')->where('is_sign_up', '1')->get()->toArray();
    }
    
    public static function getRoleId(){
        if(Auth::user()){
             return Auth::user()->role_id;
        }else{
            return 0;
        }
    }

}   
