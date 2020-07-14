<?php
namespace App\Models\Surcharge\Portal;
use App\Models\Model;

class PortalSurchargeCriterias extends Model
{
    //use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    public function getTable()
    { 
       return $this->table = config('tables.portal_surcharge_criterias');
    }

    protected $primaryKey = 'surcharge_criteria_id';
}
