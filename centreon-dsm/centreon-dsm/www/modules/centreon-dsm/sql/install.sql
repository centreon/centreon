CREATE TABLE `151_centreon`.`mod_dsm_pool` (
`pool_id` INT NOT NULL AUTO_INCREMENT ,
`pool_name2` VARCHAR( 63 ) NULL ,
`pool_description` VARCHAR( 255 ) NULL ,
`pool_number` INT NULL ,
`pool_prefix` VARCHAR( 10 ) NULL ,
PRIMARY KEY ( `pool_id` )
) ENGINE = MYISAM;