
DROP Table menu_details;
DROP Table submenu_details;
TRUNCATE Table menu_mapping_details;

--
-- Table structure for table `menu_details`
--

CREATE TABLE `menu_details` (
  `menu_id` mediumint(9) NOT NULL,
  `menu_name` varchar(100) NOT NULL,
  `link` varchar(100) DEFAULT NULL,
  `new_link` varchar(100) DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `new_icon` varchar(50) DEFAULT NULL,
  `menu_type` enum('A','E','S','P') NOT NULL,
  `status` varchar(1) NOT NULL DEFAULT 'Y'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `menu_details`
--

INSERT INTO `menu_details` (`menu_id`, `menu_name`, `link`, `new_link`, `icon`, `new_icon`, `menu_type`, `status`) VALUES
(1, 'Dashboard', 'home', 'home', 'dashboard', 'dashboard', 'A', 'N'),
(2, 'Settings', 'goToEmsLink', 'goToEmsLink', 'settings', 'settings1', 'A', 'Y'),
(3, 'Manage Users', 'manageUsers', 'manageUsers', 'group', 'group', 'A', 'N'),
(4, 'Manage Agents', 'manageAgents', 'manageAgent', 'person_pin', 'person-plus', 'A', 'Y'),
(5, 'Manage Agency', 'manageAccounts', 'manageAccounts', 'business', 'manageagency', 'A', 'Y'),
(6, 'Portal Details', 'portalDetails', 'portalDetails', 'phonelink', 'portal1', 'A', 'Y'),
(7, 'Flight', 'flights/search', 'dashboard', 'flight', 'flight', 'A', 'Y'),
(8, 'Invoice Details', 'invoiceDetails', 'invoiceDetails', 'group', 'invoice-statement', 'A', 'Y'),
(9, 'Bookings', 'bookings', 'bookings', 'list', 'flight-included', 'A', 'Y'),
(10, 'Flight Share Url', 'flightShareUrl', 'flightShareUrl', 'flight_takeoff', 'flight_takeoff', 'A', 'Y'),
(11, 'Portal Settings', '', '', 'web', 'portal1', 'A', 'Y'),
(12, 'Suppliers Settings', '', '', 'device_hub', 'supplier-setting', 'S', 'Y'),
(13, 'Airline Manage', 'airlineManage', 'airlineManage', 'flight_takeoff', 'flight3', 'S', 'N'),
(14, 'Airport Manage', 'airportManage', 'airportManage', 'flight', 'flight', 'S', 'N'),
(15, 'Login History', 'showLoginHistory', 'showLoginHistory', 'accessibility', 'login', 'S', 'Y'),
(16, 'City Management', 'cityManagement', 'cityManagement', 'location_city', 'location_city', 'A', 'N'),
(17, 'Ticketing Module', 'view_module', 'view_module', 'list', 'add-file', 'A', 'Y'),
(18, 'Flight Search Logs', 'flightSearchReqIndex', 'flightSearchReqIndex', 'flight', 'flight', 'A', 'N'),
(19, 'CMS Menu', NULL, NULL, 'next_week', 'next_week', 'A', 'Y'),
(20, 'Customer Management', 'manageCustomers', 'manageCustomers', 'people', 'filter_head', 'A', 'Y'),
(21, 'CMS Settings', NULL, NULL, 'web', 'portal1', 'A', 'Y'),
(22, 'User Travellers', 'userTravellers', 'userTraveller', 'rowing', 'rowing', 'A', 'Y'),
(23, 'Route Pages', 'routePages', 'routePages', 'flight', 'flight', 'A', 'Y'),
(24, 'Events Menu', NULL, 'event', 'event', 'event', 'A', 'Y'),
(25, 'Meta Logs', 'metaLogs', 'metaLogs', 'swap_vert', 'log', 'A', 'Y'),
(27, 'Show Login History', 'showLoginHistory', 'showLoginHistory', 'accessibility', NULL, 'S', 'Y'),
(28, 'User Referral', 'userReferral', 'userReferral', 'insert_link', NULL, 'A', 'Y'),
(29, 'Content Source Management', NULL, NULL, 'business', 'manageagency', 'A', 'Y'),
(30, 'B2C Portal Settings', NULL, NULL, 'list', 'add-file', 'A', 'Y'),
(31, 'Points Management', NULL, NULL, 'web', 'portal1', 'A', 'Y');

-- --------------------------------------------------------

--
-- Table structure for table `submenu_details`
--

CREATE TABLE `submenu_details` (
  `submenu_id` mediumint(9) NOT NULL,
  `submenu_name` varchar(100) DEFAULT NULL,
  `link` varchar(100) DEFAULT NULL,
  `new_link` varchar(100) DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `new_icon` varchar(50) DEFAULT NULL,
  `sub_menu_type` enum('A','E','S','P') NOT NULL DEFAULT 'A',
  `status` varchar(1) NOT NULL DEFAULT 'Y'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `submenu_details`
--

INSERT INTO `submenu_details` (`submenu_id`, `submenu_name`, `link`, `new_link`, `icon`, `new_icon`, `sub_menu_type`, `status`) VALUES
(1, 'No Submenu', '', '', '', '', 'A', 'Y'),
(2, 'Airline Settings', 'airlineManage', 'airlineManage', 'flight', 'flight', 'A', 'N'),
(3, 'Airport Settings', 'airportManage', 'airportManage', 'flight_takeoff', 'flight_takeoff', 'A', 'N'),
(4, 'Flight', 'bookingindex', 'flightBookingList', 'flight', 'flight', 'A', 'Y'),
(5, 'Airline Groups', 'airlineGroup', 'airlineGroup', 'flight', 'flight', 'A', 'Y'),
(6, 'Airport Groups', 'airportGroup', 'airportGroups', 'flight_takeoff', 'flight_takeoff', 'A', 'Y'),
(7, 'Aggregation Profile', 'profileAggregation', 'profileAggregation', 'assignment', 'assignment', 'A', 'Y'),
(8, 'Airline Blocking', 'portalAirlineBlockingTemplates', 'portalAirlineBlockingTemplates', 'flight', 'flight', 'A', 'N'),
(9, 'Route Blocking', 'portalRouteBlockingTemplates', 'portalRouteBlockingTemplates', 'flight_takeoff', 'flight_takeoff', 'A', 'N'),
(10, 'Airline Masking', 'portalAirlineMaskingTemplates', 'portalAirlineMaskingTemplates', 'airline_seat_recline_extra', 'airline_seat_recline_extra', 'A', 'N'),
(11, 'Markup Templates', 'supplierMarkupTemplate', 'supplierMarkupTemplate', 'title', 'title', 'A', 'Y'),
(12, 'Surcharge', 'portalSurcharge', 'portalSurcharge', 'attach_money', 'attach_money', 'A', 'N'),
(13, 'Content Source', 'contentSource', 'contentSource', 'timeline', 'timeline', 'S', 'Y'),
(14, 'Surcharge', 'supplierSurcharge', 'supplierSurcharge', 'attach_money', 'attach_money', 'S', 'Y'),
(15, 'Contract Groups', 'supplierPosContract', 'contractList', 'style', 'style', 'S', 'Y'),
(16, 'Supplier Markup Templates', 'supplierMarkupTemplate', 'supplierMarkupTemplate', 'device_hub', 'device_hub', 'S', 'N'),
(17, 'Default Markup', 'supplierDefaultMarkup', 'supplierDefaultMarkup', 'title', 'title', 'S', 'N'),
(18, 'Distribution', 'supplierPosTemplate', 'supplierPosTemplate', 'transfer_within_a_station', 'transfer_within_a_station', 'S', 'N'),
(19, 'Airline Blocking', 'supplierAirlineBlockingTemplates', 'supplierAirlineBlockingTemplates', 'flight', 'flight', 'S', 'N'),
(20, 'Route Blocking', 'supplierRouteBlockingTemplates', 'supplierRouteBlockingTemplates', 'flight_takeoff', 'flight_takeoff', 'S', 'N'),
(21, 'Airline Masking', 'supplierAirlineMaskingTemplates', 'supplierAirlineMaskingTemplates', 'airline_seat_recline_extra', 'airline_seat_recline_extra', 'S', 'N'),
(22, 'Mapped Partners', 'partnersList', 'partnersList', 'web', 'web', 'S', 'Y'),
(23, 'Content Source List', 'contentSourceList', 'contentSourceList', 'timeline', 'timeline', 'A', 'N'),
(24, 'Airline Manage', 'airlineManage', 'airlineManage', 'flight', 'flight', 'A', 'N'),
(25, 'Airport Manage', 'airportManage', 'airportManage', 'flight_takeoff', 'flight_takeoff', 'A', 'N'),
(26, 'Currency Exchange Rate', 'currencyExchangeRate', 'currencyExchangeRate', 'flight_takeoff', 'flight_takeoff', 'S', 'Y'),
(27, 'Form Of Payment', 'formOfPayment', 'formOfPayment', 'timeline', 'timeline', 'A', 'Y'),
(28, 'Insurance', 'insuranceindex', 'insuranceindex', 'supervisor_account', 'supervisor_account', 'A', 'Y'),
(29, 'Sector Mapping', 'sectorMapping', 'sectorMapping', 'group_work', 'group_work', 'A', 'Y'),
(30, 'Payment Gateway Config', 'gateWayConfig', 'paymentGatewayConfig', 'payment', 'payment', 'A', 'Y'),
(31, 'Extra Payment', 'extraPayment', 'extraPayment', 'card_giftcard', 'card_giftcard', 'A', 'Y'),
(32, 'PG Transaction', 'pgTransactionIndex', 'pgTransactionList', 'input', 'input', 'A', 'Y'),
(34, 'Hotel', 'hotelBookingIndex', 'hotelBookingIndex', 'hotel', 'hotel', 'A', 'N'),
(35, 'Hotel Bookings Index', 'hotelBookingsIndex', 'hotelBookingsIndex', 'list_alt', 'list_alt', 'A', 'Y'),
(36, 'Look to Book Ratio', 'bookingRatio', 'lookToBookRatio', 'block', 'block', 'A', 'Y'),
(37, 'City Management', 'cityManagement', 'cityManagement', 'location_city', 'location_city', 'A', 'N'),
(38, 'Ticketing Rules', 'ticketingRules', 'ticketingRules', 'receipt', 'receipt', 'A', 'Y'),
(39, 'Remarks Templates', 'remarkTemplate', 'remarkTemplate', 'receipt', 'receipt', 'A', 'N'),
(40, 'Ticketing Queue', 'ticketingQueueIndex', 'ticketingQueueList', 'playlist_add', 'playlist_add', 'A', 'Y'),
(41, 'Supplier LowFare Template', 'supplierLowfareTemplate', 'supplierLowfareTemplate', 'title', 'title', 'A', 'Y'),
(42, 'Risk Analysis Template', 'riskAnalysisManagement', 'riskAnalysisManagement', 'low_priority', 'low_priority', 'A', 'Y'),
(43, 'Quality Check Template', 'qualityCheckTemplate', 'qualityCheckTemplate', 'check_circle_outline', 'check_circle_outline', 'A', 'Y'),
(44, 'Hotel Beds City Management', 'hotelBedsCityManagement', 'cityManagement', 'location_city', 'location_city', 'A', 'Y'),
(45, 'Agency Fee Management', 'agencyFeeManagement', 'agencyFeeManagement', 'account_balance_wallet', 'account_balance_wallet', 'A', 'Y'),
(46, 'Schedule Management Queue', 'scheduleManagementQueue', 'scheduleManagementQueue', 'queue', 'queue', 'A', 'Y'),
(47, 'Country Details', 'countryDetails', 'countryDetails', 'language', 'language', 'A', 'Y'),
(48, 'User Group Details', 'userGroupDetails', 'userGroupDetails', 'group', 'group', 'A', 'Y'),
(49, 'Import PNR', 'getPnrFrom', 'getPnrFrom', 'queue', 'queue', 'A', 'Y'),
(50, 'CMS Settings', 'home', 'home', 'web', 'web', 'A', 'Y'),
(51, 'Subscription', 'subscription', 'subscription', 'subscriptions', 'subscriptions', 'A', 'Y'),
(52, 'Portal Promotion', 'portalPromotions', 'portalPromotions', 'local_offer', 'local_offer', 'A', 'Y'),
(53, 'Settings', 'goToEmsLink', 'goToEmsLink', 'settings', 'settings', 'A', 'Y'),
(54, 'Portal Config', 'portalConfig', 'portalConfig', 'devices_other', 'devices_other', 'A', 'Y'),
(55, 'Customer Feedback', 'customerFeedbacks', 'customerFeedback', 'question_answer', 'question_answer', 'A', 'Y'),
(56, 'Popular Routes', 'popularRoutes', 'popularRoutes', 'trending_flat', 'trending_flat', 'A', 'Y'),
(57, 'Popular Destination', 'popularDestinations', 'popularDestinations', 'flight_land', 'flight_land', 'A', 'Y'),
(58, 'Footer links and pages', 'footerLinks', 'footerLinks', 'crop_7_5', 'crop_7_5', 'A', 'Y'),
(59, 'Footer Icons', 'footerIcons', 'footerIcons', 'grain', 'grain', 'A', 'Y'),
(60, 'Promo Codes', 'promoCode', 'promoCode', 'refresh', 'refresh', 'A', 'Y'),
(61, 'Add Benefits', 'addBenefit', 'addBenefit', 'playlist_add', 'playlist_add', 'A', 'Y'),
(62, 'Route Pages', 'routePages', 'routePages', 'flight', 'flight', 'A', 'Y'),
(63, 'Route Config Log', 'routeConfig', 'routeConfig', 'swap_calls', 'swap_calls', 'A', 'Y'),
(64, 'Route Config Management', 'routeConfigManage', 'routeConfigManage', 'create_new_folder', 'create_new_folder', 'A', 'Y'),
(65, 'Route Url Generator', 'routeUrlGenerator', 'routeUrlGenerator', 'trending_flat', 'trending_flat', 'A', 'Y'),
(66, 'Route Page Settings', 'routePageSettings', 'routePageSettings', 'trending_flat', 'trending_flat', 'A', 'Y'),
(67, 'Meta Logs', 'metaLogs', 'metaLogs', 'swap_vert', 'swap_vert', 'A', 'Y'),
(68, 'Events Menu', 'event', 'event', 'event', 'event', 'A', 'Y'),
(69, 'Events', 'event', 'event', 'event', 'event', 'A', 'Y'),
(70, 'Event Subscription', 'eventSubscription', 'eventSubscription', 'touch_app', 'touch_app', 'A', 'Y'),
(71, 'Email Template', 'emailTemplate', 'emailTemplate', 'email', 'email', 'A', 'Y'),
(72, 'Airport Info', 'airportInfo', 'airportInfo', 'flight', 'flight', 'A', 'Y'),
(73, 'Airline Info', 'airlineInfo', 'airlineInfo', 'flight_takeoff', 'flight_takeoff', 'A', 'Y'),
(74, 'Popular Cities', 'popularCities', 'popularCities', 'location_city', 'location_city', 'A', 'Y'),
(75, 'Banner Section', 'bannerSection', 'bannerSection', 'picture_in_picture', 'picture_in_picture', 'A', 'Y'),
(76, 'Customer Management', 'manageCustomers', 'manageCustomers', 'people', 'people', 'A', 'Y'),
(77, 'Contact Us', 'contactUsForm', 'contactUsForm', 'feedback', 'feedback', 'A', 'Y'),
(78, 'User Referral', 'userReferral', 'userReferral', 'insert_link', 'insert_link', 'A', 'Y'),
(79, 'User Travellers', 'userTravellers', 'userTraveller', 'rowing', 'rowing', 'A', 'Y'),
(80, 'Component Detail', 'componentDetails', 'componentDetails', 'settings_input_component', 'settings_input_component', 'A', 'Y'),
(81, 'Page Detail', 'pageDetails', 'pageDetails', 'pages', 'pages', 'A', 'Y'),
(82, 'Portal Page Detail', 'portalPageDetail', 'portalPageDetail', 'tune', 'tune', 'A', 'Y'),
(83, 'Blog Content', 'blogContent', 'blogContent', 'create', 'create', 'A', 'Y'),
(84, 'User Roles', 'userRoles', 'dynamicRoleAllocation', 'create', 'create', 'A', 'Y'),
(85, 'Reward Points', NULL, 'rewardPoints', 'attach_money', NULL, 'A', 'Y'),
(86, 'Reward Transaction List', NULL, 'rewardTransactionList', 'list', NULL, 'A', 'Y'),
(87, 'Flight Share Url', 'flightShareUrl', 'flightShareUrl', 'flight_takeoff', 'flight_takeoff', 'A', 'Y'),
(88, 'Agency Management', 'manageAccounts', 'manageAccounts', 'business', 'manageagency', 'A', 'Y'),
(89, 'Manage Agents', 'manageAgents', 'manageAgents', 'person_pin', 'person-plus', 'A', 'Y'),
(90, 'Invoice Details', 'invoiceDetails', 'invoiceDetails', 'group', 'invoice-statement', 'A', 'Y'),
(91, 'Portal Details', 'portalDetails', 'portalDetails', 'phonelink', 'portal1', 'A', 'Y');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `menu_details`
--
ALTER TABLE `menu_details`
  ADD PRIMARY KEY (`menu_id`),
  ADD UNIQUE KEY `menu_name_UNIQUE` (`menu_name`,`link`);

--
-- Indexes for table `submenu_details`
--
ALTER TABLE `submenu_details`
  ADD PRIMARY KEY (`submenu_id`),
  ADD UNIQUE KEY `submneu_name_UNIQUE` (`submenu_name`,`link`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `menu_details`
--
ALTER TABLE `menu_details`
  MODIFY `menu_id` mediumint(9) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;
--
-- AUTO_INCREMENT for table `submenu_details`
--
ALTER TABLE `submenu_details`
  MODIFY `submenu_id` mediumint(9) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;


UPDATE `submenu_details` SET `status` = 'Y' WHERE `submenu_name` = 'Airport Manage';
UPDATE `submenu_details` SET `status` = 'Y' WHERE `submenu_name` = 'Airline Manage';


-- Menu Mapping For Supper Admin

-- Flight Menu

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Flight'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'),0,1,0,1,'Y','Y');

-- Booking List Menu

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'),0,2,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Insurance'),0,2,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel Bookings Index'),0,2,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Import PNR'),0,2,0,4,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Extra Payment'),0,2,0,5,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight Share Url'),0,2,0,6,'Y','Y');

-- Agency Management

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Management'),0,3,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Manage Agents'),0,3,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Invoice Details'),0,3,0,3,'Y','Y');

-- Content Source Management

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Content Source Management'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'),0,4,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Content Source Management'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'),0,4,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Content Source Management'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Aggregation Profile'),0,4,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Content Source Management'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contract Groups'),0,4,0,4,'Y','Y');

-- Suppliers Settings

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'supplierSurcharge'),0,5,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'supplierAirlineBlockingTemplates'),0,5,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'supplierRouteBlockingTemplates'),0,5,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'supplierAirlineMaskingTemplates'),0,5,0,4,'Y','Y');

-- Settings

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Roles'),0,6,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Group Details'),0,6,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Payment Gateway Config'),0,6,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Currency Exchange Rate'),0,6,0,4,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Form Of Payment'),0,6,0,5,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Sector Mapping'),0,6,0,6,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'PG Transaction'),0,6,0,7,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Look to Book Ratio'),0,6,0,8,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'),0,6,0,9,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Manage'),0,6,0,10,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Manage'),0,6,0,11,'Y','Y');

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Component Detail'),0,6,0,10,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Page Detail'),0,6,0,11,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Page Detail'),0,6,0,12,'Y','Y');

-- Portal Settings

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Details'),0,7,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Groups'),0,7,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Groups'),0,7,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'portalAirlineBlockingTemplates'),0,7,0,4,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'portalRouteBlockingTemplates'),0,7,0,5,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'portalAirlineMaskingTemplates'),0,7,0,6,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel Beds City Management'),0,7,0,7,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Country Details'),0,7,0,8,'Y','Y');



-- Ticketing Module

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Queue'),0,8,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Rules'),0,8,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier LowFare Template'),0,8,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Risk Analysis Template'),0,8,0,4,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Quality Check Template'),0,8,0,5,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Schedule Management Queue'),0,8,0,6,'Y','Y');


-- B2C Portal Settings

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='B2C Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Promo Codes'),0,9,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='B2C Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Settings'),0,9,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='B2C Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config management'),0,9,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='B2C Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'),0,9,0,4,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='B2C Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'City Management'),0,9,0,5,'Y','Y');


-- CMS settings

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Subscription'),0,10,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Promotion'),0,10,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Customer Feedback'),0,10,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Routes'),0,10,0,4,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Destination'),0,10,0,5,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer Icons'),0,10,0,6,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer links and pages'),0,10,0,7,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Blog Content'),0,10,0,8,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Add Benefits'),0,10,0,9,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contact Us'),0,10,0,10,'Y','Y');


-- Route Pages

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Page Settings'),0,11,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Banner Section'),0,11,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Info'),0,11,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Info'),0,11,0,4,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Cities'),0,11,0,5,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Url Generator'),0,11,0,6,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer links and pages'),0,11,0,7,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Blog Content'),0,11,0,8,'Y','Y');


-- Points Management Need to check

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Points Management'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Reward Points'),0,12,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Points Management'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Reward Transaction List'),0,12,0,2,'Y','Y');


-- Manage Customer

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Customer Management'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'),0,13,0,1,'Y','Y');

-- Events

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Events Menu'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Events'),0,14,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Events Menu'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Event Subscription'),0,14,0,2,'Y','Y');

-- User Traveller

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='User Travellers'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'),0,15,0,1,'Y','Y');

-- User Referral

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='User Referral'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'),0,16,0,1,'Y','Y');

-- Meta Logs

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Meta Logs'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'),0,17,0,1,'Y','Y');


-- Login History

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Login History'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'),0,18,0,1,'Y','Y');


-- Flight Search RQ Log

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Flight Search Logs'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'),0,19,0,1,'Y','Y');



-- Menu Mapping For Owner

-- Flight Menu

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Flight'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'),0,1,0,1,'Y','Y');

-- Booking List Menu

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'),0,2,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Insurance'),0,2,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel Bookings Index'),0,2,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Import PNR'),0,2,0,4,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Extra Payment'),0,2,0,5,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight Share Url'),0,2,0,6,'Y','Y');

-- Agency Management

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Management'),0,3,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Manage Agents'),0,3,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Invoice Details'),0,3,0,3,'Y','Y');

-- Content Source Management

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Content Source Management'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'),0,4,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Content Source Management'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'),0,4,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Content Source Management'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Aggregation Profile'),0,4,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Content Source Management'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contract Groups'),0,4,0,4,'Y','Y');

-- Suppliers Settings

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'supplierSurcharge'),0,5,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'supplierAirlineBlockingTemplates'),0,5,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'supplierRouteBlockingTemplates'),0,5,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'supplierAirlineMaskingTemplates'),0,5,0,4,'Y','Y');

-- Settings

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Roles'),0,6,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Group Details'),0,6,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Payment Gateway Config'),0,6,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Currency Exchange Rate'),0,6,0,4,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Form Of Payment'),0,6,0,5,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Sector Mapping'),0,6,0,6,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'PG Transaction'),0,6,0,7,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Look to Book Ratio'),0,6,0,8,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'),0,6,0,9,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Manage'),0,6,0,10,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Manage'),0,6,0,11,'Y','Y');

-- Portal Settings

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Details'),0,7,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Groups'),0,7,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Groups'),0,7,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'portalAirlineBlockingTemplates'),0,7,0,4,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'portalRouteBlockingTemplates'),0,7,0,5,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'portalAirlineMaskingTemplates'),0,7,0,6,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel Beds City Management'),0,7,0,7,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Country Details'),0,7,0,8,'Y','Y');



-- Ticketing Module

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Queue'),0,8,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Rules'),0,8,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier LowFare Template'),0,8,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Risk Analysis Template'),0,8,0,4,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Quality Check Template'),0,8,0,5,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Schedule Management Queue'),0,8,0,6,'Y','Y');


-- B2C Portal Settings

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='B2C Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Promo Codes'),0,9,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='B2C Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Settings'),0,9,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='B2C Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config management'),0,9,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='B2C Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'),0,9,0,4,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='B2C Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'City Management'),0,9,0,5,'Y','Y');


-- CMS settings

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Subscription'),0,10,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Promotion'),0,10,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Customer Feedback'),0,10,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Routes'),0,10,0,4,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Destination'),0,10,0,5,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer Icons'),0,10,0,6,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer links and pages'),0,10,0,7,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Blog Content'),0,10,0,8,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Add Benefits'),0,10,0,9,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contact Us'),0,10,0,10,'Y','Y');


-- Route Pages

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Page Settings'),0,11,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Banner Section'),0,11,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Info'),0,11,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Info'),0,11,0,4,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Cities'),0,11,0,5,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Url Generator'),0,11,0,6,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer links and pages'),0,11,0,7,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Blog Content'),0,11,0,8,'Y','Y');


-- Points Management Need to check

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Points Management'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Reward Points'),0,12,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Points Management'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Reward Transaction List'),0,12,0,2,'Y','Y');


-- Manage Customer

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Customer Management'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'),0,13,0,1,'Y','Y');

-- Events

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Events Menu'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Events'),0,14,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Events Menu'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Event Subscription'),0,14,0,2,'Y','Y');

-- User Traveller

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='User Travellers'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'),0,15,0,1,'Y','Y');

-- User Referral

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='User Referral'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'),0,16,0,1,'Y','Y');

-- Meta Logs

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Meta Logs'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'),0,17,0,1,'Y','Y');


-- Menu Mapping For Manager

-- Flight Menu

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Flight'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'),0,1,0,1,'Y','Y');

-- Booking List Menu

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'),0,2,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Insurance'),0,2,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel Bookings Index'),0,2,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight Share Url'),0,2,0,6,'Y','Y');

-- Agency Management

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Manage Agents'),0,3,0,2,'Y','Y');


-- Menu Mapping For Agent

-- Flight Menu

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AG'),(SELECT menu_id FROM menu_details WHERE menu_name ='Flight'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'),0,1,0,1,'Y','Y');

-- Booking List Menu

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AG'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'),0,2,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AG'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Insurance'),0,2,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AG'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel Bookings Index'),0,2,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AG'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Extra Payment'),0,2,0,5,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AG'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight Share Url'),0,2,0,6,'Y','Y');


-- Menu Mapping For Home Based Agent

-- Flight Menu

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='HA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Flight'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'),0,1,0,1,'Y','Y');

-- Booking List Menu

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='HA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'),0,2,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='HA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Insurance'),0,2,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='HA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel Bookings Index'),0,2,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='HA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Extra Payment'),0,2,0,5,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='HA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight Share Url'),0,2,0,6,'Y','Y');


-- Menu Mapping For Revenue

-- Flight Menu

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Flight'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'),0,1,0,1,'Y','Y');

-- Booking List Menu

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'),0,2,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Insurance'),0,2,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel Bookings Index'),0,2,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Extra Payment'),0,2,0,5,'Y','Y');


-- Agency Management

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Invoice Details'),0,3,0,3,'Y','Y');


-- Suppliers Settings

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'supplierSurcharge'),0,5,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'supplierAirlineBlockingTemplates'),0,5,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'supplierRouteBlockingTemplates'),0,5,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'supplierAirlineMaskingTemplates'),0,5,0,4,'Y','Y');

-- Settings

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Roles'),0,6,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Group Details'),0,6,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Payment Gateway Config'),0,6,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Currency Exchange Rate'),0,6,0,4,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Form Of Payment'),0,6,0,5,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Sector Mapping'),0,6,0,6,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'PG Transaction'),0,6,0,7,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Look to Book Ratio'),0,6,0,8,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'),0,6,0,9,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Manage'),0,6,0,10,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Manage'),0,6,0,11,'Y','Y');

-- Portal Settings

INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Details'),0,7,0,1,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Groups'),0,7,0,2,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Groups'),0,7,0,3,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'portalAirlineBlockingTemplates'),0,7,0,4,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'portalRouteBlockingTemplates'),0,7,0,5,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE link = 'portalAirlineMaskingTemplates'),0,7,0,6,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel Beds City Management'),0,7,0,7,'Y','Y'),
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='RE'),(SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Country Details'),0,7,0,8,'Y','Y');

-- Seat Mapping

INSERT INTO `submenu_details` (`submenu_id`, `submenu_name`, `link`, `new_link`, `icon`, `new_icon`, `sub_menu_type`, `status`) VALUES
(NULL, 'Seat Mapping', 'seatMapping', 'seatMapping', 'settings1', 'settings1', 'A', 'Y');


INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Seat Mapping'),0,5,0,5,'Y','Y');


INSERT INTO `submenu_details` (`submenu_id`, `submenu_name`, `link`, `new_link`, `icon`, `new_icon`, `sub_menu_type`, `status`) VALUES
(NULL, 'Packages', 'packages', 'packages', 'list_alt', 'list_alt', 'A', 'Y');


INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Packages'),0,2,0,7,'Y','Y');