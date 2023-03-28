<?php
/*
 * Copyright 2005-2015 CENTREON
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
 * As a special exception, the copyright holders of this program give CENTREON
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of CENTREON choice, provided that
 * CENTREON also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

require_once "Centreon/Object/Object.php";

use Centreon\Domain\Log\LegacyLogger;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;

/**
 * Used for interacting with hosts
 *
 * @author sylvestre
 */
class Centreon_Object_Host extends Centreon_Object
{
    protected $table = "host";
    protected $primaryKey = "host_id";
    protected $uniqueLabelField = "host_name";
    private static ?ReadVaultConfigurationRepositoryInterface $repository = null;

    private const VAULT_DEFAULT_SCHEME = 'https';

    /**
     * Update host table
     *
     * @param $hostId
     * @param array $params
     * @return void
     */
    public function update($hostId, $params = [])
    {
        parent::update($hostId, $params);
        $vaultConfiguration = $this->getVaultConfiguration();
        if ($vaultConfiguration === null) {
            return;
        }
        try {
            $httpClient = new CentreonRestHttp();
            $logger = $this->getLogger();
            $clientToken = $this->authenticateToVault($vaultConfiguration, $logger, $httpClient);
            $hostSecrets = $this->getHostSecretsFromVault(
                $vaultConfiguration,
                $hostId,
                $clientToken,
                $logger,
                $httpClient
            );

            if (array_key_exists('host_snmp_community', $params)) {
                //Replace olds vault values by the new ones
                $hostSecrets['_HOSTSNMPCOMMUNITY'] = $params['host_snmp_community'];
                $this->writeSecretsInVault(
                    $vaultConfiguration,
                    $hostId,
                    $clientToken,
                    $hostSecrets,
                    $logger,
                    $httpClient
                );
                $this->updateHostTablesWithVaultPath($vaultConfiguration, $hostId, $this->db);
            }
        } catch (\Throwable $ex) {
            error_log((string) $ex);
        }
    }

    /**
     * Get vault configurations
     *
     * @return VaultConfiguration|null
     */
    private function getVaultConfiguration(): ?VaultConfiguration
    {
        $readVaultConfigurationRepository = self::getVaultConfigurationRepositoryInstance();

        return $readVaultConfigurationRepository->findDefaultVaultConfiguration();
    }

    /**
     * Get Client Token for Vault.
     *
     * @param VaultConfiguration $vaultConfiguration
     * @param LegacyLogger $logger
     * @param CentreonRestHttp $httpClient
     * @return string
     * @throws \Throwable
     */
    private function authenticateToVault(
        VaultConfiguration $vaultConfiguration,
        LegacyLogger $logger,
        CentreonRestHttp $httpClient
    ): string {
        try {
            $url = $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort() . '/v1/auth/approle/login';
            $url = sprintf("%s://%s", self::VAULT_DEFAULT_SCHEME, $url);
            $body = [
                "role_id" => $vaultConfiguration->getRoleId(),
                "secret_id" => $vaultConfiguration->getSecretId(),
            ];
            $logger->info('Authenticating to Vault: ' . $url);
            $loginResponse = $httpClient->call($url, "POST", $body);
        } catch (\Throwable $ex) {
            $logger->error($url . " did not respond with a 2XX status");
            throw $ex;
        }

        if (! isset($loginResponse['auth']['client_token'])) {
            $logger->error($url . " Unable to retrieve client token from Vault");
            throw new \Exception('Unable to authenticate to Vault');
        }
        return $loginResponse['auth']['client_token'];
    }

    /**
     * Get host secrets data from vault
     *
     * @param VaultConfiguration $vaultConfiguration
     * @param integer $hostId
     * @param string $clientToken
     * @param LegacyLogger $logger
     * @param CentreonRestHttp $httpClient
     * @return array<string, mixed>
     * @throws \Throwable
     */
    private function getHostSecretsFromVault(
        VaultConfiguration $vaultConfiguration,
        int $hostId,
        string $clientToken,
        LegacyLogger $logger,
        CentreonRestHttp $httpClient
    ): array {
        $url = $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort() . '/v1/'
            . $vaultConfiguration->getRootPath() . '/monitoring/hosts/' . $hostId;
        $url = sprintf("%s://%s", self::VAULT_DEFAULT_SCHEME, $url);
        $logger->info(sprintf("Search Host %d secrets at: %s", $hostId, $url));
        try {
            $content = $httpClient->call($url, 'GET', null, ['X-Vault-Token: ' . $clientToken]);
        } catch (\RestNotFoundException $ex) {
            $logger->info(sprintf("Host %d not found in vault", $hostId));

            return [];
        } catch (\Exception $ex) {
            $logger->error(sprintf('Unable to get secrets for host : %d', $hostId));
            throw $ex;
        }
        if (array_key_exists('data', $content)) {
            return $content['data'];
        }

        return [];
    }

    /**
     * Write Host secrets data in vault.
     *
     * @param VaultConfiguration $vaultConfiguration
     * @param integer $hostId
     * @param string $clientToken
     * @param array<string,mixed> $passwordTypeData
     * @param LegacyLogger $logger
     * @param CentreonRestHttp $httpClient
     * @throws \Exception
     */
    private function writeSecretsInVault(
        VaultConfiguration $vaultConfiguration,
        int $hostId,
        string $clientToken,
        array $passwordTypeData,
        LegacyLogger $logger,
        CentreonRestHttp $httpClient
    ): void {
        try {
            $url = $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort()
                . '/v1/' . $vaultConfiguration->getRootPath()
                . '/monitoring/hosts/' . $hostId;
            $url = sprintf("%s://%s", self::VAULT_DEFAULT_SCHEME, $url);
            $logger->info(
                "Writing Host Secrets at : " . $url,
                ["host_id" => $hostId, "secrets" => implode(", ", array_keys($passwordTypeData))]
            );
            $httpClient->call($url, "POST", $passwordTypeData, ['X-Vault-Token: ' . $clientToken]);
        } catch(\Exception $ex) {
            $logger->error(
                "Unable to write host secrets into vault",
                [
                    "message" => $ex->getMessage(),
                    "trace" => $ex->getTraceAsString(),
                    "host_id" => $hostId,
                    "secrets" => implode(", ", array_keys($passwordTypeData))
                ]
            );

            throw $ex;
        }

        $logger->info(sprintf("Write successfully secrets in vault: %s", implode(', ', array_keys($passwordTypeData))));
    }

    /**
     * Update host table with secrets path on vault.
     *
     * @param VaultConfiguration $vaultConfiguration
     * @param integer $hostId
     * @param \CentreonDB $pearDB
     * @throws \Throwable
     */
    private function updateHostTablesWithVaultPath(
        VaultConfiguration $vaultConfiguration,
        int $hostId,
        \CentreonDB $pearDB
    ): void {
        $path = "secret::" . $vaultConfiguration->getId() . "::" . $vaultConfiguration->getRootPath()
            . "/monitoring/hosts/" . $hostId;

        $statementUpdateHost = $pearDB->prepare(
            <<<SQL
                UPDATE `host` SET host_snmp_community = :path WHERE host_id = :hostId
            SQL
        );
        $statementUpdateHost->bindValue(':path', $path, \PDO::PARAM_STR);
        $statementUpdateHost->bindValue(':hostId', (int) $hostId);
        $statementUpdateHost->execute();
    }

    /**
     * Singleton for Repository Instanciation
     *
     * @return ReadVaultConfigurationRepositoryInterface
     */
    private static function getVaultConfigurationRepositoryInstance(): ReadVaultConfigurationRepositoryInterface
    {
        if (self::$repository === null) {
            $kernel = \App\Kernel::createForWeb();
            self::$repository = $kernel->getContainer()->get(
                Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface::class
            );
        }

        return self::$repository;
    }

    /**
     * Get logger
     *
     * @return LegacyLogger
     */
    private function getLogger(): LegacyLogger
    {
        try {
            $kernel = \App\Kernel::createForWeb();
            $logger = $kernel->getContainer()->get(\Centreon\Domain\Log\LegacyLogger::class);
        } catch(\Throwable $ex) {
            error_log((string) $ex);
            throw $ex;
        }

        return $logger;
    }
}
