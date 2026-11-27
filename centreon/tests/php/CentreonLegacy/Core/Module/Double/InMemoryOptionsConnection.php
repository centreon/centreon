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

namespace Tests\CentreonLegacy\Core\Module\Double;

use Adaptation\Database\Connection\Collection\BatchInsertParameters;
use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Model\ConnectionConfig;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Database\ExpressionBuilder\ExpressionBuilderInterface;
use Adaptation\Database\QueryBuilder\QueryBuilderInterface;

/**
 * The options table in memory, as ModuleUpgradeProgressRepository queries it.
 */
final class InMemoryOptionsConnection implements ConnectionInterface
{
    /** @var array<string, string> */
    public array $rows = [];

    /** @var int how many query builders were created: each one opens a connection on a real database */
    public int $queryBuilders = 0;

    /** @var array<string, \Throwable> the failure raised by each statement verb (SELECT, UPDATE, INSERT, DELETE) */
    public array $failures = [];

    public static function createFromConfig(ConnectionConfig $connectionConfig): self
    {
        return new self();
    }

    public function failOn(string $verb, \Throwable $failure): void
    {
        $this->failures[$verb] = $failure;
    }

    public function fetchOne(string $query, ?QueryParameters $queryParameters = null): mixed
    {
        $this->failIfAsked('SELECT');

        return $this->rows[$this->parameter($queryParameters, 'key')] ?? false;
    }

    public function update(string $query, ?QueryParameters $queryParameters = null): int
    {
        $this->failIfAsked('UPDATE');
        $key = $this->parameter($queryParameters, 'key');
        $value = $this->parameter($queryParameters, 'value');
        if (! isset($this->rows[$key])) {
            return 0;
        }

        // As MySQL, an unchanged row is not counted as affected.
        $affectedRows = $this->rows[$key] !== $value ? 1 : 0;
        $this->rows[$key] = $value;

        return $affectedRows;
    }

    public function insert(string $query, ?QueryParameters $queryParameters = null): int
    {
        $this->failIfAsked('INSERT');
        $key = $this->parameter($queryParameters, 'key');
        if (isset($this->rows[$this->parameter($queryParameters, 'existing_key')])) {
            return 0;
        }
        $this->rows[$key] = $this->parameter($queryParameters, 'value');

        return 1;
    }

    public function delete(string $query, ?QueryParameters $queryParameters = null): int
    {
        $this->failIfAsked('DELETE');
        $key = $this->parameter($queryParameters, 'key');
        if (! isset($this->rows[$key])) {
            return 0;
        }
        unset($this->rows[$key]);

        return 1;
    }

    public function createQueryBuilder(): QueryBuilderInterface
    {
        ++$this->queryBuilders;

        return OfflineQueryBuilder::create();
    }

    public function createExpressionBuilder(): ExpressionBuilderInterface
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function getConnectionConfig(): ConnectionConfig
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function getDatabaseName(): ?string
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function getNativeConnection(): mixed
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function getLastInsertId(): string
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function quoteString(string $value): string
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function executeStatement(string $query, ?QueryParameters $queryParameters = null): int
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function batchInsert(string $tableName, array $columns, BatchInsertParameters $batchInsertParameters): int
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function fetchNumeric(string $query, ?QueryParameters $queryParameters = null): false|array
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function fetchAssociative(string $query, ?QueryParameters $queryParameters = null): false|array
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function fetchFirstColumn(string $query, ?QueryParameters $queryParameters = null): array
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function fetchAllNumeric(string $query, ?QueryParameters $queryParameters = null): array
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function fetchAllAssociative(string $query, ?QueryParameters $queryParameters = null): array
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function fetchAllKeyValue(string $query, ?QueryParameters $queryParameters = null): array
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function fetchAllAssociativeIndexed(string $query, ?QueryParameters $queryParameters = null): array
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function iterateNumeric(string $query, ?QueryParameters $queryParameters = null): \Traversable
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function iterateAssociative(string $query, ?QueryParameters $queryParameters = null): \Traversable
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function iterateColumn(string $query, ?QueryParameters $queryParameters = null): \Traversable
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function iterateKeyValue(string $query, ?QueryParameters $queryParameters = null): \Traversable
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function iterateAssociativeIndexed(string $query, ?QueryParameters $queryParameters = null): \Traversable
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function isTransactionActive(): bool
    {
        return false;
    }

    public function startTransaction(): void
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function commitTransaction(): bool
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function rollBackTransaction(): bool
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function allowUnbufferedQuery(): bool
    {
        return false;
    }

    public function startUnbufferedQuery(): void
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function isUnbufferedQueryActive(): bool
    {
        return false;
    }

    public function stopUnbufferedQuery(): void
    {
        throw $this->unsupported(__FUNCTION__);
    }

    public function columnExists(string $dbName, string $tableName, string $columnName): bool
    {
        throw $this->unsupported(__FUNCTION__);
    }

    private function failIfAsked(string $verb): void
    {
        if (isset($this->failures[$verb])) {
            throw $this->failures[$verb];
        }
    }

    private function parameter(?QueryParameters $queryParameters, string $name): string
    {
        foreach ($queryParameters?->getIterator() ?? [] as $queryParameter) {
            if ($queryParameter instanceof QueryParameter && $queryParameter->getName() === $name) {
                return (string) $queryParameter->getValue();
            }
        }

        throw new \LogicException(\sprintf('The "%s" parameter is not bound', $name));
    }

    private function unsupported(string $method): \LogicException
    {
        return new \LogicException(\sprintf('%s() is not used by ModuleUpgradeProgressRepository', $method));
    }
}
