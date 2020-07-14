<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Libraries\Common;



class MasterDataUpload extends Command
{
	protected $signature = 'MasterDataUpload:masterDataUpload';

    protected $description = 'MasterDataUpload';

    public function __construct()
    {
        parent::__construct();
    }
    
    public function handle()
    {

        Common::uploadMasterFiles();


    }
}
?>