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
