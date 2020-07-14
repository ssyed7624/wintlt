<?php
namespace App\Models\PortalDetails;
use App\Models\Model;

class PortalCredentials extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.portal_credentials');
    }

    public function getTableColumns() {
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

    protected $primaryKey = 'portal_credential_id';

    protected $fillable = [
        'portal_id','user_name','password','auth_key','session_expiry_time','allow_ip_restriction','allowed_ip','block','visible_to_portal','status', 'is_meta', 'product_rsource', 'max_itins', 'is_branded_fare', 'oneway_fares'
    ];

    public function getAllPortalCredentials(){
        return PortalCredentials::orderBy('updated_at','DESC')->get();
    }

    public function getAllCredentialsForPortal($portal_id){
        $portalDetails = PortalDetails::whereNotin('status',['D'])->where('portal_id',$portal_id)->first();
        if(isset($portalDetails) ){
                return PortalCredentials::with(['user'])->where('portal_id',$portal_id)->whereNotin('status',['D'])->orderBy('portal_credential_id','DESC')->get();
        }
    }

    public function user(){
        return $this->belongsTo('App\Models\UserDetails\UserDetails','created_by');
    }
    
    public static function getMetaList($rSourceOnly = false){
        $metaNameArr    = PortalCredentials::where('is_meta', 'Y');
        if($rSourceOnly){
            $metaNameArr = $metaNameArr->select('product_rsource');
        }
        $metaNameArr = $metaNameArr->whereIn('status', ['A', 'IA'])->get()->toArray();

        return $metaNameArr;
    }
}
