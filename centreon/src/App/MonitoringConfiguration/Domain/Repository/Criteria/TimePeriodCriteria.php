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

use App\Shared\Domain\Repository\PaginableCriteria;
use App\Shared\Domain\Repository\PaginableCriteriaTrait;
use Webmozart\Assert\Assert;

final class TimePeriodCriteria implements PaginableCriteria
{
    use PaginableCriteriaTrait;
    public const string OPERATOR_LIKE = 'lk';
    public const array ALLOWED_OPERATORS = [self::OPERATOR_LIKE];

    /** @var array<self::OPERATOR_*, list<string>> */
    private array $names = [];

    public function withName(string $name, string $operator): self
    {
        // notEmpty() relies on empty(), which would wrongly reject a legitimate name of "0"
        Assert::stringNotEmpty($name);
        Assert::inArray($operator, self::ALLOWED_OPERATORS);

        $names = $this->names[$operator] ?? [];
        $names[] = $name;
        $names = array_values(array_unique($names));

        $new = clone $this;
        $new->names[$operator] = $names;

        return $new;
    }

    /**
     * @return array<self::OPERATOR_*, list<string>>
     */
    public function getNames(): array
    {
        return $this->names;
    }
}
