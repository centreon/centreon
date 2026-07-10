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
use CentreonLog;
use Core\AdditionalConnectorConfiguration\Application\Repository\ReadAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Application\Repository\WriteAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Broker\Application\Repository\ReadBrokerInputOutputRepositoryInterface;
use Core\Broker\Application\Repository\WriteBrokerInputOutputRepositoryInterface;
use Core\Broker\Domain\Model\BrokerInputOutput;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\UseCase\VaultTrait;
use Core\Common\Application\VaultEligibilityService;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Application\Repository\WriteHostRepositoryInterface;
use Core\Host\Domain\Model\Host;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\Repository\WriteHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteHostMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteServiceMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\Option\Application\Repository\ReadOptionRepositoryInterface;
use Core\Option\Application\Repository\WriteOptionRepositoryInterface;
use Core\Option\Domain\Option;
use Core\PollerMacro\Application\Repository\ReadPollerMacroRepositoryInterface;
use Core\PollerMacro\Application\Repository\WritePollerMacroRepositoryInterface;
use Core\PollerMacro\Domain\Model\PollerMacro;
use Core\Security\ProviderConfiguration\Application\OpenId\Repository\WriteOpenIdConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Application\Repository\ReadConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\OpenId\Model\CustomConfiguration;
use Core\Security\Vault\Application\Exceptions\VaultException;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialTypeEnum;
use Core\Security\Vault\Application\UseCase\RevertAllCredentials\Reverter\AccCredentialReverterInterface;
use Core\Security\Vault\Domain\Model\VaultConfiguration;

final class RevertAllCredentials
{
    use LoggerTrait;
    use VaultTrait;

    private RevertAllCredentialsResponse $response;

    /** @var AccCredentialReverterInterface[] */
    private array $accCredentialReverters = [];

    /**
     * @param ReadVaultRepositoryInterface $readVaultRepository
     * @param VaultEligibilityService $vaultEligibilityService
     * @param ReadHostRepositoryInterface $readHostRepository
     * @param ReadHostMacroRepositoryInterface $readHostMacroRepository
     * @param ReadHostTemplateRepositoryInterface $readHostTemplateRepository
     * @param ReadServiceMacroRepositoryInterface $readServiceMacroRepository
     * @param ReadOptionRepositoryInterface $readOptionRepository
     * @param ReadPollerMacroRepositoryInterface $readPollerMacroRepository
     * @param ReadConfigurationRepositoryInterface $readProviderConfigurationRepository
     * @param WriteHostRepositoryInterface $writeHostRepository
     * @param WriteHostMacroRepositoryInterface $writeHostMacroRepository
     * @param WriteHostTemplateRepositoryInterface $writeHostTemplateRepository
     * @param WriteServiceMacroRepositoryInterface $writeServiceMacroRepository
     * @param WriteOptionRepositoryInterface $writeOptionRepository
     * @param WritePollerMacroRepositoryInterface $writePollerMacroRepository
     * @param WriteOpenIdConfigurationRepositoryInterface $writeOpenIdConfigurationRepository
     * @param ReadBrokerInputOutputRepositoryInterface $readBrokerInputOutputRepository
     * @param WriteBrokerInputOutputRepositoryInterface $writeBrokerInputOutputRepository
     * @param ReadAccRepositoryInterface $readAccRepository
     * @param WriteAccRepositoryInterface $writeAccRepository
     * @param \Traversable<AccCredentialReverterInterface> $accCredentialReverters
     */
    public function __construct(
        private readonly ReadVaultRepositoryInterface $readVaultRepository,
        private readonly VaultEligibilityService $vaultEligibilityService,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadHostMacroRepositoryInterface $readHostMacroRepository,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ReadServiceMacroRepositoryInterface $readServiceMacroRepository,
        private readonly ReadOptionRepositoryInterface $readOptionRepository,
        private readonly ReadPollerMacroRepositoryInterface $readPollerMacroRepository,
        private readonly ReadConfigurationRepositoryInterface $readProviderConfigurationRepository,
        private readonly WriteHostRepositoryInterface $writeHostRepository,
        private readonly WriteHostMacroRepositoryInterface $writeHostMacroRepository,
        private readonly WriteHostTemplateRepositoryInterface $writeHostTemplateRepository,
        private readonly WriteServiceMacroRepositoryInterface $writeServiceMacroRepository,
        private readonly WriteOptionRepositoryInterface $writeOptionRepository,
        private readonly WritePollerMacroRepositoryInterface $writePollerMacroRepository,
        private readonly WriteOpenIdConfigurationRepositoryInterface $writeOpenIdConfigurationRepository,
        private readonly ReadBrokerInputOutputRepositoryInterface $readBrokerInputOutputRepository,
        private readonly WriteBrokerInputOutputRepositoryInterface $writeBrokerInputOutputRepository,
        private readonly ReadAccRepositoryInterface $readAccRepository,
        private readonly WriteAccRepositoryInterface $writeAccRepository,
        \Traversable $accCredentialReverters,
    ) {
        $this->response = new RevertAllCredentialsResponse();
        $this->accCredentialReverters = iterator_to_array($accCredentialReverters);
    }

    public function __invoke(RevertAllCredentialsPresenterInterface $presenter): void
    {
        try {
            if (! $this->vaultEligibilityService->shouldUseVault()) {
                $presenter->presentResponse(new ErrorResponse(VaultException::vaultNotAvailable()));

                return;
            }

            $hosts = $this->readHostRepository->findAll();
            $hostTemplates = $this->readHostTemplateRepository->findAll();
            $hostMacros = $this->readHostMacroRepository->findPasswords();
            $serviceMacros = $this->readServiceMacroRepository->findPasswords();
            $knowledgeBasePasswordOption = $this->readOptionRepository->findByName('kb_wiki_password');
            $pollerMacros = $this->readPollerMacroRepository->findPasswords();
            $openIdConfiguration = $this->readProviderConfigurationRepository->getConfigurationByType(
                Provider::OPENID
            );
            $brokerInputOutputs = $this->vaultEligibilityService->shouldUseVault('vault_broker')
                ? $this->readBrokerInputOutputRepository->findAll()
                : [];
            $accs = $this->vaultEligibilityService->shouldUseVault('vault_gorgone')
                ? $this->readAccRepository->findAll()
                : [];

            $credentials = $this->createCredentialDtos(
                $hosts,
                $hostTemplates,
                $hostMacros,
                $serviceMacros,
                $pollerMacros,
                $knowledgeBasePasswordOption,
                $openIdConfiguration,
                $brokerInputOutputs,
                $accs,
            );

            $this->revertCredentials(
                $credentials,
                $this->response,
                $hosts,
                $hostTemplates,
                $hostMacros,
                $serviceMacros,
                $pollerMacros,
                $openIdConfiguration,
                $brokerInputOutputs,
                $accs,
            );
            $presenter->presentResponse($this->response);
        } catch (\Throwable $e) {
            CentreonLog::create()->error(logTypeId: CentreonLog::TYPE_BUSINESS_LOG, message: $e->getMessage(), exception: $e);
            $presenter->presentResponse(new ErrorResponse(VaultException::unableToRevertCredentials()));
        }
    }

    /**
     * @param \Countable&\Traversable<CredentialDto> $credentials
     * @param RevertAllCredentialsResponse $response
     * @param Host[] $hosts
     * @param HostTemplate[] $hostTemplates
     * @param Macro[] $hostMacros
     * @param Macro[] $serviceMacros
     * @param PollerMacro[] $pollerMacros
     * @param Configuration $openIdConfiguration
     * @param array<int,BrokerInputOutput[]> $brokerInputOutputs
     * @param Acc[] $accs
     */
    private function revertCredentials(
        \Traversable&\Countable $credentials,
        RevertAllCredentialsResponse $response,
        array $hosts,
        array $hostTemplates,
        array $hostMacros,
        array $serviceMacros,
        array $pollerMacros,
        Configuration $openIdConfiguration,
        array $brokerInputOutputs,
        array $accs,
    ): void {
        $response->results = new CredentialReverter(
            $credentials,
            $this->readVaultRepository,
            $this->writeHostRepository,
            $this->writeHostTemplateRepository,
            $this->writeHostMacroRepository,
            $this->writeServiceMacroRepository,
            $this->writeOptionRepository,
            $this->writePollerMacroRepository,
            $this->writeOpenIdConfigurationRepository,
            $this->readBrokerInputOutputRepository,
            $this->writeBrokerInputOutputRepository,
            $this->writeAccRepository,
            $this->accCredentialReverters,
            $hosts,
            $hostTemplates,
            $hostMacros,
            $serviceMacros,
            $pollerMacros,
            $openIdConfiguration,
            $brokerInputOutputs,
            $accs,
        );
    }

    /**
     * @param Host[] $hosts
     * @param HostTemplate[] $hostTemplates
     * @param Macro[] $hostMacros
     * @param Macro[] $serviceMacros
     * @param PollerMacro[] $pollerMacros
     * @param Option|null $knowledgeBasePasswordOption
     * @param Configuration $openIdConfiguration
     * @param array<int,BrokerInputOutput[]> $brokerInputOutputs
     * @param Acc[] $accs
     *
     * @return \ArrayIterator<int, CredentialDto> $credentials
     */
    private function createCredentialDtos(
        array $hosts,
        array $hostTemplates,
        array $hostMacros,
        array $serviceMacros,
        array $pollerMacros,
        ?Option $knowledgeBasePasswordOption,
        Configuration $openIdConfiguration,
        array $brokerInputOutputs,
        array $accs,
    ): \ArrayIterator {
        $hostSNMPCommunityCredentialDtos = $this->createHostSNMPCommunityCredentialDtos($hosts);
        $hostTemplateSNMPCommunityCredentialDtos = $this->createHostTemplateSNMPCommunityCredentialDtos($hostTemplates);
        $hostMacroCredentialDtos = $this->createHostMacroCredentialDtos($hostMacros);
        $serviceMacroCredentialDtos = $this->createServiceMacroCredentialDtos($serviceMacros);
        $pollerMacroCredentialDtos = $this->createPollerMacroCredentialDtos($pollerMacros);
        $knowledgeBasePasswordCredentialDto = $this->createKnowledgeBasePasswordCredentialDto(
            $knowledgeBasePasswordOption
        );
        $openIdConfigurationCredentialDtos = $this->createOpenIdConfigurationCredentialDtos($openIdConfiguration);
        $brokerConfigurationCredentialDtos = $this->createBrokerInputOutputCredentialDtos($brokerInputOutputs);
        $accCredentialDtos = $this->createAccCredentialDtos($accs);

        return new \ArrayIterator(array_merge(
            $hostSNMPCommunityCredentialDtos,
            $hostTemplateSNMPCommunityCredentialDtos,
            $hostMacroCredentialDtos,
            $serviceMacroCredentialDtos,
            $pollerMacroCredentialDtos,
            $knowledgeBasePasswordCredentialDto,
            $openIdConfigurationCredentialDtos,
            $brokerConfigurationCredentialDtos,
            $accCredentialDtos,
        ));
    }

    /**
     * @param Host[] $hosts
     *
     * @return CredentialDto[]
     */
    private function createHostSNMPCommunityCredentialDtos(array $hosts): array
    {
        $credentials = [];
        foreach ($hosts as $host) {
            if (! $this->isAVaultPath($host->getSnmpCommunity())) {
                continue;
            }
            $credential = new CredentialDto();
            $credential->resourceId = $host->getId();
            $credential->type = CredentialTypeEnum::TYPE_HOST;
            $credential->name = VaultConfiguration::HOST_SNMP_COMMUNITY_KEY;
            $credential->value = $host->getSnmpCommunity();
            $credentials[] = $credential;
        }

        return $credentials;
    }

    /**
     * @param HostTemplate[] $hostTemplates
     *
     * @return CredentialDto[]
     */
    private function createHostTemplateSNMPCommunityCredentialDtos(array $hostTemplates): array
    {
        $credentials = [];
        foreach ($hostTemplates as $hostTemplate) {
            if (! $this->isAVaultPath($hostTemplate->getSnmpCommunity())) {
                continue;
            }
            $credential = new CredentialDto();
            $credential->resourceId = $hostTemplate->getId();
            $credential->type = CredentialTypeEnum::TYPE_HOST_TEMPLATE;
            $credential->name = VaultConfiguration::HOST_SNMP_COMMUNITY_KEY;
            $credential->value = $hostTemplate->getSnmpCommunity();
            $credentials[] = $credential;
        }

        return $credentials;
    }

    /**
     * @param Macro[] $hostMacros
     *
     * @return CredentialDto[]
     */
    private function createHostMacroCredentialDtos(array $hostMacros): array
    {
        $credentials = [];
        foreach ($hostMacros as $hostMacro) {
            if (! $this->isAVaultPath($hostMacro->getValue())) {
                continue;
            }
            $credential = new CredentialDto();
            $credential->resourceId = $hostMacro->getOwnerId();
            $credential->type = CredentialTypeEnum::TYPE_HOST;
            $credential->name = $hostMacro->getName();
            $credential->value = $hostMacro->getValue();
            $credentials[] = $credential;
        }

        return $credentials;
    }

    /**
     * @param Macro[] $serviceMacros
     *
     * @return CredentialDto[]
     */
    private function createServiceMacroCredentialDtos(array $serviceMacros): array
    {
        $credentials = [];
        foreach ($serviceMacros as $serviceMacro) {
            if (! $this->isAVaultPath($serviceMacro->getValue())) {
                continue;
            }
            $credential = new CredentialDto();
            $credential->resourceId = $serviceMacro->getOwnerId();
            $credential->type = CredentialTypeEnum::TYPE_SERVICE;
            $credential->name = $serviceMacro->getName();
            $credential->value = $serviceMacro->getValue();
            $credentials[] = $credential;
        }

        return $credentials;
    }

    /**
     * @param PollerMacro[] $pollerMacros
     *
     * @return CredentialDto[]
     */
    private function createPollerMacroCredentialDtos(array $pollerMacros): array
    {
        $credentials = [];
        foreach ($pollerMacros as $pollerMacro) {
            if (! $this->isAVaultPath($pollerMacro->getValue())) {
                continue;
            }
            $credential = new CredentialDto();
            $credential->resourceId = $pollerMacro->getId();
            $credential->type = CredentialTypeEnum::TYPE_POLLER_MACRO;
            $credential->name = $pollerMacro->getName();
            $credential->value = $pollerMacro->getValue();
            $credentials[] = $credential;
        }

        return $credentials;
    }

    /**
     * @param Option|null $knowledgeBasePasswordOption
     *
     * @return CredentialDto[]
     */
    private function createKnowledgeBasePasswordCredentialDto(?Option $knowledgeBasePasswordOption): array
    {
        $credentials = [];
        if (
            $knowledgeBasePasswordOption === null
            || $knowledgeBasePasswordOption->getValue() === null
            || ! $this->isAVaultPath($knowledgeBasePasswordOption->getValue())
        ) {
            return $credentials;
        }

        $credential = new CredentialDto();
        $credential->type = CredentialTypeEnum::TYPE_KNOWLEDGE_BASE_PASSWORD;
        $credential->name = VaultConfiguration::KNOWLEDGE_BASE_KEY;
        $credential->value = $knowledgeBasePasswordOption->getValue();
        $credentials[] = $credential;

        return $credentials;
    }

    /**
     * @param Configuration $openIdConfiguration
     *
     * @return CredentialDto[]
     */
    private function createOpenIdConfigurationCredentialDtos(Configuration $openIdConfiguration): array
    {
        $credentials = [];

        /**
         * @var CustomConfiguration $customConfiguration
         */
        $customConfiguration = $openIdConfiguration->getCustomConfiguration();

        if (
            $customConfiguration->getClientId() !== null
            && $this->isAVaultPath($customConfiguration->getClientId())
        ) {
            $credential = new CredentialDto();
            $credential->type = CredentialTypeEnum::TYPE_OPEN_ID;
            $credential->name = VaultConfiguration::OPENID_CLIENT_ID_KEY;
            $credential->value = $customConfiguration->getClientId();
            $credentials[] = $credential;
        }

        if (
            $customConfiguration->getClientSecret() !== null
            && $this->isAVaultPath($customConfiguration->getClientSecret())
        ) {
            $credential = new CredentialDto();
            $credential->type = CredentialTypeEnum::TYPE_OPEN_ID;
            $credential->name = VaultConfiguration::OPENID_CLIENT_SECRET_KEY;
            $credential->value = $customConfiguration->getClientSecret();
            $credentials[] = $credential;
        }

        return $credentials;
    }

    /**
     * @param array<int,BrokerInputOutput[]> $inputOutputs
     *
     * @return CredentialDto[]
     */
    private function createBrokerInputOutputCredentialDtos(array $inputOutputs): array
    {
        $credentials = [];
        $fieldsCache = [];

        foreach ($inputOutputs as $brokerId => $inputOutputConfigs) {
            foreach ($inputOutputConfigs as $config) {
                if (! isset($fieldsCache[$config->getType()->id])) {
                    $fieldsCache[$config->getType()->id]
                        = $this->readBrokerInputOutputRepository->findParametersByType($config->getType()->id);
                }

                $fields = $fieldsCache[$config->getType()->id];
                $params = $config->getParameters();

                foreach ($fields as $fieldName => $field) {
                    if (! isset($params[$fieldName])) {
                        continue;
                    }
                    if (is_array($field)) {
                        if (! is_array($params[$fieldName])) {
                            // for phpstan, should never happen.
                            throw new \Exception('unexpected error');
                        }
                        foreach ($params[$fieldName] as $groupedParams) {
                            if (
                                isset($groupedParams['type'])
                                && $groupedParams['type'] === 'password'
                                && isset($groupedParams['value'])
                                && $this->isAVaultPath((string) $groupedParams['value'])
                            ) {
                                /** @var array{type:string,name:string,value:string|int} $groupedParams */
                                $credential = new CredentialDto();
                                $credential->resourceId = $brokerId;
                                $credential->type = CredentialTypeEnum::TYPE_BROKER_INPUT_OUTPUT;
                                $credential->name = $config->getName() . '_' . $fieldName . '_' . $groupedParams['name'];
                                $credential->value = (string) $groupedParams['value'];
                                $credentials[] = $credential;
                            }
                        }
                    } elseif ($field->getType() === 'password') {
                        /** @var string $value */
                        $value = $params[$fieldName];
                        if (! $this->isAVaultPath($value)) {
                            continue;
                        }

                        $credential = new CredentialDto();
                        $credential->resourceId = $brokerId;
                        $credential->type = CredentialTypeEnum::TYPE_BROKER_INPUT_OUTPUT;
                        $credential->name = $config->getName() . '_' . $fieldName;
                        $credential->value = $value;
                        $credentials[] = $credential;
                    }
                }
            }
        }

        return $credentials;
    }

    /**
     * @param Acc[] $accs
     *
     * @return CredentialDto[]
     */
    private function createAccCredentialDtos(array $accs): array
    {
        $credentials = [];
        foreach ($accs as $acc) {
            $credentialDtos = [];
            foreach ($this->accCredentialReverters as $reverter) {
                if ($reverter->isValidFor($acc->getType())) {
                    $credentialDtos = $reverter->createCredentialDtos($acc);
                }
            }
            $credentials = [...$credentials, ...$credentialDtos];
        }

        return $credentials;
    }
}
