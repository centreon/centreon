--
-- TOPOLOGY
--

INSERT INTO `topology` (`topology_id`, `topology_name`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_url`, `topology_url_opt`, `topology_popup`, `topology_modules`, `topology_show`) VALUES
(NULL, 'Open Tickets', '604', NULL, NULL, '8', NULL, NULL, '0', '1', '1'),
(NULL, 'Rules', '604', '60420', '10', '8', './modules/centreon-open-tickets/views/rules/index.php', NULL, NULL, '1', '1');
INSERT INTO `topology_JS` (`id_page`, `PathName_js`) VALUES ('60420', './modules/centreon-open-tickets/lib/jquery.sheepItPlugin.js');
INSERT INTO `topology_JS` (`id_page`, `PathName_js`) VALUES ('60420', './modules/centreon-open-tickets/lib/jquery.serialize-object.min.js');
INSERT INTO `topology_JS` (`id_page`, `PathName_js`) VALUES ('60420', './modules/centreon-open-tickets/lib/doClone.js');
INSERT INTO `topology_JS` (`id_page`, `PathName_js`) VALUES ('60420', './modules/centreon-open-tickets/lib/commonFunc.js');

INSERT INTO `topology` (`topology_id`, `topology_name`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_url`, `topology_url_opt`, `topology_popup`, `topology_modules`, `topology_show`, `topology_style_class`, `topology_style_id`, `topology_OnClick`, `readonly`) VALUES
(NULL,'Ticket Logs', 203, 20320,30,30,'./modules/centreon-open-tickets/views/logs/index.php',NULL,'0','0','1',NULL,NULL,NULL,'1');
INSERT INTO `topology_JS` (`id_page`, `PathName_js`) VALUES ('20320', './modules/centreon-open-tickets/lib/commonFunc.js');
INSERT INTO `topology_JS` (`id_page`, `PathName_js`) VALUES ('20320', './modules/centreon-open-tickets/lib/jquery.serialize-object.min.js');

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
    `uniq_id` VARCHAR(512) NOT NULL,
    `label` VARCHAR(512) NOT NULL,
    `value` TEXT,
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
      `uniq_id` VARCHAR(512) NOT NULL,
    `rule_id` int(11) NOT NULL,
    `value` TEXT,
    PRIMARY KEY (`form_value_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `mod_open_tickets_form_value`
  ADD CONSTRAINT `mod_open_tickets_form_value_fk_1` FOREIGN KEY (`rule_id`) REFERENCES `mod_open_tickets_rule` (`rule_id`) ON DELETE CASCADE;

-- Historic and tickets
CREATE TABLE IF NOT EXISTS `@DB_CENTSTORAGE@`.`mod_open_tickets` (
    `ticket_id` int(11) NOT NULL AUTO_INCREMENT,
    `timestamp` int(11) NOT NULL,
    `user` VARCHAR(512) NOT NULL,
    `ticket_value` VARCHAR(2048),
    PRIMARY KEY (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE INDEX `mod_open_tickets_timestamp_idx` ON `@DB_CENTSTORAGE@`.`mod_open_tickets` (`timestamp`);
CREATE INDEX `mod_open_tickets_ticket_value_idx` ON `@DB_CENTSTORAGE@`.`mod_open_tickets` (`ticket_value`(768));

CREATE TABLE IF NOT EXISTS `@DB_CENTSTORAGE@`.`mod_open_tickets_data` (
    `ticket_id` int(11) NOT NULL,
    `subject` VARCHAR(2048),
    `data_type` enum('0', '1') NOT NULL DEFAULT '1',
    `data` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `@DB_CENTSTORAGE@`.`mod_open_tickets_data`
  ADD CONSTRAINT `mod_open_tickets_data_fk_1` FOREIGN KEY (`ticket_id`) REFERENCES `mod_open_tickets` (`ticket_id`) ON DELETE CASCADE;

CREATE TABLE IF NOT EXISTS `@DB_CENTSTORAGE@`.`mod_open_tickets_link` (
    `ticket_id` int(11) NOT NULL,
    `host_id` int(11),
    `service_id` int(11) DEFAULT NULL,
    `host_state` int(11),
    `service_state` int(11),
    `hostname` VARCHAR(1024),
    `service_description` VARCHAR(1024) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `@DB_CENTSTORAGE@`.`mod_open_tickets_link`
  ADD CONSTRAINT `mod_open_tickets_link_fk_1` FOREIGN KEY (`ticket_id`) REFERENCES `mod_open_tickets` (`ticket_id`) ON DELETE CASCADE;
CREATE INDEX `mod_open_tickets_link_hostservice_idx` ON `@DB_CENTSTORAGE@`.`mod_open_tickets_link` (`host_id`, `service_id`);

INSERT INTO widget_parameters_field_type (ft_typename, field_type_id, is_connector) VALUES ('openTicketsRule', '100', '1');
