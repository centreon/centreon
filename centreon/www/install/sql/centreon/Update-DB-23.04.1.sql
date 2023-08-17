ALTER TABLE `topology` ADD COLUMN `topology_feature_flag` varchar(255) DEFAULT NULL AFTER `topology_OnClick`;
ALTER TABLE topology ADD topology_url_substitute VARCHAR(255) NULL AFTER topology_url_opt;
