<?php 
return [
	'cache_version'    => '28082018',

    'engine_url'       => 'https://newapi.tltid.com/',
    'split_resonse'    => 'Y', //Y - Yes , N - No
    'engine_version'   => '',

    'api_url'          => 'http://localhost/api',

    // User Auth Client

    'grant_type'         => 'password',
    'client_id'          => 2,
    'client_secret'      => 'RykJF9l0aABhjYjMIFlvnF2kEByB4M3bhunCup45',
    'provider'           => 'api',
    'scope'              => '*',
    

    // Customer Auth Client

    'cust_grant_type'         => 'password',
    'cust_client_id'          => 3,
    'cust_client_secret'      => 'ZBaNjmtfqWgKp5Vpjywd5pE2oUJVrqEXDPtnmUSx',
    'cust_provider'           => 'customer',
    'cust_scope'              => '*',

    'contact_from_email' => 'no-reply@clarity.com',
    'contact_to_email' => [],
    'contact_cc_email' => [],
    'contact_bcc_email' => ['r.subba@wintlt.com'],
    'email_config_from' => 'do-not-reply@cttsid.com',
    'email_config_to' => 'do-not-reply@cttsid.com',
    'email_config_username' => '749cbf521f0a3bb490796c29ccc72d31',
    'email_config_password' => '0a908cdac67f6a478355091912c97681',
    'email_config_host' => 'in-v3.mailjet.com',
    'email_config_port' => '465',
    'email_config_encryption' => 'ssl',

    'use_erun_config'   => false,
    'erun_action_ip'    => 'localhost', // 192.168.147.141
    'erun_action_port'  => '80', // 8053

];
