<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Tests\Adaptation\Database\ExpressionBuilder\Adapter\Dbal;

use Adaptation\Database\ExpressionBuilder\Adapter\Dbal\DbalExpressionBuilderAdapter;
use Adaptation\Database\ExpressionBuilder\Enum\ComparisonOperatorEnum;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Mockery;

beforeEach(function () {
    $connection = Mockery::mock(Connection::class);
    $dbalExpressionBuilder = new ExpressionBuilder($connection);
    $this->dbalExpressionBuilderAdapterTest = new DbalExpressionBuilderAdapter($dbalExpressionBuilder);
});

it('test with the method comparison', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->comparison('field1', ComparisonOperatorEnum::EQUAL, ':value1');
    expect($expr)->toBeString()->toBe('field1 = :value1');
});

it('test with the method equal', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->equal('field1', ':value1');
    expect($expr)->toBeString()->toBe('field1 = :value1');
});

it('test with the method notEqual', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->notEqual('field1', ':value1');
    expect($expr)->toBeString()->toBe('field1 <> :value1');
});

it('test with the method lowerThan', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->lowerThan('field1', ':value1');
    expect($expr)->toBeString()->toBe('field1 < :value1');
});

it('test with the method lowerThanEqual', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->lowerThanEqual('field1', ':value1');
    expect($expr)->toBeString()->toBe('field1 <= :value1');
});

it('test with the method greaterThan', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->greaterThan('field1', ':value1');
    expect($expr)->toBeString()->toBe('field1 > :value1');
});

it('test with the method greaterThanEqual', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->greaterThanEqual('field1', ':value1');
    expect($expr)->toBeString()->toBe('field1 >= :value1');
});

it('test with the method isNull', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->isNull('field1');
    expect($expr)->toBeString()->toBe('field1 IS NULL');
});

it('test with the method isNotNull', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->isNotNull('field1');
    expect($expr)->toBeString()->toBe('field1 IS NOT NULL');
});

it('test with the method like', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->like('field1', ':value1');
    expect($expr)->toBeString()->toBe('field1 LIKE :value1');
});

it('test with the method like with escape', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->like('field1', ':value1', '$');
    expect($expr)->toBeString()->toBe('field1 LIKE :value1 ESCAPE $');
});

it('test with the method notLike', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->notLike('field1', ':value1');
    expect($expr)->toBeString()->toBe('field1 NOT LIKE :value1');
});

it('test with the method notLike with escape', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->notLike('field1', ':value1', '$');
    expect($expr)->toBeString()->toBe('field1 NOT LIKE :value1 ESCAPE $');
});

it('test with the method in with string', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->in('field1', ':value1');
    expect($expr)->toBeString()->toBe('field1 IN (:value1)');
});

it('test with the method in with array', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->in('field1', [':value1', ':value2']);
    expect($expr)->toBeString()->toBe('field1 IN (:value1, :value2)');
});

it('test with the method notIn with string', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->notIn('field1', ':value1');
    expect($expr)->toBeString()->toBe('field1 NOT IN (:value1)');
});

it('test with the method notIn with array', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->notIn('field1', [':value1', ':value2']);
    expect($expr)->toBeString()->toBe('field1 NOT IN (:value1, :value2)');
});

it('test with the method and', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->and(
        'field1 = :value1',
        'field2 = :value2',
        'field3 = :value3'
    );
    expect($expr)->toBeString()->toBe('(field1 = :value1) AND (field2 = :value2) AND (field3 = :value3)');
});

it('test with the method and with expressions', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->and(
        $this->dbalExpressionBuilderAdapterTest->equal('field1', ':value1'),
        $this->dbalExpressionBuilderAdapterTest->equal('field2', ':value2'),
        $this->dbalExpressionBuilderAdapterTest->equal('field3', ':value3')
    );
    expect($expr)->toBeString()->toBe('(field1 = :value1) AND (field2 = :value2) AND (field3 = :value3)');
});

it('test with the method or', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->or(
        'field1 = :value1',
        'field2 = :value2',
        'field3 = :value3'
    );
    expect($expr)->toBeString()->toBe('(field1 = :value1) OR (field2 = :value2) OR (field3 = :value3)');
});

it('test with the method or with expressions', function () {
    $expr = $this->dbalExpressionBuilderAdapterTest->or(
        $this->dbalExpressionBuilderAdapterTest->equal('field1', ':value1'),
        $this->dbalExpressionBuilderAdapterTest->equal('field2', ':value2'),
        $this->dbalExpressionBuilderAdapterTest->equal('field3', ':value3')
    );
    expect($expr)->toBeString()->toBe('(field1 = :value1) OR (field2 = :value2) OR (field3 = :value3)');
});
