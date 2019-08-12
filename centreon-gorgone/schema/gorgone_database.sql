CREATE TABLE IF NOT EXISTS `gorgone_identity` (
  `id` INTEGER PRIMARY KEY,
  `ctime` int(11) DEFAULT NULL,
  `identity` varchar(2048) DEFAULT NULL,
  `key` varchar(4096) DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_gorgone_identity_identity ON gorgone_identity (identity);

CREATE TABLE IF NOT EXISTS `gorgone_history` (
  `id` INTEGER PRIMARY KEY,
  `token` varchar(2048) DEFAULT NULL,
  `code` int(11) DEFAULT NULL,
  `etime` int(11) DEFAULT NULL,
  `ctime` int(11) DEFAULT NULL,
  `data` TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_gorgone_history_id ON gorgone_history (id);
CREATE INDEX IF NOT EXISTS idx_gorgone_history_token ON gorgone_history (token);
CREATE INDEX IF NOT EXISTS idx_gorgone_history_etime ON gorgone_history (etime);
CREATE INDEX IF NOT EXISTS idx_gorgone_history_code ON gorgone_history (code);
CREATE INDEX IF NOT EXISTS idx_gorgone_history_ctime ON gorgone_history (ctime);

CREATE TABLE IF NOT EXISTS `gorgone_synchistory` (
  `id` int(11) DEFAULT NULL,
  `ctime` int(11) DEFAULT NULL,
  `last_id` int(11) DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_gorgone_synchistory_id ON gorgone_synchistory (id);
