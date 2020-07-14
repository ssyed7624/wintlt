<?php

namespace App\Models\AirlineBlocking;

use App\Models\Model; 
use Apo\Models\PortalDetails\PortalDetails;

class SupplierAirlineBlockingTemplates extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.supplier_airline_blocking_templates');
    }

    protected $primaryKey = 'airline_blocking_template_id';

    protected $fillable = [
        'account_id',
        'template_name',
        'template_type',
        'partner_type',
        'status',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];

    public function getAllAirlineBlockingTemplates(){
        return SupplierAirlineBlockingTemplates::where('status','!=','D')->orderBy('updated_at','Desc')->get();
    }
    public static function getPartnerPortalAccount($partnerAcId){
        $getPortals = PortalDetails::select('portal_id', 'account_id', 'portal_name')->where('account_id', $partnerAcId)->where('status','A')->get()->toarray();
        return $getPortals;
    }
}
