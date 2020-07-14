<?php

namespace App\Models\UserACL;

use App\Models\Model;
use App\Models\UserRoles\UserRoles;
use DB;

class RolePermissionMapping extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.role_permission_mapping');
    }

    protected $primaryKey = 'role_permission_id';

    public $timestamps = false;

     protected $fillable = [
        'role_id', 'permission_id'];


    public static function getMappedPermissions($roleId = []){

        $roleId = explode(',', $roleId);

        $roleId[] = UserRoles::getRoleIdBasedCode('CU');

    	$mappedPermissions = DB::table(config('tables.permissions').' as p')
            ->leftjoin(config('tables.role_permission_mapping').' as rpm', 'p.permission_id', '=', 'rpm.permission_id')
	        ->leftjoin(config('tables.user_roles').' as ur', 'ur.role_id', '=', 'rpm.role_id')
            ->whereIn('rpm.role_id', $roleId)
            ->where('p.status', '=', 'A')
	        ->where('ur.status', '=', 'A')
            ->where('p.menu_id', '!=', '0')            
            ->orWhere(function($query){ return $query->where('p.is_public', '=', 'Y')->whereNotNull('p.permission_url'); })
	        ->pluck('p.permission_route','p.permission_id')->toArray();

        return $permissions =  explode(',', implode(',', $mappedPermissions));
    }

    public static function getMappedPermissionsRoute($roleId = []){

        $roleId = explode(',', $roleId);

        $roleId[] = UserRoles::getRoleIdBasedCode('CU');

        $mappedPermissions = DB::table(config('tables.permissions').' as p')
            ->leftjoin(config('tables.role_permission_mapping').' as rpm', 'p.permission_id', '=', 'rpm.permission_id')
            ->leftjoin(config('tables.user_roles').' as ur', 'ur.role_id', '=', 'rpm.role_id')
            ->whereIn('rpm.role_id', $roleId)
            ->where('p.status', '=', 'A')
            ->where('ur.status', '=', 'A')
            ->where('p.menu_id', '!=', '0')            
            ->orWhere(function($query){ return $query->where('p.is_public', '=', 'Y')->whereNotNull('p.permission_url'); })                        
            ->pluck('p.permission_route','p.permission_url')->toArray();

        return $mappedPermissions;
    }
}
