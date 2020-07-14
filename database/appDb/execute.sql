/*Karthick April 9th 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contracts'), 'Contract Management' , 'edit contract', 'ContractManagementController@edit', 'contract/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContractManagementController@edit%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContractManagementController@edit%') AND menu_id != 0);  

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management', 'getAccountAggregationList', 'AgencyManageController@getAccountAggregationList', 'manageAgency/getAccountAggregationList', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management', 'saveAgentModelData', 'AgencyManageController@saveAgentModelData', 'manageAgency/saveAgentModelData', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyManageController@getAccountAggregationList%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyManageController@getAccountAggregationList%') AND menu_id != 0); 

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyManageController@saveAgentModelData%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyManageController@saveAgentModelData%') AND menu_id != 0); 

/* Divakar 10-Apr-2020 Permission */

UPDATE `menu_details` SET `new_icon` = 'settings1' WHERE `menu_name` = 'Settings';
UPDATE `menu_details` SET `new_icon` = 'person-plus' WHERE `menu_name` = 'Manage Agents';
UPDATE `menu_details` SET `new_icon` = 'manageagency' WHERE `menu_name` = 'Manage Agency';
UPDATE `menu_details` SET `new_icon` = 'portal1' WHERE `menu_name` = 'Portal Details';
UPDATE `menu_details` SET `new_icon` = 'filter_head' WHERE `menu_name` = 'Customer Management';
UPDATE `menu_details` SET `new_icon` = 'flight3' WHERE `menu_name` = 'Airline Manage';
UPDATE `menu_details` SET `new_icon` = 'supplier-setting' WHERE `menu_name` = 'Suppliers Settings';
UPDATE `menu_details` SET `new_icon` = 'flight-included' WHERE `menu_name` = 'Bookings';
UPDATE `menu_details` SET `new_icon` = 'portal1' WHERE `menu_name` = 'Portal Settings';
UPDATE `menu_details` SET `new_icon` = 'portal1' WHERE `menu_name` = 'CMS Settings';
UPDATE `menu_details` SET `new_icon` = 'add-file' WHERE `menu_name` = 'Ticketing Module';

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Flight Booking  Index', 'BookingsController@index', 'bookings/index', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Store Request', 'CommonController@storeRequest', 'storeRequest', '', 'Y', 'A', 1, '2020-03-21 00:00:00');


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Quote Request', 'InsuranceController@getSearchResponse', 'insurance/getSearchResponse', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Checkout', 'InsuranceController@getSelectedQutoe', 'insurance/getSelectedQutoe', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Booking', 'InsuranceController@insuranceBooking', 'insurance/booking', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

/*Karthick 10th April changes done*/

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MarkupTemplateController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MarkupTemplateController%') AND menu_id != 0);  

/*Karthick 10 april portal setting implemented*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Settings', 'create', 'PortalSettingsController@create', 'portalSetting/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Settings', 'store', 'PortalSettingsController@store', 'portalSetting/store', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalSettingsController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalSettingsController%') AND menu_id != 0); 

/* Divakar 10th April 2020 Insurance Promocode */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Promocode List', 'InsuranceController@getInsurancePromoCodeList', 'insurance/getInsurancePromoCodeList', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Apply Promocode', 'InsuranceController@applyInsurancePromoCode', 'insurance/applyInsurancePromoCode', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

/*Karthick 11th April 2020 Hotel*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Hotel getHotelPromoCodeList', 'HotelsController@getHotelPromoCodeList', 'hotels/getHotelPromoCodeList', '', 'Y', 'A', 1, '2020-05-27 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Hotel applyHotelPromoCode', 'HotelsController@applyHotelPromoCode', 'hotels/applyHotelPromoCode', '', 'Y', 'A', 1, '2020-05-27 00:00:00');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`,  `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Bookings', 'Get Pnr List', 'BookingsController@rescheduleGetPnrList', 'bookings/getPnrList', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Reschedule', 'Search', 'BookingsController@rescheduleSearch', 'bookings/rescheduleSearch', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Reschedule', 'Shopping', 'RescheduleController@getAirExchangeShopping', 'reschedule/getAirExchangeShopping', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Reschedule', 'Price', 'RescheduleController@getAirExchangeOfferPrice', 'reschedule/getAirExchangeOfferPrice', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Reschedule', 'Order Create', 'RescheduleController@getAirExchangeOrderCreate', 'reschedule/getAirExchangeOrderCreate', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Reschedule', 'Voucher', 'RescheduleController@voucher', 'reschedule/voucher', '', 'N', 'A', 1, '2020-03-21 00:00:00');


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RescheduleController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RescheduleController%') AND menu_id != 0);  


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@rescheduleGetPnrList%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@rescheduleGetPnrList%') AND menu_id != 0);  

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@rescheduleSearch%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@rescheduleSearch%') AND menu_id != 0);


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Booking', 'InsuranceBookingController@insuranceBooking', 'insurance/insuranceBooking', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

/* Venkatesan 11th Apirl 2020 Meta Logs */
INSERT INTO `menu_details` (`menu_id`, `menu_name`, `link`, `new_link`, `icon`, `new_icon`, `menu_type`, `status`) VALUES (NULL, 'Meta Logs', 'metaLogs', 'metaLogs', 'swap_vert', 'swap_vert', 'A', 'Y');

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'Meta Logs'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'No Submenu'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'No Submenu'), '0', '3', '3', 'Y', 'Y');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Meta Logs'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Meta Logs List', 'MetaLogController@index', 'metaLogs/list', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Meta Logs'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Meta Logs List', 'MetaLogController@getList', 'metaLogs/list', '', 'N', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Meta Logs'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Booking Detail For Search ID', 'MetaLogController@getBookingDetailForSearchID', 'metaLogs/getBookingDetailForSearchID', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MetaLogController%') AND menu_id != 0); 

/*Karthick 11 April Ticketing Queue implemented*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Queue'), 'Ticketing Queue', 'addToTicketingQueue', 'TicketingQueueController@addToTicketingQueue', 'ticketingQueue/addToTicketingQueue', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Ticketing Module'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Ticketing Queue'), 'Ticketing Queue', 'queueDataStore', 'TicketingQueueController@queueDataStore', 'ticketingQueue/queueDataStore', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%TicketingQueueController@addToTicketingQueue%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%TicketingQueueController@addToTicketingQueue%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%TicketingQueueController@queueDataStore%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%TicketingQueueController@queueDataStore%') AND menu_id != 0);

UPDATE `menu_mapping_details` SET `menu_order` = '10' WHERE `menu_mapping_details`.`menu_id` = 22;

/*KArthick 12th April 2020 Cancel Booking*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'),'Booking Management', 'Flight Booking  Cancel', 'BookingsController@cancelBooking', 'bookings/cancelBooking', '', 'N', 'A', 1, '2020-03-21 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@cancelBooking%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@cancelBooking%') AND menu_id != 0);  

/*Share Url Permission*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Flight ShareUrl','Flight ShareUrl', 'FlightsController@shareUrl', 'flights/shareUrl', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Management', 'list', 'AgencyManageController@index', 'manageAgency/list', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyManageController@index%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyManageController@index%') AND menu_id != 0);  


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Supplier Airline Masking', 'list', 'SupplierAirlineMaskingTemplatesController@list', 'supplierAirlineMaskingTemplates/list', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineMaskingTemplatesController@list%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineMaskingTemplatesController@list%') AND menu_id != 0);  

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Supplier Airline Masking', 'getMappedPartnerList', 'SupplierAirlineMaskingTemplatesController@getMappedPartnerList', 'supplierAirlineMaskingTemplates/getMappedPartnerList', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineMaskingTemplatesController@getMappedPartnerList%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineMaskingTemplatesController@getMappedPartnerList%') AND menu_id != 0);  

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 

(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Masking' AND link = 'supplierAirlineMaskingTemplates'), 'Airline Masking Rules', 'list', 'SupplierAirlineMaskingRulesController@list', 'supplierAirlineMaskingRules/list', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineMaskingRulesController@list%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineMaskingRulesController@list%') AND menu_id != 0);

/* Seenivasan 12 April 2020 Supplier Airline Blocking Rules History */


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking'  AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Rules Get History', 'SupplierAirlineBlockingRulesController@getHistory', 'supplierAirlineBlockingTemplates/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking'  AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Rules Get History Diff', 'SupplierAirlineBlockingRulesController@getHistoryDiff', 'supplierAirlineBlockingTemplates/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineBlockingRulesController@getHistory%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineBlockingRulesController@getHistory%') AND menu_id != 0);

/* Seenivasan 20 March 2020 Supplier Airline Blocking Templates */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Template Get History', 'SupplierAirlineBlockingTemplatesController@getHistory', 'supplierAirlineBlockingTemplates/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Blocking' AND link ='supplierAirlineBlockingTemplates'), 'Airline Blocking Template Get History Diff', 'SupplierAirlineBlockingTemplatesController@getHistoryDiff', 'supplierAirlineBlockingTemplates/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineBlockingTemplatesController@getHistory%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierAirlineBlockingTemplatesController@getHistory%') AND menu_id != 0);

/* Divakar 12-Apr-2020 Permission */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Insurance Confirm', 'InsuranceBookingController@bookingConfirm', 'insurance/bookingConfirm', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Initiate Payment', 'CommonController@initiatePayment', 'initiatePayment', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Check Status', 'CommonController@checkBookingStatus', 'checkBookingStatus', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'PG Response', 'PaymentGatewayController@pgResponse', 'pgResponse', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Payment Failed', 'PaymentGatewayController@paymentFailed', 'paymentFailed', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

/*Karthick 13th April 2020 Permission update*/

UPDATE `permissions` SET `submenu_id`= (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contract Groups') WHERE permission_route LIKE "%ContractManagementController@%";
UPDATE `permissions` SET `submenu_id`= (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Group Details') WHERE permission_route LIKE "%UserGroupsController@%";

/*Karthick 13th April 20202*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management', 'getMappedAgencyDetails', 'AgencyCreditManagementController@getMappedAgencyDetails', 'agencyCredit/getMappedAgencyDetails', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyCreditManagementController@getMappedAgencyDetails%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyCreditManagementController@getMappedAgencyDetails%') AND menu_id != 0);  

/* Divakar 14-Apr-2020 Permission */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Flight','Checkout Data', 'FlightsController@getCheckoutData', 'flights/getCheckoutData', '', 'Y', 'A', 1, '2018-05-08 00:00:00');


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Low Fare Search','Search', 'LowFareSearchController@lowFareSearch', 'lowFareSearch/search', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Low Fare Search','Price', 'LowFareSearchController@checkPrice', 'lowFareSearch/checkPrice', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%LowFareSearchController%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%LowFareSearchController%') AND menu_id != 0);  

/* Divakar 14-Apr-2020 Hotel Permission */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Hotel Checkout', 'HotelsController@hotelCheckoutData', 'hotels/hotelCheckoutData', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

/*Karthick 14th April 2020*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates', 'edit', 'MarkupTemplateController@templateEdit', 'markupTemplate/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates', 'copy', 'MarkupTemplateController@templateEdit', 'markupTemplate/copy', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MarkupTemplateController@templateEdit%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MarkupTemplateController@templateEdit%') AND menu_id != 0);  


/* Divakar 14-Apr-2020 Hotel Permission */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Hotel Booking', 'HotelBookingController@hotelBooking', 'hotels/hotelBooking', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

/* Venkatesan 15-Apr-2020 Permission */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Markup Templates'), 'Markup Templates', 'getSurchargeList', 'MarkupTemplateController@getSurchargeList', 'markupTemplate/getSurchargeList', '', 'N', 'A', 1, '2020-03-20 00:00:00');
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MarkupTemplateController@getSurchargeList%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MarkupTemplateController@getSurchargeList%') AND menu_id != 0);  

/*Karthick April 15th 2020 issue */
INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'CMS Settings'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Footer links and pages'), '0','5', '1', '1', 'Y', 'Y');

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='AO'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'CMS Settings'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Footer links and pages'), '0','5', '1', '1', 'Y', 'Y');


INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'CMS Settings'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Add Benefits'), '0','6', '1', '1', 'Y', 'Y');

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='AO'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'CMS Settings'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Add Benefits'), '0','6', '1', '1', 'Y', 'Y');

INSERT INTO `menu_details` (`menu_id`, `menu_name`, `link`, `new_link`, `icon`, `menu_type`, `status`) VALUES (NULL, 'Show Login History', 'showLoginHistory', 'showLoginHistory', 'accessibility', 'S', 'Y');

INSERT INTO `menu_details` (`menu_id`, `menu_name`, `link`, `new_link`, `icon`, `menu_type`, `status`) VALUES (NULL, 'User Referral', 'userReferral', 'userReferral', 'insert_link', 'A', 'Y');

DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%UserReferralController%" AND permission_url IS NOT NULL)x);
DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%LoginController@index%" AND permission_url IS NOT NULL)x);
DELETE FROM role_permission_mapping WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%LoginController@showLoginHistory%" AND permission_url IS NOT NULL)x);
DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%UserReferralController%" AND permission_url IS NOT NULL)x);
DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%LoginController@index%" AND permission_url IS NOT NULL)x);
DELETE FROM permissions WHERE permission_id IN (SELECT temp_id FROM (SELECT permission_id AS temp_id FROM permissions where permission_route LIKE "%LoginController@showLoginHistory%" AND permission_url IS NOT NULL)x);


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`,  `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Referral'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Referral', ' Delete', 'UserReferralController@delete', 'userReferral/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Referral'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Referral', 'Save', 'UserReferralController@store', 'userReferral/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Referral'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Referral', 'Create', 'UserReferralController@create', 'userReferral/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Referral'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Referral', 'List', 'UserReferralController@list', 'userReferral/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Referral'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Referral', 'Index', 'UserReferralController@index', 'userReferral/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00');


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`,  `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Referral'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Referral', 'Api List', 'UserReferralController@getReferralList', 'userReferral/getReferralList', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Referral'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Referral', 'Api Store', 'UserReferralController@referralStore', 'userReferral/referralStore', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'User Referral'), '1', '0','6', '1', '1', 'Y', 'Y');

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='AO'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'User Referral'), '1', '0','6', '1', '1', 'Y', 'Y');


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserReferralController%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserReferralController%') AND menu_id != 0);


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`,  `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Login History'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Login History', 'index', 'LoginController@index', 'showLoginHistoryIndex', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Login History'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Login History', 'List', 'LoginController@showLoginHistory', 'showLoginHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%LoginController@showLoginHistory%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%LoginController@showLoginHistory%') AND menu_id != 0);

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'Show Login History'), '1', '0','6', '1', '1', 'Y', 'Y');

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='AO'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'Show Login History'), '1', '0','6', '1', '1', 'Y', 'Y');


/* Seenivasan 15-April- 2020 Airline Info  */

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'Route Pages'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Airline Info'), '0','4', '1', '1', 'Y', 'Y');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Info'), 'Airline Info Update', 'AirlineInfoController@update', 'airlineInfo/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Info'), 'Airline Info ChangeStatus', 'AirlineInfoController@changeStatus', 'airlineInfo/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Info'), 'Airline Info Delete', 'AirlineInfoController@delete', 'airlineInfo/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Info'), 'Airline Info Edit', 'AirlineInfoController@edit', 'airlineInfo/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Info'), 'Airline Info Save', 'AirlineInfoController@store', 'airlineInfo/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Info'), 'Airline Info Create', 'AirlineInfoController@create', 'airlineInfo/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Info'), 'Airline Info List', 'AirlineInfoController@list', 'airlineInfo/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airline Info'), 'Airline Info Index', 'AirlineInfoController@index', 'airlineInfo/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirlineInfoController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirlineInfoController%') AND menu_id != 0);


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Banner Section'), 'Banner Section Index', 'BannerSectionController@getIndex', 'bannerSection/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route ='BannerSectionController@getIndex') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route ='BannerSectionController@getIndex') AND menu_id != 0);


/* Divakar 16-Apr-2020 get Request Permission */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Requst', 'CommonController@getRequest', 'getRequest', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

/*Karthick 16th Apr 2020 Icon update*/

UPDATE `menu_details` SET `new_icon` = 'login' WHERE `menu_details`.`menu_name` = 'Login History';
UPDATE `menu_details` SET `new_icon` = 'invoice-statement' WHERE `menu_details`.`menu_name` = 'Invoice Details';
UPDATE `menu_details` SET `new_icon` = 'log' WHERE `menu_details`.`menu_name` = 'Meta Logs';

/* Seenivasan 16-April- 2020 Airport Info  */

INSERT INTO `menu_mapping_details` (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL, (SELECT role_id FROM user_roles WHERE role_code ='SA'), (SELECT `menu_id` from `menu_details` where `menu_name` = 'Route Pages'), (SELECT `submenu_id` from `submenu_details` where `submenu_name` = 'Airport Info'), '0','5', '1', '1', 'Y', 'Y');

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Info'), 'Airport Info Update', 'AirportInfoController@update', 'airportInfo/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Info'), 'Airport Info ChangeStatus', 'AirportInfoController@changeStatus', 'airportInfo/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Info'), 'Airport Info Delete', 'AirportInfoController@delete', 'airportInfo/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Info'), 'Airport Info Edit', 'AirportInfoController@edit', 'airportInfo/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Info'), 'Airport Info Save', 'AirportInfoController@store', 'airportInfo/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Info'), 'Airport Info Create', 'AirportInfoController@create', 'airportInfo/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Info'), 'Airport Info List', 'AirportInfoController@list', 'airportInfo/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Route Pages'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Info'), 'Airport Info Index', 'AirportInfoController@index', 'airportInfo/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirportInfoController%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AirportInfoController%') AND menu_id != 0);

/*Karthick 16th apr 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management', 'store', 'AgencyCreditManagementController@store', 'agencyCredit/creditManagementTransactionList', '', 'N', 'A', 1, '2020-03-20 00:00:00');


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyCreditManagementController@store%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyCreditManagementController@store%') AND menu_id != 0);  


/* Seenivasan 17-April- 2020 Route url genarator */

ALTER TABLE `route_url_generator` ADD `meta_description` VARCHAR(255) NOT NULL AFTER `page_title`;

ALTER TABLE `route_url_generator` ADD `meta_image` VARCHAR(255) NOT NULL AFTER `meta_description`;

ALTER TABLE `route_url_generator` ADD `image_original_name` VARCHAR(255) NOT NULL AFTER `meta_image`;

ALTER TABLE `route_url_generator` ADD `image_saved_location` VARCHAR(255) NOT NULL AFTER `image_original_name`;


/* Seenivasan 17-April- 2020 User referral */


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='User Referral'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'User Referral Api Store', 'UserReferralController@updateReferralStatus', 'userReferral/updateStatus', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='MA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserReferralController@updateReferralStatus%') AND menu_id != 0);


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserReferralController@updateReferralStatus%') AND menu_id != 0);

/*Karthick 18th april */
UPDATE `submenu_details` SET `new_link` = 'invoiceStatement' WHERE `menu_details`.`menu_name` = 'Invoice Details';


/* Divakar 21 April 2020 1:00 AM Seat Map Table Query */

CREATE TABLE `seat_map_markup_details` (
  `seat_map_markup_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `consumer_account_id` int(11) NOT NULL,
  `markup_details` mediumtext NOT NULL,
  `status` enum('A','IA','D') NOT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `seat_map_markup_details`
  ADD PRIMARY KEY (`seat_map_markup_id`);


ALTER TABLE `seat_map_markup_details`
  MODIFY `seat_map_markup_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;


 INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Flight'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Seat Map', 'FlightsController@airSeatMapRq', 'flights/airSeatMapRq', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

/*Karthick 21st april 2020*/

UPDATE `submenu_details` SET `new_link` = 'invoiceStatement' WHERE `submenu_details`.`submenu_name` = 'Invoice Details';

/*Karthick 21st April 2020*/

UPDATE `menu_details` SET `new_icon` = 'flight' WHERE `menu_details`.`menu_name` = 'Flight';
UPDATE `menu_details` SET `new_icon` = 'route-page' WHERE `menu_details`.`menu_name` = 'Route Pages';
UPDATE `menu_details` SET `new_icon` = 'usertravellers' WHERE `menu_details`.`menu_name` = 'User Travellers';
UPDATE `menu_details` SET `new_icon` = 'user-referral' WHERE `menu_details`.`menu_name` = 'User Referral';

/*KArthick 22nd April 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Split PNR' , 'Split Pnr', 'RescheduleController@splitPassengerPnr', 'Reschedule/splitPnr', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RescheduleController@splitPassengerPnr%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%RescheduleController@splitPassengerPnr%') AND menu_id != 0);

/*Karthick 23rd April 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Management'), 'Assign Supplier Get History', 'SupplierMappingController@getHistory', 'manageAgency/supplier/getHistory', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

/*Divakar 23rd April 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Flight Insurance Quote', 'InsuranceController@getFlightInsuranceQuote', 'insurance/getFlightInsuranceQuote', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

/*Karthick 23rd April 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Content Source Management'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contract Groups'), 'Contract Management', 'index', 'ContractManagementController@assignToTemplateList', 'contract/templateAssign/assignToTemplateList', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Content Source Management'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contract Groups'), 'Contract Management', 'list', 'ContractManagementController@unAssignToTemplateList', 'contract/templateAssign/unAssignToTemplateList', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContractManagementController@assignToTemplateList%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContractManagementController@assignToTemplateList%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContractManagementController@unAssignToTemplateList%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%ContractManagementController@unAssignToTemplateList%') AND menu_id != 0);

/*Karthick 23rd April 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Booking count', 'Get Booking Count', 'CommonController@getBookingsCount', '/getBookingsCount', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

/*Seenivasan 23-April-2020 Seat Mapping*/
ALTER TABLE `seat_map_markup_details` CHANGE `consumer_account_id` `consumer_account_id` VARCHAR(255) NOT NULL;

INSERT INTO `submenu_details` (`submenu_id`, `submenu_name`, `link`, `new_link`, `icon`, `new_icon`, `sub_menu_type`, `status`) VALUES
(NULL, 'Seat Mapping', 'seatMapping', 'seatMapping', 'settings1', 'settings1', 'A', 'Y');


INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Seat Mapping'),0,5,0,5,'Y','Y');


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`,`permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Seat Mapping'), 'Seat Mapping','Seat Mapping History Diff', 'SeatMappingController@getHistoryDiff', 'seatMapping/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Seat Mapping'), 'Seat Mapping','Seat Mapping History', 'SeatMappingController@getHistory', 'seatMapping/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Seat Mapping'), 'Seat Mapping','Seat Mapping Update', 'SeatMappingController@update', 'seatMapping/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Seat Mapping'), 'Seat Mapping','Seat Mapping ChangeStatus', 'SeatMappingController@changeStatus', 'seatMapping/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Seat Mapping'), 'Seat Mapping','Seat Mapping Delete', 'SeatMappingController@delete', 'seatMapping/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Seat Mapping'), 'Seat Mapping','Seat Mapping Edit', 'SeatMappingController@edit', 'seatMapping/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Seat Mapping'), 'Seat Mapping','Seat Mapping Save', 'SeatMappingController@store', 'seatMapping/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Seat Mapping'), 'Seat Mapping','Seat Mapping Create', 'SeatMappingController@create', 'seatMapping/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Seat Mapping'), 'Seat Mapping','Seat Mapping List', 'SeatMappingController@list', 'seatMapping/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Suppliers Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Seat Mapping'), 'Seat Mapping','Seat Mapping Index', 'SeatMappingController@index', 'seatMapping/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SeatMappingController%') AND menu_id != 0);

/*Karthick april 24 work update*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Management'), 'Agency Credit Management', 'getCreditTransactionIndex', 'AgencyCreditManagementController@getCreditTransactionIndex', 'agencyCredit/getCreditTransactionIndex', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyCreditManagementController@getCreditTransactionIndex%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyCreditManagementController@getCreditTransactionIndex%') AND menu_id != 0);

/*Karthick April 24 */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'),'Get Meta Response', 'Flight Get Meta Response', 'FlightBookingsController@getMetaResponse', 'flights/getMetaResponse', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

/* Seenivasan 24-April-2020 User Groups */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Groups'), 'User Group', 'parentGroup', 'UserGroupsController@parentGroup', 'userGroups/parentGroup', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserGroupsController@parentGroup%') AND menu_id != 0);


/* Divakar 24 - April - 2020 Permission */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'),'PenAir AccountApi', 'PenAir AccountApi', 'AccountApiController@index', 'accountApi', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'),'PenAir Call AccountApi', 'PenAir Call AccountApi', 'AccountApiController@callAccountApi', 'callAccountApi', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

/*Karthick April 25th 2020*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Setting'), 'Agency Settings Management', 'create', 'AgencySettingsController@create', 'agencySettings/create', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Setting'), 'Agency Settings Management', 'store', 'AgencySettingsController@store', 'agencySettings/store', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Setting'), 'Agency Settings Management', 'edit', 'AgencySettingsController@edit', 'agencySettings/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Setting'), 'Agency Settings Management', 'update', 'AgencySettingsController@update', 'agencySettings/update', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Setting'), 'Agency Settings Management', 'History', 'AgencySettingsController@getHistory', 'agencySettings/getHistory', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Setting'), 'Agency Settings Management', 'History Diff', 'AgencySettingsController@getHistoryDiff', 'agencySettings/getHistoryDiff', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Setting'), 'Agency Settings Management', 'sendTestMail', 'AgencySettingsController@sendTestMail', 'agencySettings/edit', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencySettingsController%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencySettingsController%') AND menu_id != 0);

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`,`permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Dashboard','getTopCities', 'RoutePageController@getTopCities', 'getTopCities', '', 'Y', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Dashboard','getRouteOriginDestination', 'RoutePageController@getRouteOriginDestination', 'getRouteOriginDestination', '', 'Y', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Dashboard','getTopFlights', 'RoutePageController@getTopFlights', 'getTopFlights', '', 'Y', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Dashboard','getTopFlights', 'RoutePageController@getOriginDestinationContent', 'getOriginDestinationContent', '', 'Y', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Dashboard','RoutePageController', 'RoutePageController@getTopDealsAndCities', 'getTopDealsAndCities', '', 'Y', 'A', 1, '2020-05-26 00:00:00');


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Management'), 'Agency Management', 'updatePortalAggregation', 'AccountDetailsController@updatePortalAggregation', 'manageAgency/updatePortalAggregation', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Management'), 'Agency Management', 'status update', 'AgencyManageController@changeStatus', 'manageAgency/changeStatus', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AccountDetailsController@updatePortalAggregation%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AccountDetailsController@updatePortalAggregation%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyManageController@changeStatus%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyManageController@changeStatus%') AND menu_id != 0);

/* Divakar 27 - April - 2020 Flight Booking */

ALTER TABLE `flight_passenger` ADD `onfly_details` MEDIUMTEXT NULL AFTER `seats`;

/*Karthick 27th April 2020 terminal Page*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`,`permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Terminal Booking App','login', 'TerminalAppController@terminalLoginPage', 'terminalBooking/terminalLoginPage', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Terminal Booking App','submit login', 'TerminalAppController@terminalLoginSubmit', 'terminalBooking/terminalLoginSubmit', '', 'N', 'A', 1, '2020-05-26 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Terminal Booking App','command execution', 'TerminalAppController@terminalCommandExecute', 'terminalBooking/terminalCommandExecute', '', 'N', 'A', 1, '2020-05-26 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%TerminalAppController%') AND menu_id != 0);

/* Divakar 27 - April - 2020 Flight Booking */

ALTER TABLE `booking_contact` ADD `contact_ref` VARCHAR(10) NULL DEFAULT NULL AFTER `booking_master_id`;
ALTER TABLE `login_activities` CHANGE `version` `version` TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;

/* Divakr 30 - April - 2020 */


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Add Payment', 'Add Payment', 'BookingsController@addPayment', 'bookings/addPayment', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_url like '%bookings/addPayment%') AND menu_id != 0);


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Add Payment', 'Add payNow', 'BookingsController@payNow', 'bookings/payNow', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_url like '%bookings/payNow%') AND menu_id != 0);


/* Divakr 01 - May - 2020 */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Extra Payment', 'Extra Payment', 'MakePaymentController@makePayment', 'makepayment/makePayment', '', 'Y', 'A', 1, '2020-03-25 00:00:00');

/*Karthick 27th April 2020 Hotel Hold to Confirm*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`,`permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Hotel'), 'Hotel Booking', 'View','HotelBookingsController@hotelHoldToConfirmBooking', 'bookings/hotelHoldToConfirmBooking', '', 'N', 'A', 1, '2020-03-26 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%HotelBookingsController@hotelHoldToConfirmBooking%') AND menu_id != 0);

/* Divakr 04 - May - 2020 settlement */

ALTER TABLE `supplier_wise_booking_total` ADD `settlement_currency` CHAR(4) NULL DEFAULT NULL AFTER `converted_currency`;
ALTER TABLE `supplier_wise_booking_total` ADD `settlement_exchange_rate` decimal(14,4) NULL DEFAULT 1.00 AFTER `settlement_currency`;

UPDATE `supplier_wise_booking_total` SET `settlement_currency`=`converted_currency`, `settlement_exchange_rate` = `converted_exchange_rate` WHERE 1;

ALTER TABLE `supplier_wise_hotel_booking_total` ADD `settlement_currency` CHAR(4) NULL DEFAULT NULL AFTER `converted_currency`;
ALTER TABLE `supplier_wise_hotel_booking_total` ADD `settlement_exchange_rate` decimal(14,4) NULL DEFAULT 1.00 AFTER `settlement_currency`;

UPDATE `supplier_wise_hotel_booking_total` SET `settlement_currency`=`converted_currency`, `settlement_exchange_rate` = `converted_exchange_rate` WHERE 1;

ALTER TABLE `ltbr_supplier_wise_booking_total` ADD `settlement_currency` CHAR(4) NULL DEFAULT NULL AFTER `converted_currency`;
ALTER TABLE `ltbr_supplier_wise_booking_total` ADD `settlement_exchange_rate` decimal(14,4) NULL DEFAULT 1.00 AFTER `settlement_currency`;

UPDATE `ltbr_supplier_wise_booking_total` SET `settlement_currency`=`converted_currency`, `settlement_exchange_rate` = `converted_exchange_rate` WHERE 1;

ALTER TABLE `insurance_supplier_wise_booking_total` ADD `settlement_currency` CHAR(4) NULL DEFAULT NULL AFTER `converted_currency`;
ALTER TABLE `insurance_supplier_wise_booking_total` ADD `settlement_exchange_rate` decimal(14,4) NULL DEFAULT 1.00 AFTER `settlement_currency`;

UPDATE `insurance_supplier_wise_booking_total` SET `settlement_currency`=`converted_currency`, `settlement_exchange_rate` = `converted_exchange_rate` WHERE 1;

ALTER TABLE `booking_total_fare_details` ADD `settlement_currency` CHAR(4) NULL DEFAULT NULL AFTER `converted_currency`;
ALTER TABLE `booking_total_fare_details` ADD `settlement_exchange_rate` decimal(14,4) NULL DEFAULT 1.00 AFTER `settlement_currency`;

UPDATE `booking_total_fare_details` SET `settlement_currency`=`converted_currency`, `settlement_exchange_rate` = `converted_exchange_rate` WHERE 1;




/* Divakar 08 - May - 2020 */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Bookings', 'PayNow Post', 'BookingsController@payNowPost', 'bookings/payNowPost', '', 'N', 'A', 1, '2020-03-21 00:00:00');


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@payNowPost%') AND menu_id != 0);

/* Seenivasan 12-May-2020*/
UPDATE `permissions` SET `is_public` = 'N' , `permission_group`='Contact Us' , `permission_name`='Index' , `menu_id`=(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings') , `submenu_id`=(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contact Us') WHERE `permission_route`='ContactUsController@list';
UPDATE `permissions` SET `is_public` = 'N' , `permission_group`='Contact Us' , `permission_name`='List' , `menu_id`=(SELECT menu_id FROM menu_details WHERE menu_name ='CMS Settings') , `submenu_id`=(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Contact Us') WHERE `permission_route`='ContactUsController@index';


UPDATE `permissions` SET `permission_group` = 'Portal Credentials' WHERE `permission_route` LIKE '%PortalCredentialsController%';

UPDATE `permissions` SET `permission_name` = 'Save' WHERE `permission_name` LIKE '%store%';

UPDATE `permissions` SET `permission_name` = 'History' WHERE `permission_name`='Get History';

UPDATE `permissions` SET `permission_name` = 'History Diff' WHERE `permission_name`='Get History Diff';

UPDATE `permissions` SET `menu_id` = (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency') WHERE `permission_route` LIKE '%UserManagementController@getHistory%';

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Common','Common Route', 'ContactUsController@getIndex', 'contactUs/store', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

/* Seenivasan 29-May-2020*/
ALTER TABLE `route_url_generator` ADD `return_days` INT(5) NOT NULL AFTER `no_of_days`;

/* Seenivasan 29-May-2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Roles'), 'User Roles','History', 'UserRolesController@getHistory', 'userRoles/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Roles'), 'User Roles','History Diff', 'UserRolesController@getHistoryDiff', 'userRoles/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserRolesController@getHistory%') AND menu_id != 0);

/* Seenivasan 04-Jun-2020*/

ALTER TABLE `user_referral_details` ADD `type` ENUM('B2B','B2C') NOT NULL AFTER `referral_by`;

UPDATE `menu_mapping_details` SET `menu_id`=(SELECT menu_id FROM menu_details WHERE menu_name ='Settings') WHERE`submenu_id`=(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Airport Settings');

/* Seenivasan 06-Jun-2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Common','Common Route', 'UserManagementController@getUserRole', 'getUserRole', '', 'Y', 'A', 1, '2018-05-08 00:00:00');


/*Karthick  16th june - 2020*/
ALTER TABLE `portal_details` ADD `allow_seat_mapping` ENUM('0','1') NOT NULL DEFAULT '0' AFTER `insurance_setting`, ADD `allow_hotel` ENUM('0','1') NOT NULL DEFAULT '0' AFTER `allow_seat_mapping`; 

/* Seenivasan 19-Jun-2020*/

UPDATE `permissions` SET `permission_group` = 'Reward Points Transaction ' WHERE `permission_route` LIKE '%RewardPointTransactionController%'

/* Seenivasan 19-Jun-2020 Currency Details*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Common','Common Route', 'AccountDetailsController@getCurrecyDetails', 'getAccountCurrency', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

/*Karthick 19th june 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Currency Exchange Rate'), 'Currency Exchange Rate','Upload Exchange Rate', 'CurrencyExchangeRateController@uploadExchangeRate', 'currencyExchangeRate/uploadExchangeRate', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Currency Exchange Rate'), 'Currency Exchange Rate','Export Exchange Rate', 'CurrencyExchangeRateController@exportExchangeRate', 'currencyExchangeRate/exportExchangeRate', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%CurrencyExchangeRateController@uploadExchangeRate%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%CurrencyExchangeRateController@exportExchangeRate%') AND menu_id != 0);

ALTER TABLE `currency_exchange_rate` CHANGE `portal_id` `portal_id` TINYTEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT '0' COMMENT 'Portal id may comma separated value for each portal or 0 or individual'; 

UPDATE `currency_exchange_rate` SET `portal_id` = '0' WHERE `currency_exchange_rate`.`portal_id` = '';

/* Seenivasan 19-Jun-2020*/

ALTER TABLE `seat_map_markup_details` CHANGE `consumer_account_id` `consumer_account_id` VARCHAR(255) NOT NULL;

/* Seenivasan 20-Jun-2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Common','Common Route', 'SeatMappingController@getConsumerDetails', 'getConsumerDetails', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

/*Karthick june 23rd Booking Fee*/

/*submenu */
INSERT INTO `submenu_details` (`submenu_id`, `submenu_name`, `link`, `new_link`, `icon`, `new_icon`, `sub_menu_type`, `status`) VALUES
(NULL, 'Booking Fee Management', 'bookingFee', 'bookingFee', 'flight', 'flight', 'A', 'N');

/*menu mapping SA */
INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Booking Fee Management'),0,6,0,12,'Y','Y');

/*menu mapping AO */
INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Booking Fee Management'),0,6,0,12,'Y','Y');

/*Permission*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Booking Fee Management'), 'Booking Fee Management','Index', 'BookingFeeTemplateController@index', 'bookingFee/index', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Booking Fee Management'), 'Booking Fee Management','List', 'BookingFeeTemplateController@list', 'bookingFee/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Booking Fee Management'), 'Booking Fee Management','Create', 'BookingFeeTemplateController@create', 'bookingFee/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Booking Fee Management'), 'Booking Fee Management','Store', 'BookingFeeTemplateController@store', 'bookingFee/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Booking Fee Management'), 'Booking Fee Management','Edit', 'BookingFeeTemplateController@edit', 'bookingFee/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Booking Fee Management'), 'Booking Fee Management','Update', 'BookingFeeTemplateController@update', 'bookingFee/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Booking Fee Management'), 'Booking Fee Management','Change Status', 'BookingFeeTemplateController@changeStatus', 'bookingFee/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Booking Fee Management'), 'Booking Fee Management','Delete', 'BookingFeeTemplateController@delete', 'bookingFee/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Booking Fee Management'), 'Booking Fee Management','Get History', 'BookingFeeTemplateController@getHistory', 'bookingFee/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Booking Fee Management'), 'Booking Fee Management','Get History Difference', 'BookingFeeTemplateController@getHistoryDiff', 'bookingFee/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingFeeTemplateController%') AND menu_id != 0);

/*Karthick june 24th 2020*/
ALTER TABLE `booking_fee_templates` CHANGE `account_id` `account_id` VARCHAR(255) NOT NULL; 

/*KArthick june 24th 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Backend Data','Form data', 'BackendDetailsController@getformData', 'backendData/getformData', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Backend Data','Encryption', 'BackendDetailsController@getEncryptionData', 'backendData/getEncryptionData', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Backend Data','SQL', 'BackendDetailsController@getSqlResults', 'backendData/getSqlResults', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BackendDetailsController%') AND menu_id != 0);

/*Kathick june 24th Supplier Remark Template*/

CREATE TABLE `supplier_remark_templates` (
`supplier_remark_template_id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
`supplier_account_id` int(11) NOT NULL,
`consumer_account_id` tinytext NOT NULL COMMENT 'Consumer id may comma separated value for each consumers or 0 or individual',
`parent_id` int(11) NULL DEFAULT '0',
`template_name` varchar(100) NOT NULL,
`gds_source` varchar(30) NOT NULL,
`content_source_id` tinytext NOT NULL COMMENT 'Content sourceid may comma separated value for each Content',
`priority` int(11) NULL DEFAULT '1',
`remark_control` text,
`itinerary_remark_list` text,
`selected_criterias` text,
`criterias` text,
`status` enum('A','IA','D') NOT NULL,
`created_by` int(11) NOT NULL,
`updated_by` int(11) NOT NULL,
`created_at` datetime NOT NULL,
`updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/*submenu */
INSERT INTO `submenu_details` (`submenu_id`, `submenu_name`, `link`, `new_link`, `icon`, `new_icon`, `sub_menu_type`, `status`) VALUES
(NULL, 'Supplier Remark Template', 'supplierRemarkTemplate', 'supplierRemarkTemplate', 'flight', 'flight', 'A', 'N');

/*menu mapping SA */
INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier Remark Template'),0,6,0,13,'Y','Y');

/*menu mapping AO */
INSERT INTO menu_mapping_details (`menu_mapping_id`, `role_id`, `menu_id`, `submenu_id`, `submenu_parent_id`, `menu_order`, `submenu_position`, `submenu_order`, `menu_status`, `submenu_status`) VALUES 
(NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),(SELECT menu_id FROM menu_details WHERE menu_name ='Settings'),(SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier Remark Template'),0,6,0,13,'Y','Y');

/*Permission*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier Remark Template'), 'Supplier Remark Template','Index', 'SupplierRemarkTemplateController@index', 'supplierRemarkTemplate/index', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier Remark Template'), 'Supplier Remark Template','List', 'SupplierRemarkTemplateController@list', 'supplierRemarkTemplate/list', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier Remark Template'), 'Supplier Remark Template','Create', 'SupplierRemarkTemplateController@create', 'supplierRemarkTemplate/create', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier Remark Template'), 'Supplier Remark Template','Store', 'SupplierRemarkTemplateController@store', 'supplierRemarkTemplate/store', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier Remark Template'), 'Supplier Remark Template','Edit', 'SupplierRemarkTemplateController@edit', 'supplierRemarkTemplate/edit', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier Remark Template'), 'Supplier Remark Template','Update', 'SupplierRemarkTemplateController@update', 'supplierRemarkTemplate/update', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier Remark Template'), 'Supplier Remark Template','Change Status', 'SupplierRemarkTemplateController@changeStatus', 'supplierRemarkTemplate/changeStatus', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier Remark Template'), 'Supplier Remark Template','Delete', 'SupplierRemarkTemplateController@delete', 'supplierRemarkTemplate/delete', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier Remark Template'), 'Supplier Remark Template','Get History', 'SupplierRemarkTemplateController@getHistory', 'supplierRemarkTemplate/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Supplier Remark Template'), 'Supplier Remark Template','Get History Difference', 'SupplierRemarkTemplateController@getHistoryDiff', 'supplierRemarkTemplate/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%SupplierRemarkTemplateController%') AND menu_id != 0);

/*Karthick june 25th*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Users Traveller', 'Common Route', 'UserTravellerController@getUserTraveller', 'getUserTraveller', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Users Traveller', 'Common Route', 'UserTravellerController@store', 'getUserTravellerStore', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Users Traveller', 'Common Route', 'UserTravellerController@edit', 'getUserTravellerEdit', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Users Traveller', 'Common Route', 'UserTravellerController@update', 'getUserTravellerUpdate', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Users Traveller', 'Common Route', 'UserTravellerController@delete', 'getUserTravellerDelete', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Users Traveller', 'Common Route', 'UserTravellerController@searchUserTravellersDetails', 'searchUserTravellersDetails', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

/*Divakar june 27th*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES (NULL, '1', '1', 'ERunActions ', 'Common minioLog', 'ERunActionsController@minioLog', 'minioLog', '', 'Y', 'A', '1', '2020-03-21 00:00:00');

/*KArthick june 27th 2020*/

ALTER TABLE `account_details` ADD `available_country_language` VARCHAR(255) NULL DEFAULT '[\"EN\"]' AFTER `payment_gateway_ids`; 

/*Karthick june 28th 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Meta Logs'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Meta Logs','Export', 'MetaLogController@exportMetaLog', 'metaLogs/exportMetaLog', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`)
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%MetaLogController@exportMetaLog%') AND menu_id != 0);

/* Seenivasan 27-Jun-2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Group Details'), 'User Group Details', 'History', 'UserGroupsController@getHistory', 'userGroups/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'User Group Details'), 'User Group Details', 'HistoryDiff', 'UserGroupsController@getHistoryDiff', 'userGroups/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserGroupsController@getHistory%') AND menu_id != 0);

/*Karthick june 29th */

CREATE TABLE `import_pnr_aggregation_mapping` (
  `import_pnr_aggregation_mapping_id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `supplier_account_id` int(11) NOT NULL,
  `gds_source` varchar(30) NOT NULL,
  `pcc` varchar(30) DEFAULT NULL,
  `content_source_id` varchar(255) NOT NULL,
  `status` enum('A','IA','') NOT NULL DEFAULT 'A',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_by` int(11) NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/*Import pnr permission*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Management'), 'Agency Management', 'Get Import Pnr Form', 'AgencyManageController@getImportPnrForm', 'manageAgency/getImportPnrForm', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Agency Management'), 'Agency Management', 'Store Import Pnr Form Aggregation', 'AgencyManageController@storeImportPnrFormAggregation', 'manageAgency/storeImportPnrFormAggregation', '', 'N', 'A', 1, '2020-03-20 00:00:00');


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyManageController@getImportPnrForm%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%AgencyManageController@storeImportPnrFormAggregation%') AND menu_id != 0);
