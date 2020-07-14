<?php

namespace App\Models\PortalPageComponent;

use App\Models\Model;
use App\Models\PortalDetails\PortalDetails;

class PortalPageComponents extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.portal_page_components');
    }

    public $timestamps = false;

    protected $primaryKey = 'portal_page_component_id';

    public static function getPageComponents( $param = [] ){

        $portalId   = $param['portalId'];
        $pageName   = $param['pageName'];
        $type       = $param['type'];

        $getData = PortalPageComponents::select('pd.page_name', 'cd.component_name', 'pd.page_title', 'pd.page_meta')
                    ->join(config('tables.component_details').' as cd', 'cd.component_details_id', '=', config('tables.portal_page_components').'.component_details_id')
                    ->join(config('tables.page_details').' as pd', 'pd.page_detail_id', '=', config('tables.portal_page_components').'.page_detail_id')
                    ->join(config('tables.portal_details').' as po', 'po.portal_id', '=', config('tables.portal_page_components').'.portal_id')
                    ->where(config('tables.portal_page_components').'.portal_id', $portalId)
                    ->where('pd.page_name', $pageName)
                    ->whereIn('pd.page_type', [$type, 'BOTH'])
                    ->get();

        $pageData           = array();

        $pageData['title']  = '';
        $pageData['metas']  = [];

        foreach ($getData as $key => $value) {
            if($pageData['title'] == ''){
                $pageData['title'] = $value['page_title'];
            }

            if(empty($pageData['metas'])){
                $pageData['metas'] = json_decode($value['page_meta']);
            }

            $pageData['components'][$value['component_name']] = true;
        }

        return $pageData;

    }

    public static function getAllPageComponents(){

        $portalDetails  = PortalDetails::where('status', 'A')->where('portal_url', '!=', '')->get();
        $pageDetails    = PageDetails::where('status', 'A')->get();


        $componentData = PortalPageComponents::select('po.portal_id', 'pd.page_detail_id', 'cd.component_details_id', 'pd.page_name',  'pd.page_url', 'cd.component_name', 'pd.page_title', 'pd.page_meta', 'cd.component_type')
                    ->join(config('tables.component_details').' as cd', 'cd.component_details_id', '=', config('tables.portal_page_components').'.component_details_id')
                    ->join(config('tables.page_details').' as pd', 'pd.page_detail_id', '=', config('tables.portal_page_components').'.page_detail_id')
                    ->join(config('tables.portal_details').' as po', 'po.portal_id', '=', config('tables.portal_page_components').'.portal_id')
                    ->where(config('tables.portal_page_components').'.status', 'A')
                    ->where('po.status', 'A')
                    ->where('pd.status', 'A')
                    ->where('pd.status', 'A')
                    ->get();

        
        $responseData = [];
        if($componentData != null){ 
            $responseData['default']['componentConfig'] = [
                'Banner' => true,
                'AboutUs' => true,
                'Header' => true,
                'Features' => true,
                'Ourserve' => true,
                'Ourpartner' => true,
                'HappyCustomers' => true,
                'ContactUs' => true,
                'SponcerIconContent' => true,
                'Footer' => true,
                'HeaderOne' => true,
                'BannerOne' => true,
                'ExploreSection' => true,
                'TopFlightSection' => true,
                'HomebannerSection' => true,
                'DealsOfferCont' => true,
                'NewsletterIndex' => true,
                'FooterOne' => true
            ];


            foreach ($portalDetails as $pKey => $pData) {

                $portalUrl = $pData->portal_url.'/'.config('common.sub_domain');

                foreach ($pageDetails as $pgKey => $pgData) {

                    if($pgData->page_url == '/')
                        $pgData->page_url = '';

                    if($pgData->page_type != $pData->business_type)continue;

                    if(!isset($responseData[$portalUrl.'/'.$pgData->page_url]))
                        $responseData[$portalUrl.'/'.$pgData->page_url] = [];

                    if(!isset($responseData[$portalUrl.'/'.$pgData->page_url]['componentConfig']))
                        $responseData[$portalUrl.'/'.$pgData->page_url]['componentConfig'] = [];

                    foreach ($componentData as $ckey => $cData) {

                        if($pData->portal_id != $cData->portal_id || $cData->component_type != $pData->business_type || $pgData->page_detail_id != $cData->page_detail_id)continue;

                        $responseData[$portalUrl.'/'.$pgData->page_url]['componentConfig'][$cData->component_name] = true;

                    }

                }

            }
            $responseData['status'] = 'success';
        }else{
            $responseData['status'] = 'failed';
        }

        return $responseData;

    }

    public static function migrateComponents(){

        $portalDetails  = PortalDetails::where('status', 'A')->where('portal_url', '!=', '')->get();
        $pageDetails    = PageDetails::where('status', 'A')->get();
        $componentData  = ComponentDetails::where('status', 'A')->get();

        // $param = [
     //        'Banner' => true,
     //        'AboutUs' => true,
     //        'Header' => true,
     //        'Features' => true,
     //        'Ourserve' => true,
     //        'Ourpartner' => true,
     //        'HappyCustomers' => true,
     //        'ContactUs' => true,
     //        'SponcerIconContent' => true,
     //        'Footer' => true,
     //        'HeaderOne' => true,
     //        'BannerOne' => true,
     //        'ExploreSection' => true,
     //        'TopFlightSection' => true,
     //        'HomebannerSection' => true,
     //        'DealsOfferCont' => true,
     //        'NewsletterIndex' => true,
     //        'FooterOne' => true
     //    ];

     //    foreach ($param as $key => $value) {
     //     $obj = new ComponentDetails;
     //     $obj->component_name = $key;
     //     $obj->save();
     //    }




        PortalPageComponents::query()->truncate();


        foreach ($portalDetails as $pKey => $pData) {

            foreach ($pageDetails as $pgKey => $pgData) {

                foreach ($componentData as $ckey => $cData) {

                    if( $pgData->page_type == $pData->business_type && $cData->component_type == $pData->business_type ){

                        $obj = new PortalPageComponents;

                        $obj->portal_id             = $pData->portal_id;
                        $obj->page_detail_id        = $pgData->page_detail_id;
                        $obj->component_details_id  = $cData->component_details_id;
                        $obj->save();
                    }

                }

            }

        }

        return $componentData;

    }

    public static function getAllPageComponentsV1(){

        $portalDetails  = PortalDetails::where('status', 'A')->where('portal_url', '!=', '')->get();
        $pageDetails    = PageDetails::where('status', 'A')->get();


        $componentData = PortalPageComponents::select('po.portal_id', 'pd.page_detail_id', 'cd.component_details_id', 'pd.page_name',  'pd.page_url', 'cd.component_name', 'pd.page_title', 'pd.page_meta', 'cd.component_type')
                    ->join(config('tables.component_details').' as cd', 'cd.component_details_id', '=', config('tables.portal_page_components').'.component_details_id')
                    ->join(config('tables.page_details').' as pd', 'pd.page_detail_id', '=', config('tables.portal_page_components').'.page_detail_id')
                    ->join(config('tables.portal_details').' as po', 'po.portal_id', '=', config('tables.portal_page_components').'.portal_id')
                    ->where(config('tables.portal_page_components').'.status', 'A')
                    ->where('po.status', 'A')
                    ->where('pd.status', 'A')
                    ->where('pd.status', 'A')
                    ->get();

        
        $responseData = [];
        if($componentData != null){

            $responseData['default'] = json_decode('{"title":"Home","meta":[{"description":"Home Page"},{"socialmedia":"facebook"}],"components":[{"component":"home-hero-banner","visible":true,"data":{}},{"component":"about-us","visible":true,"data":{}},{"component":"features","visible":true,"data":{}},{"component":"happy-customers","visible":false,"data":{}},{"component":"contact-us","visible":true,"data":{}}]}',true);

            $b2bCom = json_decode('[{"component":"home-hero-banner","visible":true,"data":{}},{"component":"about-us","visible":true,"data":{}},{"component":"features","visible":true,"data":{}},{"component":"happy-customers","visible":false,"data":{}},{"component":"contact-us","visible":true,"data":{}}]',true);

            $b2cCom = json_decode('[{"component":"banner-one","visible":true,"data":{}},{"component":"explore-section","visible":true,"data":{}},{"component":"top-flight-section","visible":true,"data":{}},{"component":"home-banner-section","visible":true,"data":{}},{"component":"top-flight-section-domestic","visible":true,"data":{}},{"component":"deals-offer-cont","visible":true,"data":{}},{"component":"popular-destination-grid","visible":true,"data":{}},{"component":"popular-routes-grid","visible":true,"data":{}},{"component":"blog-grid","visible":true,"data":{}},{"component":"feedback-grid","visible":true,"data":{}},{"component":"why-brand-grid","visible":true,"data":{}},{"component":"newsletter-index","visible":true,"data":{}}]',true);


            foreach ($portalDetails as $pKey => $pData) {

                $portalUrl = $pData->portal_url;

                $portalUrl = str_replace('/ndc', '', $portalUrl);

                $responseData[$portalUrl]['title']  = $pData->portal_name;
                $responseData[$portalUrl]['meta']   = array();
                $responseData[$portalUrl]['meta'][]   = array("description" => $pData->portal_name);
                $responseData[$portalUrl]['meta'][]   = array("socialmedia" => "facebook");

                if($pData->business_type == 'B2B'){
                    $responseData[$portalUrl]['components'] = $b2bCom;
                }
                else{
                    $responseData[$portalUrl]['components'] = $b2cCom;
                }

            }
            $responseData['status'] = 'success';
        }else{
            $responseData['status'] = 'failed';
        }

        return $responseData;

    }

    public static function getPageMeta(){

        $portalDetails  = PortalDetails::where('status', 'A')->where('portal_url', '!=', '')->get();
        $pageDetails    = PageDetails::where('status', 'A')->get();

        $pageData = array();


        $defaultMeta = '{"default":{"title":"default","metas":[{"property":"og:title","content":"pageTitle"},{"name":"twitter:title","content":"pageTitle"},{"name":"viewport","content":"width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"}]}}';

        $pageData = json_decode($defaultMeta, true);


        foreach ($portalDetails as $pKey => $pData) {

            $portalUrl = $pData->portal_url.'/'.config('common.sub_domain');

            foreach ($pageDetails as $pgKey => $pgData) {

                if($pgData->page_url == '/')
                    $pgData->page_url = '';

                if(!isset($pageData[$portalUrl.'/'.$pgData->page_url]))
                    $pageData[$portalUrl.'/'.$pgData->page_url] = [];

                $pageData[$portalUrl.'/'.$pgData->page_url]['title'] = $pData->portal_name;

                $pageData[$portalUrl.'/'.$pgData->page_url]['metas'] = json_decode($pgData['page_meta']);

            }

        }
        $pageData['status'] = 'success';
        return $pageData;

    }

    public static function getPageMetaV1(){

        $portalDetails  = PortalDetails::where('status', 'A')->where('portal_url', '!=', '')->get();
        $pageDetails    = PageDetails::where('status', 'A')->get();

        $pageData = array();


        $defaultMeta = '{"default":{"title":"default","metas":[{"property":"og:title","content":"pageTitle"},{"name":"twitter:title","content":"pageTitle"},{"name":"viewport","content":"width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"}]}}';

        $pageData = json_decode($defaultMeta, true);


        foreach ($portalDetails as $pKey => $pData) {

            $portalUrl = $pData->portal_url.'/'.config('common.sub_domain');

            foreach ($pageDetails as $pgKey => $pgData) {

                if($pgData->page_url == '/')
                    $pgData->page_url = '';

                if(!isset($pageData[$portalUrl.'/'.$pgData->page_url]))
                    $pageData[$portalUrl.'/'.$pgData->page_url] = [];

                $pageData[$portalUrl.'/'.$pgData->page_url]['title'] = $pData->portal_name;

                $pageData[$portalUrl.'/'.$pgData->page_url]['metas'] = json_decode($pgData['page_meta']);

            }

        }
        $pageData['status'] = 'success';
        return $pageData;

    }

    public static function getPortalTheme(){

        $portalDetails  = PortalDetails::where('status', 'A')->where('portal_url', '!=', '')->get();

        $themeData = array();

        $defaultTheme = '{"default":{"language":"en","theme":"default"}}';

        $themeData = json_decode($defaultTheme, true);

        foreach ($portalDetails as $pKey => $pData) {

            $portalUrl = $pData->portal_url;

            $portalUrl = str_replace('/ndc', '', $portalUrl);

            if(!isset($themeData[$portalUrl]))
                $themeData[$portalUrl] = [];

            $themeData[$portalUrl]['language']  = 'en';
            $themeData[$portalUrl]['theme']     = 'default';

        }

        $themeData['status'] = 'success';
        return $themeData;

    }

    public static function getPortalThemeV1(){

        $portalDetails  = PortalDetails::where('status', 'A')->where('portal_url', '!=', '')->get();

        $themeData = array();

        $defaultTheme = '{"default":{"layout":"b2blayout","theme":"default"}}';

        $themeData = json_decode($defaultTheme, true);

        foreach ($portalDetails as $pKey => $pData) {

            $portalUrl = $pData->portal_url;

            $portalUrl = str_replace('/ndc', '', $portalUrl);

            if(!isset($themeData[$portalUrl]))
                $themeData[$portalUrl] = [];

            $themeData[$portalUrl]['layout']  = 'b2blayout';
            $themeData[$portalUrl]['theme']     = 'default';

            if($pData->business_type == 'B2C'){
                $themeData[$portalUrl]['layout']  = 'b2clayout';
                $themeData[$portalUrl]['theme']     = 'tripzumi';
            }

        }

        $themeData['status'] = 'success';
        return $themeData;

    }


}
