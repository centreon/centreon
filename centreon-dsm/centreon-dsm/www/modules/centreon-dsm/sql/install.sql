--
-- Structure de la table `mod_dsm_cct_relation`
--

--CREATE TABLE IF NOT EXISTS `mod_dsm_cct_relation` (
--  `cgpr_id` int(11) NOT NULL AUTO_INCREMENT,
--  `cct_cct_id` int(11) DEFAULT NULL,
--  `pool_id` int(11) DEFAULT NULL,
--  PRIMARY KEY (`cgpr_id`),
--  KEY `cct_cct_id` (`cct_cct_id`),
--  KEY `pool_id` (`pool_id`)
--) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Structure de la table `mod_dsm_cg_relation`
--

--CREATE TABLE IF NOT EXISTS `mod_dsm_cg_relation` (
--  `cgpr_id` int(11) NOT NULL AUTO_INCREMENT,
--  `cg_cg_id` int(11) DEFAULT NULL,
--  `pool_id` int(11) DEFAULT NULL,
--  PRIMARY KEY (`cgpr_id`),
--  KEY `cg_cg_id` (`cg_cg_id`),
--  KEY `pool_id` (`pool_id`)
--) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Structure de la table `mod_dsm_pool`
--

CREATE TABLE IF NOT EXISTS `mod_dsm_pool` (
  `pool_id` int(11) NOT NULL AUTO_INCREMENT,
  `pool_name` varchar(63) DEFAULT NULL,
  `pool_host_id` int(11) DEFAULT NULL,
  `pool_description` varchar(255) DEFAULT NULL,
  `pool_number` int(11) DEFAULT NULL,
  `pool_prefix` varchar(10) DEFAULT NULL,
  `pool_cmd_id` int(11) DEFAULT NULL,
  `pool_args` varchar(255) DEFAULT NULL,
  `pool_tp_id` int(11) DEFAULT NULL,
  `pool_activate` enum('0','1') DEFAULT '1',
  `pool_tp_id2` int(11) DEFAULT NULL,
  `pool_tp_interval` int(11) DEFAULT NULL,
  `pool_service_template_id` int(11) DEFAULT NULL,
  `pool_notif_options` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`pool_id`),
  KEY `pool_host_id` (`pool_host_id`),
  KEY `pool_cmd_id` (`pool_cmd_id`),
  KEY `pool_tp_id` (`pool_tp_id`),
  KEY `pool_tp_id2` (`pool_tp_id2`),
  KEY `pool_service_template_id` (`pool_service_template_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;

--
-- Contraintes pour les tables exportées
--

--
-- Contraintes pour la table `mod_dsm_cct_relation`
--
ALTER TABLE `mod_dsm_cct_relation`
--  ADD CONSTRAINT `mod_dsm_cct_relation_ibfk_2` FOREIGN KEY (`pool_id`) REFERENCES `mod_dsm_pool` (`pool_id`) ON DELETE CASCADE,
--  ADD CONSTRAINT `mod_dsm_cct_relation_ibfk_1` FOREIGN KEY (`cct_cct_id`) REFERENCES `contact` (`contact_id`) ON DELETE CASCADE;
--
--
-- Contraintes pour la table `mod_dsm_cg_relation`
--
ALTER TABLE `mod_dsm_cg_relation`
--  ADD CONSTRAINT `mod_dsm_cg_relation_ibfk_2` FOREIGN KEY (`pool_id`) REFERENCES `mod_dsm_pool` (`pool_id`) ON DELETE CASCADE,
--  ADD CONSTRAINT `mod_dsm_cg_relation_ibfk_1` FOREIGN KEY (`cg_cg_id`) REFERENCES `contactgroup` (`cg_id`) ON DELETE CASCADE;
--
--
-- Contraintes pour la table `mod_dsm_pool`
--
ALTER TABLE `mod_dsm_pool`
  ADD CONSTRAINT `mod_dsm_pool_ibfk_5` FOREIGN KEY (`pool_service_template_id`) REFERENCES `service` (`service_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mod_dsm_pool_ibfk_1` FOREIGN KEY (`pool_host_id`) REFERENCES `host` (`host_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mod_dsm_pool_ibfk_2` FOREIGN KEY (`pool_cmd_id`) REFERENCES `command` (`command_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mod_dsm_pool_ibfk_3` FOREIGN KEY (`pool_tp_id`) REFERENCES `timeperiod` (`tp_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mod_dsm_pool_ibfk_4` FOREIGN KEY (`pool_tp_id2`) REFERENCES `timeperiod` (`tp_id`) ON DELETE CASCADE;


INSERT INTO `topology` (`topology_id`, `topology_name`, `topology_icone`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_url`, `topology_url_opt`, `topology_popup`, `topology_modules`, `topology_show`, `topology_style_class`, `topology_style_id`, `topology_OnClick`) VALUES (NULL, 'Dynamic Services', NULL, 507, NULL, 0, 11, NULL, NULL, '0', '0', '1', NULL, NULL, NULL);
INSERT INTO `topology` (`topology_id`, `topology_name`, `topology_icone`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_url`, `topology_url_opt`, `topology_popup`, `topology_modules`, `topology_show`, `topology_style_class`, `topology_style_id`, `topology_OnClick`) VALUES (NULL, 'Configure', './img/icones/16x16/centreon.gif', 507, 50711, 10, 11, './modules/centreon-dsm/core/configuration/services/slots.php', NULL, '0', '0', '1', NULL, NULL, NULL);

--
-- Structure de la table `mod_dsm_cache`
--

CREATE TABLE IF NOT EXISTS @DB_CENTSTORAGE@.`mod_dsm_cache` (
  `cache_id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_time` int(11) DEFAULT NULL,
  `host_name` varchar(255) DEFAULT NULL,
  `ctime` int(11) DEFAULT NULL,
  `status` smallint(6) DEFAULT NULL,
  `macros` text,
  `id` varchar(255) DEFAULT NULL,
  `output` text,
  `finished` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`cache_id`),
  KEY `host_name` (`host_name`,`entry_time`,`ctime`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;


--
-- Structure de la table `mod_dsm_locks`
--

CREATE TABLE IF NOT EXISTS @DB_CENTSTORAGE@.`mod_dsm_locks` (
  `lock_id` int(11) NOT NULL AUTO_INCREMENT,
  `host_name` varchar(255) DEFAULT NULL,
  `service_description` varchar(255) DEFAULT NULL,
  `id` varchar(255) DEFAULT NULL,
  `ctime` int(11) DEFAULT NULL,
  PRIMARY KEY (`lock_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;


