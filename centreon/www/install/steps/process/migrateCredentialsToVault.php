<?php

/*
 * Copyright 2005-2024 Centreon
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

declare(strict_types=1);

require_once __DIR__ . '/../../../../bootstrap.php';

use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Core\Security\Vault\Infrastructure\Repository\{
    FsReadVaultConfigurationRepository,
    FsVaultConfigurationFactory
};
use Security\Encryption;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Migrate database credentials to Vault and update the different config files.
 *
 * @return void
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
 * Retrieve Vault configuration.
 *
 * @return VaultConfiguration
 *
 * @throws Throwable
 */
function getVaultConfiguration(): VaultConfiguration
{
    $encryption = new Encryption();
    $encryption->setFirstKey($_ENV["APP_SECRET"]);
    $readVaultConfigurationRepository = new FsReadVaultConfigurationRepository(
        '/var/lib/centreon/vault/vault.json',
        new Filesystem(),
        new FsVaultConfigurationFactory($encryption)
    );

    $vaultConfiguration = $readVaultConfigurationRepository->find();
    if ($vaultConfiguration === null) {
        throw new \Exception('Unable to read Vault configuration');
    }

    return $vaultConfiguration;
}

/**
 * Authenticate to vault and retrieve token.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param CentreonRestHttp $httpClient
 *
 * @return string
 *
 * @throws RestBadRequestException
 * @throws RestConflictException
 * @throws RestForbiddenException
 * @throws RestInternalServerErrorException
 * @throws RestMethodNotAllowedException
 * @throws RestNotFoundException
 * @throws RestUnauthorizedException
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
        throw new \Exception('Unable to authenticate to Vault');
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
 * @return string
 *
 * @throws RestBadRequestException
 * @throws RestConflictException
 * @throws RestForbiddenException
 * @throws RestInternalServerErrorException
 * @throws RestMethodNotAllowedException
 * @throws RestNotFoundException
 * @throws RestUnauthorizedException
 */
function migrateDatabaseCredentials(
    VaultConfiguration $vaultConfiguration,
    string $token,
    CentreonRestHttp $httpClient
): string {
    $uuidGenerator = new \Utility\UUIDGenerator();
    $uuid = $uuidGenerator->generateV4();
    $vaultPathUri = "jeremy/data/database/" . $uuid;
    $vaultPath = "secret::hashicorp_vault::" . $vaultPathUri;
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

function updateConfigFilesWithVaultPath($vaultPath): void
{
    updateCentreonConfPhpFile($vaultPath);
    updateCentreonConfPmFile($vaultPath);
    updateDatabaseYamlFile($vaultPath);
}

function retrieveDatabaseCredentialsFromConfigFile(): array
{
    $content = file_get_contents("/etc/centreon/centreon.conf.php");

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

function updateCentreonConfPhpFile(string $vaultPath): void
{
    $content = file_get_contents("/etc/centreon/centreon.conf.php");

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

    file_put_contents("/etc/centreon/centreon.conf.php", $newContentPhp);
}

function updateCentreonConfPmFile(string $vaultPath): void
{
    $content = file_get_contents("/etc/centreon/conf.pm");

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

    file_put_contents("/etc/centreon/conf.pm", $newContentPm);
}

function updateDatabaseYamlFile(string $vaultPath): void
{
    $content = file_get_contents("/etc/centreon/config.d/10-database.yaml");

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

    file_put_contents("/etc/centreon/config.d/10-database.yaml", $newContentYaml);
}
