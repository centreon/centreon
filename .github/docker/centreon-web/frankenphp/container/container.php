<?php

/*
 * Entry point of the FrankenPHP centreon-web image (no shell): `frankenphp php-cli container.php <role>`.
 *
 *   serve    writes the configuration files, waits for the installed databases, then becomes
 *            FrankenPHP (as apache) on port 80
 *   install  one-shot: creates the databases (or the database user when they exist) and sets
 *            the options of the environment (dataset, language, Gorgone API, VictoriaMetrics,
 *            LDAP, OpenID, SAML), then exits
 *   cron     runs the PHP cron jobs of centreon-web on their schedules
 *
 * Port of the startup scripts of the slim image (alma9/entrypoint/container.d and
 * alma9/entrypoint-slim/container.d), same environment variables.
 */

declare(strict_types=1);

const BIN = '/usr/local/bin/frankenphp';
const CADDYFILE = '/etc/frankenphp/Caddyfile';
const CENTREON_DIR = '/usr/share/centreon';
const CENTREON_ETC = '/etc/centreon';
const INSTALLER_DIST = '/usr/local/lib/centreon-container/install';
const INSTALLER_DIR = CENTREON_DIR . '/www/install';
const SQL_DATA_DIR = '/usr/local/lib/centreon-container/sql';
const READY_FILE = '/tmp/docker.ready';
const WEB_USER = 'apache';
const WEB_UID = 48;
const WEB_GID = 48;
// Test certificate of the saml profile (keycloak realm Centreon_SSO), as in 72-authentication.sh.
const SAML_CERTIFICATE = 'MIICpzCCAY8CBgGFydyVcDANBgkqhkiG9w0BAQsFADAXMRUwEwYDVQQDDAxDZW50cmVvbl9TU08wHhcNMjMwMTE5MTE0NzM0WhcNMzMwMTE5MTE0OTE0WjAXMRUwEwYDVQQDDAxDZW50cmVvbl9TU08wggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCCpNndecGJI2xOaNQXDDvwDwo/beQ7Q4HW/ck1BNkE13IgPf5GRpvP2jp/1IZsx92vQ2Ub9g5urNG/jo3nZzsUUIdTICsN9Bq2OIjYU9Uxmc1PpHzklN/SqZWbKXOw8EzqXkQ3YNXHqL9omJJ5JMxe4zg758zlvOUh3I44XhMy6PKgeReJIm+HxYJ8SKeu/XVRI7Uiyav5L2M85ED3kqiI3iPrGfLQzv8zqkTeNfuZIeigqI+M8MqRxR3Qf0UlmWA3ZAzsoxJUU+e0tHnD7MhgyRLfg76FjQ1U7Tv7X/h8uqRthjTbva5v0k0M85z21C85UrHxpS3e/HJFInrkJredAgMBAAEwDQYJKoZIhvcNAQELBQADggEBADQANd/iYhefXpcqXC+co3fEe7IaZ93XelZzJ5S4OAR5dHnhMMlMQnnscW/nH8NAEwWRImJPfOEcKun8rBUphZZJxi2WHHj5ilhGdNtcyZzh0sufyIQav/QMreGmDEj/J/uRfmG15Lj1wJB6mw+O4kuwJj/8DzxK6/sQYPisJuXrSWrDmcpvShvbo59JbVjdYK49WXVDbl++7hrwiOYuCQ/uodQYgvChZnIQbL4O6TbG4OLy+prFd5FBsEQds8ZNXoLWM5bCUz+bz4N68fAqhtPR8+yR+pIrE7/cvRaRCmgnG0s61JBZVxHoT4dbMJUTTSSS4dWCUUNhMCIFtEKL06c=';

// Schedules of /etc/cron.d/centreon and /etc/cron.d/centstorage kept by the slim image (UTC, as
// crond in the container). The Perl jobs (eventReportBuilder, dashboardBuilder, backup) are left out.
const CRON_JOBS = [
    'centAcl' => ['* * * * *', 'cron/centAcl.php'],
    'downtimeManager' => ['*/5 * * * *', 'cron/downtimeManager.php'],
    'knowledgebase' => ['*/5 * * * *', 'cron/centKnowledgeSynchronizer.php'],
    'statistics' => ['12 5 * * *', 'cron/centreon-send-stats.php'],
    'centreon-tokens' => ['* * * * *', 'cron/outdated-token-removal.php'],
    'centreon-purge' => ['0 2 * * *', 'cron/centstorage_purge.php'],
    'centreon-partitioning' => ['0 4 * * *', 'cron/centreon-partitioning.php'],
];

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

/** Value of an environment variable, $default when unset or empty (as ${VAR:-default}). */
function env(string $name, ?string $default = null): ?string
{
    $value = getenv($name);

    return ($value === false || $value === '') ? $default : $value;
}

/** `timeout 0.2 getent ahostsv4 <host>`: the optional services are enabled when their host resolves. */
function resolves(?string $host): bool
{
    return $host !== null && gethostbynamel($host) !== false;
}

function dbConfiguration(): string
{
    return env('MYSQL_DB_CONFIGURATION', 'centreon');
}

function dbStorage(): string
{
    return env('MYSQL_DB_STORAGE', 'centreon_storage');
}

function dbUser(): string
{
    return env('MYSQL_USER', 'centreon');
}

function dbPassword(): string
{
    return env('MYSQL_PASSWORD', 'centreon');
}

function connect(string $user, string $password, ?string $database = null): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%s', env('MYSQL_HOST', 'localhost'), env('MYSQL_PORT', '3306'));
    if ($database !== null) {
        $dsn .= ';dbname=' . $database;
    }

    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
}

/** 10-mysql.sh: wait for the database server. */
function waitForRoot(): PDO
{
    out('Waiting for DB server to be ready...');
    while (true) {
        try {
            $pdo = connect('root', (string) env('MYSQL_ROOT_PASSWORD', ''));
            out('DB server is running.');

            return $pdo;
        } catch (PDOException) {
            sleep(1);
        }
    }
}

/** serve and cron: wait until the install role has created the databases and the user. */
function waitForInstalledDatabases(): void
{
    out('Waiting for the Centreon databases to be installed...');
    $reported = '';
    while (true) {
        try {
            $pdo = connect(dbUser(), dbPassword(), dbConfiguration());
            $version = $pdo->query("SELECT `value` FROM `informations` WHERE `key` = 'version'")->fetchColumn();
            // Set by the install role after the installation and the dataset.
            $gorgone = $pdo->query("SELECT `value` FROM `options` WHERE `key` = 'gorgone_api_address'")->fetchColumn();
            connect(dbUser(), dbPassword(), dbStorage());
            if ($version !== false && $gorgone !== false) {
                out("Centreon {$version} databases are installed.");

                return;
            }
            $message = 'the install role has not configured the options yet';
        } catch (PDOException $exception) {
            $message = $exception->getMessage();
        }
        if ($message !== $reported) {
            out("Not installed yet: {$message}");
            $reported = $message;
        }
        sleep(2);
    }
}

function sqlQuote(string $value): string
{
    return "'" . str_replace(['\\', "'"], ['\\\\', "''"], $value) . "'";
}

/** Run every statement of a SQL script (mysql < file) and report the first error. */
function runSqlScript(PDO $pdo, string $sql): void
{
    $statement = $pdo->query($sql);
    while ($statement->nextRowset()) {
    }
    $statement->closeCursor();
}

function replaceOptions(PDO $pdo, array $options): void
{
    $keys = implode(', ', array_map('sqlQuote', array_keys($options)));
    $values = implode(', ', array_map(
        static fn (string $key, string $value): string => '(' . sqlQuote($key) . ', ' . sqlQuote($value) . ')',
        array_keys($options),
        $options
    ));
    $pdo->exec("DELETE FROM `options` WHERE `key` IN ({$keys})");
    $pdo->exec("INSERT INTO `options` (`key`, `value`) VALUES {$values}");
}

function dropPrivileges(): void
{
    if (posix_getuid() !== 0) {
        return;
    }
    if (! posix_initgroups(WEB_USER, WEB_GID) || ! posix_setgid(WEB_GID) || ! posix_setuid(WEB_UID)) {
        fail('Unable to switch to the ' . WEB_USER . ' user: ' . posix_strerror(posix_get_last_error()));
    }
    putenv('USER=' . WEB_USER);
}

/** Run a PHP script with `frankenphp php-cli` (no shell) and return its exit code. */
function runPhp(string $script, array $arguments = [], ?string $cwd = null, array $extraEnv = [], ?string &$output = null): int
{
    $env = $extraEnv === [] ? null : array_merge(getenv(), $extraEnv);
    $process = proc_open(
        [BIN, 'php-cli', $script, ...$arguments],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => STDERR],
        $pipes,
        $cwd,
        $env
    );
    if ($process === false) {
        fail("Unable to run {$script}");
    }
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    return proc_close($process);
}

/** Run an installation step (`php <step>.php` in steps/process) and check its JSON result. */
function runInstallStep(string $step, array $extraEnv = []): void
{
    $code = runPhp($step, [], INSTALLER_DIR . '/steps/process', $extraEnv, $output);
    $result = json_decode((string) $output, true);
    if ($code !== 0 || (is_array($result) && (int) ($result['result'] ?? 0) !== 0)) {
        fail("Installation step {$step} failed (exit code {$code}): " . trim((string) $output));
    }
}

function copyTree(string $source, string $target): void
{
    @mkdir($target, 0775, true);
    foreach (new DirectoryIterator($source) as $entry) {
        if ($entry->isDot()) {
            continue;
        }
        $to = $target . '/' . $entry->getFilename();
        if ($entry->isDir()) {
            copyTree($entry->getPathname(), $to);
        } else {
            copy($entry->getPathname(), $to);
        }
        chown($to, WEB_UID);
        chgrp($to, WEB_GID);
    }
    chown($target, WEB_UID);
    chgrp($target, WEB_GID);
}

function removeTree(string $path): void
{
    if (! is_dir($path) || is_link($path)) {
        @unlink($path);

        return;
    }
    foreach (new DirectoryIterator($path) as $entry) {
        if (! $entry->isDot()) {
            removeTree($entry->getPathname());
        }
    }
    rmdir($path);
}

/**
 * Files of the image that depend on the environment, in every role.
 * 01-gorgone-api-required.sh, 73-gorgone-api.sh (feature flag part), 71-vault.sh (refused).
 */
function prepareFiles(): void
{
    if (env('GORGONE_API_ADDRESS') === null) {
        fail('GORGONE_API_ADDRESS is required by the FrankenPHP centreon-web image (no local gorgoned).');
    }

    // gorgone_api_commands always, plus the flags listed in CENTREON_FEATURE_FLAGS (comma separated).
    $flags = ['gorgone_api_commands', ...array_filter(array_map('trim', explode(',', (string) env('CENTREON_FEATURE_FLAGS', ''))))];
    $featuresFile = CENTREON_DIR . '/config/features.json';
    $features = (string) file_get_contents($featuresFile);
    $updated = $features;
    foreach (array_unique($flags) as $flag) {
        $pattern = '/"' . preg_quote($flag, '/') . '": [0-3]/';
        if (preg_match($pattern, $updated) !== 1) {
            out("Feature flag {$flag} is unknown to this image: ignored.");

            continue;
        }
        $updated = (string) preg_replace($pattern, "\"{$flag}\": 3", $updated);
    }
    if ($updated !== $features) {
        file_put_contents($featuresFile, $updated);
    }

    if (resolves(env('VAULT_HOST'))) {
        out('VAULT_HOST is set but the FrankenPHP image does not configure Vault (see README.md): ignored.');
    }

    // The gettext library of the static build reads the language from the environment only
    // (not from setlocale(), and FrankenPHP threads do not share putenv()): the legacy pages
    // of the container are in the CENTREON_LANG language, whatever the language of the user.
    $locale = centreonLocale();
    if ($locale !== null) {
        putenv("LANG={$locale}.UTF-8");
    }
}

/** Locale of CENTREON_LANG (15-installation.sh), null when unset or unsupported. */
function centreonLocale(): ?string
{
    $languages = ['de' => 'de_DE', 'en' => 'en_US', 'es' => 'es_ES', 'fr' => 'fr_FR', 'pt_BR' => 'pt_BR', 'pt' => 'pt_PT'];
    $lang = (string) env('CENTREON_LANG', '');

    return $languages[$lang] ?? $languages[substr($lang, 0, 2)] ?? null;
}

/**
 * serve and cron: /etc/centreon/centreon.conf.php, as configFileSetup.php writes it at
 * installation, from the same environment (the install role writes it in its own container).
 */
function writeCentreonConfiguration(): void
{
    $escape = static fn (string $value): string => str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    $contents = strtr((string) file_get_contents(INSTALLER_DIST . '/var/configFileTemplate'), [
        '--ADDRESS--' => $escape(env('MYSQL_HOST', 'localhost')),
        '--DBUSER--' => $escape(dbUser()),
        '--DBPASS--' => $escape(dbPassword()),
        '--CONFDB--' => $escape(dbConfiguration()),
        '--STORAGEDB--' => $escape(dbStorage()),
        '--DBPORT--' => $escape(env('MYSQL_PORT', '3306')),
        '--CENTREONDIR--' => CENTREON_DIR . '/',
    ]);
    $file = CENTREON_ETC . '/centreon.conf.php';
    file_put_contents($file, $contents);
    chmod($file, 0640);
    chown($file, WEB_UID);
    chgrp($file, WEB_GID);
}

/** 15-installation.sh */
function install(PDO $root): void
{
    out('Copying the installer into www/install...');
    removeTree(INSTALLER_DIR);
    copyTree(INSTALLER_DIST, INSTALLER_DIR);

    $databaseFile = INSTALLER_DIR . '/tmp/database.json';
    $database = json_decode((string) file_get_contents($databaseFile), true, flags: JSON_THROW_ON_ERROR);
    $database = array_merge($database, [
        'address' => env('MYSQL_HOST', 'localhost'),
        'port' => env('MYSQL_PORT', '3306'),
        'root_password' => (string) env('MYSQL_ROOT_PASSWORD', ''),
        'db_configuration' => dbConfiguration(),
        'db_storage' => dbStorage(),
        'db_user' => dbUser(),
        'db_password' => dbPassword(),
        'db_password_confirm' => dbPassword(),
    ]);
    file_put_contents($databaseFile, json_encode($database, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $settings = ['foreign_key_checks', 'unique_checks', 'innodb_flush_log_at_trx_commit', 'sync_binlog'];
    $saved = [];
    foreach ($settings as $setting) {
        $saved[$setting] = $root->query("SELECT @@GLOBAL.{$setting}")->fetchColumn();
    }
    $restore = static function () use ($root, $saved): void {
        foreach ($saved as $setting => $value) {
            $root->exec("SET GLOBAL {$setting}=" . (int) $value);
        }
    };
    register_shutdown_function($restore);
    foreach (['foreign_key_checks' => 0, 'unique_checks' => 0, 'innodb_flush_log_at_trx_commit' => 2, 'sync_binlog' => 0] as $setting => $value) {
        $root->exec("SET GLOBAL {$setting}={$value}");
    }

    dropPrivileges();

    out('Creating Centreon configuration files...');
    runInstallStep('configFileSetup.php');

    $configurationDb = dbConfiguration();
    $installed = (int) $root->query(
        'SELECT count(*) FROM information_schema.tables WHERE table_schema=' . sqlQuote($configurationDb)
        . " AND table_name='nagios_server'"
    )->fetchColumn() === 1;

    if ($installed) {
        out('Centreon is already installed.');
        out('Creating Centreon database user...');
        runInstallStep('createDbUser.php');
    } else {
        out('Installing Centreon configuration database...');
        runInstallStep('installConfigurationDb.php');
        out('Installing Centreon storage database...');
        runInstallStep('installStorageDb.php');
        out('Creating Centreon database user...');
        runInstallStep('createDbUser.php');
        out('Inserting base configuration...');
        runInstallStep('insertBaseConf.php', ['SERVER_ADDR' => '127.0.0.1']);
        if (env('DATABASE_PARTITIONING', '1') === '1') {
            out('Creating database partition tables...');
            runInstallStep('partitionTables.php');
        }

        $root->exec("USE `{$configurationDb}`");
        $root->exec('UPDATE cfg_centreonbroker_info SET config_value = ' . sqlQuote(env('MYSQL_HOST', 'localhost'))
            . " WHERE config_key = 'db_host'");
        $root->exec('GRANT ALL ON *.* to ' . sqlQuote(dbUser()) . "@'%' WITH GRANT OPTION");

        if (env('CENTREON_DATASET', '0') === '1') {
            out('CENTREON_DATASET environment variable is set, dump will be inserted.');
            $dumps = glob(SQL_DATA_DIR . '/*') ?: [];
            sort($dumps);
            foreach ($dumps as $dump) {
                out('Inserting dump ' . basename($dump) . ' ...');
                runSqlScript($root, (string) file_get_contents($dump));
            }
        }
    }

    // generationCache.php (cache:clear) and createEngineContextConfiguration.php are not run: the
    // cache is built in the image, and centreon-web creates engine-context.json on demand
    // (feature flag gorgone_api_commands), both in the containers that serve.

    out('Disabling statistics collection...');
    $root->exec("USE `{$configurationDb}`");
    replaceOptions($root, ['send_statistics' => '0']);

    $restore();

    removeTree(INSTALLER_DIR);
}

/** 15-installation.sh (language), 72-authentication.sh (LDAP), 73-gorgone-api.sh, 74-victoria-metrics.sh */
function configureOptions(PDO $root): void
{
    $root->exec('USE `' . dbConfiguration() . '`');

    $lang = env('CENTREON_LANG');
    if ($lang !== null) {
        $locale = centreonLocale();
        if ($locale === null) {
            out("Language {$lang} not supported");
        } else {
            out("Setting language to {$locale}");
            $root->exec("UPDATE contact SET contact_lang = '{$locale}.UTF-8' WHERE contact_alias = 'admin'");
        }
    }

    if (resolves(env('LDAP_HOST'))) {
        $root->exec("UPDATE auth_ressource SET ar_enable = '1' WHERE ar_name = 'openldap'");
    }

    $address = (string) env('GORGONE_API_ADDRESS');
    $port = env('GORGONE_API_PORT', '8085');
    $gorgone = ['gorgone_api_address' => $address, 'gorgone_api_port' => $port];
    if (env('GORGONE_API_USERNAME') !== null) {
        $gorgone['gorgone_api_username'] = env('GORGONE_API_USERNAME');
        $gorgone['gorgone_api_password'] = env('GORGONE_API_PASSWORD', '');
    }
    out("Using the Gorgone API at {$address}:{$port} (local gorgoned is not started).");
    $root->exec("DELETE FROM `options` WHERE `key` IN ('gorgone_api_username', 'gorgone_api_password')");
    replaceOptions($root, $gorgone);

    $victoriaMetricsUrl = (string) env('VICTORIA_METRICS_URL');
    out("Reading the graphs from VictoriaMetrics at {$victoriaMetricsUrl}.");
    replaceOptions($root, [
        'metrics_data_source' => 'victoria_metrics',
        'victoria_metrics_url' => $victoriaMetricsUrl,
        'victoria_metrics_user' => env('VICTORIA_METRICS_USER', ''),
        'victoria_metrics_password' => env('VICTORIA_METRICS_PASSWORD', ''),
    ]);
}

/** 72-authentication.sh, OpenID and SAML parts: they go through the API v2 and CLAPI. */
function configureIdentityProviders(): void
{
    $openId = resolves(env('OPENID_HOST'));
    $saml = resolves(env('SAML_HOST'));
    if (! $openId && ! $saml) {
        return;
    }

    // centreon-web of this container, on a local port, for the API calls.
    $server = proc_open(
        [BIN, 'run', '--config', CADDYFILE, '--adapter', 'caddyfile'],
        [0 => ['file', '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR],
        $pipes,
        null,
        array_merge(getenv(), ['CENTREON_HTTP_ADDRESS' => '127.0.0.1:8080'])
    );
    $api = 'http://127.0.0.1:8080/centreon/api/latest';
    $request = static function (string $method, string $url, ?array $body, ?string $token = null): array {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array_filter([
                'Content-Type: application/json',
                'Accept: application/json',
                $token !== null ? "X-AUTH-TOKEN: {$token}" : null,
            ]),
            CURLOPT_POSTFIELDS => $body !== null ? json_encode($body, JSON_UNESCAPED_SLASHES) : null,
            CURLOPT_TIMEOUT => 60,
        ]);
        $response = curl_exec($handle);

        return [(int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), (string) $response];
    };

    try {
        for ($i = 0; $i < 60; $i++) {
            [$code] = $request('GET', "{$api}/platform/versions", null);
            if ($code === 200) {
                break;
            }
            sleep(1);
        }
        [, $login] = $request('POST', "{$api}/login", ['security' => ['credentials' => ['login' => 'admin', 'password' => 'Centreon!2021']]]);
        $token = json_decode($login, true)['security']['token'] ?? fail("API login failed: {$login}");

        $contactTemplate = static function (string $name): array {
            $clapi = [CENTREON_DIR . '/bin/centreon', '-u', 'admin', '-p', 'Centreon!2021', '-o', 'CONTACTTPL'];
            runPhp($clapi[0], [...array_slice($clapi, 1), '-a', 'ADD', '-v', "{$name};{$name};;1;1;en_US.UTF-8;local"]);
            runPhp($clapi[0], [...array_slice($clapi, 1), '-a', 'SHOW', '-v', $name], null, [], $output);
            foreach (explode("\n", (string) $output) as $line) {
                if (str_contains($line, $name)) {
                    return ['id' => (int) explode(';', $line)[0], 'name' => $name];
                }
            }
            fail("Contact template {$name} not found");
        };

        if ($openId) {
            $ip = gethostbyname((string) env('OPENID_HOST'));
            $base = "http://{$ip}:8080";
            [$code, $body] = $request('PATCH', "{$api}/administration/authentication/providers/openid", [
                'is_active' => true,
                'base_url' => "{$base}/realms/Centreon_SSO/protocol/openid-connect",
                'authorization_endpoint' => '/auth',
                'token_endpoint' => '/token',
                'endsession_endpoint' => '/logout',
                'client_id' => 'centreon-oidc-frontend',
                'client_secret' => 'IKbUBottl5eoyhf0I5Io2nuDsTA85D50',
                'introspection_token_endpoint' => '/token/introspect',
                'userinfo_endpoint' => '/userinfo',
                'login_claim' => 'preferred_username',
                'connection_scopes' => ['openid'],
                'email_bind_attribute' => 'email',
                'fullname_bind_attribute' => 'name',
                'auto_import' => true,
                'contact_template' => $contactTemplate('openid_contact_template'),
            ], $token);
            $code < 400 || fail("OpenID configuration failed ({$code}): {$body}");
        }

        if ($saml) {
            $base = 'http://localhost:8080';
            [$code, $body] = $request('PUT', "{$api}/administration/authentication/providers/saml", [
                'is_active' => true,
                'is_forced' => false,
                'remote_login_url' => "{$base}/realms/Centreon_SSO/protocol/saml/clients/centreon",
                'requested_authn_context' => false,
                'requested_authn_context_comparison' => 'exact',
                'entity_id_url' => "{$base}/realms/Centreon_SSO",
                'certificate' => SAML_CERTIFICATE,
                'user_id_attribute' => 'urn:oid:1.2.840.113549.1.9.1',
                'logout_from' => true,
                'logout_from_url' => "{$base}/realms/Centreon_SSO/protocol/saml",
                'auto_import' => true,
                'contact_template' => $contactTemplate('saml_contact_template'),
                'email_bind_attribute' => 'urn:oid:1.2.840.113549.1.9.1',
                'fullname_bind_attribute' => 'urn:oid:2.5.4.42',
                'authentication_conditions' => ['is_enabled' => false, 'attribute_path' => '', 'authorized_values' => []],
                'groups_mapping' => ['is_enabled' => false, 'attribute_path' => '', 'relations' => []],
                'roles_mapping' => ['is_enabled' => false, 'apply_only_first_role' => false, 'attribute_path' => '', 'relations' => []],
            ], $token);
            $code < 400 || fail("SAML configuration failed ({$code}): {$body}");
        }
    } finally {
        proc_terminate($server);
        proc_close($server);
    }
}

function cronFieldMatches(string $field, int $value): bool
{
    foreach (explode(',', $field) as $part) {
        [$range, $step] = array_pad(explode('/', $part, 2), 2, '1');
        if ($range === '*') {
            [$from, $to] = [0, 59];
        } elseif (str_contains($range, '-')) {
            [$from, $to] = array_map('intval', explode('-', $range, 2));
        } else {
            [$from, $to] = [(int) $range, (int) $range];
        }
        if ($value >= $from && $value <= $to && ($value - $from) % max(1, (int) $step) === 0) {
            return true;
        }
    }

    return false;
}

function cronMatches(string $schedule, int $time): bool
{
    [$minute, $hour, $day, $month, $weekday] = preg_split('/\s+/', trim($schedule));
    $now = array_map('intval', explode(' ', gmdate('i G j n w', $time)));

    return cronFieldMatches($minute, $now[0]) && cronFieldMatches($hour, $now[1])
        && cronFieldMatches($day, $now[2]) && cronFieldMatches($month, $now[3])
        && cronFieldMatches($weekday, $now[4]);
}

/**
 * Minimal crond: one process per job and per matching minute, output to the container logs,
 * no overlap of two runs of the same job (crond allows it; centAcl.php detects it with ps,
 * which this image does not have).
 */
function runCron(): never
{
    pcntl_async_signals(true);
    $stop = false;
    foreach ([SIGTERM, SIGINT] as $signal) {
        pcntl_signal($signal, static function () use (&$stop): void {
            $stop = true;
        });
    }

    $running = [];
    $nextMinute = intdiv(time(), 60) * 60 + 60;
    out('cron: ' . count(CRON_JOBS) . ' jobs scheduled (UTC): ' . implode(', ', array_keys(CRON_JOBS)));
    while (! $stop) {
        foreach ($running as $name => [$process, $started]) {
            $status = proc_get_status($process);
            if (! $status['running']) {
                proc_close($process);
                unset($running[$name]);
                out(sprintf('cron: %s exited with %d after %ds', $name, $status['exitcode'], time() - $started));
            }
        }

        if (time() >= $nextMinute) {
            foreach (CRON_JOBS as $name => [$schedule, $script]) {
                if (! cronMatches($schedule, $nextMinute)) {
                    continue;
                }
                if (isset($running[$name])) {
                    out("cron: {$name} is still running, run skipped");

                    continue;
                }
                $process = proc_open(
                    [BIN, 'php-cli', CENTREON_DIR . '/' . $script],
                    [0 => ['file', '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR],
                    $pipes
                );
                if ($process === false) {
                    out("cron: unable to start {$name}");

                    continue;
                }
                $running[$name] = [$process, time()];
                out("cron: {$name} started");
            }
            $nextMinute += 60;
        }
        sleep(1);
    }

    foreach ($running as [$process]) {
        proc_terminate($process);
        proc_close($process);
    }
    exit(0);
}

$role = $argv[1] ?? 'serve';
switch ($role) {
    case 'serve':
        @unlink(READY_FILE);
        prepareFiles();
        writeCentreonConfiguration();
        waitForInstalledDatabases();
        touch(READY_FILE);
        out('Centreon is ready');
        dropPrivileges();
        pcntl_exec(BIN, ['run', '--config', CADDYFILE, '--adapter', 'caddyfile']);
        fail('Unable to start FrankenPHP: ' . pcntl_strerror(pcntl_get_last_error()));

    case 'install':
        if (env('VICTORIA_METRICS_URL') === null) {
            fail('VICTORIA_METRICS_URL is required by the FrankenPHP centreon-web image (no rrdtool, no RRD file).');
        }
        prepareFiles();
        $root = waitForRoot();
        install($root);
        configureOptions($root);
        configureIdentityProviders();
        out('Centreon is installed');
        exit(0);

    case 'cron':
        prepareFiles();
        writeCentreonConfiguration();
        waitForInstalledDatabases();
        dropPrivileges();
        touch(READY_FILE);
        runCron();

    default:
        fail("Unknown role {$role}: serve (default), install or cron.");
}
