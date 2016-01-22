--
-- TOPOLOGY
--

INSERT INTO `topology` (`topology_id`, `topology_name`, `topology_icone`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_url`, `topology_url_opt`, `topology_popup`, `topology_modules`, `topology_show`) VALUES
('', 'Open Tickets', NULL, '604', NULL, NULL, '8', NULL, NULL, '0', '1', '1'),
('', 'Rules', './modules/centreon-open-tickets/images/wrench.png', '604', '60420', '10', '8', './modules/centreon-open-tickets/views/rules/index.php', NULL, NULL, '1', '1');
INSERT INTO `topology_JS` (`id_page`, `PathName_js`) VALUES ('60420', './modules/centreon-open-tickets/lib/jquery.sheepItPlugin.js');
INSERT INTO `topology_JS` (`id_page`, `PathName_js`) VALUES ('60420', './modules/centreon-open-tickets/lib/jquery.serialize-object.min.js');
INSERT INTO `topology_JS` (`id_page`, `PathName_js`) VALUES ('60420', './modules/centreon-open-tickets/lib/doClone.js');
INSERT INTO `topology_JS` (`id_page`, `PathName_js`) VALUES ('60420', './modules/centreon-open-tickets/lib/commonFunc.js');

--
-- STRUCTURE FOR mod_open_tickets_rule
--
CREATE TABLE IF NOT EXISTS `mod_open_tickets_rule` (
	`rule_id` int(11) NOT NULL AUTO_INCREMENT,
	`alias` varchar(255) DEFAULT NULL,
    `provider_id` int(11) NOT NULL,
	`activate` enum('0','1') NOT NULL DEFAULT '1',
	PRIMARY KEY (`rule_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

--
-- STRUCTURE FOR mod_open_tickets_form_clone
--
CREATE TABLE IF NOT EXISTS `mod_open_tickets_form_clone` (
	`form_clone_id` int(11) NOT NULL AUTO_INCREMENT,
    `uniq_id` int(11) NOT NULL,
    `label` VARCHAR(521) NOT NULL,
	`value1` VARCHAR(512),
    `value2` VARCHAR(512),
	`rule_id` int(11) NOT NULL,
    `order` int(11) NOT NULL,
	PRIMARY KEY (`form_clone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `mod_open_tickets_form_clone`
  ADD CONSTRAINT `mod_open_tickets_form_clone_fk_1` FOREIGN KEY (`rule_id`) REFERENCES `mod_open_tickets_rule` (`rule_id`) ON DELETE CASCADE;

--
-- STRUCTURE FOR mod_open_tickets_form_value
--
CREATE TABLE IF NOT EXISTS `mod_open_tickets_form_value` (
	`form_value_id` int(11) NOT NULL AUTO_INCREMENT,
  	`uniq_id` int(11) NOT NULL,
    `rule_id` int(11) NOT NULL,
    `value` TEXT,
	PRIMARY KEY (`form_value_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `mod_open_tickets_form_value`
  ADD CONSTRAINT `mod_open_tickets_form_value_fk_1` FOREIGN KEY (`rule_id`) REFERENCES `mod_open_tickets_rule` (`rule_id`) ON DELETE CASCADE;

-- Il y'aura une table d'historique des IDs ouverts
-- CREATE TABLE IF NOT EXISTS @DB_CENTSTORAGE@.`mod_dsm_cache` (