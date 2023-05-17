INSERT INTO `topology` (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`, `topology_group`, `topology_order`, `topology_feature_flag`) VALUES ('Dashboard (beta)', '/home/dashboards', '1', '1', 1, 104, 1, 2, 'dashboard');

INSERT INTO `widget_models` (`title`,`version`,`description`,`url`,`directory`,`author`) VALUES ('centreon-widget-text','23.10.0','This is a sample widget with text','http://centreon.com','centreon-widget-text','Centreon');
INSERT INTO `widget_models` (`title`,`version`,`description`,`url`,`directory`,`author`) VALUES ('centreon-widget-text2','23.10.0','This is a sample widget with text','http://centreon.com','centreon-widget-text2','Centreon');

CREATE TABLE IF NOT EXISTS `dashboard` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(200) NOT NULL,
  `description` text,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `name_index` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
