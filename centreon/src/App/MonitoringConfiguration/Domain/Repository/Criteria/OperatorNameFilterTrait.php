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

use Webmozart\Assert\Assert;

/**
 * Operator-keyed name filtering shared by the listing criteria that accept an
 * "equal" / "like" operator per name value (Command, Connector, GlobalMacro,
 * StandardMacro). Names are grouped by operator and de-duplicated.
 */
trait OperatorNameFilterTrait
{
    public const OPERATOR_EQUAL = 'eq';
    public const OPERATOR_LIKE = 'lk';
    public const ALLOWED_OPERATORS = [self::OPERATOR_EQUAL, self::OPERATOR_LIKE];

    /** @var array<self::OPERATOR_*, list<string>> */
    private array $names = [];

    /**
     * @param self::OPERATOR_* $operator
     */
    public function withName(string $name, string $operator): self
    {
        // stringNotEmpty() rejects only "", unlike notEmpty()/empty() which would
        // wrongly reject a legitimate name of "0".
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
