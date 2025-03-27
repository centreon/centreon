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

namespace Tests\Adaptation\Database\QueryBuilder\Adapter\Dbal;

use Adaptation\Database\Connection\Model\ConnectionConfig;
use Adaptation\Database\QueryBuilder\Adapter\Dbal\DbalQueryBuilderAdapter;
use Adaptation\Database\QueryBuilder\Exception\QueryBuilderException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\SQL\Builder\DefaultSelectSQLBuilder;
use Doctrine\DBAL\SQL\Builder\DefaultUnionSQLBuilder;
use Mockery;

beforeEach(function () {
    // prepare instanciation of ConnectionConfig with a mock (mandatory)
    $connectionConfig = new ConnectionConfig(
            host: 'fake_host',
            user: 'fake_user',
            password: 'fake_password',
            databaseNameConfiguration: 'fake_databaseName',
            databaseNameRealTime: 'fake_databaseNameStorage'
        );
    // prepare instanciation of DbalQueryBuilderAdapter with mocking of dbal Connection (mandatory)
    $dbalConnection = Mockery::mock(Connection::class);
    $platform = Mockery::mock(AbstractPlatform::class);
    $platform->shouldReceive('getUnionSelectPartSQL')
        ->andReturnArg(0);
    $platform->shouldReceive('getUnionAllSQL')
        ->andReturn('UNION ALL');
    $platform->shouldReceive('getUnionDistinctSQL')
        ->andReturn('UNION');
    $platform->shouldReceive('createSelectSQLBuilder')
        ->andReturn(new DefaultSelectSQLBuilder($platform, null, null));
    $platform->shouldReceive('createUnionSQLBuilder')
        ->andReturn(new DefaultUnionSQLBuilder($platform));
    $dbalConnection->shouldReceive('getDatabasePlatform')
        ->andReturn($platform);
    // instanciation of DbalQueryBuilderAdapter
    $dbalQueryBuilder = new QueryBuilder($dbalConnection);
    $this->dbalQueryBuilderAdapterTest = new DbalQueryBuilderAdapter($dbalQueryBuilder, $connectionConfig);
});

it('expr with error because no database connection', function () {
    $this->dbalQueryBuilderAdapterTest->expr();
})->throws(QueryBuilderException::class);

it('select with one parameter', function () {
    $query = $this->dbalQueryBuilderAdapterTest->select('field1')->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1');
});

it('select with several parameters', function () {
    $query = $this->dbalQueryBuilderAdapterTest->select('field1', 'field2', 'alias.field3')->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1, field2, alias.field3');
});

it('select distinct', function () {
    $query = $this->dbalQueryBuilderAdapterTest->select('field1')->distinct()->getQuery();
    expect($query)->toBeString()->toBe('SELECT DISTINCT field1');
});

it('addSelect with one parameter', function () {
    $query = $this->dbalQueryBuilderAdapterTest->select('field1')->addSelect('field2')->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1, field2');
});

it('addSelect with several parameters', function () {
    $query = $this->dbalQueryBuilderAdapterTest->select('field1')->addSelect('field2', 'field3')->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1, field2, field3');
});

it('delete', function () {
    $query = $this->dbalQueryBuilderAdapterTest->delete('table')->getQuery();
    expect($query)->toBeString()->toBe('DELETE FROM table');
});

it('update', function () {
    $query = $this->dbalQueryBuilderAdapterTest->update('table')->getQuery();
    expect($query)->toBeString()->toBe('UPDATE table SET ');
});

it('update with an alias', function () {
    $query = $this->dbalQueryBuilderAdapterTest->update('table foo')->getQuery();
    expect($query)->toBeString()->toBe('UPDATE table foo SET ');
});

it('update with set one field', function () {
    $query = $this->dbalQueryBuilderAdapterTest->update('table')->set('field1', 'value1')->getQuery();
    expect($query)->toBeString()->toBe('UPDATE table SET field1 = value1');
});

it('update with set several fields', function () {
    $sets = ['field1' => 'value1', 'field2' => 'value2'];
    $this->dbalQueryBuilderAdapterTest->update('table');
    foreach ($sets as $column => $value) {
        $this->dbalQueryBuilderAdapterTest->set($column, $value);
    }
    $query = $this->dbalQueryBuilderAdapterTest->getQuery();
    expect($query)->toBeString()->toBe('UPDATE table SET field1 = value1, field2 = value2');
});

it('insert ', function () {
    $query = $this->dbalQueryBuilderAdapterTest->insert('table')->getQuery();
    expect($query)->toBeString()->toBe('INSERT INTO table () VALUES()');
});

it('insert with fields', function () {
    $query = $this->dbalQueryBuilderAdapterTest->insert('table')->values(
        ['field1' => 'value1', 'field2' => 'value2']
    )->getQuery();
    expect($query)->toBeString()->toBe('INSERT INTO table (field1, field2) VALUES(value1, value2)');
});

it('insert with a field added', function () {
    $query = $this->dbalQueryBuilderAdapterTest->insert('table')->values(
        ['field1' => 'value1', 'field2' => 'value2']
    )->setValue('field3', 'value3')->getQuery();
    expect($query)->toBeString()->toBe('INSERT INTO table (field1, field2, field3) VALUES(value1, value2, value3)');
});

it('insert with a field updated', function () {
    $query = $this->dbalQueryBuilderAdapterTest->insert('table')->values(
        ['field1' => 'value1', 'field2' => 'value2']
    )->setValue('field1', 'value3')->getQuery();
    expect($query)->toBeString()->toBe('INSERT INTO table (field1, field2) VALUES(value3, value2)');
});

it('from ', function () {
    $query = $this->dbalQueryBuilderAdapterTest->select('field1')->from('table')->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table');
});

it('from with an alias', function () {
    $query = $this->dbalQueryBuilderAdapterTest->select('field1')->from('table', 'foo')->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo');
});

it('join ', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->join('foo', 'table2', 'bar', 'foo.id = bar.id')
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo INNER JOIN table2 bar ON foo.id = bar.id');
});

it('inner join', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->innerJoin('foo', 'table2', 'bar', 'foo.id = bar.id')
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo INNER JOIN table2 bar ON foo.id = bar.id');
});

it('left join', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->leftJoin('foo', 'table2', 'bar', 'foo.id = bar.id')
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo LEFT JOIN table2 bar ON foo.id = bar.id');
});

it('right join', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->rightJoin('foo', 'table2', 'bar', 'foo.id = bar.id')
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo RIGHT JOIN table2 bar ON foo.id = bar.id');
});

it('where', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->where('field1 = value1')
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo WHERE field1 = value1');
});

it('andWhere', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->where('field1 = value1')
        ->andWhere('field2 = value2')
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo WHERE (field1 = value1) AND (field2 = value2)');
});

it('orWhere', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->where('field1 = value1')
        ->orWhere('field2 = value2')
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo WHERE (field1 = value1) OR (field2 = value2)');
});

it('groupBy', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->where('field1 = value1')
        ->groupBy('field1')
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo WHERE field1 = value1 GROUP BY field1');
});

it('addGroupBy', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->where('field1 = value1')
        ->groupBy('field1')
        ->addGroupBy('field2')
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo WHERE field1 = value1 GROUP BY field1, field2');
});

it('having', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->groupBy('field1')
        ->having('field1 = value1')
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo GROUP BY field1 HAVING field1 = value1');
});

it('andHaving', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->groupBy('field1')
        ->having('field1 = value1')
        ->andHaving('field2 = value2')
        ->getQuery();
    expect($query)->toBeString()->toBe(
        'SELECT field1 FROM table foo GROUP BY field1 HAVING (field1 = value1) AND (field2 = value2)'
    );
});

it('orHaving', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->groupBy('field1')
        ->having('field1 = value1')
        ->orHaving('field2 = value2')
        ->getQuery();
    expect($query)->toBeString()->toBe(
        'SELECT field1 FROM table foo GROUP BY field1 HAVING (field1 = value1) OR (field2 = value2)'
    );
});

it('orderBy with order', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->orderBy('field1', 'DESC')
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo ORDER BY field1 DESC');
});

it('orderBy without order', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->orderBy('field1')
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo ORDER BY field1');
});

it('addOrderBy with order', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->orderBy('field1', 'DESC')
        ->addOrderBy('field2', 'ASC')
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo ORDER BY field1 DESC, field2 ASC');
});

it('addOrderBy without order', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->orderBy('field1')
        ->addOrderBy('field2')
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo ORDER BY field1, field2');
});

it('limit', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->orderBy('field1')
        ->limit(10)
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo ORDER BY field1 LIMIT 10');
});

it('offset', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->orderBy('field1')
        ->limit(10)
        ->offset(5)
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo ORDER BY field1 LIMIT 10 OFFSET 5');
});

it('resetWhere', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->where('field1 = value1')
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo WHERE field1 = value1');
    $query = $this->dbalQueryBuilderAdapterTest->resetWhere()->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo');
});

it('resetGroupBy', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->where('field1 = value1')
        ->groupBy('field1')
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo WHERE field1 = value1 GROUP BY field1');
    $query = $this->dbalQueryBuilderAdapterTest->resetGroupBy()->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo WHERE field1 = value1');
});

it('resetHaving', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->groupBy('field1')
        ->having('field1 = value1')
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo GROUP BY field1 HAVING field1 = value1');
    $query = $this->dbalQueryBuilderAdapterTest->resetHaving()->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo GROUP BY field1');
});

it('resetOrderBy', function () {
    $query = $this->dbalQueryBuilderAdapterTest
        ->select('field1')
        ->from('table', 'foo')
        ->orderBy('field1')
        ->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo ORDER BY field1');
    $query = $this->dbalQueryBuilderAdapterTest->resetOrderBy()->getQuery();
    expect($query)->toBeString()->toBe('SELECT field1 FROM table foo');
});
