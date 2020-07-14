<?php 
return [

'flight_classes'        =>  [
                                'Y' => 'ECONOMY',
                                'S' => 'PREMECONOMY',
                                'C' => 'BUSINESS',
                                'D' => 'PREMBUSINESS',
                                'F' => 'FIRSTCLASS'
                            ],
'original_trip_type'    =>  [
                                '1' => 'Oneway',
                                '2' => 'RoundTrip',
                                '3' => 'Multicity'
                            ],
'passanger_type'        =>  [
                                'ADT'  => 'adult',
                                'CHD'  => 'child',  
                                'INF'  => 'Infant on Lap',
                            ],
'alternate_dates'       =>  [
                                '0' => '+/- 0 Days',
                                '1' => '+/- 1 Days',
                                '2' => '+/- 2 Days',
                                '3' => '+/- 3 Days',
                            ],   
'flight_criteria'       =>  [
                                'include'   => 'Include',
                                'exclude'   => 'Exclude',
                            ],
'pax_type' => [
        'adult'         => 'ADT',
        'senior_citizen'=> 'SCR',
        'youth'         => 'YCR',
        'child'         => 'CHD',        
        'junior'        => 'JUN',
        'infant'        => 'INF',
        'lap_infant'    => 'INF'
    ],
'aggregation_fare_types'    => [
                                'PUB'=>'Published',
                                'PRI'=>'JCB',
                                'ITX'=>'ITX Fares',
                                ],
'aggregation_market_types'  =>  [
                                    'B2B'=>'B2B',
                                    'B2C'=>'B2C',
                                    'B2C_META'=>'B2C Meta',
                                ],
'max_content_source_add_aggregation' => 15,

'redis_expire' => 240 * 60, //4 Hours
'lfb_initiated_expire' => 3 * 60, //4 Hours
'redis_share_url_expire' => 240 * 60, //4 Hours
'redis_recent_search_req_expire' => 1440 * 60, //1 Day

'no_of_rooms_allowed' => 3,

'ssr_enabled' => true,

'gds_currency_display' => 'Y',

'flight_gender' => [
        'M',
        'F',
    ],
'seat_preference' => [
    'any',
    'aisle',
    'window',
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
'allowed_ffp_airlines' => [
        'Marketing' => 'Y',
        'Operating' => 'N',
        'Validating'=> 'N',
    ],

'search_original_trip_type'     =>  [
                                        '1' => 'oneway',
                                        '2' => 'return',
                                        '3' => 'multi'
                                    ],
'search_passanger_type'         =>  [
                                        'adult'       => 'ADT' ,
                                        'child'       => 'CHD' ,
                                        'lap_infant'  => 'INF' 
                                    ],
'search_alternate_dates'        =>  [
                                        '0',
                                        '1',
                                        '2',
                                        '3',
                                    ], 

'reschedule_change_fee' => 'B', //B - Before, A - After 
'retry_booking_max_limit' => 2,
'lpad_min_length_cheque_number' => 6,
'redis_flight_search_request_expire' => 43200,

'recent_search_required' => true, // Is required recent search or not
'max_recent_search_allowed' => 10,

'hotel_recent_search_required' => true, // Is required hotel recent search or not
'hotel_max_recent_search_allowed' => 10,

'insurance_recent_search_required' => true, // Is required insurance recent search or not
'insurance_max_recent_search_allowed' => 10,

'package_recent_search_required' => true, // Is required package recent search or not
'package_max_recent_search_allowed' => 10,

'flight_failed_itin_redis' => 1440 * 60, //1 Day

];