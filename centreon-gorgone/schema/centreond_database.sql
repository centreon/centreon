CREATE TABLE IF NOT EXISTS `centreond_identity` (
  `id` INTEGER PRIMARY KEY,
  `ctime` int(11) DEFAULT NULL,
  `identity` varchar(2048) DEFAULT NULL,
  `key` varchar(4096) DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_centreond_identity_identity ON centreond_identity (identity);

CREATE TABLE IF NOT EXISTS `centreond_history` (
  `id` INTEGER PRIMARY KEY,
  `token` varchar(255) DEFAULT NULL,
  `code` int(11) DEFAULT NULL,
  `etime` int(11) DEFAULT NULL,
  `ctime` int(11) DEFAULT NULL,
  `data` TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_centreond_history_id ON centreond_history (id);
CREATE INDEX IF NOT EXISTS idx_centreond_history_token ON centreond_history (token);
CREATE INDEX IF NOT EXISTS idx_centreond_history_etime ON centreond_history (etime);
CREATE INDEX IF NOT EXISTS idx_centreond_history_code ON centreond_history (code);
CREATE INDEX IF NOT EXISTS idx_centreond_history_ctime ON centreond_history (ctime);

CREATE TABLE IF NOT EXISTS `centreond_synchistory` (
  `id` int(11) DEFAULT NULL,
  `ctime` int(11) DEFAULT NULL,
  `last_id` int(11) DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_centreond_synchistory_id ON centreond_synchistory (id);