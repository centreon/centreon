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

namespace Core\AdditionalConnectorConfiguration\Application\UseCase\AddAcc;

use Core\AdditionalConnectorConfiguration\Domain\Model\Poller;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;

final class AddAccResponse
{
    /**
     * @param int $id
     * @param Type $type
     * @param string $name
     * @param string|null $description
     * @param null|array{id:int,name:string} $createdBy
     * @param null|array{id:int,name:string} $updatedBy
     * @param \DateTimeImmutable $createdAt
     * @param \DateTimeImmutable $updatedAt
     * @param array<string,mixed> $parameters
     * @param Poller[] $pollers
     */
    public function __construct(
        public int $id = 0,
        public Type $type = Type::VMWARE_V6,
        public string $name = '',
        public ?string $description = null,
        public ?array $createdBy = null,
        public ?array $updatedBy = null,
        public \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        public \DateTimeImmutable $updatedAt = new \DateTimeImmutable(),
        public array $parameters = [],
        public array $pollers = [],
    ) {
    }
}
