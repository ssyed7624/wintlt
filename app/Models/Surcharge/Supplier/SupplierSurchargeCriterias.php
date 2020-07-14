<?php
namespace App\Models\Surcharge\Supplier;
use App\Models\Model;

class SupplierSurchargeCriterias extends Model
{
    //use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    public function getTable()
    { 
       return $this->table = config('tables.supplier_surcharge_criterias');
    }

    protected $primaryKey = 'surcharge_criteria_id';
}
