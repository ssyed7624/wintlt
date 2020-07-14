/* 08 Feb 2020 Inital Migration */

--
-- Table structure for table `oauth_access_tokens`
--

CREATE TABLE `oauth_access_tokens` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scopes` text COLLATE utf8mb4_unicode_ci,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_auth_codes`
--

CREATE TABLE `oauth_auth_codes` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `scopes` text COLLATE utf8mb4_unicode_ci,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_clients`
--

CREATE TABLE `oauth_clients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redirect` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `personal_access_client` tinyint(1) NOT NULL,
  `password_client` tinyint(1) NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_personal_access_clients`
--

CREATE TABLE `oauth_personal_access_clients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_refresh_tokens`
--

CREATE TABLE `oauth_refresh_tokens` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_token_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `oauth_access_tokens`
--
ALTER TABLE `oauth_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `oauth_access_tokens_user_id_index` (`user_id`);

--
-- Indexes for table `oauth_auth_codes`
--
ALTER TABLE `oauth_auth_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `oauth_auth_codes_user_id_index` (`user_id`);

--
-- Indexes for table `oauth_clients`
--
ALTER TABLE `oauth_clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `oauth_clients_user_id_index` (`user_id`);

--
-- Indexes for table `oauth_personal_access_clients`
--
ALTER TABLE `oauth_personal_access_clients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `oauth_refresh_tokens`
--
ALTER TABLE `oauth_refresh_tokens`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `oauth_clients`
--
ALTER TABLE `oauth_clients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
--
-- AUTO_INCREMENT for table `oauth_personal_access_clients`
--
ALTER TABLE `oauth_personal_access_clients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

/*Karthick 17th feb 2020 password Expire*/
ALTER TABLE `user_details` ADD `password_expiry` ENUM('0','1') NULL DEFAULT '1' AFTER `email_verification`; 

/* Seenivasan 24th feb 2020 alter country code  */
ALTER TABLE `airport_groups` CHANGE `county_code` `country_code` TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL COMMENT 'All or Selected country list by comma separated';



/* Divakar 02 - March - 2020 */

--
-- Table structure for table `component_details`
--

CREATE TABLE `component_details` (
  `component_details_id` int(11) NOT NULL,
  `component_name` varchar(200) DEFAULT NULL,
  `component_type` enum('B2B','B2C','BOTH','') DEFAULT 'B2B',
  `status` enum('A','IA','D','') DEFAULT 'A'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `component_details`
--

INSERT INTO `component_details` (`component_details_id`, `component_name`, `component_type`, `status`) VALUES
(1, 'Banner', 'B2B', 'A'),
(2, 'AboutUs', 'B2B', 'A'),
(3, 'Header', 'B2B', 'A'),
(4, 'Features', 'B2B', 'A'),
(5, 'Ourserve', 'B2B', 'A'),
(6, 'Ourpartner', 'B2B', 'A'),
(7, 'HappyCustomers', 'B2B', 'A'),
(8, 'ContactUs', 'B2B', 'A'),
(9, 'SponcerIconContent', 'B2B', 'A'),
(10, 'Footer', 'B2B', 'A'),
(11, 'HeaderOne', 'B2C', 'A'),
(12, 'BannerOne', 'B2C', 'A'),
(13, 'ExploreSection', 'B2C', 'A'),
(14, 'TopFlightSection', 'B2C', 'A'),
(15, 'HomebannerSection', 'B2C', 'A'),
(16, 'DealsOfferCont', 'B2C', 'A'),
(17, 'NewsletterIndex', 'B2C', 'A'),
(18, 'FooterOne', 'B2C', 'A');

-- --------------------------------------------------------

--
-- Table structure for table `page_details`
--

CREATE TABLE `page_details` (
  `page_detail_id` int(11) NOT NULL,
  `page_name` varchar(200) DEFAULT NULL,
  `page_url` varchar(200) DEFAULT NULL,
  `page_title` mediumtext,
  `page_meta` text,
  `page_type` enum('B2B','B2C','BOTH','') DEFAULT 'B2B',
  `status` enum('A','IN','D','') DEFAULT 'A'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `page_details`
--

INSERT INTO `page_details` (`page_detail_id`, `page_name`, `page_url`, `page_title`, `page_meta`, `page_type`, `status`) VALUES
(1, '', '/', 'B2B home', '[{"property":"og:title","content":"pageTitle"},{"name":"twitter:title","content":"pageTitle"},{"name":"viewport","content":"width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"}]', 'B2B', 'A'),
(2, '', '/', 'B2C home', '[{"property":"og:title","content":"pageTitle"},{"name":"twitter:title","content":"pageTitle"},{"name":"viewport","content":"width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"}]', 'B2C', 'A'),
(3, 'home', 'home', 'B2B home', '[{"property":"og:title","content":"pageTitle"},{"name":"twitter:title","content":"pageTitle"},{"name":"viewport","content":"width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"}]', 'B2B', 'A'),
(4, 'home', 'home', 'B2C home', '[{"property":"og:title","content":"pageTitle"},{"name":"twitter:title","content":"pageTitle"},{"name":"viewport","content":"width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"}]', 'B2C', 'A');

-- --------------------------------------------------------

--
-- Table structure for table `portal_page_components`
--

CREATE TABLE `portal_page_components` (
  `portal_page_component_id` int(11) NOT NULL,
  `portal_id` int(11) NOT NULL,
  `page_detail_id` int(11) NOT NULL,
  `component_details_id` int(11) NOT NULL,
  `status` enum('A','IN','D','') DEFAULT 'A'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `component_details`
--
ALTER TABLE `component_details`
  ADD PRIMARY KEY (`component_details_id`);

--
-- Indexes for table `page_details`
--
ALTER TABLE `page_details`
  ADD PRIMARY KEY (`page_detail_id`) USING BTREE;

--
-- Indexes for table `portal_page_components`
--
ALTER TABLE `portal_page_components`
  ADD PRIMARY KEY (`portal_page_component_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `component_details`
--
ALTER TABLE `component_details`
  MODIFY `component_details_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;
--
-- AUTO_INCREMENT for table `page_details`
--
ALTER TABLE `page_details`
  MODIFY `page_detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
--
-- AUTO_INCREMENT for table `portal_page_components`
--
ALTER TABLE `portal_page_components`
  MODIFY `portal_page_component_id` int(11) NOT NULL AUTO_INCREMENT;

/* 10 -march - 2020 venkat */
ALTER TABLE currency_exchange_rate DROP COLUMN portal_id;
ALTER TABLE `currency_exchange_rate` ADD `portal_id` TINYTEXT NOT NULL COMMENT 'Portal id may comma separated value for each portal or 0 or individual' AFTER `consumer_account_id`;
ALTER TABLE `currency_exchange_rate` ADD `type` ENUM('ALL','AS','PS') NOT NULL DEFAULT 'ALL' COMMENT 'ALL - both , AS -Agency Specific, PS - Portal Specific' AFTER `portal_id`;


/*18 - March - 2020 Divakar */

ALTER TABLE `permissions` ADD `menu_id` INT NULL DEFAULT '0' AFTER `permission_id`;
ALTER TABLE `permissions` ADD `submenu_id` INT NULL DEFAULT '0' AFTER `menu_id`;
ALTER TABLE `permissions` ADD `permission_url` VARCHAR(200) NULL AFTER `permission_route`;
ALTER TABLE `permissions` CHANGE `permission_name` `permission_name` VARCHAR(200) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL;

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Groups'), 'AirlineGroupCopy', 'AirlineGroupController@edit', 'airlineGroup/copy', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Groups'), 'Airline Group Update', 'AirlineGroupController@update', 'airlineGroup/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Groups'), 'Airline Group ChangeStatus', 'AirlineGroupController@changeStatus', 'airlineGroup/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Groups'), 'AirlineGroupEdit', 'AirlineGroupController@edit', 'airlineGroup/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Groups'), 'AirlineGroupSave', 'AirlineGroupController@store', 'airlineGroup/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Groups'), 'AirlineGroupCreate', 'AirlineGroupController@create', 'airlineGroup/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Groups'), 'AirlineGroupIndex', 'AirlineGroupController@index', 'airlineGroup/list', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Menu Details', 'MenuDetailsController@getMenu', 'getMenu', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Permission Details', 'PermissionsController@getPermissions', 'getPermissions', '', 'Y', 'A', 1, '2018-05-08 00:00:00');


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Balance Details', 'AgencyCreditManagementController@getBalance', 'agencyCredit/getBalance', '', 'Y', 'A', 1, '2018-05-08 00:00:00');


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirlineGroupController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirlineGroupController%') AND menu_id != 0);

/* 18 - March - 2020 Seenivasan */
/*Customer Management*/
INSERT INTO `menu_details` (`menu_id`, `menu_name`, `link`, `icon`, `menu_type`, `status`) VALUES (NULL, 'Customer Management', 'manageCustomers', 'people', 'A', 'Y');

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'Customer Management'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'No Submenu'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'No Submenu'), '1', '3', '3', 'Y', 'Y');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Customer Management'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Customer Management Update', 'CustomerManagementController@update', 'manageCustomers/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Customer Management'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Customer Management ChangeStatus', 'CustomerManagementController@changeStatus', 'manageCustomers/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Customer Management'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Customer Management Delete', 'CustomerManagementController@delete', 'manageCustomers/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Customer Management'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Customer Management Edit', 'CustomerManagementController@edit', 'manageCustomers/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Customer Management'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Customer Management Save', 'CustomerManagementController@store', 'manageCustomers/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Customer Management'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Customer Management Create', 'CustomerManagementController@create', 'manageCustomers/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Customer Management'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Customer Management List', 'CustomerManagementController@getList', 'manageCustomers/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Customer Management'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Customer Management Index', 'CustomerManagementController@index', 'manageCustomers/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%CustomerManagementController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%CustomerManagementController%') AND menu_id != 0);
/*Karthick 18th march 2020*/

/*Contract Permission*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract index', 'ContractManagementController@index', 'contract/index', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract list', 'ContractManagementController@list', 'contract/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract create', 'ContractManagementController@create', 'contract/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract contractStore', 'ContractManagementController@contractStore', 'contract/storeContract', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract ruleStore', 'ContractManagementController@ruleStore', 'contract/storeRule', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract updateContract', 'ContractManagementController@updateContract', 'contract/updateContract', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract updateRules', 'ContractManagementController@updateRules', 'contract/updateRules', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract ruleEdit', 'ContractManagementController@ruleEdit', 'contract/rule/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract rulecopy', 'ContractManagementController@ruleEdit', 'contract/rule/copy', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract contractChangeStatus', 'ContractManagementController@contractChangeStatus', 'contract/contractChangeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract rulesChangeStatus', 'ContractManagementController@rulesChangeStatus', 'contract/rulesChangeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract assignToTemplate', 'ContractManagementController@assignToTemplate', 'contract/templateAssign/assignToTemplate', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract unAssignFromTemplate', 'ContractManagementController@unAssignFromTemplate', 'contract/templateAssign/unAssignFromTemplate', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract mapToTemplate', 'ContractManagementController@mapToTemplate', 'contract/templateAssign/mapToTemplate', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContractManagementController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContractManagementController%') AND menu_id != 0);

/*KArthick 19th March 2020*/
/*User Groups Permission*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'User Group index', 'UserGroupsController@index', 'userGroups/index', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'User Group list', 'UserGroupsController@userGroupsList', 'userGroups/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'User Group create', 'UserGroupsController@create', 'userGroups/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'User Group store', 'UserGroupsController@store', 'userGroups/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'User Group edit', 'UserGroupsController@edit', 'userGroups/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'User Group update', 'UserGroupsController@update', 'userGroups/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'User Group delete', 'UserGroupsController@delete', 'userGroups/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'User Group changeStatus', 'UserGroupsController@changeStatus', 'userGroups/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00');


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserGroupsController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserGroupsController%') AND menu_id != 0);

/* Venkatesan 19-March-2020 Route config Management Template */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Management Update', 'RouteConfigManagementController@update', 'routeConfig/update', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Management ChangeStatus', 'RouteConfigManagementController@changeStatus', 'routeConfig/changeStatus', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Management Delete', 'RouteConfigManagementController@delete', 'routeConfig/delete', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Management Edit', 'RouteConfigManagementController@edit', 'routeConfig/edit', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Management store', 'RouteConfigManagementController@store', 'routeConfig/store', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Management Create', 'RouteConfigManagementController@create', 'routeConfig/create', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Management Index', 'RouteConfigManagementController@index', 'routeConfig/list', '', 'Y', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Management Get List', 'RouteConfigManagementController@getList', 'routeConfig/list', '', 'Y', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Management Generate File', 'RouteConfigManagementController@generateFile', 'routeConfig/generateFile', '', 'Y', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Management Erun Route Config', 'RouteConfigManagementController@erunRouteConfig', 'routeConfig/erunRouteConfig', '', 'Y', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Management Download File', 'RouteConfigManagementController@downloadFile', 'routeConfig/downloadFile', '', 'Y', 'A', 1, '2020-03-19 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RouteConfigManagementController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RouteConfigManagementController%') AND menu_id != 0);

/* Venkatesan 19-March-2020 Route config Management Rules */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Rules Update', 'RouteConfigRulesController@update', 'routeConfigRules/update', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Rules ChangeStatus', 'RouteConfigRulesController@changeStatus', 'routeConfigRules/changeStatus', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Rules Delete', 'RouteConfigRulesController@delete', 'routeConfigRules/delete', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Rules Edit', 'RouteConfigRulesController@edit', 'routeConfigRules/edit', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Rules store', 'RouteConfigRulesController@store', 'routeConfigRules/store', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Rules Create', 'RouteConfigRulesController@create', 'routeConfigRules/create', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Rules Index', 'RouteConfigRulesController@index', 'routeConfigRules/list', '', 'Y', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Rules Get List', 'RouteConfigRulesController@getList', 'routeConfigRules/list', '', 'Y', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Get Country For Route Config Rule', 'RouteConfigRulesController@getCountryForRouteConfigRule', 'routeConfigRules/getCountryForRouteConfigRule', '', 'Y', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Get Airport List For Route Config Rule', 'RouteConfigRulesController@getAirportListForRouteConfigRule', 'routeConfigRules/getAirportListForRouteConfigRule', '', 'Y', 'A', 1, '2020-03-19 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RouteConfigRulesController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RouteConfigRulesController%') AND menu_id != 0);

/* Venkatesan 19-March-2020 Portal Promotion */
INSERT INTO `menu_details` (`menu_id`, `menu_name`, `link`, `icon`, `menu_type`, `status`) VALUES (NULL, 'CMS Settings', NULL, 'web', 'A', 'Y');

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES (NULL, '1', (SELECT `menu_id` from `menu_details` where `menu_name` = 'CMS Settings'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Portal Promotion'), '0', '1', '1', '1', 'Y', 'Y');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Promotion'), 'Portal Promotion Update', 'PortalPromotionController@update', 'portalPromotions/update', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Promotion'), 'Portal Promotion ChangeStatus', 'PortalPromotionController@changeStatus', 'portalPromotions/changeStatus', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Promotion'), 'Portal Promotion Delete', 'PortalPromotionController@delete', 'portalPromotions/delete', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Promotion'), 'Portal Promotion Edit', 'PortalPromotionController@edit', 'portalPromotions/edit', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Promotion'), 'Portal Promotion store', 'PortalPromotionController@store', 'portalPromotions/store', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Promotion'), 'Portal Promotion Create', 'PortalPromotionController@create', 'portalPromotions/create', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Promotion'), 'Portal Promotion Index', 'PortalPromotionController@index', 'portalPromotions/list', '', 'Y', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Promotion'), 'Portal Promotion Get List', 'PortalPromotionController@getList', 'portalPromotions/list', '', 'Y', 'A', 1, '2020-03-19 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalPromotionController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalPromotionController%') AND menu_id != 0);

/*Flight Share URL Permission*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'Flight ShareURL list', 'FlightShareUrlController@getFlightShareUrlList', 'flightShareUrl/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'Flight ShareURL index', 'FlightShareUrlController@getShareUrlIndex', 'flightShareUrl/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'Flight ShareURL shareUrlExpiryUpdateEmail', 'FlightShareUrlController@sendExpiryUpdateEmail', 'flightShareUrl/shareUrlExpiryUpdateEmail', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'Flight ShareURL store', 'FlightShareUrlController@shareUrlChangeStatus', 'flightShareUrl/shareUrlChangeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Groups'), 'Airline Group Delete', 'AirlineGroupController@delete', 'airlineGroup/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirlineGroupController@delete%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirlineGroupController@delete%') AND menu_id != 0);

/*Seenivasan 19 March 2020 Airport group*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Groups'), 'Airport Group Copy', 'AirportGroupController@edit', 'airportGroup/copy', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Groups'), 'Airport Group Update', 'AirportGroupController@update', 'airportGroup/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Groups'), 'Airport Group Delete', 'AirportGroupController@delete', 'airportGroup/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Groups'), 'Airport Group ChangeStatus', 'AirportGroupController@changeStatus', 'airportGroup/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Groups'), 'Airport Group Edit', 'AirportGroupController@edit', 'airportGroup/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Groups'), 'Airport Group Save', 'AirportGroupController@store', 'airportGroup/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Groups'), 'Airport Group Create', 'AirportGroupController@create', 'airportGroup/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Groups'), 'Airport Group Index', 'AirportGroupController@index', 'airportGroup/list', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirportGroupController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirportGroupController%') AND menu_id != 0);


/* Seenivasan 19 March 2020 Popular Routes*/

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES (NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'CMS Settings'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Popular Routes'), '0', '2', '1', '1', 'Y', 'Y');


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Routes'), 'Popular Routes Update', 'PopularRoutesController@update', 'popularRoutes/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Routes'), 'Popular Routes Delete', 'PopularRoutesController@delete', 'popularRoutes/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Routes'), 'Popular Routes ChangeStatus', 'PopularRoutesController@changeStatus', 'popularRoutes/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Routes'), 'Popular Routes Edit', 'PopularRoutesController@edit', 'popularRoutes/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Routes'), 'Popular Routes Save', 'PopularRoutesController@store', 'popularRoutes/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Routes'), 'Popular Routes Create', 'PopularRoutesController@create', 'popularRoutes/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Routes'), 'Popular Routes Index', 'PopularRoutesController@index', 'popularRoutes/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Routes'), 'Popular Routes List', 'PopularRoutesController@list', 'popularRoutes/list', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PopularRoutesController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PopularRoutesController%') AND menu_id != 0);


/* Seenivasan 19 March 2020 Currency Exchange Rate*/


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Currency Exchange Rate'), 'Currency ExchangeRate Update', 'CurrencyExchangeRateController@update', 'currencyExchangeRate/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Currency Exchange Rate'), 'Currency ExchangeRate ChangeStatus', 'CurrencyExchangeRateController@changeStatus', 'currencyExchangeRate/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Currency Exchange Rate'), 'Currency ExchangeRate Delete', 'CurrencyExchangeRateController@delete', 'currencyExchangeRate/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Currency Exchange Rate'), 'Currency ExchangeRate Edit', 'CurrencyExchangeRateController@edit', 'currencyExchangeRate/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Currency Exchange Rate'), 'Currency ExchangeRate Save', 'CurrencyExchangeRateController@store', 'currencyExchangeRate/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Currency Exchange Rate'), 'Currency ExchangeRate Create', 'CurrencyExchangeRateController@create', 'currencyExchangeRate/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Currency Exchange Rate'), 'Currency ExchangeRate List', 'CurrencyExchangeRateController@getList', 'currencyExchangeRate/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Currency Exchange Rate'), 'Currency ExchangeRate Index', 'CurrencyExchangeRateController@index', 'currencyExchangeRate/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%CurrencyExchangeRateController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%CurrencyExchangeRateController%') AND menu_id != 0);

/* Seenivasan 19 March 2020 Country Details*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Country Details'), 'Country Details Update', 'CountryDetailsController@update', 'countryDetails/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Country Details'), 'Country Details ChangeStatus', 'CountryDetailsController@changeStatus', 'countryDetails/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Country Details'), 'Country Details Delete', 'CountryDetailsController@delete', 'countryDetails/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Country Details'), 'Country Details Edit', 'CountryDetailsController@edit', 'countryDetails/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Country Details'), 'Country Details Save', 'CountryDetailsController@store', 'countryDetails/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Country Details'), 'Country Details Create', 'CountryDetailsController@create', 'countryDetails/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Country Details'), 'Country Details List', 'CountryDetailsController@index', 'countryDetails/list', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%CountryDetailsController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%CountryDetailsController%') AND menu_id != 0);

/* Seenivasan 19 March 2020 User Traveller*/


INSERT INTO `menu_details` (`menu_id`, `menu_name`, `link`, `icon`, `menu_type`, `status`) VALUES (NULL, 'User Travellers', 'userTravellers', 'rowing', 'A', 'Y');

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'User Travellers'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'No Submenu'), 0, '5', '1', '1', 'Y', 'Y');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Travellers'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Travellers Update', 'UserTravellerController@update', 'userTraveller/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Travellers'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Travellers ChangeStatus', 'UserTravellerController@changeStatus', 'userTraveller/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Travellers'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Travellers Delete', 'UserTravellerController@delete', 'userTraveller/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Travellers'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Travellers Edit', 'UserTravellerController@edit', 'userTraveller/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Travellers'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Travellers Save', 'UserTravellerController@store', 'userTraveller/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Travellers'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Travellers Create', 'UserTravellerController@create', 'userTraveller/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Travellers'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Travellers List', 'UserTravellerController@index', 'userTraveller/list', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserTravellerController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserTravellerController%') AND menu_id != 0);

/* Seenivasan 19 March 2020 Banner Section*/

INSERT INTO `menu_details` (`menu_id`, `menu_name`, `link`, `icon`, `menu_type`, `status`) VALUES (NULL, 'Route Pages', 'routePages', 'flight', 'A', 'Y');


INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'Route Pages'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Banner Section'), 0, '6', '1', '1', 'Y', 'Y');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Banner Section'), 'Banner Section Update', 'BannerSectionController@update', 'bannerSection/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Banner Section'), 'Banner Section ChangeStatus', 'BannerSectionController@changeStatus', 'bannerSection/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Banner Section'), 'Banner Section Delete', 'BannerSectionController@delete', 'bannerSection/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Banner Section'), 'Banner Section Edit', 'BannerSectionController@edit', 'bannerSection/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Banner Section'), 'Banner Section Save', 'BannerSectionController@store', 'bannerSection/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Banner Section'), 'Banner Section Create', 'BannerSectionController@create', 'bannerSection/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Banner Section'), 'Banner Section List', 'BannerSectionController@index', 'bannerSection/list', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BannerSectionController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BannerSectionController%') AND menu_id != 0);


/* Seenivasan 19 March 2020 Promo Codes*/

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'Settings'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Promo Codes'), 0, '6', '1', '1', 'Y', 'Y');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Promo Codes'), 'Promo Codes Update', 'PromoCodeController@update', 'promoCode/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Promo Codes'), 'Promo Codes ChangeStatus', 'PromoCodeController@changeStatus', 'promoCode/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Promo Codes'), 'Promo Codes Delete', 'PromoCodeController@delete', 'promoCode/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Promo Codes'), 'Promo Codes Edit', 'PromoCodeController@edit', 'promoCode/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Promo Codes'), 'Promo Codes Save', 'PromoCodeController@store', 'promoCode/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Promo Codes'), 'Promo Codes Create', 'PromoCodeController@create', 'promoCode/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Promo Codes'), 'Promo Codes List', 'PromoCodeController@index', 'promoCode/list', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Promo Codes'), 'Promo Codes Portal Info', 'PromoCodeController@portalInfo', 'promoCode/portalDetails', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PromoCodeController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PromoCodeController%') AND menu_id != 0);


/* Seenivasan 19 March 2020 City Management*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'City Management'), 'City Management Update', 'CityManagementController@update', 'cityManagement/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'City Management'), 'City Management ChangeStatus', 'CityManagementController@changeStatus', 'cityManagement/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'City Management'), 'City Management Delete', 'CityManagementController@delete', 'cityManagement/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'City Management'), 'City Management Edit', 'CityManagementController@edit', 'cityManagement/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'City Management'), 'City Management Save', 'CityManagementController@store', 'cityManagement/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'City Management'), 'City Management Create', 'CityManagementController@create', 'cityManagement/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'City Management'), 'City Management List', 'CityManagementController@index', 'cityManagement/list', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%CityManagementController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%CityManagementController%') AND menu_id != 0);


/* Seenivasan 19 March 2020 Remark Template*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Remark Templates'), 'Remark Templates Update', 'RemarkTemplateController@update', 'remarkTemplate/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Remark Templates'), 'Remark Templates ChangeStatus', 'RemarkTemplateController@changeStatus', 'remarkTemplate/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Remark Templates'), 'Remark Templates Delete', 'RemarkTemplateController@delete', 'remarkTemplate/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Remark Templates'), 'Remark Templates Edit', 'RemarkTemplateController@edit', 'remarkTemplate/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Remark Templates'), 'Remark Templates Save', 'RemarkTemplateController@store', 'remarkTemplate/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Remark Templates'), 'Remark Templates Create', 'RemarkTemplateController@create', 'remarkTemplate/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Remark Templates'), 'Remark Templates List', 'RemarkTemplateController@index', 'remarkTemplate/list', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RemarkTemplateController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RemarkTemplateController%') AND menu_id != 0);

/* Seenivasan 19 March 2020 Risk Analysis*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Risk Analysis Template'), 'Risk Analysis Template Update', 'RiskAnalysisManagementController@update', 'riskAnalysisManagement/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Risk Analysis Template'), 'Risk Analysis Template ChangeStatus', 'RiskAnalysisManagementController@changeStatus', 'riskAnalysisManagement/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Risk Analysis Template'), 'Risk Analysis Template Delete', 'RiskAnalysisManagementController@delete', 'riskAnalysisManagement/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Risk Analysis Template'), 'Risk Analysis Template Edit', 'RiskAnalysisManagementController@edit', 'riskAnalysisManagement/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Risk Analysis Template'), 'Risk Analysis Template Save', 'RiskAnalysisManagementController@store', 'riskAnalysisManagement/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Risk Analysis Template'), 'Risk Analysis Template Create', 'RiskAnalysisManagementController@create', 'riskAnalysisManagement/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Risk Analysis Template'), 'Risk Analysis Template List', 'RiskAnalysisManagementController@list', 'riskAnalysisManagement/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Risk Analysis Template'), 'Risk Analysis Template List', 'RiskAnalysisManagementController@index', 'riskAnalysisManagement/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RiskAnalysisManagementController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RiskAnalysisManagementController%') AND menu_id != 0);


/* Seenivasan 19 March 2020 Popular Destination*/


INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES (NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'CMS Settings'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Popular Destination'), '0', '3', '1', '1', 'Y', 'Y');


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Destination'), 'Popular Destination Update', 'PopularDestinationController@update', 'popularDestinations/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Destination'), 'Popular Destination Delete', 'PopularDestinationController@delete', 'popularDestinations/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Destination'), 'Popular Destination ChangeStatus', 'PopularDestinationController@changeStatus', 'popularDestinations/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Destination'), 'Popular Destination Edit', 'PopularDestinationController@edit', 'popularDestinations/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Destination'), 'Popular Destination Save', 'PopularDestinationController@store', 'popularDestinations/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Destination'), 'Popular Destination Create', 'PopularDestinationController@create', 'popularDestinations/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Destination'), 'Popular Destination Index', 'PopularDestinationController@index', 'popularDestinations/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Destination'), 'Popular Destination List', 'PopularDestinationController@list', 'popularDestinations/list', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PopularDestinationController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PopularDestinationController%') AND menu_id != 0);


/* Seenivasan 19 March 2020 Customer Feedback*/

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES (NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'CMS Settings'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Customer Feedback'), '0', '4', '1', '1', 'Y', 'Y');


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Customer Feedback'), 'Customer Feedback Update', 'CustomerFeedbackController@update', 'customerFeedbacks/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Customer Feedback'), 'Customer Feedback Delete', 'CustomerFeedbackController@delete', 'customerFeedbacks/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Customer Feedback'), 'Customer Feedback ChangeStatus', 'CustomerFeedbackController@changeStatus', 'customerFeedbacks/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Customer Feedback'), 'Customer Feedback Edit', 'CustomerFeedbackController@edit', 'customerFeedbacks/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Customer Feedback'), 'Customer Feedback Save', 'CustomerFeedbackController@store', 'customerFeedbacks/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Customer Feedback'), 'Customer Feedback Create', 'CustomerFeedbackController@create', 'customerFeedbacks/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Customer Feedback'), 'Customer Feedback Index', 'CustomerFeedbackController@index', 'customerFeedbacks/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Customer Feedback'), 'Customer Feedback List', 'CustomerFeedbackController@list', 'customerFeedbacks/list', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%CustomerFeedbackController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%CustomerFeedbackController%') AND menu_id != 0);


/* Seenivasan 19 March 2020 Quality Check Template  */


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Quality Check Template'), 'Quality Check Template Update', 'QualityCheckTemplateController@update', 'qualityCheckTemplate/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Quality Check Template'), 'Quality Check Template Delete', 'QualityCheckTemplateController@delete', 'qualityCheckTemplate/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Quality Check Template'), 'Quality Check Template ChangeStatus', 'QualityCheckTemplateController@changeStatus', 'qualityCheckTemplate/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Quality Check Template'), 'Quality Check Template Edit', 'QualityCheckTemplateController@edit', 'qualityCheckTemplate/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Quality Check Template'), 'Quality Check Template Save', 'QualityCheckTemplateController@store', 'qualityCheckTemplate/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Quality Check Template'), 'Quality Check Template Create', 'QualityCheckTemplateController@create', 'qualityCheckTemplate/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Quality Check Template'), 'Quality Check Template Index', 'QualityCheckTemplateController@index', 'qualityCheckTemplate/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Quality Check Template'), 'Quality Check Template List', 'QualityCheckTemplateController@getList', 'qualityCheckTemplate/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Quality Check Template'), 'Quality Check Template List', 'QualityCheckTemplateController@getContentSourcePCCForQc', 'qualityCheckTemplate/getPCCForQc', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%QualityCheckTemplateController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%QualityCheckTemplateController%') AND menu_id != 0);

/* Venkatesan 19-March-2020 Portal Details */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Portal Details Update', 'PortalDetailsController@update', 'portalDetails/update', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Portal Details ChangeStatus', 'PortalDetailsController@changeStatus', 'portalDetails/changeStatus', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Portal Details Delete', 'PortalDetailsController@delete', 'portalDetails/delete', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Portal Details Edit', 'PortalDetailsController@edit', 'portalDetails/edit', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Portal Details store', 'PortalDetailsController@store', 'portalDetails/store', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Portal Details Create', 'PortalDetailsController@create', 'portalDetails/create', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Portal Details Index', 'PortalDetailsController@index', 'portalDetails/list', '', 'Y', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Portal Details Get List', 'PortalDetailsController@getList', 'portalDetails/list', '', 'Y', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Portal Details Based By Account Id', 'PortalDetailsController@getPortalDetailsBasedByAccountId', 'portalDetails/getPortalDetailsBasedByAccountId', '', 'Y', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'portal Exists Validation', 'PortalDetailsController@portalExistsValidation', 'portalDetails/portalExistsValidation', '', 'Y', 'A', 1, '2020-03-19 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalDetailsController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalDetailsController%') AND menu_id != 0);

/* Venkatesan 19-March-2020 Portal Credentials */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Portal Credentials Index', 'PortalCredentialsController@index', 'portalCredentials/list', '', 'Y', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Portal Credentials Get List', 'PortalCredentialsController@getList', 'portalCredentials/list', '', 'Y', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Portal Credentials Create', 'PortalCredentialsController@create', 'portalCredentials/create', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Portal Credentials store', 'PortalCredentialsController@store', 'portalCredentials/store', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Portal Credentials Edit', 'PortalCredentialsController@edit', 'portalCredentials/edit', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Portal Credentials Update', 'PortalCredentialsController@update', 'portalCredentials/update', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Portal Credentials Delete', 'PortalCredentialsController@delete', 'portalCredentials/delete', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Portal Credentials ChangeStatus', 'PortalCredentialsController@changeStatus', 'portalCredentials/changeStatus', '', 'N', 'A', 1, '2020-03-19 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalCredentialsController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalCredentialsController%') AND menu_id != 0);


/* Venkatesan 19-March-2020 Get Airport List*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Airport Management'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Airport Management', 'AirportManagementController@getAirports', 'getAirports', '', 'Y', 'A', 1, '2020-03-19 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirportManagementController@getAirports%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirportManagementController@getAirports%') AND menu_id != 0);


/* Venkatesan 19-March-2020 Meta Portal */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Meta Portal Index', 'MetaPortalController@index', 'metaPortal/list', '', 'Y', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Meta Portal Get List', 'MetaPortalController@getList', 'metaPortal/list', '', 'Y', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Meta Portal Create', 'MetaPortalController@create', 'metaPortal/create', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Meta Portal store', 'MetaPortalController@store', 'metaPortal/store', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Meta Portal Edit', 'MetaPortalController@edit', 'metaPortal/edit', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Meta Portal Update', 'MetaPortalController@update', 'metaPortal/update', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Meta Portal Delete', 'MetaPortalController@delete', 'metaPortal/delete', '', 'N', 'A', 1, '2020-03-19 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Meta Portal ChangeStatus', 'MetaPortalController@changeStatus', 'metaPortal/changeStatus', '', 'N', 'A', 1, '2020-03-19 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MetaPortalController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MetaPortalController%') AND menu_id != 0);

/* Divakar 20-March-2020 Permission */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Token', 'AccessTokenController@issueToken', 'token', '', 'Y', 'A', 1, '2018-05-08 00:00:00');


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Login', 'LoginController@authenticate', 'login', '', 'Y', 'A', 1, '2018-05-08 00:00:00');


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Logout', 'LoginController@logout', 'logout', '', 'Y', 'A', 1, '2018-05-08 00:00:00');


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Menu', 'MenuDetailsController@getMenu', 'getMenu', '', 'Y', 'A', 1, '2018-05-08 00:00:00');


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Permission', 'PermissionsController@getPermissions', 'getPermissions', '', 'Y', 'A', 1, '2018-05-08 00:00:00');


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Flight Result', 'FlightsController@getResult', 'flights/getResult', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Flight Check Price', 'FlightsController@checkPrice', 'flights/checkPrice', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Flight booking', 'FlightsController@flightBooking', 'flights/booking', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Insurance Quote', 'InsuranceController@getQuote', 'insurance/getQuote', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Hotel Result', 'HotelsController@getResults', 'hotels/getResults', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Dynamic Package Result', 'PackagesController@getFlightHotel', 'packages/getFlightHotel', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Balance', 'AgencyCreditManagementController@getBalance', 'getBalance', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

/* Venkatesan 20-March-2020  Portal Airline Blocking Template*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Portal Airline Blocking Template Index', 'PortalAirlineBlockingTemplatesController@index', 'portalAirlineBlockingTemplate/list', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Portal Airline Blocking Template Get List', 'PortalAirlineBlockingTemplatesController@getList', 'portalAirlineBlockingTemplate/list', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Portal Airline Blocking Template Create', 'PortalAirlineBlockingTemplatesController@create', 'portalAirlineBlockingTemplate/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Portal Airline Blocking Template store', 'PortalAirlineBlockingTemplatesController@store', 'portalAirlineBlockingTemplate/store', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Portal Airline Blocking Template Edit', 'PortalAirlineBlockingTemplatesController@edit', 'portalAirlineBlockingTemplate/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Portal Airline Blocking Template Update', 'PortalAirlineBlockingTemplatesController@update', 'portalAirlineBlockingTemplate/update', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Portal Airline Blocking Template Delete', 'PortalAirlineBlockingTemplatesController@delete', 'portalAirlineBlockingTemplate/delete', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Portal Airline Blocking Template Change Status', 'PortalAirlineBlockingTemplatesController@changeStatus', 'portalAirlineBlockingTemplate/changeStatus', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Portal Airline Blocking Template Get Portal List', 'PortalAirlineBlockingTemplatesController@getPortalList', 'getPortalList', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalAirlineBlockingTemplatesController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalAirlineBlockingTemplatesController%') AND menu_id != 0);

/* Venkatesan 20-March-2020  Portal Airline Blocking Rules*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Portal Airline Blocking Rules Index', 'PortalAirlineBlockingRulesController@index', 'portalAirlineBlockingRules/list', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Portal Airline Blocking Rules Get List', 'PortalAirlineBlockingRulesController@getList', 'portalAirlineBlockingRules/list', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Portal Airline Blocking Rules Create', 'PortalAirlineBlockingRulesController@create', 'portalAirlineBlockingRules/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Portal Airline Blocking Rules store', 'PortalAirlineBlockingRulesController@store', 'portalAirlineBlockingRules/store', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Portal Airline Blocking Rules Edit', 'PortalAirlineBlockingRulesController@edit', 'portalAirlineBlockingRules/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Portal Airline Blocking Rules Update', 'PortalAirlineBlockingRulesController@update', 'portalAirlineBlockingRules/update', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Portal Airline Blocking Rules Delete', 'PortalAirlineBlockingRulesController@delete', 'portalAirlineBlockingRules/delete', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Portal Airline Blocking Rules ChangeStatus', 'PortalAirlineBlockingRulesController@changeStatus', 'portalAirlineBlockingRules/changeStatus', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalAirlineBlockingRulesController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalAirlineBlockingRulesController%') AND menu_id != 0);

/* Venkatesan 20-March-2020  Portal Airline Masking Template*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Portal Airline Masking Template Index', 'PortalAirlineMaskingTemplatesController@index', 'portalAirlineMaskingTemplates/list', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Portal Airline Masking Template Get List', 'PortalAirlineMaskingTemplatesController@getList', 'portalAirlineMaskingTemplates/list', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Portal Airline Masking Template Create', 'PortalAirlineMaskingTemplatesController@create', 'portalAirlineMaskingTemplates/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Portal Airline Masking Template store', 'PortalAirlineMaskingTemplatesController@store', 'portalAirlineMaskingTemplates/store', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Portal Airline Masking Template Edit', 'PortalAirlineMaskingTemplatesController@edit', 'portalAirlineMaskingTemplates/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Portal Airline Masking Template Update', 'PortalAirlineMaskingTemplatesController@update', 'portalAirlineMaskingTemplates/update', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Portal Airline Masking Template Delete', 'PortalAirlineMaskingTemplatesController@delete', 'portalAirlineMaskingTemplates/delete', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Portal Airline Masking Template Change Status', 'PortalAirlineMaskingTemplatesController@changeStatus', 'portalAirlineMaskingTemplates/changeStatus', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalAirlineMaskingTemplatesController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalAirlineMaskingTemplatesController%') AND menu_id != 0);

/* Venkatesan 20-March-2020  Portal Airline Masking Rules*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Portal Airline Masking Rules Index', 'PortalAirlineMaskingRulesController@index', 'portalAirlineMaskingRules/list', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Portal Airline Masking Rules Get List', 'PortalAirlineMaskingRulesController@getList', 'portalAirlineMaskingRules/list', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Portal Airline Masking Rules Create', 'PortalAirlineMaskingRulesController@create', 'portalAirlineMaskingRules/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Portal Airline Masking Rules store', 'PortalAirlineMaskingRulesController@store', 'portalAirlineMaskingRules/store', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Portal Airline Masking Rules Edit', 'PortalAirlineMaskingRulesController@edit', 'portalAirlineMaskingRules/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Portal Airline Masking Rules Update', 'PortalAirlineMaskingRulesController@update', 'portalAirlineMaskingRules/update', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Portal Airline Masking Rules Delete', 'PortalAirlineMaskingRulesController@delete', 'portalAirlineMaskingRules/delete', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Portal Airline Masking Rules Change Status', 'PortalAirlineMaskingRulesController@changeStatus', 'portalAirlineMaskingRules/changeStatus', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalAirlineMaskingRulesController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalAirlineMaskingRulesController%') AND menu_id != 0);

/*Karthick 19th march 2020 Agency fee permission */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee list', 'AgencyFeeManagementController@agencyFeeList', 'agencyFee/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee create', 'AgencyFeeManagementController@create', 'agencyFee/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee store', 'FlightShareUrlController@store', 'agencyFee/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee edit', 'AgencyFeeManagementController@edit', 'agencyFee/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee update', 'AgencyFeeManagementController@update', 'agencyFee/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee changeStatus', 'AgencyFeeManagementController@changeStatus', 'agencyFee/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee delete', 'AgencyFeeManagementController@delete', 'agencyFee/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee getHistory', 'AgencyFeeManagementController@getHistory', 'agencyFee/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee getHistoryDiff', 'AgencyFeeManagementController@getHistoryDiff', 'agencyFee/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyFeeManagementController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyFeeManagementController%') AND menu_id != 0);


/* Divakar 20 - March - 2020 Menu Related Changes */

ALTER TABLE `menu_details` ADD `new_link` VARCHAR(100) NULL DEFAULT NULL AFTER `link`;
ALTER TABLE `submenu_details` ADD `new_link` VARCHAR(100) NULL DEFAULT NULL AFTER `link`;

UPDATE `submenu_details` SET `new_link` = `link` WHERE 1;
UPDATE `menu_details` SET `new_link` = `link` WHERE 1;

/* Venkatesan 20-March-2020  Form of payment*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Form of payment'), 'Form of payment Rules Index', 'FormOfPaymentController@index', 'formOfPayment/list', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Form of payment'), 'Form of payment Rules Get List', 'FormOfPaymentController@getList', 'formOfPayment/list', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Form of payment'), 'Form of payment Rules Create', 'FormOfPaymentController@create', 'formOfPayment/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Form of payment'), 'Form of payment Rules store', 'FormOfPaymentController@store', 'formOfPayment/store', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Form of payment'), 'Form of payment Rules Edit', 'FormOfPaymentController@edit', 'formOfPayment/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Form of payment'), 'Form of payment Rules Update', 'FormOfPaymentController@update', 'formOfPayment/update', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Form of payment'), 'Form of payment Rules Delete', 'FormOfPaymentController@delete', 'formOfPayment/delete', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Form of payment'), 'Form of payment Rules Change Status', 'FormOfPaymentController@changeStatus', 'formOfPayment/changeStatus', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%FormOfPaymentController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%FormOfPaymentController%') AND menu_id != 0);  

/* Seenivasan 20 March 2020 Supplier Airline Blocking Templates */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Template Update', 'SupplierAirlineBlockingTemplatesController@update', 'supplierAirlineBlockingTemplates/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Template Delete', 'SupplierAirlineBlockingTemplatesController@delete', 'supplierAirlineBlockingTemplates/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Template ChangeStatus', 'SupplierAirlineBlockingTemplatesController@changeStatus', 'supplierAirlineBlockingTemplates/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Template Edit', 'SupplierAirlineBlockingTemplatesController@edit', 'supplierAirlineBlockingTemplates/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Template Save', 'SupplierAirlineBlockingTemplatesController@store', 'supplierAirlineBlockingTemplates/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Template Create', 'SupplierAirlineBlockingTemplatesController@create', 'supplierAirlineBlockingTemplates/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Template Index', 'SupplierAirlineBlockingTemplatesController@index', 'supplierAirlineBlockingTemplates/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Template List', 'SupplierAirlineBlockingTemplatesController@getList', 'supplierAirlineBlockingTemplates/list', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineBlockingTemplatesController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineBlockingTemplatesController%') AND menu_id != 0);

/* Seenivasan 20 March 2020 Supplier Airline Blocking Rules */


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking'  AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Rules Update', 'SupplierAirlineBlockingRulesController@update', 'supplierAirlineBlockingTemplates/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking'  AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Rules Delete', 'SupplierAirlineBlockingRulesController@delete', 'supplierAirlineBlockingTemplates/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking'  AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Rules ChangeStatus', 'SupplierAirlineBlockingRulesController@changeStatus', 'supplierAirlineBlockingTemplates/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking'  AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Rules Edit', 'SupplierAirlineBlockingRulesController@edit', 'supplierAirlineBlockingTemplates/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking'  AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Rules Save', 'SupplierAirlineBlockingRulesController@store', 'supplierAirlineBlockingTemplates/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking'  AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Rules Create', 'SupplierAirlineBlockingRulesController@create', 'supplierAirlineBlockingTemplates/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking'  AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Rules Index', 'SupplierAirlineBlockingRulesController@index', 'supplierAirlineBlockingTemplates/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking'  AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Rules List', 'SupplierAirlineBlockingRulesController@getList', 'supplierAirlineBlockingTemplates/list', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineBlockingRulesController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineBlockingRulesController%') AND menu_id != 0);

/* Venkatesan 20-March-2020  Sector Mapping*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Sector Mapping'), 'Sector Mapping Index', 'SectorMappingController@index', 'sectorMapping/list', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Sector Mapping'), 'Sector Mapping Get List', 'SectorMappingController@getList', 'sectorMapping/list', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Sector Mapping'), 'Sector Mapping Create', 'SectorMappingController@create', 'sectorMapping/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Sector Mapping'), 'Sector Mapping store', 'SectorMappingController@store', 'sectorMapping/store', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Sector Mapping'), 'Sector Mapping Edit', 'SectorMappingController@edit', 'sectorMapping/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Sector Mapping'), 'Sector Mapping Update', 'SectorMappingController@update', 'sectorMapping/update', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Sector Mapping'), 'Sector Mapping Delete', 'SectorMappingController@delete', 'sectorMapping/delete', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Sector Mapping'), 'Sector Mapping Change Status', 'SectorMappingController@changeStatus', 'sectorMapping/changeStatus', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Sector Mapping'), 'Get Content Source For Sector Mapping', 'SectorMappingController@getContentSourceForSectorMapping', 'sectorMapping/getContentSourceForSectorMapping', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SectorMappingController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SectorMappingController%') AND menu_id != 0);  

/* Karthick 20-March-2020  Agency Management*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management Index', 'AgencyManageController@getAgencyIndexDetails', 'manageAgency/getAgencyIndexDetails', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management Get List', 'AgencyManageController@list', 'manageAgency/list', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management Create', 'AgencyManageController@create', 'manageAgency/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management store', 'AgencyManageController@store', 'manageAgency/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management Edit', 'AgencyManageController@edit', 'manageAgency/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management Update', 'AgencyManageController@update', 'manageAgency/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management newRequests', 'AgencyManageController@newRequests', 'pendingAgency/newRequests', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management newRequestsView', 'AgencyManageController@newRequestsView', 'pendingAgency/newRequestsView', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management newAgencyRequestApprove', 'AgencyManageController@newAgencyRequestApprove', 'pendingAgency/newAgencyRequestApprove', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management newAgencyRequestReject', 'AgencyManageController@newAgencyRequestReject', 'pendingAgency/newAgencyRequestReject', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyManageController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyManageController%') AND menu_id != 0);  

/*Agency Credit Management*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management creditManagementTransactionList', 'AgencyCreditManagementController@creditManagementTransactionList', 'agencyCredit/creditManagementTransactionList', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management Get temporaryTopUpTransactionList', 'AgencyCreditManagementController@temporaryTopUpTransactionList', 'agencyCredit/temporaryTopUpTransactionList', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management agencyDepositTransactionList', 'AgencyCreditManagementController@agencyDepositTransactionList', 'agencyCredit/agencyDepositTransactionList', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management agencyPaymentTransactionList', 'AgencyCreditManagementController@agencyPaymentTransactionList', 'agencyCredit/agencyPaymentTransactionList', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management create', 'AgencyCreditManagementController@create', 'agencyCredit/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management approvePendingCredits', 'AgencyCreditManagementController@approve', 'agencyCredit/approvePendingCredits', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management approveReject', 'AgencyCreditManagementController@approveReject', 'agencyCredit/approveReject', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management getBalance', 'AgencyCreditManagementController@getBalance', 'agencyCredit/getBalance', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management showBalance', 'AgencyCreditManagementController@showBalance', 'agencyCredit/showBalance', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyCreditManagementController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyCreditManagementController%') AND menu_id != 0);  

/*Content Source*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management list', 'ContentSourceController@index', 'contentSource/list', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management create', 'ContentSourceController@create', 'contentSource/create', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management store', 'ContentSourceController@store', 'contentSource/store', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management edit', 'ContentSourceController@edit', 'contentSource/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management copy', 'ContentSourceController@edit', 'contentSource/copy', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management update', 'ContentSourceController@update', 'contentSource/update', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management changeStatus', 'ContentSourceController@changeStatus', 'contentSource/changeStatus', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management delete', 'ContentSourceController@delete', 'contentSource/delete', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management getContentSourceRefKey', 'ContentSourceController@getContentSourceRefKey', 'contentSource/getContentSourceRefKey', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management checkAAAtoPCCcsrefkeyExist', 'ContentSourceController@checkAAAtoPCCcsrefkeyExist', 'contentSource/checkAAAtoPCCcsrefkeyExist', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContentSourceController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContentSourceController%') AND menu_id != 0);  

/*Portal Config*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Config index', 'PortalConfigController@index', 'portalConfig/index', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Config list', 'PortalConfigController@portalConfigList', 'portalConfig/list', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Config create', 'PortalConfigController@create', 'portalConfig/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Config store', 'PortalConfigController@store', 'portalConfig/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Config edit', 'PortalConfigController@edit', 'portalConfig/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Config update', 'PortalConfigController@update', 'portalConfig/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Config getPaymentGatewayList', 'PortalConfigController@paymentGatewaySelect', 'portalConfig/getPaymentGatewayList', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalConfigController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalConfigController%') AND menu_id != 0);  

/*Markup Template*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates index', 'MarkupTemplateController@index', 'markupTemplate/index', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates list', 'MarkupTemplateController@supplierMarkUpTemplateList', 'markupTemplate/list', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates create', 'MarkupTemplateController@create', 'markupTemplate/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates store', 'MarkupTemplateController@store', 'markupTemplate/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates edit', 'MarkupTemplateController@edit', 'markupTemplate/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates update', 'MarkupTemplateController@update', 'markupTemplate/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates changeStatus', 'MarkupTemplateController@changeStatus', 'markupTemplate/changeStatus', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates delete', 'MarkupTemplateController@delete', 'markupTemplate/delete', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalConfigController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalConfigController%') AND menu_id != 0);  

/*Suplier Airline masking Template*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking index', 'SupplierAirlineMaskingTemplatesController@index', 'supplierAirlineMaskingTemplates/index', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking list', 'SupplierAirlineMaskingTemplatesController@getList', 'supplierAirlineMaskingTemplates/list', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking create', 'SupplierAirlineMaskingTemplatesController@create', 'supplierAirlineMaskingTemplates/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking store', 'SupplierAirlineMaskingTemplatesController@store', 'supplierAirlineMaskingTemplates/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking edit', 'SupplierAirlineMaskingTemplatesController@edit', 'supplierAirlineMaskingTemplates/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking update', 'SupplierAirlineMaskingTemplatesController@update', 'supplierAirlineMaskingTemplates/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking changeStatus', 'SupplierAirlineMaskingTemplatesController@changeStatus', 'supplierAirlineMaskingTemplates/changeStatus', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking delete', 'SupplierAirlineMaskingTemplatesController@delete', 'supplierAirlineMaskingTemplates/delete', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineMaskingTemplatesController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineMaskingTemplatesController%') AND menu_id != 0);  

/*Suplier Airline masking Template*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules index', 'SupplierAirlineMaskingRulesController@index', 'supplierAirlineMaskingRules/index', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules list', 'SupplierAirlineMaskingRulesController@getList', 'supplierAirlineMaskingRules/list', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules create', 'SupplierAirlineMaskingRulesController@create', 'supplierAirlineMaskingRules/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules store', 'SupplierAirlineMaskingRulesController@store', 'supplierAirlineMaskingRules/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules edit', 'SupplierAirlineMaskingRulesController@edit', 'supplierAirlineMaskingRules/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules update', 'SupplierAirlineMaskingRulesController@update', 'supplierAirlineMaskingRules/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules changeStatus', 'SupplierAirlineMaskingRulesController@changeStatus', 'supplierAirlineMaskingRules/changeStatus', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules delete', 'SupplierAirlineMaskingRulesController@delete', 'supplierAirlineMaskingRules/delete', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineMaskingRulesController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineMaskingRulesController%') AND menu_id != 0);

/* Venkatesan 21-March-2020  Look to Book Ratio*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Look to Book Ratio'), 'Look to Book Ratio Index', 'LookToBookRatioController@index', 'lookToBookRatio/list', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Look to Book Ratio'), 'Look to Book Ratio Get List', 'LookToBookRatioController@getList', 'lookToBookRatio/list', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Look to Book Ratio'), 'Look to Book Ratio Create', 'LookToBookRatioController@create', 'lookToBookRatio/create', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Look to Book Ratio'), 'Look to Book Ratio store', 'LookToBookRatioController@store', 'lookToBookRatio/store', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Look to Book Ratio'), 'Look to Book Ratio Edit', 'LookToBookRatioController@edit', 'lookToBookRatio/edit', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Look to Book Ratio'), 'Look to Book Ratio Update', 'LookToBookRatioController@update', 'lookToBookRatio/update', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Look to Book Ratio'), 'Look to Book Ratio Delete', 'LookToBookRatioController@delete', 'lookToBookRatio/delete', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Look to Book Ratio'), 'Look to Book Ratio Change Status', 'LookToBookRatioController@changeStatus', 'lookToBookRatio/changeStatus', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Look to Book Ratio'), 'Get Supplier Consumer Currency', 'LookToBookRatioController@getSupplierConsumerCurrency', 'lookToBookRatio/getSupplierConsumerCurrency', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Look to Book Ratio'), 'Get History', 'LookToBookRatioController@getHistory', 'lookToBookRatio/getHistory', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Look to Book Ratio'), 'Get History Diff', 'LookToBookRatioController@getHistoryDiff', 'lookToBookRatio/getHistoryDiff', '', 'N', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%LookToBookRatioController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%LookToBookRatioController%') AND menu_id != 0);  

/* Venkatesan 21-March-2020  Assign Supplier*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'supplier mapping Get List', 'SupplierMappingController@index', 'manageAgency/supplier/list', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'supplier mapping Get List', 'SupplierMappingController@supplierList', 'manageAgency/supplier/list', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'supplier mapping Create', 'SupplierMappingController@create', 'manageAgency/supplier/create', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'supplier mapping store', 'SupplierMappingController@store', 'manageAgency/supplier/store', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'supplier mapping Delete', 'SupplierMappingController@delete', 'manageAgency/supplier/delete', '', 'N', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierMappingController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierMappingController%') AND menu_id != 0);  

/* Venkatesan 21-March-2020 Portal Aggregation View For ManageAgency*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'portal Aggregation View', 'AccountDetailsController@portalAggregationView', 'manageAgency/portalAggregationView', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AccountDetailsController@portalAggregationView%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AccountDetailsController@portalAggregationView%') AND menu_id != 0);  

/* Venkatesan 21-March-2020 Agency Ticket Credentials*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Ticket Credentials  List', 'AgencyTicketCredentialsController@index', 'manageAgency/ticketCredentials/list', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Ticket Credentials Get List', 'AgencyTicketCredentialsController@getList', 'manageAgency/ticketCredentials/list', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Ticket Credentials Create', 'AgencyTicketCredentialsController@create', 'manageAgency/ticketCredentials/create', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Ticket Credentials Store', 'AgencyTicketCredentialsController@store', 'manageAgency/ticketCredentials/store', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Ticket Credentials Edit', 'AgencyTicketCredentialsController@edit', 'manageAgency/ticketCredentials/edit', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Ticket Credentials Update', 'AgencyTicketCredentialsController@update', 'manageAgency/ticketCredentials/update', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Ticket Credentials Delete', 'AgencyTicketCredentialsController@delete', 'manageAgency/ticketCredentials/delete', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Ticket Credentials Change Status', 'AgencyTicketCredentialsController@changeStatus', 'manageAgency/ticketCredentials/changeStatus', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Ticket Availability Check', 'AgencyTicketCredentialsController@agencyTicketAvailabilityCheck', 'manageAgency/ticketCredentials/agencyTicketAvailabilityCheck', '', 'N', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyTicketCredentialsController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyTicketCredentialsController%') AND menu_id != 0);  

/* Venkatesan 21-March-2020 Agency Promotions*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Promotions  List', 'AgencyPromotionController@index', 'agencyPromotion/list', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Promotions Get List', 'AgencyPromotionController@getList', 'agencyPromotion/list', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Promotions Create', 'AgencyPromotionController@create', 'agencyPromotion/create', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Promotions Store', 'AgencyPromotionController@store', 'agencyPromotion/store', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Promotions Edit', 'AgencyPromotionController@edit', 'agencyPromotion/edit', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Promotions Update', 'AgencyPromotionController@update', 'agencyPromotion/update', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Promotions Delete', 'AgencyPromotionController@delete', 'agencyPromotion/delete', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Promotions Change Status', 'AgencyPromotionController@changeStatus', 'agencyPromotion/changeStatus', '', 'N', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyPromotionController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyPromotionController%') AND menu_id != 0);  

/* Venkatesan 21-March-2020 Airline Manage*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Airline Manage'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Airline Manage  List', 'AirlineManagementController@index', 'airlineManage/list', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Airline Manage'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Airline Manage Get List', 'AirlineManagementController@getList', 'airlineManage/list', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Airline Manage'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Airline Manage Create', 'AirlineManagementController@create', 'airlineManage/create', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Airline Manage'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Airline Manage Store', 'AirlineManagementController@store', 'airlineManage/store', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Airline Manage'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Airline Manage Edit', 'AirlineManagementController@edit', 'airlineManage/edit', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Airline Manage'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Airline Manage Update', 'AirlineManagementController@update', 'airlineManage/update', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Airline Manage'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Airline Manage Delete', 'AirlineManagementController@delete', 'airlineManage/delete', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Airline Manage'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Airline Manage Change Status', 'AirlineManagementController@changeStatus', 'airlineManage/changeStatus', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Airline Manage'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Airlines', 'AirlineManagementController@getAirlines', 'getAirlines', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirlineManagementController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirlineManagementController%') AND menu_id != 0);  


/*KArthick 21st march 2020*/

CREATE TABLE `home_agent_details`
(
 `home_agent_id` INT(11) NOT NULL AUTO_INCREMENT ,
  `account_id` INT(11) NOT NULL ,
  `employment_status` VARCHAR(255) NULL ,
  `sales_amount_from` VARCHAR(50) NULL ,
  `sales_amount_to` VARCHAR(50) NULL ,
  `travel_industry_experience` ENUM('N','Y','') NULL DEFAULT 'N' ,
  `experience_level` INT(3) NULL ,
  `memberships` VARCHAR(255) NULL ,
  `business_specialization` VARCHAR(255) NULL ,
  `hours_per_week` VARCHAR(255) NULL ,
  `about_agent` TEXT NULL ,
  `travel_invest` VARCHAR(255) NULL ,
  `destination_country` VARCHAR(255) NULL ,
  `destination_state` VARCHAR(255) NULL ,
  `language` VARCHAR(255) NULL ,
  `existing_domain_information` VARCHAR(255) NULL ,
  `website_email` VARCHAR(255) NULL ,
  `phone_number` INT NULL ,
  `phone_number_country_code` INT NULL ,
  `phone_number_code` INT NULL ,
  `make_profile_flag` ENUM('N','Y','') NULL DEFAULT 'N' ,
  `profile_original_name` VARCHAR(255) NULL ,
  `profile_pic_name` VARCHAR(255) NULL ,
  `created_by` INT NOT NULL ,
  `updated_by` INT NOT NULL ,
  `created_at` DATETIME NOT NULL ,
  `updated_at` DATETIME NOT NULL ,
  PRIMARY KEY (`home_agent_id`), UNIQUE (`account_id`)) ENGINE = InnoDB;

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Registration login', 'LoginController@authenticate', 'login', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Registration getAgencyRegisterFormData', 'AgencyRegisterController@getAgencyRegisterFormData', 'getAgencyRegisterFormData', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Registration getHomeAgencyRegisterFormData', 'AgencyRegisterController@getHomeAgencyRegisterFormData', 'getHomeAgencyRegisterFormData', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Registration userLogin', 'UsersController@authenticate', 'userLogin', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Registration customerLogin', 'CustomersController@authenticate', 'customerLogin', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Registration agencyRegister', 'AgencyRegisterController@agencyRegister', 'agencyRegister', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Registration agentRegister', 'UserRegisterController@userRegister', 'agentRegister', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Registration forgotPassword', 'ForgotPasswordController@forgotPassword', 'forgotPassword', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Registration updatePassword', 'ForgotPasswordController@updatePassword', 'updatePassword', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Registration getAgencyData', 'AgencyManageController@getAgencyData', 'getAgencyData', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Registration checkAlreadyExists', 'CommonController@checkAlreadyExists', 'checkAlreadyExists', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Registration updateRedisData', 'CommonController@updateRedisData', 'updateRedisData', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Registration getPortalBasedDetails', 'PortalConfigController@getPortalBasedDetails', 'getPortalBasedDetails', '', 'Y', 'A', 1, '2020-03-20 00:00:00');

/* Venkatesan 21-March-2020 Benefit Content*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Add Benefits'), 'Add Benefits  List', 'BenefitContentController@index', 'benefitContent/list', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Add Benefits'), 'Add Benefits Get List', 'BenefitContentController@getList', 'benefitContent/list', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Add Benefits'), 'Add Benefits Create', 'BenefitContentController@create', 'benefitContent/create', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Add Benefits'), 'Add Benefits Store', 'BenefitContentController@store', 'benefitContent/store', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Add Benefits'), 'Add Benefits Edit', 'BenefitContentController@edit', 'benefitContent/edit', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Add Benefits'), 'Add Benefits Update', 'BenefitContentController@update', 'benefitContent/update', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Add Benefits'), 'Add Benefits Delete', 'BenefitContentController@delete', 'benefitContent/delete', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Add Benefits'), 'Add Benefits Change Status', 'BenefitContentController@changeStatus', 'benefitContent/changeStatus', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Add Benefits'), 'Get Benefit List', 'BenefitContentController@getBenefitList', 'benefitContent/getBenefitList', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BenefitContentController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BenefitContentController%') AND menu_id != 0);  

/* Venkatesan 21-March-2020 Blog Content*/
INSERT INTO `submenu_details` (`submenu_id`, `submenu_name`, `link`, `icon`, `sub_menu_type`, `status`) VALUES (NULL, 'Blog Content', 'blogContent', 'create', 'A', 'Y');

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES (NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA') , (SELECT `menu_id` from `menu_details` where `menu_name` = 'CMS Settings'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Blog Content'), '0', '1', '1', '1', 'Y', 'Y');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Blog Content'), 'Blog Content  List', 'BlogContentController@index', 'blogContent/list', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Blog Content'), 'Blog Content Get List', 'BlogContentController@getList', 'blogContent/list', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Blog Content'), 'Blog Content Create', 'BlogContentController@create', 'blogContent/create', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Blog Content'), 'Blog Content Store', 'BlogContentController@store', 'blogContent/store', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Blog Content'), 'Blog Content Edit', 'BlogContentController@edit', 'blogContent/edit', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Blog Content'), 'Blog Content Update', 'BlogContentController@update', 'blogContent/update', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Blog Content'), 'Blog Content Delete', 'BlogContentController@delete', 'blogContent/delete', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Blog Content'), 'Blog Content Change Status', 'BlogContentController@changeStatus', 'blogContent/changeStatus', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Blog Content'), 'Get Blog List', 'BlogContentController@getBlogList', 'blogContent/getBlogList', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BlogContentController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BlogContentController%') AND menu_id != 0);  


/* Divakar 21 - March - 2020 Booking List Permission */


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Flight Booking  List', 'BookingsController@bookingList', 'bookings/list', '', 'N', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@bookingList%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@bookingList%') AND menu_id != 0);  

/* Seenivasan 20 March 2020 Airline Group History */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Groups'), 'Airline Group History', 'AirlineGroupController@getHistory', 'airlineGroup/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Groups'), 'Airline Group History Diff', 'AirlineGroupController@getHistoryDiff', 'airlineGroup/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirlineGroupController@getHistory%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirlineGroupController@getHistory%') AND menu_id != 0);

/* Seenivasan 20 March 2020 Airport Group History */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Groups'), 'Airport Group History', 'AirportGroupController@getHistory', 'airportGroup/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Groups'), 'Airport Group History Diff', 'AirportGroupController@getHistoryDiff', 'airportGroup/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirportGroupController@getHistory%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirportGroupController@getHistory%') AND menu_id != 0);


/* Seenivasan 20 March 2020 Promo Code History */


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Promo Codes'), 'Promo Codes History', 'PromoCodeController@getHistory', 'promoCode/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Promo Codes'), 'Promo Codes History Diff', 'PromoCodeController@getHistoryDiff', 'promoCode/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PromoCodeController@getHistory%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PromoCodeController@getHistory%') AND menu_id != 0);

/* Seenivasan 20 March 2020 Airport Settings */


INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'Settings'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Airport Manage'), 0, '8', '1', '1', 'Y', 'Y');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Manage'), 'Airport Manage Update', 'AirportSettingsController@update', 'airportManage/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Manage'), 'Airport Manage ChangeStatus', 'AirportSettingsController@changeStatus', 'airportManage/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Manage'), 'Airport Manage Delete', 'AirportSettingsController@delete', 'airportManage/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Manage'), 'Airport Manage Edit', 'AirportSettingsController@edit', 'airportManage/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Manage'), 'Airport Manage Save', 'AirportSettingsController@store', 'airportManage/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Manage'), 'Airport Manage Create', 'AirportSettingsController@create', 'airportManage/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Manage'), 'Airport Manage List', 'AirportSettingsController@index', 'airportManage/list', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirportSettingsController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirportSettingsController%') AND menu_id != 0);


/* Seenivasan 20 March 2020 Remark Template */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Remark Templates'), 'Remark Templates History', 'RemarkTemplateController@getHistory', 'remarkTemplate/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Remark Templates'), 'Remark Templates History Diff', 'RemarkTemplateController@getHistoryDiff', 'remarkTemplate/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RemarkTemplateController@getHistory%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RemarkTemplateController@getHistory%') AND menu_id != 0);


/* Seenivasan 20 March 2020 Risk Analysis */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Risk Analysis Template'), 'Risk Analysis Template History', 'RiskAnalysisManagementController@getHistory', 'riskAnalysisManagement/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Risk Analysis Template'), 'Risk Analysis Template History Diff', 'RiskAnalysisManagementController@getHistoryDiff', 'riskAnalysisManagement/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RiskAnalysisManagementController@getHistory%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RiskAnalysisManagementController@getHistory%') AND menu_id != 0);


/* Seenivasan 20 March 2020 Quality Check */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Quality Check Template'), 'Quality Check Template History', 'QualityCheckTemplateController@getHistory', 'qualityCheckTemplate/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Quality Check Template'), 'Quality Check Template History Diff', 'QualityCheckTemplateController@getHistoryDiff', 'qualityCheckTemplate/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%QualityCheckTemplateController@getHistory%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%QualityCheckTemplateController@getHistory%') AND menu_id != 0);

/* Venkatesan 21-March-2020 Footer Icons*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer Icons'), 'Footer Icons  List', 'FooterIconController@index', 'footerIcons/list', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer Icons'), 'Footer Icons Get List', 'FooterIconController@getList', 'footerIcons/list', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer Icons'), 'Footer Icons Create', 'FooterIconController@create', 'footerIcons/create', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer Icons'), 'Footer Icons Store', 'FooterIconController@store', 'footerIcons/store', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer Icons'), 'Footer Icons Edit', 'FooterIconController@edit', 'footerIcons/edit', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer Icons'), 'Footer Icons Update', 'FooterIconController@update', 'footerIcons/update', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer Icons'), 'Footer Icons Delete', 'FooterIconController@delete', 'footerIcons/delete', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer Icons'), 'Footer Icons Change Status', 'FooterIconController@changeStatus', 'footerIcons/changeStatus', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer Icons'), 'Get Footer Icons', 'FooterIconController@getFooterIcons', 'footerIcons/getFooterIcons', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%FooterIconController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%FooterIconController%') AND menu_id != 0);  

/* Venkatesan 22-March-2020 Footer Links and Pages*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer links and pages'), 'Footer links and pages  List', 'FooterLinkController@index', 'footerLinks/list', '', 'Y', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer links and pages'), 'Footer links and pages Get List', 'FooterLinkController@getList', 'footerLinks/list', '', 'N', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer links and pages'), 'Footer links and pages Create', 'FooterLinkController@create', 'footerLinks/create', '', 'N', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer links and pages'), 'Footer links and pages Store', 'FooterLinkController@store', 'footerLinks/store', '', 'N', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer links and pages'), 'Footer links and pages Edit', 'FooterLinkController@edit', 'footerLinks/edit', '', 'N', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer links and pages'), 'Footer links and pages Update', 'FooterLinkController@update', 'footerLinks/update', '', 'N', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer links and pages'), 'Footer links and pages Delete', 'FooterLinkController@delete', 'footerLinks/delete', '', 'N', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer links and pages'), 'Footer links and pages Change Status', 'FooterLinkController@changeStatus', 'footerLinks/changeStatus', '', 'N', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer links and pages'), 'Get Footer links and pages', 'FooterLinkController@getFooterLinks', 'footerLinks/getFooterLinks', '', 'Y', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer links and pages'), 'Footer links and pages Title Select', 'FooterLinkController@footerLinkTitleSelect', 'footerLinks/footerLinkTitleSelect', '', 'Y', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Footer links and pages'), 'Get Footer links and pages Content', 'FooterLinkController@getFooterLinkContent', 'footerLinks/getFooterLinkContent', '', 'Y', 'A', 1, '2020-03-22 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%FooterLinkController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%FooterLinkController%') AND menu_id != 0); 

/* Venkatesan 21-March-2020  PG Transaction */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'PG Transaction'), 'PG Transaction Index', 'PGTransactionController@index', 'pgTransaction/list', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'PG Transaction'), 'PG Transaction List', 'PGTransactionController@list', 'pgTransaction/list', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'PG Transaction'), 'PG Transaction View', 'PGTransactionController@view', 'pgTransaction/view', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PGTransactionController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PGTransactionController%') AND menu_id != 0);  

/*  Venkatesan 21-March-2020 GET Place Info for hotels */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='No Submenu'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Place Details', 'HotelBedsCityManagementController@getPlaceDetails', 'getPlaceDetails', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%HotelBedsCityManagementController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%HotelBedsCityManagementController%') AND menu_id != 0);  

/* Venkatesan 21-March-2020 GET Search Form Data */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='No Submenu'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Search Form Data', 'SearchFormController@getSearchFormData', 'getSearchFormData', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SearchFormController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SearchFormController%') AND menu_id != 0);  

/* Venkatesan 21-March-2020 Manage Agents */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agents'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Manage Agents  List', 'UserManagementController@index', 'manageUsers/list', '', 'Y', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agents'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Manage Agents Get List', 'UserManagementController@getUserList', 'manageUsers/getUserList', '', 'N', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agents'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Manage Agents Create', 'UserManagementController@create', 'manageUsers/create', '', 'N', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agents'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Manage Agents Store', 'UserManagementController@store', 'manageUsers/store', '', 'N', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agents'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Manage Agents Edit', 'UserManagementController@edit', 'manageUsers/edit', '', 'N', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agents'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Manage Agents Update', 'UserManagementController@update', 'manageUsers/update', '', 'N', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agents'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Manage Agents Delete', 'UserManagementController@delete', 'manageUsers/delete', '', 'N', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agents'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Manage Agents Change Status', 'UserManagementController@changeStatus', 'manageUsers/changeStatus', '', 'N', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agents'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Manage Agent New Requests List', 'UserManagementController@newRequests', 'manageUsers/newRequests/list', '', 'Y', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agents'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Manage Agent New Requests List', 'UserManagementController@newRequestsList', 'manageUsers/newRequests/list', '', 'Y', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agents'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Manage Agent New Requests View', 'UserManagementController@newRequestsView', 'manageUsers/newRequestsView', '', 'Y', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agents'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Manage Agent New Requests Approve', 'UserManagementController@newRequestsApprove', 'manageUsers/newRequestsApprove', '', 'Y', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agents'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Manage Agent New Requests Reject', 'UserManagementController@newAgentRequestReject', 'manageUsers/newAgentRequestReject', '', 'Y', 'A', 1, '2020-03-22 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserManagementController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserManagementController%') AND menu_id != 0);  

/* Venkatesan 21-March-2020  Profile Aggregation*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Aggregation Profile'), 'Aggregation Profile Index', 'ProfileAggregationController@index', 'profileAggregation/list', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Aggregation Profile'), 'Aggregation Profile Get List', 'ProfileAggregationController@getList', 'profileAggregation/list', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Aggregation Profile'), 'Aggregation Profile Create', 'ProfileAggregationController@create', 'profileAggregation/create', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Aggregation Profile'), 'Aggregation Profile store', 'ProfileAggregationController@store', 'profileAggregation/store', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Aggregation Profile'), 'Aggregation Profile Edit', 'ProfileAggregationController@edit', 'profileAggregation/edit', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Aggregation Profile'), 'Aggregation Profile Update', 'ProfileAggregationController@update', 'profileAggregation/update', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Aggregation Profile'), 'Aggregation Profile Delete', 'ProfileAggregationController@delete', 'profileAggregation/delete', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Aggregation Profile'), 'Aggregation Profile Change Status', 'ProfileAggregationController@changeStatus', 'profileAggregation/changeStatus', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Aggregation Profile'), 'Get Profile Aggregation Content Source', 'ProfileAggregationController@getProfileAggregationContentSource', 'profileAggregation/getProfileAggregationContentSource', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Aggregation Profile'), 'Get Markup Template', 'ProfileAggregationController@getMarkupTemplate', 'profileAggregation/getMarkupTemplate', '', 'N', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ProfileAggregationController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ProfileAggregationController%') AND menu_id != 0);

/*Karthick march 23rd 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Airport Group', 'AirportGroupController@getAirportGroup', '/getAirportGroup', '', 'Y', 'A', 1, '2020-03-20 00:00:00');


/* Venkatesan 21-March-2020 Get Account Details*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, 0, (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Account Details', 'AccountDetailsController@getAccountDetails', 'getAccountDetails', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AccountDetailsController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AccountDetailsController%') AND menu_id != 0);


/* Venkatesan 21-March-2020 Payment Gateway Config */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Payment Gateway Config'), 'Payment Gateway Config Index', 'PaymentGatewayConfigController@index', 'paymentGatewayConfig/list', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Payment Gateway Config'), 'Payment Gateway Config Get List', 'PaymentGatewayConfigController@getList', 'paymentGatewayConfig/list', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Payment Gateway Config'), 'Payment Gateway Config Create', 'PaymentGatewayConfigController@create', 'paymentGatewayConfig/create', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Payment Gateway Config'), 'Payment Gateway Config store', 'PaymentGatewayConfigController@store', 'paymentGatewayConfig/store', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Payment Gateway Config'), 'Payment Gateway Config Edit', 'PaymentGatewayConfigController@edit', 'paymentGatewayConfig/edit', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Payment Gateway Config'), 'Payment Gateway Config Edit', 'PaymentGatewayConfigController@edit', 'paymentGatewayConfig/copy', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Payment Gateway Config'), 'Payment Gateway Config Update', 'PaymentGatewayConfigController@update', 'paymentGatewayConfig/update', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Payment Gateway Config'), 'Payment Gateway Config Delete', 'PaymentGatewayConfigController@delete', 'paymentGatewayConfig/delete', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Payment Gateway Config'), 'Payment Gateway Config Change Status', 'PaymentGatewayConfigController@changeStatus', 'paymentGatewayConfig/changeStatus', '', 'N', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PaymentGatewayConfigController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PaymentGatewayConfigController%') AND menu_id != 0);  


/* Venkatesan 23-March-2020  Portal Route Blocking Template*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Portal Route Blocking Templates Index', 'PortalRouteBlockingTemplatesController@index', 'portalRouteBlockingTemplates/list', '', 'Y', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Portal Route Blocking Templates Get List', 'PortalRouteBlockingTemplatesController@getList', 'portalRouteBlockingTemplates/list', '', 'Y', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Portal Route Blocking Templates Create', 'PortalRouteBlockingTemplatesController@create', 'portalRouteBlockingTemplates/create', '', 'N', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Portal Route Blocking Templates store', 'PortalRouteBlockingTemplatesController@store', 'portalRouteBlockingTemplates/store', '', 'N', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Portal Route Blocking Templates Edit', 'PortalRouteBlockingTemplatesController@edit', 'portalRouteBlockingTemplates/edit', '', 'N', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Portal Route Blocking Templates Update', 'PortalRouteBlockingTemplatesController@update', 'portalRouteBlockingTemplates/update', '', 'N', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Portal Route Blocking Templates Delete', 'PortalRouteBlockingTemplatesController@delete', 'portalRouteBlockingTemplates/delete', '', 'N', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Portal Route Blocking Templates ChangeStatus', 'PortalRouteBlockingTemplatesController@changeStatus', 'portalRouteBlockingTemplates/changeStatus', '', 'N', 'A', 1, '2020-03-23 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalRouteBlockingTemplatesController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalRouteBlockingTemplatesController%') AND menu_id != 0);


/* Divakar 23 - March - 2020 User Role */

ALTER TABLE `user_roles` ADD `updated_by` INT NULL DEFAULT '0' AFTER `created_at`, ADD `updated_at` DATETIME NULL AFTER `updated_by`;

/*Karthick march 23rd 2020*/

DELETE FROM `permissions` WHERE permission_route like '%ContentSourceController%' AND 'permission_url' != NULL;

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management index', 'ContentSourceController@index', 'contentSource/index', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management list', 'ContentSourceController@list', 'contentSource/list', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management create', 'ContentSourceController@create', 'contentSource/create', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management store', 'ContentSourceController@store', 'contentSource/store', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management edit', 'ContentSourceController@edit', 'contentSource/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management copy', 'ContentSourceController@edit', 'contentSource/copy', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management update', 'ContentSourceController@update', 'contentSource/update', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management changeStatus', 'ContentSourceController@changeStatus', 'contentSource/changeStatus', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management delete', 'ContentSourceController@delete', 'contentSource/delete', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management getContentSourceRefKey', 'ContentSourceController@getContentSourceRefKey', 'contentSource/getContentSourceRefKey', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management checkAAAtoPCCcsrefkeyExist', 'ContentSourceController@checkAAAtoPCCcsrefkeyExist', 'contentSource/checkAAAtoPCCcsrefkeyExist', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContentSourceController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContentSourceController%') AND menu_id != 0);  

/* Seenivasan 23-Mar- 2020 Supplier Surcharge*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Surcharge' and new_link='supplierSurcharge'), 'Supplier Surcharge History Diff', 'SupplierSurchargeController@getHistoryDiff', 'supplierSurcharge/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Surcharge' and new_link='supplierSurcharge'), 'Supplier Surcharge History', 'SupplierSurchargeController@getHistory', 'supplierSurcharge/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Surcharge' and new_link='supplierSurcharge'), 'Supplier Surcharge Update', 'SupplierSurchargeController@update', 'supplierSurcharge/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Surcharge' and new_link='supplierSurcharge'), 'Supplier Surcharge ChangeStatus', 'SupplierSurchargeController@changeStatus', 'supplierSurcharge/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Surcharge' and new_link='supplierSurcharge'), 'Supplier Surcharge Delete', 'SupplierSurchargeController@delete', 'supplierSurcharge/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Surcharge' and new_link='supplierSurcharge'), 'Supplier Surcharge Edit', 'SupplierSurchargeController@edit', 'supplierSurcharge/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Surcharge' and new_link='supplierSurcharge'), 'Supplier Surcharge Save', 'SupplierSurchargeController@store', 'supplierSurcharge/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Surcharge' and new_link='supplierSurcharge'), 'Supplier Surcharge Create', 'SupplierSurchargeController@create', 'supplierSurcharge/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Surcharge' and new_link='supplierSurcharge'), 'Supplier Surcharge List', 'SupplierSurchargeController@list', 'supplierSurcharge/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Surcharge' and new_link='supplierSurcharge'), 'Supplier Surcharge Index', 'SupplierSurchargeController@index', 'supplierSurcharge/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierSurchargeController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierSurchargeController%') AND menu_id != 0);

/* Karthick 23rd march 20202 Markup Tempalte  Permission*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'Markup Contract index', 'MarkupContractController@index', 'markupContract/index', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'Markup Contract list', 'MarkupContractController@list', 'markupContract/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'Markup Contract create', 'MarkupContractController@create', 'markupContract/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'Markup Contract storeContract', 'MarkupContractController@storeContract', 'markupContract/storeContract', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'Markup Contract storeRules', 'MarkupContractController@storeRules', 'markupContract/storeRules', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'Markup Contract updateContract', 'MarkupContractController@updateContract', 'markupContract/updateContract', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'Markup Contract updateRules', 'MarkupContractController@updateRules', 'markupContract/updateRules', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'Markup Contract editContract', 'MarkupContractController@editContract', 'markupContract/editContract', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'Markup Contract editRules', 'MarkupContractController@editRules', 'markupContract/editRules', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'Markup Contract conctractChangeStatus', 'MarkupContractController@conctractChangeStatus', 'markupContract/conctractChangeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'Markup Contract conctractDelete', 'MarkupContractController@conctractDelete', 'markupContract/conctractDelete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'Markup Contract ruleChangeStatus', 'MarkupContractController@ruleChangeStatus', 'markupContract/ruleChangeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'Markup Contract ruleDelete', 'MarkupContractController@ruleDelete', 'markupContract/ruleDelete', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MarkupContractController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MarkupContractController%') AND menu_id != 0);

/* Venkatesan 23-March-2020  Portal Route Blocking Rules*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Portal Route Blocking Rules Index', 'PortalRouteBlockingRulesController@index', 'portalRouteBlockingRules/list', '', 'Y', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Portal Route Blocking Rules Get List', 'PortalRouteBlockingRulesController@getList', 'portalRouteBlockingRules/list', '', 'Y', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Portal Route Blocking Rules Create', 'PortalRouteBlockingRulesController@create', 'portalRouteBlockingRules/create', '', 'N', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Portal Route Blocking Rules store', 'PortalRouteBlockingRulesController@store', 'portalRouteBlockingRules/store', '', 'N', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Portal Route Blocking Rules Edit', 'PortalRouteBlockingRulesController@edit', 'portalRouteBlockingRules/edit', '', 'N', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Portal Route Blocking Rules Update', 'PortalRouteBlockingRulesController@update', 'portalRouteBlockingRules/update', '', 'N', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Portal Route Blocking Rules Delete', 'PortalRouteBlockingRulesController@delete', 'portalRouteBlockingRules/delete', '', 'N', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Portal Route Blocking Rules ChangeStatus', 'PortalRouteBlockingRulesController@changeStatus', 'portalRouteBlockingRules/changeStatus', '', 'N', 'A', 1, '2020-03-23 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalRouteBlockingRulesController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalRouteBlockingRulesController%') AND menu_id != 0);

/* Venkatesan 23-March-2020  Supplier Route Blocking Template*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Suppliers Route Blocking Template Index', 'SupplierRouteBlockingTemplatesController@index', 'supplierRouteBlockingTemplates/list', '', 'Y', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Suppliers Route Blocking Template Get List', 'SupplierRouteBlockingTemplatesController@getList', 'supplierRouteBlockingTemplates/list', '', 'Y', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Suppliers Route Blocking Template Create', 'SupplierRouteBlockingTemplatesController@create', 'supplierRouteBlockingTemplates/create', '', 'N', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Suppliers Route Blocking Template store', 'SupplierRouteBlockingTemplatesController@store', 'supplierRouteBlockingTemplates/store', '', 'N', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Suppliers Route Blocking Template Edit', 'SupplierRouteBlockingTemplatesController@edit', 'supplierRouteBlockingTemplates/edit', '', 'N', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Suppliers Route Blocking Template Update', 'SupplierRouteBlockingTemplatesController@update', 'supplierRouteBlockingTemplates/update', '', 'N', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Suppliers Route Blocking Template Delete', 'SupplierRouteBlockingTemplatesController@delete', 'supplierRouteBlockingTemplates/delete', '', 'N', 'A', 1, '2020-03-23 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Suppliers Route Blocking Template ChangeStatus', 'SupplierRouteBlockingTemplatesController@changeStatus', 'supplierRouteBlockingTemplates/changeStatus', '', 'N', 'A', 1, '2020-03-23 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierRouteBlockingTemplatesController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierRouteBlockingTemplatesController%') AND menu_id != 0);

/* Venkatesan 24-March-2020  Supplier Route Blocking Rules*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Suppliers Route Blocking Rules Index', 'SupplierRouteBlockingRulesController@index', 'supplierRouteBlockingRules/list', '', 'Y', 'A', 1, '2020-03-24 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Suppliers Route Blocking Rules Get List', 'SupplierRouteBlockingRulesController@getList', 'supplierRouteBlockingRules/list', '', 'Y', 'A', 1, '2020-03-24 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Suppliers Route Blocking Rules Create', 'SupplierRouteBlockingRulesController@create', 'supplierRouteBlockingRules/create', '', 'N', 'A', 1, '2020-03-24 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Suppliers Route Blocking Rules store', 'SupplierRouteBlockingRulesController@store', 'supplierRouteBlockingRules/store', '', 'N', 'A', 1, '2020-03-24 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Suppliers Route Blocking Rules Edit', 'SupplierRouteBlockingRulesController@edit', 'supplierRouteBlockingRules/edit', '', 'N', 'A', 1, '2020-03-24 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Suppliers Route Blocking Rules Update', 'SupplierRouteBlockingRulesController@update', 'supplierRouteBlockingRules/update', '', 'N', 'A', 1, '2020-03-24 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Suppliers Route Blocking Rules Delete', 'SupplierRouteBlockingRulesController@delete', 'supplierRouteBlockingRules/delete', '', 'N', 'A', 1, '2020-03-24 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Suppliers Route Blocking Rules ChangeStatus', 'SupplierRouteBlockingRulesController@changeStatus', 'supplierRouteBlockingRules/changeStatus', '', 'N', 'A', 1, '2020-03-24 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierRouteBlockingRulesController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierRouteBlockingRulesController%') AND menu_id != 0);

/*Seenivasan 24- Mar -2020 Event Subscription*/

-- ALTER TABLE `events` ADD `event_url` VARCHAR(255) NOT NULL AFTER `event_name`;
INSERT INTO `menu_details` (`menu_id`, `menu_name`, `new_link`, `icon`, `menu_type`, `status`) VALUES (NULL, 'Events Menu', 'event', 'event', 'A', 'Y');

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'Events Menu'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'No Submenu'), '0','21', '1', '1', 'Y', 'Y');

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'Events Menu'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Events'), '0','1', '1', '1', 'Y', 'Y');

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'Events Menu'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Event Subscription'),'0', '2', '1', '1', 'Y', 'Y');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Events Menu'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Events'), 'Events Update', 'EventController@update', 'event/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Events Menu'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Events'), 'Events ChangeStatus', 'EventController@changeStatus', 'event/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Events Menu'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Events'), 'Events Delete', 'EventController@delete', 'event/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Events Menu'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Events'), 'Events Edit', 'EventController@edit', 'event/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Events Menu'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Events'), 'Events Save', 'EventController@store', 'event/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Events Menu'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Events'), 'Events Create', 'EventController@create', 'event/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Events Menu'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Events'), 'Events List', 'EventController@list', 'event/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Events Menu'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Events'), 'Events Index', 'EventController@index', 'event/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%EventController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%EventController%') AND menu_id != 0);


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Events Menu'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Event Subscription'), 'Event Subscription ChangeStatus', 'EventSubscriptionController@changeStatus', 'eventSubscription/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Events Menu'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Event Subscription'), 'Event Subscription Delete', 'EventSubscriptionController@delete', 'eventSubscription/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Events Menu'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Event Subscription'), 'Event Subscription List', 'EventSubscriptionController@list', 'eventSubscription/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Events Menu'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Event Subscription'), 'Event Subscription Index', 'EventSubscriptionController@index', 'eventSubscription/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%EventSubscriptionController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%EventSubscriptionController%') AND menu_id != 0);

/*Karthick 24th march 2020 */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES

(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Look to Book Ratio'), 'Look to Book Ratio getHistory', 'LookToBookRatioController@getHistory', 'lookToBookRatio/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Look to Book Ratio'), 'Look to Book Ratio getHistoryDiff', 'LookToBookRatioController@getHistoryDiff', 'lookToBookRatio/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%LookToBookRatioController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%LookToBookRatioController@getHistory%') AND menu_id != 0);


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'Markup Contract getSupplierPosRuleList', 'MarkupContractController@getSupplierPosRuleList', 'markupContract/getSupplierPosRuleList', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'Markup Contract copyRules', 'MarkupContractController@copyRules', 'markupContract/copyRules', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MarkupContractController@getSupplierPosRuleList%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MarkupContractController@copyRules%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MarkupContractController@getSupplierPosRuleList%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MarkupContractController@copyRules%') AND menu_id != 0);


/* Venkatesan 24-March-2020 Supplier Low Fare Template*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier LowFare Template'), 'Supplier Low Fare Template Index', 'SupplierLowfareTemplateController@index', 'supplierLowfareTemplate/list', '', 'Y', 'A', 1, '2020-03-24 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier LowFare Template'), 'Supplier Low Fare Template Get List', 'SupplierLowfareTemplateController@getList', 'supplierLowfareTemplate/list', '', 'Y', 'A', 1, '2020-03-24 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier LowFare Template'), 'Supplier Low Fare Template Create', 'SupplierLowfareTemplateController@create', 'supplierLowfareTemplate/create', '', 'N', 'A', 1, '2020-03-24 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier LowFare Template'), 'Supplier Low Fare Template store', 'SupplierLowfareTemplateController@store', 'supplierLowfareTemplate/store', '', 'N', 'A', 1, '2020-03-24 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier LowFare Template'), 'Supplier Low Fare Template Edit', 'SupplierLowfareTemplateController@edit', 'supplierLowfareTemplate/edit', '', 'N', 'A', 1, '2020-03-24 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier LowFare Template'), 'Supplier Low Fare Template Update', 'SupplierLowfareTemplateController@update', 'supplierLowfareTemplate/update', '', 'N', 'A', 1, '2020-03-24 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier LowFare Template'), 'Supplier Low Fare Template Delete', 'SupplierLowfareTemplateController@delete', 'supplierLowfareTemplate/delete', '', 'N', 'A', 1, '2020-03-24 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier LowFare Template'), 'Supplier Low Fare Template ChangeStatus', 'SupplierLowfareTemplateController@changeStatus', 'supplierLowfareTemplate/changeStatus', '', 'N', 'A', 1, '2020-03-24 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier LowFare Template'), 'Get Supplier List', 'SupplierLowfareTemplateController@getSupplierList', 'getSupplierList', '', 'Y', 'A', 1, '2020-03-24 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier LowFare Template'), 'Get Content Source PCC', 'SupplierLowfareTemplateController@getContentSourcePCC', 'getContentSourcePCC', '', 'Y', 'A', 1, '2020-03-24 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierLowfareTemplateController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierLowfareTemplateController%') AND menu_id != 0);

/*Karthick 24th march 2020 8 pm*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Queue'), 'Ticketing Queue List', 'TicketingQueueController@list', 'ticketingQueue/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Queue'), 'Ticketing Queue Index', 'TicketingQueueController@index', 'ticketingQueue/index', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%TicketingQueueController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%TicketingQueueController%') AND menu_id != 0);

/*Karthick 25th March 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Queue'), 'Ticketing Queue view', 'TicketingQueueController@view', 'ticketingQueue/view', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%TicketingQueueController@view%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%TicketingQueueController@view%') AND menu_id != 0);

/* Venkatesan 25-March-2020  Profile Aggregation*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Aggregation Profile'), 'Get History', 'ProfileAggregationController@getHistory', 'profileAggregation/getHistory', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Aggregation Profile'), 'Get History Diff  ', 'ProfileAggregationController@getHistoryDiff', 'profileAggregation/getHistoryDiff', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ProfileAggregationController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ProfileAggregationController@getHistory%') AND menu_id != 0);

/* Venkatesan 25-March-2020 Portal Details */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get History', 'PortalDetailsController@getHistory', 'portalDetails/getHistory', '', 'Y', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get History Diff', 'PortalDetailsController@getHistoryDiff', 'portalDetails/getHistoryDiff', '', 'Y', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalDetailsController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalDetailsController@getHistory%') AND menu_id != 0);

/* Venkatesan 25-March-2020 Portal Credentials */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get History', 'PortalCredentialsController@getHistory', 'portalCredentials/getHistory', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get History Diff', 'PortalCredentialsController@getHistoryDiff', 'portalCredentials/getHistoryDiff', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalCredentialsController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalCredentialsController@getHistory%') AND menu_id != 0);

/* Venkatesan 25-March-2020 Meta Portal */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get History', 'MetaPortalController@getHistory', 'metaPortal/getHistory', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get History Diff', 'MetaPortalController@getHistoryDiff', 'metaPortal/getHistoryDiff', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MetaPortalController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MetaPortalController@getHistory%') AND menu_id != 0);

/* Venkatesan 25-March-2020 Payment Gateway Config */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Payment Gateway Config'), 'Get History', 'PaymentGatewayConfigController@getHistory', 'paymentGatewayConfig/getHistory', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Payment Gateway Config'), 'Get History Diff', 'PaymentGatewayConfigController@getHistoryDiff', 'paymentGatewayConfig/getHistoryDiff', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PaymentGatewayConfigController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PaymentGatewayConfigController@getHistory%') AND menu_id != 0);  

/*Karthick 25th March 2020 4pm*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Queue'), 'Ticketing Queue removeFromQueueList', 'TicketingQueueController@removeFromQueueList', 'ticketingQueue/removeFromQueueList', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Queue'), 'Ticketing Queue manualReview', 'TicketingQueueController@manualReview', 'ticketingQueue/manualReview', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Queue'), 'Ticketing Queue manualReviewStore', 'TicketingQueueController@manualReviewStore', 'ticketingQueue/manualReviewStore', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%TicketingQueueController@removeFromQueueList%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%TicketingQueueController@manualReview%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%TicketingQueueController@manualReviewStore%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%TicketingQueueController@removeFromQueueList%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%TicketingQueueController@manualReview%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%TicketingQueueController@manualReviewStore%') AND menu_id != 0);

/*Seenivasan 24- Mar -2020 User Referals*/

INSERT INTO `menu_details` (`menu_id`, `menu_name`, `new_link`, `icon`, `menu_type`, `status`) VALUES (NULL, 'User Referral', 'userReferral', 'insert_link', 'A', 'Y');

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'User Referral'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'No Submenu'), '0','22', '1', '1', 'Y', 'Y');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Referral'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Referral Delete', 'UserReferralController@delete', 'userReferral/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Referral'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Referral Save', 'UserReferralController@store', 'userReferral/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Referral'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Referral Create', 'UserReferralController@create', 'userReferral/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Referral'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Referral List', 'UserReferralController@list', 'userReferral/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Referral'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Referral Index', 'UserReferralController@index', 'userReferral/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserReferralController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserReferralController%') AND menu_id != 0);

/* Seenivasan 25-Mar- 2020 Route Page Settings */

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'Route Pages'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Route Page Settings'), '0','2', '1', '1', 'Y', 'Y');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Page Settings'), 'Route Page Settings History Diff', 'RoutePageSettingsController@getHistoryDiff', 'routePageSettings/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Page Settings'), 'Route Page Settings History', 'RoutePageSettingsController@getHistory', 'routePageSettings/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Page Settings'), 'Route Page Settings Update', 'RoutePageSettingsController@update', 'routePageSettings/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Page Settings'), 'Route Page Settings ChangeStatus', 'RoutePageSettingsController@changeStatus', 'routePageSettings/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Page Settings'), 'Route Page Settings Delete', 'RoutePageSettingsController@delete', 'routePageSettings/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Page Settings'), 'Route Page Settings Edit', 'RoutePageSettingsController@edit', 'routePageSettings/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Page Settings'), 'Route Page Settings Save', 'RoutePageSettingsController@store', 'routePageSettings/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Page Settings'), 'Route Page Settings Create', 'RoutePageSettingsController@create', 'routePageSettings/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Page Settings'), 'Route Page Settings List', 'RoutePageSettingsController@list', 'routePageSettings/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Page Settings'), 'Route Page Settings Index', 'RoutePageSettingsController@index', 'routePageSettings/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RoutePageSettingsController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RoutePageSettingsController%') AND menu_id != 0);

/* Seenivasan 25-Mar- 2020 Currency Exchange Rate Histroy*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Currency Exchange Rate'), 'Currency ExchangeRate History', 'CurrencyExchangeRateController@getHistory', 'currencyExchangeRate/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Currency Exchange Rate'), 'Currency ExchangeRate History Diff', 'CurrencyExchangeRateController@getHistoryDiff', 'currencyExchangeRate/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%CurrencyExchangeRateController@getHistory%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%CurrencyExchangeRateController@getHistory%') AND menu_id != 0);

/* Seenivasan 25-Mar- 2020 Customer Management History*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Customer Management'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Customer Management History', 'CustomerManagementController@getHistory', 'manageCustomers/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Customer Management'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Customer Management History Diff', 'CustomerManagementController@getHistoryDiff', 'manageCustomers/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%CustomerManagementController@getHistory%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%CustomerManagementController@getHistory%') AND menu_id != 0);

/* Venkatesan 25-March-2020  Portal Airline Blocking Template*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Get History', 'PortalAirlineBlockingTemplatesController@getHistory', 'portalAirlineBlockingTemplate/getHistory', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Get History Diff', 'PortalAirlineBlockingTemplatesController@getHistoryDiff', 'portalAirlineBlockingTemplate/getHistoryDiff', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalAirlineBlockingTemplatesController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalAirlineBlockingTemplatesController@getHistory%') AND menu_id != 0);

/* Venkatesan 25-March-2020  Portal Airline Blocking Rules*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Get History', 'PortalAirlineBlockingRulesController@getHistory', 'portalAirlineBlockingRules/getHistory', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link = 'portalAirlineBlockingTemplates'), 'Get History Diff', 'PortalAirlineBlockingRulesController@getHistoryDiff', 'portalAirlineBlockingRules/getHistoryDiff', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalAirlineBlockingRulesController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalAirlineBlockingRulesController@getHistory%') AND menu_id != 0);

/* Venkatesan 25-March-2020  Portal Route Blocking Template*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Get History', 'PortalRouteBlockingTemplatesController@getHistory', 'portalRouteBlockingTemplates/getHistory', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Get History Diff', 'PortalRouteBlockingTemplatesController@getHistoryDiff', 'portalRouteBlockingTemplates/getHistoryDiff', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalRouteBlockingTemplatesController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalRouteBlockingTemplatesController@getHistory%') AND menu_id != 0);

/* Venkatesan 25-March-2020  Portal Route Blocking Rules*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Get History', 'PortalRouteBlockingRulesController@getHistory', 'portalRouteBlockingRules/getHistory', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'portalRouteBlockingTemplates'), 'Get History Diff', 'PortalRouteBlockingRulesController@getHistoryDiff', 'portalRouteBlockingRules/getHistoryDiff', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalRouteBlockingRulesController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalRouteBlockingRulesController@getHistory%') AND menu_id != 0);

/* Venkatesan 25-March-2020  Supplier Route Blocking Template*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Get History', 'SupplierRouteBlockingTemplatesController@getHistory', 'supplierRouteBlockingTemplates/getHistory', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Get History Diff', 'SupplierRouteBlockingTemplatesController@getHistoryDiff', 'supplierRouteBlockingTemplates/getHistoryDiff', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierRouteBlockingTemplatesController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierRouteBlockingTemplatesController@getHistory%') AND menu_id != 0);

/* Venkatesan 25-March-2020  Supplier Route Blocking Rules*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Get History', 'SupplierRouteBlockingRulesController@getHistory', 'supplierRouteBlockingRules/getHistory', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Blocking' AND link = 'supplierRouteBlockingTemplates'), 'Get History Diff', 'SupplierRouteBlockingRulesController@getHistoryDiff', 'supplierRouteBlockingRules/getHistoryDiff', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierRouteBlockingRulesController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierRouteBlockingRulesController@getHistory%') AND menu_id != 0);


/* Venkatesan 25-March-2020  Portal Airline Masking Template*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Get History', 'PortalAirlineMaskingTemplatesController@getHistory', 'portalAirlineMaskingTemplates/getHistory', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Get History Diff', 'PortalAirlineMaskingTemplatesController@getHistoryDiff', 'portalAirlineMaskingTemplates/getHistoryDiff', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalAirlineMaskingTemplatesController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalAirlineMaskingTemplatesController@getHistory%') AND menu_id != 0);

/* Venkatesan 25-March-2020  Portal Airline Masking Rules*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Get History', 'PortalAirlineMaskingRulesController@getHistory', 'portalAirlineMaskingRules/getHistory', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'portalAirlineMaskingTemplates'), 'Get History Diff', 'PortalAirlineMaskingRulesController@getHistoryDiff', 'portalAirlineMaskingRules/getHistoryDiff', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalAirlineMaskingRulesController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalAirlineMaskingRulesController@getHistory%') AND menu_id != 0);
 
 /* Venkatesan 25-March-2020  Form of payment*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Form of payment'), 'Get History', 'FormOfPaymentController@getHistory', 'formOfPayment/getHistory', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Form of payment'), 'Get History Diff', 'FormOfPaymentController@getHistoryDiff', 'formOfPayment/getHistoryDiff', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%FormOfPaymentController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%FormOfPaymentController@getHistory%') AND menu_id != 0);  

/* Venkatesan 25-March-2020  Sector Mapping*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Sector Mapping'), 'Get History', 'SectorMappingController@getHistory', 'sectorMapping/getHistory', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Sector Mapping'), 'Get History Diff', 'SectorMappingController@getHistoryDiff', 'sectorMapping/getHistoryDiff', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SectorMappingController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SectorMappingController@getHistory%') AND menu_id != 0);  

/* Venkatesan 24-March-2020 Supplier Low Fare Template*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier LowFare Template'), 'Get History', 'SupplierLowfareTemplateController@getHistory', 'supplierLowfareTemplate/getHistory', '', 'Y', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier LowFare Template'), 'Get History Diff', 'SupplierLowfareTemplateController@getHistoryDiff', 'supplierLowfareTemplate/getHistoryDiff', '', 'Y', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierLowfareTemplateController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierLowfareTemplateController@getHistory%') AND menu_id != 0);

/* Venkatesan 25-March-2020 Ticketing Rules*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Rules'), 'Ticketing Rules Index', 'TicketingRulesController@index', 'ticketingRules/list', '', 'Y', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Rules'), 'Ticketing Rules Get List', 'TicketingRulesController@getList', 'ticketingRules/list', '', 'Y', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Rules'), 'Ticketing Rules Create', 'TicketingRulesController@create', 'ticketingRules/create', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Rules'), 'Ticketing Rules store', 'TicketingRulesController@store', 'ticketingRules/store', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Rules'), 'Ticketing Rules Edit', 'TicketingRulesController@edit', 'ticketingRules/edit', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Rules'), 'Ticketing Rules Update', 'TicketingRulesController@update', 'ticketingRules/update', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Rules'), 'Ticketing Rules Delete', 'TicketingRulesController@delete', 'ticketingRules/delete', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Rules'), 'Ticketing Rules ChangeStatus', 'TicketingRulesController@changeStatus', 'ticketingRules/changeStatus', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Rules'), 'Get History', 'TicketingRulesController@getHistory', 'ticketingRules/getHistory', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Rules'), 'Get History Diff', 'TicketingRulesController@getHistoryDiff', 'ticketingRules/getHistoryDiff', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Rules'), 'Get Template Details', 'TicketingRulesController@getTemplateDetails', 'getTemplateDetails', '', 'Y', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%TicketingRulesController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%TicketingRulesController%') AND menu_id != 0);

/* Venkatesan 21-March-2020 Manage Agents */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agents'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get History', 'UserManagementController@getHistory', 'manageUsers/getHistory', '', 'Y', 'A', 1, '2020-03-22 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agents'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get History Diff', 'UserManagementController@getHistoryDiff', 'manageUsers/getHistoryDiff', '', 'N', 'A', 1, '2020-03-22 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserManagementController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserManagementController@getHistory%') AND menu_id != 0);  

/* Venkatesan 25-March-2020 Route config Log List */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Log'), 'Route Config Log List', 'RouteConfigManagementController@routeConfigLogList', 'routeConfig/routeConfigLogList', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RouteConfigManagementController@routeConfigLogList%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RouteConfigManagementController@routeConfigLogList%') AND menu_id != 0);

/* Seenivasan 26-Mar- 2020 Popular Cities */

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'Route Pages'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Popular Cities'), '0','3', '1', '1', 'Y', 'Y');


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Cities'), 'Popular Cities City', 'PopularCitiesController@getCountryBasedCities', 'getCountryBasedCities', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Cities'), 'Popular Cities Portal', 'PopularCitiesController@getCountryBasedPortal', 'getCountryBasedPortal', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Cities'), 'Popular Cities Update', 'PopularCitiesController@update', 'popularCities/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Cities'), 'Popular Cities ChangeStatus', 'PopularCitiesController@changeStatus', 'popularCities/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Cities'), 'Popular Cities Delete', 'PopularCitiesController@delete', 'popularCities/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Cities'), 'Popular Cities Edit', 'PopularCitiesController@edit', 'popularCities/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Cities'), 'Popular Cities Save', 'PopularCitiesController@store', 'popularCities/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Cities'), 'Popular Cities Create', 'PopularCitiesController@create', 'popularCities/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Cities'), 'Popular Cities List', 'PopularCitiesController@list', 'popularCities/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Popular Cities'), 'Popular Cities Index', 'PopularCitiesController@index', 'popularCities/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PopularCitiesController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PopularCitiesController%') AND menu_id != 0);


/* Seenivasan 26-Mar- 2020 Route Url Generator */

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'Route Pages'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Route Url Generator'), '0','4', '1', '1', 'Y', 'Y');


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Url Generator'), 'Route Url Generator Update', 'RouteUrlGeneratorController@update', 'routeUrlGenerator/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Url Generator'), 'Route Url Generator ChangeStatus', 'RouteUrlGeneratorController@changeStatus', 'routeUrlGenerator/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Url Generator'), 'Route Url Generator Delete', 'RouteUrlGeneratorController@delete', 'routeUrlGenerator/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Url Generator'), 'Route Url Generator Edit', 'RouteUrlGeneratorController@edit', 'routeUrlGenerator/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Url Generator'), 'Route Url Generator Save', 'RouteUrlGeneratorController@store', 'routeUrlGenerator/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Url Generator'), 'Route Url Generator Create', 'RouteUrlGeneratorController@create', 'routeUrlGenerator/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Url Generator'), 'Route Url Generator List', 'RouteUrlGeneratorController@list', 'routeUrlGenerator/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Url Generator'), 'Route Url Generator Index', 'RouteUrlGeneratorController@index', 'routeUrlGenerator/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RouteUrlGeneratorController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RouteUrlGeneratorController%') AND menu_id != 0);

/* Divakar 26-march-2020 Permision Related Changes */

ALTER TABLE `permissions` ADD `permission_group` VARCHAR(255) NULL DEFAULT NULL AFTER `submenu_id`;


/*Karthick 26th March 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management getHistory', 'AgencyManageController@getHistory', 'manageAgency/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management getHistoryDiff', 'AgencyManageController@getHistoryDiff', 'manageAgency/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyManageController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyManageController@getHistory%') AND menu_id != 0);

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management getHistory', 'AgencyCreditManagementController@getHistory', 'agencyCredit/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management getHistoryDiff', 'AgencyCreditManagementController@getHistoryDiff', 'agencyCredit/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyCreditManagementController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyCreditManagementController@getHistory%') AND menu_id != 0);

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'A Management getHistory', 'SupplierAirlineMaskingTemplatesController@getHistory', 'supplierAirlineMaskingTemplates/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'A Management getHistoryDiff', 'SupplierAirlineMaskingTemplatesController@getHistoryDiff', 'supplierAirlineMaskingTemplates/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineMaskingTemplatesController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineMaskingTemplatesController@getHistory%') AND menu_id != 0);

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules getHistory', 'SupplierAirlineMaskingRulesController@getHistory', 'supplierAirlineMaskingRules/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules getHistoryDiff', 'SupplierAirlineMaskingRulesController@getHistoryDiff', 'supplierAirlineMaskingRules/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineMaskingRulesController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineMaskingRulesController@getHistory%') AND menu_id != 0);

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Config getHistory', 'PortalConfigController@getHistory', 'portalConfig/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Config getHistoryDiff', 'PortalConfigController@getHistoryDiff', 'portalConfig/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalConfigController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalConfigController@getHistory%') AND menu_id != 0);

-- INSERT INTO `submenu_details` (`submenu_id`, `submenu_name`, `link`, `icon`, `sub_menu_type`, `status`) VALUES (NULL, 'Portal Config', 'portalConfig', 'create', 'A', 'Y');

-- INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES (NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA') , (SELECT `menu_id` from `menu_details` where `menu_name` = 'Settings'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Portal Config'), '0', '2', '2', '2', 'Y', 'Y');
-- INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES (NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO') , (SELECT `menu_id` from `menu_details` where `menu_name` = 'Settings'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Portal Config'), '0', '2', '2', '2', 'Y', 'Y');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract getHistory', 'ContractManagementController@getHistory', 'contract/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract getHistoryDiff', 'ContractManagementController@getHistoryDiff', 'contract/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContractManagementController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContractManagementController@getHistory%') AND menu_id != 0);

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates getHistory', 'MarkupTemplateController@getHistory', 'markupTemplate/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates getHistoryDiff', 'MarkupTemplateController@getHistoryDiff', 'markupTemplate/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MarkupTemplateController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MarkupTemplateController@getHistory%') AND menu_id != 0);

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Contract getHistory', 'MarkupContractController@getHistory', 'markupContract/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Contract getHistoryDiff', 'MarkupContractController@getHistoryDiff', 'markupContract/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MarkupTemplateController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MarkupTemplateController@getHistory%') AND menu_id != 0);


/* Venkatesan 26 - March - 2020 Hotel List Permission */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel'), 'Hotel Booking  List', 'HotelBookingsController@index', 'bookings/hotelBookingList', '', 'N', 'A', 1, '2020-03-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel'), 'Hotel Booking  List', 'HotelBookingsController@hotelBookingList', 'bookings/hotelBookingList', '', 'N', 'A', 1, '2020-03-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel'), 'Hotel Booking  List', 'HotelBookingsController@hotelBookingView', 'bookings/hotelBookingView', '', 'N', 'A', 1, '2020-03-26 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%HotelBookingsController@index%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%HotelBookingsController@index%') AND menu_id != 0);  

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%HotelBookingsController@hotelBookingList%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%HotelBookingsController@hotelBookingList%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%HotelBookingsController@hotelBookingView%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%HotelBookingsController@hotelBookingView%') AND menu_id != 0);

/* Divakar 26-march-2020 Permision Related Changes */
ALTER TABLE `user_roles` CHANGE `account_id` `account_id` TINYTEXT NULL DEFAULT NULL;

/*Karthick 26th March 2020 - 8 pm Offline Payment*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Extra Payment'), 'Offline Payment index', 'OfflinePaymentController@index', 'offlinePayment/index', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Extra Payment'), 'Offline Payment list', 'OfflinePaymentController@list', 'offlinePayment/list', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Extra Payment'), 'Offline Payment view', 'OfflinePaymentController@view', 'offlinePayment/view', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Extra Payment'), 'Offline Payment delete', 'OfflinePaymentController@delete', 'offlinePayment/delete', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%OfflinePaymentController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%OfflinePaymentController%') AND menu_id != 0);

/*Karthick 27th march 2020 9am*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Hotel Beds country', 'CommonController@getHotelbedsCountryList', 'getHotelbedsCountryList', '', 'Y', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Hotel Beds state', 'CommonController@getHotelbedsStateList', 'getHotelbedsStateList', '', 'Y', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Hotel Beds state', 'CommonController@getHotelbedsStateList', 'getHotelbedsStateList', '', 'Y', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'criterias form data', 'CommonController@getCriteriasFormData', 'getCriteriasFormData', '', 'Y', 'A', 1, '2020-03-25 00:00:00');

/* Venkatesan 26-March-2020 Get Pnr Form */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Import PNR'), 'Get PNR Form', 'GetPnrController@getPnrForm', '/getPnrForm', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Import PNR'), 'Get PNR Supplier Info', 'GetPnrController@getPnrSupplierInfo', '/getPnrSupplierInfo', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%GetPnrController@getPnr%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%GetPnrController@getPnr%') AND menu_id != 0);

/* Venkatesan 26-March-2020 Get Pnr Log */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Import PNR'), 'Get PNR Log List', 'ImportPnrLogDetailsController@index', '/getPnrLog/list', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Import PNR'), 'Get PNR Log List', 'ImportPnrLogDetailsController@getList', '/getPnrLog/getList', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Import PNR'), 'Get PNR Log View', 'ImportPnrLogDetailsController@pnrLogView', '/getPnrLog/view', '', 'N', 'A', 1, '2020-05-26 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ImportPnrLogDetailsController%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ImportPnrLogDetailsController%') AND menu_id != 0);

/*Karthick 27th March 2020 - 1 pm insurance list*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Insurance'), 'Insurance index', 'InsuranceBookingsController@index', 'insurance/index', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Insurance'), 'Insurance list', 'InsuranceBookingsController@list', 'insurance/list', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Insurance'), 'Insurance view', 'InsuranceBookingsController@view', 'insurance/view', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Insurance'), 'Insurance retry', 'InsuranceBookingsController@retry', 'insurance/retry', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%InsuranceBookingsController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%InsuranceBookingsController%') AND menu_id != 0);

/*Seenivasan 27-march-2020 Permission  Route pages setting*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Page Settings'), 'get Route Page Settings', 'RoutePageController@getRoutePageSettings', 'getRoutePageSettings', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RoutePageController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RoutePageController%') AND menu_id != 0);

/*Seenivasan 27-march-2020 Subscription Details*/

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES (NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA') , (SELECT `menu_id` from `menu_details` where `menu_name` = 'CMS Settings'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Subscription'), '0', '7', '1', '1', 'Y', 'Y');


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Subscription'), 'Subscription index', 'SubscriptionController@index', 'subscription/index', '', 'Y', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Subscription'), 'Subscription list', 'SubscriptionController@list', 'subscription/list', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Subscription'), 'Subscription view', 'SubscriptionController@store', 'subscription/store', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Subscription'), 'Subscription delete', 'SubscriptionController@delete', 'subscription/delete', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Subscription'), 'Subscription delete', 'SubscriptionController@changeStatus', 'subscription/changeStatus', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SubscriptionController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SubscriptionController%') AND menu_id != 0);

/* Karthick 27-March-2020 9.30pm Invoice Statement */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements index', 'InvoiceStatementController@index', 'invoiceStatement/index', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements payableInvoiceList', 'InvoiceStatementController@payableInvoiceList', 'invoiceStatement/payableInvoiceList', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements paidInvoiceList', 'InvoiceStatementController@paidInvoiceList', 'invoiceStatement/paidInvoiceList', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements receivableInvoiceList', 'InvoiceStatementController@receivableInvoiceList', 'invoiceStatement/receivableInvoiceList', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements receivedInvoiceList', 'InvoiceStatementController@receivedInvoiceList', 'invoiceStatement/receivedInvoiceList', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements pendingInvoiceList', 'InvoiceStatementController@pendingInvoiceList', 'invoiceStatement/pendingInvoiceList', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements approvedInvoiceList', 'InvoiceStatementController@approvedInvoiceList', 'invoiceStatement/approvedInvoiceList', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements creditLimitCheck', 'InvoiceStatementController@creditLimitCheck', 'invoiceStatement/creditLimitCheck', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements getInvoiceDetails', 'InvoiceStatementController@getInvoiceDetails', 'invoiceStatement/getInvoiceDetails', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements getInvoiceBookingDetails', 'InvoiceStatementController@getInvoiceBookingDetails', 'invoiceStatement/getInvoiceBookingDetails', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements getInvoicePaymentDetails', 'InvoiceStatementController@getInvoicePaymentDetails', 'invoiceStatement/getInvoicePaymentDetails', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements payInvoice', 'InvoiceStatementController@payInvoice', 'invoiceStatement/payInvoice', '', 'N', 'A', 1, '2020-05-26 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%InvoiceStatementController%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%InvoiceStatementController%') AND menu_id != 0);

/*Karthick 28th march 2020 route config*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Route Page Domestic', 'RoutePageController@getAllDomesticRoute', 'getAllDomesticRoute', '', 'Y', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Route Page International', 'RoutePageController@getAllInternationalRoute', 'getAllInternationalRoute', '', 'Y', 'A', 1, '2020-05-26 00:00:00');


/* Venkatesan 27-March-2020 Schedule Management Queue */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Schedule Management Queue'), 'Schedule Management Queue List', 'ScheduleManagementQueueController@getList', '/scheduleManagementQueue/list', '', 'N', 'A', 1, '2020-05-27 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Schedule Management Queue'), 'Schedule Management Queue view', 'ScheduleManagementQueueController@view', '/scheduleManagementQueue/view', '', 'N', 'A', 1, '2020-05-27 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ScheduleManagementQueueController%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ScheduleManagementQueueController%') AND menu_id != 0);

/*Seenivasan 27- march - 2020 User referal Api */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Referral'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Referral Api List', 'UserReferralController@getReferralList', 'userReferral/getReferralList', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Referral'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Referral Api Store', 'UserReferralController@referralStore', 'userReferral/referralStore', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserReferralController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserReferralController%') AND menu_id != 0);

/*Seenivasan 27- march - 2020 Login History */


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES

(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Login History'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Login History List', 'LoginController@showLoginHistory', 'showLoginHistory', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%LoginController@showLoginHistory%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%LoginController@showLoginHistory%') AND menu_id != 0);

/* Venkatesan 27-March-2020 Flight Search Logs */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Flight Search Logs'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Flight Search Logs List', 'FlightSearchLogController@index', '/flightSearchLog/list', '', 'N', 'A', 1, '2020-05-27 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Flight Search Logs'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Flight Search Logs List', 'FlightSearchLogController@getList', '/flightSearchLog/list', '', 'N', 'A', 1, '2020-05-27 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%FlightSearchLogController%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%FlightSearchLogController%') AND menu_id != 0);

/*Karthick 28 th march 2020 Check price hotel*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Hotel getRoomsCheckPrice', 'HotelsController@getRoomsCheckPrice', 'hotels/getRoomsCheckPrice', '', 'Y', 'A', 1, '2020-05-27 00:00:00');

/* Divakar  30-March-2020 User Role */
INSERT INTO `submenu_details` (`submenu_id`, `submenu_name`, `link`, `icon`, `sub_menu_type`, `status`) VALUES (NULL, 'User Roles', 'userRoles', 'create', 'A', 'Y');

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES (NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA') , (SELECT `menu_id` from `menu_details` where `menu_name` = 'Settings'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'User Roles'), '0', '17', '1', '1', 'Y', 'Y');


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Roles'), 'User Roles','Index', 'UserRolesController@index', 'userRoles/index', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Roles'), 'User Roles','List', 'UserRolesController@getList', 'userRoles/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Roles'), 'User Roles','Create', 'UserRolesController@create', 'userRoles/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Roles'), 'User Roles','Store', 'UserRolesController@store', 'userRoles/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Roles'), 'User Roles','Edit', 'UserRolesController@edit', 'userRoles/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Roles'), 'User Roles','Update', 'UserRolesController@update', 'userRoles/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Roles'), 'User Roles','Change Status', 'UserRolesController@changeStatus', 'userRoles/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Roles'), 'User Roles','Delete', 'UserRolesController@delete', 'userRoles/delete', '', 'Y', 'A', 1, '2018-05-08 00:00:00');


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserRolesController%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserRolesController%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserRolesController%') AND menu_id != 0);

/*Karthick 30 th march 2020 Check price hotel*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Hotel getRoomsResult', 'HotelsController@getRoomsResult', 'hotels/getRoomsResult', '', 'Y', 'A', 1, '2020-05-27 00:00:00');

/*Venkatesan 30 th march 2020*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Download Component','Download Component', 'InitDetailsController@downloadComponent', 'downloadComponent', '', 'Y', 'A', 1, '2020-03-30 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Page Meta','Get Page Meta', 'InitDetailsController@getPageMeta', 'getPageMeta', '', 'Y', 'A', 1, '2020-03-30 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Portal Theme','Get Portal Theme', 'InitDetailsController@getPortalTheme', 'getPortalTheme', '', 'Y', 'A', 1, '2020-03-30 00:00:00');

/* Venkatesan 30-March-2020  Route config Management Template*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Get History', 'RouteConfigManagementController@getHistory', 'routeConfig/getHistory', '', 'N', 'A', 1, '2020-03-30 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Get History Diff', 'RouteConfigManagementController@getHistoryDiff', 'routeConfig/getHistoryDiff', '', 'N', 'A', 1, '2020-03-30 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RouteConfigManagementController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RouteConfigManagementController@getHistory%') AND menu_id != 0);

                                                                                      
/*Karthick 30 th march 6 pm 2020 Check price hotel*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Setting sendTestMail', 'AgencySettingsController@sendTestMail', 'agencySettings/sendTestMail', '', 'Y', 'A', 1, '2020-05-27 00:00:00');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract agencyUserHasApproveContract', 'ContractManagementController@agencyUserHasApproveContract', 'contract/agencyUser/agencyUserHasApproveContract', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContractManagementController@agencyUserHasApproveContract%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContractManagementController@agencyUserHasApproveContract%') AND menu_id != 0);

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management getHistory', 'ContentSourceController@getHistory', 'contentSource/getHistory/history', '', 'Y', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management getHistoryDiff', 'ContentSourceController@getHistoryDiff', 'contentSource/getHistoryDiff', '', 'Y', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContentSourceController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContentSourceController@getHistory%') AND menu_id != 0);  

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContentSourceController@getHistoryDiff%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContentSourceController@getHistoryDiff%') AND menu_id != 0);  

                                                                                      
/*Divakar 31 th march 2020 */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Portal Data','Portal Data', 'PortalConfigController@getPortalData', 'getPortalData', '', 'Y', 'A', 1, '2018-05-08 00:00:00');


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Reschedule Search','Reschedule Search', 'BookingsController@rescheduleSearch', 'bookings/rescheduleSearch', '', 'Y', 'A', 1, '2018-05-08 00:00:00');


/*Divakar 1st Apr 2020 */

ALTER TABLE `menu_details` ADD `new_icon` VARCHAR(50) NULL DEFAULT NULL AFTER `icon`;
ALTER TABLE `submenu_details` ADD `new_icon` VARCHAR(50) NULL DEFAULT NULL AFTER `icon`;


UPDATE `submenu_details` SET `new_icon` = `icon` WHERE 1;
UPDATE `menu_details` SET `new_icon` = `icon` WHERE 1;

UPDATE `menu_details` SET `new_icon` = 'snowflake-o' WHERE `menu_details`.`menu_id` = 2;


/*Divakar 1st Apr 2020 Re Schedule*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Exchange Shopping','Search', 'RescheduleController@getAirExchangeShopping', 'reschedule/getAirExchangeShopping', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Exchange Shopping','Price', 'RescheduleController@getAirExchangeOfferPrice', 'reschedule/getAirExchangeShopping', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Exchange Shopping','Create', 'RescheduleController@getAirExchangeOrderCreate', 'reschedule/getAirExchangeShopping', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Exchange Shopping','voucher', 'RescheduleController@voucher', 'reschedule/voucher', '', 'Y', 'A', 1, '2018-05-08 00:00:00');


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RescheduleController%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RescheduleController%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RescheduleController%') AND menu_id != 0);

/* Seenivasan 1 April 2020 Hotel Beds City Management*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel Beds City Management'), 'Hotel Beds City Management History Diff', 'HotelBedsCityManagementController@getHistoryDiff', 'hotelBedsCityManagement/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel Beds City Management'), 'Hotel Beds City Management History', 'HotelBedsCityManagementController@getHistory', 'hotelBedsCityManagement/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel Beds City Management'), 'Hotel Beds City Management Update', 'HotelBedsCityManagementController@update', 'hotelBedsCityManagement/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel Beds City Management'), 'Hotel Beds City Management ChangeStatus', 'HotelBedsCityManagementController@changeStatus', 'hotelBedsCityManagement/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel Beds City Management'), 'Hotel Beds City Management Delete', 'HotelBedsCityManagementController@delete', 'hotelBedsCityManagement/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel Beds City Management'), 'Hotel Beds City Management Edit', 'HotelBedsCityManagementController@edit', 'hotelBedsCityManagement/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel Beds City Management'), 'Hotel Beds City Management Save', 'HotelBedsCityManagementController@store', 'hotelBedsCityManagement/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel Beds City Management'), 'Hotel Beds City Management Create', 'HotelBedsCityManagementController@create', 'hotelBedsCityManagement/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel Beds City Management'), 'Hotel Beds City Management List', 'HotelBedsCityManagementController@list', 'hotelBedsCityManagement/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel Beds City Management'), 'Hotel Beds City Management List', 'HotelBedsCityManagementController@index', 'hotelBedsCityManagement/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%HotelBedsCityManagementController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%HotelBedsCityManagementController%') AND menu_id != 0);

UPDATE `submenu_details` SET `new_link` = 'lookToBookRatio' WHERE `submenu_details`.`link` = 'bookingRatio';

/* karthick 1st April- 2020 Booking View Permission */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Flight Booking view', 'BookingsController@view', 'bookings/view', '', 'N', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@view%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@view%') AND menu_id != 0);  


/* Divakar 02 - 03 - 2020 Ticket Plugin Related Changes */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`,`permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Ticket Plugin', 'Booking List', 'AgencyBookingController@getAgencyBookingList', 'getAgencyBookingList', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Ticket Plugin', 'Check Balance', 'AgencyBalanceCheckController@agencyBalanceCheck', 'agencyBalanceCheck', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Ticket Plugin', 'Check PNR', 'PnrStatusCheckController@pnrStatusCheck', 'pnrStatusCheck', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Ticket Plugin', 'Fare Quote', 'FareQuoteController@fareQuote', 'fareQuote', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Ticket Plugin', 'Confirm Fare', 'FareQuoteController@priceConfirmation', 'priceConfirmation', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Ticket Plugin', 'Fare Rules', 'FareRulesController@getFareRules', 'getFareRules', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Ticket Plugin', 'Issue Ticket', 'TicketingController@issueTicket', 'issueTicket', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Ticket Plugin', 'Cancel Ticket', 'TicketingController@cancelTicket', 'cancelTicket', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

/* karthick 1st April- 2020 Booking View Permission */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Flight Booking checkDuplicateTicketNumber', 'BookingsController@checkDuplicateTicketNumber', 'bookings/checkDuplicateTicketNumber', '', 'N', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@checkDuplicateTicketNumber%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@checkDuplicateTicketNumber%') AND menu_id != 0);  

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Flight Booking updateTicketNumber', 'BookingsController@updateTicketNumber', 'bookings/updateTicketNumber', '', 'N', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@updateTicketNumber%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@updateTicketNumber%') AND menu_id != 0);  

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Flight Booking bookingOfflinePayment', 'BookingsController@bookingOfflinePayment', 'bookings/bookingOfflinePayment', '', 'N', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@bookingOfflinePayment%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@bookingOfflinePayment%') AND menu_id != 0);  

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Flight Booking getBookingDetails', 'BookingsController@getBookingDetails', 'bookings/getBookingDetails', '', 'N', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@getBookingDetails%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@getBookingDetails%') AND menu_id != 0);  

/*Karthick 2nd April 2020 Customers Booking List*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Customer Flight Booking list', 'CustomerBookingManagementController@list', 'customerBooking/list', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Customer Flight Booking view', 'CustomerBookingManagementController@view', 'customerBooking/view', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Customer Flight Booking getGuestBookingView', 'CustomerBookingManagementController@getGuestBookingView', 'customerBooking/getGuestBookingView', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Customer Flight Booking userCancelBooking', 'CustomerBookingManagementController@userCancelBooking', 'customerBooking/userCancelBooking', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Customer Flight Booking bookingCancelEmail', 'CustomerBookingManagementController@bookingCancelEmail', 'customerBooking/bookingCancelEmail', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Customer Flight Booking emailSend', 'CustomerBookingManagementController@emailSend', 'customerBooking/emailSend', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Customer Flight Booking getAllRescheduleBooking', 'CustomerBookingManagementController@getAllRescheduleBooking', 'customerBooking/getAllRescheduleBooking', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Customer Flight Booking bookingSuccessEmail', 'CustomerBookingManagementController@bookingSuccessEmail', 'customerBooking/bookingSuccessEmail', '', 'Y', 'A', 1, '2020-03-21 00:00:00');
 
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel'), 'Customer Hotel Booking list', 'CustomerHotelBookingManagementController@list', 'customerHotelBooking/list', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel'), 'Customer Hotel Booking view', 'CustomerHotelBookingManagementController@view', 'customerHotelBooking/view', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel'), 'Customer Hotel Booking guestHotelBookingView', 'CustomerHotelBookingManagementController@guestHotelBookingView', 'customerHotelBooking/guestHotelBookingView', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel'), 'Customer Hotel Booking hotelSuccessEmailSend', 'CustomerHotelBookingManagementController@hotelSuccessEmailSend', 'customerHotelBooking/hotelSuccessEmailSend', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel'), 'Customer Hotel Booking userHotelCancelBooking', 'CustomerHotelBookingManagementController@userHotelCancelBooking', 'customerHotelBooking/userHotelCancelBooking', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

/*Karthick 3rd April 2020 Customer Insurance Booking Management*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Insurance'), 'Customer Insurance Booking list', 'CustomerInsuranceBookingManagementController@list', 'customerHotelBooking/list', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Insurance'), 'Customer Insurance Booking view', 'CustomerInsuranceBookingManagementController@view', 'customerHotelBooking/view', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Insurance'), 'Customer Insurance Booking guestInsuranceBookingView', 'CustomerInsuranceBookingManagementController@guestInsuranceBookingView', 'customerHotelBooking/guestInsuranceBookingView', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

/* Seenivasan 4 April 2020 User Referral Api */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Referral'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Referral List', 'UserReferralController@getReferralList', 'userReferral/getReferralList', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Referral'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Referral Save', 'UserReferralController@referralStore', 'userReferral/referralStore', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='CU'),permission_id as pd from permissions where is_public = 'Y' AND ( permission_route = 'UserReferralController@getReferralList') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='CU'),permission_id as pd from permissions where is_public = 'Y' AND ( permission_route = 'UserReferralController@referralStore') AND menu_id != 0);


/* Seenivasan 4 April 2020 User Management Api */


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Customer Management'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Customer Management Edit Customer', 'CustomerManagementController@editCustomer', 'manageCustomers/editCustomer', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Customer Management'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Customer Management Update Customer', 'CustomerManagementController@updateCustomer', 'manageCustomers/updateCustomer', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Customer Management'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Customer Management Chnage Password', 'CustomerManagementController@changePassword', 'manageCustomers/changePassword', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='CU'),permission_id as pd from permissions where is_public = 'Y' AND ( permission_route = 'CustomerManagementController@editCustomer') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='CU'),permission_id as pd from permissions where is_public = 'Y' AND ( permission_route = 'CustomerManagementController@updateCustomer') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='CU'),permission_id as pd from permissions where is_public = 'Y' AND ( permission_route = 'CustomerManagementController@changePassword') AND menu_id != 0);

/*Karthick 3rd April 2020 reward points*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Insurance'), 'Customer Reward Transaction getRewardRedemTranList', 'RewardPointTransactionController@getRewardRedemTranList', 'rewardPoints/getRewardRedemTranList', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

/* Divakar 4th April 2020 Import PNR Permission */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Import PNR'), 'Import PNR','Form Data', 'GetPnrController@getFormData', 'importPnr/getFormData', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Import PNR'), 'Import PNR','Get Supplier', 'GetPnrController@getSupplierInfo', 'importPnr/getSupplierInfo', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Import PNR'), 'Import PNR','Get PNR', 'GetPnrController@getPnr', 'importPnr/getPnr', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Import PNR'), 'Import PNR','Store', 'GetPnrController@storePnr', 'importPnr/storePnr', '', 'N', 'A', 1, '2018-05-08 00:00:00');


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%GetPnrController%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%GetPnrController%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%GetPnrController%') AND menu_id != 0);


/* Venkatesan Remark Template 05-04-1995*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Remarks Templates'), 'Remarks Templates Update', 'RemarkTemplateController@update', 'remarkTemplate/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Remarks Templates'), 'Remarks Templates ChangeStatus', 'RemarkTemplateController@changeStatus', 'remarkTemplate/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Remarks Templates'), 'Remarks Templates Delete', 'RemarkTemplateController@delete', 'remarkTemplate/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Remarks Templates'), 'Remarks Templates Edit', 'RemarkTemplateController@edit', 'remarkTemplate/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Remarks Templates'), 'Remarks Templates Save', 'RemarkTemplateController@store', 'remarkTemplate/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Remarks Templates'), 'Remarks Templates Create', 'RemarkTemplateController@create', 'remarkTemplate/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Remarks Templates'), 'Remarks Templates List', 'RemarkTemplateController@index', 'remarkTemplate/list', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Remarks Templates'), 'Remarks Templates List', 'RemarkTemplateController@getList', 'remarkTemplate/list', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Remarks Templates'), 'Remarks Templates History', 'RemarkTemplateController@getHistory', 'remarkTemplate/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Remarks Templates'), 'Remarks Templates History Diff', 'RemarkTemplateController@getHistoryDiff', 'remarkTemplate/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RemarkTemplateController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RemarkTemplateController%') AND menu_id != 0);

/* Venkatesan Agency Fee Store 05-04-2020*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee store', 'AgencyFeeManagementController@store', 'agencyFee/store', '', 'N', 'A', 1, '2018-05-08 00:00:00');


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyFeeManagementController@store%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyFeeManagementController@store%') AND menu_id != 0);

/* Divakar 6th April 2020 Permission */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Fare Rule','Get Fare Rule', 'FlightsController@getFareRules', 'flights/getFareRules', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Call Fare Rule','Call Fare Rule', 'FlightsController@callFareRules', 'flights/callFareRules', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

/* Seenivasan 6 April 2020 Rewards Points */

CREATE TABLE `reward_points` (
  `reward_point_id` bigint(10) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `account_id` int(10) NOT NULL,
  `portal_id` int(10) NOT NULL,
  `user_groups` varchar(255) NOT NULL,
  `product_type` enum('1','2','3') NOT NULL COMMENT '1- Flight, 2- Hotel, 3. insurance',
  `fare_type` varchar(255) NOT NULL COMMENT 'BF = Bafe Fare, TF = Total Fare, BQ = Base fare + YQ',
  `additional_services` varchar(255) NOT NULL COMMENT 'SSR, Insurance',
  `earn_points` int(10) NOT NULL COMMENT 'ex: 1 USD = 1 Points',
  `redemption_points` int(10) NOT NULL COMMENT 'ex: 1 USD = 1 Points',
  `maximum_redemption_points` int(11) NOT NULL,
  `minimum_reward_points` int(10) NOT NULL,
  `status` enum('A','IA','D') NOT NULL,
  `created_by` int(10) NOT NULL,
  `updated_by` int(10) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL  
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/*Seenivasan 6 April 2020 User reward summary */

CREATE TABLE `user_reward_summary` (
  `user_reward_summary_id` bigint(10) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `account_id` int(10) NOT NULL,
  `portal_id` int(10) NOT NULL,
  `user_id` int(10) NOT NULL,
  `available_points` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/*Seenivasan 6 April 2020 Reward point transaction list */

CREATE TABLE `reward_point_transaction_list` (
  `reward_point_transaction_id` bigint(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `portal_id` int(11) NOT NULL,
  `user_id` bigint(11) NOT NULL,
  `order_id` bigint(11) NOT NULL,
  `order_type` varchar(255) NOT NULL,
  `reward_type` enum('earn','redeem') NOT NULL,
  `reward_points` int(11) NOT NULL,
  `request_ip` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL,
  `created_by` bigint(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


/* Seenivasan 6 April 2020 Reward point details */
ALTER TABLE `booking_master` ADD `reward_point_detail` VARCHAR(255) NOT NULL AFTER `meta_name`;


ALTER TABLE `reward_points` ADD `earn_points_conversion_rate` DOUBLE(12,2) NOT NULL AFTER `additional_services`;
ALTER TABLE `reward_points` CHANGE `earn_points` `earn_points` INT(10) NOT NULL;
ALTER TABLE `reward_points` CHANGE `redemption_points` `redemption_conversation_rate` DOUBLE(12,2) NOT NULL COMMENT 'ex: 1 Poins = 1 CAD';

/* Seenivasan 6 April 2020 - Reward Points - 20 March 2020 */
ALTER TABLE `reward_point_transaction_list` ADD `other_details` TEXT NULL DEFAULT NULL AFTER `request_ip`;
ALTER TABLE `reward_point_transaction_list` ADD `status` ENUM('I','S','F','') NOT NULL DEFAULT 'I' COMMENT 'I - Initiated, S - Success, F - Failed' AFTER `other_details`;

/*Karthick 06th April 2020 Agency fee index*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee index', 'AgencyFeeManagementController@index', 'agencyFee/index', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyFeeManagementController@index%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyFeeManagementController@index%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%FlightShareUrlController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%FlightShareUrlController%') AND menu_id != 0);

/*Seenivasan 6 April 2020 Reward Points Submenu Details */

INSERT INTO `submenu_details` (`submenu_id`, `submenu_name`, `new_link`, `icon`, `sub_menu_type`, `status`) VALUES (NULL, 'Reward Points', 'rewardPoints', 'attach_money', 'A', 'Y');

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Reward Points'),'0', '15', '0', '0', 'Y', 'Y');

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='MA'), (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Reward Points'),'0', '15', '0', '0', 'Y', 'Y');

/*Seenivasan 6 April 2020 Reward Points Transaction List Submenu Details */

INSERT INTO `submenu_details` (`submenu_id`, `submenu_name`, `new_link`, `icon`, `sub_menu_type`, `status`) VALUES (NULL, 'Reward Transaction List', 'rewardTransactionList', 'list', 'A', 'Y');


INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Reward Transaction List'),'0', '16', '0', '0', 'Y', 'Y');

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='MA'), (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Reward Transaction List'),'0', '16', '0', '0', 'Y', 'Y');

/* Seenivasan 6 April 2020 Reward Points Permission*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Reward Points'), 'Reward Points Update', 'RewardPointsController@update', 'rewardPoints/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Reward Points'), 'Reward Points ChangeStatus', 'RewardPointsController@changeStatus', 'rewardPoints/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Reward Points'), 'Reward Points Delete', 'RewardPointsController@delete', 'rewardPoints/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Reward Points'), 'Reward Points Edit', 'RewardPointsController@edit', 'rewardPoints/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Reward Points'), 'Reward Points Save', 'RewardPointsController@store', 'rewardPoints/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Reward Points'), 'Reward Points Create', 'RewardPointsController@create', 'rewardPoints/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Reward Points'), 'Reward Points List', 'RewardPointsController@list', 'rewardPoints/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Reward Points'), 'Reward Points List', 'RewardPointsController@index', 'rewardPoints/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RewardPointsController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RewardPointsController%') AND menu_id != 0);

/* Seenivasan 6 April 2020 Reward Points Reransaction Permission*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Reward Transaction List'), 'Reward Transaction  View', 'RewardPointTransactionController@view', 'rewardTransactionList/view', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Reward Transaction List'), 'Reward Transaction  List', 'RewardPointTransactionController@list', 'rewardTransactionList/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Reward Transaction List'), 'Reward Transaction  Index', 'RewardPointTransactionController@index', 'rewardTransactionList/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RewardPointTransactionController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RewardPointTransactionController%') AND menu_id != 0);


/* Seenivasan 6- April 2020 User Traveller */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Travellers'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Travellers Api Delete', 'UserTravellerController@delete', 'getUserTravellerDelete', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Travellers'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Travellers Api Update', 'UserTravellerController@update', 'getUserTravellerUpdate', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Travellers'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Travellers Api Edit', 'UserTravellerController@edit', 'getUserTravellerEdit', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Travellers'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Travellers Api Save', 'UserTravellerController@store', 'getUserTravellerStore', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Travellers'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Travellers Api List', 'UserTravellerController@getUserTraveller', 'getUserTraveller', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='CU'),permission_id as pd from permissions where is_public = 'Y' AND ( permission_route like '%UserTravellerController%') AND menu_id != 0);

/* Venkatesan loadExchangeRate 07-04-2020*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Currency Exchange Rate'), 'Currency ExchangeRate loadExchangeRate', 'CurrencyExchangeRateController@loadExchangeRate', 'currencyExchangeRate/loadExchangeRate', '', 'Y', 'A', 1, '2020-04-07 00:00:00');

/* Link */
UPDATE `submenu_details` SET `new_link` = 'ticketingQueueList' WHERE `submenu_details`.`link` = 'ticketingQueueIndex';


/* Divaktr 07 - 04 - 2020 Menu Link Updated */

UPDATE `menu_details` SET `new_link` = 'dashboard' WHERE `link` = 'flights/search';
UPDATE `menu_details` SET `new_link` = 'flightShareUrl' WHERE `link` = 'flightShareUrl';
UPDATE `menu_details` SET `new_link` = 'portalDetails' WHERE `link` = 'portalDetails';
UPDATE `menu_details` SET `new_link` = 'manageAgent' WHERE `link` = 'manageAgents';
UPDATE `submenu_details` SET `new_link` = 'airlineGroup' WHERE `link` = 'airlineGroup';
UPDATE `submenu_details` SET `new_link` = 'airportGroups' WHERE `link` = 'airportGroup';
UPDATE `submenu_details` SET `new_link` = 'formOfPayment' WHERE `link` = 'formOfPayment';
UPDATE `submenu_details` SET `new_link` = 'contentSource' WHERE `link` = 'contentSource';
UPDATE `submenu_details` SET `new_link` = 'agencyFeeManagement' WHERE `link` = 'agencyFeeManagement';
UPDATE `submenu_details` SET `new_link` = 'cityManagement' WHERE `link` = 'hotelBedsCityManagement';
UPDATE `submenu_details` SET `new_link` = 'pgTransactionList' WHERE `link` = 'pgTransactionIndex';
UPDATE `submenu_details` SET `new_link` = 'userGroupDetails' WHERE `link` = 'userGroupDetails';
UPDATE `submenu_details` SET `new_link` = 'airlineManage' WHERE `link` = 'airlineManage';
UPDATE `submenu_details` SET `new_link` = 'subscription' WHERE `link` = 'subscription';
UPDATE `submenu_details` SET `new_link` = 'footerIcons' WHERE `link` = 'footerIcons';
UPDATE `submenu_details` SET `new_link` = 'customerFeedback' WHERE `link` = 'customerFeedbacks';
UPDATE `submenu_details` SET `new_link` = 'routeConfigManagement' WHERE `link` = 'routeConfigList';
UPDATE `submenu_details` SET `new_link` = 'popularRoutes' WHERE `link` = 'popularRoutes';
UPDATE `submenu_details` SET `new_link` = 'blogContent' WHERE `link` = 'blogContent';
UPDATE `submenu_details` SET `new_link` = 'countryDetails' WHERE `link` = 'countryDetails';
UPDATE `submenu_details` SET `new_link` = 'airportManage' WHERE `link` = 'airportManage';
UPDATE `submenu_details` SET `new_link` = 'sectorMapping' WHERE `link` = 'sectorMapping';
UPDATE `submenu_details` SET `new_link` = 'paymentGatewayConfig' WHERE `link` = 'gateWayConfig';
UPDATE `submenu_details` SET `new_link` = 'qualityCheckTemplate' WHERE `link` = 'qualityCheckTemplate';
UPDATE `submenu_details` SET `new_link` = 'promoCode' WHERE `link` = 'promoCode';
UPDATE `submenu_details` SET `new_link` = 'popularDestinations' WHERE `link` = 'popularDestinations';
UPDATE `submenu_details` SET `new_link` = 'footerLinks' WHERE `link` = 'footerLinks';
UPDATE `submenu_details` SET `new_link` = 'addBenefit' WHERE `link` = 'addBenefit';
UPDATE `submenu_details` SET `new_link` = 'profileAggregation' WHERE `link` = 'profileAggregation';
UPDATE `submenu_details` SET `new_link` = 'flightBookingList' WHERE `link` = 'bookingindex';
UPDATE `submenu_details` SET `new_link` = 'dynamicRoleAllocation' WHERE `link` = 'dynamicRoleAllocation';
UPDATE `submenu_details` SET `new_link` = 'portalPromotions' WHERE `link` = 'portalPromotions';
UPDATE `submenu_details` SET `new_link` = 'supplierSurcharge' WHERE `link` = 'supplierSurcharge';
UPDATE `submenu_details` SET `new_link` = 'remarkTemplate' WHERE `link` = 'remarkTemplate';
UPDATE `submenu_details` SET `new_link` = 'supplierLowfareTemplate' WHERE `link` = 'supplierLowfareTemplate';
UPDATE `submenu_details` SET `new_link` = 'riskAnalysisManagement' WHERE `link` = 'riskAnalysisManagement';
UPDATE `submenu_details` SET `new_link` = 'ticketingRules' WHERE `link` = 'ticketingRules';
UPDATE `submenu_details` SET `new_link` = 'ticketingQueueList' WHERE `link` = 'ticketingQueueIndex';
UPDATE `submenu_details` SET `new_link` = 'lookToBookRatio' WHERE `link` = 'bookingRatio';
UPDATE `submenu_details` SET `new_link` = 'currencyExchangeRate' WHERE `link` = 'currencyExchangeRate';
UPDATE `submenu_details` SET `new_link` = 'userTraveller' WHERE `link` = 'userTravellers';

/*Karthick 8th April 2020 all query changes*/
/*Delete Role Permission*/

DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%ContractManagementController%" AND permission_url IS NOT NULL)x);
DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%UserGroupsController%" AND permission_url IS NOT NULL)x);
DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%FlightShareUrlController%" AND permission_url IS NOT NULL)x);
DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%AgencyFeeManagementController%" AND permission_url IS NOT NULL)x);
DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%AgencyManageController%" AND permission_url IS NOT NULL)x);
DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%AgencyCreditManagementController%" AND permission_url IS NOT NULL)x);
DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%ContentSourceController%" AND permission_url IS NOT NULL)x);
DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%PortalConfigController%" AND permission_url IS NOT NULL)x);
DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%MarkupTemplateController%" AND permission_url IS NOT NULL)x);
DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%SupplierAirlineMaskingTemplatesController%" AND permission_url IS NOT NULL)x);
DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%SupplierAirlineMaskingRulesController%" AND permission_url IS NOT NULL)x);
DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%MarkupContractController%" AND permission_url IS NOT NULL)x);
DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%TicketingQueueController%" AND permission_url IS NOT NULL)x);
DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%OfflinePaymentController%" AND permission_url IS NOT NULL)x);
DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%InsuranceBookingsController%" AND permission_url IS NOT NULL)x);
DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%InvoiceStatementController%" AND permission_url IS NOT NULL)x);
DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%BookingsController%" AND permission_url IS NOT NULL)x);

/*Delete Permission*/

DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%ContractManagementController%" AND permission_url IS NOT NULL)x);
DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%UserGroupsController%" AND permission_url IS NOT NULL)x);
DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%FlightShareUrlController%" AND permission_url IS NOT NULL)x);
DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%AgencyFeeManagementController%" AND permission_url IS NOT NULL)x);
DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%AgencyManageController%" AND permission_url IS NOT NULL)x);
DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%AgencyCreditManagementController%" AND permission_url IS NOT NULL)x);
DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%ContentSourceController%" AND permission_url IS NOT NULL)x);
DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%PortalConfigController%" AND permission_url IS NOT NULL)x);
DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%MarkupTemplateController%" AND permission_url IS NOT NULL)x);
DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%SupplierAirlineMaskingTemplatesController%" AND permission_url IS NOT NULL)x);
DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%SupplierAirlineMaskingRulesController%" AND permission_url IS NOT NULL)x);
DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%MarkupContractController%" AND permission_url IS NOT NULL)x);
DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%TicketingQueueController%" AND permission_url IS NOT NULL)x);
DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%OfflinePaymentController%" AND permission_url IS NOT NULL)x);
DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%InsuranceBookingsController%" AND permission_url IS NOT NULL)x);
DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%InvoiceStatementController%" AND permission_url IS NOT NULL)x);
DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%BookingsController%" AND permission_url IS NOT NULL)x);


/*Contract Permission*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract Management' , 'index', 'ContractManagementController@index', 'contract/index', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract Management' , 'list', 'ContractManagementController@list', 'contract/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract Management' , 'create', 'ContractManagementController@create', 'contract/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract Management' , 'contractStore', 'ContractManagementController@contractStore', 'contract/storeContract', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract Management' , 'ruleStore', 'ContractManagementController@ruleStore', 'contract/storeRule', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract Management' , 'updateContract', 'ContractManagementController@updateContract', 'contract/updateContract', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract Management' , 'updateRules', 'ContractManagementController@updateRules', 'contract/updateRules', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract Management' , 'ruleEdit', 'ContractManagementController@ruleEdit', 'contract/rule/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract Management' , 'rulecopy', 'ContractManagementController@ruleEdit', 'contract/rule/copy', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract Management' , 'contractChangeStatus', 'ContractManagementController@contractChangeStatus', 'contract/contractChangeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract Management' , 'rulesChangeStatus', 'ContractManagementController@rulesChangeStatus', 'contract/rulesChangeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract Management' , 'assignToTemplate', 'ContractManagementController@assignToTemplate', 'contract/templateAssign/assignToTemplate', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract Management' , 'unAssignFromTemplate', 'ContractManagementController@unAssignFromTemplate', 'contract/templateAssign/unAssignFromTemplate', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract Management' , 'mapToTemplate', 'ContractManagementController@mapToTemplate', 'contract/templateAssign/mapToTemplate', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract Management' , 'getHistory', 'ContractManagementController@getHistory', 'contract/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract Management' , 'agencyUserHasApproveContract', 'ContractManagementController@agencyUserHasApproveContract', 'contract/agencyUser/agencyUserHasApproveContract', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract Management' , 'getHistoryDiff', 'ContractManagementController@getHistoryDiff', 'contract/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContractManagementController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContractManagementController%') AND menu_id != 0);

/*User Groups Permission*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'User Group', 'index', 'UserGroupsController@index', 'userGroups/index', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'User Group', 'list', 'UserGroupsController@userGroupsList', 'userGroups/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'User Group', 'create', 'UserGroupsController@create', 'userGroups/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'User Group', 'store', 'UserGroupsController@store', 'userGroups/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'User Group', 'edit', 'UserGroupsController@edit', 'userGroups/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'User Group', 'update', 'UserGroupsController@update', 'userGroups/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'User Group', 'delete', 'UserGroupsController@delete', 'userGroups/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'User Group', 'changeStatus', 'UserGroupsController@changeStatus', 'userGroups/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00');


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserGroupsController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserGroupsController%') AND menu_id != 0);

/*Flight Share URL Permission*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Flight Share Url'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Flight Share URL', 'list', 'FlightShareUrlController@getFlightShareUrlList', 'flightShareUrl/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Flight Share Url'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Flight Share URL', 'index', 'FlightShareUrlController@getShareUrlIndex', 'flightShareUrl/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Flight Share Url'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Flight Share URL', 'shareUrlExpiryUpdateEmail', 'FlightShareUrlController@sendExpiryUpdateEmail', 'flightShareUrl/shareUrlExpiryUpdateEmail', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Flight Share Url'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Flight Share URL', 'store', 'FlightShareUrlController@shareUrlChangeStatus', 'flightShareUrl/shareUrlChangeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%FlightShareUrlController%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%FlightShareUrlController%') AND menu_id != 0);

/*Agency fee permission */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee Management', 'list', 'AgencyFeeManagementController@agencyFeeList', 'agencyFee/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee Management', 'create', 'AgencyFeeManagementController@create', 'agencyFee/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee Management', 'store', 'AgencyFeeManagementController@store', 'agencyFee/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee Management', 'edit', 'AgencyFeeManagementController@edit', 'agencyFee/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee Management', 'update', 'AgencyFeeManagementController@update', 'agencyFee/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee Management', 'changeStatus', 'AgencyFeeManagementController@changeStatus', 'agencyFee/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee Management', 'delete', 'AgencyFeeManagementController@delete', 'agencyFee/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee Management', 'getHistory', 'AgencyFeeManagementController@getHistory', 'agencyFee/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee Management', 'getHistoryDiff', 'AgencyFeeManagementController@getHistoryDiff', 'agencyFee/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Fee Management'), 'Agency Fee Management', 'index', 'AgencyFeeManagementController@index', 'agencyFee/index', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyFeeManagementController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyFeeManagementController%') AND menu_id != 0);

/* Agency Management*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management', 'Index', 'AgencyManageController@getAgencyIndexDetails', 'manageAgency/getAgencyIndexDetails', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management', 'Get List', 'AgencyManageController@list', 'manageAgency/list', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management', 'Create', 'AgencyManageController@create', 'manageAgency/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management', 'store', 'AgencyManageController@store', 'manageAgency/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management', 'Edit', 'AgencyManageController@edit', 'manageAgency/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management', 'Update', 'AgencyManageController@update', 'manageAgency/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management', 'newRequests', 'AgencyManageController@newRequests', 'pendingAgency/newRequests', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management', 'newRequestsView', 'AgencyManageController@newRequestsView', 'pendingAgency/newRequestsView', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management', 'newAgencyRequestApprove', 'AgencyManageController@newAgencyRequestApprove', 'pendingAgency/newAgencyRequestApprove', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management', 'newAgencyRequestReject', 'AgencyManageController@newAgencyRequestReject', 'pendingAgency/newAgencyRequestReject', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management', 'getPaymentGateWays', 'AgencyManageController@getPaymentGateWays', 'manageAgency/getPaymentGateWays', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management', 'getHistory', 'AgencyManageController@getHistory', 'manageAgency/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management', 'getHistoryDiff', 'AgencyManageController@getHistoryDiff', 'manageAgency/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyManageController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyManageController%') AND menu_id != 0);  

/*Agency Credit Management*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management', 'creditManagementTransactionList', 'AgencyCreditManagementController@creditManagementTransactionList', 'agencyCredit/creditManagementTransactionList', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management', 'Get temporaryTopUpTransactionList', 'AgencyCreditManagementController@temporaryTopUpTransactionList', 'agencyCredit/temporaryTopUpTransactionList', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management', 'agencyDepositTransactionList', 'AgencyCreditManagementController@agencyDepositTransactionList', 'agencyCredit/agencyDepositTransactionList', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management', 'agencyPaymentTransactionList', 'AgencyCreditManagementController@agencyPaymentTransactionList', 'agencyCredit/agencyPaymentTransactionList', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management', 'create', 'AgencyCreditManagementController@create', 'agencyCredit/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management', 'approvePendingCredits', 'AgencyCreditManagementController@approve', 'agencyCredit/approvePendingCredits', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management', 'approveReject', 'AgencyCreditManagementController@approveReject', 'agencyCredit/approveReject', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management', 'getBalance', 'AgencyCreditManagementController@getBalance', 'agencyCredit/getBalance', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management', 'showBalance', 'AgencyCreditManagementController@showBalance', 'agencyCredit/showBalance', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management', 'getHistory', 'AgencyCreditManagementController@getHistory', 'agencyCredit/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management', 'getHistoryDiff', 'AgencyCreditManagementController@getHistoryDiff', 'agencyCredit/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyCreditManagementController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyCreditManagementController%') AND menu_id != 0);  

/*Content Source*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management', 'index', 'ContentSourceController@index', 'contentSource/index', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management', 'list', 'ContentSourceController@list', 'contentSource/list', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management', 'create', 'ContentSourceController@create', 'contentSource/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management', 'store', 'ContentSourceController@store', 'contentSource/store', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management', 'edit', 'ContentSourceController@edit', 'contentSource/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management', 'copy', 'ContentSourceController@edit', 'contentSource/copy', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management', 'update', 'ContentSourceController@update', 'contentSource/update', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management', 'changeStatus', 'ContentSourceController@changeStatus', 'contentSource/changeStatus', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management', 'delete', 'ContentSourceController@delete', 'contentSource/delete', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management', 'getContentSourceRefKey', 'ContentSourceController@getContentSourceRefKey', 'contentSource/getContentSourceRefKey', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management', 'checkAAAtoPCCcsrefkeyExist', 'ContentSourceController@checkAAAtoPCCcsrefkeyExist', 'contentSource/checkAAAtoPCCcsrefkeyExist', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management', 'getHistory', 'ContentSourceController@getHistory', 'contentSource/getHistory/history', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Content Source'), 'Content Source Management', 'getHistoryDiff', 'ContentSourceController@getHistoryDiff', 'contentSource/getHistoryDiff', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContentSourceController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContentSourceController%') AND menu_id != 0);  

/*Portal Config*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Config', 'index', 'PortalConfigController@index', 'portalConfig/index', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Config', 'list', 'PortalConfigController@portalConfigList', 'portalConfig/list', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Config', 'create', 'PortalConfigController@create', 'portalConfig/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Config', 'store', 'PortalConfigController@store', 'portalConfig/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Config', 'edit', 'PortalConfigController@edit', 'portalConfig/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Config', 'update', 'PortalConfigController@update', 'portalConfig/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Config', 'getPaymentGatewayList', 'PortalConfigController@paymentGatewaySelect', 'portalConfig/getPaymentGatewayList', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Config', 'getHistory', 'PortalConfigController@getHistory', 'portalConfig/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Config', 'getHistoryDiff', 'PortalConfigController@getHistoryDiff', 'portalConfig/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalConfigController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalConfigController%') AND menu_id != 0);  

/*Markup Template*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates', 'index', 'MarkupTemplateController@index', 'markupTemplate/index', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates', 'list', 'MarkupTemplateController@supplierMarkUpTemplateList', 'markupTemplate/list', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates', 'create', 'MarkupTemplateController@create', 'markupTemplate/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates', 'store', 'MarkupTemplateController@store', 'markupTemplate/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates', 'edit', 'MarkupTemplateController@edit', 'markupTemplate/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates', 'update', 'MarkupTemplateController@update', 'markupTemplate/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates', 'changeStatus', 'MarkupTemplateController@changeStatus', 'markupTemplate/changeStatus', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates', 'delete', 'MarkupTemplateController@delete', 'markupTemplate/delete', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates', 'getHistory', 'MarkupTemplateController@getHistory', 'markupTemplate/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates', 'getHistoryDiff', 'MarkupTemplateController@getHistoryDiff', 'markupTemplate/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalConfigController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalConfigController%') AND menu_id != 0);  

/*Suplier Airline masking Template*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Supplier Airline Masking', 'index', 'SupplierAirlineMaskingTemplatesController@index', 'supplierAirlineMaskingTemplates/index', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Supplier Airline Masking', 'list', 'SupplierAirlineMaskingTemplatesController@getList', 'supplierAirlineMaskingTemplates/list', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Supplier Airline Masking', 'create', 'SupplierAirlineMaskingTemplatesController@create', 'supplierAirlineMaskingTemplates/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Supplier Airline Masking', 'store', 'SupplierAirlineMaskingTemplatesController@store', 'supplierAirlineMaskingTemplates/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Supplier Airline Masking', 'edit', 'SupplierAirlineMaskingTemplatesController@edit', 'supplierAirlineMaskingTemplates/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Supplier Airline Masking', 'update', 'SupplierAirlineMaskingTemplatesController@update', 'supplierAirlineMaskingTemplates/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Supplier Airline Masking', 'changeStatus', 'SupplierAirlineMaskingTemplatesController@changeStatus', 'supplierAirlineMaskingTemplates/changeStatus', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Supplier Airline Masking', 'delete', 'SupplierAirlineMaskingTemplatesController@delete', 'supplierAirlineMaskingTemplates/delete', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Supplier Airline Masking', 'getHistory', 'SupplierAirlineMaskingTemplatesController@getHistory', 'supplierAirlineMaskingTemplates/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Supplier Airline Masking', 'getHistoryDiff', 'SupplierAirlineMaskingTemplatesController@getHistoryDiff', 'supplierAirlineMaskingTemplates/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineMaskingTemplatesController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineMaskingTemplatesController%') AND menu_id != 0);  

/*Suplier Airline masking Template*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules', 'index', 'SupplierAirlineMaskingRulesController@index', 'supplierAirlineMaskingRules/index', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules', 'list', 'SupplierAirlineMaskingRulesController@getList', 'supplierAirlineMaskingRules/list', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules', 'create', 'SupplierAirlineMaskingRulesController@create', 'supplierAirlineMaskingRules/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules', 'store', 'SupplierAirlineMaskingRulesController@store', 'supplierAirlineMaskingRules/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules', 'edit', 'SupplierAirlineMaskingRulesController@edit', 'supplierAirlineMaskingRules/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules', 'update', 'SupplierAirlineMaskingRulesController@update', 'supplierAirlineMaskingRules/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules', 'changeStatus', 'SupplierAirlineMaskingRulesController@changeStatus', 'supplierAirlineMaskingRules/changeStatus', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules', 'delete', 'SupplierAirlineMaskingRulesController@delete', 'supplierAirlineMaskingRules/delete', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules', 'getHistory', 'SupplierAirlineMaskingRulesController@getHistory', 'supplierAirlineMaskingRules/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules', 'getHistoryDiff', 'SupplierAirlineMaskingRulesController@getHistoryDiff', 'supplierAirlineMaskingRules/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineMaskingRulesController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineMaskingRulesController%') AND menu_id != 0);

/*Markup Contract  Permission*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Contract', 'index', 'MarkupContractController@index', 'markupContract/index', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Contract', 'list', 'MarkupContractController@list', 'markupContract/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Contract', 'create', 'MarkupContractController@create', 'markupContract/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Contract', 'storeContract', 'MarkupContractController@storeContract', 'markupContract/storeContract', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Contract', 'storeRules', 'MarkupContractController@storeRules', 'markupContract/storeRules', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Contract', 'updateContract', 'MarkupContractController@updateContract', 'markupContract/updateContract', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Contract', 'updateRules', 'MarkupContractController@updateRules', 'markupContract/updateRules', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Contract', 'editContract', 'MarkupContractController@editContract', 'markupContract/editContract', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Contract', 'editRules', 'MarkupContractController@editRules', 'markupContract/editRules', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Contract', 'conctractChangeStatus', 'MarkupContractController@conctractChangeStatus', 'markupContract/conctractChangeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Contract', 'conctractDelete', 'MarkupContractController@conctractDelete', 'markupContract/conctractDelete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Contract', 'ruleChangeStatus', 'MarkupContractController@ruleChangeStatus', 'markupContract/ruleChangeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Contract', 'ruleDelete', 'MarkupContractController@ruleDelete', 'markupContract/ruleDelete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Contract', 'getSupplierPosRuleList', 'MarkupContractController@getSupplierPosRuleList', 'markupContract/getSupplierPosRuleList', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Contract', 'copyRules', 'MarkupContractController@copyRules', 'markupContract/copyRules', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Contract', 'getHistory', 'MarkupContractController@getHistory', 'markupContract/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Contract', 'getHistoryDiff', 'MarkupContractController@getHistoryDiff', 'markupContract/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MarkupContractController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MarkupContractController%') AND menu_id != 0);

/*Ticketing Queue*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Queue'), 'Ticketing Queue', 'List', 'TicketingQueueController@list', 'ticketingQueue/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Queue'), 'Ticketing Queue', 'Index', 'TicketingQueueController@index', 'ticketingQueue/index', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Queue'), 'Ticketing Queue', 'view', 'TicketingQueueController@view', 'ticketingQueue/view', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Queue'), 'Ticketing Queue', 'removeFromQueueList', 'TicketingQueueController@removeFromQueueList', 'ticketingQueue/removeFromQueueList', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Queue'), 'Ticketing Queue', 'manualReview', 'TicketingQueueController@manualReview', 'ticketingQueue/manualReview', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Queue'), 'Ticketing Queue', 'manualReviewStore', 'TicketingQueueController@manualReviewStore', 'ticketingQueue/manualReviewStore', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%TicketingQueueController%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%TicketingQueueController%') AND menu_id != 0);

/*Offline Payment*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Extra Payment'), 'Offline Payment', 'index', 'OfflinePaymentController@index', 'offlinePayment/index', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Extra Payment'), 'Offline Payment', 'list', 'OfflinePaymentController@list', 'offlinePayment/list', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Extra Payment'), 'Offline Payment', 'view', 'OfflinePaymentController@view', 'offlinePayment/view', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Extra Payment'), 'Offline Payment', 'delete', 'OfflinePaymentController@delete', 'offlinePayment/delete', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%OfflinePaymentController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%OfflinePaymentController%') AND menu_id != 0);

/*Insuranace list*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Insurance'), 'Insurance Management', 'index', 'InsuranceBookingsController@index', 'insurance/index', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Insurance'), 'Insurance Management', 'list', 'InsuranceBookingsController@list', 'insurance/list', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Insurance'), 'Insurance Management', 'view', 'InsuranceBookingsController@view', 'insurance/view', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Insurance'), 'Insurance Management', 'retry', 'InsuranceBookingsController@retry', 'insurance/retry', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%InsuranceBookingsController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%InsuranceBookingsController%') AND menu_id != 0);

/*Invoice Statement*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements', 'index', 'InvoiceStatementController@index', 'invoiceStatement/index', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements', 'payableInvoiceList', 'InvoiceStatementController@payableInvoiceList', 'invoiceStatement/payableInvoiceList', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements', 'paidInvoiceList', 'InvoiceStatementController@paidInvoiceList', 'invoiceStatement/paidInvoiceList', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements', 'receivableInvoiceList', 'InvoiceStatementController@receivableInvoiceList', 'invoiceStatement/receivableInvoiceList', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements', 'receivedInvoiceList', 'InvoiceStatementController@receivedInvoiceList', 'invoiceStatement/receivedInvoiceList', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements', 'pendingInvoiceList', 'InvoiceStatementController@pendingInvoiceList', 'invoiceStatement/pendingInvoiceList', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements', 'approvedInvoiceList', 'InvoiceStatementController@approvedInvoiceList', 'invoiceStatement/approvedInvoiceList', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements', 'creditLimitCheck', 'InvoiceStatementController@creditLimitCheck', 'invoiceStatement/creditLimitCheck', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements', 'getInvoiceDetails', 'InvoiceStatementController@getInvoiceDetails', 'invoiceStatement/getInvoiceDetails', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements', 'getInvoiceBookingDetails', 'InvoiceStatementController@getInvoiceBookingDetails', 'invoiceStatement/getInvoiceBookingDetails', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements', 'getInvoicePaymentDetails', 'InvoiceStatementController@getInvoicePaymentDetails', 'invoiceStatement/getInvoicePaymentDetails', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Invoice Details'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Invoice Statements', 'payInvoice', 'InvoiceStatementController@payInvoice', 'invoiceStatement/payInvoice', '', 'N', 'A', 1, '2020-05-26 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%InvoiceStatementController%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%InvoiceStatementController%') AND menu_id != 0);

/*Booking Management*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Flight Booking Management', 'view', 'BookingsController@view', 'bookings/view', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Flight Booking Management', 'getBookingDetails', 'BookingsController@getBookingDetails', 'bookings/getBookingDetails', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Flight Booking Management', 'checkDuplicateTicketNumber', 'BookingsController@checkDuplicateTicketNumber', 'bookings/checkDuplicateTicketNumber', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Flight Booking Management', 'updateTicketNumber', 'BookingsController@updateTicketNumber', 'bookings/updateTicketNumber', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Flight Booking Management', 'bookingOfflinePayment', 'BookingsController@bookingOfflinePayment', 'bookings/bookingOfflinePayment', '', 'N', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController%') AND menu_id != 0);  

/* Venkatesan 19-March-2020 Route config Management Rules */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Route Config Management'), 'Route Config Rules Index', 'RouteConfigRulesController@index', 'routeConfigRules/list', '', 'Y', 'A', 1, '2020-03-19 00:00:00');

/*Karthick april 9 2020 invoice check pending approval */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management', 'checkInvoicePendingApproval', 'AgencyCreditManagementController@checkInvoicePendingApproval', 'agencyCredit/checkInvoicePendingApproval', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyCreditManagementController@checkInvoicePendingApproval%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyCreditManagementController@checkInvoicePendingApproval%') AND menu_id != 0);  
