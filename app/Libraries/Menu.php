<?php

namespace App\Libraries;

use App\Models\Menu\MenuMappingDetails;

class Menu 
{
    public static function getMenu($accountId = 0)
	{
		$menuData  = MenuMappingDetails::getMenuData($accountId);
       
        $tempData  = array();
        $tempCheck = array();
        
        foreach($menuData as $key=>$value){			
            
            $check = $value['menu_id']."_".$value['submenu_id'];
            
            if(in_array($check,$tempCheck)){
				unset($menuData[$key]);
				continue;
            }

			$tempCheck[] = $check;
            
            if(!isset($tempData[$value['menu_id']]) && !empty($value['menu_order'])){
				unset($value['submenu_name']);
				unset($value['submenu_link']);
				unset($value['submenu_icon']);
				$tempData[$value['menu_id']] = $value;
            }
            
            if($value['submenu_parent_id']!=0){
				self::mapSubmenuInsideSubmenu($menuData,$value['submenu_parent_id'],$menuData[$key]);
			}
        }
        $finalData = array();

        $i = 1;
	
		foreach($tempData as $menuKey=>$menuValue){


			$menuValue['menu_name'] = __('menu.'.$menuValue['menu_name']);

			$finalData[$i]=$menuValue;
			foreach($menuData as $submenuKey=>$submenuValue){
				if($menuValue['menu_id'] == $submenuValue['menu_id'] && $submenuValue['submenu_parent_id'] == 0 && $submenuValue['submenu_id'] !=1 ){
					
					$submenuValue['menu_name'] 		= __('menu.'.$submenuValue['menu_name']);
					$submenuValue['submenu_name'] 	= __('menu.'.$submenuValue['submenu_name']);

					$finalData[$i]['submenus'][]=$submenuValue;
				}
			}

			$i++;
        }
        
        return $finalData;
            
    }

    public static function mapSubmenuInsideSubmenu(&$givenArray,$parentSubmenuId,$subArray)
	{
		foreach($givenArray as $key=>$value){
			if($value['submenu_id'] == $parentSubmenuId){
				if(!isset($givenArray[$key]['submenus'])){
					$givenArray[$key]['submenus'] = [];
				}
				$givenArray[$key]['submenus'][]=$subArray;
				$givenArray[$key]['parentSubmenu']='Y';
			}
		}
	} 
}