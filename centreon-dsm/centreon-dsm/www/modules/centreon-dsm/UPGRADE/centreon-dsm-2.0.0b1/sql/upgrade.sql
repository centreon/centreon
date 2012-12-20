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
