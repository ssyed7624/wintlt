<?php

namespace App\Models\CustomerDetails;

use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use App\Models\PortalDetails\PortalDetails;
use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Support\Facades\Hash;
use App\Models\UserRoles\UserRoles;
use Laravel\Passport\HasApiTokens;
use App\Libraries\Common;
use App\Models\Model;
use Auth;

class CustomerDetails extends Model implements AuthenticatableContract, AuthorizableContract
{
    use HasApiTokens, Authenticatable, Authorizable;

    public $guard = 'customer';

    public function getTable()
    {
        return $this->table = config('tables.customer_details');
    }

    public function getTableColumns() {
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

    protected $primaryKey = 'user_id'; //Changed default primary key into our user_details table user_id.
    public $email = '';


    protected $fillable = [
    'user_name', 'email_id', 'b2b_user_id', 'alternate_email_id', 'alternate_contact_no', 'zipcode','user_groups', 'other_info', 'password','remember_token','role_id','account_id','title','first_name','last_name','mobile_code','mobile_no','phone_no','country','state','city','address_line_1','address_line_2','timezone','fare_info_display','status','created_by','updated_by','mobile_code_country','portal_id', 'provider','referred_by', 'event_id','user_ip','dob'
    ];


    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
    ];

    public function findForPassport($identifier) {

        $reqestUrl = isset($_SERVER['HTTP_PORTAL_ORIGIN'])?$_SERVER['HTTP_PORTAL_ORIGIN']:'';

        $reqestUrl = str_replace('www.','',$reqestUrl);

        $accountId = 0;

        $portalDetails = PortalDetails::where('portal_url',$reqestUrl)->where('status','A')->first();
        if($portalDetails){
            $accountId = $portalDetails->account_id;
        }

        return $this->select('customer_details.*')->where('email_id', $identifier)->join('account_details', 'account_details.account_id', '=', 'customer_details.account_id')->where('account_details.account_id', $accountId)->where('account_details.status', 'A')->where('customer_details.status', 'A')->first();
    }

    public static function store($input){
       
        $input['created_at']      = Common::getDate();
        $input['updated_at']      = Common::getDate();

        $userId = CustomerDetails::create($input)->user_id;
        return $userId;
        
    }
    public function portal()
    {
    	return $this->hasone(PortalDetails::class,'portal_id','portal_id');
    }

    public static function getCustomerUserId($inputData)
    {        
        $userDetails = Common::getTokenUser($inputData);
        return isset($userDetails['user_id']) ? $userDetails['user_id'] : 0;
    }

    //to get all users from account
    public static function getAllCustomerByAccount($accountID=''){
        $returnData = [];
        if($accountID != ''){
            $userDetails = CustomerDetails::where('status','=','A')
            ->where('account_id', '=', $accountID)
            ->get()->toArray();
        }
        return $returnData;
    }//eof

    //get customer role
    public static function getCutsomerApiRoleId(){
        $roleId = 0;
        $customerRoleId = UserRoles::where('role_code','CU')->value('role_id');

        if(isset($customerRoleId) && $customerRoleId != ''){
           $roleId = $customerRoleId;
        }
        return $roleId;
    }//eof

}
