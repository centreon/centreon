<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

declare(strict_types=1);

require_once __DIR__ . '/../../../../bootstrap.php';

use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Core\Security\Vault\Infrastructure\Repository\{
    FsReadVaultConfigurationRepository,
    FsVaultConfigurationFactory
};
use Security\Encryption;
use Symfony\Component\Filesystem\Filesystem;
use Utility\UUIDGenerator;

/**
 * Migrate database credentials to Vault and update the different config files.
 *
 * @throws Throwable
 */
function migrateCredentialsToVault(): void
{
    $vaultConfiguration = getVaultConfiguration();
    $httpClient = new CentreonRestHttp();
    $vaultToken = authenticateToVault($vaultConfiguration, $httpClient);
    $vaultPath = migrateDatabaseCredentials($vaultConfiguration, $vaultToken, $httpClient);
    updateConfigFilesWithVaultPath($vaultPath);
}

/**
 * @throws Throwable
 *
 * @return VaultConfiguration
 */
function getVaultConfiguration(): VaultConfiguration
{
    $encryption = new Encryption();
    $encryption->setFirstKey($_ENV['APP_SECRET']);
    $readVaultConfigurationRepository = new FsReadVaultConfigurationRepository(
        _CENTREON_VARLIB_ . '/vault/vault.json',
        new Filesystem(),
        new FsVaultConfigurationFactory($encryption)
    );

    $vaultConfiguration = $readVaultConfigurationRepository->find();
    if ($vaultConfiguration === null) {
        throw new Exception('Unable to read Vault configuration');
    }

    return $vaultConfiguration;
}

/**
 * Authenticate to vault and retrieve token.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param CentreonRestHttp $httpClient
 *
 * @throws RestBadRequestException
 * @throws RestConflictException
 * @throws RestForbiddenException
 * @throws RestInternalServerErrorException
 * @throws RestMethodNotAllowedException
 * @throws RestNotFoundException
 * @throws RestUnauthorizedException
 *
 * @return string
 */
function authenticateToVault(VaultConfiguration $vaultConfiguration, CentreonRestHttp $httpClient): string
{
    $url = 'https://' . $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort()
        . '/v1/auth/approle/login';
    $body = [
        'role_id' => $vaultConfiguration->getRoleId(),
        'secret_id' => $vaultConfiguration->getSecretId(),
    ];

    $loginResponse = $httpClient->call($url, 'POST', $body);
    if (! isset($loginResponse['auth']['client_token'])) {
        throw new Exception('Unable to authenticate to Vault');
    }

    return $loginResponse['auth']['client_token'];
}

/**
 * Migrate database credentials to Vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param string $token
 * @param CentreonRestHttp $httpClient
 *
 * @throws RestBadRequestException
 * @throws RestConflictException
 * @throws RestForbiddenException
 * @throws RestInternalServerErrorException
 * @throws RestMethodNotAllowedException
 * @throws RestNotFoundException
 * @throws RestUnauthorizedException
 *
 * @return string
 */
function migrateDatabaseCredentials(
    VaultConfiguration $vaultConfiguration,
    string $token,
    CentreonRestHttp $httpClient
): string {
    $uuidGenerator = new UUIDGenerator();
    $uuid = $uuidGenerator->generateV4();
    $vaultPathUri = $vaultConfiguration->getRootPath() . '/data/database/' . $uuid;
    $vaultPath = 'secret::hashicorp_vault::' . $vaultPathUri;
    $credentials = retrieveDatabaseCredentialsFromConfigFile();
    $url = 'https://' . $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort()
        . '/v1/' . $vaultPathUri;
    $headers = [
        'X-Vault-Token: ' . $token,
    ];

    $body = [
        'data' => ['_DBUSERNAME' => $credentials['username'], '_DBPASSWORD' => $credentials['password']],
    ];

    $httpClient->call($url, 'POST', $body, $headers);

    return $vaultPath;
}

/**
 * Update the different config files with the vault path.
 *
 * @param $vaultPath
 *
 * @throws Exception
 */
function updateConfigFilesWithVaultPath($vaultPath): void
{
    updateCentreonConfPhpFile($vaultPath);
    updateCentreonConfPmFile($vaultPath);
    updateDatabaseYamlFile($vaultPath);
}

/**
 * Retrieve database credentials from config file.
 *
 * @throws Exception
 *
 * @return array{username: string, password: string
 */
function retrieveDatabaseCredentialsFromConfigFile(): array
{
    if (! file_exists(_CENTREON_ETC_ . '/centreon.conf.php')
        || ($content = file_get_contents(_CENTREON_ETC_ . '/centreon.conf.php')) === false
    ) {
        throw new Exception('Unable to retrieve content of file: ' . _CENTREON_ETC_ . '/centreon.conf.php');
    }

    preg_match(
        '/\$conf_centreon\[[\'\"]user[\'\"]\]\s*=\s*[\'\"](.*)[\'\"]\s*;/',
        $content,
        $matches
    );

    $userContent = $matches[1];

    preg_match(
        '/\$conf_centreon\[[\'\"]password[\'\"]\]\s*=\s*[\'\"](.*)[\'\"]\s*;/',
        $content,
        $matches
    );

    $passwordContent = $matches[1];

    return ['username' => $userContent, 'password' => $passwordContent];
}

/**
 * @param string $vaultPath
 *
 * @throws Exception
 */
function updateCentreonConfPhpFile(string $vaultPath): void
{
    if (! file_exists(_CENTREON_ETC_ . '/centreon.conf.php')
        || ($content = file_get_contents(_CENTREON_ETC_ . '/centreon.conf.php')) === false
    ) {
        throw new Exception('Unable to retrieve content of file: ' . _CENTREON_ETC_ . '/centreon.conf.php');
    }

    $newContentPhp = preg_replace(
        '/\$conf_centreon\[[\'\"]user[\'\"]\]\s*=\s*(.*)/',
        '\$conf_centreon[\'user\'] = \'' . $vaultPath . '\';',
        $content
    );
    $newContentPhp = preg_replace(
        '/\$conf_centreon\[[\'\"]password[\'\"]\]\s*=\s*(.*)/',
        '\$conf_centreon[\'password\'] = \'' . $vaultPath . '\';',
        $newContentPhp
    );

    file_put_contents(_CENTREON_ETC_ . '/centreon.conf.php', $newContentPhp)
        ?: throw new Exception('Unable to update file: ' . _CENTREON_ETC_ . '/centreon.conf.php');
}

/**
 * @param string $vaultPath
 *
 * @throws Exception
 */
function updateCentreonConfPmFile(string $vaultPath): void
{
    if (! file_exists(_CENTREON_ETC_ . '/conf.pm')
        || ($content = file_get_contents(_CENTREON_ETC_ . '/conf.pm')) === false
    ) {
        throw new Exception('Unable to retrieve content of file: ' . _CENTREON_ETC_ . '/conf.pm');
    }

    $newContentPm = preg_replace(
        '/"db_user"\s*=>\s*(.*)/',
        '"db_user" => "' . $vaultPath .'",',
        $content
    );
    $newContentPm = preg_replace(
        '/"db_passwd"\s*=>\s*(.*)/',
        '"db_passwd" => "' . $vaultPath .'"',
        $newContentPm
    );
    $newContentPm = preg_replace(
        '/\$mysql_user\s*=\s*(.*)/',
        '$mysql_user = "' . $vaultPath .'";',
        $newContentPm
    );
    $newContentPm = preg_replace(
        '/\$mysql_passwd\s*=\s*(.*)/',
        '$mysql_passwd = "' . $vaultPath .'";',
        $newContentPm
    );

    file_put_contents(_CENTREON_ETC_ . '/conf.pm', $newContentPm)
        ?: throw new Exception('Unable to update file: ' . _CENTREON_ETC_ . '/conf.pm');
}

/**
 * @param string $vaultPath
 *
 * @throws Exception
 */
function updateDatabaseYamlFile(string $vaultPath): void
{
    if (! file_exists(_CENTREON_ETC_ . '/config.d/10-database.yaml')
        || ($content = file_get_contents(_CENTREON_ETC_ . '/config.d/10-database.yaml')) === false
    ) {
        throw new Exception('Unable to retrieve content of file: ' . _CENTREON_ETC_
            . '/config.d/10-database.yaml');
    }
    $newContentYaml = preg_replace(
        '/username: (.*)/',
        'username: "' . $vaultPath . '"',
        $content
    );
    $newContentYaml = preg_replace(
        '/password: (.*)/',
        'password: "' . $vaultPath . '"',
        $newContentYaml
    );

    file_put_contents(_CENTREON_ETC_ . '/config.d/10-database.yaml', $newContentYaml)
        ?: throw new Exception('Unable to update file: ' . _CENTREON_ETC_ . '/config.d/10-database.yaml');
}
