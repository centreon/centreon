<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Utility;

/**
 * This is *NOT* a SQL Builder.
 * This is a *String Builder* dedicated to SQL queries we named "Concatenator" to be explicit.
 *
 * > See it like a SQL "mutable string".
 * > No more, no less : only string concatenations.
 *
 * The aim is to facilitate string manipulations for SQL queries.
 * *DO NOT USE* for simple queries where a basic string is preferable.
 *
 * This class should very probably not have any evolution at all in the future !
 */
final class SqlConcatenator implements \Stringable
{
    private const SEPARATOR_JOINS = ' ';
    private const SEPARATOR_CONDITIONS = ' AND ';
    private const SEPARATOR_EXPRESSIONS = ', ';
    private const PREFIX_SELECT = 'SELECT ';
    private const PREFIX_FROM = 'FROM ';
    private const PREFIX_WHERE = 'WHERE ';
    private const PREFIX_GROUP_BY = 'GROUP BY ';
    private const PREFIX_HAVING = 'HAVING ';
    private const PREFIX_ORDER_BY = 'ORDER BY ';
    private const PREFIX_LIMIT = 'LIMIT ';

    /** @var bool */
    private bool $withNoCache = false;

    /** @var bool */
    private bool $withCalcFoundRows = false;

    /** @var array<string|\Stringable> */
    private array $select = [];

    /** @var string|\Stringable */
    private string|\Stringable $from = '';

    /** @var array<string|\Stringable> */
    private array $joins = [];

    /** @var array<string|\Stringable> */
    private array $where = [];

    /** @var array<string|\Stringable> */
    private array $groupBy = [];

    /** @var array<string|\Stringable> */
    private array $having = [];

    /** @var array<string|\Stringable> */
    private array $orderBy = [];

    /** @var string|\Stringable */
    private string|\Stringable $limit = '';

    /** @var array<string, array{mixed, int}> */
    private array $bindValues = [];

    /** @var array<string, string> */
    private array $bindArrayReplacements = [];

    public function __toString(): string
    {
        return $this->concatAll();
    }

    /**
     * @param bool $bool
     *
     * @return $this
     */
    public function withCalcFoundRows(bool $bool): self
    {
        $this->withCalcFoundRows = $bool;

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return $this
     */
    public function withNoCache(bool $bool): self
    {
        $this->withNoCache = $bool;

        return $this;
    }

    /**
     * @param string|\Stringable ...$expressions
     *
     * @return $this
     */
    public function defineSelect(string|\Stringable ...$expressions): self
    {
        $this->select = $this->trimPrefix(self::PREFIX_SELECT, ...$expressions);

        return $this;
    }

    /**
     * @param string|\Stringable ...$expressions
     *
     * @return $this
     */
    public function appendSelect(string|\Stringable ...$expressions): self
    {
        $this->select = array_merge($this->select, $this->trimPrefix(self::PREFIX_SELECT, ...$expressions));

        return $this;
    }

    /**
     * @param string|\Stringable $table
     *
     * @return $this
     */
    public function defineFrom(string|\Stringable $table): self
    {
        $this->from = $this->trimPrefix(self::PREFIX_FROM, $table)[0];

        return $this;
    }

    /**
     * @param string|\Stringable ...$joins
     *
     * @return $this
     */
    public function defineJoins(string|\Stringable ...$joins): self
    {
        $this->joins = $joins;

        return $this;
    }

    /**
     * @param string|\Stringable ...$joins
     *
     * @return $this
     */
    public function appendJoins(string|\Stringable ...$joins): self
    {
        $this->joins = array_merge($this->joins, $joins);

        return $this;
    }

    /**
     * @param string|\Stringable ...$conditions
     *
     * @return $this
     */
    public function defineWhere(string|\Stringable ...$conditions): self
    {
        $this->where = $this->trimPrefix(self::PREFIX_WHERE, ...$conditions);

        return $this;
    }

    /**
     * @param string|\Stringable ...$conditions
     *
     * @return $this
     */
    public function appendWhere(string|\Stringable ...$conditions): self
    {
        $this->where = array_merge($this->where, $this->trimPrefix(self::PREFIX_WHERE, ...$conditions));

        return $this;
    }

    /**
     * @param string|\Stringable ...$expressions
     *
     * @return $this
     */
    public function defineGroupBy(string|\Stringable ...$expressions): self
    {
        $this->groupBy = $this->trimPrefix(self::PREFIX_GROUP_BY, ...$expressions);

        return $this;
    }

    /**
     * @param string|\Stringable ...$expressions
     *
     * @return $this
     */
    public function appendGroupBy(string|\Stringable ...$expressions): self
    {
        $this->groupBy = array_merge($this->groupBy, $this->trimPrefix(self::PREFIX_GROUP_BY, ...$expressions));

        return $this;
    }

    /**
     * @param string|\Stringable ...$expressions
     *
     * @return $this
     */
    public function defineHaving(string|\Stringable ...$expressions): self
    {
        $this->having = $this->trimPrefix(self::PREFIX_HAVING, ...$expressions);

        return $this;
    }

    /**
     * @param string|\Stringable ...$conditions
     *
     * @return $this
     */
    public function appendHaving(string|\Stringable ...$conditions): self
    {
        $this->having = array_merge($this->having, $this->trimPrefix(self::PREFIX_HAVING, ...$conditions));

        return $this;
    }

    /**
     * @param string|\Stringable ...$expressions
     *
     * @return $this
     */
    public function defineOrderBy(string|\Stringable ...$expressions): self
    {
        $this->orderBy = $this->trimPrefix(self::PREFIX_ORDER_BY, ...$expressions);

        return $this;
    }

    /**
     * @param string|\Stringable ...$expressions
     *
     * @return $this
     */
    public function appendOrderBy(string|\Stringable ...$expressions): self
    {
        $this->orderBy = array_merge($this->orderBy, $this->trimPrefix(self::PREFIX_ORDER_BY, ...$expressions));

        return $this;
    }

    /**
     * @param string|\Stringable $limit
     *
     * @return $this
     */
    public function defineLimit(string|\Stringable $limit): self
    {
        $this->limit = $this->trimPrefix(self::PREFIX_LIMIT, $limit)[0];

        return $this;
    }

    /**
     * This method serve only to store bind values in an easy way close to the sql strings.
     *
     * @param string $param
     * @param mixed $value
     * @param int $type
     *
     * @return $this
     */
    public function storeBindValue(string $param, mixed $value, int $type = \PDO::PARAM_STR): self
    {
        $param = ':' . ltrim($param, ':');
        $this->bindValues[$param] = [$value, $type];

        return $this;
    }

    /**
     * This method serve only to store bind values in an easy way close to the sql strings.
     *
     * @param string $param
     * @param list<int|string> $values
     * @param int $type
     *
     * @return $this
     */
    public function storeBindValueMultiple(string $param, array $values, int $type = \PDO::PARAM_STR): self
    {
        $param = ':' . ltrim($param, ':');
        $names = [];

        for ($i = 1, $nb = \count($values); $i <= $nb; $i++) {
            $names[] = $name = "{$param}_{$i}";
            $this->bindValues[$name] = [$values[$i - 1], $type];
        }

        $this->bindArrayReplacements[$param] = implode(', ', $names);

        return $this;
    }

    /**
     * Empty the bindValues array.
     *
     * @return $this
     */
    public function clearBindValues(): self
    {
        $this->bindValues = [];
        $this->bindArrayReplacements = [];

        return $this;
    }

    /**
     * @return array<string, array{mixed, int}> Format: $param => [$value, $type]
     */
    public function retrieveBindValues(): array
    {
        return $this->bindValues;
    }

    /**
     * Helper method which bind all values stored here into a statement.
     *
     * @param \PDOStatement $statement
     */
    public function bindValuesToStatement(\PDOStatement $statement): void
    {
        foreach ($this->retrieveBindValues() as $param => [$value, $type]) {
            $statement->bindValue($param, $value, $type);
        }
    }

    /**
     * @return string
     */
    public function concatAll(): string
    {
        $sql = rtrim(
            $this->concatSelect()
            . $this->concatFrom()
            . $this->concatJoins()
            . $this->concatWhere()
            . $this->concatGroupBy()
            . $this->concatHaving()
            . $this->concatOrderBy()
            . $this->concatLimit()
        );

        return $this->bindArrayReplacements($sql);
    }

    /**
     * @param string $sql
     *
     * @return string
     */
    private function bindArrayReplacements(string $sql): string
    {
        if ([] === $this->bindArrayReplacements) {
            return $sql;
        }

        $patterns = array_map(
            static fn(string $name): string => '!' . preg_quote($name, '!') . '\b!',
            array_keys($this->bindArrayReplacements)
        );

        return preg_replace($patterns, $this->bindArrayReplacements, $sql);
    }

    /**
     * @return string
     */
    private function concatSelect(): string
    {
        if ([] === $this->select) {
            return '';
        }

        $noCache = $this->withNoCache ? 'SQL_NO_CACHE ' : '';
        $calcFoundRows = $this->withCalcFoundRows ? 'SQL_CALC_FOUND_ROWS ' : '';

        return self::PREFIX_SELECT . $noCache . $calcFoundRows
            . implode(self::SEPARATOR_EXPRESSIONS, $this->select) . ' ';
    }

    /**
     * @return string
     */
    private function concatFrom(): string
    {
        $from = (string) $this->from;

        return '' === $from ? ''
            : self::PREFIX_FROM . $this->from . ' ';
    }

    /**
     * @return string
     */
    private function concatJoins(): string
    {
        return [] === $this->joins ? ''
            : implode(self::SEPARATOR_JOINS, $this->joins) . ' ';
    }

    /**
     * @return string
     */
    private function concatWhere(): string
    {
        return [] === $this->where ? ''
            : self::PREFIX_WHERE . implode(self::SEPARATOR_CONDITIONS, $this->where) . ' ';
    }

    /**
     * @return string
     */
    private function concatGroupBy(): string
    {
        return [] === $this->groupBy ? ''
            : self::PREFIX_GROUP_BY . implode(self::SEPARATOR_EXPRESSIONS, $this->groupBy) . ' ';
    }

    /**
     * @return string
     */
    private function concatHaving(): string
    {
        return [] === $this->having ? ''
            : self::PREFIX_HAVING . implode(self::SEPARATOR_CONDITIONS, $this->having) . ' ';
    }

    /**
     * @return string
     */
    private function concatOrderBy(): string
    {
        return [] === $this->orderBy ? ''
            : self::PREFIX_ORDER_BY . implode(self::SEPARATOR_EXPRESSIONS, $this->orderBy) . ' ';
    }

    /**
     * @return string
     */
    private function concatLimit(): string
    {
        return '' === $this->limit ? ''
            : self::PREFIX_LIMIT . $this->limit;
    }

    /**
     * We remove spaces and prefix in front of the string, and we skip empty string values.
     *
     * @param string $prefix
     * @param string|\Stringable ...$strings
     *
     * @return array<string|\Stringable>
     */
    private function trimPrefix(string $prefix, string|\Stringable ...$strings): array
    {
        $regex = "!^\s*" . preg_quote(trim($prefix), '!') . "\s+!i";

        $sanitized = [];
        foreach ($strings as $string) {
            if ($string instanceof \Stringable) {
                $sanitized[] = $string;
            } elseif ('' !== $string) {
                $sanitized[] = preg_replace($regex, '', $string);
            }
        }

        return $sanitized;
    }
}
