<?php 
return [

	'supplier_pos_rule_criterias' => [
		'default' => [
			'flight' => [],
            'insurance' => [],
            'hotel'		=> [],
            'validation_mandatory' => [],
		],
		'optional' => [
			'flight' => [
                'contentSource',
                'noOfSeats',
                'onwardMarketingAirline',                                
                'passengerType',
				'onwardBlockoutDepartureDate',
                'onwardDepartureDate',
                'bookingPeriod',
				'bookingDayScheduler',        						
				'fareRange',
				'onwardNoOfStops',
				'onwardDepartureDayScheduler',
				'onwardBookingClass',
				'onwardFareBasisCode',
				'onwardOperatingAirline',
				'origin',
				'destination',
				'onwardFlightNumber',
				'stopOver',
				'onwardSegmentCount',
				'minStay',
				'maxStay',
                'ticketingDate',
                'daysToDeparture',
                'originalTripType',
			],
			'insurance' => [],
			'hotel'		=> [],
		],
	    'table' => 'supplier_pos_rule_criterias',
	    'master_table' => [
	            'table'            => 'supplier_pos_rules', 
	            'map_id'           => 'pos_rule_id',
	            'criteria_map_id'  => 'pos_rule_id'
	    ],
    ],
    'supplier_pos_contract_criterias' => [
    	'default' => [
			'flight' => [
                'contentSource',
                        ],
            'insurance' => [],
            'hotel'		=> [],
            'validation_mandatory' => [
                'contentSource',
            ],
		],
		'optional' => [
			'flight' => [
				'onwardBlockoutDepartureDate',
                'onwardDepartureDate',
                'bookingPeriod',
                'bookingDayScheduler',                              
                'fareRange',
                'onwardNoOfStops',
                'onwardDepartureDayScheduler',
                'onwardBookingClass',
                'onwardFareBasisCode',
                'onwardOperatingAirline',
                'origin',
                'destination',
                'onwardFlightNumber',
                'stopOver',
                'onwardSegmentCount',
                'minStay',
                'maxStay',
                'ticketingDate',
                'daysToDeparture',
                'onwardMarketingAirline',
			],
			'insurance' => [],
			'hotel'		=> [],
		],
	    'table' => 'supplier_pos_contract_criterias',
	    'master_table' => [
	            'table'            => 'supplier_pos_contracts', 
	            'map_id'           => 'pos_contract_id',
	            'criteria_map_id'  => 'pos_contract_id'
	    ],
    ],

    'supplier_airline_masking_rule_criterias' => [
        'optional' => [
            'flight' => [
                'bookingPeriod',
                'onwardDepartureDate',
                'bookingDayScheduler',
                'onwardDepartureDayScheduler',
                'onwardBookingClass',
            ],
            'insurance' =>  [],
            'hotel'     =>  [],
        ],
        'default' => [
            'flight'    =>  [],
            'hotel'     =>  [],
            'insurance' =>  [],
            'validation_mandatory' => [],
        ],
        'table' => 'supplier_airline_masking_rule_criterias',
        'master_table' => [
                'table'            => 'supplier_airline_masking_rules', 
                'map_id'           => 'airline_masking_rule_id',
                'criteria_map_id'  => 'airline_masking_rule_id'
        ],
    ],
    "model_based_config" => [
    	"supplier_pos_rules" => "supplier_pos_rule_criterias",
    ],
    'product_type' =>  	[
                            'flight',
                            'hotel',
                            'insurance'
	],
	'risk_analysis_template_rule_criterias' => [
        'optional' => [
            'flight' => [
                'origin',
                'destination'   
            ],
            'insurance' => [],
            'hotel'		=> [],     
        ],
        'default' => [
            'flight' => [],
            'insurance' => [],
            'hotel'		=> [],
            'validation_mandatory' => [],
        ],
        'table' => 'risk_analysis_template_rule_criterias',
        'master_table' => [
            'table'            => 'risk_analysis_template', 
            'map_id'           => 'risk_template_id',
            'criteria_map_id'  => 'risk_template_id'
        ],
    ],
    'supplier_airline_blocking_rule_criterias' => [
        'optional' => [
            'flight' => [
                    'bookingPeriod',
                    'onwardDepartureDate',
                    'bookingDayScheduler',
                    'onwardDepartureDayScheduler',
                ],
                'insurance' => [],
                'hotel'		=> [],
        ],
        'default' => [
            'flight' => [
                'origin',
                'destination',
                'onwardBookingClass',
            ],
            'insurance' => [],
			'hotel'		=> [],
            'validation_mandatory' => [
                'origin',
                'destination',
                'onwardBookingClass',
            ],
        ],
        'table' => 'supplier_airline_blocking_rule_criterias',
        'master_table' => [
                'table'            => 'supplier_airline_blocking_rules', 
                'map_id'           => 'airline_blocking_rule_id',
                'criteria_map_id'  => 'airline_blocking_rule_id'
        ],
    ],

    // Portal Airline Blocking Rules Criteria
    'portal_airline_blocking_rule_criterias'        =>  [
        'optional' => [
            'flight' => [
                'bookingPeriod',
                'onwardDepartureDate',
                'bookingDayScheduler',
                'onwardDepartureDayScheduler',
                'origin',
                'destination',
                'onwardBookingClass',
            ],
            'insurance' => [],
            'hotel'		=> [], 
        ],
        'default' =>  [
            'flight'    => [],
            'insurance' => [],
            'hotel'		=> [], 
            'validation_mandatory' => [],
        ],
        'table'             => 'portal_airline_blocking_rule_criterias',
        'master_table'      =>  [
                                    'table'            => 'portal_airline_blocking_rules', 
                                    'map_id'           => 'airline_blocking_rule_id',
                                    'criteria_map_id'  => 'airline_blocking_rule_id'
                                ],
    ],
    // Portal Airline Masking Rules Criteria
    'portal_airline_masking_rule_criterias'         =>  [
        'optional' => [
            'flight' => [
                'bookingPeriod',
                'onwardDepartureDate',
                'bookingDayScheduler',
                'onwardDepartureDayScheduler',
                'onwardBookingClass',
            ],
            'insurance' => [],
            'hotel'		=> [], 
        ],  
        'default' => [
            'flight'    => [],
            'insurance' => [],
            'hotel'		=> [], 
            'validation_mandatory' => [],
        ],
        'table' => 'portal_airline_masking_rule_criterias',
        'master_table' => [
                'table'            => 'portal_airline_masking_rules', 
                'map_id'           => 'airline_masking_rule_id',
                'criteria_map_id'  => 'airline_masking_rule_id'
        ],
    ],
    // Suppller Surcharge

    'supplier_surcharge_criterias' => [
        'optional' => [
            'flight' =>[
                'onwardDepartureDate',
                'onwardArrivalDate',
                'onwardDepartureDayScheduler',
                'onwardArrivalDayScheduler',
                'noOfSeats'
            ],
            'hotel' => [
                'countryStateCity',
                'noOfRatings',
                'noOfRooms',
                'checkInDate',
                'checkoutDate', 
                'bookingPeriod'
            ],
            'insurance' =>  [
                'bookingPeriod'

            ]
            ],        
        'default' => [
            'flight'    =>  [],
            'hotel'     =>  [],
            'insurance' =>  [],
            'validation_mandatory' => [],
        ],
 
        'table' => 'supplier_surcharge_criterias',
        'master_table' => [
                'table'            => 'supplier_surcharge_details', 
                'map_id'           => 'surcharge_id',
                'criteria_map_id'  => 'surcharge_id'
        ],
    ],

    'markup_group_contract_criterias' => [
        'default' => [
            'flight' => [],
            'insurance' => [],
            'hotel'     => [],
            'validation_mandatory' => [],
        ],
        'optional' => [
            'flight' => [
                'onwardBlockoutDepartureDate',
                'onwardDepartureDate',
                'bookingPeriod',
                'bookingDayScheduler',                              
                'fareRange',
                'onwardNoOfStops',
                'onwardDepartureDayScheduler',
                'onwardBookingClass',
                'onwardFareBasisCode',
                'onwardOperatingAirline',
                'origin',
                'destination',
                'onwardFlightNumber',
                'stopOver',
                'onwardSegmentCount',
                'minStay',
                'maxStay',
                'ticketingDate',
                'daysToDeparture',
                'contentSource',
                'onwardMarketingAirline',
            ],
            'insurance' => [],
            'hotel'     => [],
        ],
        'table' => 'supplier_pos_rule_criterias',
        'master_table' => [
                'table'            => 'supplier_pos_rules', 
                'map_id'           => 'pos_rule_id',
                'criteria_map_id'  => 'pos_rule_id'
        ],
    ],

    'markup_rule_criterias' => [
        'default' => [
            'flight' => [
                // 'contentSource',
                // 'noOfSeats',
                // 'onwardMarketingAirline',                                
                // 'passengerType',
                        ],
            'insurance' => [],
            'hotel'     => [],
            'validation_mandatory' => [
                // 'contentSource',
                // 'noOfSeats',
                // 'onwardMarketingAirline',                                
                // 'passengerType',
            ],
        ],
        'optional' => [
            'flight' => [
                'onwardBlockoutDepartureDate',
                'onwardDepartureDate',
                'bookingPeriod',
                'bookingDayScheduler',                              
                'fareRange',
                'onwardNoOfStops',
                'onwardDepartureDayScheduler',
                'onwardBookingClass',
                'onwardFareBasisCode',
                'onwardOperatingAirline',
                'origin',
                'destination',
                'onwardFlightNumber',
                'stopOver',
                'onwardSegmentCount',
                'minStay',
                'maxStay',
                'ticketingDate',
                'daysToDeparture',
                'originalTripType',
                'onwardMarketingAirline',
                'contentSource',
                'noOfSeats',
                'passengerType'
            ],
            'insurance' =>  [
                'bookingPeriod',
                'bookingDayScheduler',
                'contentSource',
                'fareRange',                                
                'minStay',
                'maxStay',
            ],
            'hotel'     => [
                'countryStateCity',
                'noOfRooms',
                'noOfRatings',
                'checkInDate',
                'checkoutDate',                        
                'bookingPeriod',
                'bookingDayScheduler',
                'contentSource',
                'fareRange',                                
                'minStay',
                'maxStay',
            ],
        ],
        'table' => 'supplier_pos_rule_criterias',
        'master_table' => [
                'table'            => 'supplier_pos_rules', 
                'map_id'           => 'pos_rule_id',
                'criteria_map_id'  => 'pos_rule_id'
        ],
    ],

    'portal_route_blocking_rule_criterias'          => [
        'optional' => [
            'flight' => [
                'origin',
                'destination',            
                'onwardDepartureDate',
                'bookingDayScheduler',
                'onwardDepartureDayScheduler',
                //'onwardBookingClass',
            ],
            'insurance' => [],
            'hotel'		=> [], 
        ],
        'default'   =>    [
            'flight'    => ['bookingPeriod'],
            'insurance' => [],
            'hotel'		=> [], 
            'validation_mandatory' => ['bookingPeriod'],
        ],
        'table' => 'portal_route_blocking_rule_criterias',
        'master_table' => [
                'table'            => 'portal_route_blocking_rules', 
                'map_id'           => 'route_blocking_rule_id',
                'criteria_map_id'  => 'route_blocking_rule_id'
        ],
    ],

    'supplier_route_blocking_rule_criterias'        =>  [
        'optional'  =>  [
            'flight'    =>  [
                'origin',
                'destination',
                'onwardDepartureDate',
                'bookingDayScheduler',
                'onwardDepartureDayScheduler',
            ],
            'insurance'  => [],
            'hotel'	     => [], 
        ],
        'default'   =>      [
            'flight'    =>  ['bookingPeriod'],
            'insurance' =>  [],
            'hotel'		=>  [], 
            'validation_mandatory' => ['bookingPeriod'],
        ],

        'table' => 'supplier_route_blocking_rule_criterias',
        'master_table' => [
                'table'            => 'supplier_route_blocking_rules', 
                'map_id'           => 'route_blocking_rule_id',
                'criteria_map_id'  => 'route_blocking_rule_id'
        ],
    ],
  //Supplier Lowfare Template criterias
  'supplier_lowfare_template_criterias'             =>  [
                                                           
        'optional' =>   [
            'flight'    =>  [
                'origin',
                'destination',
                'bookingPeriod',
            ],
            'insurance'  => [],
            'hotel'	     => [], 
        ],
        'default'   =>  [
            'flight'     =>     [],
            'insurance'  =>     [],
            'hotel'	     =>     [], 
        ],  
        'table' => 'supplier_lowfare_template_rule_criterias',
        'master_table' => [
                'table'            => 'supplier_lowfare_template', 
                'map_id'           => 'lowfare_template_id',
                'criteria_map_id'  => 'lowfare_template_id'

        ],
    ],

    'ticketing_rules'                               =>  [
        'default'   =>  [
            'flight'    =>  [],
            'insurance' =>  [],
            'hotel'		=>  [],
            'validation_mandatory' => [],
        ],
        'optional' =>   [
            'flight'    =>  [
                'origin',
                'destination',
                'validatingAirline',
                'onwardBookingClass',
                'onwardFlightNumber',
                'onwardFareBasisCode',
                'bookingPeriod',
                'minStay',
                'corporateCode',
                'maxStay',
            ],   
            'insurance' =>  [],
            'hotel'		=>  [],
        ],
        'table' => 'ticketing_rule_criterias',
        'master_table' => [
                'table'            => 'ticketing_rules', 
                'map_id'           => 'ticketing_rule_id',
                'criteria_map_id'  => 'ticketing_rule_id'
        ],
    ],
    'fare_range_F' => [
        'PPBF'=>'per pax base fare',
        'PPTF'=>'per pax total fare',
        'PPBFPYQ'=>'per pax base fare + yq',
        'PPTFMYQ'=>'per pax base fare + yq',
    ],
    'fare_range_H' => [
        'PR'=>'per room',
        'PN'=>'per pax total fare',
    ],
    'fare_range_I' => [
        'PPBF'=>'per pax base fare',
        'PPTF'=>'per pax total fare',
    ],    
    'pax_type' => [
        'ALL'=>'all',
        'ADT'=>'adult',
        'SCR'=>'senior citizen',
        'YCR'=>'youth',
        'CHD'=>'child',
        'JUN'=>'junior',
        'INS'=>'infant',
        'INF'=>'infant on lap',
    ],
    'fare_basis' => [
        'IN' => 'equal' ,
        'SW' => 'starting with',
        'EW' => 'ending with',
        'HAS' => 'contains',
        'NTH' => 'nth characters',
    ],
    'trip_type' => [
        'ALL' => 'all',
        'ONEWAY' => 'oneway',
        'MULTI' => 'multiple',
        'ROUND' => 'round',
        'OPENJAW' => 'openjaw',
    ],
    'profile_aggregation_criterias' => [
        'default'=>[
            'flight'    =>  [],
            'insurance' =>  [],
            'hotel'		=>  [],
            'validation_mandatory' => [],
        ],

        'optional' => [
            'flight'    =>  [
                'tripType', 
                'noOfSeats', 
                'bookingPeriod',
                'onwardDepartureDate',
                //'returnDepartureDate',
                //'daysToDeparture',
                'onwardBlockoutDepartureDate',
                //'returnBlockoutDepartureDate',
                'origin',
                'destination',
                'onwardDepartureDayScheduler',
            ],
            'hotel'         =>  [
                'countryStateCity',
                //'noOfRatings',
                'noOfRooms',
                'checkInDate',
                'checkoutDate', 
                'bookingPeriod'
            ],
            'insurance'  => [
                'bookingPeriod'
            ]
        ],
        'table' => 'profile_aggregation_criterias',
        'master_table' => [
                'table'            => 'profile_aggregation', 
                'map_id'           => 'profile_aggregation_id',
                'criteria_map_id'  => 'aggregation_id'
        ],
    ],

    'booking_fee_rules_criterias' => [
        'default' => [
            'flight' => [],
            'insurance' => [],
            'hotel'     => [],
            'validation_mandatory' => [],
        ],
        'optional' => [
            'flight' => [
                'origin',
                'destination',
                'originalTripType',
                'onwardMarketingAirline',                                
                'bookingDayScheduler',
                'onwardDepartureDate',
                'onwardBlockoutDepartureDate',
                'onwardBookingClass',
                'fareRange',
                'tripType'
            ],
            'insurance' => [
                'origin',
                'destination',
                'onwardBlockoutDepartureDate',
                'bookingPeriod',
            ],
            'hotel' => [
                'countryStateCity',
                'noOfRooms',
                'noOfRatings',
                'checkInDate',
                'checkoutDate',                        
                'bookingPeriod'
            ],
        ],
        'table' => 'booking_fee_rules',
        'master_table' => [
                'table'            => 'booking_fee_rules', 
                'map_id'           => 'booking_fee_rule_id',
                'criteria_map_id'  => 'booking_fee_rule_id'
        ],
    ],

    'supplier_remark_template_criterias' => [
        'default' => [
            'flight' => [],
            'insurance' => [],
            'hotel'     => [],
            'validation_mandatory' => [],
        ],
        'optional' => [
            'flight' => [
                'origin',
                'destination',
                'originalTripType',
                'onwardMarketingAirline',                                
                'bookingDayScheduler',
                'onwardDepartureDate',
                'onwardBlockoutDepartureDate',
                'onwardBookingClass',
                'fareRange',
                'tripType'
            ],
            'insurance' => [],
            'hotel' => [],
        ],
        'table' => 'supplier_remark_templates',
        'master_table' => [
                'table'            => 'supplier_remark_templates', 
                'map_id'           => 'supplier_remark_template_id',
                'criteria_map_id'  => 'supplier_remark_template_id'
        ],
    ],

];