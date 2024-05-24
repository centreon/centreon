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
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Core\Security\Vault\Application\Repository\WriteVaultConfigurationRepositoryInterface;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Utility\Interfaces\UUIDGeneratorInterface;

final class MigrateAllCredentials
{
    private MigrateAllCredentialsResponse $response;
    public function __construct(
        private readonly UUIDGeneratorInterface $UUIDGenerator,
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
            if (($vaultConfiguration = $this->readVaultConfigurationRepository->find()) === null) {
                $presenter->presentResponse(new ErrorResponse('No Vault configured'));
            }
            $hosts = $this->readHostRepository->findAll();
            $hostTemplates = $this->readHostTemplateRepository->findAll();
            $hostMacros = $this->readHostMacroRepository->findPasswords();
            $serviceMacros = $this->readServiceMacroRepository->findPasswords();
            $credentials = new \ArrayIterator([]);
    //        foreach ($hosts as $host) {
    //            if ($host->getSnmpCommunity() !== '' && ! str_starts_with('secret::', $host->getSnmpCommunity())) {
    //                $credential = new CredentialDto();
    //                $credential->resourceId = $host->getId();
    //                $credential->type = CredentialTypeEnum::TYPE_HOST;
    //                $credential->name = '_HOSTSNMPCOMMUNITY';
    //                $credential->value = $host->getSnmpCommunity();
    //
    //                $credentials[] = $credential;
    //            }
    //        }
    //        foreach ($hostTemplates as $hostTemplate) {
    //            if (
    //                $hostTemplate->getSnmpCommunity() !== ''
    //                && ! str_starts_with('secret::', $host->getSnmpCommunity())
    //            ) {
    //                $credential = new CredentialDto();
    //                $credential->resourceId = $hostTemplate->getId();
    //                $credential->type = CredentialTypeEnum::TYPE_HOST_TEMPLATE;
    //                $credential->name = '_HOSTSNMPCOMMUNITY';
    //                $credential->value = $hostTemplate->getSnmpCommunity();
    //
    //                $credentials[] = $credential;
    //            }
    //        }
    //        foreach ($hostMacros as $macro) {
    //            if (! str_starts_with('secret::', $macro->getValue())) {
    //                $credential = new CredentialDto();
    //                $credential->resourceId = $macro->getOwnerId();
    //                $credential->type = CredentialTypeEnum::TYPE_HOST;
    //                $credential->name = $macro->getName();
    //                $credential->value = $macro->getValue();
    //
    //                $credentials[] = $credential;
    //            }
    //        }
    //        foreach ($serviceMacros as $macro) {
    //            if (! str_starts_with('secret::', $macro->getValue())) {
    //                $credential = new CredentialDto();
    //                $credential->resourceId = $macro->getOwnerId();
    //                $credential->type = CredentialTypeEnum::TYPE_SERVICE;
    //                $credential->name = $macro->getName();
    //                $credential->value = $macro->getValue();
    //
    //                $credentials[] = $credential;
    //            }
    //        }

            $resources = [
                ['data' => $hosts,
                    'type' => CredentialTypeEnum::TYPE_HOST,
                    'name' => '_HOSTSNMPCOMMUNITY',
                    'getter' => 'getSnmpCommunity',
                    'idGetter' => 'getId'
                ],
                ['data' => $hostTemplates,
                    'type' => CredentialTypeEnum::TYPE_HOST_TEMPLATE,
                    'name' => '_HOSTSNMPCOMMUNITY',
                    'getter' => 'getSnmpCommunity',
                    'idGetter' => 'getId'
                ],
                ['data' => $hostMacros,
                    'type' => CredentialTypeEnum::TYPE_HOST,
                    'name' => null,
                    'getter' => 'getValue',
                    'idGetter' => 'getOwnerId'
                ],
                ['data' => $serviceMacros,
                    'type' => CredentialTypeEnum::TYPE_SERVICE,
                    'name' => null,
                    'getter' => 'getValue',
                    'idGetter' => 'getOwnerId'
                ]
            ];

            foreach ($resources as $resource) {
                foreach ($resource['data'] as $item) {
                    $value = $item->{$resource['getter']}();
                    if ($value !== '' && !str_starts_with('secret::', $value)) {
                        $credential = new CredentialDto();
                        $credential->resourceId = $item->{$resource['idGetter']}();
                        $credential->type = $resource['type'];
                        $credential->name = $resource['name'] ?? $item->getName();
                        $credential->value = $value;

                        $credentials[] = $credential;
                    }
                }
            }

            $this->migrateCredentials($credentials, $this->response, $hosts, $hostTemplates, $vaultConfiguration);
            $presenter->presentResponse($this->response);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
        }
    }

    /**
     * @param \Countable&\Traversable<CredentialDto> $credentials
     * @param MigrateAllCredentialsResponse $response
     * @param Host[] $hosts
     * @param HostTemplate[] $hostTemplates
     * @param VaultConfiguration $vaultConfiguration
     */
    public function migrateCredentials(
        \Traversable&\Countable $credentials,
        MigrateAllCredentialsResponse $response,
        array $hosts,
        array $hostTemplates,
        VaultConfiguration $vaultConfiguration
    ): void {

        $response->results = new class(
            $credentials,
            $this->writeVaultRepository,
            $this->writeHostRepository,
            $this->writeHostTemplateRepository,
            $this->writeHostMacroRepository,
            $this->writeServiceMacroRepository,
            $this->UUIDGenerator,
            $hosts,
            $hostTemplates,
            $vaultConfiguration
        ) implements \IteratorAggregate, \Countable {
            /**
             * @param \Countable&\Traversable<CredentialDto> $credentials
             * @param WriteVaultConfigurationRepositoryInterface $writeVaultRepository
             * @param WriteHostRepositoryInterface $writeHostRepository
             * @param WriteHostTemplateRepositoryInterface $writeHostTemplateRepository
             * @param WriteHostMacroRepositoryInterface $writeHostMacroRepository
             * @param WriteServiceMacroRepositoryInterface $writeServiceMacroRepository
             * @param UUIDGeneratorInterface $UUIDGenerator,
             * @param Host[] $hosts,
             * @param HostTemplate[] $hostTemplates,
             * @param VaultConfiguration $vaultConfiguration
             */
            public function __construct(
                private readonly \Traversable&\Countable $credentials,
                private readonly WriteVaultConfigurationRepositoryInterface $writeVaultRepository,
                private readonly WriteHostRepositoryInterface $writeHostRepository,
                private readonly WriteHostTemplateRepositoryInterface $writeHostTemplateRepository,
                private readonly WriteHostMacroRepositoryInterface $writeHostMacroRepository,
                private readonly WriteServiceMacroRepositoryInterface $writeServiceMacroRepository,
                private readonly UUIDGeneratorInterface $UUIDGenerator,
                private readonly array $hosts,
                private readonly array $hostTemplates,
                private readonly VaultConfiguration $vaultConfiguration
            ) {
            }

            public function getIterator(): \Traversable
            {
                $existingUuids = [
                    'hosts' => [],
                    'services' => []
                ];
                /**
                 * @var CredentialDto $credential
                 */
                foreach ($this->credentials as $credential) {
                    try {
                        if (
                            $credential->type === CredentialTypeEnum::TYPE_HOST
                            || $credential->type == CredentialTypeEnum::TYPE_HOST_TEMPLATE
                        ) {
                            $recordInformation = $this->migrateHostAndHostTemplateCredentials(
                                $credential,
                                $existingUuids
                            );
                        } else {
                            $recordInformation = $this->migrateServiceCredentials($credential, $existingUuids);
                        }

                        $status = new CredentialRecordedDto();
                        $status->uuid = $recordInformation['uuid'];
                        $status->resourceId = $credential->resourceId;
                        $status->vaultPath = $recordInformation['path'];
                        $status->type = $credential->type;
                        $status->credentialName = $credential->name;

                        yield $status;
                    } catch (\Throwable $ex) {
                        $status = new CredentialErrorDto();
                        $status->resourceId = $credential->resourceId;
                        $status->type = $credential->type;
                        $status->credentialName = $credential->name;
                        $status->message = $ex->getMessage();

                        yield $status;
                    }
                }
            }

            public function count(): int
            {
                return count($this->credentials);
            }

            private function migrateHostAndHostTemplateCredentials(
                CredentialDto $credential,
                array &$existingUuids
            ): array {
                if ($array_key_exists($credential->resourceId, $existingUuids['hosts'])) {
                    $uuid = $existingUuids['hosts'][$credential->resourceId];
                } else {
                    $uuid = $this->UUIDGenerator->generateV4();
                    $existingUuids['hosts'][$credential->resourceId] = $uuid;
                }

                $this->writeVaultRepository->upsert($uuid, [$credential->name => $credential->value]);
                $vaultPath = 'secret::' . $this->vaultConfiguration->getName() . '::'
                    . $this->vaultConfiguration->getRootPath()
                    . '/data/hosts/' . $uuid;
                if ($credential->name === '_HOSTSNMPCOMMUNITY') {
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
                    $updatedMacro = new Macro($credential->resourceId, $credential->name, $vaultPath);
                    $this->writeHostMacroRepository->update($updatedMacro);
                }

                return [
                    'uuid' => $uuid,
                    'path' => $vaultPath
                ];
            }

            private function migrateServiceCredentials(CredentialDto $credential, &$existingUuids): array
            {
                if ($array_key_exists($credential->resourceId, $existingUuids['services'])) {
                    $uuid = $existingUuids['services'][$credential->resourceId];
                } else {
                    $uuid = $this->UUIDGenerator->generateV4();
                    $existingUuids['services'][$credential->resourceId] = $uuid;
                }
                $this->writeVaultRepository->upsert($uuid, [$credential->name => $credential->value]);
                $vaultPath = 'secret::' . $this->vaultConfiguration->getName() . '::'
                    . $this->vaultConfiguration->getRootPath()
                    . '/data/hosts/' . $uuid;
                $updatedMacro = new Macro($credential->resourceId, $credential->name, $vaultPath);
                $this->writeServiceMacroRepository->update($updatedMacro);

                return [
                    'uuid' => $uuid,
                    'path' => $vaultPath
                ];
            }
        };
    }
}