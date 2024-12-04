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

use Adaptation\Database\Adapter\Dbal\DbalConnectionAdapter;
use Adaptation\Database\Adapter\Dbal\DbalExpressionBuilderAdapter;
use Adaptation\Database\Adapter\Dbal\DbalQueryBuilderAdapter;
use Adaptation\Database\Enum\ConnectionDriver;
use Adaptation\Database\Enum\ParameterType;
use Adaptation\Database\Exception\ConnectionException;
use Adaptation\Database\Model\ConnectionConfig;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;

beforeEach(function () {
    // False connection to the database for the transaction and unbuffered query tests
    $dbConfig = [
        'driver' => 'pdo_mysql',
        'host' => 'localhost',
        'user' => 'root',
        'password' => 'password',
        'port' => 1234,
    ];
    $this->falseConnection = DriverManager::getConnection($dbConfig);
    // Mocking the connection to the database for tests with queries
    $this->dbalConnectionMock = Mockery::mock(Connection::class);
    $this->dbalConnectionAdapterTest = new DbalConnectionAdapter($this->dbalConnectionMock);
});

afterEach(function () {
    Mockery::close();
});

it('test DbalConnectionAdapter : create QueryBuilder', function () {
    $queryBuilder = $this->dbalConnectionAdapterTest->createQueryBuilder();
    expect($queryBuilder)
        ->toBeInstanceOf(DbalQueryBuilderAdapter::class);
});

it('test DbalConnectionAdapter : create ExpressionBuilder', function () {
    $expressionBuilder = $this->dbalConnectionAdapterTest->createExpressionBuilder();
    expect($expressionBuilder)
        ->toBeInstanceOf(DbalExpressionBuilderAdapter::class);
});

it('test DbalConnectionAdapter : get dbal connection', function () {
    $dbalConnection = $this->dbalConnectionAdapterTest->getDbalConnection();
    expect($dbalConnection)
        ->toBeInstanceOf(Connection::class);
});

it('test DbalConnectionAdapter : is connected with connection ', function () {
    $this->dbalConnectionMock
        ->shouldReceive('getServerVersion')
        ->andReturn('8.0.25');
    expect($this->dbalConnectionAdapterTest->isConnected())
        ->toBeTrue();
});

it('test DbalConnectionAdapter : is connected without connection', function () {
    $this->dbalConnectionMock
        ->shouldReceive('getServerVersion')
        ->andReturn('');
    expect($this->dbalConnectionAdapterTest->isConnected())
        ->toBeFalse();
});

it('test DbalConnectionAdapter : isUnbufferedQueryActive at true by default', function () {
    expect($this->dbalConnectionAdapterTest->isUnbufferedQueryActive())
        ->toBeFalse();
});

// -------------------------------- EXECUTE STATEMENT TESTS --------------------------------------

it('test DbalConnectionAdapter : executeStatement with a bad query', function () {
    $query = "INSERT INTO (id, field1)";
    $this->dbalConnectionMock
        ->shouldReceive('executeStatement')
        ->with($query, [], [])
        ->andThrow(Exception::class);
    $this->dbalConnectionAdapterTest->executeStatement($query);
})->throws(ConnectionException::class);

it('test DbalConnectionAdapter : executeStatement without errors with no parameters', function () {
    $query = "INSERT INTO foo (id, field1) VALUES (1,'value1')";
    $this->dbalConnectionMock
        ->shouldReceive('executeStatement')
        ->with($query, [], [])
        ->andReturn(1);
    expect($this->dbalConnectionAdapterTest->executeStatement($query))
        ->toBeInt()
        ->toBe(1);
});

it('test DbalConnectionAdapter : executeStatement without errors with prepared parameters', function () {
    $query = "INSERT INTO foo (id, field1) VALUES (1,':value')";
    $this->dbalConnectionMock
        ->shouldReceive('executeStatement')
        ->with($query, ['value' => 'value1'], [])
        ->andReturn(1);
    expect($this->dbalConnectionAdapterTest->executeStatement($query, ['value' => 'value1']))
        ->toBeInt()
        ->toBe(1);
});

it('test DbalConnectionAdapter : executeStatement without errors with prepared parameters and typing', function () {
    $query = "INSERT INTO foo (id, int, string) VALUES (1,':int',':string')";
    $this->dbalConnectionMock
        ->shouldReceive('executeStatement')
        ->with($query, ['int' => 1, 'string' => 'bar'], ['int' => 1, 'string' => 2])
        ->andReturn(1);
    expect(
        $this->dbalConnectionAdapterTest->executeStatement(
            $query,
            ['int' => 1, 'string' => 'bar'],
            ['int' => ParameterType::INT->value, 'string' => ParameterType::STRING->value]
        )
    )
        ->toBeInt()
        ->toBe(1);
});

// -------------------------------- INSERT TESTS --------------------------------------

it('test DbalConnectionAdapter : insert not start by INSERT INTO', function () {
    $query = "DELETE FROM foo WHERE id = 1";
    $this->dbalConnectionAdapterTest->insert($query);
})->throws(ConnectionException::class);

it('test DbalConnectionAdapter : insert with bad query', function () {
    $query = "INSERT INTO VALUES (1,'value1')";
    $this->dbalConnectionMock
        ->shouldReceive('executeStatement')
        ->with($query, [], [])
        ->andThrow(Exception::class);
    $this->dbalConnectionAdapterTest->insert($query);
})->throws(ConnectionException::class);

it('test DbalConnectionAdapter : insert without errors with no parameters', function () {
    $query = "INSERT INTO foo (id, field1) VALUES (1,'value1')";
    $this->dbalConnectionMock
        ->shouldReceive('executeStatement')
        ->with($query, [], [])
        ->andReturn(1);
    expect($this->dbalConnectionAdapterTest->insert($query))
        ->toBeInt()
        ->toBe(1);
});

it('test DbalConnectionAdapter : insert without errors with prepared parameters', function () {
    $query = "INSERT INTO foo (id, field1) VALUES (1, :field1)";
    $this->dbalConnectionMock
        ->shouldReceive('executeStatement')
        ->with($query, ['field1' => 'value1'], [])
        ->andReturn(1);
    expect($this->dbalConnectionAdapterTest->insert($query, ['field1' => 'value1']))
        ->toBeInt()
        ->toBe(1);
});

it('test DbalConnectionAdapter : insert without errors with prepared parameters and typing', function () {
    $query = "INSERT INTO foo (id, int, string) VALUES (1, :int, :string)";
    $this->dbalConnectionMock
        ->shouldReceive('executeStatement')
        ->with($query, ['int' => 1, 'string' => 'bar'], ['int' => 1, 'string' => 2])
        ->andReturn(1);
    expect(
        $this->dbalConnectionAdapterTest->insert(
            $query,
            ['int' => 1, 'string' => 'bar'],
            ['int' => ParameterType::INT->value, 'string' => ParameterType::STRING->value]
        )
    )
        ->toBeInt()
        ->toBe(1);
});

// -------------------------------- UPDATE TESTS --------------------------------------

it('test DbalConnectionAdapter : update not start by UPDATE ', function () {
    $query = "DELETE FROM foo WHERE id = 1";
    $this->dbalConnectionAdapterTest->update($query);
})->throws(ConnectionException::class);

it('test DbalConnectionAdapter : update with bad query', function () {
    $query = "UPDATE foo SET WHERE id = 1";
    $this->dbalConnectionMock
        ->shouldReceive('executeStatement')
        ->with($query, [], [])
        ->andThrow(Exception::class);
    $this->dbalConnectionAdapterTest->update($query);
})->throws(ConnectionException::class);

it('test DbalConnectionAdapter : update without errors with no parameters', function () {
    $query = "UPDATE foo SET field1 = 'value1' WHERE id = 1";
    $this->dbalConnectionMock
        ->shouldReceive('executeStatement')
        ->with($query, [], [])
        ->andReturn(1);
    expect($this->dbalConnectionAdapterTest->update($query))
        ->toBeInt()
        ->toBe(1);
});

it('test DbalConnectionAdapter : update without errors with prepared parameters', function () {
    $query = "UPDATE foo SET field1 = :field1 WHERE id = 1";
    $this->dbalConnectionMock
        ->shouldReceive('executeStatement')
        ->with($query, ['field1' => 'value1'], [])
        ->andReturn(1);
    expect($this->dbalConnectionAdapterTest->update($query, ['field1' => 'value1']))
        ->toBeInt()
        ->toBe(1);
});

it('test DbalConnectionAdapter : update without errors with prepared parameters and typing', function () {
    $query = "UPDATE foo SET int = :int, string = :string WHERE id = 1";
    $this->dbalConnectionMock
        ->shouldReceive('executeStatement')
        ->with($query, ['int' => 1, 'string' => 'bar'], ['int' => 1, 'string' => 2])
        ->andReturn(1);
    expect(
        $this->dbalConnectionAdapterTest->update(
            $query,
            ['int' => 1, 'string' => 'bar'],
            ['int' => ParameterType::INT->value, 'string' => ParameterType::STRING->value]
        )
    )
        ->toBeInt()
        ->toBe(1);
});

// -------------------------------- DELETE TESTS --------------------------------------

it('test DbalConnectionAdapter : delete not start by DELETE ', function () {
    $query = "UPDATE foo SET WHERE id = 1";
    $this->dbalConnectionAdapterTest->update($query);
})->throws(ConnectionException::class);

it('test DbalConnectionAdapter : delete with bad query', function () {
    $query = "DELETE id = 1";
    $this->dbalConnectionMock
        ->shouldReceive('executeStatement')
        ->with($query, [], [])
        ->andThrow(Exception::class);
    $this->dbalConnectionAdapterTest->update($query);
})->throws(ConnectionException::class);

it('test DbalConnectionAdapter : delete without errors with no parameters', function () {
    $query = "DELETE FROM foo WHERE id = 1";
    $this->dbalConnectionMock
        ->shouldReceive('executeStatement')
        ->with($query, [], [])
        ->andReturn(1);
    expect($this->dbalConnectionAdapterTest->delete($query))
        ->toBeInt()
        ->toBe(1);
});

it('test DbalConnectionAdapter : delete without errors with prepared parameters', function () {
    $query = "DELETE FROM foo WHERE id = :id";
    $this->dbalConnectionMock
        ->shouldReceive('executeStatement')
        ->with($query, ['id' => 1], [])
        ->andReturn(1);
    expect($this->dbalConnectionAdapterTest->delete($query, ['id' => 1]))
        ->toBeInt()
        ->toBe(1);
});

it('test DbalConnectionAdapter : delete without errors with prepared parameters and typing', function () {
    $query = "DELETE FROM foo WHERE int = :int AND string = :string";
    $this->dbalConnectionMock
        ->shouldReceive('executeStatement')
        ->with($query, ['int' => 1, 'string' => 'bar'], ['int' => 1, 'string' => 2])
        ->andReturn(1);
    expect(
        $this->dbalConnectionAdapterTest->delete(
            $query,
            ['int' => 1, 'string' => 'bar'],
            ['int' => ParameterType::INT->value, 'string' => ParameterType::STRING->value]
        )
    )
        ->toBeInt()
        ->toBe(1);
});

// -------------------------------- FETCH ASSOCIATIVE TESTS --------------------------------------

it('test DbalConnectionAdapter : fetchAssociative with bad query', function () {
    $query = "SELECT id = 1";
    $this->dbalConnectionMock
        ->shouldReceive('fetchAssociative')
        ->with($query, [], [])
        ->andThrow(Exception::class);
    $this->dbalConnectionAdapterTest->fetchAssociative($query);
})->throws(ConnectionException::class);

it('test DbalConnectionAdapter : fetchAssociative without errors with no parameters', function () {
    $query = 'SELECT * FROM foo WHERE id = 1';
    $this->dbalConnectionMock
        ->shouldReceive('fetchAssociative')
        ->with($query, [], [])
        ->andReturn(['field1' => 'value1', 'field2' => 'value2']);
    expect($this->dbalConnectionAdapterTest->fetchAssociative($query))
        ->toBeArray()
        ->toBe(['field1' => 'value1', 'field2' => 'value2']);
});

it('test DbalConnectionAdapter : fetchAssociative without errors with prepared parameters', function () {
    $query = 'SELECT * FROM foo WHERE id = :id';
    $this->dbalConnectionMock
        ->shouldReceive('fetchAssociative')
        ->with($query, ['id' => 1], [])
        ->andReturn(['field1' => 'value1', 'field2' => 'value2']);
    expect($this->dbalConnectionAdapterTest->fetchAssociative($query, ['id' => 1]))
        ->toBeArray()
        ->toBe(['field1' => 'value1', 'field2' => 'value2']);
});

it('test DbalConnectionAdapter : fetchAssociative without errors with prepared parameters and typing', function () {
    $query = 'SELECT * FROM foo WHERE int = :int AND string = :string';
    $this->dbalConnectionMock
        ->shouldReceive('fetchAssociative')
        ->with($query, ['int' => 1, 'string' => 'bar'], ['int' => 1, 'string' => 2])
        ->andReturn(['field1' => 'value1', 'field2' => 'value2']);
    expect(
        $this->dbalConnectionAdapterTest->fetchAssociative(
            $query,
            ['int' => 1, 'string' => 'bar'],
            ['int' => ParameterType::INT->value, 'string' => ParameterType::STRING->value]
        )
    )
        ->toBeArray()
        ->toBe(['field1' => 'value1', 'field2' => 'value2']);
});

// -------------------------------- FETCH NUMERIC TESTS --------------------------------------

it('test DbalConnectionAdapter : fetchNumeric with bad query', function () {
    $query = "SELECT id = 1";
    $this->dbalConnectionMock
        ->shouldReceive('fetchNumeric')
        ->with($query, [], [])
        ->andThrow(Exception::class);
    $this->dbalConnectionAdapterTest->fetchNumeric($query);
})->throws(ConnectionException::class);

it('test DbalConnectionAdapter : fetchNumeric without errors with no parameters', function () {
    $query = 'SELECT * FROM foo WHERE id = 1';
    $this->dbalConnectionMock
        ->shouldReceive('fetchNumeric')
        ->with($query, [], [])
        ->andReturn([1 => 'value1', 2 => 'value2']);
    expect($this->dbalConnectionAdapterTest->fetchNumeric($query))
        ->toBeArray()
        ->toBe([1 => 'value1', 2 => 'value2']);
});

it('test DbalConnectionAdapter : fetchNumeric without errors with prepared parameters', function () {
    $query = 'SELECT * FROM foo WHERE id = :id';
    $this->dbalConnectionMock
        ->shouldReceive('fetchNumeric')
        ->with($query, ['id' => 1], [])
        ->andReturn([1 => 'value1', 2 => 'value2']);
    expect($this->dbalConnectionAdapterTest->fetchNumeric($query, ['id' => 1]))
        ->toBeArray()
        ->toBe([1 => 'value1', 2 => 'value2']);
});

it('test DbalConnectionAdapter : fetchNumeric without errors with prepared parameters and typing', function () {
    $query = 'SELECT * FROM foo WHERE int = :int AND string = :string';
    $this->dbalConnectionMock
        ->shouldReceive('fetchNumeric')
        ->with($query, ['int' => 1, 'string' => 'bar'], ['int' => 1, 'string' => 2])
        ->andReturn([1 => 'value1', 2 => 'value2']);
    expect(
        $this->dbalConnectionAdapterTest->fetchNumeric(
            $query,
            ['int' => 1, 'string' => 'bar'],
            ['int' => ParameterType::INT->value, 'string' => ParameterType::STRING->value]
        )
    )
        ->toBeArray()
        ->toBe([1 => 'value1', 2 => 'value2']);
});

// -------------------------------- FETCH ONE TESTS --------------------------------------

it('test DbalConnectionAdapter : fetchOne with bad query', function () {
    $query = "SELECT id = 1";
    $this->dbalConnectionMock
        ->shouldReceive('fetchOne')
        ->with($query, [], [])
        ->andThrow(Exception::class);
    $this->dbalConnectionAdapterTest->fetchOne($query);
})->throws(ConnectionException::class);

it('test DbalConnectionAdapter : fetchOne without errors with no parameters', function () {
    $query = 'SELECT * FROM foo WHERE id = 1';
    $this->dbalConnectionMock
        ->shouldReceive('fetchOne')
        ->with($query, [], [])
        ->andReturn('value1');
    expect($this->dbalConnectionAdapterTest->fetchOne($query))
        ->toBeString()
        ->toBe('value1');
});

it('test DbalConnectionAdapter : fetchOne without errors with prepared parameters', function () {
    $query = 'SELECT * FROM foo WHERE id = :id';
    $this->dbalConnectionMock
        ->shouldReceive('fetchOne')
        ->with($query, ['id' => 1], [])
        ->andReturn('value1');
    expect($this->dbalConnectionAdapterTest->fetchOne($query, ['id' => 1]))
        ->toBeString()
        ->toBe('value1');
});

it('test DbalConnectionAdapter : fetchOne without errors with prepared parameters and typing', function () {
    $query = 'SELECT * FROM foo WHERE int = :int AND string = :string';
    $this->dbalConnectionMock
        ->shouldReceive('fetchOne')
        ->with($query, ['int' => 1, 'string' => 'bar'], ['int' => 1, 'string' => 2])
        ->andReturn('value1');
    expect(
        $this->dbalConnectionAdapterTest->fetchOne(
            $query,
            ['int' => 1, 'string' => 'bar'],
            ['int' => ParameterType::INT->value, 'string' => ParameterType::STRING->value]
        )
    )
        ->toBeString()
        ->toBe('value1');
});

// -------------------------------- FETCH ALL NUMERIC TESTS --------------------------------------

it('test DbalConnectionAdapter : fetchAllNumeric with bad query', function () {
    $query = "SELECT id = 1";
    $this->dbalConnectionMock
        ->shouldReceive('fetchAllNumeric')
        ->with($query, [], [])
        ->andThrow(Exception::class);
    $this->dbalConnectionAdapterTest->fetchAllNumeric($query);
})->throws(ConnectionException::class);

it('test DbalConnectionAdapter : fetchAllNumeric without errors with no parameters', function () {
    $query = 'SELECT * FROM foo WHERE id = 1';
    $this->dbalConnectionMock
        ->shouldReceive('fetchAllNumeric')
        ->with($query, [], [])
        ->andReturn([0 => [1 => 'value1', 2 => 'value2'], 1 => [1 => 'value1', 2 => 'value2']]);
    expect($this->dbalConnectionAdapterTest->fetchAllNumeric($query))
        ->toBeArray()
        ->toBe([0 => [1 => 'value1', 2 => 'value2'], 1 => [1 => 'value1', 2 => 'value2']]);
});

it('test DbalConnectionAdapter : fetchAllNumeric without errors with prepared parameters', function () {
    $query = 'SELECT * FROM foo WHERE id = :id';
    $this->dbalConnectionMock
        ->shouldReceive('fetchAllNumeric')
        ->with($query, ['id' => 1], [])
        ->andReturn([0 => [1 => 'value1', 2 => 'value2'], 1 => [1 => 'value1', 2 => 'value2']]);
    expect($this->dbalConnectionAdapterTest->fetchAllNumeric($query, ['id' => 1]))
        ->toBeArray()
        ->toBe([0 => [1 => 'value1', 2 => 'value2'], 1 => [1 => 'value1', 2 => 'value2']]);
});

it('test DbalConnectionAdapter : fetchAllNumeric without errors with prepared parameters and typing', function () {
    $query = 'SELECT * FROM foo WHERE int = :int AND string = :string';
    $this->dbalConnectionMock
        ->shouldReceive('fetchAllNumeric')
        ->with($query, ['int' => 1, 'string' => 'bar'], ['int' => 1, 'string' => 2])
        ->andReturn([0 => [1 => 'value1', 2 => 'value2'], 1 => [1 => 'value1', 2 => 'value2']]);
    expect(
        $this->dbalConnectionAdapterTest->fetchAllNumeric(
            $query,
            ['int' => 1, 'string' => 'bar'],
            ['int' => ParameterType::INT->value, 'string' => ParameterType::STRING->value]
        )
    )
        ->toBeArray()
        ->toBe([0 => [1 => 'value1', 2 => 'value2'], 1 => [1 => 'value1', 2 => 'value2']]);
});

// -------------------------------- FETCH ALL ASSOCIATIVE TESTS --------------------------------------

it('test DbalConnectionAdapter : fetchAllAssociative with bad query', function () {
    $query = "SELECT id = 1";
    $this->dbalConnectionMock
        ->shouldReceive('fetchAllAssociative')
        ->with($query, [], [])
        ->andThrow(Exception::class);
    $this->dbalConnectionAdapterTest->fetchAllAssociative($query);
})->throws(ConnectionException::class);

it('test DbalConnectionAdapter : fetchAllAssociative without errors with no parameters', function () {
    $query = 'SELECT * FROM foo WHERE id = 1';
    $this->dbalConnectionMock
        ->shouldReceive('fetchAllAssociative')
        ->with($query, [], [])
        ->andReturn(
            [0 => ['field1' => 'value1', 'field2' => 'value2'], 1 => ['field1' => 'value1', 'field2' => 'value2']]
        );
    expect($this->dbalConnectionAdapterTest->fetchAllAssociative($query))
        ->toBeArray()
        ->toBe([0 => ['field1' => 'value1', 'field2' => 'value2'], 1 => ['field1' => 'value1', 'field2' => 'value2']]);
});

it('test DbalConnectionAdapter : fetchAllAssociative without errors with prepared parameters', function () {
    $query = 'SELECT * FROM foo WHERE id = :id';
    $this->dbalConnectionMock
        ->shouldReceive('fetchAllAssociative')
        ->with($query, ['id' => 1], [])
        ->andReturn(
            [0 => ['field1' => 'value1', 'field2' => 'value2'], 1 => ['field1' => 'value1', 'field2' => 'value2']]
        );
    expect($this->dbalConnectionAdapterTest->fetchAllAssociative($query, ['id' => 1]))
        ->toBeArray()
        ->toBe([0 => ['field1' => 'value1', 'field2' => 'value2'], 1 => ['field1' => 'value1', 'field2' => 'value2']]);
});

it('test DbalConnectionAdapter : fetchAllAssociative without errors with prepared parameters and typing', function () {
    $query = 'SELECT * FROM foo WHERE int = :int AND string = :string';
    $this->dbalConnectionMock
        ->shouldReceive('fetchAllAssociative')
        ->with($query, ['int' => 1, 'string' => 'bar'], ['int' => 1, 'string' => 2])
        ->andReturn(
            [0 => ['field1' => 'value1', 'field2' => 'value2'], 1 => ['field1' => 'value1', 'field2' => 'value2']]
        );
    expect(
        $this->dbalConnectionAdapterTest->fetchAllAssociative(
            $query,
            ['int' => 1, 'string' => 'bar'],
            ['int' => ParameterType::INT->value, 'string' => ParameterType::STRING->value]
        )
    )
        ->toBeArray()
        ->toBe([0 => ['field1' => 'value1', 'field2' => 'value2'], 1 => ['field1' => 'value1', 'field2' => 'value2']]);
});

// -------------------------------- FETCH ALL KEY VALUE TESTS --------------------------------------

it('test DbalConnectionAdapter : fetchAllKeyValue with bad query', function () {
    $query = "SELECT id = 1";
    $this->dbalConnectionMock
        ->shouldReceive('fetchAllKeyValue')
        ->with($query, [], [])
        ->andThrow(Exception::class);
    $this->dbalConnectionAdapterTest->fetchAllKeyValue($query);
})->throws(ConnectionException::class);

it('test DbalConnectionAdapter : fetchAllKeyValue without errors with no parameters', function () {
    $query = 'SELECT username, email FROM foo WHERE id = 1';
    $this->dbalConnectionMock
        ->shouldReceive('fetchAllKeyValue')
        ->with($query, [], [])
        ->andReturn([0 => ['john doe' => 'john.doe@example.com'], 1 => ['alan smith' => 'alan.smith@example.com']]);
    expect($this->dbalConnectionAdapterTest->fetchAllKeyValue($query))
        ->toBeArray()
        ->toBe([0 => ['john doe' => 'john.doe@example.com'], 1 => ['alan smith' => 'alan.smith@example.com']]);
});

it('test DbalConnectionAdapter : fetchAllKeyValue without errors with prepared parameters', function () {
    $query = 'SELECT username, email FROM foo WHERE id = :id';
    $this->dbalConnectionMock
        ->shouldReceive('fetchAllKeyValue')
        ->with($query, ['id' => 1], [])
        ->andReturn([0 => ['john doe' => 'john.doe@example.com'], 1 => ['alan smith' => 'alan.smith@example.com']]);
    expect($this->dbalConnectionAdapterTest->fetchAllKeyValue($query, ['id' => 1]))
        ->toBeArray()
        ->toBe([0 => ['john doe' => 'john.doe@example.com'], 1 => ['alan smith' => 'alan.smith@example.com']]);
});

it('test DbalConnectionAdapter : fetchAllKeyValue without errors with prepared parameters and typing', function () {
    $query = 'SELECT username, email FROM foo WHERE int = :int AND string = :string';
    $this->dbalConnectionMock
        ->shouldReceive('fetchAllKeyValue')
        ->with($query, ['int' => 1, 'string' => 'bar'], ['int' => 1, 'string' => 2])
        ->andReturn([0 => ['john doe' => 'john.doe@example.com'], 1 => ['alan smith' => 'alan.smith@example.com']]);
    expect(
        $this->dbalConnectionAdapterTest->fetchAllKeyValue(
            $query,
            ['int' => 1, 'string' => 'bar'],
            ['int' => ParameterType::INT->value, 'string' => ParameterType::STRING->value]
        )
    )
        ->toBeArray()
        ->toBe([0 => ['john doe' => 'john.doe@example.com'], 1 => ['alan smith' => 'alan.smith@example.com']]);
});

// -------------------------------- FETCH ALL ASSOCIATIVE INDEXED TESTS --------------------------------------

it('test DbalConnectionAdapter : fetchAllAssociativeIndexed with bad query', function () {
    $query = "SELECT id = 1";
    $this->dbalConnectionMock
        ->shouldReceive('fetchAllAssociativeIndexed')
        ->with($query, [], [])
        ->andThrow(Exception::class);
    $this->dbalConnectionAdapterTest->fetchAllAssociativeIndexed($query);
})->throws(ConnectionException::class);

it('test DbalConnectionAdapter : fetchAllAssociativeIndexed without errors with no parameters', function () {
    $query = 'SELECT id, username, email FROM foo WHERE id = 1';
    $this->dbalConnectionMock
        ->shouldReceive('fetchAllAssociativeIndexed')
        ->with($query, [], [])
        ->andReturn([1 => ['john doe' => 'john.doe@example.com'], 89 => ['alan smith' => 'alan.smith@example.com']]);
    expect($this->dbalConnectionAdapterTest->fetchAllAssociativeIndexed($query))
        ->toBeArray()
        ->toBe([1 => ['john doe' => 'john.doe@example.com'], 89 => ['alan smith' => 'alan.smith@example.com']]);
});

it('test DbalConnectionAdapter : fetchAllAssociativeIndexed without errors with prepared parameters', function () {
    $query = 'SELECT id, username, email FROM foo WHERE id = :id';
    $this->dbalConnectionMock
        ->shouldReceive('fetchAllAssociativeIndexed')
        ->with($query, ['id' => 1], [])
        ->andReturn([1 => ['john doe' => 'john.doe@example.com'], 89 => ['alan smith' => 'alan.smith@example.com']]);
    expect($this->dbalConnectionAdapterTest->fetchAllAssociativeIndexed($query, ['id' => 1]))
        ->toBeArray()
        ->toBe([1 => ['john doe' => 'john.doe@example.com'], 89 => ['alan smith' => 'alan.smith@example.com']]);
});

it(
    'test DbalConnectionAdapter : fetchAllAssociativeIndexed without errors with prepared parameters and typing',
    function () {
        $query = 'SELECT id, username, email FROM foo WHERE int = :int AND string = :string';
        $this->dbalConnectionMock
            ->shouldReceive('fetchAllAssociativeIndexed')
            ->with($query, ['int' => 1, 'string' => 'bar'], ['int' => 1, 'string' => 2])
            ->andReturn([1 => ['john doe' => 'john.doe@example.com'], 89 => ['alan smith' => 'alan.smith@example.com']]
            );
        expect(
            $this->dbalConnectionAdapterTest->fetchAllAssociativeIndexed(
                $query,
                ['int' => 1, 'string' => 'bar'],
                ['int' => ParameterType::INT->value, 'string' => ParameterType::STRING->value]
            )
        )
            ->toBeArray()
            ->toBe([1 => ['john doe' => 'john.doe@example.com'], 89 => ['alan smith' => 'alan.smith@example.com']]);
    }
);

// -------------------------------- FETCH FIRST COLUMN TESTS --------------------------------------

it('test DbalConnectionAdapter : fetchFirstColumn with bad query', function () {
    $query = "SELECT id = 1";
    $this->dbalConnectionMock
        ->shouldReceive('fetchFirstColumn')
        ->with($query, [], [])
        ->andThrow(Exception::class);
    $this->dbalConnectionAdapterTest->fetchFirstColumn($query);
})->throws(ConnectionException::class);

it('test DbalConnectionAdapter : fetchFirstColumn without errors with no parameters', function () {
    $query = 'SELECT username FROM foo WHERE id = 1';
    $this->dbalConnectionMock
        ->shouldReceive('fetchFirstColumn')
        ->with($query, [], [])
        ->andReturn(['john doe', 'alan smith']);
    expect($this->dbalConnectionAdapterTest->fetchFirstColumn($query))
        ->toBeArray()
        ->toBe(['john doe', 'alan smith']);
});

it('test DbalConnectionAdapter : fetchFirstColumn without errors with prepared parameters', function () {
    $query = 'SELECT username FROM foo WHERE id = :id';
    $this->dbalConnectionMock
        ->shouldReceive('fetchFirstColumn')
        ->with($query, ['id' => 1], [])
        ->andReturn(['john doe', 'alan smith']);
    expect($this->dbalConnectionAdapterTest->fetchFirstColumn($query, ['id' => 1]))
        ->toBeArray()
        ->toBe(['john doe', 'alan smith']);
});

it('test DbalConnectionAdapter : fetchFirstColumn without errors with prepared parameters and typing', function () {
    $query = 'SELECT username FROM foo WHERE int = :int AND string = :string';
    $this->dbalConnectionMock
        ->shouldReceive('fetchFirstColumn')
        ->with($query, ['int' => 1, 'string' => 'bar'], ['int' => 1, 'string' => 2])
        ->andReturn(['john doe', 'alan smith']);
    expect(
        $this->dbalConnectionAdapterTest->fetchFirstColumn(
            $query,
            ['int' => 1, 'string' => 'bar'],
            ['int' => ParameterType::INT->value, 'string' => ParameterType::STRING->value]
        )
    )
        ->toBeArray()
        ->toBe(['john doe', 'alan smith']);
});

// -------------------------------- TRANSACTION TESTS --------------------------------------


it('test DbalConnectionAdapter : isAutoCommit at true by default', function () {
    $dbalConnectionAdapterTest = new DbalConnectionAdapter($this->falseConnection);
    expect($dbalConnectionAdapterTest->isAutoCommit())
        ->toBeTrue();
});

it('test DbalConnectionAdapter : setAutoCommit', function () {
    $dbalConnectionAdapterTest = new DbalConnectionAdapter($this->falseConnection);
    $dbalConnectionAdapterTest->setAutoCommit(true);
    expect($dbalConnectionAdapterTest->isAutoCommit())
        ->toBeTrue();
    $dbalConnectionAdapterTest->setAutoCommit(false);
    expect($dbalConnectionAdapterTest->isAutoCommit())
        ->toBeFalse();
});

it('test DbalConnectionAdapter : isTransactionActive at false by default', function () {
    $dbalConnectionAdapterTest = new DbalConnectionAdapter($this->falseConnection);
    expect($dbalConnectionAdapterTest->isTransactionActive())
        ->toBeFalse();
});

it('test DbalConnectionAdapter : startTransaction', function () {
    $platform = $this->createStub(AbstractPlatform::class);
    $platform
        ->method('supportsSavepoints')
        ->willReturn(true);

    $driver = $this->createStub(Driver::class);
    $driver
        ->method('getDatabasePlatform')
        ->willReturn($platform);

    $conn = new Connection([], $driver);
    $dbalConnectionAdapterTest = new DbalConnectionAdapter($conn);
    $dbalConnectionAdapterTest->startTransaction();
    expect($dbalConnectionAdapterTest->isTransactionActive())
        ->toBeTrue();
});

it('test DbalConnectionAdapter : commit with no transaction in progress', function () {
    $dbalConnectionAdapterTest = new DbalConnectionAdapter($this->falseConnection);
    $dbalConnectionAdapterTest->commit();
})->throws(ConnectionException::class);

it('test DbalConnectionAdapter : rollback with no transaction in progress', function () {
    $dbalConnectionAdapterTest = new DbalConnectionAdapter($this->falseConnection);
    $dbalConnectionAdapterTest->rollBack();
})->throws(ConnectionException::class);

// -------------------------------- UNBUFFERED QUERY TESTS --------------------------------------

it('test DbalConnectionAdapter : isUnbufferedQueryActive at false by default', function () {
    $dbalConnectionAdapterTest = new DbalConnectionAdapter($this->falseConnection);
    expect($dbalConnectionAdapterTest->isUnbufferedQueryActive())
        ->toBeFalse();
});

// ============================ TESTS WITH A DATABASE CONNECTION REQUIRED (LOCAL ONLY) ===============================

/**
 * @param string $nameEnvVar
 *
 * @return string|null
 */
function getEnvironmentVariable(string $nameEnvVar): ?string
{
    $envVarValue = getenv($nameEnvVar, true) ?: getenv($nameEnvVar);

    return (is_string($envVarValue) && ! empty($envVarValue)) ? $envVarValue : null;
}

/**
 * @param ConnectionConfig $dbConfig
 *
 * @return bool
 */
function hasDbConnection(ConnectionConfig $dbConfig): bool
{
    try {
        $dbalConnectionAdapterTest = DbalConnectionAdapter::createFromConfig($dbConfig);

        return $dbalConnectionAdapterTest->isConnected();
    } catch (ConnectionException $e) {
        return false;
    }
}

$dbConfigCentreon = null;
$dbHost = getEnvironmentVariable('MYSQL_HOST');
$dbUser = getEnvironmentVariable('MYSQL_USER');
$dbPassword = getEnvironmentVariable('MYSQL_PASSWORD');

if (! is_null($dbHost) && ! is_null($dbUser) && ! is_null($dbPassword)) {
    $dbConfigCentreon = new ConnectionConfig(
        host: $dbHost,
        user: $dbUser,
        password: $dbPassword,
        databaseName: 'centreon',
        driver: ConnectionDriver::DRIVER_MYSQL,
        port: 3306,
    );
}

if (! is_null($dbConfigCentreon) && hasDbConnection($dbConfigCentreon)) {
    it(
        'test DbalConnectionAdapter (with db connection) : is connected',
        function () use ($dbConfigCentreon) {
            $dbalConnectionAdapterTest = DbalConnectionAdapter::createFromConfig($dbConfigCentreon);
            expect($dbalConnectionAdapterTest->isConnected())
                ->toBeTrue();
        }
    );

    it(
        'test DbalConnectionAdapter (with db connection) : get native connection (PDO)',
        function () use ($dbConfigCentreon) {
            $dbalConnectionAdapterTest = DbalConnectionAdapter::createFromConfig($dbConfigCentreon);
            expect($dbalConnectionAdapterTest->getNativeConnection())
                ->toBeInstanceOf(PDO::class);
        }
    );

    it(
        'test DbalConnectionAdapter (with db connection) : get database name',
        function () use ($dbConfigCentreon) {
            $dbalConnectionAdapterTest = DbalConnectionAdapter::createFromConfig($dbConfigCentreon);
            expect($dbalConnectionAdapterTest->getDatabaseName())
                ->toBeString()
                ->toBe('centreon');
        }
    );

    it(
        'test DbalConnectionAdapter (with db connection) : get last inserted id',
        function () use ($dbConfigCentreon) {
            $dbalConnectionAdapterTest = DbalConnectionAdapter::createFromConfig($dbConfigCentreon);
            $nbRow = $dbalConnectionAdapterTest->insert(
                <<<SQL
                INSERT INTO locale(locale_id, locale_short_name, locale_long_name, locale_img)
                    VALUES(99, "fr", "Foo", "foo.png");
                SQL
            );
            expect($nbRow)
                ->toBeInt()
                ->toBe(1)
                ->and($dbalConnectionAdapterTest->getLastInsertId())
                ->toBeString()
                ->toBe('99');
            $nbRow = $dbalConnectionAdapterTest->delete('DELETE FROM locale WHERE locale_id = 99');
            expect($nbRow)
                ->toBeInt()
                ->toBe(1);
        }
    );

    // -------------------------------- UNBUFFERED QUERY TESTS --------------------------------------

    it(
        'test DbalConnectionAdapter (with db connection) : allow unbuffered query for PDO driver',
        function () use ($dbConfigCentreon) {
            $dbalConnectionAdapterTest = DbalConnectionAdapter::createFromConfig($dbConfigCentreon);
            $dbalConnectionAdapterTest->allowUnbufferedQuery();
            expect($dbalConnectionAdapterTest->isUnbufferedQueryActive())
                ->toBeFalse();
        }
    );

    it(
        'test DbalConnectionAdapter (with db connection) : startUnbufferedQuery',
        function () use ($dbConfigCentreon) {
            $dbalConnectionAdapterTest = DbalConnectionAdapter::createFromConfig($dbConfigCentreon);
            $dbalConnectionAdapterTest->startUnbufferedQuery();
            expect($dbalConnectionAdapterTest->isUnbufferedQueryActive())
                ->toBeTrue();
        }
    );

    it(
        'test DbalConnectionAdapter (with db connection) : stop unbuffered query when unbuffered query is not active',
        function () use ($dbConfigCentreon) {
            $dbalConnectionAdapterTest = DbalConnectionAdapter::createFromConfig($dbConfigCentreon);
            $dbalConnectionAdapterTest->stopUnbufferedQuery();
        }
    )->throws(ConnectionException::class);

    it(
        'test DbalConnectionAdapter (with db connection) : stop unbuffered query when unbuffered query is active',
        function () use ($dbConfigCentreon) {
            $dbalConnectionAdapterTest = DbalConnectionAdapter::createFromConfig($dbConfigCentreon);
            $dbalConnectionAdapterTest->startUnbufferedQuery();
            expect($dbalConnectionAdapterTest->isUnbufferedQueryActive())
                ->toBeTrue();
            $dbalConnectionAdapterTest->stopUnbufferedQuery();
            expect($dbalConnectionAdapterTest->isUnbufferedQueryActive())
                ->toBeFalse();
        }
    );
}
