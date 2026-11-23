<?php

// gls PoC only, until the web generator writes them (contracts.md § 1, § 2):
//   api <central-broker.json>   adds centreonBroker.remote_api
//   actions <*-module.json>...  adds remote_actions + remote_actions_insecure (no TLS yet, flagged)
declare(strict_types=1);

[$self, $mode] = [$argv[0], $argv[1] ?? ''];
$files = array_slice($argv, 2);
if (! in_array($mode, ['api', 'actions'], true) || $files === []) {
    fwrite(STDERR, "usage: {$self} api|actions <file>...\n");
    exit(2);
}

foreach ($files as $file) {
    $json = json_decode((string) file_get_contents($file), true);
    if (! is_array($json) || ! isset($json['centreonBroker'])) {
        fwrite(STDERR, "{$file}: not a broker configuration\n");
        exit(1);
    }
    if ($mode === 'api') {
        $json['centreonBroker']['remote_api'] = [
            'listen_address' => '0.0.0.0',
            'port' => '8099',
            'token' => getenv('CENTREON_BROKER_API_TOKEN') ?: 'gls-poc-token',
        ];
    } else {
        $json['centreonBroker']['remote_actions'] = 'yes';
        $json['centreonBroker']['remote_actions_insecure'] = 'yes';
    }
    if (file_put_contents($file, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n") === false) {
        fwrite(STDERR, "{$file}: write failed\n");
        exit(1);
    }
    echo "{$file}: {$mode} keys set\n";
}
