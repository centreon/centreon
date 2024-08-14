<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\AdditionalConnectorConfiguration\Application\Factory;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\NewAcc;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;
use Core\AdditionalConnectorConfiguration\Domain\Model\VmWareV6Parameters;
use Security\Interfaces\EncryptionInterface;

/**
 * @phpstan-import-type _VmWareV6Parameters from VmWareV6Parameters
 */
class AccFactory
{
    public function __construct(private readonly EncryptionInterface $encryption){
    }

    /**
     * @param string $name
     * @param Type $type
     * @param int $createdBy
     * @param array<string,mixed> $parameters
     * @param null|string $description
     *
     * @return NewAcc
     */
    public function createNewAcc(
        string $name,
        Type $type,
        int $createdBy,
        array $parameters,
        ?string $description = null,
    ): NewAcc
    {
        return new NewAcc(
            name: $name,
            type: $type,
            createdBy: $createdBy,
            description: $description,
            parameters: match ($type) {
                Type::VMWARE_V6 => new VmWareV6Parameters($this->encryption, $parameters),
            }
        );
    }

    /**
     * @param int $id
     * @param string $name
     * @param Type $type
     * @param null|int $createdBy
     * @param null|int $updatedBy
     * @param \DateTimeImmutable $createdAt
     * @param \DateTimeImmutable $updatedAt
     * @param array<string,mixed> $parameters
     * @param null|string $description
     *
     * @return Acc
     */
    public function createAcc(
        int $id,
        string $name,
        Type $type,
        ?int $createdBy,
        ?int $updatedBy,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        array $parameters,
        ?string $description = null,
    ): Acc
    {
        return new Acc(
            id: $id,
            name: $name,
            type: $type,
            createdBy: $createdBy,
            updatedBy: $updatedBy,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            description: $description,
            parameters: match ($type->value) {
                Type::VMWARE_V6->value => new VmWareV6Parameters($this->encryption, $parameters),
            }
        );
    }

    /**
     * @param Acc $acc
     * @param string $name
     * @param int $updatedBy
     * @param array<string,mixed> $parameters
     * @param null|string $description
     *
     * @return Acc
     */
    public function updateAcc(
        Acc $acc,
        string $name,
        int $updatedBy,
        array $parameters,
        ?string $description = null,
    ): Acc
    {
        return new Acc(
            id: $acc->getId(),
            name: $name,
            type: $acc->getType(),
            createdBy: $acc->getCreatedBy(),
            updatedBy: $updatedBy,
            createdAt: $acc->getCreatedAt(),
            updatedAt: new \DateTimeImmutable(),
            description: $description,
            parameters: match ($acc->getType()) {
                Type::VMWARE_V6 => VmWareV6Parameters::update($this->encryption, $acc->getParameters(), $parameters),
            }
        );
    }
}
