<?php

namespace App\Http\Controllers\InitDetails;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PortalPageComponent\PortalPageComponents;
use Storage;

class InitDetailsController extends Controller
{
    public function index(Request $request){

    	$inputData = $request->all();

    	$siteData = $request->siteDefaultData;

    	$portalId = $siteData['portal_id'];

    	$param = array();

    	$param['portalId'] 		= $siteData['portal_id'];
    	$param['pageName'] 		= isset($inputData['page']) ? $inputData['page'] : '';
    	$param['type'] 			= $siteData['business_type'];

    	$getComponents = PortalPageComponents::getPageComponents($param);

		$responseData = array();

		$responseData['status'] 		= 'success';
		$responseData['status_code'] 	= config('common.common_status_code.success');
		$responseData['message'] 		= 'Init Data Retrived Successfully';
		$responseData['shortText'] 		= 'init_success_msg';
		$responseData['data'] 			= array();


		$responseData['data'] 	= $getComponents;
		
		if(isset($inputData['page']) && $inputData['page'] == 'home'){

			$menu = array();
			
			$menu[] = array("name" => "Home", "id" => "home");
			$menu[] = array("name" => "About Us", "id" => "about-us");
			$menu[] = array("name" => "Features", "id" => "features");
			$menu[] = array("name" => "Contact Us", "id" => "contact-us");

			$responseData['data']['menus'] = $menu;
		}		

		return response()->json($responseData);
	}

	public function migrateComponent(Request $request){

    	$getComponents = PortalPageComponents::migrateComponents();

		return response()->json($getComponents);
	}

	public function downloadComponent(Request $request){

    	// $getComponents = PortalPageComponents::getAllPageComponents();
    	$getComponents = PortalPageComponents::getAllPageComponentsV1();

    	Storage::disk('local')->put('/Url-configuration/component.config.json', json_encode($getComponents));

		return response()->json($getComponents);
	}

	public function getPageMeta(Request $request){

    	$getMeta = PortalPageComponents::getPageMeta();

    	Storage::disk('local')->put('/Seo/meta.json', json_encode($getMeta));

		return response()->json($getMeta);
	}

	public function getPortalTheme(Request $request){

    	// $getTheme = PortalPageComponents::getPortalTheme();
    	$getTheme = PortalPageComponents::getPortalThemeV1();

    	Storage::disk('local')->put('/Url-configuration/theme.config.json', json_encode($getTheme));

		return response()->json($getTheme);
	}

}
