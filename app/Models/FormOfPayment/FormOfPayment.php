<?php

namespace App\Models\FormOfPayment;

use App\Models\Model;
use DB;

class FormOfPayment extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.form_of_payment');
    }

    protected $primaryKey = 'fop_id';

    protected $fillable = ['account_id','content_source_id','validating_airline','consumer_account_id','fop_details','status','created_by','updated_by','created_at','updated_at'];

    public function contentSource(){

        return $this->hasOne('App\Models\ContentSource\ContentSourceDetails','content_source_id','content_source_id');
    }
    public  function account(){
        return $this->belongsTo('App\Models\AccountDetails\AccountDetails','account_id','account_id');
    }
}
