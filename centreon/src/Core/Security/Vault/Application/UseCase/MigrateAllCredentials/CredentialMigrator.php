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
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
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

/**
 * @implements \IteratorAggregate<CredentialRecordedDto|CredentialErrorDto>
 */
class CredentialMigrator implements \IteratorAggregate, \Countable
{
    use LoggerTrait;

    /**
     * @param \Countable&\Traversable<CredentialDto> $credentials
     * @param WriteVaultRepositoryInterface $writeVaultRepository
     * @param WriteHostRepositoryInterface $writeHostRepository
     * @param WriteHostTemplateRepositoryInterface $writeHostTemplateRepository
     * @param WriteHostMacroRepositoryInterface $writeHostMacroRepository
     * @param WriteServiceMacroRepositoryInterface $writeServiceMacroRepository
     * @param Host[] $hosts,
     * @param HostTemplate[] $hostTemplates,
     */
    public function __construct(
        private readonly \Traversable&\Countable $credentials,
        private readonly WriteVaultRepositoryInterface $writeVaultRepository,
        private readonly WriteHostRepositoryInterface $writeHostRepository,
        private readonly WriteHostTemplateRepositoryInterface $writeHostTemplateRepository,
        private readonly WriteHostMacroRepositoryInterface $writeHostMacroRepository,
        private readonly WriteServiceMacroRepositoryInterface $writeServiceMacroRepository,
        private readonly WriteOptionRepositoryInterface $writeOptionRepository,
        private readonly array $hosts,
        private readonly array $hostTemplates,
    ) {
    }

    public function getIterator(): \Traversable
    {
        $existingUuids = [
            'hosts' => [],
            'services' => [],
        ];
        /**
         * @var CredentialDto $credential
         */
        foreach ($this->credentials as $credential) {
            try {

                switch($credential->type) {
                    case CredentialTypeEnum::TYPE_HOST:
                    case CredentialTypeEnum::TYPE_HOST_TEMPLATE:
                        $recordInformation = $this->migrateHostAndHostTemplateCredentials(
                            $credential,
                            $existingUuids
                        );
                        break;
                    case CredentialTypeEnum::TYPE_SERVICE:
                        $recordInformation = $this->migrateServiceAndServiceTemplateCredentials(
                            $credential,
                            $existingUuids
                        );
                        break;
                    case CredentialTypeEnum::TYPE_KNOWLEDGE_BASE_PASSWORD:
                        $recordInformation = $this->migrateKnowledgeBasePassword($credential);
                        break;
                }
                if (
                    $credential->type === CredentialTypeEnum::TYPE_HOST
                    || $credential->type === CredentialTypeEnum::TYPE_HOST_TEMPLATE
                ) {

                } else {

                }

                $status = new CredentialRecordedDto();
                $status->uuid = $recordInformation['uuid'];
                $status->resourceId = $credential->resourceId;
                $status->vaultPath = $recordInformation['path'];
                $status->type = $credential->type;
                $status->credentialName = $credential->name;

                yield $status;
            } catch (\Throwable $ex) {
                $this->error($ex->getMessage(), ['trace' => (string) $ex]);
                dd($ex);
                $status = new CredentialErrorDto();
                $status->resourceId = $credential->resourceId;
                $status->type = $credential->type;
                $status->credentialName = $credential->name;
                $status->message = $ex->getMessage();
                dd($status);
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
     * @param array{hosts: array<string>, services: array<string>} $existingUuids
     *
     * @return array{uuid: string, path: string}
     */
    private function migrateHostAndHostTemplateCredentials(
        CredentialDto $credential,
        array &$existingUuids
    ): array {
        $uuid = null;
        if (array_key_exists($credential->resourceId, $existingUuids['hosts'])) {
            $uuid = $existingUuids['hosts'][$credential->resourceId];
        }
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::HOST_VAULT_PATH);
        $vaultPath = $this->writeVaultRepository->upsert(
            $uuid,
            [
                $credential->name === '_HOSTSNMPCOMMUNITY'
                    ? $credential->name
                    : '_HOST' . $credential->name => $credential->value,
            ]
        );
        $vaultPathPart = explode('/', $vaultPath);
        $existingUuids['hosts'][$credential->resourceId] = end($vaultPathPart);
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
            $updatedMacro->setIsPassword(true);
            $this->writeHostMacroRepository->update($updatedMacro);
        }

        return [
            'uuid' => $existingUuids['hosts'][$credential->resourceId],
            'path' => $vaultPath,
        ];
    }

    /**
     * @param CredentialDto $credential
     * @param array{hosts: array<string>, services: array<string>} $existingUuids
     *
     * @throws \Throwable
     *
     * @return array{uuid: string, path: string}
     */
    private function migrateServiceAndServiceTemplateCredentials(
        CredentialDto $credential,
        array &$existingUuids
    ): array {
        $uuid = null;
        if (array_key_exists($credential->resourceId, $existingUuids['services'])) {
            $uuid = $existingUuids['services'][$credential->resourceId];
        }
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);
        $vaultPath = $this->writeVaultRepository->upsert(
            $uuid,
            ['_SERVICE' . $credential->name => $credential->value]
        );
        $vaultPathPart = explode('/', $vaultPath);
        $existingUuids['services'][$credential->resourceId] = end($vaultPathPart);
        $updatedMacro = new Macro($credential->resourceId, $credential->name, $vaultPath);
        $updatedMacro->setIsPassword(true);
        $this->writeServiceMacroRepository->update($updatedMacro);

        return [
            'uuid' => $existingUuids['services'][$credential->resourceId],
            'path' => $vaultPath,
        ];
    }

    private function migrateKnowledgeBasePassword(CredentialDto $credential): array
    {
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::KNOWLEDGE_BASE_PATH);
        $vaultPath = $this->writeVaultRepository->upsert(
            null,
            [$credential->name => $credential->value]
        );
        $vaultPathPart = explode('/', $vaultPath);
        $uuid = end($vaultPathPart);
        $option = new Option('kb_wiki_password', $vaultPath);
        $this->writeOptionRepository->update($option);
        return [
            'uuid' => $uuid,
            'path' => $vaultPath,
        ];
    }
}