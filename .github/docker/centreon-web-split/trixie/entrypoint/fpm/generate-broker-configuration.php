<?php

declare(strict_types=1);

/*
 * Triggers "Generate Configuration Files" + "Move Export Files" + reload for
 * a monitoring server via the REST API's `generate-and-reload` endpoint —
 * the same mechanism the legacy UI's two-click flow drives — so
 * centreon-broker gets a real config delivered via gorgone (REMOTECOPY)
 * instead of booting against the package-default central-broker.json.
 *
 * A plain HTTP client, not a direct in-process UseCase call: tried that
 * first and found GenerateConfiguration::execute() requires an
 * authenticated admin ContactInterface, which only gets populated by a real
 * HTTP request's auth listener — faking one from a CLI context would mean
 * reimplementing that listener's logic, fragile and untested. Not placed
 * under www/install/steps/process/ like the install scripts either: this
 * runs from a background retry loop (container.d/*_background*.sh) that can
 * keep trying long after 12-install.sh has deleted www/install/.
 *
 * allow_url_fopen is Off in this image's php.ini (centreon.ini.template) —
 * the caller invokes this with `-d allow_url_fopen=On` rather than adding a
 * curl dependency for one script.
 *
 * Before attempting delivery, polls gorgone's own HTTP API
 * (GET /internal/constatus, confirmed reachable over plain HTTP with no
 * auth) for the poller node's connection state, instead of just trying
 * blind — the REST generate-and-reload endpoint's 204 only confirms the
 * API-level call completed, not that the downstream gorgone REMOTECOPY had
 * anywhere connected to land on (confirmed for real: it returned 204 once
 * while gorgone-central-poller's pullwss module was still loading, and
 * nothing was actually delivered). This is a latency optimization, not a
 * correctness requirement: the calling shell loop
 * (container.d/13-generate-broker-config_background.sh) keeps retrying
 * forever regardless, so a constatus timeout here just falls through to
 * attempting the call anyway rather than blocking indefinitely.
 */

$apiBase = rtrim(getenv('CENTREON_INTERNAL_API_BASE_URL') ?: 'http://httpd:80', '/') . '/centreon/api/latest';
$pollerId = getenv('CENTRAL_POLLER_ID') ?: '1';
$login = getenv('CENTREON_API_USERNAME') ?: 'admin';
$password = getenv('CENTREON_API_PASSWORD') ?: 'Centreon!2021';
// Same id in this topology as $pollerId above (GORGONE_UID is set to match
// Central's nagios_server.id in docker-compose.yml) — gorgone's own node
// numbering and Centreon's monitoring_server id happen to coincide here,
// not reused out of laziness.
$gorgoneApiBase = rtrim(getenv('GORGONE_API_BASE_URL') ?: 'http://gorgone-central:8085', '/') . '/api';
// gorgone-central's HTTP API may require basic auth (service account).
$gorgoneAuth = [];
if ((getenv('GORGONE_API_USERNAME') ?: '') !== '' && (getenv('GORGONE_API_PASSWORD') ?: '') !== '') {
    $gorgoneAuth = ['Authorization: Basic '
        . base64_encode(getenv('GORGONE_API_USERNAME') . ':' . getenv('GORGONE_API_PASSWORD'))];
}
$gorgoneNodeId = $pollerId;

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
            'timeout' => 15,
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

/**
 * True once gorgone reports at least one successful ping for this node —
 * ping_ok/last_ping_recv both start at 0 immediately after the websocket
 * connects (confirmed empirically: a just-connected node still reads 0
 * for a few seconds, through the first ping cycle), so this is a real
 * "actually talking", not just "socket open" check.
 */
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

// gorgone-central loads its node list from the DB once at startup. If it
// started before this install created the schema (no depends_on ordering
// between containers of one Kubernetes pod; compose only got it right via
// `depends_on: fpm: service_healthy`), that list stays empty and the
// poller is refused forever with "please registernodes". Asking for a
// resync whenever the node isn't connected yet is idempotent and fixes it
// whatever the start order (confirmed on k3d: one manual resync and the
// poller was accepted on its next pullwss retry).
if (! isGorgoneNodeConnected($gorgoneApiBase, $gorgoneNodeId)) {
    [$syncStatus] = apiCall('POST', "{$gorgoneApiBase}/centreon/nodes/sync", '{}', array_merge(['Content-Type: application/json'], $gorgoneAuth));
    echo "Gorgone node {$gorgoneNodeId} not connected, requested a nodes resync (HTTP {$syncStatus}).\n";
}
// Bounded wait, not indefinite: stays under the shell loop's own 60s retry
// interval so this never doubles up with it, and falls through to
// attempting delivery anyway on timeout (see the file-level comment above).
$maxWaitSeconds = 55;
$pollIntervalSeconds = 2;
$waited = 0;
while ($waited < $maxWaitSeconds && ! isGorgoneNodeConnected($gorgoneApiBase, $gorgoneNodeId)) {
    sleep($pollIntervalSeconds);
    $waited += $pollIntervalSeconds;
}

if ($waited >= $maxWaitSeconds && getenv('CONFIG_DELIVERY_REQUIRE_CONNECTED') === '1') {
    // A 204 without a connected node delivers nothing, so a one-shot caller
    // (the helm chart's config-delivery Job) must not count it as done.
    fwrite(STDERR, "Gorgone node {$gorgoneNodeId} not connected after {$maxWaitSeconds}s, not attempting delivery.\n");
    exit(1);
} elseif ($waited >= $maxWaitSeconds) {
    echo "Gorgone node {$gorgoneNodeId} not confirmed connected after {$maxWaitSeconds}s, attempting delivery anyway.\n";
} else {
    echo "Gorgone node {$gorgoneNodeId} connected, proceeding.\n";
}

[$loginStatus, $loginBody] = apiCall(
    'POST',
    "{$apiBase}/login",
    json_encode(['security' => ['credentials' => ['login' => $login, 'password' => $password]]]),
    ['Content-Type: application/json'],
);

if ($loginStatus !== 200) {
    fwrite(STDERR, "Login failed (HTTP {$loginStatus}).\n");
    exit(1);
}

$decoded = json_decode($loginBody, true);
$token = is_array($decoded) ? ($decoded['security']['token'] ?? null) : null;

if (! is_string($token) || $token === '') {
    fwrite(STDERR, "Login succeeded but no token in the response.\n");
    exit(1);
}

[$genStatus, $genBody] = apiCall(
    'GET',
    "{$apiBase}/configuration/monitoring-servers/{$pollerId}/generate-and-reload",
    null,
    ["X-AUTH-TOKEN: {$token}"],
);

if ($genStatus === 204) {
    echo "Configuration generated and delivered for poller {$pollerId} (HTTP 204).\n";
    exit(0);
}

fwrite(STDERR, "generate-and-reload failed for poller {$pollerId} (HTTP {$genStatus}): {$genBody}\n");
exit(1);
