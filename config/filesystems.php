<?php
$path = public_path() . "/googleCloudeApiJson/clarity-tts-70382cb4c4b0.json";
return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Default Cloud Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Many applications store files both locally and in the cloud. For this
    | reason, you may specify a default "cloud" driver here. This driver
    | will be bound as the Cloud disk implementation in the container.
    |
    */

    //'cloud' => env('FILESYSTEM_CLOUD', 's3'),
    'cloud' => env('FILESYSTEM_CLOUD', 'gcs'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3", "rackspace"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            //'root' => storage_path('app'),
            'root' => public_path(),
        ],
        'flight_log_local' => [
            'driver' => 'local',
            'root' => storage_path(),
        ],
        'storage' => [
            'driver' => 'local',
            'root' => storage_path(),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
        ],

        'minio' => [
            'driver' => 'minio',
            'key' => 'AKIA6M72PWCVO2BYUPXP',
            'secret' => '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',
            'region' => 'us-east-1',
            'bucket' => 'b2b2c-logs',
            'endpoint' => 'http://172.20.175.43:9000'
        ],

        'gcs' => [
            'driver'           => 'gcs',
            'project_id'       => env('GOOGLE_CLOUD_PROJECT_ID', 'clarity-tts'),
            'key_file'         => env('GOOGLE_APPLICATION_CREDENTIALS', $path), // optional: /path/to/service-account.json
            'bucket'           => env('GOOGLE_CLOUD_STORAGE_BUCKET', 'otp-cloud-storage'),
            'path_prefix'      => env('GOOGLE_CLOUD_STORAGE_PATH_PREFIX', null), // optional: /default/path/to/apply/in/bucket            
            'storage_api_uri' => env('GOOGLE_CLOUD_STORAGE_API_URI', 'https://storage.googleapis.com/otp-cloud-storage/'), // see: Public URLs below
        ],

         'custom-ftp' => [
                
            'driver' => 'ftp',
                        
            'host' => '127.0.0.1',
                        
            'username' => 'TEST',
                        
            'password' => 'TEST',

            'root' => '/var/www/ddd/storage/master-data',

            // Optional FTP Settings...
            // 'port'     => 21,            
            // 'passive'  => true,
            // 'ssl'      => true,
            // 'timeout'  => 30,
        ],

    ],

];
