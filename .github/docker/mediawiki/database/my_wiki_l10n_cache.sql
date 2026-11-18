CREATE TABLE l10n_cache (
		lc_lang BLOB NOT NULL,
		lc_key TEXT NOT NULL,
		lc_value BLOB NOT NULL,
		PRIMARY KEY (lc_lang, lc_key)
	);
