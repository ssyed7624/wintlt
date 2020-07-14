<?php

namespace App\Models\Common;

use App\Models\PortalDetails\PortalDetails;
use App\Libraries\Common;
use App\Models\Model;

class MetaLog extends Model
{

    public function getTable()
    {
        return $this->table 	= config('tables.meta_log');
    }

    protected $primaryKey 	= 'meta_log_id';

    public $timestamps 		= false;

    public static function saveMetaLog($metaName, $searchID, $shoppingResponseId, $itinID, $portalId, $ip, $msg = '', $status,$searchInput){
        $accountId = PortalDetails::where('portal_id',$portalId)->value('account_id');
        $metaLogModel                           = new MetaLog();
        $metaLogModel['product_rsource']        = $metaName;
        $metaLogModel['search_id']              = $searchID;
        $metaLogModel['shopping_response_id']   = $shoppingResponseId;
        $metaLogModel['offer_id']               = implode('-', $itinID);
        $metaLogModel['portal_id']              = $portalId;
        $metaLogModel['account_id']             = $accountId;
        $metaLogModel['ip']                     = $ip;
        $metaLogModel['message']                = $msg;
        $metaLogModel['search_input']           = json_encode($searchInput);
        $metaLogModel['redirect_id']            = $searchInput['redirectId'];
        $metaLogModel['redirect_info']          = $searchInput['redirectInfo'];
        $metaLogModel['status']                 = $status;
        $metaLogModel['created_by']             = 1;
        $metaLogModel['created_at']             = Common::getDate();
        $metaLogModel->save();        
    }
    
}
