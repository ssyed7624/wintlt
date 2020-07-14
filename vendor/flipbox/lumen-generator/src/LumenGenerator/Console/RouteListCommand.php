<?php

namespace Flipbox\LumenGenerator\Console;

use Dingo\Api\Routing\Router;
use Illuminate\Console\Command;

use App\Models\UserACL\Permissions;

class RouteListCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'route:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display all registered routes.';

    /**
     * Get the router.
     *
     * @return \Laravel\Lumen\Routing\Router
     */
    protected function getRouter()
    {
        return isset($this->laravel->router) ? $this->laravel->router : $this->laravel;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $router = $this->getRouter();
        $routeCollection = $router->getRoutes();
        $rows = array();

        foreach ($routeCollection as $route) {

            $routeAction = $this->getActionNew($route['action']);

            $check = Permissions::where('permission_route',$routeAction)->first();

            if(empty($check)){
                $rows[] = $routeAction;
            }
        }

        $checkCommon = ["LoginController","UsersController","CustomersController","AgencyRegisterController","UserRegisterController","ForgotPasswordController","AgencyManageController","CommonController","CustomersRegisterController","ERunActionsController","HomeController","AccountApiController","RewardPointTransactionController","UserTravellerController","FooterIconController","CustomerBookingManagementController","PortalConfigController","SearchFormController","RoutePageController","FooterLinkController","BenefitContentController","BlogContentController","PopularDestinationController","PopularRoutesController","CustomerFeedbackController","UserController","MenuDetailsController","PermissionsController","PaymentGatewayController"];

        $checkFlCommon = ["HotelsController","HotelBookingController","FlightsController","FlightBookingsController","InsuranceController","InsuranceBookingController","PackagesController"];

        $sql = "INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES ";

        if ($this->laravel->bound(Router::class)) {
            $routes = $this->laravel->make(Router::class)->getRoutes();

            foreach ($routes as $route) {
                foreach ($route->getRoutes() as $innerRoute) {

                    if(!$innerRoute->getControllerInstance())continue;

                    // $rows[] = [
                    //     'verb' => implode('|', $innerRoute->getMethods()),
                    //     'path' => $innerRoute->getPath(),
                    //     'namedRoute' => $innerRoute->getName(),
                    //     'controller' => get_class($innerRoute->getControllerInstance()),
                    //     'action' => $this->getAction($innerRoute->getAction()),
                    //     'middleware' => implode('|', $innerRoute->getMiddleware()),
                    // ];

                    $url = $innerRoute->getPath();

                    $urlExplod = explode('/{', $url);



                    $route = substr($urlExplod[0],4);


                    $routeAction = $this->getActionNew($innerRoute->getAction());

                    $check = Permissions::where('permission_route',$routeAction)->first();

                    if(empty($check)){

                        $controllerAction = explode('@', $routeAction);

                        $group = str_replace('Controller', ' ', $controllerAction[0]);

                        if(in_array($controllerAction[0], $checkCommon)){

                            $sql .= "(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), '".$group."', 'Common Route', '".$routeAction."', '".$route."', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),";
                        }
                        else if(in_array($controllerAction[0], $checkFlCommon)){
                             $sql .= "(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Flight'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), '".$group."', '".$route."', '".$routeAction."', '".$route."', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),";
                        }

                        $rows[] = $routeAction;
                    }
                }
            }
        }

        // echo $sql;exit;

        echo json_encode($rows);exit();

        $headers = array('Verb', 'Path', 'NamedRoute', 'Controller', 'Action', 'Middleware');
        $this->table($headers, $rows);
    }

    public function handleOld()
    {
        $router = $this->getRouter();
        $routeCollection = $router->getRoutes();
        $rows = array();

        foreach ($routeCollection as $route) {
            $rows[] = [
                'verb' => $route['method'],
                'path' => $route['uri'],
                'namedRoute' => $this->getNamedRoute($route['action']),
                'controller' => $this->getController($route['action']),
                'action' => $this->getAction($route['action']),
                'middleware' => $this->getMiddleware($route['action']),
            ];
        }

        if ($this->laravel->bound(Router::class)) {
            $routes = $this->laravel->make(Router::class)->getRoutes();

            foreach ($routes as $route) {
                foreach ($route->getRoutes() as $innerRoute) {

                    if(!$innerRoute->getControllerInstance())continue;

                    $rows[] = [
                        'verb' => implode('|', $innerRoute->getMethods()),
                        'path' => $innerRoute->getPath(),
                        'namedRoute' => $innerRoute->getName(),
                        'controller' => get_class($innerRoute->getControllerInstance()),
                        'action' => $this->getAction($innerRoute->getAction()),
                        'middleware' => implode('|', $innerRoute->getMiddleware()),
                    ];
                }
            }
        }

        $headers = array('Verb', 'Path', 'NamedRoute', 'Controller', 'Action', 'Middleware');
        $this->table($headers, $rows);
    }

    /**
     * @param array $action
     * @return string
     */
    protected function getNamedRoute(array $action)
    {
        return (!isset($action['as'])) ? "" : $action['as'];
    }

    /**
     * @param array $action
     * @return mixed|string
     */
    protected function getController(array $action)
    {
        if (empty($action['uses'])) {
            return 'None';
        }

        return current(explode("@", $action['uses']));
    }

    /**
     * @param array $action
     * @return string
     */
    protected function getAction(array $action)
    {
        if (!empty($action['uses']) && is_string($action['uses'])) {
            $data = $action['uses'];
            if (($pos = strpos($data, "@")) !== false) {
                return substr($data, $pos + 1);
            } else {
                return "METHOD NOT FOUND";
            }
        } else {
            return 'Closure';
        }
    }

    protected function getActionNew(array $action)
    {
        if (!empty($action['uses']) && is_string($action['uses'])) {
            $data = $action['uses'];
            if (($pos = strpos($data, "@")) !== false) {
                $explodeData = explode('\\',$data);
                return $explodeData[count($explodeData)-1];
            } else {
                return "METHOD NOT FOUND";
            }
        } else {
            return 'Closure';
        }
    }

    /**
     * @param array $action
     * @return string
     */
    protected function getMiddleware(array $action)
    {
        return (isset($action['middleware'])) ? (is_array($action['middleware'])) ? join(", ", $action['middleware']) : $action['middleware'] : '';
    }
}
