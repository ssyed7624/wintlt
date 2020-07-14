<?php

namespace App\Models\Menu;

use App\Models\Model;
use Auth;
use DB;

class MenuMappingDetails extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.menu_mapping_details');
    }

    protected $primaryKey = 'menu_mapping_id';

    public $timestamps = false;

    protected $fillable = [
        'role_id', 'menu_id', 'submenu_id', 'submenu_parent_id', 'menu_order', 'submenu_position', 'submenu_order', 'menu_status', 'submenu_status'];

    public static function getMenuData($accountId = 0)
    {
        // $roleIds 		    = isset(Auth::user()->role_id)?Auth::user()->role_id:0;

		$isSupplier         = isset(Auth::user()->is_supplier)?Auth::user()->is_supplier:0;

        $extendedData       = Auth::user()->extendedAccess;

        $authAccId          = isset(Auth::user()->account_id) ? Auth::user()->account_id:0;

        $roleIds            = Auth::user()->role_id;

        foreach ($extendedData as $key => $value) {

            if($accountId == $value['account_id'] || ($authAccId == $value['account_id'] && $roleIds == 0))
            {
                $roleIds = $value['role_id'];
            }
            
        }


        $mappedPermissions = DB::table(config('tables.permissions').' as p')
            ->select('p.menu_id', 'p.submenu_id')
            ->leftjoin(config('tables.role_permission_mapping').' as rpm', 'p.permission_id', '=', 'rpm.permission_id')
            ->leftjoin(config('tables.user_roles').' as ur', 'ur.role_id', '=', 'rpm.role_id')
            ->where('rpm.role_id', $roleIds)
            ->where('p.status', '=', 'A')
            ->where('ur.status', '=', 'A')
            ->where('p.menu_id', '!=', '0')            
            ->orWhere(function($query){ return $query->where('p.is_public', '=', 'Y')->whereNotNull('p.permission_url'); })                        
            ->get()->toArray();

        $menuIds    = [];
        $subMenuIds = [];

        foreach ($mappedPermissions as $key => $value) {
            $menuIds[$value->menu_id] = $value->menu_id;
            $subMenuIds[$value->submenu_id] = $value->submenu_id;
        }

        $menuIds = implode(',', array_filter($menuIds));
        $subMenuIds = implode(',', array_filter($subMenuIds));
        
        $menuTypeCriteria   = "";
        
        if($isSupplier == '0'){
			$menuTypeCriteria = " AND m.menu_type != 'S' AND sm.sub_menu_type != 'S' ";
        }
        $sqlQuery = "SELECT
                            m.menu_id,
                            m.menu_name,
                            m.new_link as link,
                            m.new_icon as icon,
                            m.menu_type,
                            sm.submenu_id,
                            sm.submenu_name,
                            sm.new_link as submenu_link,
                            sm.new_icon as submenu_icon,
                            mmd.submenu_parent_id,
                            mmd.menu_order,
                            mmd.submenu_position,
                            mmd.submenu_order
                    FROM
                            ".config('tables.menu_details')." m,
                            ".config('tables.submenu_details')." sm,
                            ".config('tables.menu_mapping_details')." mmd
                    WHERE
                            m.menu_id = mmd.menu_id
                            AND sm.submenu_id = mmd.submenu_id
                            AND m.status = mmd.menu_status
                            AND sm.status = mmd.submenu_status
                            AND m.status = 'Y'
                            AND sm.status = 'Y'".
                            $menuTypeCriteria.
                            " AND m.menu_id IN ({$menuIds})
                            AND sm.submenu_id IN ({$subMenuIds})
                            AND mmd.role_id = $roleIds                                                        
                            ORDER BY mmd.menu_order,mmd.submenu_position DESC,mmd.submenu_order";

                            // AND m.menu_id IN ({$menuIds})
                            // AND sm.submenu_id IN ({$subMenuIds})
                            //"AND mmd.role_id IN ({$roleIds})
       
        $menuData = DB::select($sqlQuery);
	    $menuData = json_decode(json_encode($menuData),true);
		
		return $menuData;

    }
}