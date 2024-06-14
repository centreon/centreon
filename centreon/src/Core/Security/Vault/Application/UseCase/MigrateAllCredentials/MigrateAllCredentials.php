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
     * @param WriteHostRepositoryInterface $writeHostRepository
     * @param WriteHostMacroRepositoryInterface $writeHostMacroRepository
     * @param WriteHostTemplateRepositoryInterface $writeHostTemplateRepository
     * @param WriteServiceMacroRepositoryInterface $writeServiceMacroRepository
     */
    public function __construct(
        private readonly WriteVaultRepositoryInterface $writeVaultRepository,
        private readonly ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadHostMacroRepositoryInterface $readHostMacroRepository,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ReadServiceMacroRepositoryInterface $readServiceMacroRepository,
        private readonly WriteHostRepositoryInterface $writeHostRepository,
        private readonly WriteHostMacroRepositoryInterface $writeHostMacroRepository,
        private readonly WriteHostTemplateRepositoryInterface $writeHostTemplateRepository,
        private readonly WriteServiceMacroRepositoryInterface $writeServiceMacroRepository,
    )
    {
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

            /**
             * @var \ArrayIterator<int, CredentialDto> $credentials
             */
            $credentials = new \ArrayIterator([]);

            $resources = [
                ['data' => $hosts,
                    'type' => CredentialTypeEnum::TYPE_HOST,
                    'name' => '_HOSTSNMPCOMMUNITY',
                    'getter' => 'getSnmpCommunity',
                    'idGetter' => 'getId',
                ],
                ['data' => $hostTemplates,
                    'type' => CredentialTypeEnum::TYPE_HOST_TEMPLATE,
                    'name' => '_HOSTSNMPCOMMUNITY',
                    'getter' => 'getSnmpCommunity',
                    'idGetter' => 'getId',
                ],
                ['data' => $hostMacros,
                    'type' => CredentialTypeEnum::TYPE_HOST,
                    'name' => null,
                    'getter' => 'getValue',
                    'idGetter' => 'getOwnerId',
                ],
                ['data' => $serviceMacros,
                    'type' => CredentialTypeEnum::TYPE_SERVICE,
                    'name' => null,
                    'getter' => 'getValue',
                    'idGetter' => 'getOwnerId',
                ],
            ];
            foreach ($resources as $resource) {
                foreach ($resource['data'] as $item) {
                    $value = $item->{$resource['getter']}();
                    if ($value !== '' && ! str_starts_with($value, 'secret::')) {
                        $credential = new CredentialDto();
                        $credential->resourceId = $item->{$resource['idGetter']}();
                        $credential->type = $resource['type'];
                        $credential->name = $resource['name'] ?? $item->getName();
                        $credential->value = $value;

                        $credentials[] = $credential;
                    }
                }
            }

            $this->migrateCredentials(
                $credentials,
                $this->response,
                $hosts,
                $hostTemplates,
                $hostMacros,
                $serviceMacros
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
     */
    private function migrateCredentials(
        \Traversable&\Countable $credentials,
        MigrateAllCredentialsResponse $response,
        array $hosts,
        array $hostTemplates,
        array $hostMacros,
        array $serviceMacros,
    ): void {

        $response->results = new CredentialMigrator(
            $credentials,
            $this->writeVaultRepository,
            $this->writeHostRepository,
            $this->writeHostTemplateRepository,
            $this->writeHostMacroRepository,
            $this->writeServiceMacroRepository,
            $hosts,
            $hostTemplates,
            $hostMacros,
            $serviceMacros,
        );
    }
}