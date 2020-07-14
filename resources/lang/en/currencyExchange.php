<?php

    /*
    |--------------------------------------------------------------------------
    | To get all Currency Exchange Rate values in language
    |--------------------------------------------------------------------------
    */

    return[

        //  Required field validation
        'exchange_rate_id_required'                 =>  'The exchange rate id is required',
        'supplier_account_id_required'              =>  'The supplier account id is required',
        'consumer_account_id_required'              =>  'The consumer account id is required',
        'exchange_rate_from_currency_required'      =>  'The exchange rate from is required',
        'exchange_rate_to_currency_required'        =>  'The exchange rate to is required',
        'exchange_rate_equivalent_value_required'   =>  'The exchange rate equivalent value is required',
        'type_required'                             =>  'The exchange rate type is required',
        'this_currency_exchange_already_exists'     =>  'This currency exchange is already exists',
        // Success Messages
        'currency_exchange_rate_store_success'      =>  'Currency exchange rate settings added success',
        'currency_exchange_rate_store_failed'       =>  'Currency exchange rate settings added failed',
        'currency_exchange_rate_updated_success'    =>  'Currency exchange rate settings updated successfully',
        'currency_exchange_rate_updated_failed'     =>  'Currency exchange rate settings updated failed',
        'currency_exchange_rate_deleted_success'    =>  'Currency exchange rate settings deleted successfully',
        'currency_exchange_rate_status_success'     =>  'Currency exchange rate settings status changed successfully',
        'currency_exchange_rate_data_success'       =>  'Currency exchange rate settings retrived successfully',
        'currency_exchange_rate_data_failed'        =>  'Currency exchange rate settings retrived failed',
    ];