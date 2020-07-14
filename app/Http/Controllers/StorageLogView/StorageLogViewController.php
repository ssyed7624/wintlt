<?php

namespace App\Http\Controllers\StorageLogView;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use DB;

class StorageLogViewController extends Controller
{
    public function index()
    {
        $returnArray = [];
        $returnArray['config_data'] = config('common.log_view_activity');
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'log_index_data';
        $responseData['message']        = 'log index data success';
        $responseData['data']           = $returnArray;
    	return response()->json($responseData);
    }

    public function fileViewer(Request $request)
    {
        $inputVal = $request->all();
        $dir = storage_path($inputVal['folder'].'/'.$inputVal['file_name']);
        if(file_exists($dir))
        {
            $file = file_get_contents($dir);
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'log_file_view_success';
            $responseData['message']        = 'log file view success';
            $responseData['data']           = $file;        
        }
        else
        {
            $file = 'File Not Exists';
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.failed');
            $responseData['short_text']     = 'file_not_exists';
            $responseData['message']        = 'file not exists';
        }

        return response()->json($responseData);
    }
}
