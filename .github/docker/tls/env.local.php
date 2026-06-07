<?php

/*
 * Test-only .env.local.php that reads DATABASE_* values from the system
 * environment. Bind-mounted onto /usr/share/centreon/.env.local.php by
 * docker-compose.tls.yml so the test stack can switch modes without
 * editing files. Mirrors what an OnPrem admin would write by hand in
 * .env.local.
 *
 * APP_SECRET is left as a placeholder on purpose (no secret committed).
 * For local runs, replace %APP_SECRET% with the value baked into the dev
 * image you use (grep APP_SECRET in the image's /usr/share/centreon/.env*).
 */

return [
    'APP_ENV' => 'prod',
    'APP_SECRET' => '%APP_SECRET%',

    'DATABASE_SSL_ENABLED'        => getenv('DATABASE_SSL_ENABLED')        ?: '0',
    'DATABASE_VERIFY_SERVER_CERT' => getenv('DATABASE_VERIFY_SERVER_CERT') ?: '0',
    'DATABASE_CA_PATH'            => getenv('DATABASE_CA_PATH')            ?: '',
    'DATABASE_SSL_CERT_PATH'      => getenv('DATABASE_SSL_CERT_PATH')      ?: '',
    'DATABASE_SSL_KEY_PATH'       => getenv('DATABASE_SSL_KEY_PATH')       ?: '',
];
