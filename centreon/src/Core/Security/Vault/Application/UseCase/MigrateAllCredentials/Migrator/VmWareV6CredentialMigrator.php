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

use Core\AdditionalConnector\Domain\Model\AdditionalConnector;
use Core\AdditionalConnector\Domain\Model\Type;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialTypeEnum;

/**
 * @phpstan-import-type _VmWareV6 from \Core\AdditionalConnector\Application\UseCase\AddAdditionalConnector\Validation\VmWareV6DataValidator
 */
class VmWareV6CredentialMigrator implements AccCredentialMigratorInterface
{
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
    public function createCredentialDtos(AdditionalConnector $acc): array
    {
        /** @var _VmWareV6 $parameters */
        $parameters = $acc->getParameters();

        $credentials = [];
        foreach ($parameters['vcenters'] as $vcenter) {
            $credential = new CredentialDto();
            $credential->resourceId = $acc->getId();
            $credential->type = CredentialTypeEnum::TYPE_ADDITIONAL_CONNECTOR_CONFIGURATION;
            $credential->name = $vcenter['name'];
            $credential->value = $vcenter['password'];
            $credentials[] = $credential;
        }

        return $credentials;
    }

    /**
     * @inheritDoc
     */
    public function updateMigratedCredential(
        AdditionalConnector $acc,
        CredentialDto $credential,
        string $vaultPath
    ): AdditionalConnector
    {
        /** @var _VmWareV6 $parameters */
        $parameters = $acc->getParameters();
        foreach ($parameters['vcenters'] as $index => $vcenter) {
            if ($credential->name === $vcenter['name']) {
                $parameters['vcenters'][$index]['password'] = $vaultPath;
            }
        }

        $migratedAcc = new AdditionalConnector(
            id: $acc->getId(),
            name: $acc->getName(),
            type: $acc->getType(),
            createdBy: $acc->getCreatedBy(),
            updatedBy: null,
            createdAt: $acc->getCreatedAt(),
            updatedAt: new \DateTimeImmutable(),
            parameters: $parameters
        );
        $migratedAcc->setDescription($acc->getDescription());

        return $migratedAcc;
    }
}
