<?php

namespace App\Models\UserGroupDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\Model;

class UserGroupDetails extends Model
{    
    public function getTable()
    { 
       return $this->table = config('tables.user_group_details');
    }
    protected $primaryKey = 'user_group_id';
    protected $fillable =   [
                                'user_group_id','account_id','portal_id','parent_group_id',   'group_code','group_name','status','created_by','updated_by','created_at','updated_at',
                            ]; 
    public function portal()
    {
    	return $this->hasone(PortalDetails::class,'portal_id','portal_id');
    }

    public function parentName()
    {
        return $this->hasone(UserGroupDetails::class,'user_group_id','parent_group_id');
    }

    public static function getUserGroups($flag='list')
    {
        
        $groupDetails = UserGroupDetails::where('status','A')->get()->keyBy('user_group_id')->toArray();

        $tempArray = array();
        if(empty($groupDetails)){
            $groupDetails = config('common.user_groups');
            foreach ($groupDetails as $groupId => $groupCode) {
                if($flag == 'dropdown')
                {
                    $temp['label'] = __('common.'.$groupCode) ;
                    $temp['value'] = $groupCode ;
                    $tempArray[] = $temp;
                }
                else{
                    $tempArray[$groupCode] = __('common.'.$groupCode);
                }
            }

        }else{
                    
            foreach ($groupDetails as $groupId => $gDetails) {
                $groupCode = $gDetails['group_code'];

                $parentName = isset($groupDetails[$gDetails['parent_group_id']]) ? ' - ('.$groupDetails[$gDetails['parent_group_id']]['group_name'].')': '';
                if($flag == 'dropdown')
                {
                    $temp['label'] = $gDetails['group_name'].$parentName ;
                    $temp['value'] = $groupCode ;
                    $tempArray[] = $temp;
                }
                else{
                    $tempArray[$groupCode] = $gDetails['group_name'].$parentName;
                }
            }
        }

        return $tempArray;
    }
       
}
