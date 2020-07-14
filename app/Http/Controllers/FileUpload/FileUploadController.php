<?php

namespace App\Http\Controllers\FileUpload;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Storage;

class FileUploadController extends Controller
{
    public function index(Request $request){

    	$inputData = $request->all();

    	$flag = isset($inputData['flag']) ? $inputData['flag'] : '';    	
    	$siteData = $request->siteDefaultData;
		$inputData['account_id'] = isset($inputData['account_id']) ? $inputData['account_id'] : $siteData['account_id'];
		$inputData['portal_id'] = isset($inputData['portal_id']) ? $inputData['portal_id'] : $siteData['portal_id'];
		$responseData = array();

		$responseData['status'] 		= 'success';
		$responseData['status_code'] 	= config('common.common_status_code.success');
		$responseData['message'] 		= 'File Upload Successfully';
		$responseData['shortText'] 		= 'file_upload_success_msg';

		$uploadFile 				= array();

		$uploadFile['newFileName'] 	= '';
		$uploadFile['orgFileName'] 	= '';
		$uploadFile['location'] 	= '';

		switch ($flag) {
			case 'PromoCode':
				$uploadFile = self::storePromoCodeImage($inputData);
				break;			
			default:
				break;
		}

       	$uploadFileData = array();

       	if(!isset($uploadFile['newFileName']) || $uploadFile['newFileName'] == ''){

       		$responseData['status'] 		= 'failed';
			$responseData['status_code'] 	= config('common.common_status_code.failed');
			$responseData['message'] 		= 'File Upload Error';
			$responseData['shortText'] 		= 'file_upload_error_msg';
       		$responseData['errors'] 		= ['error' => ['File Upload Error']];
       	}
       	else{
       		$uploadFileData['new_file_name'] 		= $uploadFile['newFileName'];
	       	$uploadFileData['original_file_name'] 	= $uploadFile['orgFileName'];
	       	$uploadFileData['location'] 			= $uploadFile['location'];
	       	$responseData['data'] 					= $uploadFileData;
       	}

		return response()->json($responseData);
	}

	public static function storePromoCodeImage($inputData){

		$returnData = array();
		$storageLocation    =   config('common.promo_code_storage_location');
		$fileName 	= '';
		$orgName 	= '';
		if(file($inputData['promo_code_img'])){

			$fileInput             		=   $inputData['promo_code_img'];
			$fileName                  	=   $inputData['account_id'].'_'.time().'_promo_code.'.$fileInput->extension();
			$orgName          			=   $fileInput->getClientOriginalName();			
			$storagePath = config('common.promo_code_save_path');
			self::fileUpload($fileInput,$storagePath,$storageLocation,$fileName);
		}
		$returnData['newFileName'] 	= $fileName;
		$returnData['orgFileName'] 	= $orgName;
		$returnData['location'] 	= $storageLocation;

		return $returnData;

	}

	public static function fileUpload($fileInput,$storagePath,$storageLocation,$fileName)
	{
		if($storageLocation == 'local'){

			if(!File::exists($storagePath)) {
				File::makeDirectory($storagePath, 0777, true, true);            
			}

			$disk	= Storage::disk($storageLocation)->put($storagePath.$fileName, file_get_contents($fileInput),'public');
		}
		else{

			// Do needed

		}
	}

}
