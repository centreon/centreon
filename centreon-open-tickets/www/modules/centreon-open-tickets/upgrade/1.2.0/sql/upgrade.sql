
ALTER TABLE mod_open_tickets_form_clone MODIFY `value` TEXT;

CREATE INDEX `mod_open_tickets_timestamp_idx` ON centreon_storage.`mod_open_tickets` (`timestamp`);
CREATE INDEX `mod_open_tickets_ticket_value_idx` ON centreon_storage.`mod_open_tickets` (`ticket_value`(768));

CREATE TABLE IF NOT EXISTS centreon_storage.`mod_open_tickets_data` (
    `ticket_id` int(11) NOT NULL,
    `subject` VARCHAR(2048),
    `data_type` enum('0', '1') NOT NULL DEFAULT '1',
    `data` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE centreon_storage.`mod_open_tickets_data`
    ADD CONSTRAINT `mod_open_tickets_data_fk_1` FOREIGN KEY (`ticket_id`) REFERENCES `mod_open_tickets` (`ticket_id`) ON DELETE CASCADE;

CREATE TABLE IF NOT EXISTS centreon_storage.`mod_open_tickets_link` (
    `ticket_id` int(11) NOT NULL,
    `host_id` int(11),
    `service_id` int(11) DEFAULT NULL,
    `host_state` int(11),
    `service_state` int(11),
    `hostname` VARCHAR(1024),
    `service_description` VARCHAR(1024) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE centreon_storage.`mod_open_tickets_link`
    ADD CONSTRAINT `mod_open_tickets_link_fk_1` FOREIGN KEY (`ticket_id`) REFERENCES `mod_open_tickets` (`ticket_id`) ON DELETE CASCADE;
CREATE INDEX `mod_open_tickets_link_hostservice_idx` ON centreon_storage.`mod_open_tickets_link` (`host_id`, `service_id`);

INSERT INTO `topology` (`topology_id`, `topology_name`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_url`, `topology_url_opt`, `topology_popup`, `topology_modules`, `topology_show`, `topology_style_class`, `topology_style_id`, `topology_OnClick`, `readonly`) VALUES
(NULL,'Ticket Logs', 203, 20320,30,30,'./modules/centreon-open-tickets/views/logs/index.php',NULL,'0','0','1',NULL,NULL,NULL,'1');
INSERT INTO `topology_JS` (`id_page`, `PathName_js`) VALUES ('20320', './modules/centreon-open-tickets/lib/commonFunc.js');
