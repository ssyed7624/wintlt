<?php

namespace App\Models\Surcharge\Supplier;
use App\Models\Model;
use App\Libraries\EMSCommon as Common;

class SupplierSurcharge extends Model
{
    //use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    public function getTable()
    { 
       return $this->table = config('tables.supplier_surcharge_details');
    }

    protected $primaryKey = 'surcharge_id';

    protected $fillable =       [
        'account_id',
        'product_type',
        'surcharge_name',
        'surcharge_code',
        'surcharge_type',
        'currency_type',
        'calculation_on',
        'surcharge_amount',
        'criterias',
        'selected_criterias',
        'selected_criterias',
        'status',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];

    public static function getSurchargeDetails($id){
        $aSurcharge = SupplierSurcharge::where('surcharge_id',$id)->get()->toArray();
        return $aSurcharge[0];
    }

    public static function getSupplierSurchargeByCurrency($productType = '', $accountID = '', $currencyType = ''){
        $surchargesList = SupplierSurcharge::select('surcharge_id', 'surcharge_code','surcharge_name', 'currency_type')->whereIn('status', ['A'])->where('account_id',$accountID)->where('currency_type',$currencyType)->whereIn('status', ['A']);

        if($productType != ''){
            $surchargesList = $surchargesList->where('product_type', $productType);
        }

        $surchargesList = $surchargesList->get()->toArray();

        if(count($surchargesList) > 0){
            return $surchargesList;
        }

        return '';
    }
}
