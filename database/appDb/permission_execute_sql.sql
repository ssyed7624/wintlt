/*Karthick 28th april 2020 offline payment*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Extra Payment'), 'Offline Payment', 'Common Extra Payment', 'OfflinePaymentController@commonOfflinePayment', 'offlinePayment/commonOfflinePayment', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%OfflinePaymentController@commonOfflinePayment%') AND menu_id != 0);

/* Url Expire */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Common','Common Route', 'UserReferralController@urlReferralLinkExpire', 'userReferral/urlReferralLinkExpire', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

/*KArthick 29th April 2020 Offline Payment Get Payment Details */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Offline Payment', 'Make Offline Payment', 'MakePaymentController@getOfflinePaymentInfo', 'makepayment/getOfflinePaymentInfo', '', 'Y', 'A', 1, '2020-03-25 00:00:00');

/*Karthick 29th april 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Flight Booking Management', 'download voucher', 'BookingsController@downloadVoucher', 'bookings/downloadVoucher', '', 'Y', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Flight Booking Management', 'resend email', 'BookingsController@resendEmail', 'bookings/resendEmail', '', 'Y', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@downloadVoucher%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@resendEmail%') AND menu_id != 0);

/* Divakr 30 - April - 2020 */


INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Add Payment', 'Add Payment', 'BookingsController@addPayment', 'bookings/addPayment', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_url like '%bookings/addPayment%') AND menu_id != 0);



INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Add Payment', 'Add payNow', 'BookingsController@payNow', 'bookings/payNow', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_url like '%bookings/payNow%') AND menu_id != 0);

/*Karthick May 1 2020 home based agency changes */

ALTER TABLE `home_agent_details` ADD `status` ENUM('A','IA','D','PA','R','') NULL AFTER `profile_pic_name`;

/* Divakr 01 - May - 2020 */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Extra Payment', 'Extra Payment', 'MakePaymentController@makePayment', 'makepayment/makePayment', '', 'Y', 'A', 1, '2020-03-25 00:00:00');

/*Karthick May 1 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='B2C Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Settings', 'get history', 'PortalSettingsController@getHistory', 'portalSetting/getHistory', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='B2C Portal Settings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Portal Config'), 'Portal Settings', 'get history diff', 'PortalSettingsController@getHistoryDiff', 'portalSetting/getHistoryDiff', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalSettingsController@getHistory%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalSettingsController@getHistory%') AND menu_id != 0); 
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalSettingsController@getHistoryDiff%') AND menu_id != 0);
INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%PortalSettingsController@getHistoryDiff%') AND menu_id != 0); 

/* Divakr 02 - May - 2020 */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Checkout Data', 'Checkout Data', 'PackagesController@getCheckoutData', 'packages/getCheckoutData', '', 'Y', 'A', 1, '2020-03-25 00:00:00');

/*Senivasan 02-May-2020 */
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Common','Common Route', 'RewardPointsController@getAccountPortal', 'getAccountPortal', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

/* Seenivasan 04-May-2020*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'UserTraveller ', 'Common Route', 'UserTravellerController@searchUserTravellersDetails', 'searchUserTravellersDetails', '', 'Y', 'A', 1, '2020-03-21 00:00:00');


/* Divakar 05 - May - 2020 */

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='CU'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%UserTravellerController%') AND menu_id != 0);


/* Divakar 08 - May - 2020 */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Bookings', 'PayNow Post', 'BookingsController@payNowPost', 'bookings/payNowPost', '', 'N', 'A', 1, '2020-03-21 00:00:00');


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@payNowPost%') AND menu_id != 0);

/* Seenivasan 08-May-2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Common ', 'Common Route', 'PortalPromotionController@getPortalPromotion', 'getPortalPromotion', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

/*KArthick 11th May 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Common ', 'Get Flight Promo Code List', 'FlightsController@getFlightPromoCodeList', 'getFlightPromoCodeList', '', 'Y', 'A', 1, '2020-03-21 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Common ', 'Apply Flight Promo Code', 'FlightsController@applyFlightPromoCode', 'applyFlightPromoCode', '', 'Y', 'A', 1, '2020-03-21 00:00:00');

/* Seenivasan 12-May-2020*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Common','Common Route', 'ContactUsController@list', 'contactUs/index', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Common','Common Route', 'ContactUsController@index', 'contactUs/list', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

/* Seenivasan 15-May-2020*/
ALTER TABLE `country_details` CHANGE `country_code` `country_code` CHAR(10) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;


/* Seenivasan 15-May-2020*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Common','Common Route', 'EventSubscriptionController@postEventRegister', 'postEventRegister', '', 'Y', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Common','Common Route', 'EventSubscriptionController@checkEventPortal', 'checkEventPortal', '', 'Y', 'A', 1, '2018-05-08 00:00:00');

/* Divakr 17 - May - 2020 */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Package', 'Booking', 'PackagesController@packageBooking', 'packages/packageBooking', '', 'Y', 'A', 1, '2020-03-25 00:00:00');

/*Karthick 18th may 2020*/
INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Common','Storage Log Index', 'StorageLogViewController@index', 'index', '', 'N', 'A', 1, '2018-05-08 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Common','Storage Log File View', 'StorageLogViewController@fileViewer', 'fileViewer', '', 'N', 'A', 1, '2018-05-08 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%StorageLogViewController%') AND menu_id != 0);


/* Divakr 19 - May - 2020 */

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Engine Payment', 'Engine Payment', 'ApiPgCommonController@apiPgBookingPayment', 'apiPgPayment', '', 'Y', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Look To Book', 'Look To Book', 'LookToBookRatioApiController@index', 'lookToBookRatio', '', 'Y', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Get Currency', 'Get Currency', 'LookToBookRatioApiController@getB2bSupplierConsumerCurrency', 'getB2bSupplierConsumerCurrency', '', 'Y', 'A', 1, '2020-03-25 00:00:00');

/*Karthick 20th May 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Insurance', 'Insurance Booking Mail', 'CustomerInsuranceBookingManagementController@insuranceSuccessEmailSend', 'insuranceSuccessEmailSend', '', 'Y', 'A', 1, '2020-03-25 00:00:00');


/*Divakar 20th May 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Packages'), 'Packages', 'List', 'BookingsController@packageIndex', 'bookings/packageIndex', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Packages'), 'Packages', 'Package List', 'BookingsController@packageList', 'bookings/packageList', '', 'N', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Packages'), 'Packages', 'Package View', 'BookingsController@packageView', 'bookings/packageView', '', 'N', 'A', 1, '2020-03-25 00:00:00');


INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@packageList%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@packageView%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_route like '%BookingsController@packageIndex%') AND menu_id != 0);


ALTER TABLE `booking_master` CHANGE `booking_type` `booking_type` TINYINT(4) NOT NULL COMMENT '1 Flight, 2 - Hotel, 3 - Insurance, 4 - Package';

ALTER TABLE `supplier_wise_hotel_booking_total` ADD `currency_code` CHAR(4) NULL AFTER `settlement_exchange_rate`;

/*Karthick 20th May 2020 Pacakage*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Customer Pacakage', 'list', 'CustomerPackageBookingManagementController@list', 'customerPackageBooking/list', '', 'Y', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Customer Pacakage', 'view', 'CustomerPackageBookingManagementController@view', 'customerPackageBooking/view', '', 'Y', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Customer Pacakage', '', 'CustomerPackageBookingManagementController@getPackageGuestBookingView', 'customerPackageBooking/getPackageGuestBookingView', '', 'Y', 'A', 1, '2020-03-25 00:00:00');


/*Divakar 20th May 2020*/

ALTER TABLE `promo_code_details` CHANGE `product_type` `product_type` ENUM('1','2','3','4') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT '1' COMMENT '1- Flight, 2- Hotel, 3. insurance, 4. Package';

/*Karthick 21st 2020 Route page*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'route Page', 'get Airline Content', 'RoutePageController@getAirlineContent', 'getAirlineContent', '', 'Y', 'A', 1, '2020-03-25 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'route Page', 'get City Airport Details', 'RoutePageController@getCityAirportDetails', 'getCityAirportDetails', '', 'Y', 'A', 1, '2020-03-25 00:00:00');

/*Divakar 27th May 2020*/

ALTER TABLE `flight_itinerary` ADD `split_payment_info` TEXT NULL DEFAULT NULL AFTER `fare_details`;

/*Karthick 27th 2020 User Group update*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Customer Register', 'User Group Update', 'UserGroupsController@updateUserGroup', 'updateUserGroup', '', 'Y', 'A', 1, '2020-03-25 00:00:00');


/*Karthick june 6th 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management', 'creditManagementTransactionList', 'AgencyCreditManagementController@creditManagementTransactionList', 'allTransactionList', '', 'N', 'A', 1, '2020-03-20 00:00:00'),
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Manage Agency'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Agency Credit Management', 'creditManagementTransactionList', 'AgencyCreditManagementController@creditManagementTransactionList', 'allPendingApprovalList', '', 'N', 'A', 1, '2020-03-20 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_url like '%allTransactionList%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_url like '%allPendingApprovalList%') AND menu_id != 0);


/* divakar 09 - Jun - 2020 supplier_wise_hotel_booking_total*/

ALTER TABLE `supplier_wise_hotel_booking_total` CHANGE `payment_mode` `payment_mode` ENUM('CL','FU','CP','CF','BH','PC','AC','PG','') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT '' COMMENT 'CL - Credit Limit , FU - Fund , CP - Card Payment , CF - Credit Limit Plus Fund, BH - Book & Hold, PC - Pay By Cheque, AC - ACH, PG - Payment Gateway';
ALTER TABLE `insurance_supplier_wise_booking_total` CHANGE `payment_mode` `payment_mode` ENUM('CL','FU','CP','CF','BH','PC','AC','PG','') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT '' COMMENT 'CL - Credit Limit , FU - Fund , CP - Card Payment , CF - Credit Limit Plus Fund, BH - Book & Hold, PC - Pay By Cheque, AC - ACH, PG - Payment Gateway';

/* divakar 11 - Jun - 2020 supplier_wise_hotel_booking_total*/

ALTER TABLE `booking_master` ADD `hotel` ENUM('Yes','No','') NULL DEFAULT 'No' AFTER `insurance`;

/*Divakar june 14th 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Bookings'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'Flight'), 'Flight Booking', 'Flight Booking History', 'BookingsController@getBookingHistory', 'bookings/getBookingHistory', '', 'N', 'A', 1, '2020-03-25 00:00:00');

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='SA'),permission_id as pd from permissions where is_public = 'N' AND ( permission_url like '%bookings/getBookingHistory%') AND menu_id != 0);

INSERT INTO `role_permission_mapping`(`role_permission_id`, `role_id`, `permission_id`) 
(select NULL,(SELECT role_id FROM user_roles WHERE role_code ='AO'),permission_id as pd from permissions where is_public = 'N' AND ( permission_url like '%bookings/getBookingHistory%') AND menu_id != 0);

/*Divakar june 20th 2020*/

INSERT INTO `permissions` (`permission_id`, `menu_id`, `submenu_id`, `permission_group`, `permission_name`, `permission_route`, `permission_url`, `route_mapping`, `is_public`, `status`, `created_by`, `created_at`) VALUES 
(NULL, (SELECT menu_id FROM menu_details WHERE menu_name ='Dashboard'), (SELECT submenu_id FROM submenu_details WHERE submenu_name = 'No Submenu'), 'Hotel', 'Get Search Data', 'HotelsController@getSearchData', 'hotels/getSearchData', '', 'Y', 'A', 1, '2020-03-25 00:00:00');
