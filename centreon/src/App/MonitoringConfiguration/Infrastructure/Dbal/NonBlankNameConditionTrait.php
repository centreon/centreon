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

namespace App\MonitoringConfiguration\Infrastructure\Dbal;

use Doctrine\DBAL\Query\QueryBuilder;

/**
 * The one SQL condition telling a name is not blank, shared by every query that reads services as the domain
 * sees them, so a name the `ServiceName` value object would reject never reaches it.
 */
trait NonBlankNameConditionTrait
{
    /**
     * Matches a character PHP's `trim()` keeps: anything but a space, `\t`, `\n`, `\r`, `\v` or NUL. Written in
     * the regular-expression syntax, so the backslashes are the pattern's own escapes, not the string's.
     */
    private const NON_BLANK_NAME_PATTERN = '[^ \t\n\r\x{0B}\x{00}]';

    /**
     * Keeps the rows whose `$column` holds at least one character PHP's `trim()` keeps: a NULL, empty or blank
     * name is left out. SQL's `TRIM()` only strips spaces, so it cannot tell.
     */
    private function andWhereNameIsNotBlank(QueryBuilder $qb, string $column): void
    {
        $qb->andWhere(sprintf('%s REGEXP %s', $column, $qb->createNamedParameter(self::NON_BLANK_NAME_PATTERN)));
    }
}
