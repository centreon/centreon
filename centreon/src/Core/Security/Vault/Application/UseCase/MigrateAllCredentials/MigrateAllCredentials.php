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

declare(strict_types = 1);

namespace Core\Security\Vault\Application\UseCase\MigrateAllCredentials;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
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
use Core\Security\Vault\Application\Exceptions\VaultException;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;

final class MigrateAllCredentials
{
    use LoggerTrait;

    private MigrateAllCredentialsResponse $response;

    /**
     * @param WriteVaultRepositoryInterface $writeVaultRepository
     * @param ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository
     * @param ReadHostRepositoryInterface $readHostRepository
     * @param ReadHostMacroRepositoryInterface $readHostMacroRepository
     * @param ReadHostTemplateRepositoryInterface $readHostTemplateRepository
     * @param ReadServiceMacroRepositoryInterface $readServiceMacroRepository
     * @param ReadOptionRepositoryInterface $readOptionRepository
     * @param ReadPollerMacroRepositoryInterface $readPollerMacroRepository
     * @param WriteHostRepositoryInterface $writeHostRepository
     * @param WriteHostMacroRepositoryInterface $writeHostMacroRepository
     * @param WriteHostTemplateRepositoryInterface $writeHostTemplateRepository
     * @param WriteServiceMacroRepositoryInterface $writeServiceMacroRepository
     * @param WriteOptionRepositoryInterface $writeOptionRepository
     * @param WritePollerMacroRepositoryInterface $writePollerMacroRepository
     */
    public function __construct(
        private readonly WriteVaultRepositoryInterface $writeVaultRepository,
        private readonly ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadHostMacroRepositoryInterface $readHostMacroRepository,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ReadServiceMacroRepositoryInterface $readServiceMacroRepository,
        private readonly ReadOptionRepositoryInterface $readOptionRepository,
        private readonly ReadPollerMacroRepositoryInterface $readPollerMacroRepository,
        private readonly WriteHostRepositoryInterface $writeHostRepository,
        private readonly WriteHostMacroRepositoryInterface $writeHostMacroRepository,
        private readonly WriteHostTemplateRepositoryInterface $writeHostTemplateRepository,
        private readonly WriteServiceMacroRepositoryInterface $writeServiceMacroRepository,
        private readonly WriteOptionRepositoryInterface $writeOptionRepository,
        private readonly WritePollerMacroRepositoryInterface $writePollerMacroRepository,
    ) {
        $this->response = new MigrateAllCredentialsResponse();
    }

    public function __invoke(MigrateAllCredentialsPresenterInterface $presenter): void
    {
        try {
            if ($this->readVaultConfigurationRepository->find() === null) {
                $presenter->presentResponse(new ErrorResponse(VaultException::noVaultConfigured()));

                return;
            }

            $hosts = $this->readHostRepository->findAll();
            $hostTemplates = $this->readHostTemplateRepository->findAll();
            $hostMacros = $this->readHostMacroRepository->findPasswords();
            $serviceMacros = $this->readServiceMacroRepository->findPasswords();
            $knowledgeBasePasswordOption = $this->readOptionRepository->findByName('kb_wiki_password');
            $pollerMacros = $this->readPollerMacroRepository->findPasswords();

            $credentials = $this->createCredentialDtos(
                $hosts,
                $hostTemplates,
                $hostMacros,
                $serviceMacros,
                $pollerMacros,
                $knowledgeBasePasswordOption
            );

            $this->migrateCredentials(
                $credentials,
                $this->response,
                $hosts,
                $hostTemplates,
                $hostMacros,
                $serviceMacros,
                $pollerMacros,
            );
            $presenter->presentResponse($this->response);
        } catch (\Throwable $ex) {
            $this->error((string) $ex);
            $presenter->presentResponse(new ErrorResponse(VaultException::unableToMigrateCredentials()));
        }
    }

    /**
     * @param \Countable&\Traversable<CredentialDto> $credentials
     * @param MigrateAllCredentialsResponse $response
     * @param Host[] $hosts
     * @param HostTemplate[] $hostTemplates
     * @param Macro[] $hostMacros
     * @param Macro[] $serviceMacros
     * @param PollerMacro[] $pollerMacros
     */
    private function migrateCredentials(
        \Traversable&\Countable $credentials,
        MigrateAllCredentialsResponse $response,
        array $hosts,
        array $hostTemplates,
        array $hostMacros,
        array $serviceMacros,
        array $pollerMacros,
    ): void {

        $response->results = new CredentialMigrator(
            $credentials,
            $this->writeVaultRepository,
            $this->writeHostRepository,
            $this->writeHostTemplateRepository,
            $this->writeHostMacroRepository,
            $this->writeServiceMacroRepository,
            $this->writeOptionRepository,
            $this->writePollerMacroRepository,
            $hosts,
            $hostTemplates,
            $hostMacros,
            $serviceMacros,
            $pollerMacros,
        );
    }

    /**
     * @param Host[] $hosts
     * @param HostTemplate[] $hostTemplates
     * @param Macro[] $hostMacros
     * @param Macro[] $serviceMacros
     * @param PollerMacro[] $pollerMacros
     * @param Option|null $knowledgeBasePasswordOption
     *
     * @return \ArrayIterator<int, CredentialDto> $credentials
     *
     */
    private function createCredentialDtos(
        array $hosts,
        array $hostTemplates,
        array $hostMacros,
        array $serviceMacros,
        array $pollerMacros,
        ?Option $knowledgeBasePasswordOption
    ): \ArrayIterator {

        $hostSNMPCommunityCredentialDtos = $this->createHostSNMPCommunityCredentialDtos($hosts);
        $hostTemplateSNMPCommunityCredentialDtos = $this->createHostTemplateSNMPCommunityCredentialDtos($hostTemplates);
        $hostMacroCredentialDtos = $this->createHostMacroCredentialDtos($hostMacros);
        $serviceMacroCredentialDtos = $this->createServiceMacroCredentialDtos($serviceMacros);
        $pollerMacroCredentialDtos = $this->createPollerMacroCredentialDtos($pollerMacros);
        $knowledgeBasePasswordCredentialDto = $this->createKnowledgeBasePasswordCredentialDto(
            $knowledgeBasePasswordOption
        );

        return new \ArrayIterator(array_merge(
            $hostSNMPCommunityCredentialDtos,
            $hostTemplateSNMPCommunityCredentialDtos,
            $hostMacroCredentialDtos,
            $serviceMacroCredentialDtos,
            $pollerMacroCredentialDtos,
            $knowledgeBasePasswordCredentialDto
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
            $credential = new CredentialDto();
            $credential->resourceId = $host->getId();
            $credential->type = CredentialTypeEnum::TYPE_HOST;
            $credential->name = '_HOSTSNMPCOMMUNITY';
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
            $credential = new CredentialDto();
            $credential->resourceId = $hostTemplate->getId();
            $credential->type = CredentialTypeEnum::TYPE_HOST_TEMPLATE;
            $credential->name = '_HOSTSNMPCOMMUNITY';
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
        if ($knowledgeBasePasswordOption === null) {
            return $credentials;
        }

        $credential = new CredentialDto();
        $credential->type = CredentialTypeEnum::TYPE_KNOWLEDGE_BASE_PASSWORD;
        $credential->name = '_KBPASSWORD';
        $credential->value = $knowledgeBasePasswordOption->getValue();
        $credentials[] = $credential;

        return $credentials;
    }

}
