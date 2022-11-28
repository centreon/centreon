PRAGMA encoding = "UTF-8";

CREATE TABLE `gorgone_information` (
    `key` varchar(1024) DEFAULT NULL,
    `value` varchar(1024) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS `gorgone_identity` (
  `id` INTEGER PRIMARY KEY,
  `ctime` int(11) DEFAULT NULL,
  `mtime` int(11) DEFAULT NULL,
  `identity` varchar(2048) DEFAULT NULL,
  `key` varchar(1024) DEFAULT NULL,
  `oldkey` varchar(1024) DEFAULT NULL,
  `iv` varchar(1024) DEFAULT NULL,
  `oldiv` varchar(1024) DEFAULT NULL,
  `parent` int(11) DEFAULT '0'
);

CREATE INDEX IF NOT EXISTS idx_gorgone_identity ON gorgone_identity (identity);
CREATE INDEX IF NOT EXISTS idx_gorgone_parent ON gorgone_identity (parent);

CREATE TABLE IF NOT EXISTS `gorgone_history` (
  `id` INTEGER PRIMARY KEY,
  `token` varchar(2048) DEFAULT NULL,
  `code` int(11) DEFAULT NULL,
  `etime` int(11) DEFAULT NULL,
  `ctime` FLOAT DEFAULT NULL,
  `instant` int(11) DEFAULT '0',
  `data` TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_gorgone_history_id ON gorgone_history (id);
CREATE INDEX IF NOT EXISTS idx_gorgone_history_token ON gorgone_history (token);
CREATE INDEX IF NOT EXISTS idx_gorgone_history_etime ON gorgone_history (etime);
CREATE INDEX IF NOT EXISTS idx_gorgone_history_code ON gorgone_history (code);
CREATE INDEX IF NOT EXISTS idx_gorgone_history_ctime ON gorgone_history (ctime);
CREATE INDEX IF NOT EXISTS idx_gorgone_history_instant ON gorgone_history (instant);

CREATE TABLE IF NOT EXISTS `gorgone_synchistory` (
  `id` int(11) NOT NULL,
  `ctime` FLOAT DEFAULT NULL,
  `last_id` int(11) DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_gorgone_synchistory_id ON gorgone_synchistory (id);

CREATE TABLE IF NOT EXISTS `gorgone_target_fingerprint` (
  `id` INTEGER PRIMARY KEY,
  `target` varchar(2048) DEFAULT NULL,
  `fingerprint` varchar(4096) DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_gorgone_target_fingerprint_target ON gorgone_target_fingerprint (target);

CREATE TABLE IF NOT EXISTS `gorgone_centreon_judge_spare` (
  `cluster_name` varchar(2048) NOT NULL,
  `status` int(11) NOT NULL,
  `data` TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_gorgone_centreon_judge_spare_cluster_name ON gorgone_centreon_judge_spare (cluster_name);
