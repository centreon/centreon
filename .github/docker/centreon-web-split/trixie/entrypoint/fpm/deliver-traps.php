<?php

declare(strict_types=1);

/*
 * First centreontrapd database of the pollers with SNMP traps, the same thing
 * the UI's "Configuration > SNMP Traps > Generate" does: bin/generateSqlLite
 * builds centreontrapd.sdb from the configuration database, then a SYNCTRAP
 * legacy command (the file embedded, as LegacyCommandSender::sendTrapDatabase
 * sends it) has the poller's gorgone write it.
 *
 * Without it a fresh poller has no database at all: its trap definitions are
 * mounted read-only in centreontrapd, which tries to create the missing file,
 * gets "Read-only file system" and exits, again and again until someone
 * generates the traps. An empty file is no better: centreontrapd's first
 * query (cfg_nagios) fails on it.
 *
 * Needs the configuration database: run through container.sh, which writes
 * /etc/centreon/centreon.conf.php (read by generateSqlLite) before exec'ing
 * the caller's command. Later changes to traps are the admin's to generate,
 * as on any platform.
 *
 * TRAP_POLLER_IDS: nagios_server ids (the Central poller); TRAP_POLLER_UIDS:
 * nagios_server uids (remote pollers, known to the caller by uid only). Each
 * poller is waited for (gorgone constatus) up to TRAP_WAIT_SECONDS. Exits 1 if
 * any poller did not get its database, after trying them all.
 */

$gorgoneApiBase = rtrim(getenv('GORGONE_API_BASE_URL') ?: 'http://gorgone-central:8085', '/') . '/api';
$gorgoneAuth = [];
if ((getenv('GORGONE_API_USERNAME') ?: '') !== '' && (getenv('GORGONE_API_PASSWORD') ?: '') !== '') {
    $gorgoneAuth = ['Authorization: Basic '
        . base64_encode(getenv('GORGONE_API_USERNAME') . ':' . getenv('GORGONE_API_PASSWORD'))];
}
$waitSeconds = (int) (getenv('TRAP_WAIT_SECONDS') ?: '300');

/**
 * @param array<int,string> $headers
 * @return array{0:int,1:string}
 */
function apiCall(string $method, string $url, ?string $body, array $headers): array
{
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body ?? '',
            'ignore_errors' => true,
            'timeout' => 30,
        ],
    ]);

    $responseBody = @file_get_contents($url, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    $statusCode = 0;
    if (preg_match('#HTTP/\S+\s+(\d+)#', $statusLine, $matches) === 1) {
        $statusCode = (int) $matches[1];
    }

    return [$statusCode, $responseBody === false ? '' : $responseBody];
}

// Same check as generate-broker-configuration.php: at least one ping answered.
function isGorgoneNodeConnected(string $gorgoneApiBase, string $nodeId): bool
{
    [$status, $body] = apiCall('GET', "{$gorgoneApiBase}/internal/constatus", null, $GLOBALS['gorgoneAuth']);
    if ($status !== 200) {
        return false;
    }

    $decoded = json_decode($body, true);
    $node = is_array($decoded) ? ($decoded['data'][$nodeId] ?? null) : null;
    if (! is_array($node)) {
        return false;
    }

    return (int) ($node['ping_ok'] ?? 0) > 0 || (int) ($node['last_ping_recv'] ?? 0) > 0;
}

$pollerIds = preg_split('/[\s,]+/', trim(getenv('TRAP_POLLER_IDS') ?: ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$pollerUids = preg_split('/[\s,]+/', trim(getenv('TRAP_POLLER_UIDS') ?: ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

if ($pollerUids !== []) {
    require_once '/usr/share/centreon/config/centreon.config.php';
    // Database TLS as DatabaseTLSResolver applies it (DATABASE_* env).
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
    if (getenv('DATABASE_SSL_ENABLED') === '1') {
        $options[PDO::MYSQL_ATTR_SSL_CA] = getenv('DATABASE_CA_PATH') ?: '';
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = getenv('DATABASE_VERIFY_SERVER_CERT') === '1';
    }
    $pdo = new PDO(
        'mysql:host=' . $conf_centreon['hostCentreon'] . ';port=' . $conf_centreon['port'] . ';dbname=' . $conf_centreon['db'],
        $conf_centreon['user'],
        $conf_centreon['password'],
        $options
    );
    $stmt = $pdo->prepare('SELECT id FROM nagios_server WHERE uid = :uid');
    foreach ($pollerUids as $uid) {
        $stmt->execute([':uid' => $uid]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            fwrite(STDERR, "WARNING: no poller with uid {$uid} in the configuration, skipped.\n");
            continue;
        }
        $pollerIds[] = (string) $id;
    }
}

$failed = 0;
foreach (array_unique($pollerIds) as $pollerId) {
    $waited = 0;
    while ($waited < $waitSeconds && ! isGorgoneNodeConnected($gorgoneApiBase, $pollerId)) {
        sleep(5);
        $waited += 5;
    }
    if ($waited >= $waitSeconds) {
        fwrite(STDERR, "Poller {$pollerId}: not connected to gorgone after {$waitSeconds}s, trap database not sent.\n");
        $failed++;
        continue;
    }

    $file = sys_get_temp_dir() . "/centreon-traps/{$pollerId}/centreontrapd.sdb";
    @mkdir(dirname($file), 0700, true);
    @unlink($file);
    exec(
        'php /usr/share/centreon/bin/generateSqlLite ' . escapeshellarg($pollerId) . ' ' . escapeshellarg($file) . ' 2>&1',
        $output,
        $returnVal
    );
    if ($returnVal !== 0 || ! is_file($file)) {
        fwrite(STDERR, "Poller {$pollerId}: generateSqlLite failed ({$returnVal}): " . implode(' ', $output) . "\n");
        $failed++;
        continue;
    }

    [$status, $body] = apiCall(
        'POST',
        "{$gorgoneApiBase}/centreon/legacycmd/command",
        json_encode([[
            'command' => 'SYNCTRAP',
            'target' => $pollerId,
            'files' => ['trap' => [['name' => 'centreontrapd.sdb', 'content' => base64_encode((string) file_get_contents($file))]]],
        ]]),
        array_merge(['Content-Type: application/json'], $gorgoneAuth),
    );
    if ($status !== 200) {
        fwrite(STDERR, "Poller {$pollerId}: SYNCTRAP refused by gorgone (HTTP {$status}): {$body}\n");
        $failed++;
        continue;
    }
    echo "Poller {$pollerId}: trap database (" . filesize($file) . " bytes) sent, SYNCTRAP accepted.\n";
}

exit($failed > 0 ? 1 : 0);
