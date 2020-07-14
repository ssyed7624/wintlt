<?php

namespace App\Http\Controllers\BackendData;

use App\Http\Controllers\Controller;
use App\Http\Middleware\UserAcl;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Validator;
use Storage;
use Auth;
use DB;

class BackendDetailsController extends Controller
{
	public function __construct()
	{
		if(!UserAcl::isSuperAdmin())
		{
			$outputArrray['message']             = 'Only Super Admin Can access this Page';
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'super_admin_can_access_this_page';
            $outputArrray['status']              = 'failed';
        	return response()->json($outputArrray);
		}
	}
	public function getformData()
	{
		$encrtptTypes = [];
		foreach (config('common.encrypt_types') as $key => $value) {
			$tempEncrypt['value'] = $key; 
			$tempEncrypt['label'] = $value;
			$encrtptTypes[] = $tempEncrypt;
		}
        $outputArrray['status'] = 'success';
        $outputArrray['status_code'] = config('common.common_status_code.success');
        $outputArrray['short_text'] = 'encryption_data_success';
        $outputArrray['message'] = 'form data sucess';
        $outputArrray['data'] = $encrtptTypes;
        return response()->json($outputArrray);
	}

	public function getEncryptionData(Request $request)
	{
		$inputArray = $request->all();
		$rules  =   [
            'encryption_type'       		=> 'required',
            'values'     					=> 'required',
        ];
        $message    =   [
            'encryption_type.required'      =>  __('common.account_id_required'),
            'values.required'    			=>  __('contentSource.content_source_id_required'),
        ];
        $validator = Validator::make($inputArray, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }

        $outputArrray = [];
        switch ($inputArray['encryption_type']) {
        	case 'encryptData':
        		$value = encryptData($inputArray['values']);
        		break;
        	case 'decryptData':
        		$value = decryptData($inputArray['values']);
        		break;
        	case 'encryptBase64':
        		$value = base64_encode($inputArray['values']);
        		break;
        	case 'decryptBase64':
        		$value = base64_decode($inputArray['values']);
        		break;
        	
        }
        $outputArrray['status'] = 'success';
        $outputArrray['status_code'] = config('common.common_status_code.success');
        $outputArrray['short_text'] = 'encryption_data_success';
        $outputArrray['message'] = 'Encryption decryption data sucess';
        $outputArrray['data']['value'] = $value;
        return response()->json($outputArrray);
	}

	public function getSqlResults(Request $request)
	{
		$inputArray = $request->all();
		$rules  =   [
            'query'       		=> 'required',
        ];
        $message    =   [
            'query.required'      =>  __('common.account_id_required'),
        ];
        $validator = Validator::make($inputArray, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }

        $checkQuery = strtoupper($request['query']);
        $limitscolumn = config('common.sql_query_view_limit_column');
    	try { 
    		if(strpos($checkQuery,'SELECT') !== false && strpos($checkQuery,'INSERT') === false && strpos($checkQuery,'DELETE') === false && strpos($checkQuery,'DROP') === false && strpos($checkQuery,'UPDATE ') === false && strpos($checkQuery,'TRUNCATE') === false && strpos($checkQuery,'CREATE ') === false && strpos($checkQuery,'ALTER') === false) 
    		{
    			if(strpos($request['query'],';'))
    			{
    				$query = substr($request['query'],0,strpos($request['query'],';'));
                    if(strpos($query, 'LIMIT') || strpos($query, 'limit'))
                    {
                       $query = $query; 
                    }
                    else
                    {
                        $query = $query.' LIMIT '.$limitscolumn;
                    }

    			}
    			else
    			{
                    if(strpos($checkQuery, 'LIMIT'))
                    {
                       $query = $request['query']; 
                    }
                    else
                    {
                        $query = $request['query'].' LIMIT '.$limitscolumn;
                    }
    				
    			}

    			
    				$results = DB::select($query);    							
    		}
    		else
    		{	
    			$outputArrray['status'] = 'failed';
		        $outputArrray['status_code'] = config('common.common_status_code.validation_error');
		        $outputArrray['short_text'] = 'select_statement_will_work';
		        $outputArrray['message'] = 'only select statement will work';
    			return response()->json($outputArrray);
    		}
    	// select * from user_details	
    	} 
    	catch (Exception $e) {
    		$outputArrray['status'] = 'failed';
	        $outputArrray['status_code'] = config('common.common_status_code.failed');
	        $outputArrray['short_text'] = 'internal_error';
	        $outputArrray['message'] = 'execute sql internal error';
	        $outputArrray['error'] = $e->getMessage();
    		return response()->json($outputArrray);
    	}
 
        $outputArrray['status'] = 'success';
        $outputArrray['status_code'] = config('common.common_status_code.success');
        $outputArrray['short_text'] = 'execute_sql_data_success';
        $outputArrray['message'] = 'execute sql data successfully';
        $outputArrray['data']['result'] = $results;
        $outputArrray['data']['json_result'] = json_encode($results);
        return response()->json($outputArrray);
	}

	public function downloadSqlQueryResult(Request $request)
    {
        if(isset($request->download_sql) && $request->download_sql != '')
        {
            $sqlResult = json_decode($request->download_sql);
            $tempSqlResult = [];
            foreach ($sqlResult as $resultValue) {
                $tempSqlResult[] = (array) $resultValue;
            }
            $name = 'SqlResult_from_B2B_'.date('Y-d-m h:m:s');
            Excel::create($name, function($excel) use($tempSqlResult) {
                        $excel->sheet('ExportFile', function($sheet) use($tempSqlResult) {
                            $sheet->fromArray($tempSqlResult);
                        });
                })->export('xls');
        }
        else
        {
            Session::flash('message', 'Result Cannot Be Download'); 
            Session::flash('alert-class', 'alert-warning');
            return Redirect::to('getSqlQueryResult');
        }
    }
}
