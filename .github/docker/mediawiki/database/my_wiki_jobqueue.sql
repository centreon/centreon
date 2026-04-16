PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
CREATE TABLE job (
		job_id INTEGER  NOT NULL PRIMARY KEY AUTOINCREMENT,
		job_cmd BLOB NOT NULL default '',
		job_namespace INTEGER NOT NULL,
		job_title TEXT  NOT NULL,
		job_timestamp BLOB NULL default NULL,
		job_params BLOB NOT NULL,
		job_random integer  NOT NULL default 0,
		job_attempts integer  NOT NULL default 0,
		job_token BLOB NOT NULL default '',
		job_token_timestamp BLOB NULL default NULL,
		job_sha1 BLOB NOT NULL default ''
	);
DELETE FROM sqlite_sequence;
COMMIT;
