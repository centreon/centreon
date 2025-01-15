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

namespace Core\Security\Vault\Application\UseCase\MigrateAllCredentials\Migrator;

use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;
use Core\AdditionalConnectorConfiguration\Domain\Model\VmWareV6\VmWareV6Parameters;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialTypeEnum;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Security\Interfaces\EncryptionInterface;

/**
 * @phpstan-import-type _VmWareV6Parameters from VmWareV6Parameters
 */
class VmWareV6CredentialMigrator implements AccCredentialMigratorInterface
{
    public function __construct(private readonly EncryptionInterface $encryption)
    {}

    /**
     * @inheritDoc
     */
    public function isValidFor(Type $type): bool
    {
        return Type::VMWARE_V6 === $type;
    }

    /**
     * @inheritDoc
     */
    public function createCredentialDtos(Acc $acc): array
    {
        /** @var _VmWareV6Parameters $parameters */
        $parameters = $acc->getParameters()->getDecryptedData();

        $credentials = [];
        foreach ($parameters['vcenters'] as $vcenter) {
            if (! str_starts_with($vcenter['username'], VaultConfiguration::VAULT_PATH_PATTERN)) {
                $credential = new CredentialDto();
                $credential->resourceId = $acc->getId();
                $credential->type = CredentialTypeEnum::TYPE_ADDITIONAL_CONNECTOR_CONFIGURATION;
                $credential->name = $vcenter['name'] . '_username';
                $credential->value = $vcenter['username'];
                $credentials[] = $credential;
            }

            if (! str_starts_with($vcenter['password'], VaultConfiguration::VAULT_PATH_PATTERN)) {
                $credential = new CredentialDto();
                $credential->resourceId = $acc->getId();
                $credential->type = CredentialTypeEnum::TYPE_ADDITIONAL_CONNECTOR_CONFIGURATION;
                $credential->name = $vcenter['name'] . '_password';
                $credential->value = $vcenter['password'];
                $credentials[] = $credential;
            }
        }

        return $credentials;
    }

    /**
     * @inheritDoc
     */
    public function updateMigratedCredential(
        Acc $acc,
        CredentialDto $credential,
        string $vaultPath
    ): Acc
    {
        /** @var _VmWareV6Parameters $parameters */
        $parameters = $acc->getParameters()->getData();
        foreach ($parameters['vcenters'] as $index => $vcenter) {
            if ($credential->name === $vcenter['name'] . '_username') {
                $parameters['vcenters'][$index]['username'] = $vaultPath;
            } elseif ($credential->name === $vcenter['name'] . '_password') {
                $parameters['vcenters'][$index]['password'] = $vaultPath;
            }
        }

       return new Acc(
            id: $acc->getId(),
            name: $acc->getName(),
            type: $acc->getType(),
            createdBy: $acc->getCreatedBy(),
            updatedBy: null,
            createdAt: $acc->getCreatedAt(),
            updatedAt: new \DateTimeImmutable(),
            description: $acc->getDescription(),
            parameters: new VmWareV6Parameters($this->encryption, $parameters)
        );
    }
}
