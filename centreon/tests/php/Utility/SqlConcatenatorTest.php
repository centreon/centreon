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

namespace Tests\Utility;

use Utility\SqlConcatenator;

beforeEach(function (): void {
    $this->concat = new SqlConcatenator();

    $this->executePrivateTrimPrefix = static function (string $prefix, string|\Stringable ...$values): array {
        $builder = new SqlConcatenator();
        $reflectionClass = new \ReflectionClass($builder);
        $method = $reflectionClass->getMethod('trimPrefix');
        $method->setAccessible(true);

        return $method->invoke($builder, $prefix, ...$values);
    };

    $this->stringable = static function (string $string): \Stringable {
        return new class ($string) implements \Stringable {
            public function __construct(public string $string)
            {
            }

            public function __toString(): string
            {
                return $this->string;
            }
        };
    };
});

// ---------- string concatenation ----------

it('should concat a string for select', function (): void {
    expect($this->concat->appendSelect('abc', 'def')->concatAll())
        ->toBe('SELECT abc, def');
});

it('should concat a string for from', function (): void {
    expect($this->concat->defineFrom('xyz')->concatAll())
        ->toBe('FROM xyz');
});

it('should concat a string for joins', function (): void {
    expect($this->concat->appendJoins('abc', 'def')->concatAll())
        ->toBe('abc def');
});

it('should concat a string for where', function (): void {
    expect($this->concat->appendWhere('abc', 'def')->concatAll())
        ->toBe('WHERE abc AND def');
});

it('should concat a string for group by', function (): void {
    expect($this->concat->appendGroupBy('abc', 'def')->concatAll())
        ->toBe('GROUP BY abc, def');
});

it('should concat a string for having', function (): void {
    expect($this->concat->appendHaving('abc', 'def')->concatAll())
        ->toBe('HAVING abc AND def');
});

it('should concat a string for order by', function (): void {
    expect($this->concat->appendOrderBy('abc', 'def')->concatAll())
        ->toBe('ORDER BY abc, def');
});

it('should concat a string for limit', function (): void {
    expect($this->concat->defineLimit('xyz')->concatAll())
        ->toBe('LIMIT xyz');
});

// ---------- stringable concatenation ----------

it('should concat a Stringable for select', function (): void {
    expect($this->concat->appendSelect('abc', ($this->stringable)('def'))->concatAll())
        ->toBe('SELECT abc, def');
});

it('should concat a Stringable for from', function (): void {
    expect($this->concat->defineFrom(($this->stringable)('xyz'))->concatAll())
        ->toBe('FROM xyz');
});

it('should concat a Stringable for joins', function (): void {
    expect($this->concat->appendJoins('abc', ($this->stringable)('def'))->concatAll())
        ->toBe('abc def');
});

it('should concat a Stringable for where', function (): void {
    expect($this->concat->appendWhere('abc', ($this->stringable)('def'))->concatAll())
        ->toBe('WHERE abc AND def');
});

it('should concat a Stringable for group by', function (): void {
    expect($this->concat->appendGroupBy('abc', ($this->stringable)('def'))->concatAll())
        ->toBe('GROUP BY abc, def');
});

it('should concat a Stringable for having', function (): void {
    expect($this->concat->appendHaving('abc', ($this->stringable)('def'))->concatAll())
        ->toBe('HAVING abc AND def');
});

it('should concat a Stringable for order by', function (): void {
    expect($this->concat->appendOrderBy('abc', ($this->stringable)('def'))->concatAll())
        ->toBe('ORDER BY abc, def');
});

it('should concat a Stringable for limit', function (): void {
    expect($this->concat->defineLimit(($this->stringable)('xyz'))->concatAll())
        ->toBe('LIMIT xyz');
});

// ---------- other concatenation ----------

it('should concat all together', function (): void {
    expect(
        $this->concat
            ->appendSelect('s1', ($this->stringable)('s2'))
            ->defineFrom('t1')
            ->appendJoins('j1', ($this->stringable)('j2'))
            ->appendWhere('w1', ($this->stringable)('w2'))
            ->appendGroupBy('g1', ($this->stringable)('g2'))
            ->appendHaving('h1', ($this->stringable)('h2'))
            ->appendOrderBy('o1', ($this->stringable)('o2'))
            ->defineLimit('l1')
            ->concatAll()
    )->toBe(
        'SELECT s1, s2 FROM t1 j1 j2 WHERE w1 AND w2 GROUP BY g1, g2 HAVING h1 AND h2 ORDER BY o1, o2 LIMIT l1'
    );
});

// ---------- no Stringable cast before the final concatenation ----------

it('should not cast Stringable to string before the final concatenation for select', function (): void {
    $stringable = ($this->stringable)('init_value_for_stringable');
    $this->concat->appendSelect($stringable);
    $stringable->string = 'updated';

    expect($this->concat->concatAll())->toBe('SELECT updated');
});

it('should not cast Stringable to string before the final concatenation for from', function (): void {
    $stringable = ($this->stringable)('init_value_for_stringable');
    $this->concat->defineFrom($stringable);
    $stringable->string = 'updated';

    expect($this->concat->concatAll())->toBe('FROM updated');
});

it('should not cast Stringable to string before the final concatenation for joins', function (): void {
    $stringable = ($this->stringable)('init_value_for_stringable');
    $this->concat->appendJoins($stringable);
    $stringable->string = 'updated';

    expect($this->concat->concatAll())->toBe('updated');
});

it('should not cast Stringable to string before the final concatenation for where', function (): void {
    $stringable = ($this->stringable)('init_value_for_stringable');
    $this->concat->appendWhere($stringable);
    $stringable->string = 'updated';

    expect($this->concat->concatAll())->toBe('WHERE updated');
});

it('should not cast Stringable to string before the final concatenation for group by', function (): void {
    $stringable = ($this->stringable)('init_value_for_stringable');
    $this->concat->appendGroupBy($stringable);
    $stringable->string = 'updated';

    expect($this->concat->concatAll())->toBe('GROUP BY updated');
});

it('should not cast Stringable to string before the final concatenation for having', function (): void {
    $stringable = ($this->stringable)('init_value_for_stringable');
    $this->concat->appendHaving($stringable);
    $stringable->string = 'updated';

    expect($this->concat->concatAll())->toBe('HAVING updated');
});

it('should not cast Stringable to string before the final concatenation for order by', function (): void {
    $stringable = ($this->stringable)('init_value_for_stringable');
    $this->concat->appendOrderBy($stringable);
    $stringable->string = 'updated';

    expect($this->concat->concatAll())->toBe('ORDER BY updated');
});

it('should not cast Stringable to string before the final concatenation for limit', function (): void {
    $stringable = ($this->stringable)('init_value_for_stringable');
    $this->concat->defineLimit($stringable);
    $stringable->string = 'updated';

    expect($this->concat->concatAll())->toBe('LIMIT updated');
});

// ---------- trim prefix features ----------

it('should trim an already present prefix for select', function (): void {
    expect($this->concat->appendSelect('SELECT abc', 'select def')->concatAll())
        ->toBe('SELECT abc, def');
});

it('should trim an already present prefix for from', function (): void {
    expect($this->concat->defineFrom('FROM xyz')->concatAll())
        ->toBe('FROM xyz');
});

it('should trim an already present prefix for where', function (): void {
    expect($this->concat->appendWhere('WHERE abc', 'where def')->concatAll())
        ->toBe('WHERE abc AND def');
});

it('should trim an already present prefix for group by', function (): void {
    expect($this->concat->appendGroupBy('GROUP BY abc', 'group by def')->concatAll())
        ->toBe('GROUP BY abc, def');
});

it('should trim an already present prefix for having', function (): void {
    expect($this->concat->appendHaving('HAVING abc', 'having def')->concatAll())
        ->toBe('HAVING abc AND def');
});

it('should trim an already present prefix for order by', function (): void {
    expect($this->concat->appendOrderBy('ORDER BY abc', 'order by def')->concatAll())
        ->toBe('ORDER BY abc, def');
});

it('should trim an already present prefix for limit', function (): void {
    expect($this->concat->defineLimit('LIMIT xyz')->concatAll())
        ->toBe('LIMIT xyz');
});

it('should trim a prefix case insensitive', function (): void {
    expect(($this->executePrivateTrimPrefix)('PREFIX', 'pReFix abc'))
        ->toBe(['abc']);
});

it('should trim a prefix with surrounding spaces', function (): void {
    expect(($this->executePrivateTrimPrefix)('PREFIX', '     PREFIX    abc'))
        ->toBe(['abc']);
});

it('should remove empty strings while trimming arguments', function (): void {
    expect(($this->executePrivateTrimPrefix)('PREFIX', 'abc', '', '0', 'def'))
        ->toBe(['abc', '0', 'def']);
});

it('should ignore Stringables while trimming arguments', function (): void {
    expect(($this->executePrivateTrimPrefix)('PREFIX', $stringable = ($this->stringable)('a string')))
        ->toBe([$stringable]);
});

// ---------- select modifiers ----------

it('should add select modifiers when asked', function (): void {
    expect(
        $this->concat
            ->withNoCache(true)
            ->withCalcFoundRows(true)
            ->appendSelect('hello')
            ->concatAll()
    )->toBe(
        'SELECT SQL_NO_CACHE SQL_CALC_FOUND_ROWS hello'
    );
});

// ---------- storing bind values feature ----------

it('should store simple bind values and clear them', function (): void {
    $this->concat
        ->storeBindValue(':defg', '456', \PDO::PARAM_STR)
        ->storeBindValue(':abc', 123, \PDO::PARAM_INT);

    expect($this->concat->retrieveBindValues())
        ->toBe(
            [
                ':defg' => ['456', \PDO::PARAM_STR],
                ':abc' => [123, \PDO::PARAM_INT],
            ]
        );

    $this->concat->clearBindValues();
    expect($this->concat->retrieveBindValues())->toBeArray()->toBeEmpty();
});

it('should store multiple bind values', function (): void {
    $this->concat
        ->storeBindValue(':defg', '456', \PDO::PARAM_STR)
        ->storeBindValueMultiple('array', [7, 6, 5], \PDO::PARAM_INT);

    expect($this->concat->retrieveBindValues())
        ->toBe(
            [
                ':defg' => ['456', \PDO::PARAM_STR],
                ':array_1' => [7, \PDO::PARAM_INT],
                ':array_2' => [6, \PDO::PARAM_INT],
                ':array_3' => [5, \PDO::PARAM_INT],
            ]
        );
});

it('should prefix a colon in bind value names when not set', function (): void {
    $this->concat
        ->storeBindValue('simple_without_colon', 1, \PDO::PARAM_INT)
        ->storeBindValue(':simple_with_colon', 2, \PDO::PARAM_INT)
        ->storeBindValueMultiple('multiple_without_colon', [10, 20], \PDO::PARAM_INT)
        ->storeBindValueMultiple(':multiple_with_colon', [30, 40], \PDO::PARAM_INT);

    expect($this->concat->retrieveBindValues())
        ->toBe(
            [
                ':simple_without_colon' => [1, \PDO::PARAM_INT],
                ':simple_with_colon' => [2, \PDO::PARAM_INT],
                ':multiple_without_colon_1' => [10, \PDO::PARAM_INT],
                ':multiple_without_colon_2' => [20, \PDO::PARAM_INT],
                ':multiple_with_colon_1' => [30, \PDO::PARAM_INT],
                ':multiple_with_colon_2' => [40, \PDO::PARAM_INT],
            ]
        );
});

it('should replace a multiple bind value name at any place in the final concat', function (): void {
    $param = ':array';
    $replaced = ':array_1, :array_2, :array_3';

    $this->concat
        ->storeBindValueMultiple($param, [7, 6, 5], \PDO::PARAM_INT)
        ->appendSelect("This is totaly nonsense but why not ? {$param}")
        ->defineFrom("Remember this is a stupid string concatenator {$param}")
        ->appendJoins($param)
        ->appendWhere("field IN ({$param})")
        ->appendGroupBy($param)
        ->appendOrderBy($param)
        ->appendHaving($param)
        ->defineLimit($param);

    expect($this->concat->concatAll())
        ->toBe(
            "SELECT This is totaly nonsense but why not ? {$replaced}"
            . " FROM Remember this is a stupid string concatenator {$replaced}"
            . " {$replaced}"
            . " WHERE field IN ({$replaced})"
            . " GROUP BY {$replaced}"
            . " HAVING {$replaced}"
            . " ORDER BY {$replaced}"
            . " LIMIT {$replaced}"
        );
});

it('should replace a multiple bind value name taking into account only the word', function (): void {
    $param = ':array';
    $replaced = ':array_1, :array_2, :array_3';

    $this->concat
        ->storeBindValueMultiple($param, [7, 6, 5], \PDO::PARAM_INT)
        ->appendWhere("field IN ({$param}) AND 42 = {$param}_not_replaced");

    expect($this->concat->concatAll())
        ->toBe("WHERE field IN ({$replaced}) AND 42 = {$param}_not_replaced");
});
