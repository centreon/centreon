
CREATE INDEX `mod_open_tickets_timestamp_idx` ON centreon_storage.`mod_open_tickets` (`timestamp`);
CREATE INDEX `mod_open_tickets_ticket_value_idx` ON centreon_storage.`mod_open_tickets` (`ticket_value`);

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
    `service_id` int(11),
    `hostname` VARCHAR(1024),
    `service_description` VARCHAR(1024)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE centreon_storage.`mod_open_tickets_link`
  ADD CONSTRAINT `mod_open_tickets_link_fk_1` FOREIGN KEY (`ticket_id`) REFERENCES `mod_open_tickets` (`ticket_id`) ON DELETE CASCADE;