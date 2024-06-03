<?php

/*
 * Copyright 2005-2022 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

session_start();
require_once __DIR__ . '/../../../../bootstrap.php';
require_once __DIR__ . '/../functions.php';
require __DIR__ . '/../../../common/common-Func.php';


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
            '/var/lib/centreon/vault/vault.yaml',
            new \Symfony\Component\Filesystem\Filesystem()
        );
    $vaultConfiguration = new \Core\Security\Vault\Domain\Model\NewVaultConfiguration(
        $encryption,
        'hashicorp_vault',
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

if (!count($err['required'])  && trim($err['connection_error']) == '') {
    $step = new \CentreonLegacy\Core\Install\Step\Step6Vault($dependencyInjector);
    $step->setVaultConfiguration($parameters);
}

echo json_encode($err);
