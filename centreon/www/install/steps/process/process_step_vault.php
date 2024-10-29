<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */
session_start();
require_once __DIR__ . '/../../../../bootstrap.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../../install.conf.php';

$requiredParameters = [
    'address',
    'port',
    'root_path',
    'role_id',
    'secret_id',
];

$err = [
    'required' => [],
    'connection_error' => '',
];

$parameters = filter_input_array(INPUT_POST);

foreach (array_keys($parameters) as $fieldName) {
    if (in_array($fieldName, $requiredParameters) && trim($value) === '') {
        $err['required'][] = $name;
    }
}

try {
    $url = $parameters['address'] . ':' . $parameters['port'] . '/v1/auth/approle/login';
    $url = sprintf('%s://%s', 'https', $url);
    $body = [
        'role_id' => $parameters['role_id'],
        'secret_id' => $parameters['secret_id'],
    ];

    $httpClient = new \Symfony\Component\HttpClient\CurlHttpClient();
    $loginResponse = $httpClient->request('POST', $url, ['json' => $body]);
    $content = json_decode($loginResponse->getContent(), true);
    if (! isset($content['auth']['client_token'])) {
        throw new \Exception('Unable to authenticate to Vault');
    }

    /**
     * @var \Security\Interfaces\EncryptionInterface $encryption
     */
    $encryption = new \Security\Encryption();
    (new \Symfony\Component\Dotenv\Dotenv())->bootEnv('/usr/share/centreon/.env');
    $encryption->setFirstKey($_ENV["APP_SECRET"]);
    $writeVaultConfigurationRepository =
        new \Core\Security\Vault\Infrastructure\Repository\FsWriteVaultConfigurationRepository(
            $conf_centreon['centreon_varlib'] . '/vault/vault.json',
            new \Symfony\Component\Filesystem\Filesystem()
        );
    $vaultConfiguration = new \Core\Security\Vault\Domain\Model\NewVaultConfiguration(
        $encryption,
        $parameters['address'],
        (int) $parameters['port'],
        $parameters['root_path'],
        $parameters['role_id'],
        $parameters['secret_id']
    );
    $writeVaultConfigurationRepository->create($vaultConfiguration);

} catch (\Symfony\Component\HttpClient\Exception\TransportException $e) {
    $err['connection_error'] = $e->getMessage();
} catch (\Throwable $e) {
    $err['connection_error'] = "Unable to create vault configuration";
}

if ($err['required'] === []  && trim($err['connection_error']) == '') {
    $step = new \CentreonLegacy\Core\Install\Step\Step6Vault($dependencyInjector);
    $step->setVaultConfiguration($parameters);
}

echo json_encode($err);
