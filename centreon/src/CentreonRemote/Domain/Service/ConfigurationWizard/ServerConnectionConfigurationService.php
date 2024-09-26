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

namespace CentreonRemote\Domain\Service\ConfigurationWizard;

use App\Kernel;
use Centreon\Infrastructure\CentreonLegacyDB\CentreonDBAdapter;
use CentreonRemote\Domain\Resources\RemoteConfig\BamBrokerCfgInfo;
use CentreonRemote\Domain\Resources\RemoteConfig\CfgNagios;
use CentreonRemote\Domain\Resources\RemoteConfig\CfgNagiosBrokerModule;
use CentreonRemote\Domain\Resources\RemoteConfig\CfgNagiosLogger;
use CentreonRemote\Domain\Resources\RemoteConfig\NagiosServer;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Application\UseCase\VaultTrait;
use Core\Common\Infrastructure\FeatureFlags;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;

abstract class ServerConnectionConfigurationService
{
    use VaultTrait;

    protected string|null $serverIp;

    protected string|null $centralIp;

    protected string|null $dbUser;

    protected string|null $dbPassword;

    protected string|null $name;

    protected bool $onePeerRetention = false;

    protected bool $shouldInsertBamBrokers = false;

    protected bool $isLinkedToCentralServer = false;

    protected int|null $brokerID = null;

    public function __construct(
        protected CentreonDBAdapter $dbAdapter,
        // protected FeatureFlags $featureFlags,
        // protected ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository,
        // protected WriteVaultRepositoryInterface $writeVaultRepository
    )
    {
        $this->dbAdapter = $dbAdapter;
    }

    /**
     * @param string|null $ip
     */
    public function setServerIp($ip): void
    {
        $this->serverIp = $ip;
    }

    /**
     * @param string|null $name
     */
    public function setName($name): void
    {
        $this->name = $name;
    }

    /**
     * @param string|null $ip
     */
    public function setCentralIp($ip): void
    {
        $this->centralIp = $ip;
    }

    /**
     * @param string|null $user
     */
    public function setDbUser($user): void
    {
        $this->dbUser = $user;
    }

    /**
     * @param string|null $password
     */
    public function setDbPassword($password): void
    {
        $this->dbPassword = $password;
    }

    /**
     * Set one peer retention mode.
     *
     * @param bool $onePeerRetention if one peer retention mode is enabled
     */
    public function setOnePeerRetention(bool $onePeerRetention): void
    {
        $this->onePeerRetention = $onePeerRetention;
    }

    /**
     * @throws \Exception
     *
     * @return int
     */
    public function insert(): int
    {
        $this->getDbAdapter()->beginTransaction();

        $serverID = $this->insertNagiosServer();

        if (! $serverID) {
            throw new \Exception('Error inserting nagios server.');
        }

        $this->insertConfigNagios($serverID);

        $this->insertConfigResources($serverID);

        $this->insertConfigCentreonBroker($serverID);

        if ($this->shouldInsertBamBrokers && $this->isRemote()) {
            $this->insertBamBrokers();
        }

        $this->getDbAdapter()->commit();

        return $serverID;
    }

    public function shouldInsertBamBrokers(): void
    {
        $this->shouldInsertBamBrokers = true;
    }

    public function isLinkedToCentralServer(): void
    {
        $this->isLinkedToCentralServer = true;
    }

    abstract protected function insertConfigCentreonBroker(int $serverID): void;

    protected function getDbAdapter(): CentreonDBAdapter
    {
        return $this->dbAdapter;
    }

    protected function insertNagiosServer(): int
    {
        return $this->insertWithAdapter('nagios_server', NagiosServer::getConfiguration($this->name, $this->serverIp));
    }

    /**
     * @param int $serverID
     *
     * @return int
     */
    protected function insertConfigNagios($serverID): int
    {
        $configID = $this->insertWithAdapter('cfg_nagios', CfgNagios::getConfiguration($this->name, $serverID));

        $this->insertWithAdapter('cfg_nagios_logger', CfgNagiosLogger::getConfiguration($configID));

        $configBroker = CfgNagiosBrokerModule::getConfiguration($configID, $this->name);

        $this->insertWithAdapter('cfg_nagios_broker_module', $configBroker[0]);
        $this->insertWithAdapter('cfg_nagios_broker_module', $configBroker[1]);

        return $configID;
    }

    /**
     * @param int $serverID
     *
     * @throws \Exception
     */
    protected function insertConfigResources($serverID): void
    {
        $sql = 'SELECT `resource_id`, `resource_name` FROM `cfg_resource`';
        $sql .= "WHERE `resource_name` IN('\$USER1$', '\$CENTREONPLUGINS$') ORDER BY `resource_name` DESC";
        $this->getDbAdapter()->query($sql);
        $results = $this->getDbAdapter()->results();

        if (count($results) < 2) {
            throw new \Exception('Resources records from `cfg_resource` could not be fetched.');
        }

        if (
            $results[0]->resource_name !== '$USER1$'
            || $results[1]->resource_name !== '$CENTREONPLUGINS$'
        ) {
            throw new \Exception('Resources records from `cfg_resource` are not as expected.');
        }

        $userResourceData = ['resource_id' => $results[0]->resource_id, 'instance_id' => $serverID];
        $pluginResourceData = ['resource_id' => $results[1]->resource_id, 'instance_id' => $serverID];

        $this->insertWithAdapter('cfg_resource_instance_relations', $userResourceData);
        $this->insertWithAdapter('cfg_resource_instance_relations', $pluginResourceData);
    }

    /**
     * insert broker log information.
     *
     * @param \Generator<array<string,string|int>> $brokerLogs
     */
    protected function insertBrokerLog(\Generator $brokerLogs): void
    {
        foreach ($brokerLogs as $brokerLog) {
            $this->insertWithAdapter('cfg_centreonbroker_log', $brokerLog);
        }
    }

    /**
     * @throws \Exception
     */
    protected function insertBamBrokers(): void
    {
        global $conf_centreon;

        if (! $this->brokerID) {
            throw new \Exception('Broker ID was not inserted in order to add BAM broker configs to it.');
        }

        $bamBrokerInfoData = BamBrokerCfgInfo::getConfiguration($conf_centreon['password']);

        $bamBrokerInfoData = $this->saveCredentialInVault($bamBrokerInfoData);
        foreach ($bamBrokerInfoData['monitoring'] as $row) {
            $row['config_id'] = $this->brokerID;
            $this->insertWithAdapter('cfg_centreonbroker_info', $row);
        }

        foreach ($bamBrokerInfoData['reporting'] as $row) {
            $row['config_id'] = $this->brokerID;
            $this->insertWithAdapter('cfg_centreonbroker_info', $row);
        }
    }

    /**
     * @param string $table
     * @param array<string,mixed> $data
     *
     * @throws \Exception
     *
     * @return int
     */
    protected function insertWithAdapter($table, array $data): int
    {
        try {
            $result = $this->getDbAdapter()->insert($table, $data);
        } catch (\Exception $e) {
            $this->getDbAdapter()->rollBack();

            throw new \Exception("Error inserting remote configuration. Rolling back. Table name: {$table}.");
        }

        return $result;
    }

    protected function isRemote(): bool
    {
        return false;
    }

    /**
     * @param array<string, array<int, string[]>> $brokerInfos
     *
     * @throws \Throwable
     *
     * @return array<string, array<int, string[]>>
     */
    protected function saveCredentialInVault(array $brokerInfos): array
    {
        $kernel = Kernel::createForWeb();
        /** @var ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository */
        $readVaultConfigurationRepository = $kernel->getContainer()->get(
            ReadVaultConfigurationRepositoryInterface::class
        );
        /** @var FeatureFlags $featureFlags */
        $featureFlags = $kernel->getContainer()->get(FeatureFlags::class);

        /** @var WriteVaultRepositoryInterface $writeVaultRepository */
        $writeVaultRepository = $kernel->getContainer()->get(WriteVaultRepositoryInterface::class);

        $writeVaultRepository->setCustomPath(AbstractVaultRepository::BROKER_VAULT_PATH);
        $vaultConfiguration = $readVaultConfigurationRepository->find();

        if (
            ! $featureFlags->isEnabled('vault')
            || ! $featureFlags->isEnabled('vault_broker')
            || $vaultConfiguration === null
        ) {
            return $brokerInfos;
        }

        foreach ($brokerInfos as $key => $inputOutput) {
            $inputOutputName = null;
            $credentialKey = null;
            $credentialValue = null;
            foreach ($inputOutput as $index => $row) {
                if (isset($row['config_key']) && $row['config_key'] === 'name') {
                    $inputOutputName = $row['config_value'];
                }
                if (isset($row['config_key']) && $row['config_key'] === 'db_password') {
                    $credentialKey = $index;
                    $credentialValue = $row['config_value'];
                }
            }

            if (
                $inputOutputName === null
                || $credentialKey === null
                || $credentialValue === null
            ) {
                continue;
            }

            $paths = $writeVaultRepository->upsert(
                $this->uuid,
                ["{$inputOutputName}_db_password" => $credentialValue]
            );

            $path = end($paths);
            if ($path !== false) {
                $this->uuid = $this->getUuidFromPath($path);
            }

            $brokerInfos[$key][$credentialKey]['config_value'] = $paths["{$inputOutputName}_db_password"];
        }

        return $brokerInfos;
    }
}
