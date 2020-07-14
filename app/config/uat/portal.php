<?php 
return [
	'cache_version'    => '28082018',

    'engine_url'       => 'http://localhost:5200/',
    'split_resonse'    => 'Y', //Y - Yes , N - No
    'engine_version'   => '',

    'api_url'          => 'http://localhost/TTSAPI/public/api',

    // User Auth Client

    'grant_type'         => 'password',
    'client_id'          => 2,
    'client_secret'      => 'QvvrmuT0rNnnCXS14D8hehp6sfIguAvOru3lv7qd',
    'provider'           => 'api',
    'scope'              => '*',
    

    // Customer Auth Client

    'cust_grant_type'         => 'password',
    'cust_client_id'          => 6,
    'cust_client_secret'      => 'bcJbx5BYU9HqIgp87N3l8Y9UrnZc7pLVFrXfigvA',
    'cust_provider'           => 'customer',
    'cust_scope'              => '*',

    'contact_from_email' => 'no-reply@clarity.com',
    'contact_to_email' => [],
    'contact_cc_email' => ['naveenmrms@gmail.com'],
    'contact_bcc_email' => ['a.divakar@wintlt.com'],
    'email_config_from' => 'no-reply.clarity@gmail.com',
    'email_config_to' => 'a.divakar@wintlt.com',
    'email_config_username' => '749cbf521f0a3bb490796c29ccc72d31',
    'email_config_password' => '0a908cdac67f6a478355091912c97681',
    'email_config_host' => 'in-v3.mailjet.com',
    'email_config_port' => '465',
    'email_config_encryption' => 'ssl',

    'use_erun_config'   => false,
    'erun_action_ip'    => 'localhost', // 192.168.147.141
    'erun_action_port'  => '80', // 8053

];
