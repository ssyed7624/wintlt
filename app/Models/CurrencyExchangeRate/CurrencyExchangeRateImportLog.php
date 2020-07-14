<?php

namespace App\Models\CurrencyExchangeRate;

use App\Models\Model;

class CurrencyExchangeRateImportLog extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.currency_exchange_rate_import_log');
    }

    protected $primaryKey = 'currency_exchange_rate_import_log_id';

    protected $fillable = ['imported_by','imported_agency','imported_original_file_name','imported_saved_file_name','imported_file_location','created_at','updated_at'];
}
