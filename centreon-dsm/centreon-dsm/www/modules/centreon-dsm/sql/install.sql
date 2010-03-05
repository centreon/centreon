CREATE TABLE `mod_dsm_pool` (
`pool_id` INT NOT NULL AUTO_INCREMENT ,
`pool_name` VARCHAR( 63 ) NULL ,
`pool_host_id` INT NULL ,
`pool_description` VARCHAR( 255 ) NULL ,
`pool_number` INT NULL ,
`pool_prefix` VARCHAR( 10 ) NULL ,
`pool_activate` ENUM( '0', '1' ) NULL DEFAULT '1',
PRIMARY KEY ( `pool_id` )
) ENGINE = MYISAM;


INSERT INTO `topology` (`topology_id`, `topology_name`, `topology_icone`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_url`, `topology_url_opt`, `topology_popup`, `topology_modules`, `topology_show`, `topology_style_class`, `topology_style_id`, `topology_OnClick`) VALUES (NULL, 'Dynamic Services', NULL, 507, NULL, 0, 11, NULL, NULL, '0', '0', '1', NULL, NULL, NULL);
INSERT INTO `topology` (`topology_id`, `topology_name`, `topology_icone`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_url`, `topology_url_opt`, `topology_popup`, `topology_modules`, `topology_show`, `topology_style_class`, `topology_style_id`, `topology_OnClick`) VALUES (NULL, 'Configure', './img/icones/16x16/centreon16x16.png', 507, 50711, 10, 11, './modules/centreon-dsm/core/configuration/services/slots.php', NULL, '0', '0', '1', NULL, NULL, NULL);
