<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\Security\Vault\Application\UseCase\RevertAllCredentials;

use Centreon\Domain\Log\LoggerTrait;
use Core\AdditionalConnectorConfiguration\Application\Repository\WriteAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\Broker\Application\Repository\ReadBrokerInputOutputRepositoryInterface;
use Core\Broker\Application\Repository\WriteBrokerInputOutputRepositoryInterface;
use Core\Broker\Domain\Model\BrokerInputOutput;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
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
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialErrorDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialRecordedDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialTypeEnum;
use Core\Security\Vault\Application\UseCase\RevertAllCredentials\Reverter\AccCredentialReverterInterface;
use Core\Security\Vault\Domain\Model\VaultConfiguration;

/**
 * Reverse of {@see \Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialMigrator}.
 *
 * For each credential currently stored as a vault path (`secret::...`) in the database, it reads the
 * plaintext back from the vault and writes it into the original database column. Once every credential
 * has been restored, the corresponding vault secrets are deleted in a second pass (one delete per unique
 * UUID). The delete pass runs after all reads/DB writes so that credentials sharing a UUID are all restored
 * before the entry is removed; a vault deletion failure is logged and tolerated (the revert still succeeds).
 *
 * @implements \IteratorAggregate<CredentialRecordedDto|CredentialErrorDto>
 */
class CredentialReverter implements \IteratorAggregate, \Countable
{
    use LoggerTrait;
    use VaultTrait;

    /**
     * @param \Countable&\Traversable<CredentialDto> $credentials
     * @param ReadVaultRepositoryInterface $readVaultRepository
     * @param WriteVaultRepositoryInterface $writeVaultRepository
     * @param WriteHostRepositoryInterface $writeHostRepository
     * @param WriteHostTemplateRepositoryInterface $writeHostTemplateRepository
     * @param WriteHostMacroRepositoryInterface $writeHostMacroRepository
     * @param WriteServiceMacroRepositoryInterface $writeServiceMacroRepository
     * @param WriteOptionRepositoryInterface $writeOptionRepository
     * @param WritePollerMacroRepositoryInterface $writePollerMacroRepository
     * @param WriteOpenIdConfigurationRepositoryInterface $writeOpenIdConfigurationRepository
     * @param ReadBrokerInputOutputRepositoryInterface $readBrokerInputOutputRepository
     * @param WriteBrokerInputOutputRepositoryInterface $writeBrokerInputOutputRepository
     * @param WriteAccRepositoryInterface $writeAccRepository
     * @param AccCredentialReverterInterface[] $accCredentialReverters
     * @param Host[] $hosts
     * @param HostTemplate[] $hostTemplates
     * @param Macro[] $hostMacros
     * @param Macro[] $serviceMacros
     * @param PollerMacro[] $pollerMacros
     * @param Configuration $openIdProviderConfiguration
     * @param array<int,BrokerInputOutput[]> $brokerInputOutputs
     * @param Acc[] $accs
     */
    public function __construct(
        private readonly \Traversable&\Countable $credentials,
        private readonly ReadVaultRepositoryInterface $readVaultRepository,
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
        private readonly array $accCredentialReverters,
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
        /** @var array<string, array{path: string, uuid: string}> $vaultSecretsToDelete keyed by "path::uuid" */
        $vaultSecretsToDelete = [];
        /** @var array<string, true> $failedVaultSecretKeys "path::uuid" keys backing a credential that failed */
        $failedVaultSecretKeys = [];

        /**
         * @var CredentialDto $credential
         */
        foreach ($this->credentials as $credential) {
            try {
                $plaintext = $this->fetchPlaintextFromVault($credential->value);

                match ($credential->type) {
                    CredentialTypeEnum::TYPE_HOST,
                    CredentialTypeEnum::TYPE_HOST_TEMPLATE => $this->revertHostAndHostTemplateCredentials(
                        $credential,
                        $plaintext
                    ),
                    CredentialTypeEnum::TYPE_SERVICE => $this->revertServiceCredentials($credential, $plaintext),
                    CredentialTypeEnum::TYPE_POLLER_MACRO => $this->revertPollerMacroPassword($credential, $plaintext),
                    CredentialTypeEnum::TYPE_KNOWLEDGE_BASE_PASSWORD => $this->revertKnowledgeBasePassword($plaintext),
                    CredentialTypeEnum::TYPE_OPEN_ID => $this->revertOpenIdCredentials($credential, $plaintext),
                    CredentialTypeEnum::TYPE_BROKER_INPUT_OUTPUT => $this->revertBrokerInputOutputPassword(
                        $credential,
                        $plaintext
                    ),
                    CredentialTypeEnum::TYPE_ADDITIONAL_CONNECTOR_CONFIGURATION => $this->revertAccPassword(
                        $credential,
                        $plaintext
                    ),
                };

                $uuid = $this->getUuidFromPath($credential->value);
                if ($uuid !== null) {
                    $path = $this->resolveVaultPath($credential->type);
                    $vaultSecretsToDelete[$path . '::' . $uuid] = ['path' => $path, 'uuid' => $uuid];
                }

                $status = new CredentialRecordedDto();
                $status->uuid = $uuid ?? '';
                $status->resourceId = $credential->resourceId;
                $status->vaultPath = $credential->value;
                $status->type = $credential->type;
                $status->credentialName = $credential->name;

                yield $status;
            } catch (\Throwable $ex) {
                $this->error($ex->getMessage(), ['trace' => (string) $ex]);

                $uuid = $this->getUuidFromPath($credential->value);
                if ($uuid !== null) {
                    $failedVaultSecretKeys[$this->resolveVaultPath($credential->type) . '::' . $uuid] = true;
                }

                $status = new CredentialErrorDto();
                $status->resourceId = $credential->resourceId;
                $status->type = $credential->type;
                $status->credentialName = $credential->name;
                $status->message = $ex->getMessage();

                yield $status;
            }
        }

        // Never delete a secret whose UUID still backs a credential that failed to revert, otherwise the
        // still-referenced database path (e.g. a shared OpenID client id/secret) would become unrecoverable.
        foreach (array_keys($failedVaultSecretKeys) as $key) {
            unset($vaultSecretsToDelete[$key]);
        }

        $this->deleteVaultSecrets($vaultSecretsToDelete);
    }

    public function count(): int
    {
        return count($this->credentials);
    }

    /**
     * Delete the reverted secrets from the vault, one call per unique UUID.
     *
     * Runs after all credentials have been restored so that credentials sharing a UUID are all written back
     * before the entry is removed. A deletion failure is logged and tolerated: the plaintext is already in
     * the database, so the revert has succeeded regardless.
     *
     * @param array<string, array{path: string, uuid: string}> $vaultSecretsToDelete
     */
    private function deleteVaultSecrets(array $vaultSecretsToDelete): void
    {
        foreach ($vaultSecretsToDelete as $secret) {
            try {
                $this->writeVaultRepository->setCustomPath($secret['path']);
                $this->writeVaultRepository->delete($secret['uuid']);
            } catch (\Throwable $ex) {
                $this->error(
                    'Unable to delete secret from vault, continuing',
                    ['uuid' => $secret['uuid'], 'path' => $secret['path'], 'trace' => (string) $ex]
                );
            }
        }
    }

    /**
     * Map a credential type to its vault custom path, matching the migrator's paths.
     */
    private function resolveVaultPath(CredentialTypeEnum $type): string
    {
        return match ($type) {
            CredentialTypeEnum::TYPE_HOST,
            CredentialTypeEnum::TYPE_HOST_TEMPLATE => AbstractVaultRepository::HOST_VAULT_PATH,
            CredentialTypeEnum::TYPE_SERVICE => AbstractVaultRepository::SERVICE_VAULT_PATH,
            CredentialTypeEnum::TYPE_POLLER_MACRO => AbstractVaultRepository::POLLER_MACRO_VAULT_PATH,
            CredentialTypeEnum::TYPE_KNOWLEDGE_BASE_PASSWORD => AbstractVaultRepository::KNOWLEDGE_BASE_PATH,
            CredentialTypeEnum::TYPE_OPEN_ID => AbstractVaultRepository::OPEN_ID_CREDENTIALS_VAULT_PATH,
            CredentialTypeEnum::TYPE_BROKER_INPUT_OUTPUT => AbstractVaultRepository::BROKER_VAULT_PATH,
            CredentialTypeEnum::TYPE_ADDITIONAL_CONNECTOR_CONFIGURATION => AbstractVaultRepository::ACC_VAULT_PATH,
        };
    }

    /**
     * Read the plaintext value referenced by a vault path.
     *
     * @param string $vaultPath the stored `secret::...::<key>` reference
     *
     * @throws \Throwable
     *
     * @return string
     */
    private function fetchPlaintextFromVault(string $vaultPath): string
    {
        $segments = explode('::', $vaultPath);
        $vaultKey = end($segments);

        $credentials = $this->readVaultRepository->findFromPath($vaultPath);
        if (! array_key_exists($vaultKey, $credentials)) {
            throw new \Exception(sprintf("Key '%s' not found in the vault", $vaultKey));
        }

        return $credentials[$vaultKey];
    }

    /**
     * @param CredentialDto $credential
     * @param string $plaintext
     */
    private function revertHostAndHostTemplateCredentials(CredentialDto $credential, string $plaintext): void
    {
        if ($credential->name === VaultConfiguration::HOST_SNMP_COMMUNITY_KEY) {
            if ($credential->type === CredentialTypeEnum::TYPE_HOST) {
                foreach ($this->hosts as $host) {
                    if ($host->getId() === $credential->resourceId) {
                        $host->setSnmpCommunity($plaintext);
                        $this->writeHostRepository->update($host);
                    }
                }
            } else {
                foreach ($this->hostTemplates as $hostTemplate) {
                    if ($hostTemplate->getId() === $credential->resourceId) {
                        $hostTemplate->setSnmpCommunity($plaintext);
                        $this->writeHostTemplateRepository->update($hostTemplate);
                    }
                }
            }
        } else {
            foreach ($this->hostMacros as $hostMacro) {
                if ($hostMacro->getOwnerId() === $credential->resourceId && $hostMacro->getName() === $credential->name) {
                    $hostMacro->setValue($plaintext);
                    $this->writeHostMacroRepository->update($hostMacro);
                }
            }
        }
    }

    /**
     * @param CredentialDto $credential
     * @param string $plaintext
     */
    private function revertServiceCredentials(CredentialDto $credential, string $plaintext): void
    {
        foreach ($this->serviceMacros as $serviceMacro) {
            if ($serviceMacro->getOwnerId() === $credential->resourceId && $serviceMacro->getName() === $credential->name) {
                $serviceMacro->setValue($plaintext);
                $this->writeServiceMacroRepository->update($serviceMacro);
            }
        }
    }

    /**
     * @param CredentialDto $credential
     * @param string $plaintext
     */
    private function revertPollerMacroPassword(CredentialDto $credential, string $plaintext): void
    {
        foreach ($this->pollerMacros as $pollerMacro) {
            if ($pollerMacro->getId() === $credential->resourceId) {
                $pollerMacro->setValue($plaintext);
                $this->writePollerMacroRepository->update($pollerMacro);
            }
        }
    }

    /**
     * @param string $plaintext
     */
    private function revertKnowledgeBasePassword(string $plaintext): void
    {
        $option = new Option('kb_wiki_password', $plaintext);
        $this->writeOptionRepository->update($option);
    }

    /**
     * @param CredentialDto $credential
     * @param string $plaintext
     */
    private function revertOpenIdCredentials(CredentialDto $credential, string $plaintext): void
    {
        /**
         * @var CustomConfiguration $customConfiguration
         */
        $customConfiguration = $this->openIdProviderConfiguration->getCustomConfiguration();
        if ($credential->name === VaultConfiguration::OPENID_CLIENT_ID_KEY) {
            $customConfiguration->setClientId($plaintext);
        } elseif ($credential->name === VaultConfiguration::OPENID_CLIENT_SECRET_KEY) {
            $customConfiguration->setClientSecret($plaintext);
        }

        $this->openIdProviderConfiguration->setCustomConfiguration($customConfiguration);
        $this->writeOpenIdConfigurationRepository->updateConfiguration($this->openIdProviderConfiguration);
    }

    /**
     * @param CredentialDto $credential
     * @param string $plaintext
     *
     * @throws \Throwable
     */
    private function revertBrokerInputOutputPassword(CredentialDto $credential, string $plaintext): void
    {
        if ($credential->resourceId === null) {
            throw new \Exception('Resource ID should not be null');
        }
        $inputOutputs = $this->brokerInputOutputs[$credential->resourceId];
        foreach ($inputOutputs as $inputOutput) {
            $prefix = $inputOutput->getName() . '_';
            if (str_starts_with($credential->name, $prefix)) {
                $credentialNamePart = mb_substr($credential->name, mb_strlen($prefix));
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
                                $params[$paramName][$index]['value'] = $plaintext;
                            }
                        }
                    } elseif ($paramName === $credentialNamePart) {
                        $params[$paramName] = $plaintext;
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
    }

    /**
     * @param CredentialDto $credential
     * @param string $plaintext
     */
    private function revertAccPassword(CredentialDto $credential, string $plaintext): void
    {
        foreach ($this->accs as $index => $acc) {
            if ($acc->getId() === $credential->resourceId) {
                $updatedAcc = $acc;
                foreach ($this->accCredentialReverters as $reverter) {
                    if (! $reverter->isValidFor($updatedAcc->getType())) {
                        continue;
                    }
                    $updatedAcc = $reverter->revertMigratedCredential($updatedAcc, $credential, $plaintext);
                }
                $this->accs[$index] = $updatedAcc;
                $this->writeAccRepository->update($updatedAcc);
            }
        }
    }
}
