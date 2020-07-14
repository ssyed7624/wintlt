<?php 
return [

    'allowed_ips'                               => ['127.0.0.1','*'],
    'allowed_ip_ranges'                         => ['127.0.0.1'],
    'restricted_ips'                            => [],
    'restricted_ip_ranges'                      => [],
    'agency_account_type_id'                    => '1',
    'agency_home_based_agency_type_id'          => '2',
    'supper_admin_user_id'                      => '1',
    'password_expiry_mins'                      => 60,
    'server_timezone'                           =>  'America/Toronto',
    'user_display_date_time_format'             => 'd M Y H:i:s',
    'registration_time_zone'                    => 'America/Toronto',
    'date_time_format'                          => 'd/m/Y H:i:s',
    'payment_mode_flight_url'                   =>  array('CL'=>'Credit Limit','FU'=>'Fund','CP'=>'Card Payment','CF'=>'Credit Limit Plus Fund','PC'=>'Pay By Cheque','AC'=>'ACH','BH' => 'Book & Hold','PG' => 'Payment Gateway'),
    'share_url_edit_flag_created_at_max'        => 3, //Hrs
    'share_url_edit_flag_last_ticketing_max'    => 3,
    // Banner Section Image Local Path
    'banner_section_save_path'                  =>'/uploadFiles/bannerSection/',
    'banner_section_storage_location'           =>'local',
    'hotel_place_limit'                         => 30,
    'api_criterias_id_min'                      => 545,
    'api_criterias_id_max'                      => 550,
    'role_codes'                                => [
                                                            'super_admin'       => 'SA',
                                                            'owner'             => 'AO',
                                                            'manager'           => 'MA',
                                                            'portal_admin'      => 'PA',
                                                            'corporate_admin'   => 'CA',
                                                            'agent'             => 'AG',
                                                            'home_agent'        => 'HA',
                                                            'customer'          => 'CU',
                                                    ],
    'super_admin_roles'                         => [1],
    'manager_allowed_roles'                     => ['MA','AG','HA','RE'],
    'agent_allowed_roles'                       => ['AG','HA','RE'],
    'home_agent_allowed_roles'                  => ['HA','RE'],
    'owner_allowed_roles'                       => ['AO','MA','AG','HA','RE'],
    'user_activation_email'                     => true,
    'extended_access_type_id'                   => '1',
    'extended_reference_id'                     => '1',

    
    //Content Source management start       
    'Products' => [
        'product_type'=>[ 
            'Flight' => [
                'Sabre'=> [
                    'api_name'    => 'Sabre',
                    'pcc_info'    => 'Y',
                    'has_offline' => 'Y',
                    'versions' => [
                        'v1' => 'V10.01',                       
                    ],                      
                    'pcc_type'=> [
                        'webservice_pcc' => 'WebService PCC',
                        'terminal_pcc'   => 'Terminal PCC',
                    ],
                    'api_mode'=>[
                        'test'=>'Test',
                        'cert'=>'Certification',
                        'live'=>'Live',
                    ],
                    'fare_types'=> [
                        'PUB'=> 'Published',
                        'PRI'=> 'JCB',
                        'ITX'=> 'ITX Fares',
                        /*'PFA'=> 'PFA Fares',
                        'COM'=> 'Combination Fares',*/
                    ],
                    'aggregaters'=> [
                      'ALL'=>'All',
                    ],
                    'services'=> [
                        'search'  => 'Search',
                        'price'   => 'Price',
                        'booking' => 'Booking',
                        'ticket'  => 'Ticket',
                    ],
                    'type'=> [
                        'ON'  => 'Online',
                        'OFF' => 'Offline',
                    ],
                    'credentials'=> [
                        'agent_id'      => 'Agent Id',
                        'user_id'       => 'User Id',
                        'group'         => 'GROUP',
                        'password'      => 'Password',
                        'domain'        => 'Domain',
                        'json_api_url'      => 'Json Api Url',
                        'xml_api_url'    => 'XML Api Url',
                    ],
                    'default_currencies' => [
                        'CAD'   => 'CAD',
                        'USD'   => 'USD',
                        'INR'   => 'INR',
                        'BHD'   => 'BHD',
                    ],
                    'allowed_currencies' => [
                        'CAD'   => 'CAD',
                        'USD'   => 'USD',
                        'INR'   => 'INR',
                        'BHD'   => 'BHD',
                    ],
                    'status' => [
                        'A'  =>'Active',
                        'IA' =>'InActive',
                    ],
                    'sector_mapping'    =>  'N',
                    'provider_code'   =>  [
                                            '1S'
                                          ],
                  ],
                  'Travelport'=> [
                    'api_name'    => 'Travelport',
                    'pcc_info'    => 'Y',
                    'has_offline' => 'N',
                    'versions' => [
                        'v1' => 'V10.01',                       
                    ],                      
                    'pcc_type'=> [
                        'webservice_pcc' => 'WebService PCC',
                        'terminal_pcc'   => 'Terminal PCC',
                    ],
                    'api_mode'=>[
                        'test'=>'Test',
                        'cert'=>'Certification',
                        'live'=>'Live',
                    ],
                    'fare_types'=> [
                        'PUB'=> 'Published',
                        'PRI'=> 'JCB',
                        'ITX'=> 'ITX', 
                    ],
                    'aggregaters'=> [
                      'ALL'=>'All',
                    ],
                    'services'=> [
                        'search'  => 'Search',
                        'price'   => 'Price',
                        'booking' => 'Booking',
                        'ticket'  => 'Ticket',
                    ],
                    'type'=> [
                        'ON'  => 'Online',
                        'OFF' => 'Offline',
                    ],
                    'credentials'=> [
                        'user_name'     => 'User Name',                       
                        'password'      => 'Password',
                        'target_branch' => 'Target Branch',
                        'provider'      => 'Provider',
                        'common_url'    => 'Common Api Url',
                        'ticket_url'    => 'Ticket Api Url',
                    ],
                    'default_currencies' => [
                        'CAD'   => 'CAD',
                        'USD'   => 'USD',
                        'INR'   => 'INR',
                        'BHD'   => 'BHD',
                    ],
                    'allowed_currencies' => [
                        'CAD'   => 'CAD',
                        'USD'   => 'USD',
                        'INR'   => 'INR',
                        'AUD'   => 'AUD',
                        'BHD'   => 'BHD',
                    ],
                    'status' => [
                        'A'  =>'Active',
                        'IA' =>'InActive',
                    ],
                    'sector_mapping'    =>  'N',
                    'provider_code'   =>  [
                                            '1V',
                                            '1P',
                                            '1G'
                                          ],                    
                  ],
                  'NdcAfKlm'=> [
                    'api_name'    => 'NDC AirFrance KLM',
                    'pcc_info'    => 'Y',
                    'has_offline' => 'N',
                    'versions' => [
                        'v1' => 'V10.01',                       
                    ],                      
                    'pcc_type'=> [
                        'webservice_pcc' => 'WebService PCC',
                        'terminal_pcc'   => 'Terminal PCC',
                    ],
                    'api_mode'=>[
                        'test'=>'Test',
                        'cert'=>'Certification',
                        'live'=>'Live',
                    ],
                    'fare_types'=> [
                        'PUB'=> 'Published',
                    ],
                    'aggregaters'=> [
                      'KL'=>'KLM Airlines',
                      'AF'=>'Air France',
                    ],
                    'services'=> [
                        'search'  => 'Search',
                        'price'   => 'Price',
                        'booking' => 'Booking',
                        'ticket'  => 'Ticket',
                    ],
                    'type'=> [
                        'ON'  => 'Online',
                        'OFF' => 'Offline',
                    ],
                    'credentials'=> [
                        'agency_name'   => 'Agency Name',                       
                        'iata_number'   => 'IATA Number',
                        'agency_id'     => 'Agency Id',
                        'api_key'       => 'API Key',
                        'common_url'    => 'Common Api Url',
                    ],
                    'default_currencies' => [
                        'EUR'   => 'EUR',
                    ],
                    'allowed_currencies' => [
                        'EUR'   => 'EUR',
                    ],
                    'status' => [
                        'A'  =>'Active',
                        'IA' =>'InActive',
                    ],
                    'sector_mapping'    =>  'N',
                    'provider_code'   =>  [
                                            'NDCAF'
                                          ],                    
                  ],
                  'Tbo'=> [
                    'api_name'    => 'TBO',
                    'pcc_info'    => 'Y',
                    'has_offline' => 'N',
                    'versions' => [
                        'v1' => 'V1',                      
                        'v2' => 'V2',                      
                    ],
                    'pcc_type'=> [
                        'webservice_pcc' => 'WebService PCC',
                        'terminal_pcc'   => 'Terminal PCC',
                    ],
                    'api_mode'=>[
                        'test'=>'Test',
                        'cert'=>'Certification',
                        'live'=>'Live',
                    ],
                    'fare_types'=> [
                        'PUB'=> 'Published',
                    ],
                    'aggregaters'=> [
                      'GDS'=>'GDS',
                      'SG'=>'Spicejet',
                      '6E'=>'Indigo',
                      'G8'=>'Goair',
                      'G9'=>'Air Arabia',
                      'FZ'=>'Flydubai',
                      'IX'=>'Air India Express',
                      'AK'=>'Air Asia',
                      'LB'=>'AirCosta',                      
                      'B3'=>'BhutanAirlines',
                      'OP'=>'AirPegasus',
                      '2T'=>'TruJet',
                      'W5'=>'MahanAir',
                      'LV'=>'MegaMaldives',
                      'TR'=>'FlyScoot',
                      'ZO'=>'ZoomAir',
                      'DN'=>'Air Deccan'
                    ],
                    'services'=> [
                        'search'  => 'Search',
                        'price'   => 'Price',
                        'booking' => 'Booking',
                        'ticket'  => 'Ticket',
                    ],
                    'type'=> [
                        'ON'  => 'Online',
                        'OFF' => 'Offline',
                    ],
                    'credentials'=> [
                        'client_id'             => 'Client Id',                       
                        'user_name'             => 'Username',
                        'password'              => 'Password',
                        'shared_service_url'    => 'Shared Service Url',
                        'air_service_url'       => 'Air Service Url',
                        'gst_company_name'    => 'Gst Company Name',
                        'gst_number'          => 'Gst Number',
                        'gst_company_email'   => 'Gst Company Email',
                        'gst_company_number'  => 'Gst Company Number',
                        'gst_company_address' => 'Gst Company Address',
                    ],
                    'default_currencies' => [
                        'INR'   => 'INR',
                    ],
                    'allowed_currencies' => [
                        'INR'   => 'INR',
                    ],
                    'status' => [
                        'A'  =>'Active',
                        'IA' =>'InActive',
                    ],
                    'sector_mapping'    =>  'N',
                    'provider_code'   =>  [
                                            'TBO',
                                          ],                    
                  ],
                  'Flair'=> [
                    'api_name' => 'Flair',
                    'pcc_info' => 'Y',
                    'has_offline' => 'N',
                    'versions' => [
                      'v1' => 'V10.01', 
                    ],
                    'pcc_type'=> [
                      'webservice_pcc' => 'WebService PCC',
                      'terminal_pcc' => 'Terminal PCC',
                    ],
                    'api_mode'=>[
                      'test'=>'Test',
                      'cert'=>'Certification',
                      'live'=>'Live',
                    ],
                    'fare_types'=> [
                      'PUB'=> 'Published',
                      'PRI'=> 'Private Fare',
                    ],
                    'aggregaters'=> [
                      'ALL'=>'All'
                    ],
                    'services'=> [
                      'search' => 'Search',
                      'price' => 'Price',
                      'booking' => 'Booking',
                      'ticket' => 'Ticket',
                    ],
                    'type'=> [
                      'ON' => 'Online',
                      'OFF' => 'Offline',
                    ],
                    'credentials'=> [
                      'client_id' => 'Client Id', 
                      'user_name' => 'Username',
                      'password' => 'Password',
                      'air_service_url' => 'Air Service Url',
                      'company_key' => 'Company Key',
                      'company_identifier' => 'Company Identifier',
                      'company_name' => 'Company Name',
                      'account_number' => 'Account Number',
                      'pri_user_name' => 'Private Fare Username',
                      'pri_password' => 'Private Fare Password',
                      'pri_company_key' => 'Private Fare Company Key',
                      'pri_account_number' => 'Private Fare Account Number',
                    ],
                    'default_currencies' => [
                      'CAD' => 'CAD',
                    ],
                    'allowed_currencies' => [
                      'CAD' => 'CAD',
                    ],
                    'status' => [
                      'A' =>'Active',
                      'IA' =>'InActive',
                    ],
                    'sector_mapping' => 'Y',
                    'provider_code'   =>  [
                                            'F8'
                                          ], 
                  ],
                  'Ndcba'=> [
                    'api_name'    => 'British Airways',
                    'pcc_info'    => 'Y',
                    'has_offline' => 'N',
                    'versions' => [
                        'v1' => 'V10.01',                       
                    ],                      
                    'pcc_type'=> [
                        'webservice_pcc' => 'WebService PCC',
                        'terminal_pcc'   => 'Terminal PCC',
                    ],
                    'api_mode'=>[
                        'test'=>'Test',
                        'cert'=>'Certification',
                        'live'=>'Live',
                    ],
                    'fare_types'=> [
                        'PUB'=> 'Published',
                        'PRI'=> 'JCB'
                    ],
                    'aggregaters'=> [
                      'BA'=>'British Airways',
                    ],
                    'services'=> [
                        'search'  => 'Search',
                        'price'   => 'Price',
                        'booking' => 'Booking',
                        'ticket'  => 'Ticket',
                    ],
                    'type'=> [
                        'ON'  => 'Online',
                        'OFF' => 'Offline',
                    ],
                    'credentials'=> [
                        'agency_name'   => 'Agency Name',                       
                        'iata_number'   => 'IATA Number',
                        'agency_id'     => 'Agency Id',
                        'auth_key'      => 'Auth Key',
                        'common_url'    => 'Common Api Url',
                    ],
                    'default_currencies' => [
                        'CAD'   => 'CAD',
                    ],
                    'allowed_currencies' => [
                        'CAD'   => 'CAD',
                    ],
                    'status' => [
                        'A'  =>'Active',
                        'IA' =>'InActive',
                    ],
                    'sector_mapping'    =>  'Y',
                    'provider_code'   =>  [
                                            'NDCBA'
                                          ],                    
                  ],
                  'Amadeus'=> [
                    'api_name'    => 'Amadeus',
                    'pcc_info'    => 'Y',
                    'has_offline' => 'N',
                    'versions' => [
                        'v1' => 'V1.0',                       
                    ],                      
                    'pcc_type'=> [
                        'webservice_pcc' => 'WebService PCC',
                        'terminal_pcc'   => 'Terminal PCC',
                    ],
                    'api_mode'=>[
                        'test'=>'Test',
                        'cert'=>'Certification',
                        'live'=>'Live',
                    ],
                    'fare_types'=> [
                        'PUB'=> 'Published',
                        'PRI'=> 'JCB',
                        'ITX'=> 'ITX', 
                    ],
                    'aggregaters'=> [
                      'ALL'=>'All',
                    ],
                    'services'=> [
                        'search'  => 'Search',
                        'price'   => 'Price',
                        'booking' => 'Booking',
                        'ticket'  => 'Ticket',
                    ],
                    'type'=> [
                        'ON'  => 'Online',
                        'OFF' => 'Offline',
                    ],
                    'credentials'=> [
                        'user_name'     => 'User Name',                       
                        'password'      => 'Password',
                        'wsap'          => 'WSAP',
                        'office_id'     => 'Office Id',
                        'duty_code'     => 'Agent Duty Code',
                        'pos_type'      => 'POS Type',
                        'requester_type'=> 'Requester Type',
                        'api_url'       => 'Api Url',
                    ],
                    'default_currencies' => [
                        'CAD'   => 'CAD'
                    ],
                    'allowed_currencies' => [
                        'CAD'   => 'CAD'
                    ],
                    'status' => [
                        'A'  =>'Active',
                        'IA' =>'InActive',
                    ],
                    'sector_mapping'    =>  'N',
                    'provider_code'   =>  [
                                            '1A'
                                          ],
                  ],
                
            ],
            'Hotel'=> [
              'Hotelbeds'=> [
                    'api_name'    => 'Hotelbeds',
                    'pcc_info'    => 'Y',
                    'has_offline' => 'N',
                    'versions' => [
                        'v1' => 'V1',
                    ],                      
                    'pcc_type'=> [
                        'webservice_pcc' => 'WebService PCC',
                        'terminal_pcc'   => 'Terminal PCC',
                    ],
                    'api_mode'=>[
                        'test'=>'Test',
                        'cert'=>'Certification',
                        'live'=>'Live',
                    ],
                    'fare_types'=> [
                        'PUB'=> 'Published'
                    ],
                    'aggregaters'=> [
                      'ALL'=>'All',
                    ],
                    'services'=> [
                        'search'  => 'Search',
                        'price'   => 'Price',
                        'booking' => 'Booking',
                        'ticket'  => 'Ticket',
                    ],
                    'credentials'=> [
                        'api_name' => 'Api Name',
                        'api_key' => 'Api Key',
                        'password' => 'Password',
                        'json_api_url'      => 'Api Url',                        
                        'api_secure_url'    => 'Api Secure Url',
                        'card_holder_name'  => 'Card Holder Name',
                        'card_number'       => 'Card Number',
                        'card_type'         => 'Card Type',
                        'exp_month'         => 'Expiry Month',
                        'exp_year'          => 'Expiry Year'
                    ],
                    'default_currencies' => [
                        'CAD'   => 'CAD',
                    ],
                    'allowed_currencies' => [
                        'CAD'   => 'CAD',
                        'USD'   => 'USD',
                        'INR'   => 'INR',
                    ],
                    'status' => [
                        'A'  =>'Active',
                        'IA' =>'InActive',
                    ]
                ],
            ],
            'Insurance'=> [
              'Manulife'=> [
                    'api_name'    => 'Manulife',
                    'pcc_info'    => 'Y',
                    'has_offline' => 'N',
                    'versions' => [
                        'v3' => 'V3',
                    ],                      
                    'pcc_type'=> [
                        'webservice_pcc' => 'WebService PCC',
                        'terminal_pcc'   => 'Terminal PCC',
                    ],
                    'api_mode'=>[
                        'test'=>'Test',
                        'cert'=>'Certification',
                        'live'=>'Live',
                    ],
                    'fare_types'=> [
                        'PUB'=> 'Published'
                    ],
                    'aggregaters'=> [
                      'ALL'=>'All',
                    ],
                    'services'=> [
                        'search'  => 'Search',
                        'price'   => 'Price',
                        'booking' => 'Booking',
                        'ticket'  => 'Ticket',
                    ],
                    'credentials'=> [
                        'insurance_name'    => 'Insurance Name',
                        'quoteUrl'          => 'Quote Url',
                        'bookUrl'           => 'Book Url',
                        'b2c_servicekey'    => 'B2C servicekey',
                        'b2b_servicekey'    => 'B2B servicekey',
                        'agent_code'         => 'Agent Code',
                        'branch_code'        => 'Branch Code',
                    ],
                    'default_currencies' => [
                        'CAD'   => 'CAD',
                    ],
                    'allowed_currencies' => [
                        'CAD'   => 'CAD',
                        'USD'   => 'USD',
                        'INR'   => 'INR',
                    ],
                    'status' => [
                        'A'  =>'Active',
                        'IA' =>'InActive',
                    ]
                ],
            'Trawelltag'=> [
                    'api_name'    => 'Trawelltag',
                    'pcc_info'    => 'Y',
                    'has_offline' => 'N',
                    'versions' => [
                        'v1' => 'V1',
                        'v2' => 'V2',
                        'v3' => 'V3',
                    ],                      
                    'pcc_type'=> [
                        'webservice_pcc' => 'WebService PCC',
                        'terminal_pcc'   => 'Terminal PCC',
                    ],
                    'api_mode'=>[
                        'test'=>'Test',
                        'cert'=>'Certification',
                        'live'=>'Live',
                    ],
                    'fare_types'=> [
                        'PUB'=> 'Published'
                    ],
                    'aggregaters'=> [
                      'ALL'=>'All',
                    ],
                    'services'=> [
                        'search'  => 'Search',
                        'price'   => 'Price',
                        'booking' => 'Booking',
                        'ticket'  => 'Ticket',
                    ],
                    'credentials'=> [
                        'insurance_name'    => 'Insurance Name',
                        'book_url'          => 'Booking Url',
                        'ref_url'           => 'Reference Url',
                        'sign_key'          => 'Sign Key',
                        'ref_key'           => 'Reference Key',
                        'branch_sign_key'   => 'Branch Sign Key',
                        'user_name'         => 'User Name',

                    ],
                    'default_currencies' => [
                        'CAD'   => 'CAD',
                    ],
                    'allowed_currencies' => [
                        'CAD'   => 'CAD',
                        'USD'   => 'USD',
                        'INR'   => 'INR',
                    ],
                    'status' => [
                        'A'  =>'Active',
                        'IA' =>'InActive',
                    ]
                ],
            ],
        ],
    ],

    // Promo Code Image Local Path
    'promo_code_save_path'                      =>  '/uploadFiles/promoCode/',
    'promo_code_storage_location'               =>  'local',

     // Account Promotions Image Local Path 
    'promotionImage_save_path'                  => '/uploadFiles/promotionImages/',
    'promotion_image_storage_loaction'          => 'local',
    'agent_activation_email'                    => true,
    'agency_activation_email'                   => true,  

    'guest_user_group'                          => 'G1',
    'deposit_payment_mode' =>[
        // '1' => 'Cash',
        '2' => 'Card',
        '3' => 'Cheque',
        // '4' => 'Voucher',
    ],
    'payment_payment_mode' =>[
        // '1' => 'Cash',
        '2' => 'Card',
        '3' => 'Cheque',
        // '4' => 'Voucher',
        '5' => 'Fund',
    ],
    'business_type' => [
        1 => 'online_traval_agency',
        2 => 'tour_operator',
        3 => 'corporate',
        4 => 'cruise',
    ],
    'register_prefessional_association' =>  [
                                              'IATA' => 'IATA',
                                              'ARC' => 'ARC',
                                              'TIDS' => 'TIDS',
                                              'CLIA' => 'CLIA',
                                              'TICO' => 'TICO',
                                              'TRUE' => 'TRUE',
                                              'OPC' => 'OPC',
                                              'CPBC' => 'CPBC',
                                          ],                                         
    'agency_activation_email'                   => true, 
    'user_account_type_id'                      => '1',   
    'product_type'                              => [
                                                        'F' => 'Flight',
                                                        'H' => 'Hotel',
                                                        'I' => 'Insurance',
                                                    ],
    'status'                                    =>["ALL"=>"ALL" ,"Active"=>"A", "Inactive"=>"IA"],
    'all_status' => ['A' => 'Active','IA' => 'InActive','AR' => 'Archived','D'=>'Delete','NP'=>'Not Paid','PA'=>'Pending Approved','expired' => 'Expired', 'not_expired' => 'Not Expired','PP' => 'Partial Payment','R'=>'Rejected'],
    'mail_encryption_types' => array('tls'=>'tls','ssl'=>'ssl'),
    'mrms_api_config' => array(
        'allow_api'       => 'yes',    
        'api_mode'        => 'test', // test or live
        'merchant_id'     => '50065',
        'group_id'        => '',
        'template_id'     => '',
        'api_key'         => 'e7c45c7e99c0efc2a9737d219af606dc',
        'post_url'        => 'https://t1.rmsid.com/fde/api/txn/Post.xml' ,
        'id_resource_url' => 'https://t1.rmsid.com/fde/api/txn/Post.xml' ,
        'ref_resource_url'=> 'https://t1.rmsid.com/fde/api/txn/Post.xml' ,
        'reference'       => 'success.comm',
        'get_by_id_url'   =>  'https://t1.rmsid.com/fde/api/txn/GetByRef.xml',
        'get_by_ref_url'  =>  'https://t1.rmsid.com/fde/api/txn/GetByID.xml',
        'notification_url'=>  'https://t1.rmsid.com/fde/api/txn/GetByRef.xml',
        'mrms_script_url'=>  'https://elistva.com/api/script.js',
        'mrms_no_script_url'=>  '//elistva.com/api/assets/clear.png',
        'device_api_account_id' => '10377',
        'device_api_key' => 0
      ),
    'mrms_api_mode'  =>  array('test'=>'Test','live'=>'Live'),
    'osticket' => array(
        'allow_osticket' => 'yes',
        'mode'        => 'api', //api -> ticket through api, email -> ticket through email
        'alert'       => 'no', //If unset, disable alerts to staff. Default is yes
        'autorespond' => 'no', //If unset, disable autoresponses. Default is yes
        'api_support' => array(
            'api_key'  => 'E66E3C3F7299993DFCA8836F7CE5255F',
            'host_url' => 'http://uat-support.tripzumi.com/api/http.php/tickets.json' //http://uat-support.tripzumi.com/api/tickets.json
        ),
        'ticket_topic_id'            => 12, //Help Topic ID in scp
        'allow_booking_success'     => 'yes',  //yes -> create os ticket, no -> don't create os ticket
        'mode_of_booking_success'   => 'api', //api -> ticket through api, email -> ticket through email
        'allow_booking_failure'     => 'yes',  //yes -> create os ticket, no -> don't create os ticket
        'mode_of_booking_failure'   => 'api', //api -> ticket through api, email -> ticket through email
        'support_booking_mail_to'   => 'testwintlt@gmail.com',
    ),
    'os_ticket_mode'  =>  array('api'=>'Api','email'=>'Email'),

    'common_status_code'     => [
                    'success'           => 200,
                    'validation_error'  => 301,
                    'empty_data'        => 302,
                    'permission_error'  => 303,
                    'failed'            => 304,
                    'not_found'         => 404,
        ],

    'login_redirect_url'                     => 'dashboard',

    'available_payment_gateways_mode_config' => [
                                                    'Atom'=>[ 
                                                            'clientCode'=>'clientCode',
                                                            'gatewayUrl' => 'gatewayUrl',
                                                            'merchantId' => 'merchantId',
                                                            'gateway_password' => 'gateway_password',
                                                            'productId' => 'productId',
                                                            'reqHashKey' => 'reqHashKey',
                                                            'resHashKey'=> 'resHashKey',
                                                            'redirectType' => 'redirectType',
                                                            'forwardUrl' => 'forwardUrl', 
                                                            ],
                                                    'Ccavenue'=>[ 
                                                                    'merchantId' => 'merchantId',
                                                                    'workingKey' => 'workingKey',
                                                                    'accessKey' => 'accessKey',
                                                                    'gatewayUrl' => 'gatewayUrl',
                                                                    'redirectType' => 'redirectType',
                                                                    'forwardUrl' => 'forwardUrl', 
                                                                ],
                                                    'Paypal'=>[
                                                                'merchantId' => 'merchantId',
                                                                'gatewayUrl' => 'gatewayUrl', 
                                                            ],
                                                    'Worldpay'=>[
                                                                'gatewayUrl'		=> 'gatewayUrl',
                                                                'merchantCode' 	=> 'merchantCode',
                                                                'installationId' 	=> 'installationId', 
                                                                'xmlUsername'   	=> 'xmlUsername',
                                                                'xmlPassword' 		=> 'xmlPassword',                                              
                                                                'encryptionKey' 	=> 'encryptionKey',                                                                                            
                                                                ],
                                                    'Moneris'=>[
                                                                'storeId' => 'storeId',
                                                                'apiToken'  => 'apiToken',
                                                                'gatewayUrl' => 'gatewayUrl',                                              
                                                                'gatewayCountryCode' => 'gatewayCountryCode',                                              
                                                            ],
                                                    'Paytm'=>[ 
                                                            'merchantKey'=>'merchantKey',
                                                            'merchantId' => 'merchantId',
                                                            'merchantWebsite' => 'merchantWebsite',
                                                            'domain' => 'domain',
                                                            'txnUrl'=> 'txnUrl',
                                                            'industryTypeId' => 'industryTypeId',
                                                            'channelId' => 'channelId', 
                                                            ],
                                                ],

 //GatwayConfig
    'gateway_mode'                             =>  [
                                                    'test' => 'Test',
                                                    'live' => 'Live',                          
                                                ],
    //Form of payment types
    'form_of_payment_types'                     =>[
                                                    'CC' => [
                                                      'is_allwed' => true,
                                                      'types' => [
                                                        'AX'=>'American Express',
                                                        'MC'=>'MasterCard',
                                                        'VI'=>'Visa',
                                                        'JC'=>'JCB',
                                                        'DC'=>'Diners Club',
                                                        'DUS' => 'Diners Club Us',
                                                        'MA'=>'Maestro',
                                                        'DI'=>'Discover'
                                                        ]
                                                      ],
                                                    'DC' => [
                                                      'is_allwed' => true,
                                                      'types' => [
                                                        'MC'=>'MasterCard',
                                                        'VI'=>'Visa',
                                                        'RU'=>'Rupay',
                                                        ]
                                                      ],
                                                    'CASH' => [
                                                      'is_allwed' => true,
                                                      'types' => []
                                                      ],
                                                    'CHEQUE' => [
                                                      'is_allwed' => true,
                                                      'types' => []
                                                      ],
                                                    'ACH' => [
                                                      'is_allwed' => true,
                                                      'types' => []
                                                    ],
                                                    'PG' => [
                                                      'is_allwed' => true,
                                                      'types' => []
                                                      ],
                                                ],
    'common_services'=> [
        'search'  => 'Search',
        'price'   => 'Price',
        'booking' => 'Booking',
        'ticket'  => 'Ticket',
    ],
    'redisSetTime' => 8 * 3600,//60 - one minute
    'redisSetTimeForSM'  => 2880 * 60,//60 - one minute - Sector Mapping redis expiry time

    'operators' =>  [
                        '=' => "equal to",
                        '!=' => "not equal",
                        '>=' => "greater than or equal to",
                        '<=' => "less than or equal to",
                        'BETWEEN' => "between",
                        'NOTBETWEEN' => "not between",
                        'IN' => "in",
                        'NOTIN' => "not in",
                    ],

    'slave_connection'                      =>  'mysql2',
    'agency_fee_type' =>  [
                          'cancellation_fee',
                          'exchange_fee',
                        ],


     'payment_gateway_config_dynamic_constrct'=>[
                                                    'select_config_variables' => [			        /* Enter the config variable to selectpicker */
                                                        'redirectType' => 'redirectType',
                                                        '3dSecure' => '3dSecure',
                                                        'gatewayCountryCode' => 'gatewayCountryCode',
                                                    ],
                                                    'redirectType_value' => [
                                                        'empty_redirecttype',
                                                        'direct',
                                                        'forward'
                                                    ],
                                                    '3dSecure_value' => [
                                                        'empty_3dSecure',
                                                        'on',
                                                        'off'
                                                    ],
                                                    'gatewayCountryCode_value' => [
                                                        'empty_gateway_country_code',
                                                        'CA',
                                                        'US'
                                                    ],
                                                    'config_type' => [
                                                        'test' => 'test',
                                                        'live' => 'live',
                                                    ],                                          

                                                ],    
                                                
    'sector_mapping_allowed_gds'            =>  [
                                                    'Sabre'         => 'N',
                                                    'Travelport'    => 'N',
                                                    'Tbo'           => 'N',
                                                    'Ndcafklm'      => 'N',
                                                    'Ndcba'         => 'Y',
                                                    'Flair'         => 'Y',
                                                ],

    'sub_domain' => 'ndc',
    
    'incident_and_remarks' => [
        'fare_invoicing_and_service_remarks'=> 'Fare Invoicing And Service Remarks',
        'international_remarks'             => 'Internationl Remarks',
        'mandatory_and_exception_remarks'   => 'Mandatory And Exception Remarks',
        'mandatory_invoice_remarks_udid'    => 'Mandatory Invoice Remarks / UDID',
        'failed_ticketing_remarks'          => 'Failed Ticketing Remarks',
        'manual_review_sent_remark'         => 'Manual Review Sent Remark',
    ],
    'incident_for_qc' => [
        'qc_failed'             => 'QC Failed',
        'qc_passed'             => 'QC Passed',
        'manual_review'         => 'Manual Review',
        'risk_analysis_failed'  => 'Risk Analysis Failed',
    ],
    'products' => [
        'flight' => [
            'PUB'=> 'Published',
            'PRI'=> 'JCB',
            'ITX'=> 'ITX Fares',
            'PFA'=> 'PFA Fares',
            'COM'=> 'Combination Fares',
        ],
        'hotel' => [
            'PUB'=> 'Published'
        ],
        'insurance' => [
            'PUB'=> 'Published'
        ]
    ],
    //Risk Analysis Management
   'risk_analysis_management' =>[
    'risk_analysis_config'     =>[
                                // 'credit_card_type_number_match',
                                // 'card_issuing_country_is_same_as_billing_country' ,
                                // 'card_issuing_country_is_one_of_the_origin_or_destination',
                                    'card_holder_name_matches_least_one_pax_name' ,
        ],
    ],
    'risk_analysis_hide_field'              => 'Y',
    'ticketing_rules_hide_field'            => 'Y',
    'allowed_file_format'                   => 'jpeg,png,pdf,csv,bmp',  //file extension
    'allowed_file_type'                     => 'image/jpeg,image/png,image/bmp,application/pdf,text/csv,application/vnd.ms-excel',   //file type
    'allowed_file_size'                     => 2,   //getClientSize - MB
    'supplier_pos_rule_file_save_path'      => '/uploadContractRules/ruleFiles',
    'contract_file_storage_location'        => 'local',//local or gcs(google cloud)
    'partner_account_type_id'               => '1',
    'airline_booking_template_type'         =>  [
                                                    'AABS'  =>'Allow All Block Specific',
                                                    'BAAS'  =>'Block All Allow Specific',
                                                ],
    'log_disk'                              => 'storage',
    'popular_routes_save_path'              =>'/uploadFiles/popularRoutes/',
    'popular_routes_storage_location'       =>'local',
    'popular_destination_save_path'         =>'/uploadFiles/popularDestinations/',
    'popular_destination_storage_location'  =>'local',

    // Promo Code
    'search_type'=>[
        '1' => 'Flight',
        '2' => 'Hotel',
        '3' => 'Insurance',
      ],
    'promo_fare_types' => 
      array (
        1 => 
        array (
          'BF' => 'Base Fare',
          'TF' => 'Total Fare',
        ),
        2 => 
        array (
          'TF' => 'Total Fare',
        ),
        3 => 
        array (
          'BF' => 'Base Fare',
          'TF' => 'Total Fare',
        ),
      ),

    'trip_type_val'=>[
        'ALL' => 'ALL',
        '1' => 'Oneway',
        '2' => 'RoundTrip',
        '3' => 'Multicity',
    ],

    'flight_class_code' => [
        'ALL' => 'ALL',
        'Y' => 'ECONOMY',
        'S' => 'PREMECONOMY',
        'C' => 'BUSINESS',
        'J' => 'PREMBUSINESS',
        'F' => 'FIRSTCLASS',
        'P' => 'PREMFIRSTCLASS',
    ],     
    // Footer Icon List
    'footer_icons'                          =>  [
                                                    'member_of'=>'Member of',
                                                    'we_accept'=>'We accept',
                                                    'follow_us'=>'Follow us',
                                                ],
    //Footer Icons
    'footer_icons_icon'                     =>  [
                                                    'fa fa-facebook-square' => 'facebook-square' ,
                                                    'fa fa-google-plus' => 'google-plus',
                                                    'fa fa-instagram' => 'instagram',
                                                    'fa fa-linkedin' => 'linkedin',
                                                    'fa fa-twitter-square' => 'twitter-square',
                                                    'fa fa-tumblr-square' => 'tumblr-square',
                                                    'fa fa-pinterest' => 'pinterest',
                                                    'fa fa-lightbulb-o' => 'lightbulb-o',
                                                    'fa fa-youtube' => 'youtube',
                                                    'fa fa-facebook-f' => 'facebook-f',
                                                    'fa fa-twitter' => 'twitter',
                                                    'fa fa-pinterest-p' => 'pinterest-p',
                                                ],
    
    'footer_icon_save_path'                     => '/uploadFiles/footerIcons/',
    'footer_icon_storage_loaction'              => 'local',
    'footer_links'                              => [
                                                        [
                                                            'FAQs',
                                                            'Itinerary Changes',
                                                            'Online Refund Request', 
                                                        ],
                                                        [
                                                            'Flight',
                                                            'Holidays',
                                                            'Airlines',
                                                            'Flight Schedule',
                                                            'Travel Guides',
                                                            'Blog',
                                                            'Travel Updates',
                                                        ],
                                                        [
                                                            'Airline informations',
                                                            'About us',
                                                            'Terms of use',
                                                            'Privacy Policy',
                                                            'Partners',
                                                            'Partner with us',
                                                        ]
                                                    ],
'footer_link_background_storage_location'   => 'local',//local or gcs(google cloud)
'footer_link_background_storage_image'      =>  '/uploadFiles/footerLinkBackGround',
'blog_storage_location'                     => 'local',
'blog_content_save_path'                    => '/uploadFiles/blog',
// Blog Content Logo
'benefits_content_logo'                     =>  [
                                                    'bridge'    => 'Bridge',
                                                    'coins'     => 'Coins',
                                                    'hotel'     => 'Hotel',
                                                    'flight'    => 'Flight',
                                                    'tag'       => 'Tag',
                                                    'support'   => 'Support',
                                                    'dashboard' => 'Dashboard',
                                                    'clicke'    => 'Clicke',
                                                ],
'benefit_content_title'                   => 'Benefits',//get title temporariry from here
'default_portal_url' =>  'http://localhost:4100',
'default_payment_gateway' =>  'atom',
'portal_fop_type' =>  'PG',
'allow_hold'      =>  'yes',
'promo_max_discount_price' => 1000,

'currency_exchange_rate_type'             =>[
                                                'ALL' => 'ALL',
                                                'AS'  => 'Agency Specific',
                                                'PS'  => 'Portal Specific',
                                            ],

'all'                                   =>  [
                                                'ALL' => 'ALL',
                                            ],

'allowed_ratio_type'                    =>  [
                                                'ALL' => 'ALL',
                                                'Y'   => 'Yes',
                                                'N'   => 'No'
                                            ],

'footer_link_background_storage_location'   => 'local',//local or gcs(google cloud)
    'footer_link_background_storage_image'      =>  '/uploadFiles/footerLinkBackGround',
    'user_groups' => ['G1','G2','G3','G4','G5'],
    'blog_storage_location'                     => 'local',
    'benefit_content_title'                   => 'Benefits',//get title temporariry from here
    'portal_default_country' => 'IN',
    'portal_default_currency' => 'INR',
    'portal_default_payment_gateway' => 'Atom',
    'default_portal_origin' =>  'MAA',
    'default_portal_language' =>  'EN',
    'portal_multi_city_count' =>  5,
    "allowed_fare_types" => [
        "guest_access" => ["PUB" => "Y", "PRI" => "PHD", "ITX" => "N"],
        "member_access" => ["PUB" => "Y", "PRI" => "Y", "ITX" => "N"]
    ],
    
    'def_fop_type'    =>  'itin',
    'allow_hold'      =>  'yes',
    'portal_default_lat'  =>  12.918058, // karappakkam wintlt lat
    'portal_default_long'  =>  80.228053, // karappakkam wintlt long
    
    'mrms_select_column' => [
        'api_mode'  =>  ['test'=>'Test','live'=>'Live'],
    ],
    'mrms_selected_value' =>  [
        'api_mode'  => 'test',
    ],
    'google_analytics_id' => 'UA-133174858-1',
    'mail_date_time_format' => 'D, d M Y H:i:s',

    // portal Config image
    'contact_background_storage_location'    => 'local',//local or gcs(google cloud)
    'contact_background_storage_image' =>  '/uploadFiles/contactBackGround',
    'portal_logo_storage_location'    => 'local',//local or gcs(google cloud)
    'portal_logo_storage_image' =>  '/uploadFiles/portalLogo',
    'mail_logo_storage_location'    => 'local',//local or gcs(google cloud)
    'mail_logo_storage_image' =>  '/uploadFiles/portalLogo',
    'default_page_logo' =>  'tripzumi.png',
    'fav_icon_storage_location'    => 'local',//local or gcs(google cloud)
    'fav_icon_storage_image' =>  '/uploadFiles/favIcon',
    'default_fav_icon' =>  'favicon.png',
    'type_email_bg_storage_location'    => 'local',//local or gcs(google cloud)
    'type_email_bg_storage_image' =>  'uploadFiles/typeEmailBG',
    'default_type_email_bg' =>  'default.jpg',
    'hotel_background_img_location'    => 'local',//local or gcs(google cloud)
    'hotel_background_storage_image' =>  '/uploadFiles/hotelBackGround',
    'insurance_background_img_location' => 'local',
    'insurance_background_storage_image' =>  '/uploadFiles/insuranceBackGround',
    'search_background_storage_location'    => 'local',//local or gcs(google cloud)
    'search_background_storage_image' =>  '/uploadFiles/searchBackGround',

    'add_benefit_title' =>  'Benefits', //get title temporariry from here
    'show_phone_deal' => 'yes',
    'social_login' => [
        'facebook' => [
            'api_id' => '',
            'icons'   => 'facebook',
            'name'  =>  'Facebook',
        ],
        'google'  =>  [
            'api_id' => '',
            'icons' =>  'google',
            'name'  =>  'Google Account',
        ],
    ],
    'social_login_disabled_config'  =>  [
        'facebook'  =>  [
            'icons',
            'name',
        ],
        'google'  =>  [                                                        
            'icons',
            'name',
        ],              
    ],
    'frontend_menus'      =>  [
        'flights'   =>  [
            'name'  =>  'Flights',
            'url'   =>  '/flights',
        ],
        'about_us'  =>  [
            'name'  =>  'About Us',
            'url'   =>  '/page/about-us',

        ],
        'contact_us' => [
            'name'  =>  'Contact Us',
            'url'   =>  '/contactus',
        ],                              
    ],
    'default_country_language' => ['name'=>'English', 'code'=>'EN'],
    'portal_cookies_id' => 'rsmrrkadtapmrkss',
    'portal_config_redis_expire' => 2880 * 60, //2 Days
    'available_country_language' => [
        'EN'  =>  ['name'=>'English', 'code'=>'EN'],
        // 'HI'  =>  ['name'=>'Hindi', 'code'=>'HI'],
        // 'ZH'  =>  ['name'=>'Chinese', 'code'=>'ZH'],
        // 'ES'  =>  ['name'=>'Spanish', 'code'=>'ES'],
        // 'AR'  =>  ['name'=>'Arabic', 'code'=>'AR'],
        // 'PT'  =>  ['name'=>'Portuguese', 'code'=>'PT'],
        // 'BN'  =>  ['name'=>'Bengali', 'code'=>'BN'],
        // 'RU'  =>  ['name'=>'Russian', 'code'=>'RU'],
        // 'JA'  =>  ['name'=>'Japanese', 'code'=>'JA'],
        // 'DE'  =>  ['name'=>'German', 'code'=>'DE'],
        // 'TR'  =>  ['name'=>'Turkish', 'code'=>'TR'],
        // 'DA'  =>  ['name'=>'Danish', 'code'=>'DA'],
        // 'ID'  =>  ['name'=>'Indonesian', 'code'=>'ID'],
        // 'NL'  =>  ['name'=>'Dutch', 'code'=>'NL'],
        'FR'  =>  ['name'=>'French', 'code'=>'FR'],
        // 'IT'  =>  ['name'=>'Italian', 'code'=>'IT'],
        // 'SV'  =>  ['name'=>'Swedish', 'code'=>'SV'],
        // 'PL'  =>  ['name'=>'Polish', 'code'=>'PL'],
        // 'NO'  =>  ['name'=>'Norwegian', 'code'=>'NO'],
    ],
    'portal_title' =>   [
                            ''                => '',
                            'default'         => 'default', 
                            'home'            => 'home', 
                            'flights'         => 'flights', 
                            'checkout'        => 'checkout', 
                            'booking_success' => 'booking_success', 
                            'booking_failure' => 'booking_failure',
                            'viewbooking'     => 'viewbooking', 
                            'printbooking'    => 'printbooking', 
                            'cancelbooking'   => 'cancelbooking',
                            'contactus'       => 'contactus',
                            'checkoutretry'   => 'checkoutretry',
                            'booking'         => 'booking',
                            'deeplink'        => 'deeplink',
                            'updatePassword'  => 'updatePassword',
                            'changeportal'    => 'changeportal',
                            'makePayment'     => 'makePayment',
                            'paymentResponse' => 'paymentResponse',
                            'dashboard'       => 'dashboard',
                            'alltrips'        => 'alltrips',
                            'itinerary-changes' => 'itinerary-changes',
                            'about-us'          => 'about-us',
                            'privacy-policy'    => 'privacy-policy',
                            'terms-of-use'      => 'terms-of-use',
                            'faqs'              => 'faqs',
                        ],

    'list_limit'        => 10,
    'list_page_limit'   => 1,
    'payment_status'    =>  [
                                "ALL"       =>  "ALL" ,
                                "Success"   =>  "S", 
                                "Initiated" =>  'I',
                                "Hold"      =>  'H',
                                "Failed"    =>  "F",
                                "Cancelled" =>  'C',
                            ],
    'expiry_status'  =>  ['expired'=>'Expired','not_expired'=>'Not Expired'],
    'flight_url_status' =>  ['A'=>'Active','AR'=>'Archived'],
    'portal_promotion_content_type'          => [
                                                    'content'   => 'Content',
                                                    'image'     => 'Image',
                                                ],
    'portal_promotion_image_save_location'  =>  'local',

    'search_product_type'                   => [
                                                    '1' => 'Flight',
                                                    '2' => 'Hotel',
                                                    '3' => 'Insurance',
                                                ],
    'customer_feedback_avathar' => 'https://ui-avatars.com/api/?background=fb400c&color=ffffff&name=',


    'check_hotel_response'                   => 25, //seconds 
    'supplier_airline_blocking_rule_fare_types' => [
        'PUB' => ['search' => 'Search','allow_restricted' => 'Fare Allow Restricted','booking' => 'Fare Booking'],
    'PRI' => ['search' => 'Search','allow_restricted' => 'Fare Allow Restricted','booking' => 'Fare Booking'],
    'ITX' => ['search' => 'Search','allow_restricted' => 'Fare Allow Restricted','booking' => 'ITX Fare Booking'],
    'PFA' => ['search' => 'Search','allow_restricted' => 'Fare Allow Restricted','booking' => 'Fare Booking'],
    'COM' => ['search' => 'Search','allow_restricted' => 'Fare Allow Restricted','booking' => 'Fare Booking'],
    ],
    
    'route_page_settings_image' => 'uploadFiles/routePagesSettings/',
    'route_page_settings_image_save_location' => 'local',
    'banner_section_storage_location' => 'local',
    'banner_section_save_path' => 'uploadFiles/bannerSection/',
    'banner_section_format' => 'Y-m-d H:i:s',
    //Quality Check Template
    'quality_check_template'    =>[
                'check_dk_number'                         => 'Check DK Number',
                'pq_available'                            => 'PQ Is available',
                'email_address_available'                 =>'Email Address Is Available',
                'check_phone_number'                      =>'Check Phone Number',
                'contact_details_available'               =>'Contact Details Available',
                'fop_availability'                        =>'FOP Availability',
                'check_retention_line'                    =>'Check Retention Line',
                'check_dob'                               =>'Check DOB',
                'mandatory_passport_detail_international' =>'Mandatory Passport Detail For International',
                'inhibit_manual_approval_for_fop_pq'      =>'Inhibit Manual Approval For FOP And PQ',
                'check_minimum_connection_time'           =>'Check Minimum Connection Time',
    ],

    'qc_setting' => [
                'pcc'       => 'PCC',
                'country'   => 'Country',
                'global'    => 'Global',
    ],
    'blog_contnet_url' => 'https://blog.tripzumi.com/wp-json/wp/v2/posts/?_embed&per_page=4',

    'route_config'          =>  [
                                    'include_from_country'          =>['gb'],
                                    'include_from_airport_code'     =>[],
                                    'exclude_from_country'          => [],
                                    'exclude_from_airport_code'     => [],
                                    'include_to_country'            => ['ar', 'bo','br','cl','co','ec','gy','py','pe','sr','uy','ve','nz','sg','cn','ae',],
                                    'include_to_airport_code'       => [],
                                    'exclude_to_country'            => [],
                                    'exclude_to_airport_code'       => [],
                                    'user_name'                     => 'TestUserName',
                                    'password'                      => 'TestUsers',
                                    'route_cofig_store_location'    => 'routeConfig',
                                    'days_of_week'                  =>  [
                                                                            'mon' => 'Y',
                                                                            'tue' => 'Y',
                                                                            'wed' => 'Y',
                                                                            'thu' => 'Y',
                                                                            'fri' => 'Y',
                                                                            'sat' => 'Y',
                                                                            'sun' => 'Y',
                                                                        ],
                                ],

    'flight_log_store_location'   => 'flight_log_local',//flight_log_local or gcs(google cloud)
    'employee_status' =>  [
                            'travel_agent'  => 'Travel Agent',
                            'hospitality'   => 'Hospitality',
                            "meeting_planning" => "Meeting Planning",
                            "corporate_agent" => "Corporate Agent",
                            "retail_sales" => "Retail Sales",
                            "other" => "Other",
                        ],
    'memberships'   =>  [
                            "ASTA"      => "American Society of Travel Agents",
                            "CLIA"      => "Cruise Lines International Association",
                            "IATAN"     => "International Association of Travel Agents Network",
                            "ARC"       => "Airlines Reporting Corporation",
                            "TIDS"      => "Travel Industry Designator Service", 
                            "TICO"      => "Travel Industry Council of Ontario",           
                            "TRUE"      => "Travel Retailers Universal Enumeration"
                        ],
    'busuiness_specification'   => ["flights"=>"Flights", "hotels" => "Hotels", "insurance"=>"Insurance", "packages" => "Packages"],
    'travel_intrest'   => ["all_inclusive" => "All Inclusive","honeymoons" => "Honeymoons","river_cruises" => "River Cruises","groups" => "Groups","universal_parks_resort_vacations" => "Universal Parks & Resort Vacations","cruises" => "Cruises","luxury_cruises" => "Luxury Cruises","small_ship_cruises" => "Small Ship Cruises","transatlantic" => "Transatlantic","transpacific" => "Transpacific","world_cruises" => "World Cruises","yacht_charter" => "Yacht Charter","family_vacations" => "Family Vacations","destination_weddings" => "Destination Weddings","student_groups" => "Student Groups","couples_romance" => "Couples & Romance","babymoons_" => "Babymoons","bachelor_parties" => "Bachelor Parties","bachelorette_parties_girlfriend_getaways" => "Bachelorette Parties & Girlfriend Getaways","celebration_travel" => "Celebration Travel","singles" => "Singles","women's_travel" => "Women's Travel","golf" => "Golf","beach_vacations" => "Beach Vacations","safari" => "Safari","adventure" => "Adventure","animals_wildlife" => "Animals & Wildlife","auto_racing" => "Auto Racing","ballooning" => "Ballooning","bird_watching" => "Bird Watching","canoeing_rafting" => "Canoeing & Rafting","climbing" => "Climbing","cycling" => "Cycling","eco-tourism" => "Eco-Tourism","fishing_hunting" => "Fishing & Hunting","hiking_backpacking" => "Hiking & Backpacking","national_parks" => "National Parks","nature" => "Nature","outdoor_activities_sports" => "Outdoor Activities & Sports","sailing_boating" => "Sailing & Boating","scuba" => "Scuba","skiing_winter_sports" => "Skiing & Winter Sports","spa_fitness" => "Spa & Fitness","sporting_events" => "Sporting Events","sustainable_travel" => "Sustainable Travel","tennis" => "Tennis","water_sports" => "Water Sports","wellness" => "Wellness","european_culture" => "European Culture","escorted_tours" => "Escorted Tours","theme_parks" => "Theme Parks","museums" => "Museums","archaeology" => "Archaeology","architecture" => "Architecture","arts_culture" => "Arts & Culture","casinos_gambling" => "Casinos & Gambling","castles_cathedrals" => "Castles & Cathedrals","christmas_markets" => "Christmas Markets","culinary_foodie" => "Culinary & Foodie","disney_vacations" => "Disney Vacations","entertainment" => "Entertainment","festival_tours" => "Festival Tours","film" => "Film","food_wine" => "Food & Wine","heritage" => "Heritage","historical_sites" => "Historical Sites","levant" => "Levant","literature" => "Literature","military" => "Military","nightlife" => "Nightlife","parks_gardens" => "Parks & Gardens","photography" => "Photography","religious" => "Religious","shopping" => "Shopping","theatre_music" => "Theatre & Music","wine_country_vacations" => "Wine Country Vacations","air_travel" => "Air Travel","car_travel" => "Car Travel","coach_tours" => "Coach Tours","dude_ranches" => "Dude Ranches","luxury_hotels" => "Luxury Hotels","overwater_bungalows" => "Overwater Bungalows","rail" => "Rail","vacation_rentals" => "Vacation Rentals","villas" => "Villas","business_travel" => "Business Travel","executive_corporate_and_luxury_travel" => "Executive Corporate and Luxury Travel","international_business_travel_specialist" => "International Business Travel Specialist","meetings_incentives" => "Meetings & Incentives","accessible_travel" => "Accessible Travel","bespoke_travel" => "Bespoke Travel","global_travel" => "Global Travel","gluten-free_" => "Gluten-Free","holiday_travel" => "Holiday Travel","independent_travel" => "Independent Travel","premier_concierge_services" => "Premier Concierge Services","special_needs" => "Special Needs"],
  'flight_log_store_location'   => 'flight_log_local',//flight_log_local or gcs(google cloud)
  'project_business_type'       => ["B2B","B2C"],
  'salutation'                  => [
                                        'Mr'    => 'Mr',
                                        'Miss'  => 'Miss',
                                        'Mrs'   => 'Mrs',
                                        'Ms'    =>  'Ms',        
                                    ],
    'airline_mask_in'           => [
                                        'Validating'    => 'V',
                                        'Marketing'     => 'M',
                                        'Operating'     => 'O',
                                    ],

  'view_history_record_limit'   => 10,

  'enable_single_user_login'  =>  'N', //Y - Enable single user login. N - Disable single user login

  'ticketing_allowed_gds'   => array('Sabre'),
  'reschedule_allowed_gds'  => array('Sabre'),
  'split_pnr_allowed_gds'   => array(),

  'bookings_default_days_limit'     => 3,//booking list display based config min days
  'bookings_max_days_limit'         => 60,//booking list display based config max days
  'booking_period_filter_days'      => 32,
  'pg_status' => [
        'I'   => 'Initiated',
        'S'   => 'Success',   
        'F'   => 'Failed',   
        'C'   => 'Cancelled', 
        'H'   => 'Hold'
  ],
  'insurance_max_travel_days' => 365,
  'months' => [
    'ALL',
    'JAN',
    'FEB',
    'MAR',
    'APR',
    'MAY',
    'JUN',
    'JUL',
    'AUG',
    'SEP',
    'OCT',
    'NOV',
    'DEC'
  ],
  'default_payment_modes' => [
        'ITIN' => 'ITIN',
        'PG' => 'PG'
    ],
    'card_collect_pg'         => array('moneris'),
    'retry_payment_max_limit' => 4,

    'contact_title' => [
        'Mr' => 'Mr',
        'Miss' => 'Miss',
        'Mrs' => 'Mrs',
        'Mstr'  =>  'Mstr',        
        'Ms'  =>  'Ms',        
    ],
     'insurance_supplier_account_id' => 2,

    'booking_max_extra_payment'       => 3,
    'extra_payment_max_retry_count'   => 3,
    'user_display_date_time_format' => 'd M Y H:i:s',

    'surcharge_type' => [
        'S' => 'Surcharge',
        'A' => 'Additional Discount',
     ],

    'calculation_on' => [
        'flight' =>[
            'PPBF' =>'Per Pax Base Fare',
            'PPTF' => 'Per Pax Total Fare',
            'PPBFPYQ' => 'Per Pax Base Fare + YQ',
            'PPTFMYQ' => 'Per Pax Total Fare - YQ',
            'PPBFPYR' => 'Per Pax Base Fare + YR',
            'PPTFMYR' => 'Per Pax Total Fare - YR',
        ],
        'hotel' => [
            'PR'=> 'Per Room',
            'PN'=>'Per Night',
        ],
        'insurance' =>[
            'PPBF' => 'Per Pax Base Fare',
            'PPTF' => 'Per Pax Total Fare',
        ]
    ],

 'Supplier_Route_blocking_partner_type'  =>  [
    'All Partners'      =>   'ALLPARTNER',
    'Specific Partners' =>   'SPECIFICPARTNER',
],
    //User referal
        'referral_code_prefix' => 'TRIP',
        'referral_link_expire_time' => 10080,
    
    'ticketing_queue_status' => [
        '401'   => 'Initiated',
        '402'   => 'Ticketed',
        '403'   => 'Failed',
        '404'   => 'Cancelled',        
        '405'   => 'RM - Manual',        
        '406'   => 'Review Rejected',        
        '407'   => 'QC Failed',        
        '408'   => 'Review Approved',
        '409'   => 'QC Approved',        
        '410'   => 'Risk Failed',        
        '411'   => 'Risk Approved',        
        '412'   => 'Payment Failed',        
        '413'   => 'Payment Approved',        
        '414'   => 'Address Verification  Failed',        
        '415'   => 'Address Verification  Approved',        
        '416'   => 'Credit Verification Failed',        
        '417'   => 'Credit Verification Approved',        
        '418'   => 'Manual Ticket Required',        
        '419'   => 'Ticket Processing',        
        '420'   => 'Partially Ticketed',        
        '421'   => 'Inprogress',
        '422'   => 'Removed From Queue',
        '423'   => 'Manual Ticketed',
        '424'   => 'Excess Discount Failed',
        '425'   => 'Excess Discount Approved',
    ],

        'referal_status'    =>  [
            'ALL'   =>  'All',
            'P'     =>  'Pending',
            'C'     =>  'Contacted',
            'A'     =>  'Registered',
            'E'     =>  'Expired',
            'R'     =>  'Rejected',
            'H'     =>  'Hold'
        ],
    'common_operator'           => [
                                    'Greater than or equal to'  => '>=',
                                    'Equal to'                  => '=',
                                    'Not equal'                 => '!=',
                                    'Less than or equal to'     => '>=',
                                ],

    // Route Page Settings
    'classify_airport' => 
    array (
      'Domestic' => 'Domestic',
      'International' => 'International',
    ),
    'specification' => 
    array (
      'Cheapest' => 'Cheapest',
      'Fastest' => 'Fastest',
      'Best Seller' => 'Best Seller',
    ),
    'route_page_settings_image' => '/uploadFiles/routePagesSettings/',
    'route_page_settings_image_save_location' => 'local',
    'flight_date_time_format' => 'D, d M Y H:i',
    'day_and_date_format' => 'D, d M Y', 
    'day_with_date_format'=> 'd-M-Y',


    'original_trip_type'        =>  [
                                    'One Way'       => 'ONEWAY',
                                    'Return'        => 'ROUND',
                                    'Multi City'    => 'MULTI',
                                    'Openjaw'       => 'OPENJAW',
                                ],

    'manual_review_approval_codes' => ["manualQcApproved","manualRiskVerified","excessPaymentApprovalCode","addressVerificationCode","creditVerificationCode","manualReview","excessDiscountVerificationCode"],
    'manual_review_reasons_msg' =>  [
                                    'manualQcApproved' => 'qc_failed_msg',
                                    'manualRiskVerified' => 'risk_failed_msg',
                                    'excessDiscountVerificationCode' => 'discount_error_details',
                                    'addressVerificationCode' => 'Address verfication failed',
                                    'excessPaymentApprovalCode' => 'Excess payment verification failed',
                                    'creditVerificationCode' => 'Credit verification failed',
                                ],

    'google_map_place_url'  => 'https://maps.googleapis.com/maps/api/place/textsearch/json',
    'google_map_key'        => 'AIzaSyC9ASe3M5_nB1YjaQArAqvJBb18rmAePEQ',

    'amadeus_url'           => 'https://test.api.amadeus.com/v1',
    'amadeus_client_id'     => 'Me2sRAOkZHWQpcxlHStPC3FjOEgtFq1g', //YrpwL40bkeDYitZK8Hzm9NOFwGtPCNIt
    'amadeus_client_secret' => 'BGrlH2qSNrDwo1sz', //MWWwjVQr4U2lx730
    'amadeus_auth_key'      => '',
    'hotel_bookings_max_days_limit'        => 60,//booking list display based config max days
    'hotel_bookings_default_days_limit'    => 3,//booking list display based config min days
    'display_mrms_transaction_details'     => 'Yes', // Yes - To display mrms transaction display or No - To hide mrms transaction display
    'route_page_settings_limit' =>  10,


    // Login History 

    'browser'   =>  [
            'ALL'                   =>  'ALL',
            'internet_explorer'     => 'Internet Explorer',
            'mozilla_firefox'       =>  'Mozilla Firefox',
            'google_chrome'         =>  'Google Chrome',
            'apple_safari'          =>  'Apple Safari',
            'opera'                 =>  'Opera',
            'netscape'              =>  'Netscape',
    ],
    'device'    =>  [
            'ALL'                   =>  'ALL',
            'mobile'                =>  'MOBILE',
            'system'                =>  'SYSTEM',
    ],
    
    'all_bussiness_type'        => ["B2B","B2C","META"],
    'search_mode'               =>  [
                                        'NORMAL',
                                        'META',
                                        'TICKET',
                                        'RESCHEDULE',
                                        'LOWFARE'
                                    ],
    'default_fop_data' => array(
                                "CC" => [
                                    "Allowed" => "N",
                                    "Types"   => []
                                ],
                                "DC" => [
                                    "Allowed" => "N",
                                    "Types"   => []
                                ],
                                "CHEQUE" => [
                                    "Allowed" => "N",
                                    "Types"   => []
                                ],
                                "CASH" => [
                                    "Allowed" => "N",
                                    "Types"   => []
                                ],
                                "ACH" => [
                                    "Allowed" => "N",
                                    "Types"   => []
                                ], 
                                "PG" => [
                                    "Allowed" => "N",
                                    "Types"   => []
                                ]
                          ), 

    'mobile_default_country_code' => 'ca',
    'default_payment_mode'    => 'ITIN',  
    'card_collect_pg'         => array('moneris'), 

    'ticketing_api_allowed_payment_modes' => array("CC","DC","CHEQUE","CHECK","CASH","ACH","PG"),

    'ticketing_api_bookings_allow_auto_ticketing' => true,                      
    'card_collect_pg'         => array('moneris'),
    'invoice_generate_booking_status' => ['102','104','117'],                       
    'max_execution_time'      => 280, //max executrion time for every five minutes crons
    'hold_booking_mail_trigger_time' => '5',
    'default_invoice_generate_period' => 2,
    'invoice_spoilage_amount_diff' => ['DEFAULT' => 0.2, 'CAD' => 0.2, 'USD' => 0.2, 'INR' => 0.2, 'EUR' => 0.2, 'GBP' => 0.2, 'AED' => 0.2,'BHD' => 0.2, 'SAR' => 0.2, 'AUD' => 0.2, 'LKR' => 0.2, 'KWD' => 0.2],
    'invoice_valid_thru' => 7,
    'customer_bookings_default_days_limit'    => 90,
    'customer_confirm_booking_status' => ['102', '110', '104', '106', '103', '111','116', '117', '118', '119'],
    'user_hote_booking_email_sent_count' => 5,
    'user_insurance_booking_email_sent_count' => 5,
    'user_package_booking_email_sent_count' => 5,
    'reward_type' => [
        'earn' => 'Earn',
        'redeem' => 'Redemption',
    ],

        'reward_fare_type' => [
            'BF' => 'Base Fare',
            'TF' => 'Total Fare',
            'BQ' => 'Base Fare + YQ'
          ],
          'additional_services' => [
            'SSR' => 'Ancillaries',
            'INS' => 'Insurance'
          ],        
    'allow_reward_point' => 'Y',
    'low_fare_shopping_types' => [
        'basic' =>   'Basic',
        'advanced' =>   'Advanced',
    ],
    'allow_for_ticketing'   =>  [
        'OLFA' => 'Only If Low Fare is Available',
        'OPFA' => 'Only If Private Fare is Available',
    ],
    'payment_type' => [
                        'FB' => 'For Balance',
                        'FI' => 'For Invoice',
                        'BD' => 'Booking Debit',
                        'BR' => 'Booking ReFund',
                    ],
    'mask_gds' => [
        'Sabre' => 'Sabre_Mask',
        'Travelport' => 'Travelport_Mask',
        'Tbo' => 'Tbo_Mask',
        'NdcAfKlm' => 'NdcAfKlm_Mask',
        'Ndcba' => 'Ndcba_Mask',
        'Flair' => 'Flair_Mask',
        'Amadeus' => 'Amadeus_Mask',
    ],

    'cookies_expire' => [
        'seconds' =>  'Seconds',
        'minutes' => 'Minutes',
        'hours' => 'Hours',
        'days'  =>  'Days',
        'weeks' =>  'Weeks',
        'months' =>  'Months',
        'years' =>  'Years',
    ],
    'mrms_risk_levels' => [
        'Yellow' => 'Yellow',
        'Red' => 'Red',
        'Green' => 'Green'
    ],
    'fop_type_array' =>  [
        'itin'=>'ITIN',
        'pg'=>'PG'
    ],
    'portal_fare_type'  =>  [
        'BOTH'   =>  'ALL',
        'PUB'  =>  'Public',                             
        'PRI'  =>  'Private',                             
    ],
    'response_type_selection'  =>  [
        'group' =>  'Group',
        'deal'  =>  'Deal',                             
    ],
    'pax_type' => [
        'ADT',
        'SCR',
        'YCR',
        'CHD',        
        'JUN',
        'INS',
        'INF'
    ],
    'insurance_mode' => [
        'live' => 'Live',
        'test' => 'Test'
    ],
    'theme_values' => [
        'default' => 'default',
        'zen' => 'zen',
        'mercury' => 'mercury',
        'mercury1' => 'mercury1',
    ],
    'theme_colors' =>  [
        'default' => 'default',
        'red' => '#cd373a',
    ],
    'meta_log_datatble_view_range' => 31,
    'fare_types_allowed' => ['PUB','PRI','ITX'],
    'max_retry_ticket_issue_limit' => 3,
    'allow_mrms_monitor_url' => 'no',
    'offline_status'    => [
        'I' => 'Initiated' ,
        'C' => 'Completed' ,
        'F' => 'Failure'   ,
        'P' => 'Processing',
        'R' => 'Rejected'  , 
    ],
    'payment_status'    =>  [
        "ALL"       =>  "ALL" ,
        "Success"   =>  "S", 
        "Initiated" =>  'I',
        "Hold"      =>  'H',
        "Failed"    =>  "F",
        "Cancelled" =>  'C',
    ],
    'allowed_fare_types_keys' => [  
        [ 
            'label' => 'Show',
            'value' => 'Y',
        ],
        [
            'label' => 'Hide',
            'value' => 'N',
        ],
        [
            'label' => 'Phone Deal',
            'value' => 'PHD',
        ],
    ],
    'week_days' => [
       'ALL',
       'SUN',
       'MON',
       'TUE',
       'WED',
       'THU',
       'FRI',
       'SAT'
    ],
    'invoice_statement_frequency_selection' => ['weekly'=>'Weekly','monthly'=>'Monthly','daily'=>'Daily','customdays'=>'Custom Days'],
    'pay_debit_or_credit' =>[
        'debit'   => 'Debit',
        'credit'  => 'Credit',       
    ],
    'markup_pax_types' => [
        'F' => [
            'ADT'=> 'Adult',
            'SCR'=> 'Senior Citizen',
            'YCR'=> 'Youth',
            'CHD'=> 'Child',
            'JUN'=> 'Junior',
            'INS'=> 'Infant on Seat',
            'INF'=> 'Infant on Lap'
        ],
        'H'=> [
            'ADT'=> 'Adult',
        ],
        'I'=> [
            'ADT'=> 'Adult',
            'CHD'=> 'Child',
            'INF'=> 'Infant on Lap'
        ]
    ],
    'markup_flight_classes' => [
        'F'=>[
            "ECONOMY"=> "Economy",
            "PREMECONOMY"=> "Premeconomy",
            "BUSINESS"=> "Business",
            "PREMBUSINESS"=> "Prembusiness",
            "FIRSTCLASS"=> "FirstClass"
        ],
        'H'=>[
            'STANDARD'=> 'Standard',
        ],
        'I'=>[
            'PREMIUM'=> 'Premium'
        ]
    ],
    'ref_type_details' => [
        'S'=>'Same As',
        'P'=>'Percentage'
    ],
    'markup_fare_types'  => [
        'PUB'=> 'Published',
        'PRI'=> 'JCB',
        'ITX'=> 'ITX Fares',
        'PFA'=> 'PFA Fares',
        'COM'=> 'Combination Fares'
    ],
    'origin_content' => 
    [
        'top_attractions' => 'Top Attractions',
        'connected_transportation' => 'Connected Transportation',
    ],
    'destination_content' => 
    [
        'top_attractions' => 'Top Attractions',
        'connected_transpotation' => 'Connected Transportation',
        'cash_saver_guide' => 'Cash Saver Guide',
        'best_time_to_travel' => 'Best Time To Travel',
        'quick_check_list' => 'Quick Check List',
        'food_drink' => 'Food And Drink',
    ],
      'route_page_icon' => [
        'top_attractions' => 'topattractions',
        'connected_transportation' => 'transportation',
        'cash_saver_guide' => 'cash',
        'best_time_to_travel' => 'timetravel',
        'quick_check_list' => 'quickcheck',
        'food_and_drink' => 'fooddrink',
      ],
    'credit_from_config' => [
        'CREDIT' => 'Credit',
        'FLIGHT' => 'Flight',
        'HOTEL' => 'Hotel',
        'INSURANCE' => 'Insurance',
        'LTBR' => 'Look to book ratio',
        'INVOICE' => 'Invoice'
    ],
    'allow_multiple_fop'        => 'Y',
    'allowed_markup_percentage' => 25,
    'allowed_markup'            => 'Y',
    'rule_type' =>[
        'M' => 'Markup',
        'D' => 'Discount',
        'FF' => 'FixedFare'
    ],
    'contract_rule_type' =>[
        'M' => 'Commission',
        'D' => 'Discount',
        'FF' => 'FixedFare'
    ],

    'payment_details_show'          => 'Y', //Ex : Y or N
    'show_extra_payment'            => 'Y', //Ex : Y or N
    'show_pnr_own_content_source'   => true,
    'hotel_payment_details_show'  => 'Y', //Ex : Y or N
    'show_extra_payment'        => 'Y', //Ex : Y or N
    'booking_view_mrms_payment_details_show' => 'Y',
    'cancelled'                     => 'Cancelled',

    'view_trip_type'    =>  [
        '1' => 'One Way',
        '2' => 'Round Trip',
        '3' => 'Multi City'
    ],
    'seat_type' =>  [
            'W'     => 'Window',
            'C'     => 'Center',
            'A'     => 'Asile',
            'WR'    => 'Wing Row',
            'ELR'   => 'Extra Leg Room',
            'ER'    => 'Exit Row'
    ],

    'hold_booking_deadline_added_time' => 30,
    'credit_card_details' => 
      array (
        'AX' => 'american_express',
        'MC' => 'master_card',
        'VI' => 'visa_card',
        'MA' => 'maestro',
        'DC' => 'diners_club',
        'DUS' => 'diners_club_us',
        'DI' => 'discover',
        'JC' => 'jcb',
        'RU' => 'RU',
      ),
    "share_url_types" => ['SU'=>'SU','SUF'=>'SUF','SUHB'=>'SUHB'],
    'credit_card_type' => [
        'AX'  => 'American Express',
        'MC'  => 'Master Card',
        'VI'  => 'Visa',
        'MA'  => 'Maestro',
        'DC'  => 'Diners Club',
        'DUS' => 'Diners Club US',
        'DI'  => 'Discover',
        'JC'  => 'JCB',
        'RU'  => 'Rupay',
    ],
    'allow_lfs_for_owow_booking' => true,
    'departure_date_time_valid' => 3,
    'terminal_app_redis_expire' => 60*5,
    'terminal_app_gds_selection' => [
        "1S" => "Sabre - 1S",
        "1V" => "Travelport Apollo - 1V",
        "1P" => "Travelport Worldspan - 1P",
        "1G" => "Travelport Galileo - 1G",
        "1A" => "Amadeus - 1A",
    ],
    'terminal_app_live_selection' => [
        'test' => 'Test',
        'live' => 'Live'
    ],
    'encrypt_types' => [
        "encryptData" => "Encrypted Data",
        "decryptData" => "Decrypted Data",
        "encryptBase64" => "Encrypt Base 64 Data",
        "decryptBase64" => "Decrypt Base 64 Data",
    ],
    'sql_query_view_limit_column' => 50,
    'pa_dynamic_markup_template_creation' => true, //true - Enable, false - Disable
    'dynamic_aggregation_creation' => true, //true - Enable, false - Disable
    'qc_check_with_card_number' => true,
    'review_check_with_card_holder' => true,
    'dashboard_bookings_data_count' => [
        'flight' => 10,
        'hotel' => 10,
        'insurance' => 10,
        'ticketing_queue' => 10,
    ],
    'day_and_date_format' => 'D, d M Y', 
    'day_with_date_format'=> 'd-M-Y',
    'redis_share_url_expire' => 240 * 60, //4 Hour
    'dummy_payment_details' => [                         
        "expMonth" => '07', 
        "expYear" => '2019',
        "cvv" => '787',
        "cardHolderName" => 'Reschudele Test Card',    
        "cardNumber" => '4111111111111111',    
    ],
    'redis_manual_setting' => 240 * 60, //4 Hours
    'insurance_retry_limit' => 3,
    'payment_mode_value' =>  array('CL'=>'credit_limit','FU'=>'fund','CP'=>'pay_by_card','CF'=>'cl_fund','PC'=>'pay_by_cheque','AC'=>'ach','PG' => 'pg'),   
    'nature_of_enquiry' => [
        'sales' =>  'Sales',
        'complaint' =>  'Complaint',
        'feedback' =>  'Feedback',
        'reissue_or_date_change' =>  'Reissue / Date Change',
        'cancellation_or_refund' =>  'Cancellation / Refund',
        'customer_service' =>  'Customer Service',
        'online_booking_support' =>  'Online Booking Support',
        'group_travel_quotes' =>  'Group Travel Quotes',
    ],
    'cms_pax_type' => [
        'adult'         => 'ADT',
        'senior_citizen'=> 'SCR',
        'youth'         => 'YCR',
        'child'         => 'CHD',        
        'junior'        => 'JUN',
        'infant'        => 'INS',
        'lap_infant'    => 'INF'
    ],

    'private_restricted_group' => array('G1','G2'),
    'trip_type' => [
        'ONEWAY',
        'ROUND',
        'MULTI',
        'OPENJAW'
    ],
    'contract_eligibility' => [
        'STUDENT' => 'Students',
        'MILITARY' => 'Military Man',
    ],
    'contract_non_eligibility' => [
        'STUDENT' => 'Students',
        'MILITARY' => 'Military Man',
    ],
    'log_view_activity' =>  [
        'storage_log' =>  [
            'path' => 'logs/',
            'title' => 'Laravel Log Data',
            'select' => [
                [
                    'title' => 'Laravel Log Files',
                    'date_key' => 'select_date',
                    'date_title' => 'Select Laravel Log File',
                    'file_key' => 'laravel-',
                    'button_id' => 'laravelLogButton',
                ],
            ]
        ],
        'booking_log' =>  [
            'path' => 'bookingStoreData/',
            'title' => 'Booking Store Data', 
            'select' => [
                [
                    'title' => 'Booking Store Log Files',
                    'date_key' => 'select_bk_date',
                    'date_title' => 'Select Booking Data Direct File',
                    'file_key' => 'bookingStoreData_',
                    'button_id' => 'bkFileViewButton',
                ],
            ]
        ],
        'mrms_log' =>  [
            'path' => 'logs/mrms/',
            'title' => 'MRMS Log Data',
            'select_key' => 'select_mrms_to_view',
            'select_title' => 'Select MRMS Log File To View',
            'select' => [
                [
                    'title' => 'MRMS Log Files',
                    'date_key' => 'select_mrms_date',
                    'date_title' => 'Select MRMS Log Direct File',
                    'file_key' => 'mrmsLog_',
                    'button_id' => 'mrmsFileViewButton',
                ],
            ]
        ],
        'invoice_cron_log' => [
            'path' => 'logs/invoiceLogs/',
            'title' => 'Invoice Cron Log Data',
            'select' => [
                [
                    'title' => 'Invoice Cron Log Files',
                    'date_key' => 'select_invoice_cron_date',
                    'date_title' => 'Select Invoice Cron Log Data Direct File',
                    'file_key' => 'invoice_cron_',
                    'button_id' => 'invoiceCronFileViewButton',
                ],
            ]
        ],
        'portal_api' =>  [
            'path' => 'apiLog/',
            'title' => 'Portal Log Data',
            'select' => [
                [
                    'title' => 'Portal Log Files',
                    'date_key' => 'select_portal_date',
                    'date_title' => 'Select Portal Log File',
                    'file_key' => 'portalApi_',
                    'button_id' => 'portalLogButton',
                ],
            ]
        ],
        'portal_credential' =>  [
            'path' => 'apiLog/',
            'title' => 'Portal Credential Log Data',
            'select' => [
                [
                    'title' => 'Portal Credential Log Files',
                    'date_key' => 'select_portal_credential_date',
                    'date_title' => 'Select Portal Credential Log File',
                    'file_key' => 'portalCredentialApi_',
                    'button_id' => 'portalCredentialLogButton',
                ],
            ]
        ],
        'exchange_rate' =>  [
            'path' => 'exchangeRate/',
            'title' => 'Exchange Rate Log Data',
            'select' => [
                [
                    'title' => 'Exchange Rate Log Files',
                    'date_key' => 'select_exchange_rate_date',
                    'date_title' => 'Select Exchange Rate Log File',
                    'file_key' => 'exchangeRate_',
                    'button_id' => 'exchangeRateLogButton',
                ],
            ]
        ],                                              
        'agent_approve_log' =>  [
            'path' => 'logs/agentApproveLogs/',
            'title' => 'Agent Approve Data',
            'select' => [
                [
                    'title' => 'Agent Approve Log Files',
                    'date_key' => 'select_agent_date',
                    'date_title' => 'Select Agent Approve Data Direct File',
                    'file_key' => 'approve_',
                    'button_id' => 'agentFileViewButton',
                ],
            ]
        ],
        'agency_approve_log' =>  [
            'path' => 'logs/agencyApproveLogs/',
            'title' => 'Agency Approve Log Data',
            'select' => [
                [
                    'title' => 'Agency Approve Log Files',
                    'date_key' => 'select_agency_date',
                    'date_title' => 'Select Agency Approve Log Direct File',
                    'file_key' => 'approve_',
                    'button_id' => 'agencyFileViewButton',
                ],
            ]
        ],
    ],
    'portal_details_config' => [
                                'portal_name' => 'Tripzumi India',
                                'agency_contact_email' => 'do-not-reply@tripzumi.com',
                              ],
    'package_bookings_max_days_limit' => 90,
    'package_bookings_default_days_limit' => 3,

    'itin_required_plugin' => true,
    'airline_image_path' => '/images/airline/',
    'pax_type_ref' => [
       'SCR' => ['ADT'],
       'YCR' => ['ADT', 'SCR'],
       'CHD' => ['ADT','YCR'],      
       'JUN' => ['ADT','CHD', 'YCR'],
       'INS' => ['ADT','CHD', 'YCR'],
       'INF' => ['ADT','CHD', 'YCR','INS']
    ],
    'mail_date_format' => 'D, d M Y',
    'content_source_api_mode'=>[
        'test'=>'Test',
        'cert'=>'Certification',
        'live'=>'Live',
    ],
    'currency_exchange_rate_import_file_save_path' => '/uploadFiles/currencyExchangeRateFiles/',
    'currency_exchange_rate_import_file_storage_loaction' => 'local',
    'booking_fee_type' => [
        'flight' => [
            'PB' => 'Per Booking',
            'PS' => 'Per Segment',
            'PJ' => 'Per Journey',
            'PP' => 'Per Pax',
            'PT' => 'Per Trip',
        ],
        'hotel' => [
            'PR' => 'Per Room',
            'PRN' => 'Per Room OR Night',
            'PN' => 'Per Night',
        ],
        'insurance' => [
            'PPCY' => 'Per Policy',
            'PC' => 'Per Car',
            'PCD' => 'Per Care OR Day',
        ],
    ],
    'fee_type_as_per_booking' => [
        'AOT' => 'Add On Total', 
        'AIF' => 'Apply As An Individual Fee', 
    ],
    'booking_fee_fare_types' => [
        'flight' => [
            'PUB'=> 'Published',
            'PRI'=> 'JCB',
            'ITX'=> 'ITX Fares',
            'PFA'=> 'PFA Fares',
            'COM'=> 'Combination Fares',
        ],
        'hotel' => [
            'PUB'=> 'Published'
        ],
        'insurance' => [
            'PUB'=> 'Published'
        ]
    ],
    'booking_fee_calculation_on' => [
        'flight' =>[
            'PPBF' =>'Per Pax Base Fare',
            'PPTF' => 'Per Pax Total Fare',
        ],
        'hotel' => [
            'PR'=> 'Per Room',
            'PN'=>'Per Night',
        ],
        'insurance' =>[
            'PPBF' => 'Per Pax Base Fare',
            'PPTF' => 'Per Pax Total Fare',
        ]
    ],

    'store_logs_in_minio' => true,
    'b2b_default_language' => ['EN','FR'],

    'add_res_time' => true,
];