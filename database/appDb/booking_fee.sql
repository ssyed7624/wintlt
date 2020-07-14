

CREATE TABLE `booking_fee_templates` (
  `booking_fee_template_id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `portal_id` int(11) NULL DEFAULT '0',  
  `parent_id` int(11) NULL DEFAULT '0',
  `product_type` char(2) DEFAULT 'F',
  `template_name` varchar(100) NOT NULL,
  `status` enum('A','IA','D') NOT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `booking_fee_rules` (
  `booking_fee_rule_id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `booking_fee_template_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL DEFAULT '0',
  `fare_type` varchar(20) NOT NULL,
  `fee_type` enum('PB','PS','PJ','PP','PT','PR','PRN','PN','PPCY','PC','PCD') DEFAULT 'PB' COMMENT 'PB - Per Booking,PS - Per Segment,PJ - Per Journey,PP - Per Pax, PT - Per Trip ,PR - Per  Room,PRN - Per Room OR Night,PN - Per Night,PPCY - Per Policy, PC - Per Car, PCD - Per Care OR Day',
  `booking_fee_type` enum('AOT','AIF') DEFAULT 'AOT' COMMENT 'AOT - Add on total , AIF - Apply as an Individual fee',
  `fee_details` text,
  `selected_criterias` text,
  `criterias` text,
  `status` enum('A','IA','D') NOT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


INSERT INTO `booking_fee_templates` (`booking_fee_template_id`, `account_id`, `portal_id`, `parent_id`, `product_type`, `template_name`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (NULL, '2', '0', '0', 'F', 'TEST Flight', 'A', '1', '1', '2020-06-22 00:00:00', '2020-06-22 00:00:00');
INSERT INTO `booking_fee_templates` (`booking_fee_template_id`, `account_id`, `portal_id`, `parent_id`, `product_type`, `template_name`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (NULL, '2', '0', '0', 'H', 'TEST Hotel', 'A', '1', '1', '2020-06-22 00:00:00', '2020-06-22 00:00:00');
INSERT INTO `booking_fee_templates` (`booking_fee_template_id`, `account_id`, `portal_id`, `parent_id`, `product_type`, `template_name`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (NULL, '2', '0', '0', 'I', 'TEST Insuracne', 'A', '1', '1', '2020-06-22 00:00:00', '2020-06-22 00:00:00');

-- Flight Sample

INSERT INTO `booking_fee_rules` (`booking_fee_rule_id`, `booking_fee_template_id`, `parent_id`, `fare_type`, `fee_type`, `booking_fee_type`, `fee_details`, `selected_criterias`, `criterias`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (NULL, '1', '0', 'PUB', 'PP', 'AOT', '{"ADT":{"refPax":null,"refType":null,"refValue":null,"valueType":"D","value":{"BUSINESS":{"F":"0","P":"0","CALC":"PPBF"},"ECONOMY":{"F":"0","P":"0","CALC":"PPBF"},"FIRSTCLASS":{"F":"0","P":"0","CALC":"PPBF"},"PREMBUSINESS":{"F":"0","P":"0","CALC":"PPBF"},"PREMECONOMY":{"F":"0","P":"0","CALC":"PPBF"}}},"CHD":{"refPax":"ADT","refType":"S","refValue":"0","valueType":"R","value":{"BUSINESS":{"F":"0","P":"0","CALC":"PPBF"},"ECONOMY":{"F":"0","P":"0","CALC":"PPBF"},"FIRSTCLASS":{"F":"0","P":"0","CALC":"PPBF"},"PREMBUSINESS":{"F":"0","P":"0","CALC":"PPBF"},"PREMECONOMY":{"F":"0","P":"0","CALC":"PPBF"}}},"INF":{"refPax":"","refType":"","refValue":"0","valueType":"D","value":{"BUSINESS":{"F":"0","P":"0","CALC":"PPBF"},"ECONOMY":{"F":"0","P":"0","CALC":"PPBF"},"FIRSTCLASS":{"F":"0","P":"0","CALC":"PPBF"},"PREMBUSINESS":{"F":"0","P":"0","CALC":"PPBF"},"PREMECONOMY":{"F":"0","P":"0","CALC":"PPBF"}}},"INS":{"refPax":"ADT","refType":"S","refValue":"0","valueType":"R","value":{"BUSINESS":{"F":"0","P":"0","CALC":"PPBF"},"ECONOMY":{"F":"0","P":"0","CALC":"PPBF"},"FIRSTCLASS":{"F":"0","P":"0","CALC":"PPBF"},"PREMBUSINESS":{"F":"0","P":"0","CALC":"PPBF"},"PREMECONOMY":{"F":"0","P":"0","CALC":"PPBF"}}},"JUN":{"refPax":"ADT","refType":"S","refValue":"0","valueType":"R","value":{"BUSINESS":{"F":"0","P":"0","CALC":"PPBF"},"ECONOMY":{"F":"0","P":"0","CALC":"PPBF"},"FIRSTCLASS":{"F":"0","P":"0","CALC":"PPBF"},"PREMBUSINESS":{"F":"0","P":"0","CALC":"PPBF"},"PREMECONOMY":{"F":"0","P":"0","CALC":"PPBF"}}},"SCR":{"refPax":"ADT","refType":"S","refValue":"0","valueType":"R","value":{"BUSINESS":{"F":"0","P":"0","CALC":"PPBF"},"ECONOMY":{"F":"0","P":"0","CALC":"PPBF"},"FIRSTCLASS":{"F":"0","P":"0","CALC":"PPBF"},"PREMBUSINESS":{"F":"0","P":"0","CALC":"PPBF"},"PREMECONOMY":{"F":"0","P":"0","CALC":"PPBF"}}},"YCR":{"refPax":"ADT","refType":"S","refValue":"0","valueType":"R","value":{"BUSINESS":{"F":"0","P":"0","CALC":"PPBF"},"ECONOMY":{"F":"0","P":"0","CALC":"PPBF"},"FIRSTCLASS":{"F":"0","P":"0","CALC":"PPBF"},"PREMBUSINESS":{"F":"0","P":"0","CALC":"PPBF"},"PREMECONOMY":{"F":"0","P":"0","CALC":"PPBF"}}}}', '[]', '[]', 'A', '1', '1', '2020-06-22 00:00:00', '2020-06-22 00:00:00');

INSERT INTO `booking_fee_rules` (`booking_fee_rule_id`, `booking_fee_template_id`, `parent_id`, `fare_type`, `fee_type`, `booking_fee_type`, `fee_details`, `selected_criterias`, `criterias`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (NULL, '1', '0', 'PUB', 'PB', 'AOT', '{"F":"0","P":"0","CALC":"PPBF"}', '[]', '[]', 'A', '1', '1', '2020-06-22 00:00:00', '2020-06-22 00:00:00');

INSERT INTO `booking_fee_rules` (`booking_fee_rule_id`, `booking_fee_template_id`, `parent_id`, `fare_type`, `fee_type`, `booking_fee_type`, `fee_details`, `selected_criterias`, `criterias`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (NULL, '1', '0', 'PUB', 'PS', 'AOT', '{"F":"0"}', '[]', '[]', 'A', '1', '1', '2020-06-22 00:00:00', '2020-06-22 00:00:00');

INSERT INTO `booking_fee_rules` (`booking_fee_rule_id`, `booking_fee_template_id`, `parent_id`, `fare_type`, `fee_type`, `booking_fee_type`, `fee_details`, `selected_criterias`, `criterias`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (NULL, '1', '0', 'PUB', 'PJ', 'AOT', '[{"F":"0"},{"F":"0"},{"F":"0"},{"F":"0"},{"F":"0"},{"F":"0"}]', '[]', '[]', 'A', '1', '1', '2020-06-22 00:00:00', '2020-06-22 00:00:00');


-- Insurnace Sample

INSERT INTO `booking_fee_rules` (`booking_fee_rule_id`, `booking_fee_template_id`, `parent_id`, `fare_type`, `fee_type`, `booking_fee_type`, `fee_details`, `selected_criterias`, `criterias`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (NULL, '3', '0', 'PUB', 'PP', 'AOT', '{"ADT":{"refPax":null,"refType":null,"refValue":null,"valueType":"D","value":{"PREMIUM":{"F":"1","P":"1","CALC":"PPBF"}}},"CHD":{"refPax":"ADT","refType":"S","refValue":"0","valueType":"R","value":{"PREMIUM":{"F":"0","P":"0","CALC":"PPBF"}}},"INF":{"refPax":"ADT","refType":"S","refValue":"0","valueType":"R","value":{"PREMIUM":{"F":"0","P":"0","CALC":"PPBF"}}}}', '[]', '[]', 'A', '1', '1', '2020-06-22 00:00:00', '2020-06-22 00:00:00');

-- Hotel Sample Data

INSERT INTO `booking_fee_rules` (`booking_fee_rule_id`, `booking_fee_template_id`, `parent_id`, `fare_type`, `fee_type`, `booking_fee_type`, `fee_details`, `selected_criterias`, `criterias`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (NULL, '2', '0', 'PUB', 'PR', 'AOT', '{"F":"0","P":"0"}', '[]', '[]', 'A', '1', '1', '2020-06-22 00:00:00', '2020-06-22 00:00:00');

ALTER TABLE `booking_fee_templates` ADD `supplier_account_id` INT NOT NULL AFTER `booking_fee_template_id`;
