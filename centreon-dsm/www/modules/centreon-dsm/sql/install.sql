--
-- `mod_dsm_pool` table structure
--

CREATE TABLE IF NOT EXISTS `mod_dsm_pool` (
  `pool_id` int(11) NOT NULL AUTO_INCREMENT,
  `pool_name` varchar(255) DEFAULT NULL,
  `pool_host_id` int(11) DEFAULT NULL,
  `pool_description` varchar(255) DEFAULT NULL,
  `pool_number` int(11) DEFAULT NULL,
  `pool_prefix` varchar(255) DEFAULT NULL,
  `pool_cmd_id` int(11) DEFAULT NULL,
  `pool_args` varchar(255) DEFAULT NULL,
  `pool_activate` enum('0','1') DEFAULT '1',
  `pool_service_template_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`pool_id`),
  KEY `pool_host_id` (`pool_host_id`),
  KEY `pool_cmd_id` (`pool_cmd_id`),
  KEY `pool_name` (`pool_name`),
  KEY `pool_service_template_id` (`pool_service_template_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

--
-- exported tables constraints
--

ALTER TABLE `mod_dsm_pool`
  ADD CONSTRAINT `mod_dsm_pool_ibfk_1` FOREIGN KEY (`pool_service_template_id`) REFERENCES `service` (`service_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mod_dsm_pool_ibfk_2` FOREIGN KEY (`pool_host_id`) REFERENCES `host` (`host_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mod_dsm_pool_ibfk_3` FOREIGN KEY (`pool_cmd_id`) REFERENCES `command` (`command_id`) ON DELETE CASCADE;

INSERT INTO `topology` (`topology_id`, `topology_name`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_url`, `topology_url_opt`, `topology_popup`, `topology_modules`, `topology_show`, `topology_style_class`, `topology_style_id`, `topology_OnClick`) VALUES (NULL, 'Dynamic Services', 507, NULL, 0, 11, NULL, NULL, '0', '0', '1', NULL, NULL, NULL);
INSERT INTO `topology` (`topology_id`, `topology_name`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_url`, `topology_url_opt`, `topology_popup`, `topology_modules`, `topology_show`, `topology_style_class`, `topology_style_id`, `topology_OnClick`) VALUES (NULL, 'Configure', 507, 50711, 10, 11, './modules/centreon-dsm/core/configuration/services/slots.php', NULL, '0', '0', '1', NULL, NULL, NULL);

--
-- `mod_dsm_locks` table structure
--

CREATE TABLE IF NOT EXISTS centreon_storage.`mod_dsm_cache` (
  `cache_id` int(11) NOT NULL AUTO_INCREMENT,
  `host_id` int(11) DEFAULT NULL,
  `ctime` int(11) DEFAULT NULL,
  `status` smallint(6) DEFAULT NULL,
  `pool_prefix` varchar(255) DEFAULT NULL,
  `id` varchar(1024) DEFAULT NULL,
  `macros` text,
  `output` text,
  PRIMARY KEY (`cache_id`),
  KEY `cache_host_id` (`host_id`),
  KEY `pool_prefix` (`pool_prefix`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

--
-- Structure de la table `mod_dsm_locks`
--

CREATE TABLE IF NOT EXISTS centreon_storage.`mod_dsm_locks` (
  `lock_id` int(11) NOT NULL AUTO_INCREMENT,
  `host_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  `ctime` int(11) DEFAULT NULL,
  `internal_id` int(11) DEFAULT NULL,
  `id` varchar(1024) DEFAULT NULL,
  PRIMARY KEY (`lock_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

--
-- `mod_dsm_history` table structure
--

CREATE TABLE IF NOT EXISTS centreon_storage.`mod_dsm_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `host_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `ctime` int(11) DEFAULT NULL,
  `status` smallint(6) DEFAULT NULL,
  `internal_id` int(11) DEFAULT NULL,
  `id` varchar(1024) DEFAULT NULL,
  `macros` text,
  `output` text,
  PRIMARY KEY (`history_id`),
  KEY `ctime` (`ctime`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
