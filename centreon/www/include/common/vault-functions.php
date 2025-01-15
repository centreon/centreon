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
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Infrastructure\FeatureFlags;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Symfony\Component\Yaml\Yaml;

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
 * @param ReadVaultRepositoryInterface $readVaultRepository
 * @param int $hostId
 * @param string $vaultPath
 * @param Logger $logger
 *
 * @throws Exception
 *
 * @return array<string, mixed>
 */
function getHostSecretsFromVault(
    ReadVaultRepositoryInterface $readVaultRepository,
    int $hostId,
    string $vaultPath,
    Logger $logger,
): array {
    try {
        return $readVaultRepository->findFromPath($vaultPath);
    } catch (Exception $ex) {
        $logger->error(sprintf('Unable to get secrets for host : %d', $hostId));

        throw $ex;
    }
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
 * @param ReadVaultRepositoryInterface $readVaultRepository
 * @param WriteVaultRepositoryInterface $writeVaultRepository
 * @param Logger $logger
 * @param ?string $snmpCommunity
 * @param array<int, array<string, string>> $macroPasswords
 * @param int $duplicatedHostId
 * @param int $newHostId
 *
 * @throws Throwable
 */
function duplicateHostSecretsInVault(
    ReadVaultRepositoryInterface $readVaultRepository,
    WriteVaultRepositoryInterface $writeVaultRepository,
    Logger $logger,
    ?string $snmpCommunity,
    array $macroPasswords,
    int $duplicatedHostId,
    int $newHostId
): void {
    global $pearDB;

    $vaultPath = null;
    // Get UUID form Host SNMP Community Path if it is set
    if (! empty($snmpCommunity)) {
        if (str_starts_with($snmpCommunity, VaultConfiguration::VAULT_PATH_PATTERN)) {
            $vaultPath = $snmpCommunity;
        }

    // Get UUID from macro password if they match the vault path regex
    } elseif ($macroPasswords !== []) {
        foreach ($macroPasswords as $macroInfo) {
            if (str_starts_with($macroInfo['macroValue'], VaultConfiguration::VAULT_PATH_PATTERN)) {
                $vaultPath = $macroInfo['macroValue'];
                break;
            }
        }
    }

    $hostSecretsFromVault = [];
    if ($vaultPath !== null) {
        $hostSecretsFromVault = getHostSecretsFromVault(
            $readVaultRepository,
            $duplicatedHostId, // The duplicated host id
            $vaultPath,
            $logger
        );
    }

    if ($hostSecretsFromVault !== []) {
        $vaultPaths = $writeVaultRepository->upsert(null, $hostSecretsFromVault);

        // Store vault path for SNMP Community
        if (array_key_exists(VaultConfiguration::HOST_SNMP_COMMUNITY_KEY, $vaultPaths)){
            updateHostTableWithVaultPath($pearDB, $vaultPaths[VaultConfiguration::HOST_SNMP_COMMUNITY_KEY], $newHostId);
        }

        // Store vault path for macros
        if ($macroPasswords !== []) {
            foreach ($macroPasswords as  $macroId => $macroInfo) {
                $macroPasswords[$macroId]['macroValue'] = $vaultPaths[$macroInfo['macroName']];
            }
            updateOnDemandMacroHostTableWithVaultPath($pearDB, $macroPasswords);
        }
    }
}

/**
 * Update on_demand_macro_host table with secrets path on vault.
 *
 * @param CentreonDB $pearDB
 * @param array<int,array{
 *   macroName: string,
 *   macroValue: string,
 *   macroPassword: '0'|'1',
 *   originalName?: string
 *  }> $macros
 *
 * @throws Throwable
 */
function updateOnDemandMacroHostTableWithVaultPath(CentreonDB $pearDB, array $macros): void
{
    $statementUpdateMacro = $pearDB->prepare(
        <<<'SQL'
                UPDATE `on_demand_macro_host` 
                    SET host_macro_value = :path 
                WHERE host_macro_id = :macroId 
                    AND host_macro_name = :name
            SQL
    );
    foreach ($macros as $macroId => $macroInfo) {
        $statementUpdateMacro->bindValue(':path', $macroInfo['macroValue'], PDO::PARAM_STR);
        $statementUpdateMacro->bindValue(':macroId', $macroId, PDO::PARAM_INT);
        $statementUpdateMacro->bindValue(':name', '$' . $macroInfo['macroName'] . '$', PDO::PARAM_STR);
        $statementUpdateMacro->execute();
    }
}

/**
 * Delete resources from the vault.
 *
 * @param WriteVaultRepositoryInterface $writeVaultRepository
 * @param int[] $hostIds
 * @param int[] $serviceIds
 *
 * @throws Throwable
 */
function deleteResourceSecretsInVault(
    WriteVaultRepositoryInterface $writeVaultRepository,
    array $hostIds,
    array $serviceIds
): void {
    if ($hostIds !== []) {
        $uuids = retrieveMultipleHostUuidsFromDatabase($hostIds);
        if (array_key_exists('host', $uuids)) {
            foreach ($uuids['host'] as $uuid) {
                $writeVaultRepository->setCustomPath(AbstractVaultRepository::HOST_VAULT_PATH);
                $writeVaultRepository->delete($uuid);
            }
        }

        // Delete entry in vault for children services
        if (array_key_exists('service', $uuids)) {
            foreach ($uuids['service'] as $uuid) {
                $writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);
                $writeVaultRepository->delete($uuid);
            }
        }
    }

    if ($serviceIds !== []) {
        $uuids = retrieveMultipleServiceUuidsFromDatabase($serviceIds);
        foreach ($uuids as $uuid) {
            $writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);
            $writeVaultRepository->delete($uuid);
        }
    }
}

/**
 * Found Host and Service linked to Host Secrets UUIDs.
 *
 * @param non-empty-array<int> $hostIds
 *
 * @return array<string,string[]>
 */
function retrieveMultipleHostUuidsFromDatabase(array $hostIds): array
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
    $statement->bindValue(':vaultPath', 'secret::%', PDO::PARAM_STR);
    $statement->execute();
    $uuids = [];
    while ($result = $statement->fetch(PDO::FETCH_ASSOC)) {
        if (
            (
                preg_match(
                    '/' . VaultConfiguration::UUID_EXTRACTION_REGEX . '/',
                    $result['host_snmp_community'],
                    $matches
                )
                || preg_match(
                '/' . VaultConfiguration::UUID_EXTRACTION_REGEX . '/',
                $result['host_macro_value'],
                $matches
                )
            )
            && isset($matches[2])
        ) {
            $uuids['host'][] = $matches[2];
        }

        // Add UUID of services linked to host
        if (
            preg_match(
                '/' . VaultConfiguration::UUID_EXTRACTION_REGEX . '/',
                $result['svc_macro_value'],
                $matches
            )
            && isset($matches[2])
        ) {
            $uuids['service'][] = $matches[2];
        }
    }

    return $uuids;
}

/**
 * Found Service Secrets UUIDs.
 *
 * @param non-empty-array<int> $serviceIds
 *
 * @return string[]
 */
function retrieveMultipleServiceUuidsFromDatabase(array $serviceIds): array
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
    $statement->bindValue(':vaultPath', 'secret::%', PDO::PARAM_STR);
    $statement->execute();
    $uuids = [];
    while ($result = $statement->fetch(PDO::FETCH_ASSOC)) {
        if (
            preg_match(
                '/' . VaultConfiguration::UUID_EXTRACTION_REGEX . '/',
                $result['svc_macro_value'],
                $matches
            )
            && isset($matches[2])
        ) {
            $uuids[] = $matches[2];
        }

    }

    return $uuids;
}

/**
 * Retrieve Vault path of a host.
 *
 * @param CentreonDB $pearDB
 * @param int $hostId
 *
 * @throws Throwable
 *
 * @return string|null
 */
function retrieveHostVaultPathFromDatabase(CentreonDB $pearDB, int $hostId): ?string
{
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
    $statement->bindValue(':vaultPath', VaultConfiguration::VAULT_PATH_PATTERN . '%', PDO::PARAM_STR);
    $statement->execute();
    if (($result = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
        foreach ($result as $columnValue) {
            if (str_starts_with($columnValue, VaultConfiguration::VAULT_PATH_PATTERN)) {
                return $columnValue;
            }
        }
    }

    return null;
}

/**
 * Retrieve Vault path of a service.
 *
 * @param CentreonDB $pearDB
 * @param int $serviceId
 *
 * @throws Throwable
 *
 * @return string|null
 */
function retrieveServiceVaultPathFromDatabase(CentreonDB $pearDB, int $serviceId): ?string
{
    $statement = $pearDB->prepare(
        <<<'SQL'
                SELECT ods.svc_macro_value
                    FROM on_demand_macro_service AS ods
                WHERE ods.svc_svc_id= :serviceId
                    AND ods.svc_macro_value LIKE :vaultPath
            SQL
    );
    $statement->bindValue(':serviceId', $serviceId, PDO::PARAM_STR);
    $statement->bindValue(':vaultPath', VaultConfiguration::VAULT_PATH_PATTERN . '%', PDO::PARAM_STR);
    $statement->execute();
    if (($result = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
        foreach ($result as $columnValue) {
            if (str_starts_with($columnValue, VaultConfiguration::VAULT_PATH_PATTERN)) {
                return $columnValue;

            }
        }
    }

    return null;
}

/**
 * Update Host Secrets in Vault while Massive changing.
 *
 * @param ReadVaultRepositoryInterface $readVaultRepository
 * @param WriteVaultRepositoryInterface $writeVaultRepository
 * @param Logger $logger
 * @param string|null $vaultPath
 * @param int $hostId
 * @param array<int,array<string,string>> $macros
 * @param ?string $snmpCommunity
 *
 * @throws Throwable $ex
 */
function updateHostSecretsInVaultFromMC(
    ReadVaultRepositoryInterface $readVaultRepository,
    WriteVaultRepositoryInterface $writeVaultRepository,
    Logger $logger,
    ?string $vaultPath,
    int $hostId,
    array $macros,
    ?string $snmpCommunity
): void {
    global $pearDB;

    $hostSecretsFromVault = [];
    $uuid = null;
    if ($vaultPath !== null) {
        $uuid = preg_match(
            '/' . VaultConfiguration::UUID_EXTRACTION_REGEX . '/',
            $vaultPath,
            $matches
        ) ? $matches[2] : null;
        $hostSecretsFromVault = getHostSecretsFromVault(
            $readVaultRepository,
            $hostId,
            $vaultPath,
            $logger,
        );
    }

    $updateHostPayload = prepareHostUpdateMCPayload(
        $snmpCommunity,
        $macros,
        $hostSecretsFromVault
    );

    if ($updateHostPayload !== []) {
        $vaultPaths = $writeVaultRepository->upsert($uuid, $updateHostPayload);
        foreach ($macros as  $macroId => $macroInfo) {
            $macros[$macroId]['macroValue'] = $vaultPaths[$macroInfo['macroName']];
        }

        // Store vault path for SNMP Community
        if (array_key_exists(VaultConfiguration::HOST_SNMP_COMMUNITY_KEY, $vaultPaths)) {
            updateHostTableWithVaultPath($pearDB, $vaultPaths[VaultConfiguration::HOST_SNMP_COMMUNITY_KEY], $hostId);
        }

        // Store vault path for macros
        if ($macros !== []) {
            updateOnDemandMacroHostTableWithVaultPath($pearDB, $macros);
        }
    }
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
        $secretsFromVault[$macroInfos['macroName']] = $macroInfos['macroValue'];
    }

    // Add SNMP Community if a new value has been set
    if ($hostSNMPCommunity !== null && ! str_starts_with($hostSNMPCommunity, VaultConfiguration::VAULT_PATH_PATTERN)) {
        $secretsFromVault[VaultConfiguration::HOST_SNMP_COMMUNITY_KEY] = $hostSNMPCommunity;
    }

    return $secretsFromVault;
}

/**
 * Update Host Secrets in Vault.
 *
 * @param ReadVaultRepositoryInterface $readVaultRepository
 * @param WriteVaultRepositoryInterface $writeVaultRepository
 * @param Logger $logger
 * @param string|null $vaultPath
 * @param int $hostId
 * @param array $macros
 * @param string|null $snmpCommunity
 *
 * @throws Throwable
 */
function updateHostSecretsInVault(
    ReadVaultRepositoryInterface $readVaultRepository,
    WriteVaultRepositoryInterface $writeVaultRepository,
    Logger $logger,
    ?string $vaultPath,
    int $hostId,
    array $macros,
    ?string $snmpCommunity
): void {
    global $pearDB;
    $hostSecretsFromVault = [];
    $uuid = null;
    if ($vaultPath !== null) {
        $uuid = preg_match(
            '/' . VaultConfiguration::UUID_EXTRACTION_REGEX . '/',
            $vaultPath,
            $matches
        ) ? $matches[2] : null;
        $hostSecretsFromVault = getHostSecretsFromVault(
            $readVaultRepository,
            $hostId,
            $vaultPath,
            $logger
        );
    }

    $updateHostPayload = prepareHostUpdatePayload(
        $snmpCommunity,
        $macros,
        $hostSecretsFromVault
    );

    // If no more fields are password types, we delete the host from the vault has it will not be read.
    if (empty($updateHostPayload['to_insert']) && $uuid !== null) {
        $writeVaultRepository->delete($uuid);
    } else {
        $vaultPaths = $writeVaultRepository->upsert(
            $uuid,
            $updateHostPayload['to_insert'],
            $updateHostPayload['to_delete']
        );
        foreach ($macros as  $macroId => $macroInfo) {
            $macros[$macroId]['macroValue'] = $vaultPaths[$macroInfo['macroName']];
        }

        // Store vault path for SNMP Community
        if (array_key_exists(VaultConfiguration::HOST_SNMP_COMMUNITY_KEY, $vaultPaths)){
            updateHostTableWithVaultPath($pearDB, $vaultPaths[VaultConfiguration::HOST_SNMP_COMMUNITY_KEY], $hostId);
        }

        // Store vault path for macros
        if ($macros !== []) {
            updateOnDemandMacroHostTableWithVaultPath($pearDB, $macros);
        }
    }
}

/**
 * Prepare the write-in vault payload while updating a host.
 *
 * This method will compare the secrets already stored in the vault with the secrets submitted by the form
 * And return which secrets should be inserted or deleted.
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
 * @return array{to_insert: array<string,string>, to_delete: array<string,string>}
 */
function prepareHostUpdatePayload(?string $hostSNMPCommunity, array $macros, array $secretsFromVault): array
{
    $payload = ['to_insert' => [], 'to_delete' => []];
    // Unset existing macros on vault if they no more exist while submitting the form
    foreach (array_keys($secretsFromVault) as $secretKey) {
        if ($secretKey !== VaultConfiguration::HOST_SNMP_COMMUNITY_KEY) {
            $macroName = [];
            foreach ($macros as $macroInfos) {
                $macroName[] = $macroInfos['macroName'];
                if (array_key_exists('originalName', $macroInfos) && $secretKey === $macroInfos['originalName']) {
                    $secretsFromVault[$macroInfos['macroName']] = $secretsFromVault[$secretKey];
                }
            }
            if (! in_array($secretKey, $macroName, true)) {
                $payload['to_delete'][$secretKey] = $secretsFromVault[$secretKey];
                unset($secretsFromVault[$secretKey]);
            }
        }
    }

    // Add macros to payload if they are password type and their values have changed
    foreach ($macros as $macroInfos) {
        if (
            $macroInfos['macroPassword'] === '1'
            && ! str_starts_with($macroInfos['macroValue'], VaultConfiguration::VAULT_PATH_PATTERN)
        ) {
            $secretsFromVault[$macroInfos['macroName']] = $macroInfos['macroValue'];
        }
    }

    /**
     * Unset existing SNMP Community if it no more exists while submitting the form
     *
     * A non updated SNMP Community is considered as null
     * A removed SNMP Community is considered as an empty string
     */
    if (
        array_key_exists(VaultConfiguration::HOST_SNMP_COMMUNITY_KEY, $secretsFromVault)
        && $hostSNMPCommunity === ''
    ) {
        $payload['to_delete'][VaultConfiguration::HOST_SNMP_COMMUNITY_KEY] = $secretsFromVault[
            VaultConfiguration::HOST_SNMP_COMMUNITY_KEY
        ];
        unset($secretsFromVault[VaultConfiguration::HOST_SNMP_COMMUNITY_KEY]);
    }

    // Add SNMP Community if a new value has been set
    if ($hostSNMPCommunity !== null && ! str_starts_with($hostSNMPCommunity, VaultConfiguration::VAULT_PATH_PATTERN)) {
        $secretsFromVault[VaultConfiguration::HOST_SNMP_COMMUNITY_KEY] = $hostSNMPCommunity;
    }

    $payload['to_insert'] = $secretsFromVault;

    return $payload;
}

/**
 * Duplicate Service Secrets in Vault.
 *
 * @param ReadVaultRepositoryInterface $readVaultRepository
 * @param WriteVaultRepositoryInterface $writeVaultRepository
 * @param Logger $logger
 * @param int $duplicatedServiceId
 * @param array<int, array<string, string> $macroPasswords
 *
 * @throws Throwable
 */
function duplicateServiceSecretsInVault(
    ReadVaultRepositoryInterface $readVaultRepository,
    WriteVaultRepositoryInterface $writeVaultRepository,
    Logger $logger,
    int $duplicatedServiceId,
    array $macroPasswords,
): void {
    global $pearDB;

    $vaultPath = null;
    foreach ($macroPasswords as $macroInfo) {
        if (str_starts_with($macroInfo['macroValue'], VaultConfiguration::VAULT_PATH_PATTERN)) {
            $vaultPath = $macroInfo['macroValue'];
            break;
        }
    }

    $serviceSecretsFromVault = [];
    if ($vaultPath !== null) {
        $serviceSecretsFromVault = getServiceSecretsFromVault(
            $readVaultRepository,
            $duplicatedServiceId, // The duplicated service id
            $vaultPath,
            $logger,
        );
    }

    if ($serviceSecretsFromVault !== []) {
        $vaultPaths = $writeVaultRepository->upsert(null, $serviceSecretsFromVault);

        // Store vault path for macros
        if ($macroPasswords !== []) {
            foreach ($macroPasswords as  $macroId => $macroInfo) {
                $macroPasswords[$macroId]['macroValue'] = $vaultPaths[$macroInfo['macroName']];
            }
            updateOnDemandMacroServiceTableWithVaultPath($pearDB, $macroPasswords);
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
    ReadVaultRepositoryInterface $readVaultRepository,
    int $serviceId,
    string $vaultPath,
    Logger $logger,
): array {
    try {
        return $readVaultRepository->findFromPath($vaultPath);
    } catch (Exception $ex) {
        $logger->error(sprintf('Unable to get secrets for Service : %d', $serviceId));

        throw $ex;
    }
}

/**
 * Update on_demand_macro_service table with secrets path on vault.
 *
 * @param CentreonDB $pearDB
 * @param array<int,array{
 *   macroName: string,
 *   macroValue: string,
 *   macroPassword: '0'|'1',
 *   originalName?: string
 *  }> $macros
 *
 * @throws Throwable
 */
function updateOnDemandMacroServiceTableWithVaultPath(CentreonDB $pearDB, array $macros): void
{
    $statementUpdateMacro = $pearDB->prepare(
        <<<'SQL'
                UPDATE `on_demand_macro_service` 
                    SET svc_macro_value = :path 
                WHERE svc_macro_id = :macroId 
                    AND svc_macro_name = :name
            SQL
    );
    foreach ($macros as $macroId => $macroInfo) {
        $statementUpdateMacro->bindValue(':path', $macroInfo['macroValue'], PDO::PARAM_STR);
        $statementUpdateMacro->bindValue(':macroId', $macroId, PDO::PARAM_INT);
        $statementUpdateMacro->bindValue(':name', '$' . $macroInfo['macroName'] . '$', PDO::PARAM_STR);
        $statementUpdateMacro->execute();
    }
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
): ?string {
    $statement = $pearDB->prepare(
        <<<'SQL'
                SELECT ods.svc_macro_value
                    FROM on_demand_macro_service AS ods
                    WHERE ods.svc_svc_id= :serviceId
                    AND ods.svc_macro_value LIKE :vaultPath
            SQL
    );
    $statement->bindValue(':serviceId', $serviceId, PDO::PARAM_STR);
    $statement->bindValue(':vaultPath', VaultConfiguration::VAULT_PATH_PATTERN . '%', PDO::PARAM_STR);
    $statement->execute();
    if (($result = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
        if (
            preg_match(
                '/' . VaultConfiguration::UUID_EXTRACTION_REGEX . '/',
                $result['svc_macro_value'],
                $matches
            )
            && isset($matches[2])
        ) {
            return $matches[2];
        }
    }

    return null;
}

/**
 * Update Service Secrets in Vault after Massive changing.
 *
 * @param ReadVaultRepositoryInterface $readVaultRepository
 * @param WriteVaultRepositoryInterface $writeVaultRepository
 * @param Logger $logger
 * @param string|null $vaultPath
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
    ReadVaultRepositoryInterface $readVaultRepository,
    WriteVaultRepositoryInterface $writeVaultRepository,
    Logger $logger,
    ?string $vaultPath,
    int $serviceId,
    array $macros
): void {
    global $pearDB;

    $serviceSecretsFromVault = [];
    $uuid = null;
    if ($vaultPath !== null) {
        $uuid = preg_match(
            '/' . VaultConfiguration::UUID_EXTRACTION_REGEX . '/',
            $vaultPath,
            $matches
        ) ? $matches[2] : null;
        $serviceSecretsFromVault = getServiceSecretsFromVault(
            $readVaultRepository,
            $serviceId,
            $vaultPath,
            $logger
        );
    }

    $updateServicePayload = prepareServiceUpdateMCPayload(
        $macros,
        $serviceSecretsFromVault
    );
    if (! empty($updateServicePayload)) {
        $vaultPaths = $writeVaultRepository->upsert($uuid, $updateServicePayload);
        foreach ($macros as  $macroId => $macroInfo) {
            $macros[$macroId]['macroValue'] = $vaultPaths[$macroInfo['macroName']];
        }

        // Store vault path for macros
        if ($macros !== []) {
            updateOnDemandMacroServiceTableWithVaultPath($pearDB, $macros);
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
            && ! str_starts_with($macroInfos['macroValue'], VaultConfiguration::VAULT_PATH_PATTERN)
        ) {
            $serviceSecretsFromVault[$macroInfos['macroName']] = $macroInfos['macroValue'];
        }
    }

    return $serviceSecretsFromVault;
}

/**
 * Update Service Secrest in Vault.
 *
 * @param ReadVaultRepositoryInterface $readVaultRepository
 * @param WriteVaultRepositoryInterface $writeVaultRepository
 * @param Logger $logger
 * @param int $serviceId
 * @param array<int,array{
 *       macroName: string,
 *       macroValue: string,
 *       macroPassword: '0'|'1',
 *       originalName?: string
 *  }> $macros
 * @param string|null $uuid
 *
 * @throws Throwable
 */
function updateServiceSecretsInVault(
    ReadVaultRepositoryInterface $readVaultRepository,
    WriteVaultRepositoryInterface $writeVaultRepository,
    Logger $logger,
    ?string $vaultPath,
    int $serviceId,
    array $macros,
): void {
    global $pearDB;
    $serviceSecretsFromVault = [];
    if ($vaultPath !== null) {
        $uuid = preg_match(
            '/' . VaultConfiguration::UUID_EXTRACTION_REGEX . '/',
            $vaultPath,
            $matches
        ) ? $matches[2] : null;
        $serviceSecretsFromVault = getServiceSecretsFromVault(
            $readVaultRepository,
            $serviceId,
            $vaultPath,
            $logger
        );
    }
    $updateServicePayload = prepareServiceUpdatePayload(
        $macros,
        $serviceSecretsFromVault
    );

    // If no more fields are password types, we delete the service from the vault has it will not be read.
    if (empty($updateServicePayload['to_insert']) && $uuid !== null) {
        $writeVaultRepository->delete($uuid);
    } else {
        $vaultPaths = $writeVaultRepository->upsert(
            $uuid,
            $updateServicePayload['to_insert'],
            $updateServicePayload['to_delete']);
        foreach ($macros as  $macroId => $macroInfo) {
            $macros[$macroId]['macroValue'] = $vaultPaths[$macroInfo['macroName']];
        }
        // Store vault path for macros
        if ($macros !== []) {
            updateOnDemandMacroServiceTableWithVaultPath($pearDB, $macros);
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
 * @return array{to_insert: array<string,string>, to_delete: array<string,string>}
 */
function prepareServiceUpdatePayload(array $macros, array $serviceSecretsFromVault): array
{
    $payload = ['to_insert' => [], 'to_delete' => []];
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
            $payload['to_delete'][$secretKey] = $serviceSecretsFromVault[$secretKey];
            unset($serviceSecretsFromVault[$secretKey]);
        }
    }

    // Add macros to payload if they are password type and their values have changed
    foreach ($macros as $macroInfos) {
        $serviceSecretsFromVault[$macroInfos['macroName']] = $macroInfos['macroValue'];
    }

    $payload['to_insert'] = $serviceSecretsFromVault;

    return $payload;
}

/**
 * insert Service Secrets in Vault.
 *
 * @param WriteVaultRepositoryInterface $writeVaultRepository
 * @param array<int,array{
 *       macroName: string,
 *       macroValue: string,
 *       macroPassword: '1',
 *       originalName?: string
 *  }> $macroPasswords
 *
 * @throws Throwable
 */
function insertServiceSecretsInVault(
    WriteVaultRepositoryInterface $writeVaultRepository,
    array $macroPasswords
): void {
    global $pearDB;
    $payload = [];
    foreach ($macroPasswords as $macroInfo) {
        $payload[$macroInfo['macroName']] = $macroInfo['macroValue'];
    }
    $vaultPaths = $writeVaultRepository->upsert(null, $payload);
    foreach ($macroPasswords as $macroId => $macroInfo) {
        $macroPasswords[$macroId]['macroValue'] = $vaultPaths[$macroInfo['macroName']];
    }

    updateOnDemandMacroServiceTableWithVaultPath($pearDB, $macroPasswords);
}

// POLLER MACROS (Configuration > Pollers > Resources)

/**
 * Retrieve UUID of a poller macro.
 *
 * @param CentreonDB $pearDB
 *
 * @throws Throwable
 *
 * @return string|null
 */
function retrievePollerMacroVaultPathFromDatabase(CentreonDB $pearDB): ?string {
    $statement = $pearDB->prepare(
        <<<'SQL'
                SELECT resource_line FROM cfg_resource
                WHERE resource_line LIKE :vaultPath
            SQL
    );
    $statement->bindValue(':vaultPath', VaultConfiguration::VAULT_PATH_PATTERN . '%', PDO::PARAM_STR);
    $statement->execute();
    if (($result = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
        /** @var array{resource_line:string} $result */
        if (str_starts_with($result['resource_line'], VaultConfiguration::VAULT_PATH_PATTERN)) {

            return $result['resource_line'];
        }
    }

    return null;
}

/**
 *  Insert poller macros Secrets in Vault.
 *
 * @param ReadVaultRepositoryInterface $readVaultRepository
 * @param WriteVaultRepositoryInterface $writeVaultRepository
 * @param string $key
 * @param string $value
 * @param string|null $vaultPath
 *
 * @throws Throwable
 *
 * @return string|null
 */
function upsertPollerMacroSecretInVault(
    ReadVaultRepositoryInterface $readVaultRepository,
    WriteVaultRepositoryInterface $writeVaultRepository,
    string $key,
    string $value,
    ?string $vaultPath = null
): string|null {
    if (! empty($value)) {

        $data = [];
        $uuid = null;
        if ($vaultPath !== null) {
            $data = $readVaultRepository->findFromPath($vaultPath);
            $uuid = preg_match('/' . VaultConfiguration::UUID_EXTRACTION_REGEX . '/', $vaultPath, $matches) ? $matches[2] : null;
        }

        $data[$key] = $value;
        $vaultPaths = $writeVaultRepository->upsert($uuid, $data);

        return $vaultPaths[$key];
    }

    return null;
}

/**
 * delete poller macros Secrets in Vault.
 *
 * @param ReadVaultRepositoryInterface $readVaultRepository
 * @param WriteVaultRepositoryInterface $writeVaultRepository
 * @param string $uuid
 * @param string $vaultPath
 * @param string $key
 *
 * @throws Throwable
 *
 */
function deletePollerMacroSecretInVault(
    ReadVaultRepositoryInterface $readVaultRepository,
    WriteVaultRepositoryInterface $writeVaultRepository,
    string $uuid,
    string $vaultPath,
    string $key,
): void {

    $existingData = $readVaultRepository->findFromPath($vaultPath);
    unset($existingData[str_replace('$', '', $key)]);
    $dataToDelete = [$key => ''];

    if ($existingData !== []) {
        $writeVaultRepository->upsert($uuid, [], $dataToDelete);
    } else {
        $writeVaultRepository->delete($uuid);
    }
}

/**
 * Get poller macro secrets data from vault.
 *
 * @param ReadVaultRepositoryInterface $readVaultRepository
 * @param string $vaultPath
 *
 * @throws Throwable
 *
 * @return array<string, string>
 */
function readPollerMacroSecretsInVault(
    ReadVaultRepositoryInterface $readVaultRepository,
    string $vaultPath,
): array {
    try {
        return $readVaultRepository->findFromPath($vaultPath);
    } catch (Exception $ex) {
        $logger->error('Unable to get secrets for Poller macros');

        throw $ex;
    }
}

/**
 * Update or Insert the knowledge base password into the vault.
 *
 * @param WriteVaultRepositoryInterface $writeVaultRepository
 * @param string $password
 * @param string|null $uuid
 *
 * @throws Throwable
 *
 * @return string
 */
function upsertKnowledgeBasePasswordInVault(
    WriteVaultRepositoryInterface $writeVaultRepository,
    string $password,
    ?string $uuid,
): string {
    $vaultPaths = $writeVaultRepository->upsert($uuid, [VaultConfiguration::KNOWLEDGE_BASE_KEY => $password]);

    return $vaultPaths[VaultConfiguration::KNOWLEDGE_BASE_KEY];

}

/**
 * Find the Knowledge Base password from the vault.
 *
 * @param ReadVaultRepositoryInterface $readVaultRepository
 * @param string $kbPasswordPath
 *
 * @throws Throwable
 *
 * @return string
 */
function findKnowledgeBasePasswordFromVault(
    ReadVaultRepositoryInterface $readVaultRepository,
    string $kbPasswordPath,
): string {
     $data = $readVaultRepository->findFromPath($kbPasswordPath);

     return $data[VaultConfiguration::KNOWLEDGE_BASE_KEY];
}

/**
 * Migrate database credentials to Vault and update the different config files.
 *
 * @param WriteVaultRepositoryInterface $writeVaultRepository
 *
 * @return array<string, string>
 *
 * @throws Throwable
 */
function migrateDatabaseCredentialsToVault(
    WriteVaultRepositoryInterface $writeVaultRepository
): array {
    $credentials = retrieveDatabaseCredentialsFromConfigFile();
    if (str_starts_with($credentials['username'], VaultConfiguration::VAULT_PATH_PATTERN)) {
        return [];
    }

    return $writeVaultRepository->upsert(null, [
        VaultConfiguration::DATABASE_USERNAME_KEY => $credentials['username'],
        VaultConfiguration::DATABASE_PASSWORD_KEY => $credentials['password'],
    ]);
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
 * Update the different config files with the vault path.
 *
 * @param array<string,string> $vaultPath
 *
 * @throws Exception
 */
function updateConfigFilesWithVaultPath($vaultPaths): void
{
    $featuresFileContent = file_get_contents(__DIR__ . '/../../../config/features.json');
    $featureFlagManager = new FeatureFlags(false, $featuresFileContent);

    updateCentreonConfPhpFile($vaultPaths);
    if ($featureFlagManager->isEnabled('vault_broker')) {
        updateCentreonConfPmFile($vaultPaths);
        updateDatabaseYamlFile($vaultPaths);
    }
}


/**
 * Migrate Gorgone API credentials to Vault and return the vault path.
 *
 * @param WriteVaultRepositoryInterface $writeVaultRepository
 *
 * @return array<string,string>
 */
function migrateGorgoneCredentialsToVault(WriteVaultRepositoryInterface $writeVaultRepository): array
{
    $writeVaultRepository->setCustomPath(AbstractVaultRepository::GORGONE_VAULT_PATH);
    $gorgonePassword = retrieveGorgoneApiCredentialsFromConfigFile();
    if (str_starts_with($gorgonePassword, VaultConfiguration::VAULT_PATH_PATTERN)) {
        return [];
    }

    return $writeVaultRepository->upsert(null, [
        VaultConfiguration::GORGONE_PASSWORD => $gorgonePassword,
    ]);
}

/**
 * Retrieve Gorgone API credentials from the configuration file.
 *
 * @return string
 *
 * @throws Exception
 */
function retrieveGorgoneApiCredentialsFromConfigFile(): string
{
    $filePath = '/etc/centreon-gorgone/config.d/31-centreon-api.yaml';

    if (
        ! file_exists($filePath)
        || ($content = file_get_contents($filePath)) === false
    ) {
        throw new Exception('Unable to retrieve content of file: ' . $filePath);
    }

    $content = Yaml::parse($content);

    return $content['gorgone']['tpapi'][0]['password']
        ?? throw new Exception('Unable to retrieve Gorgone API password');
}


/**
 * Update the Gorgone API configuration file with the Vault path.
 *
 * @param array<string,string> $vaultPaths the Vault paths of the Gorgone API credentials
 *
 * @throws Exception if the file cannot be read or updated
 */
function updateGorgoneApiFile(array $vaultPaths): void
{
    $filePath = '/etc/centreon-gorgone/config.d/31-centreon-api.yaml';

    if (
        ! file_exists($filePath)
        || ($content = file_get_contents($filePath)) === false
    ) {
        throw new Exception('Unable to retrieve content of file: ' . $filePath);
    }

    $newContentYaml = preg_replace(
        '/password: (.*)/',
        'password: "' . $vaultPaths[VaultConfiguration::GORGONE_PASSWORD] . '"',
        $content
    );

    file_put_contents($filePath, $newContentYaml) ?: throw new Exception('Unable to update file: ' . $filePath);
}

/**
 * @param array<string, string> $vaultPaths
 *
 * @throws Exception
 */
function updateCentreonConfPhpFile(array $vaultPaths): void
{
    if (! file_exists(_CENTREON_ETC_ . '/centreon.conf.php')
        || ($content = file_get_contents(_CENTREON_ETC_ . '/centreon.conf.php')) === false
    ) {
        throw new Exception('Unable to retrieve content of file: ' . _CENTREON_ETC_ . '/centreon.conf.php');
    }

    $newContentPhp = preg_replace(
        '/\$conf_centreon\[[\'\"]user[\'\"]\]\s*=\s*(.*)/',
        '\$conf_centreon[\'user\'] = \'' . $vaultPaths[VaultConfiguration::DATABASE_USERNAME_KEY] . '\';',
        $content
    );
    $newContentPhp = preg_replace(
        '/\$conf_centreon\[[\'\"]password[\'\"]\]\s*=\s*(.*)/',
        '\$conf_centreon[\'password\'] = \'' . $vaultPaths[VaultConfiguration::DATABASE_PASSWORD_KEY] . '\';',
        $newContentPhp
    );

    file_put_contents(_CENTREON_ETC_ . '/centreon.conf.php', $newContentPhp)
        ?: throw new Exception('Unable to update file: ' . _CENTREON_ETC_ . '/centreon.conf.php');
}

/**
 * @param array<string, string> $vaultPaths
 *
 * @throws Exception
 */
function updateCentreonConfPmFile(array $vaultPaths): void
{
    if (! file_exists(_CENTREON_ETC_ . '/conf.pm')
        || ($content = file_get_contents(_CENTREON_ETC_ . '/conf.pm')) === false
    ) {
        throw new Exception('Unable to retrieve content of file: ' . _CENTREON_ETC_ . '/conf.pm');
    }

    $newContentPm = preg_replace(
        '/"db_user"\s*=>\s*(.*)/',
        '"db_user" => "' . $vaultPaths[VaultConfiguration::DATABASE_USERNAME_KEY] .'",',
        $content
    );
    $newContentPm = preg_replace(
        '/"db_passwd"\s*=>\s*(.*)/',
        '"db_passwd" => "' . $vaultPaths[VaultConfiguration::DATABASE_PASSWORD_KEY] . '"',
        $newContentPm
    );
    $newContentPm = preg_replace(
        '/\$mysql_user\s*=\s*(.*)/',
        '$mysql_user = "' . $vaultPaths[VaultConfiguration::DATABASE_USERNAME_KEY] .'";',
        $newContentPm
    );
    $newContentPm = preg_replace(
        '/\$mysql_passwd\s*=\s*(.*)/',
        '$mysql_passwd = "' . $vaultPaths[VaultConfiguration::DATABASE_PASSWORD_KEY] . '";',
        $newContentPm
    );

    file_put_contents(_CENTREON_ETC_ . '/conf.pm', $newContentPm)
        ?: throw new Exception('Unable to update file: ' . _CENTREON_ETC_ . '/conf.pm');
}

/**
 * @param array<string, string> $vaultPaths
 *
 * @throws Exception
 */
function updateDatabaseYamlFile(array $vaultPaths): void
{
    if (! file_exists(_CENTREON_ETC_ . '/config.d/10-database.yaml')
        || ($content = file_get_contents(_CENTREON_ETC_ . '/config.d/10-database.yaml')) === false
    ) {
        throw new Exception('Unable to retrieve content of file: ' . _CENTREON_ETC_
            . '/config.d/10-database.yaml');
    }
    $newContentYaml = preg_replace(
        '/username: (.*)/',
        'username: "' . $vaultPaths[VaultConfiguration::DATABASE_USERNAME_KEY] . '"',
        $content
    );
    $newContentYaml = preg_replace(
        '/password: (.*)/',
        'password: "' . $vaultPaths[VaultConfiguration::DATABASE_PASSWORD_KEY] . '"',
        $newContentYaml
    );

    file_put_contents(_CENTREON_ETC_ . '/config.d/10-database.yaml', $newContentYaml)
        ?: throw new Exception('Unable to update file: ' . _CENTREON_ETC_ . '/config.d/10-database.yaml');
}

// BROKER CONFIG

/**
 * Delete Broker Configuration from Vault.
 *
 * @param WriteVaultRepositoryInterface $writeVaultRepository
 * @param int[] $brokerConfigIds
 *
 * @throws Throwable
 */
function deleteBrokerConfigsFromVault(
    WriteVaultRepositoryInterface $writeVaultRepository,
    array $brokerConfigIds,
): void {
    $uuids = retrieveMultipleBrokerConfigUuidsFromDatabase($brokerConfigIds);
    foreach ($uuids as $uuid) {
        $writeVaultRepository->delete($uuid);
    }
}

/**
 * Retrieve broker config vault UUIDs from database.
 *
 * @param int[] $brokerIds
 *
 * @return string[]
 */
function retrieveMultipleBrokerConfigUuidsFromDatabase(array $brokerIds): array
{
    global $pearDB;

    $bindParams = [];
    foreach ($brokerIds as $key => $brokerId) {
        $bindParams[':configId_' . $key] = $brokerId;
    }

    $bindString = implode(', ', array_keys($bindParams));
    $statement = $pearDB->prepare(
        <<<SQL
            SELECT DISTINCT config_value
            FROM cfg_centreonbroker_info
            WHERE config_value LIKE :vaultPath
                AND config_id IN ( {$bindString} );
            SQL
    );
    foreach ($bindParams as $token => $brokerId) {
        $statement->bindValue($token, $brokerId, PDO::PARAM_INT);
    }
    $statement->bindValue(':vaultPath', VaultConfiguration::VAULT_PATH_PATTERN . '%', PDO::PARAM_STR);
    $statement->execute();
    $uuids = [];
    while ($result = $statement->fetchColumn()) {
        $uuids[] =
            preg_match('/' . VaultConfiguration::UUID_EXTRACTION_REGEX . '/', $result, $matches)
                ? $matches[2]
                : null;
    }

    return array_filter($uuids, fn ($uuid) => $uuid !== null);
}

/**
 * Retrieve raw value of broker config parameters from vault.
 *
 * @param ReadVaultRepositoryInterface $readVaultRepository
 * @param Logger $logger
 * @param string $key
 * @param string $vaultPath
 *
 * @throws Throwable
 *
 * @return string
 */
function findBrokerConfigValueFromVault(
    ReadVaultRepositoryInterface $readVaultRepository,
    Logger $logger,
    string $key,
    string $vaultPath,
): string
{
    try {
        $content = $readVaultRepository->findFromPath($vaultPath);
        if (! array_key_exists($key, $content)) {
            return $vaultPath;
        }
        return $content[$key];
    } catch (Exception $ex) {
        $logger->error('Unable to get secrets for Broker Configuration');

        throw $ex;
    }
}
