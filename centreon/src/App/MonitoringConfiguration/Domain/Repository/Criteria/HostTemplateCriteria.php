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

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\PaginableCriteria;
use App\Shared\Domain\Repository\PaginableCriteriaTrait;
use App\Shared\Domain\Repository\SortableCriteria;
use App\Shared\Domain\Repository\SortableCriteriaTrait;
use Webmozart\Assert\Assert;

final class HostTemplateCriteria implements PaginableCriteria, SortableCriteria
{
    use PaginableCriteriaTrait;
    use SortableCriteriaTrait;
    use ActorScopedCriteriaTrait;

    private ?HostTemplateId $id = null;

    private ?string $name = null;

    private ?string $nameOrAlias = null;

    // kept by default: the host form's template choices include the locked templates
    private bool $lockedExcluded = false;

    /** @var list<int> */
    private array $excludedIds = [];

    public function withId(HostTemplateId $id): self
    {
        $new = clone $this;
        $new->id = $id;

        return $new;
    }

    public function getId(): ?HostTemplateId
    {
        return $this->id;
    }

    public function withName(string $name): self
    {
        // notEmpty() relies on empty(), which would wrongly reject a legitimate name of "0"
        Assert::stringNotEmpty($name);

        $new = clone $this;
        $new->name = $name;

        return $new;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Matches templates whose name or alias contains the given value.
     */
    public function withNameOrAlias(string $value): self
    {
        // notEmpty() relies on empty(), which would wrongly reject a legitimate value of "0"
        Assert::stringNotEmpty($value);

        $new = clone $this;
        $new->nameOrAlias = $value;

        return $new;
    }

    public function getNameOrAlias(): ?string
    {
        return $this->nameOrAlias;
    }

    public function withoutLocked(): self
    {
        $new = clone $this;
        $new->lockedExcluded = true;

        return $new;
    }

    public function isLockedExcluded(): bool
    {
        return $this->lockedExcluded;
    }

    /**
     * @param Collection<HostTemplateId> $ids
     */
    public function withExcludedIds(Collection $ids): self
    {
        $new = clone $this;
        $new->excludedIds = array_values(array_map(static fn (HostTemplateId $id): int => $id->value, $ids->toArray()));

        return $new;
    }

    /**
     * @return list<int>
     */
    public function getExcludedIds(): array
    {
        return $this->excludedIds;
    }

    /**
     * Sortable fields exposed by the API, mapped to their `host` table column.
     *
     * @return array<string, string>
     */
    public function getFieldMapping(): array
    {
        return [
            'name' => 'host_name',
            'alias' => 'host_alias',
        ];
    }
}
