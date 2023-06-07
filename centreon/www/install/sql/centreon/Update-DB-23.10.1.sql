INSERT INTO `topology` (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`, `topology_group`, `topology_order`, `topology_feature_flag`) VALUES ('Dashboard (beta)', '/home/dashboards', '1', '1', 1, 104, 1, 2, 'dashboard');

INSERT INTO `widget_models` (`title`,`version`,`description`,`url`,`directory`,`author`) VALUES ('centreon-widget-text','23.10.0','This is a sample widget with text','http://centreon.com','centreon-widget-text','Centreon');
INSERT INTO `widget_models` (`title`,`version`,`description`,`url`,`directory`,`author`) VALUES ('centreon-widget-text2','23.10.0','This is a sample widget with text','http://centreon.com','centreon-widget-text2','Centreon');

CREATE TABLE IF NOT EXISTS `dashboard` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(200) NOT NULL,
  `description` text,
  `created_by` int(11) NULL,
  `updated_by` int(11) NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `name_index` (`name`),
  CONSTRAINT `contact_created_by`
    FOREIGN KEY (`created_by`)
    REFERENCES `contact` (`contact_id`) ON DELETE SET NULL,
  CONSTRAINT `contact_updated_by`
    FOREIGN KEY (`updated_by`)
    REFERENCES `contact` (`contact_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `dashboard_panel` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dashboard_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `widget_type` VARCHAR(200) NOT NULL,
  `widget_settings` text NOT NULL,
  `layout_x` smallint(6) NOT NULL,
  `layout_y` smallint(6) NOT NULL,
  `layout_width` smallint(6) NOT NULL,
  `layout_height` smallint(6) NOT NULL,
  `layout_min_width` smallint(6) NOT NULL,
  `layout_min_height` smallint(6) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `name_index` (`name`),
  CONSTRAINT `parent_dashboard_id`
    FOREIGN KEY (`dashboard_id`)
    REFERENCES `dashboard` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
