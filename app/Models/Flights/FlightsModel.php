<?php
namespace App\Models\Flights;
use App\Models\Model;
use DB;

class FlightsModel extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.booking_master');
    }

    protected $primaryKey = 'booking_master_id';
    public $timestamps = false;

    public static function getPortalCredentials($portalId = ''){
		$results = DB::table(config('tables.portal_credentials'))
            ->join(config('tables.portal_details'), 'portal_credentials.portal_id', '=', 'portal_details.portal_id')
            ->select('portal_credentials.auth_key', 'portal_details.prime_country', 'portal_details.portal_name', 'portal_details.portal_default_currency', 'portal_details.portal_id','portal_details.agency_name','portal_details.iata_code','portal_details.agency_email', 'portal_credentials.is_branded_fare')
            ->where('portal_details.status', 'A' )
            ->where('portal_credentials.external_api','!=', 'Y')            
            ->where('portal_credentials.status', 'A' );

            // ->where('portal_credentials.is_meta', 'N' ) // Regarding Meta portal pricing allow select meta porta credentails using meta portal

        if($portalId != ''){
        	$results->where('portal_details.portal_id', $portalId );
        }

        $results = $results->get()->toArray();
        return $results;
	}

    public static function getPortalCredentialsForLFS($acountId, $bType = 'B2B'){
          $results = DB::table(config('tables.portal_details'))
                        ->join(config('tables.portal_credentials'), 'portal_credentials.portal_id', '=', 'portal_details.portal_id')
                        ->select('portal_credentials.auth_key', 'portal_details.portal_name', 'portal_details.portal_selling_currencies', 'portal_details.portal_default_currency', 'portal_details.portal_id','portal_details.agency_name','portal_details.iata_code','portal_details.agency_email','portal_details.insurance_setting', 'portal_credentials.is_branded_fare')
                        ->where('portal_details.account_id', $acountId)
                        ->where('portal_details.business_type', $bType)
                        ->where('portal_details.status', 'A')
                        ->where('portal_credentials.external_api','!=', 'Y')
                        ->where('portal_credentials.status', 'A');

      $results = $results->get()->toArray();
      return $results;
    }

}
