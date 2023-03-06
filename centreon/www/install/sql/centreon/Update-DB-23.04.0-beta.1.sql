--- CREATE TABLES FOR VAULT CONFIGURATION ---
CREATE TABLE IF NOT EXISTS `vault` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `vault` (`name`) VALUES ('hashicorp');

CREATE TABLE IF NOT EXISTS `vault_configuration` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `vault_id` INT UNSIGNED NOT NULL,
  `url` VARCHAR(1024) NOT NULL,
  `port` SMALLINT UNSIGNED NOT NULL,
  `root_path` VARCHAR(50) NOT NULL,
  `role_id` VARCHAR(255) NOT NULL,
  `secret_id` VARCHAR(255) NOT NULL,
  `salt` CHAR(128) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_vault_configuration` (`url`, `port`, `root_path`),
  CONSTRAINT `vault_configuration_vault_id`
    FOREIGN KEY (`vault_id`)
    REFERENCES `vault` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `options` (`key`, `value`) VALUES ('resource_status_view_mode', 'compact');
