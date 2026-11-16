<?php

// Healthcheck of the serve role without shell nor curl binary:
// `[ -f /tmp/docker.ready ] && curl --fail http://localhost/centreon/api/latest/platform/versions`
if (! file_exists('/tmp/docker.ready')) {
    exit(1);
}
$handle = curl_init('http://127.0.0.1:80/centreon/api/latest/platform/versions');
curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FAILONERROR => true, CURLOPT_TIMEOUT => 5]);
exit(curl_exec($handle) === false ? 1 : 0);
