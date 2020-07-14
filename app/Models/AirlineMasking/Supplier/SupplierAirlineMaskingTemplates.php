<?php
namespace App\Models\AirlineMasking\Supplier;
use App\Models\Model;
use Lang;
use App\Libraries\Common;
use Auth;
use App\Http\Middleware\UserAcl;

class SupplierAirlineMaskingTemplates extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.supplier_airline_masking_templates');
    }

    protected $primaryKey = 'airline_masking_template_id';

    protected $fillable = [
        'account_id','template_name','status'
    ];

}
