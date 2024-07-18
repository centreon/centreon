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

namespace Core\Broker\Domain\Model;

class BrokerInputOutputField
{
    /**
     * @param int $id
     * @param string $name
     * @param string $type
     * @param null|int $groupId
     * @param null|string $groupName
     * @param bool $isRequired
     * @param bool $isMultiple
     * @param null|string $listDefault
     * @param string[] $listValues
     */
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly string $type,
        private readonly ?int $groupId,
        private readonly ?string $groupName,
        private readonly bool $isRequired,
        private readonly bool $isMultiple,
        private readonly ?string $listDefault,
        private readonly array $listValues,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getGroupId(): ?int
    {
        return $this->groupId;
    }

    public function getGroupName(): ?string
    {
        return $this->groupName;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function isMultiple(): bool
    {
        return $this->isMultiple;
    }

    public function getListDefault(): ?string
    {
        return $this->listDefault;
    }

    /**
     * @return string[]
     */
    public function getListValues(): array
    {
        return $this->listValues;
    }
}
