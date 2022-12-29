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
use Core\Security\Vault\Domain\Model\VaultConfiguration;

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
        var_dump($params);
        $vaultConfiguration = $this->getVaultConfiguration();
        if ($vaultConfiguration !== null) {
            try {
                $httpClient = new CentreonRestHttp();
                $centreonLog = new CentreonUserLog(-1, $this->db);
                $clientToken = $this->authenticateToVault($vaultConfiguration, $centreonLog, $httpClient);
                $hostSecrets = $this->getHostSecretsFromVault(
                    $vaultConfiguration,
                    $hostId,
                    $clientToken,
                    $centreonLog,
                    $httpClient
                );

                if (
                    array_key_exists('host_snmp_community_is_password', $params)
                    && $params['host_snmp_community_is_password'] === '0'
                    && ! empty($hostSecrets)
                ) {
                    /**
                     * If no more fields are password types,
                     * we delete the host from the vault has it will not be readen.
                     */
                    $this->deleteHostFromVault(
                        $vaultConfiguration,
                        (int) $hostId,
                        $clientToken,
                        $centreonLog,
                        $httpClient
                    );
                } elseif (
                    array_key_exists('host_snmp_community', $params)
                    && $params['host_snmp_community_is_password'] === '1'
                ) {
                    //Replace olds vault values by the new ones
                    foreach($params as $keyName => $value) {
                        $hostSecrets[$keyName] = $value;
                    }
                    $this->writeSecretsInVault(
                        $vaultConfiguration,
                        $hostId,
                        $clientToken,
                        $hostSecrets,
                        $centreonLog,
                        $httpClient
                    );
                    $this->updateHostTablesWithVaultPath($vaultConfiguration, $hostId, $this->db);
                }
            } catch (\Throwable $ex) {
                error_log((string) $ex);
            }
        }

    }

    /**
     * Get vault configurations
     *
     * @return VaultConfiguration|null
     */
    private function getVaultConfiguration(): ?VaultConfiguration
    {
        $kernel = \App\Kernel::createForWeb();
        $readVaultConfigurationRepository = $kernel->getContainer()->get(
            Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface::class
        );

        return $readVaultConfigurationRepository->findDefaultVaultConfiguration();
    }

    /**
     * Get Client Token for Vault.
     *
     * @param VaultConfiguration $vaultConfiguration
     * @param CentreonUserLog $centreonLog
     * @param CentreonRestHttp $httpClient
     * @return string
     * @throws \Exception
     */
    private function authenticateToVault(
        VaultConfiguration $vaultConfiguration,
        CentreonUserLog $centreonLog,
        CentreonRestHttp $httpClient
    ): string {
        try {
            $url = $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort() . '/v1/auth/approle/login';
            $body = [
                "role_id" => $vaultConfiguration->getRoleId(),
                "secret_id" => $vaultConfiguration->getSecretId(),
            ];
            $centreonLog->insertLog(5, 'Authenticating to Vault: ' . $url);
            $loginResponse = $httpClient->call($url, "POST", $body);
        } catch (\Exception $ex) {
            $centreonLog->insertLog(5, $url . " did not respond with a 200 status");
            throw $ex;
        }

        if (! isset($loginResponse['auth']['client_token'])) {
            $centreonLog->insertLog(5, $url . " Unable to retrieve client token from Vault");
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
     * @param CentreonUserLog $centreonLog
     * @param CentreonRestHttp $httpClient
     * @return array<string, mixed>
     * @throws \Throwable
     */
    private function getHostSecretsFromVault(
        VaultConfiguration $vaultConfiguration,
        int $hostId,
        string $clientToken,
        CentreonUserLog $centreonLog,
        CentreonRestHttp $httpClient
    ): array {
        $url = $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort() . '/v1/'
            . $vaultConfiguration->getStorage() . '/centreon/hosts/' . $hostId;
        $centreonLog->insertLog(5, sprintf("Search Host %d secrets at: %s", $hostId, $url));
        $hostSecrets = [];
        try {
            $content = $httpClient->call($url, 'GET', null, ['X-Vault-Token: ' . $clientToken]);
        } catch (\RestNotFoundException $ex) {
            $centreonLog->insertLog(5, sprintf("Host %d not found in vault", $hostId));

            return $hostSecrets;
        } catch (\Exception $ex) {
            $centreonLog->insertLog(5, sprintf('Unable to get secrets for host : %d', $hostId));
            throw $ex;
        }
        if (array_key_exists('data', $content)) {
            $hostSecrets = $content['data'];
        }

        return $hostSecrets;
    }

    /**
     * Write Host secrets data in vault.
     *
     * @param VaultConfiguration $vaultConfiguration
     * @param integer $hostId
     * @param string $clientToken
     * @param array<string,mixed> $passwordTypeData
     * @param CentreonUserLog $centreonLog
     * @param CentreonRestHttp $httpClient
     * @throws \Exception
     */
    private function writeSecretsInVault(
        VaultConfiguration $vaultConfiguration,
        int $hostId,
        string $clientToken,
        array $passwordTypeData,
        CentreonUserLog $centreonLog,
        CentreonRestHttp $httpClient
    ): void {
        try {
            $url = $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort()
                . '/v1/' . $vaultConfiguration->getStorage()
                . '/centreon/hosts/' . $hostId;
            $centreonLog->insertLog(5, "Writing Host Secrets at : " . $url);
            $httpClient->call($url, "POST", $passwordTypeData, ['X-Vault-Token: ' . $clientToken]);
        } catch(\Exception $ex) {
            $centreonLog->insertLog(5, "Unable to write host secrets into vault");

            throw $ex;
        }

        $centreonLog->insertLog(
            5,
            sprintf(
                "Write successfully secrets in vault: %s",
                implode(', ', array_keys($passwordTypeData))
            )
        );
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
        $path = "secret::" . $vaultConfiguration->getId() . "::" . $vaultConfiguration->getStorage()
            . "/hosts/" . $hostId;

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
     * Delete host secrets data from vault
     *
     * @param VaultConfiguration $vaultConfiguration
     * @param integer $hostId
     * @param string $clientToken
     * @param CentreonUserLog $centreonLog
     * @param CentreonRestHttp $httpClient
     * @throws \Throwable
     */
    private function deleteHostFromVault(
        VaultConfiguration $vaultConfiguration,
        int $hostId,
        string $clientToken,
        CentreonUserLog $centreonLog,
        CentreonRestHttp $httpClient
    ): void {
        $url = $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort() . '/v1/'
            . $vaultConfiguration->getStorage() . '/centreon/hosts/' . $hostId;
        $centreonLog->insertLog(5, sprintf("Deleting Host: %d", $hostId));
        try {
            $httpClient->call($url, 'DELETE', null, ['X-Vault-Token: ' . $clientToken]);
        } catch (\Exception $ex) {
            $centreonLog->insertLog(5, sprintf("Unable to delete Host: %d", $hostId));
            throw $ex;
        }
    }
}
