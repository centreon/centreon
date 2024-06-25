<?php

/*
 * Copyright 2005-2023 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
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

use Centreon\Domain\Log\Logger;
use Utility\Interfaces\UUIDGeneratorInterface;
use Core\Security\Vault\Domain\Model\VaultConfiguration;

const VAULT_PATH_REGEX = '^secret::[^:]*::';
const SNMP_COMMUNITY_MACRO_NAME = '_HOSTSNMPCOMMUNITY';
const DEFAULT_SCHEME = 'https';

/**
 * Get Client Token for Vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param Logger $logger
 * @param CentreonRestHttp $httpClient
 *
 * @throws Exception
 *
 * @return string
 */
function authenticateToVault(
    VaultConfiguration $vaultConfiguration,
    Logger $logger,
    CentreonRestHttp $httpClient
): string {
    try {
        $url = $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort() . '/v1/auth/approle/login';
        $url = sprintf('%s://%s', DEFAULT_SCHEME, $url);
        $body = [
            'role_id' => $vaultConfiguration->getRoleId(),
            'secret_id' => $vaultConfiguration->getSecretId(),
        ];
        $logger->info('Authenticating to Vault: ' . $url);
        $loginResponse = $httpClient->call($url, 'POST', $body);
    } catch (Exception $ex) {
        $logger->error($url . ' did not respond with a 2XX status');

        throw $ex;
    }

    if (! isset($loginResponse['auth']['client_token'])) {
        $logger->error($url . ' Unable to retrieve client token from Vault');

        throw new Exception('Unable to authenticate to Vault');
    }

    return $loginResponse['auth']['client_token'];
}

/**
 * Get host secrets data from vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param int $hostId
 * @param string $uuid
 * @param string $clientToken
 * @param Logger $logger
 * @param CentreonRestHttp $httpClient
 *
 * @throws Throwable
 *
 * @return array<string, mixed>
 */
function getHostSecretsFromVault(
    VaultConfiguration $vaultConfiguration,
    int $hostId,
    string $uuid,
    string $clientToken,
    Logger $logger,
    CentreonRestHttp $httpClient
): array {
    $url = $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort() . '/v1/'
        . $vaultConfiguration->getRootPath() . '/data/monitoring/hosts/' . $uuid;
    $url = sprintf('%s://%s', DEFAULT_SCHEME, $url);
    $logger->info(sprintf('Search Host %d secrets at: %s', $hostId, $url));

    try {
        $content = $httpClient->call($url, 'GET', null, ['X-Vault-Token: ' . $clientToken]);
        if (array_key_exists('data', $content) && array_key_exists('data', $content['data'])) {
            return $content['data']['data'];
        }

        return [];
    } catch (RestNotFoundException $ex) {
        $logger->info(sprintf('Host %d not found in vault', $hostId));

        return [];
    } catch (Exception $ex) {
        $logger->error(sprintf('Unable to get secrets for host : %d', $hostId));

        throw $ex;
    }
}

/**
 * Write Host secrets data in vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param string $uuid
 * @param string $clientToken
 * @param array<string,mixed> $passwordTypeData
 * @param Logger $logger
 * @param CentreonRestHttp $httpClient
 *
 * @throws Exception
 */
function writeHostSecretsInVault(
    VaultConfiguration $vaultConfiguration,
    string $uuid,
    string $clientToken,
    array $passwordTypeData,
    Logger $logger,
    CentreonRestHttp $httpClient
): void {
    try {
        $url = $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort()
            . '/v1/' . $vaultConfiguration->getRootPath()
            . '/data/monitoring/hosts/' . $uuid;
        $url = sprintf('%s://%s', DEFAULT_SCHEME, $url);
        $logger->info(
            'Writing Host Secrets at : ' . $url,
            ['secrets' => implode(', ', array_keys($passwordTypeData))]
        );
        $httpClient->call($url, 'POST', ['data' => $passwordTypeData], ['X-Vault-Token: ' . $clientToken]);
    } catch (Exception $ex) {
        $logger->error(
            'Unable to write host secrets into vault',
            [
                'message' => $ex->getMessage(),
                'url' => $url,
                'trace' => $ex->getTraceAsString(),
                'secrets' => implode(', ', array_keys($passwordTypeData)),
            ]
        );

        throw $ex;
    }

    $logger->info(sprintf('Write successfully secrets in vault: %s', implode(', ', array_keys($passwordTypeData))));
}

/**
 * Update host table with secrets path on vault.
 *
 * @param CentreonDB $pearDB
 * @param string $hostPath
 * @param int $hostId
 *
 * @throws Throwable
 */
function updateHostTableWithVaultPath(CentreonDB $pearDB, string $hostPath, int $hostId): void
{
    $statementUpdateHost = $pearDB->prepare(
        <<<'SQL'
                UPDATE `host` SET host_snmp_community = :path WHERE host_id = :hostId
            SQL
    );
    $statementUpdateHost->bindValue(':path', $hostPath, PDO::PARAM_STR);
    $statementUpdateHost->bindValue(':hostId', $hostId);
    $statementUpdateHost->execute();
}

/**
 * Duplicate Host Secrets in Vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param Logger $logger
 * @param UUIDGeneratorInterface $uuidGenerator
 * @param CentreonRestHttp $httpClient
 * @param string $snmpCommunity
 * @param array $macroPasswords
 * @param int $duplicatedHostId
 * @param string $clientToken
 * @param int $newHostId
 *
 * @throws Throwable
 */
function duplicateHostSecretsInVault(
    VaultConfiguration $vaultConfiguration,
    Logger $logger,
    UUIDGeneratorInterface $uuidGenerator,
    CentreonRestHttp $httpClient,
    ?string $snmpCommunity,
    array $macroPasswords,
    int $duplicatedHostId,
    string $clientToken,
    int $newHostId
): void {
    global $pearDB;

    // Get UUID form Host SNMP Community Path if it is set
    if (! empty($snmpCommunity)) {
        $pathPart = explode('/', $snmpCommunity);
        $uuid = end($pathPart);

        // Get UUID from macro password if they match the vault path regex
    } elseif (! empty($macroPasswords)) {
        foreach ($macroPasswords as $macroValue) {
            if (preg_match('/' . VAULT_PATH_REGEX . '/',$macroValue)) {
                $pathPart = explode('/', $macroValue);
                $uuid = end($pathPart);
                break;
            }
        }
    }
    $hostSecretsFromVault = [];
    if (isset($uuid)) {
        $hostSecretsFromVault = getHostSecretsFromVault(
            $vaultConfiguration,
            $duplicatedHostId, // The duplicated host id
            $uuid,
            $clientToken,
            $logger,
            $httpClient
        );
    }

    if (! empty($hostSecretsFromVault)) {
        $newUuid = $uuidGenerator->generateV4();
        writeHostSecretsInVault(
            $vaultConfiguration,
            $newUuid,
            $clientToken,
            $hostSecretsFromVault,
            $logger,
            $httpClient
        );
        $hostPath = 'secret::' . $vaultConfiguration->getName() . '::'
            . $vaultConfiguration->getRootPath()
            . '/data/monitoring/hosts/' . $newUuid;
        // Store vault path for SNMP Community
        if (array_key_exists(SNMP_COMMUNITY_MACRO_NAME, $hostSecretsFromVault)){
            updateHostTableWithVaultPath($pearDB, $hostPath, $newHostId);
        }

        // Store vault path for macros
        if (! empty($macroPasswords)) {
            updateOnDemandMacroHostTableWithVaultPath(
                $pearDB,
                array_keys($macroPasswords),
                $hostPath
            );
        }
    }
}

/**
 * Update on_demand_macro_host table with secrets path on vault.
 *
 * @param CentreonDB $pearDB
 * @param non-empty-array<int> $macroIds
 * @param string $hostPath
 *
 * @throws Throwable
 */
function updateOnDemandMacroHostTableWithVaultPath(CentreonDB $pearDB, array $macroIds, string $hostPath): void
{
    $bindMacroIds = [];
    foreach ($macroIds as $macroId) {
        $bindMacroIds[':macro_' . $macroId] = $macroId;
    }
    $bindMacroString = implode(', ', array_keys($bindMacroIds));
    $statementUpdateMacros = $pearDB->prepare(
        <<<SQL
                UPDATE `on_demand_macro_host` SET host_macro_value = :path WHERE host_macro_id IN ({$bindMacroString})
            SQL
    );
    $statementUpdateMacros->bindValue(':path', $hostPath, PDO::PARAM_STR);
    foreach ($bindMacroIds as $bindToken => $bindValue) {
        $statementUpdateMacros->bindValue($bindToken, $bindValue, PDO::PARAM_INT);
    }
    $statementUpdateMacros->execute();
}

/**
 * Delete resources from the vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param Logger $logger
 * @param int[] $hostIds
 * @param int[] $serviceIds
 *
 * @throws Throwable
 */
function deleteResourceSecretsInVault(
    VaultConfiguration $vaultConfiguration,
    Logger $logger,
    array $hostIds,
    array $serviceIds
): void {
    $vaultPath = 'secret::' . $vaultConfiguration->getName() . '::';
    $httpClient = new CentreonRestHttp();
    $clientToken = authenticateToVault($vaultConfiguration, $logger, $httpClient);

    if (! empty($hostIds)) {
        $uuids = retrieveMultipleHostUuidsFromDatabase($hostIds, $vaultPath);
        if (array_key_exists('host', $uuids)) {
            foreach ($uuids['host'] as $uuid) {
                deleteHostFromVault($vaultConfiguration, $uuid, $clientToken, $logger, $httpClient);
            }
        }

        // Delete entry in vault for children services
        if (array_key_exists('service', $uuids)) {
            foreach ($uuids['service'] as $uuid) {
                deleteServiceFromVault($vaultConfiguration, $uuid, $clientToken, $logger, $httpClient);
            }
        }
    }

    if (! empty($serviceIds)) {
        $uuids = retrieveMultipleServiceUuidsFromDatabase($serviceIds, $vaultPath);
        foreach ($uuids as $uuid) {
            deleteServiceFromVault($vaultConfiguration, $uuid, $clientToken, $logger, $httpClient);
        }
    }
}

/**
 * Found Host and Service linked to Host Secrets UUIDs.
 *
 * @param non-empty-array<int> $hostIds
 * @param string $vaultPath
 *
 * @return array<string,string[]>
 */
function retrieveMultipleHostUuidsFromDatabase(array $hostIds, string $vaultPath): array
{
    global $pearDB;

    $bindParams = [];
    foreach ($hostIds as $hostId) {
        $bindParams[':hostId_' . $hostId] = $hostId;
    }

    $bindString = implode(', ', array_keys($bindParams));
    $statement = $pearDB->prepare(
        <<<SQL
                SELECT DISTINCT h.host_snmp_community, odmh.host_macro_value, odms.svc_macro_value
                FROM host as h
                    LEFT JOIN on_demand_macro_host as odmh
                        ON h.host_id = odmh.host_host_id
                    LEFT JOIN host_service_relation as hsr
                        ON h.host_id = hsr.host_host_id
                    LEFT JOIN on_demand_macro_service as odms
                        ON odms.svc_svc_id = hsr.service_service_id
                    WHERE (h.host_snmp_community LIKE :vaultPath
                        OR odmh.host_macro_value LIKE :vaultPath
                        OR odms.svc_macro_value LIKE :vaultPath)
                        AND h.host_id IN ( {$bindString} );
            SQL
    );
    foreach ($bindParams as $token => $hostId) {
        $statement->bindValue($token, $hostId, PDO::PARAM_INT);
    }
    $statement->bindValue(':vaultPath', $vaultPath . '%', PDO::PARAM_STR);
    $statement->execute();
    $uuids = [];
    while ($result = $statement->fetch(PDO::FETCH_ASSOC)) {
        if (preg_match('/^' . $vaultPath .'/', $result['host_snmp_community'])) {
            $vaultPathPart = explode('/', $result['host_snmp_community']);
        } elseif (preg_match('/^' . $vaultPath .'/', $result['host_macro_value'])) {
            $vaultPathPart = explode('/', $result['host_macro_value']);
        }
        if (isset($vaultPathPart)) {
            $uuids['host'][] = end($vaultPathPart);
        }

        // Add UUID of services linked to host
        if (preg_match('/^' . $vaultPath .'/', $result['svc_macro_value'])) {
            $vaultPathPart = explode('/', $result['svc_macro_value']);
            $uuids['service'][] = end($vaultPathPart);
        }
    }

    return $uuids;
}

/**
 * Delete host secrets data from vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param string $uuid
 * @param string $clientToken
 * @param Logger $logger
 * @param CentreonRestHttp $httpClient
 *
 * @throws Throwable
 */
function deleteHostFromVault(
    VaultConfiguration $vaultConfiguration,
    string $uuid,
    string $clientToken,
    Logger $logger,
    CentreonRestHttp $httpClient
): void {
    $url = $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort() . '/v1/'
        . $vaultConfiguration->getRootPath() . '/metadata/monitoring/hosts/' . $uuid;
    $url = sprintf('%s://%s', DEFAULT_SCHEME, $url);
    $logger->info(sprintf('Deleting Host: %s', $uuid));
    try {
        $httpClient->call($url, 'DELETE', null, ['X-Vault-Token: ' . $clientToken]);
    } catch (Exception $ex) {
        $logger->error(sprintf('Unable to delete Host: %s', $uuid));

        throw $ex;
    }
}

/**
 * Delete service secrets data from vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param string $uuid
 * @param string $clientToken
 * @param Logger $logger
 * @param CentreonRestHttp $httpClient
 *
 * @throws Throwable
 */
function deleteServiceFromVault(
    VaultConfiguration $vaultConfiguration,
    string $uuid,
    string $clientToken,
    Logger $logger,
    CentreonRestHttp $httpClient
): void {
    $url = $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort() . '/v1/'
        . $vaultConfiguration->getRootPath() . '/metadata/monitoring/services/' . $uuid;
    $url = sprintf('%s://%s', DEFAULT_SCHEME, $url);
    $logger->info(sprintf('Deleting Service: %s', $uuid));
    try {
        $httpClient->call($url, 'DELETE', null, ['X-Vault-Token: ' . $clientToken]);
    } catch (Exception $ex) {
        $logger->error(sprintf('Unable to delete Service: %s', $uuid));

        throw $ex;
    }
}

/**
 * Found Service Secrets UUIDs.
 *
 * @param non-empty-array<int> $serviceIds
 * @param string $vaultPath
 *
 * @return string[]
 */
function retrieveMultipleServiceUuidsFromDatabase(array $serviceIds, string $vaultPath): array
{
    global $pearDB;

    $bindParams = [];
    foreach ($serviceIds as $serviceId) {
        $bindParams[':serviceId_' . $serviceId] = $serviceId;
    }
    $bindString = implode(', ', array_keys($bindParams));
    $statement = $pearDB->prepare(
        <<<SQL
                SELECT DISTINCT odms.svc_macro_value
                FROM on_demand_macro_service as odms
                    WHERE odms.svc_macro_value LIKE :vaultPath
                        AND odms.svc_svc_id IN ( {$bindString} )
            SQL
    );
    foreach ($bindParams as $token => $serviceId) {
        $statement->bindValue($token, $serviceId, PDO::PARAM_INT);
    }
    $statement->bindValue(':vaultPath', $vaultPath . '%', PDO::PARAM_STR);
    $statement->execute();
    $uuids = [];
    while ($result = $statement->fetch(PDO::FETCH_ASSOC)) {
        $vaultPathPart = explode('/', $result['svc_macro_value']);
        $uuids[] = end($vaultPathPart);
    }

    return $uuids;
}

/**
 * Retrieve UUID of a host.
 *
 * @param CentreonDB $pearDB
 * @param int $hostId
 * @param string $vaultConfigurationName
 *
 * @throws Throwable
 *
 * @return string|null
 */
function retrieveHostUuidFromDatabase(CentreonDB $pearDB, int $hostId, string $vaultConfigurationName): ?string
{
    $vaultPath = 'secret::'. $vaultConfigurationName .'::';
    $statement = $pearDB->prepare(
        <<<'SQL'
                SELECT h.host_snmp_community, odm.host_macro_value
                    FROM host as h
                         LEFT JOIN on_demand_macro_host AS odm
                        ON odm.host_host_id = h.host_id
                WHERE h.host_id= :hostId
                    AND (odm.host_macro_value LIKE :vaultPath
                    OR h.host_snmp_community LIKE :vaultPath)
            SQL
    );
    $statement->bindValue(':hostId', $hostId, PDO::PARAM_STR);
    $statement->bindValue(':vaultPath', $vaultPath . '%', PDO::PARAM_STR);
    $statement->execute();
    if (($result = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
        foreach ($result as $columnValue) {
            if (preg_match('/' . VAULT_PATH_REGEX . '/', $columnValue)) {
                $pathPart = explode('/', $columnValue);

                return end($pathPart);
            }
        }
    }

    return null;
}

/**
 * Update Host Secrets in Vault while Massive changing.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param Logger $logger
 * @param UUIDGeneratorInterface $uuidGenerator
 * @param string|null $uuid
 * @param int $hostId
 * @param array<int,array<string,string>> $macros
 * @param ?string $snmpCommunity
 *
 * @throws Throwable $ex
 */
function updateHostSecretsInVaultFromMC(
    VaultConfiguration $vaultConfiguration,
    Logger $logger,
    UUIDGeneratorInterface $uuidGenerator,
    ?string $uuid,
    int $hostId,
    array $macros,
    ?string $snmpCommunity
): void {
    global $pearDB;

    $httpClient = new CentreonRestHttp();
    $clientToken = authenticateToVault($vaultConfiguration, $logger, $httpClient);
    $hostSecretsFromVault = [];
    if ($uuid !== null) {
        $hostSecretsFromVault = getHostSecretsFromVault(
            $vaultConfiguration,
            $hostId,
            $uuid,
            $clientToken,
            $logger,
            $httpClient
        );
    }
    $macroPasswordIds = getIdOfUpdatedPasswordMacros($macros);
    $updateHostPayload = prepareHostUpdateMCPayload(
        $snmpCommunity,
        $macros,
        $hostSecretsFromVault
    );

    if (! empty($updateHostPayload)) {
        $uuid ??= $uuidGenerator->generateV4();
        writeHostSecretsInVault(
            $vaultConfiguration,
            $uuid,
            $clientToken,
            $updateHostPayload,
            $logger,
            $httpClient
        );

        $hostPath = 'secret::' . $vaultConfiguration->getName() . '::'
            . $vaultConfiguration->getRootPath()
            . '/data/monitoring/hosts/' . $uuid;

        // Store vault path for SNMP Community
        if (array_key_exists(SNMP_COMMUNITY_MACRO_NAME, $updateHostPayload)) {
            updateHostTableWithVaultPath($pearDB, $hostPath, $hostId);
        }

        // Store vault path for macros
        if (! empty($macroPasswordIds)) {
            updateOnDemandMacroHostTableWithVaultPath($pearDB, $macroPasswordIds, $hostPath);
        }
    }
}

/**
 * Store all the ids of password macros that have been updated.
 *
 * @param array<int,array<string,string>> $macros
 *
 * @return int[]
 */
function getIdOfUpdatedPasswordMacros(array $macros): array
{
    $macroPasswordIds = [];
    foreach ($macros as $macroId => $macroInfos) {
        if (
            $macroInfos['macroPassword'] === '1'
            && ! preg_match('/' . VAULT_PATH_REGEX . '/', $macroInfos['macroValue'])
        ) {
            $macroPasswordIds[] = $macroId;
        }
    }

    return $macroPasswordIds;
}

/**
 * Add new macros and SNMP Community to the write in vault payload.
 *
 * @param string|null $hostSNMPCommunity
 * @param array<int,array{
 *      macroName: string,
 *      macroValue: string,
 *      macroPassword: '0'|'1',
 *      originalName?: string
 * }> $macros
 * @param array<string,string> $secretsFromVault
 *
 * @return array<string,string>
 */
function prepareHostUpdateMCPayload(?string $hostSNMPCommunity, array $macros, array $secretsFromVault): array
{
    foreach ($macros as $macroInfos) {
        if (
            $macroInfos['macroPassword'] === '1'
            && ! preg_match('/' . VAULT_PATH_REGEX . '/', $macroInfos['macroValue'])
        ) {
            $secretsFromVault[$macroInfos['macroName']] = $macroInfos['macroValue'];
        }
    }

    // Add SNMP Community if a new value has been set
    if ($hostSNMPCommunity !== null && ! preg_match('/' . VAULT_PATH_REGEX . '/', $hostSNMPCommunity)) {
        $secretsFromVault[SNMP_COMMUNITY_MACRO_NAME] = $hostSNMPCommunity;
    }

    return $secretsFromVault;
}

/**
 * Update Host Secrets in Vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param Logger $logger
 * @param UUIDGeneratorInterface $uuidGenerator
 * @param string|null $uuid
 * @param int $hostId
 * @param array $macros
 * @param string|null $snmpCommunity
 *
 * @throws Throwable
 */
function updateHostSecretsInVault(
    VaultConfiguration $vaultConfiguration,
    Logger $logger,
    UUIDGeneratorInterface $uuidGenerator,
    ?string $uuid,
    int $hostId,
    array $macros,
    ?string $snmpCommunity
): void {
    global $pearDB;
    $httpClient = new CentreonRestHttp();
    $clientToken = authenticateToVault($vaultConfiguration, $logger, $httpClient);
    $hostSecretsFromVault = [];
    if ($uuid !== null) {
        $hostSecretsFromVault = getHostSecretsFromVault(
            $vaultConfiguration,
            (int) $hostId,
            $uuid,
            $clientToken,
            $logger,
            $httpClient
        );
    }
    $macroPasswordIds = getIdOfUpdatedPasswordMacros($macros);
    $updateHostPayload = prepareHostUpdatePayload(
        $snmpCommunity,
        $macros,
        $hostSecretsFromVault
    );

    // If no more fields are password types, we delete the host from the vault has it will not be read.
    if (empty($updateHostPayload) && $uuid !== null) {
        deleteHostFromVault($vaultConfiguration, $uuid, $clientToken, $logger, $httpClient);
    } else {
        $uuid ??= $uuidGenerator->generateV4();
        writeHostSecretsInVault(
            $vaultConfiguration,
            $uuid,
            $clientToken,
            $updateHostPayload,
            $logger,
            $httpClient
        );

        $hostPath = 'secret::' . $vaultConfiguration->getName() . '::'
            . $vaultConfiguration->getRootPath()
            . '/data/monitoring/hosts/' . $uuid;
        // Store vault path for SNMP Community
        if (array_key_exists(SNMP_COMMUNITY_MACRO_NAME, $updateHostPayload)){
            updateHostTableWithVaultPath($pearDB, $hostPath, $hostId);
        }

        // Store vault path for macros
        if (! empty($macroPasswordIds)) {
            updateOnDemandMacroHostTableWithVaultPath($pearDB, $macroPasswordIds, $hostPath);
        }
    }
}

/**
 * Prepare the write-in vault payload while updating a host.
 *
 * This method will compare the secrets already stored in the vault with the secrets submitted by the form
 * And update their value or delete them if they are no more setted.
 *
 * @param string|null $hostSNMPCommunity
 * @param array<int,array{
 *  macroName: string,
 *  macroValue: string,
 *  macroPassword: '0'|'1',
 *  originalName?: string
 * }> $macros
 * @param array<string,string> $secretsFromVault
 *
 * @return array<string,string>
 */
function prepareHostUpdatePayload(?string $hostSNMPCommunity, array $macros, array $secretsFromVault): array
{
    // Unset existing macros on vault if they no more exist while submitting the form
    foreach (array_keys($secretsFromVault) as $secretKey) {
        if ($secretKey !== SNMP_COMMUNITY_MACRO_NAME) {
            $macroName = [];
            foreach ($macros as $macroInfos) {
                $macroName[] = $macroInfos['macroName'];
                if (array_key_exists('originalName', $macroInfos) && $secretKey === $macroInfos['originalName']) {
                    $secretsFromVault[$macroInfos['macroName']] = $secretsFromVault[$secretKey];
                }
            }
            if (! in_array($secretKey, $macroName, true)) {
                unset($secretsFromVault[$secretKey]);
            }
        }
    }

    // Add macros to payload if they are password type and their values have changed
    foreach ($macros as $macroInfos) {
        if (
            $macroInfos['macroPassword'] === '1'
            && ! preg_match('/' . VAULT_PATH_REGEX . '/', $macroInfos['macroValue'])
        ) {
            $secretsFromVault[$macroInfos['macroName']] = $macroInfos['macroValue'];
        }
    }

    // Unset existing SNMP Community if it no more exists while submitting the form
    if (
        array_key_exists(SNMP_COMMUNITY_MACRO_NAME, $secretsFromVault)
        && $hostSNMPCommunity === null
    ) {
        unset($secretsFromVault[SNMP_COMMUNITY_MACRO_NAME]);
    }

    // Add SNMP Community if a new value has been set
    if ($hostSNMPCommunity !== null && ! preg_match('/' . VAULT_PATH_REGEX . '/', $hostSNMPCommunity)) {
        $secretsFromVault[SNMP_COMMUNITY_MACRO_NAME] = $hostSNMPCommunity;
    }

    return $secretsFromVault;
}

/**
 * Insert Host secrets In Vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param UUIDGeneratorInterface $uuidGenerator
 * @param Logger $logger
 * @param string|null $snmpCommunity
 * @param array<int,array{
 *   macroName: string,
 *   macroValue: string,
 *   macroPassword: '0'|'1',
 *   originalName?: string
 *  }> $macros $macros
 * @param int $hostId
 *
 * @throws Throwable
 */
function insertHostSecretsInVault(
    VaultConfiguration $vaultConfiguration,
    UUIDGeneratorInterface $uuidGenerator,
    Logger $logger,
    ?string $snmpCommunity,
    array $macros,
    int $hostId
): void {
    global $pearDB;
    // store SNMP Community and password macros
    $passwordTypeData = [];
    if ($snmpCommunity !== null) {
        $passwordTypeData[SNMP_COMMUNITY_MACRO_NAME] = $snmpCommunity;
    }
    $macroPasswordIds = [];
    foreach ($macros as $macroId => $macroInfos) {
        if ($macroInfos['macroPassword'] === '1') {
            $passwordTypeData[$macroInfos['macroName']] = $macroInfos['macroValue'];
            $macroPasswordIds[] = $macroId;
        }
    }

    // If there is some password values, write them in the vault
    if (! empty($passwordTypeData)) {
        $httpClient = new CentreonRestHttp();
        $clientToken = authenticateToVault($vaultConfiguration, $logger, $httpClient);
        $uuid = $uuidGenerator->generateV4();
        writeHostSecretsInVault(
            $vaultConfiguration,
            $uuid,
            $clientToken,
            $passwordTypeData,
            $logger,
            $httpClient
        );
        $hostPath = 'secret::' . $vaultConfiguration->getName() . '::' . $vaultConfiguration->getRootPath()
            . '/data/monitoring/hosts/' . $uuid;

        // Store vault path for SNMP Community
        if (array_key_exists(SNMP_COMMUNITY_MACRO_NAME, $passwordTypeData)){
            updateHostTableWithVaultPath($pearDB, $hostPath, $hostId);
        }

        // Store vault path for macros
        if (! empty($macroPasswordIds)) {
            updateOnDemandMacroHostTableWithVaultPath($pearDB, $macroPasswordIds, $hostPath);
        }
    }
}

/**
 * Duplicate Service Secrets in Vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param Logger $logger
 * @param CentreonRestHttp $httpClient
 * @param UUIDGeneratorInterface $uuidGenerator
 * @param int $duplicatedServiceId
 * @param array<int, string> $macroPasswords
 * @param string $clientToken
 *
 * @throws Throwable
 */
function duplicateServiceSecretsInVault(
    VaultConfiguration $vaultConfiguration,
    Logger $logger,
    CentreonRestHttp $httpClient,
    UUIDGeneratorInterface $uuidGenerator,
    int $duplicatedServiceId,
    array $macroPasswords,
    string $clientToken,
): void {
    global $pearDB;

    $uuid = null;
    foreach ($macroPasswords as $macroValue) {
        if (preg_match('/' . VAULT_PATH_REGEX . '/', $macroValue)) {
            $pathPart = explode('/', $macroValue);
            $uuid = end($pathPart);
            break;
        }
    }

    $serviceSecretsFromVault = [];
    if (isset($uuid)) {
        $serviceSecretsFromVault = getServiceSecretsFromVault(
            $vaultConfiguration,
            $duplicatedServiceId, // The duplicated service id
            $uuid,
            $clientToken,
            $logger,
            $httpClient
        );
    }

    if (! empty($serviceSecretsFromVault)) {
        $newUuid = $uuidGenerator->generateV4();
        writeServiceSecretsInVault(
            $vaultConfiguration,
            $newUuid,
            $clientToken,
            $serviceSecretsFromVault,
            $logger,
            $httpClient
        );
        $servicePath = 'secret::' . $vaultConfiguration->getName() . '::'
            . $vaultConfiguration->getRootPath()
            . '/data/monitoring/services/' . $newUuid;

        // Store vault path for macros
        if (! empty($macroPasswords)) {
            updateOnDemandMacroServiceTableWithVaultPath(
                $pearDB,
                array_keys($macroPasswords),
                $servicePath
            );
        }
    }
}

/**
 * Get service secrets data from vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param int $serviceId
 * @param string $uuid
 * @param string $clientToken
 * @param Logger $logger
 * @param CentreonRestHttp $httpClient
 *
 * @throws Throwable
 *
 * @return array<string, mixed>
 */
function getServiceSecretsFromVault(
    VaultConfiguration $vaultConfiguration,
    int $serviceId,
    string $uuid,
    string $clientToken,
    Logger $logger,
    CentreonRestHttp $httpClient
): array {
    $url = $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort() . '/v1/'
        . $vaultConfiguration->getRootPath() . '/data/monitoring/services/' . $uuid;
    $url = sprintf('%s://%s', DEFAULT_SCHEME, $url);
    $logger->info(sprintf('Search Service %d secrets at: %s', $serviceId, $url));

    try {
        $content = $httpClient->call($url, 'GET', null, ['X-Vault-Token: ' . $clientToken]);
        if (array_key_exists('data', $content) && array_key_exists('data', $content['data'])) {
            return $content['data']['data'];
        }

        return [];
    } catch (RestNotFoundException $ex) {
        $logger->info(sprintf('Service %d not found in vault', $serviceId));

        return [];
    } catch (Exception $ex) {
        $logger->error(sprintf('Unable to get secrets for Service : %d', $serviceId));

        throw $ex;
    }
}

/**
 * Write service secrets data in vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param int $serviceId
 * @param string $clientToken
 * @param Logger $logger
 * @param CentreonRestHttp $httpClient
 * @param string $uuid
 * @param array $macros
 *
 * @throws Exception
 */
function writeServiceSecretsInVault(
    VaultConfiguration $vaultConfiguration,
    string $uuid,
    string $clientToken,
    array $macros,
    Logger $logger,
    CentreonRestHttp $httpClient
): void {
    try {
        $url = $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort()
            . '/v1/' . $vaultConfiguration->getRootPath()
            . '/data/monitoring/services/' . $uuid;
        $url = sprintf('%s://%s', DEFAULT_SCHEME, $url);
        $logger->info(
            'Writing Service Secrets at : ' . $url,
            ['secrets' => implode(', ', array_keys($macros))]
        );
        $httpClient->call($url, 'POST', ['data' => $macros], ['X-Vault-Token: ' . $clientToken]);
    } catch (Exception $ex) {
        $logger->error(
            'Unable to write Service secrets into vault',
            [
                'message' => $ex->getMessage(),
                'trace' => $ex->getTraceAsString(),
                'secrets' => implode(', ', array_keys($macros)),
            ]
        );

        throw $ex;
    }

    $logger->info(sprintf('Write successfully secrets in vault: %s', implode(', ', array_keys($macros))));
}

/**
 * Update on_demand_macro_service table with secrets path on vault.
 *
 * @param CentreonDB $pearDB
 * @param non-empty-array<int> $macroIds
 * @param string $servicePath
 *
 * @throws Throwable
 */
function updateOnDemandMacroServiceTableWithVaultPath(CentreonDB $pearDB, array $macroIds, string $servicePath): void
{
    $bindMacroIds = [];
    foreach ($macroIds as $macroId) {
        $bindMacroIds[':macro_' . $macroId] = $macroId;
    }
    $bindMacroString = implode(', ', array_keys($bindMacroIds));
    $statementUpdateMacros = $pearDB->prepare(
        <<<SQL
                UPDATE `on_demand_macro_service` SET svc_macro_value = :path WHERE svc_macro_id IN ({$bindMacroString})
            SQL
    );
    $statementUpdateMacros->bindValue(':path', $servicePath, PDO::PARAM_STR);
    foreach ($bindMacroIds as $bindToken => $bindValue) {
        $statementUpdateMacros->bindValue($bindToken, $bindValue, PDO::PARAM_INT);
    }
    $statementUpdateMacros->execute();
}

/**
 * Retrieve UUID of a service.
 *
 * @param CentreonDB $pearDB
 * @param int $serviceId
 * @param string $vaultConfigurationName
 *
 * @throws Throwable
 *
 * @return string|null
 */
function retrieveServiceSecretUuidFromDatabase(
    CentreonDB $pearDB,
    int $serviceId,
    string $vaultConfigurationName
): ?string {
    $vaultPath = 'secret::'. $vaultConfigurationName .'::';
    $statement = $pearDB->prepare(
        <<<'SQL'
                SELECT ods.svc_macro_value
                    FROM on_demand_macro_service AS ods
                    WHERE ods.svc_svc_id= :serviceId
                    AND ods.svc_macro_value LIKE :vaultPath
            SQL
    );
    $statement->bindValue(':serviceId', $serviceId, PDO::PARAM_STR);
    $statement->bindValue(':vaultPath', $vaultPath . '%', PDO::PARAM_STR);
    $statement->execute();
    if (($result = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
        foreach ($result as $columnValue) {
            if (preg_match('/' . VAULT_PATH_REGEX . '/', $columnValue)) {
                $pathPart = explode('/', $columnValue);

                return end($pathPart);
            }
        }
    }

    return null;
}

/**
 * Update Service Secrets in Vault after Massive changing.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param Logger $logger
 * @param UUIDGeneratorInterface $uuidGenerator
 * @param string|null $uuid
 * @param int $serviceId
 * @param array<int,array{
 *       macroName: string,
 *       macroValue: string,
 *       macroPassword: '0'|'1',
 *       originalName?: string
 *  }> $macros
 *
 * @throws Throwable
 */
function updateServiceSecretsInVaultFromMC(
    VaultConfiguration $vaultConfiguration,
    Logger $logger,
    UUIDGeneratorInterface $uuidGenerator,
    ?string $uuid,
    int $serviceId,
    array $macros
): void {
    global $pearDB;

    $httpClient = new CentreonRestHttp();
    $clientToken = authenticateToVault($vaultConfiguration, $logger, $httpClient);
    $serviceSecretsFromVault = [];
    if ($uuid !== null) {
        $serviceSecretsFromVault = getServiceSecretsFromVault(
            $vaultConfiguration,
            $serviceId,
            $uuid,
            $clientToken,
            $logger,
            $httpClient
        );
    }
    $macroPasswordIds = getIdOfUpdatedPasswordMacros($macros);
    $updateServicePayload = prepareServiceUpdateMCPayload(
        $macros,
        $serviceSecretsFromVault
    );
    if (! empty($updateServicePayload)) {
        $uuid ??= $uuidGenerator->generateV4();
        writeServiceSecretsInVault(
            $vaultConfiguration,
            $uuid,
            $clientToken,
            $updateServicePayload,
            $logger,
            $httpClient
        );

        $servicePath = 'secret::' . $vaultConfiguration->getName() . '::'
            . $vaultConfiguration->getRootPath()
            . '/data/monitoring/services/' . $uuid;

        // Store vault path for macros
        if (! empty($macroPasswordIds)) {
            updateOnDemandMacroServiceTableWithVaultPath($pearDB, $macroPasswordIds, $servicePath);
        }
    }
}

/**
 * Add new macros to the write in vault payload.
 *
 * @param array<int,array{
 *      macroName: string,
 *      macroValue: string,
 *      macroPassword: '0'|'1',
 *      originalName?: string
 * }> $macros
 * @param array<string,string> $serviceSecretsFromVault
 *
 * @return array<string,string>
 */
function prepareServiceUpdateMCPayload(array $macros, array $serviceSecretsFromVault)
{
    foreach ($macros as $macroInfos) {
        if (
            $macroInfos['macroPassword'] === '1'
            && ! preg_match('/' . VAULT_PATH_REGEX . '/', $macroInfos['macroValue'])
        ) {
            $serviceSecretsFromVault[$macroInfos['macroName']] = $macroInfos['macroValue'];
        }
    }

    return $serviceSecretsFromVault;
}

/**
 * Update Service Secrest in Vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param Logger $logger
 * @param UUIDGeneratorInterface $uuidGenerator
 * @param int $serviceId
 * @param array $macros
 * @param string|null $uuid
 *
 * @throws Throwable
 */
function updateServiceSecretsInVault(
    VaultConfiguration $vaultConfiguration,
    Logger $logger,
    UUIDGeneratorInterface $uuidGenerator,
    int $serviceId,
    array $macros,
    ?string $uuid,
): void {
    global $pearDB;

    $httpClient = new CentreonRestHttp();
    $clientToken = authenticateToVault($vaultConfiguration, $logger, $httpClient);
    $serviceSecretsFromVault = [];
    if ($uuid !== null) {
        $serviceSecretsFromVault = getServiceSecretsFromVault(
            $vaultConfiguration,
            $serviceId,
            $uuid,
            $clientToken,
            $logger,
            $httpClient
        );
    }
    $macroPasswordIds = getIdOfUpdatedPasswordMacros($macros);
    $updateServicePayload = prepareServiceUpdatePayload(
        $macros,
        $serviceSecretsFromVault
    );

    // If no more fields are password types, we delete the service from the vault has it will not be read.
    if (empty($updateServicePayload) && $uuid !== null) {
        deleteServiceFromVault($vaultConfiguration, $uuid, $clientToken, $logger, $httpClient);
    } else {
        $uuid ??= $uuidGenerator->generateV4();
        writeServiceSecretsInVault(
            $vaultConfiguration,
            $uuid,
            $clientToken,
            $updateServicePayload,
            $logger,
            $httpClient
        );

        $servicePath = 'secret::' . $vaultConfiguration->getName() . '::'
            . $vaultConfiguration->getRootPath() . '/data/monitoring/services/' . $uuid;

        // Store vault path for macros
        if (! empty($macroPasswordIds)) {
            updateOnDemandMacroServiceTableWithVaultPath($pearDB, $macroPasswordIds, $servicePath);
        }
    }
}

/**
 * Prepare the write-in vault payload while updating a service.
 *
 * This method will compare the secrets already stored in the vault with the secrets submitted by the form
 * And update their value or delete them if they are no more setted.
 *
 * @param array<int,array{
 *  macroName: string,
 *  macroValue: string,
 *  macroPassword: '0'|'1',
 *  originalName?: string
 * }> $macros
 * @param array<string,string> $serviceSecretsFromVault
 *
 * @return array<string,string>
 */
function prepareServiceUpdatePayload(array $macros, array $serviceSecretsFromVault): array
{
    // Unset existing macros on vault if they no more exist while submitting the form
    foreach (array_keys($serviceSecretsFromVault) as $secretKey) {
        $macroName = [];
        foreach ($macros as $macroInfos) {
            $macroName[] = $macroInfos['macroName'];
            if (array_key_exists('originalName', $macroInfos) && $secretKey === $macroInfos['originalName']) {
                $serviceSecretsFromVault[$macroInfos['macroName']] = $serviceSecretsFromVault[$secretKey];
            }
        }
        if (! in_array($secretKey, $macroName, true)) {
            unset($serviceSecretsFromVault[$secretKey]);
        }
    }

    // Add macros to payload if they are password type and their values have changed
    foreach ($macros as $macroInfos) {
        if (
            $macroInfos['macroPassword'] === '1'
            && ! preg_match('/' . VAULT_PATH_REGEX . '/', $macroInfos['macroValue'])
        ) {
            $serviceSecretsFromVault[$macroInfos['macroName']] = $macroInfos['macroValue'];
        }
    }

    return $serviceSecretsFromVault;
}

/**
 * insert Service Secrets in Vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param UUIDGeneratorInterface $uuidGenerator
 * @param Logger $logger
 * @param array<int,array{
 *       macroName: string,
 *       macroValue: string,
 *       macroPassword: '0'|'1',
 *       originalName?: string
 *  }> $macros
 *
 * @throws Throwable
 */
function insertServiceSecretsInVault(
    VaultConfiguration $vaultConfiguration,
    UUIDGeneratorInterface $uuidGenerator,
    Logger $logger,
    array $macros
): void {
    global $pearDB;

    $macroPasswordIds = [];
    $passwordMacros = [];

    foreach ($macros as $macroId => $macroInfos) {
        if ($macroInfos['macroPassword'] === '1') {
            $passwordMacros[$macroInfos['macroName']] = $macroInfos['macroValue'];
            $macroPasswordIds[] = $macroId;
        }
    }

    if (! empty($passwordMacros)) {
        $httpClient = new CentreonRestHttp();
        $clientToken = authenticateToVault($vaultConfiguration, $logger, $httpClient);
        $uuid = $uuidGenerator->generateV4();
        writeServiceSecretsInVault(
            $vaultConfiguration,
            $uuid,
            $clientToken,
            $passwordMacros,
            $logger,
            $httpClient
        );
        $servicePath = 'secret::' . $vaultConfiguration->getName() . '::' . $vaultConfiguration->getRootPath()
            . '/data/monitoring/services/' . $uuid;
        updateOnDemandMacroServiceTableWithVaultPath($pearDB, $macroPasswordIds, $servicePath);
    }
}

// POLLER MACROS (Configuration > Pollers > Resources)

/**
 * Retrieve UUID of a poller macro.
 *
 * @param CentreonDB $pearDB
 * @param string $vaultConfigurationName
 *
 * @throws Throwable
 *
 * @return string|null
 */
function retrievePollerMacroUuidFromDatabase(
    CentreonDB $pearDB,
    string $vaultConfigurationName
): ?string {
    $vaultPath = 'secret::'. $vaultConfigurationName .'::';
    $statement = $pearDB->prepare(
        <<<'SQL'
                SELECT resource_line FROM cfg_resource
                WHERE resource_line LIKE :vaultPath
            SQL
    );
    $statement->bindValue(':vaultPath', $vaultPath . '%', PDO::PARAM_STR);
    $statement->execute();
    if (($result = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
        /** @var array{resource_line:string} $result */
        if (preg_match('/' . VAULT_PATH_REGEX . '/', $result['resource_line'])) {
            $pathPart = explode('/', $result['resource_line']);

            return end($pathPart);
        }
    }

    return null;
}

/**
 * Insert poller macros Secrets in Vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param UUIDGeneratorInterface $uuidGenerator
 * @param Logger $logger
 * @param string $key
 * @param string $value
 * @param string $uuid
 *
 * @throws Exception
 *
 * @return string|null
 */
function upsertPollerMacroSecretInVault(
    VaultConfiguration $vaultConfiguration,
    UUIDGeneratorInterface $uuidGenerator,
    Logger $logger,
    string $key,
    string $value,
    ?string $uuid = null,
): string|null {
    if (! empty($value)) {
        $httpClient = new CentreonRestHttp();
        $clientToken = authenticateToVault($vaultConfiguration, $logger, $httpClient);

        $data = [];
        if ($uuid !== null) {
            $data = readPollerMacroSecretsInVault($vaultConfiguration, $logger, $uuid, $httpClient, $clientToken);
        } else {
            $uuid = $uuidGenerator->generateV4();
        }

        $data[$key] = $value;

        writePollerMacroSecretsInVault(
            $vaultConfiguration,
            $clientToken,
            $data,
            $logger,
            $httpClient,
            $uuid,
        );

        return 'secret::' . $vaultConfiguration->getName() . '::' . $vaultConfiguration->getRootPath()
            . '/data/monitoring/pollerMacros/' . $uuid;
    }

    return null;
}

/**
 * delete poller macros Secrets in Vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param Logger $logger
 * @param string $uuid
 * @param string $key
 *
 * @throws Exception
 */
function deletePollerMacroSecretInVault(
    VaultConfiguration $vaultConfiguration,
    Logger $logger,
    string $uuid,
    string $key,
): void {
    $httpClient = new CentreonRestHttp();
    $clientToken = authenticateToVault($vaultConfiguration, $logger, $httpClient);

    $data = readPollerMacroSecretsInVault($vaultConfiguration, $logger, $uuid, $httpClient, $clientToken);

    unset($data[str_replace('$', '', $key)]);

    if ($data !== []) {
        writePollerMacroSecretsInVault(
            $vaultConfiguration,
            $clientToken,
            $data,
            $logger,
            $httpClient,
            $uuid,
        );
    } else {
        deletePollerMacroSecretsFromVault(
            $vaultConfiguration,
            $logger,
            $uuid,
            $httpClient,
            $clientToken,
        );
    }
}

/**
 * Write poller macro secrets data in vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param string $clientToken
 * @param array<string,string> $macros
 * @param Logger $logger
 * @param CentreonRestHttp $httpClient
 * @param string $uuid
 *
 * @throws Exception
 */
function writePollerMacroSecretsInVault(
    VaultConfiguration $vaultConfiguration,
    string $clientToken,
    array $macros,
    Logger $logger,
    CentreonRestHttp $httpClient,
    string $uuid
): void {
    try {
        $url = $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort()
            . '/v1/' . $vaultConfiguration->getRootPath()
            . '/data/monitoring/pollerMacros/' . $uuid;
        $url = sprintf('%s://%s', DEFAULT_SCHEME, $url);
        $logger->info(
            'Writing Poller Macro Secrets at : ' . $url,
            ['secrets' => implode(', ', array_keys($macros))]
        );
        $httpClient->call($url, 'POST', ['data' => $macros], ['X-Vault-Token: ' . $clientToken]);
    } catch (Exception $ex) {
        $logger->error(
            'Unable to write Poller Macro secrets into vault',
            [
                'message' => $ex->getMessage(),
                'trace' => $ex->getTraceAsString(),
                'secrets' => implode(', ', array_keys($macros)),
            ]
        );

        throw $ex;
    }

    $logger->info(sprintf('Write successfully secrets in vault: %s', implode(', ', array_keys($macros))));
}

/**
 * Get poller macro secrets data from vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param Logger $logger
 * @param string $uuid
 * @param null|CentreonRestHttp $httpClient
 * @param null|string $clientToken
 *
 * @throws Throwable
 *
 * @return array<string, string>
 */
function readPollerMacroSecretsInVault(
    VaultConfiguration $vaultConfiguration,
    Logger $logger,
    string $uuid,
    ?CentreonRestHttp $httpClient = null,
    ?string $clientToken = null,
): array {
    $httpClient ??= new CentreonRestHttp();
    $clientToken ??= authenticateToVault($vaultConfiguration, $logger, $httpClient);

    $url = $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort()
        . '/v1/' . $vaultConfiguration->getRootPath() . '/data/monitoring/pollerMacros/' . $uuid;
    $url = sprintf('%s://%s', DEFAULT_SCHEME, $url);
    $logger->info(sprintf('Search Poller macros secrets at: %s', $url));

    try {
        $content = $httpClient->call($url, 'GET', null, ['X-Vault-Token: ' . $clientToken]);
        if (array_key_exists('data', $content) && array_key_exists('data', $content['data'])) {
            return $content['data']['data'];
        }

        return [];
    } catch (RestNotFoundException $ex) {
        $logger->info('Poller Macros not found in vault');

        return [];
    } catch (Exception $ex) {
        $logger->error('Unable to get secrets for Poller macros');

        throw $ex;
    }
}

/**
 * Delete poller macro secret from vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param Logger $logger
 * @param string $uuid
 * @param CentreonRestHttp $httpClient
 * @param string $clientToken
 *
 * @throws Throwable
 */
function deletePollerMacroSecretsFromVault(
    VaultConfiguration $vaultConfiguration,
    Logger $logger,
    string $uuid,
    CentreonRestHttp $httpClient,
    string $clientToken,
): void {
    $url = $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort()
        . '/v1/' . $vaultConfiguration->getRootPath() . '/metadata/monitoring/pollerMacros/' . $uuid;
    $url = sprintf('%s://%s', DEFAULT_SCHEME, $url);
    $logger->info(sprintf('Delete Poller macros secrets at: %s', $url));

    try {
        $httpClient->call($url, 'DELETE', null, ['X-Vault-Token: ' . $clientToken]);

    } catch (RestNotFoundException $ex) {
        $logger->info('Poller Macros not found in vault');

    } catch (Exception $ex) {
        $logger->error('Unable to delete secrets for Poller macros');

        throw $ex;
    }
}

/**
 * Update or Insert the knowledge base password into the vault.
 *
 * @param string $password
 * @param VaultConfiguration $vaultConfiguration
 * @param Logger $logger
 * @param string|null $uuid
 * @param UUIDGeneratorInterface $uuidGenerator
 *
 * @throws \Throwable
 */
function upsertKnowledgeBasePasswordInVault(
    string $password,
    VaultConfiguration $vaultConfiguration,
    Logger $logger,
    ?string $uuid,
    UUIDGeneratorInterface $uuidGenerator
): string {
    global $pearDB;

    $httpClient = new CentreonRestHttp();
    $clientToken = authenticateToVault($vaultConfiguration, $logger, $httpClient);
    $uuid = $uuid ?? $uuidGenerator->generateV4();
    writeKnowledgeBasePasswordInVault($vaultConfiguration, $uuid, $clientToken, $password, $logger, $httpClient);
    return "secret::" . $vaultConfiguration->getName() . "::" . $vaultConfiguration->getRootPath()
        . "/data/configuration/knowledge_base/" . $uuid;

}

/**
 * Find the Knowledge Base password from the vault.
 *
 * @param Logger $logger
 * @param string $kbPasswordPath
 * @param VaultConfiguration $vaultConfiguration
 *
 * @return string
 *
 * @throws \Throwable
 */
function findKnowledgeBasePasswordFromVault(
    Logger $logger,
    string $kbPasswordPath,
    VaultConfiguration $vaultConfiguration
): string {
    $httpClient = new CentreonRestHttp();
    $clientToken = authenticateToVault($vaultConfiguration, $logger, $httpClient);
    $response = $httpClient->call(
        'https://' . $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort() . '/v1/'
            . $kbPasswordPath,
        'GET',
        null,
        ['X-Vault-Token: ' . $clientToken]
    );
    return $response['data']['data']['_KBPASSWORD'] ?? throw new \Exception(
        'Unable to retrieve Knowledge Base password from Vault'
    );
}

/**
 * Write the Knowledge Base Password into the vault.
 *
 * @param VaultConfiguration $vaultConfiguration
 * @param string $uuid
 * @param string $clientToken
 * @param string $password
 * @param Logger $logger
 * @param CentreonRestHttp $httpClient
 *
 * @throws \Throwable
 */
function writeKnowledgeBasePasswordInVault(
    VaultConfiguration $vaultConfiguration,
    string $uuid,
    string $clientToken,
    string $password,
    Logger $logger,
    CentreonRestHttp $httpClient
): void {
    try {
        $url = $vaultConfiguration->getAddress() . ':' . $vaultConfiguration->getPort()
            . '/v1/' . $vaultConfiguration->getRootPath()
            . '/data/configuration/knowledge_base/' . $uuid;
        $url = sprintf("%s://%s", DEFAULT_SCHEME, $url);
        $logger->info(
            "Writing Knowledge Base Password at : " . $url,
            ["password" => $password]
        );
        $httpClient->call(
            $url,
            "POST",
            ['data' => ['_KBPASSWORD' => $password]],
            ['X-Vault-Token: ' . $clientToken]
        );
    } catch(\Exception $ex) {
        $logger->error(
            "Unable to write Knowledge Base Password into vault",
            [
                "message" => $ex->getMessage(),
                "trace" => $ex->getTraceAsString(),
                "password" => $password
            ]
        );

        throw $ex;
    }
}
