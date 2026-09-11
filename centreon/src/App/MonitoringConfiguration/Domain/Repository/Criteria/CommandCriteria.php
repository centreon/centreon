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

namespace App\MonitoringConfiguration\Domain\Repository\Criteria;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\Shared\Domain\Repository\PaginableCriteria;
use App\Shared\Domain\Repository\PaginableCriteriaTrait;
use App\Shared\Domain\Repository\SortableCriteria;
use App\Shared\Domain\Repository\SortableCriteriaTrait;

final class CommandCriteria implements SortableCriteria, PaginableCriteria
{
    use SortableCriteriaTrait;
    use PaginableCriteriaTrait;
    use OperatorNameFilterTrait;

    /** @var list<CommandTypeEnum> */
    private array $types = [];

    private ?bool $isActivated = null;

    private ?bool $isFromMonitoringConnector = null;

    /** @var list<int> */
    private array $ids = [];

    public function withType(string $type): self
    {
        $type = CommandTypeEnum::fromName($type);

        $types = $this->types;
        $types[] = $type;

        $uniqueNames = array_unique(array_map(fn ($enumType) => $enumType->name, $types));
        $types = array_values(array_map(fn ($name): CommandTypeEnum => CommandTypeEnum::fromName($name), $uniqueNames));

        $new = clone $this;
        $new->types = $types;

        return $new;
    }

    public function withIsActivated(bool $isActivated): self
    {
        $new = clone $this;
        $new->isActivated = $isActivated;

        return $new;
    }

    public function withIsFromMonitoringConnector(bool $isFromMonitoringConnector): self
    {
        $new = clone $this;
        $new->isFromMonitoringConnector = $isFromMonitoringConnector;

        return $new;
    }

    public function withId(int $id): self
    {
        $ids = $this->ids;
        $ids[] = $id;

        $new = clone $this;
        $new->ids = $ids;

        return $new;
    }

    /**
     * @return list<CommandTypeEnum>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function getIsActivated(): ?bool
    {
        return $this->isActivated;
    }

    public function getIsFromMonitoringConnector(): ?bool
    {
        return $this->isFromMonitoringConnector;
    }

    /**
     * @return list<int>
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    public function getFieldMapping(): array
    {
        return [
            'name' => 'command_name',
            'type' => 'command_type',
        ];
    }
}
