-- MODIFY TOPOLOGY FOR DASHBOARD --
INSERT INTO `topology` (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`, `topology_group`, `topology_order`, `topology_feature_flag`) VALUES ('Dashboard', '/home/dashboards', '1', '1', 1, 104, 1, 2, 'dashboard');
INSERT INTO `topology` (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`, `topology_show`, `topology_feature_flag`) VALUES ('Viewer', '/home/dashboards', '1', '0', 104, 10401, '0', 'dashboard');
INSERT INTO `topology` (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`, `topology_show`, `topology_feature_flag`) VALUES ('Creator', '/home/dashboards', '1', '0', 104, 10402, '0', 'dashboard');
INSERT INTO `topology` (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`, `topology_show`, `topology_feature_flag`) VALUES ('Administrator', '/home/dashboards', '1', '0', 104, 10403, '0', 'dashboard');

INSERT INTO `widget_models` (`title`,`version`,`description`,`url`,`directory`,`author`) VALUES ('centreon-widget-text','23.10.0','This is a sample widget with text','http://centreon.com','centreon-widget-text','Centreon');
INSERT INTO `widget_models` (`title`,`version`,`description`,`url`,`directory`,`author`) VALUES ('centreon-widget-text2','23.10.0','This is a sample widget with text','http://centreon.com','centreon-widget-text2','Centreon');

-- CREATE TABLES FOR DASHBOARD CONFIGURATION --

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

CREATE TABLE IF NOT EXISTS `dashboard_contact_relation` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dashboard_id` INT UNSIGNED NOT NULL,
  `contact_id` int(11) NOT NULL,
  `role` enum('viewer','editor') NOT NULL DEFAULT 'viewer',
  PRIMARY KEY (`id`),
  KEY `role_index` (`role`),
  UNIQUE KEY `dashboard_contact_relation_unique` (`dashboard_id`,`contact_id`),
  CONSTRAINT `dashboard_contact_relation_dashboard_id`
    FOREIGN KEY (`dashboard_id`)
    REFERENCES `dashboard` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dashboard_contact_relation_contact_id`
    FOREIGN KEY (`contact_id`)
    REFERENCES `contact` (`contact_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `dashboard_contactgroup_relation` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dashboard_id` INT UNSIGNED NOT NULL,
  `contactgroup_id` int(11) NOT NULL,
  `role` enum('viewer','editor') NOT NULL DEFAULT 'viewer',
  PRIMARY KEY (`id`),
  KEY `role_index` (`role`),
  UNIQUE KEY `dashboard_contactgroup_relation_unique` (`dashboard_id`,`contactgroup_id`),
  CONSTRAINT `dashboard_contactgroup_relation_dashboard_id`
    FOREIGN KEY (`dashboard_id`)
    REFERENCES `dashboard` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dashboard_contactgroup_relation_contactgroup_id`
    FOREIGN KEY (`contactgroup_id`)
    REFERENCES `contactgroup` (`cg_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CREATE TABLES FOR NOTIFICATIONS CONFIGURATION --

CREATE TABLE IF NOT EXISTS `notification` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(250) NOT NULL,
  `is_activated` BOOLEAN NOT NULL DEFAULT 1,
  `timeperiod_id` INT NOT NULL,
  `hostgroup_events` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `servicegroup_events` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `included_service_events` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`timeperiod_id`) REFERENCES `timeperiod` (`tp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notification_message` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `notification_id` INT UNSIGNED NOT NULL,
  `channel` enum('Email','Slack','Sms') DEFAULT 'Email',
  `subject` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `notification_message_notification_id`
    FOREIGN KEY (`notification_id`)
    REFERENCES `notification` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notification_user_relation` (
  `notification_id` INT UNSIGNED NOT NULL,
  `user_id` INT NOT NULL,
  UNIQUE KEY `notification_user_relation_unique_index` (`notification_id`,`user_id`),
  CONSTRAINT `notification_user_relation_notification_id`
    FOREIGN KEY (`notification_id`)
    REFERENCES `notification` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notification_user_relation_user_id`
    FOREIGN KEY (`user_id`)
    REFERENCES `contact` (`contact_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notification_hg_relation` (
  `notification_id` INT UNSIGNED NOT NULL,
  `hg_id` INT NOT NULL,
  UNIQUE KEY `notification_hg_relation_unique_index` (`notification_id`,`hg_id`),
  CONSTRAINT `notification_hg_relation_notification_id`
    FOREIGN KEY (`notification_id`)
    REFERENCES `notification` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notification_hg_relation_hg_id`
    FOREIGN KEY (`hg_id`)
    REFERENCES `hostgroup` (`hg_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notification_sg_relation` (
  `notification_id` INT UNSIGNED NOT NULL,
  `sg_id` INT NOT NULL,
  UNIQUE KEY `notification_sg_relation_unique_index` (`notification_id`,`sg_id`),
  CONSTRAINT `notification_sg_relation_notification_id`
    FOREIGN KEY (`notification_id`)
    REFERENCES `notification` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notification_sg_relation_hg_id`
    FOREIGN KEY (`sg_id`)
    REFERENCES `servicegroup` (`sg_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
