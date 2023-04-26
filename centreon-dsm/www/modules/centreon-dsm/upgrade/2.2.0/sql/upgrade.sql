
DROP TABLE centreon_storage.`mod_dsm_cache`;
DROP TABLE centreon_storage.`mod_dsm_locks`;

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
  KEY `cache_mult_idx` (`host_id`,`id`,`cache_id`),
  KEY `pool_prefix` (`pool_prefix`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

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
