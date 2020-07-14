<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Auth;
use App\Libraries\Common;
use Illuminate\Support\Facades\File;
use Storage;
use PDF;
use DB;
//use App\Models\Bookings\StatusDetails;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function test(Request $request)
    {


        echo '--<pre>';
        print_r(url('/'));
        die('--');

        $tempArray = [];
        //File Upload in db
        $imageOriginalImage = '';
        $imageName = '';
        $imageSavedLocation = '';
        $accountID = 2;
        $inputArray = $request->all();
        if(isset($inputArray['image']) && file($inputArray['image'])){
            $imageSavedLocation = 'local';
            $image          = $inputArray['image'];
            $imageName           = $accountID.'_'.time().'_image.'.$image->extension();
            $imageOriginalImage  = $image->getClientOriginalName();
            /*$postImageArray  = array('_token' => csrf_token(), 'fileGet' => $image, 'changeFileName' => $image_name);
            $url            = url('/').'/uploadPopularDestinationImageToGoogleCloud';
            ERunActions::touchUrl($url, $postData = $postImageArray, $contentType = "application/json");*/
            $logFilesStorageLocation = 'local';
            if($logFilesStorageLocation == 'local'){
                $storagePath = public_path().'/uploadFiles/testingFolder/';
                if(!File::exists($storagePath)) {
                    File::makeDirectory($storagePath, $mode = 0777, true, true);            
                }
            }       
            $changeFileName = $imageName;
            $fileGet        = $image;
            $disk           = $image->move($storagePath, $changeFileName);
            // $disk           = Storage::disk($logFilesStorageLocation)->put('uploadFiles/testingFolder/'.$changeFileName, file_get_contents($fileGet),'public');
            $tempArray =    [  
                                'image'=>$imageName,
                                'image_original_name'=>$imageOriginalImage,
                                'image_saved_location'=>$imageSavedLocation
                            ];

        }
        else
        {
            dd(1);
        }
        $response =[];
        $response['image'] = asset('/uploadFiles/testingFolder/'.$imageName);
        $response['saved_details'] = $tempArray;

        return response()->json($response);

    }

}
