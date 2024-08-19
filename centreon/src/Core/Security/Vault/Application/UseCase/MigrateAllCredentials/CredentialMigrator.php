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

namespace Core\Security\Vault\Application\UseCase\MigrateAllCredentials;

use Centreon\Domain\Log\LoggerTrait;
use Core\AdditionalConnectorConfiguration\Application\Repository\WriteAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\Broker\Application\Repository\ReadBrokerInputOutputRepositoryInterface;
use Core\Broker\Application\Repository\WriteBrokerInputOutputRepositoryInterface;
use Core\Broker\Domain\Model\BrokerInputOutput;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Application\UseCase\VaultTrait;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;
use Core\Host\Application\Repository\WriteHostRepositoryInterface;
use Core\Host\Domain\Model\Host;
use Core\HostTemplate\Application\Repository\WriteHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Macro\Application\Repository\WriteHostMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteServiceMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\Option\Application\Repository\WriteOptionRepositoryInterface;
use Core\Option\Domain\Option;
use Core\PollerMacro\Application\Repository\WritePollerMacroRepositoryInterface;
use Core\PollerMacro\Domain\Model\PollerMacro;
use Core\Security\ProviderConfiguration\Application\OpenId\Repository\WriteOpenIdConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\OpenId\Model\CustomConfiguration;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\Migrator\AccCredentialMigratorInterface;
use Core\Security\Vault\Domain\Model\VaultConfiguration;

/**
 * @implements \IteratorAggregate<CredentialRecordedDto|CredentialErrorDto>
 *
 * @phpstan-type _ExistingUuids array{
 *      hosts:string[],
 *      services:string[],
 *      pollerMacro:?string,
 *      openId:?string,
 *      brokerConfigs:string[],
 *      accs: string[]
 * }
 */
class CredentialMigrator implements \IteratorAggregate, \Countable
{
    use LoggerTrait, VaultTrait;

    /**
     * @param \Countable&\Traversable<CredentialDto> $credentials
     * @param WriteVaultRepositoryInterface $writeVaultRepository
     * @param WriteHostRepositoryInterface $writeHostRepository
     * @param WriteHostTemplateRepositoryInterface $writeHostTemplateRepository
     * @param WriteHostMacroRepositoryInterface $writeHostMacroRepository
     * @param WriteServiceMacroRepositoryInterface $writeServiceMacroRepository
     * @param WriteOptionRepositoryInterface $writeOptionRepository
     * @param WritePollerMacroRepositoryInterface $writePollerMacroRepository
     * @param ReadBrokerInputOutputRepositoryInterface $readBrokerInputOutputRepository
     * @param WriteBrokerInputOutputRepositoryInterface $writeBrokerInputOutputRepository
     * @param WriteAccRepositoryInterface $writeAccRepository
     * @param AccCredentialMigratorInterface[] $accCredentialMigrators
     * @param Host[] $hosts
     * @param HostTemplate[] $hostTemplates
     * @param Macro[] $hostMacros
     * @param Macro[] $serviceMacros
     * @param PollerMacro[] $pollerMacros,
     * @param WriteOpenIdConfigurationRepositoryInterface $writeOpenIdConfigurationRepository
     * @param Configuration $openIdProviderConfiguration
     * @param array<int,BrokerInputOutput[]> $brokerInputOutputs
     * @param Acc[] $accs
     */
    public function __construct(
        private readonly \Traversable&\Countable $credentials,
        private readonly WriteVaultRepositoryInterface $writeVaultRepository,
        private readonly WriteHostRepositoryInterface $writeHostRepository,
        private readonly WriteHostTemplateRepositoryInterface $writeHostTemplateRepository,
        private readonly WriteHostMacroRepositoryInterface $writeHostMacroRepository,
        private readonly WriteServiceMacroRepositoryInterface $writeServiceMacroRepository,
        private readonly WriteOptionRepositoryInterface $writeOptionRepository,
        private readonly WritePollerMacroRepositoryInterface $writePollerMacroRepository,
        private readonly WriteOpenIdConfigurationRepositoryInterface $writeOpenIdConfigurationRepository,
        private readonly ReadBrokerInputOutputRepositoryInterface $readBrokerInputOutputRepository,
        private readonly WriteBrokerInputOutputRepositoryInterface $writeBrokerInputOutputRepository,
        private readonly WriteAccRepositoryInterface $writeAccRepository,
        private readonly array $accCredentialMigrators,
        private readonly array $hosts,
        private readonly array $hostTemplates,
        private readonly array $hostMacros,
        private readonly array $serviceMacros,
        private readonly array $pollerMacros,
        private readonly Configuration $openIdProviderConfiguration,
        private readonly array $brokerInputOutputs,
        private array $accs,
    ) {
    }

    public function getIterator(): \Traversable
    {
        $existingUuids = [
            'hosts' => [],
            'services' => [],
            'pollerMacro' => null,
            'openId' => null,
            'brokerConfigs' => [],
            'accs' => [],
        ];
        /**
         * @var CredentialDto $credential
         */
        foreach ($this->credentials as $credential) {
            try {
                $recordInformation = match ($credential->type) {
                    CredentialTypeEnum::TYPE_HOST, CredentialTypeEnum::TYPE_HOST_TEMPLATE => $this
                        ->migrateHostAndHostTemplateCredentials(
                            $credential,
                            $existingUuids
                        ),
                    CredentialTypeEnum::TYPE_SERVICE => $this->migrateServiceAndServiceTemplateCredentials(
                        $credential,
                        $existingUuids
                    ),
                    CredentialTypeEnum::TYPE_POLLER_MACRO => $this->migratePollerMacroPasswords(
                        $credential,
                        $existingUuids
                    ),
                    CredentialTypeEnum::TYPE_KNOWLEDGE_BASE_PASSWORD => $this->migrateKnowledgeBasePassword(
                        $credential
                    ),
                    CredentialTypeEnum::TYPE_OPEN_ID => $this->migrateOpenIdCredentials(
                        $credential,
                        $existingUuids
                    ),
                    CredentialTypeEnum::TYPE_BROKER_INPUT_OUTPUT => $this->migrateBrokerInputOutputPasswords(
                        $credential,
                        $existingUuids
                    ),
                    CredentialTypeEnum::TYPE_ADDITIONAL_CONNECTOR_CONFIGURATION => $this->migrateAccPasswords(
                        $credential,
                        $existingUuids
                    ),
                };

                $status = new CredentialRecordedDto();
                $status->uuid = $recordInformation['uuid'];
                $status->resourceId = $credential->resourceId;
                $status->vaultPath = $recordInformation['path'];
                $status->type = $credential->type;
                $status->credentialName = $credential->name;

                yield $status;
            } catch (\Throwable $ex) {
                $this->error($ex->getMessage(), ['trace' => (string) $ex]);
                $status = new CredentialErrorDto();
                $status->resourceId = $credential->resourceId;
                $status->type = $credential->type;
                $status->credentialName = $credential->name;
                $status->message = $ex->getMessage();

                yield $status;
            }
        }
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->credentials);
    }

    /**
     * @param CredentialDto $credential
     * @param _ExistingUuids $existingUuids
     *
     * @return array{uuid: string, path: string}
     */
    private function migrateHostAndHostTemplateCredentials(
        CredentialDto $credential,
        array &$existingUuids
    ): array {
        if ($credential->resourceId === null) {
            throw new \Exception('Resource ID should not be null');
        }
        $uuid = null;
        if (array_key_exists($credential->resourceId, $existingUuids['hosts'])) {
            $uuid = $existingUuids['hosts'][$credential->resourceId];
        }
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::HOST_VAULT_PATH);
        $vaultKey = $credential->name === VaultConfiguration::HOST_SNMP_COMMUNITY_KEY
            ? $credential->name
            : '_HOST' . $credential->name;
        $vaultPaths = $this->writeVaultRepository->upsert(
            $uuid,
            [
                $vaultKey => $credential->value,
            ]
        );
        $vaultPath = $vaultPaths[$vaultKey];
        $uuid = $this->getUuidFromPath($vaultPath);
        if ($uuid === null) {
            throw new \Exception('UUID not found in the vault path');
        }
        $existingUuids['hosts'][$credential->resourceId] = $uuid;
        if ($credential->name === VaultConfiguration::HOST_SNMP_COMMUNITY_KEY) {
            if ($credential->type === CredentialTypeEnum::TYPE_HOST) {
                foreach ($this->hosts as $host) {
                    if ($host->getId() === $credential->resourceId) {
                        $host->setSnmpCommunity($vaultPath);
                        $this->writeHostRepository->update($host);
                    }
                }
            } else {
                foreach ($this->hostTemplates as $hostTemplate) {
                    if ($hostTemplate->getId() === $credential->resourceId) {
                        $hostTemplate->setSnmpCommunity($vaultPath);
                        $this->writeHostTemplateRepository->update($hostTemplate);
                    }
                }
            }
        } else {
            foreach ($this->hostMacros as $hostMacro) {
                if ($hostMacro->getOwnerId() === $credential->resourceId) {
                    $hostMacro->setValue($vaultPath);
                    $this->writeHostMacroRepository->update($hostMacro);
                }
            }
        }

        return [
            'uuid' => $existingUuids['hosts'][$credential->resourceId],
            'path' => $vaultPath,
        ];
    }

    /**
     * @param CredentialDto $credential
     * @param _ExistingUuids $existingUuids
     *
     * @throws \Throwable
     *
     * @return array{uuid: string, path: string}
     */
    private function migrateServiceAndServiceTemplateCredentials(
        CredentialDto $credential,
        array &$existingUuids
    ): array {
        if ($credential->resourceId === null) {
            throw new \Exception('Resource ID should not be null');
        }
        $uuid = null;
        if (array_key_exists($credential->resourceId, $existingUuids['services'])) {
            $uuid = $existingUuids['services'][$credential->resourceId];
        }
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);
        $vaultKey = '_SERVICE' . $credential->name;
        $vaultPaths = $this->writeVaultRepository->upsert(
            $uuid,
            [$vaultKey => $credential->value]
        );

        $vaultPath = $vaultPaths[$vaultKey];
        $uuid = $this->getUuidFromPath($vaultPath);
        if ($uuid === null) {
            throw new \Exception('UUID not found in the vault path');
        }
        $existingUuids['services'][$credential->resourceId] = $uuid;
        foreach ($this->serviceMacros as $serviceMacro) {
            if ($serviceMacro->getOwnerId() === $credential->resourceId) {
                $serviceMacro->setValue($vaultPath);
                $this->writeServiceMacroRepository->update($serviceMacro);
            }
        }

        return [
            'uuid' => $existingUuids['services'][$credential->resourceId],
            'path' => $vaultPath,
        ];
    }

    /**
     * @param CredentialDto $credential
     * @param _ExistingUuids $existingUuids
     *
     * @throws \Throwable
     *
     * @return array{uuid: string, path: string}
     */
    private function migratePollerMacroPasswords(CredentialDto $credential, array &$existingUuids): array
    {
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::POLLER_MACRO_VAULT_PATH);
        $vaultPaths = $this->writeVaultRepository->upsert(
            $existingUuids['pollerMacro'] ?? null,
            [$credential->name => $credential->value]
        );
        $vaultPath = $vaultPaths[$credential->name];
        $uuid = $this->getUuidFromPath($vaultPath);
        if ($uuid === null) {
            throw new \Exception('UUID not found in the vault path');
        }
        $existingUuids['pollerMacro'] ??= $uuid;

        foreach ($this->pollerMacros as $pollerMacro) {
            if ($pollerMacro->getId() === $credential->resourceId) {
                $pollerMacro->setValue($vaultPath);
                $this->writePollerMacroRepository->update($pollerMacro);
            }
        }

        return [
            'uuid' => $existingUuids['pollerMacro'],
            'path' => $vaultPath,
        ];
    }

    /**
     * @param CredentialDto $credential
     *
     * @throws \Throwable
     *
     * @return array{uuid: string, path: string}
     */
    private function migrateKnowledgeBasePassword(CredentialDto $credential): array
    {
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::KNOWLEDGE_BASE_PATH);
        $vaultPaths = $this->writeVaultRepository->upsert(
            null,
            [$credential->name => $credential->value]
        );
        $vaultPath = $vaultPaths[$credential->name];
        $uuid = $this->getUuidFromPath($vaultPath);
        if ($uuid === null) {
            throw new \Exception('UUID not found in the vault path');
        }
        $option = new Option('kb_wiki_password', $vaultPath);
        $this->writeOptionRepository->update($option);

        return [
            'uuid' => $uuid,
            'path' => $vaultPath,
        ];
    }

    /**
     * @param CredentialDto $credential
     * @param _ExistingUuids $existingUuids
     *
     * @throws \Throwable
     *
     * @return array{uuid: string, path: string}
     */
    private function migrateOpenIdCredentials(CredentialDto $credential, array &$existingUuids): array
    {
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::OPEN_ID_CREDENTIALS_VAULT_PATH);
        $vaultPaths = $this->writeVaultRepository->upsert(
            $existingUuids['openId'] ?? null,
            [$credential->name => $credential->value]
        );
        $vaultPath = $vaultPaths[$credential->name];
        $uuid = $this->getUuidFromPath($vaultPath);
        if ($uuid === null) {
            throw new \Exception('UUID not found in the vault path');
        }
        $existingUuids['openId'] ??= $uuid;

        /**
         * @var CustomConfiguration $customConfiguration
         */
        $customConfiguration = $this->openIdProviderConfiguration->getCustomConfiguration();
        if ($credential->value === $customConfiguration->getClientId()) {
            $customConfiguration->setClientId($vaultPath);
        }
        if ($credential->value === $customConfiguration->getClientSecret()) {
            $customConfiguration->setClientSecret($vaultPath);
        }

        $this->openIdProviderConfiguration->setCustomConfiguration($customConfiguration);
        $this->writeOpenIdConfigurationRepository->updateConfiguration($this->openIdProviderConfiguration);

        return [
            'uuid' => $existingUuids['openId'],
            'path' => $vaultPath,
        ];
    }

    /**
     * @param CredentialDto $credential
     * @param _ExistingUuids $existingUuids
     *
     * @throws \Throwable
     *
     * @return array{uuid: string, path: string}
     */
    private function migrateBrokerInputOutputPasswords(
        CredentialDto $credential,
        array &$existingUuids
    ): array {
        if ($credential->resourceId === null) {
            throw new \Exception('Resource ID should not be null');
        }
        $uuid = null;
        if (array_key_exists($credential->resourceId, $existingUuids['brokerConfigs'])) {
            $uuid = $existingUuids['brokerConfigs'][$credential->resourceId];
        }
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::BROKER_VAULT_PATH);

        $vaultPaths = $this->writeVaultRepository->upsert(
            $uuid,
            [
                $credential->name => $credential->value,
            ]
        );
        $vaultPath = $vaultPaths[$credential->name];
        $uuid = $this->getUuidFromPath($vaultPath);
        if ($uuid === null) {
            throw new \Exception('UUID not found in the vault path');
        }
        $existingUuids['brokerConfigs'][$credential->resourceId] = $uuid;
        $inputOutputs = $this->brokerInputOutputs[$credential->resourceId];
        foreach ($inputOutputs as $inputOutput) {
            if (str_starts_with($credential->name, $inputOutput->getName())) {
                $credentialNamePart = str_replace($inputOutput->getName() . '_', '', $credential->name);
                $params = $inputOutput->getParameters();
                foreach ($params as $paramName => $param) {
                    if (is_array($param)) {
                        foreach ($param as $index => $groupedParams) {
                            if (
                                is_array($groupedParams)
                                && isset($groupedParams['type'])
                                && ($paramName . '_' . $groupedParams['name']) === $credentialNamePart
                            ) {
                                if (! isset($params[$paramName][$index]) || ! is_array($params[$paramName][$index])) {
                                    // for phpstan, should never happen.
                                    throw new \Exception('Unexpected error');
                                }
                                $params[$paramName][$index]['value'] = $vaultPath;
                            }
                        }
                    } elseif ($paramName === $credentialNamePart) {
                        $params[$paramName] = $vaultPath;
                    }
                }

                $inputOutput->setParameters($params);
                $this->writeBrokerInputOutputRepository->update(
                    $inputOutput,
                    $credential->resourceId,
                    $this->readBrokerInputOutputRepository->findParametersByType($inputOutput->getType()->id),
                );
            }
        }

        return [
            'uuid' => $existingUuids['brokerConfigs'][$credential->resourceId],
            'path' => $vaultPath,
        ];
    }

    /**
     * @param CredentialDto $credential
     * @param _ExistingUuids $existingUuids
     *
     * @throws \Throwable
     *
     * @return array{uuid: string, path: string}
     */
    private function migrateAccPasswords(CredentialDto $credential, array &$existingUuids): array
    {
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::ACC_VAULT_PATH);
        $vaultPaths = $this->writeVaultRepository->upsert(
            $existingUuids['accs'][$credential->resourceId] ?? null,
            [$credential->name => $credential->value]
        );
        $vaultPath = $vaultPaths[$credential->name];
        $uuid = $this->getUuidFromPath($vaultPath);
        if ($uuid === null) {
            throw new \Exception('UUID not found in the vault path');
        }
        $existingUuids['accs'][$credential->resourceId] ??= $uuid;

        foreach ($this->accs as $index => $acc) {
            if ($acc->getId() === $credential->resourceId) {
                $updatedAcc = $acc;
                foreach ($this->accCredentialMigrators as $migrator) {
                    $updatedAcc = $migrator->updateMigratedCredential($acc, $credential, $vaultPath);
                }
                $this->accs[$index] = $updatedAcc;
                $this->writeAccRepository->update($updatedAcc);
            }
        }

        return [
            'uuid' => $existingUuids['accs'][$credential->resourceId],
            'path' => $vaultPath,
        ];
    }
}
