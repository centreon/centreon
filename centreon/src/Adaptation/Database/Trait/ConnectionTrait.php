<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Adaptation\Database\Trait;

use Adaptation\Database\Collection\BatchInsertParameters;
use Adaptation\Database\Collection\QueryParameters;
use Adaptation\Database\Exception\ConnectionException;
use Adaptation\Database\ValueObject\QueryParameter;

/**
 * Trait
 *
 * @class   ConnectionTrait
 * @package Adaptation\Database\Trait
 */
trait ConnectionTrait {

    // ----------------------------------------- CUD METHODS -----------------------------------------

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be only used for INSERT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return int
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::int('id', 1), QueryParameter::string('name', 'John')]);
     *          $nbAffectedRows = $db->insert('INSERT INTO table (id, name) VALUES (:id, :name)', $queryParameters);
     *          // $nbAffectedRows = 1
     */
    public function insert(string $query, ?QueryParameters $queryParameters = null): int
    {
        try {
            if (! str_starts_with($query, 'INSERT INTO ')
                && ! str_starts_with($query, 'insert into ')
            ) {
                throw ConnectionException::insertQueryBadFormat($query);
            }

            return $this->executeStatement($query, $queryParameters);
        } catch (\Throwable $exception) {
            throw ConnectionException::executeStatementFailed($exception, $query, $queryParameters);
        }
    }

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows for multiple inserts.
     *
     * Could be only used for several INSERT.
     *
     * @param string $tableName
     * @param array<string> $columns
     * @param BatchInsertParameters $batchInsertParameters
     *
     * @throws ConnectionException
     * @return int
     *
     * @example $batchInsertParameters = BatchInsertParameters::create([
     *              QueryParameters::create([QueryParameter::int('id', 1), QueryParameter::string('name', 'John')]),
     *              QueryParameters::create([QueryParameter::int('id', 2), QueryParameter::string('name', 'Jean')]),
     *          ]);
     *          $nbAffectedRows = $db->batchInsert('table', ['id', 'name'], $batchInsertParameters);
     *          // $nbAffectedRows = 2
     */
    public function batchInsert(string $tableName, array $columns, BatchInsertParameters $batchInsertParameters): int
    {
        try {
            if (empty($tableName)) {
                throw ConnectionException::batchInsertQueryBadUsage('Table name must not be empty');
            }
            if (empty($columns)) {
                throw ConnectionException::batchInsertQueryBadUsage('Columns must not be empty');
            }
            if ($batchInsertParameters->isEmpty()) {
                throw ConnectionException::batchInsertQueryBadUsage('Batch insert parameters must not be empty');
            }

            $query = "INSERT INTO {$tableName} (" . implode(', ', $columns) . ') VALUES';

            $valuesInsert = [];
            $queryParametersToInsert = new QueryParameters([]);

            $indexQueryParameterToInsert = 1;

            /*
             * $batchInsertParameters is a collection of QueryParameters, each QueryParameters is a collection of QueryParameter
             * We need to iterate over the QueryParameters to build the final query.
             * Then, for each QueryParameters, we need to iterate over the QueryParameter to build :
             *  - to check if the query parameters are not empty (queryParameters)
             *  - to check if the columns and query parameters have the same length (columns, queryParameters)
             *  - to rename the parameter name to avoid conflicts with a suffix (indexQueryParameterToInsert)
             *  - the values block of the query (valuesInsert)
             *  - the query parameters to insert (queryParametersToInsert)
             */

            foreach ($batchInsertParameters->getIterator() as $queryParameters) {
                if ($queryParameters->isEmpty()) {
                    throw ConnectionException::batchInsertQueryBadUsage('Query parameters must not be empty');
                }
                if (count($columns) !== $queryParameters->length()) {
                    throw ConnectionException::batchInsertQueryBadUsage(
                        'Columns and query parameters must have the same length'
                    );
                }

                $valuesInsertItem = '';

                foreach ($queryParameters->getIterator() as $queryParameter) {
                    if (! empty($valuesInsertItem)) {
                        $valuesInsertItem .= ', ';
                    }
                    $parameterName = "{$queryParameter->getName()}_{$indexQueryParameterToInsert}";
                    $queryParameterToInsert = QueryParameter::create(
                        $parameterName,
                        $queryParameter->getValue(),
                        $queryParameter->getType()
                    );
                    $valuesInsertItem .= ":{$parameterName}";
                    $queryParametersToInsert->add($queryParameterToInsert->getName(), $queryParameterToInsert);
                }

                $valuesInsert[] = "({$valuesInsertItem})";
                $indexQueryParameterToInsert++;
            }

            if (count($valuesInsert) === $queryParametersToInsert->length()) {
                throw ConnectionException::batchInsertQueryBadUsage(
                    'Error while building the final query : values block and query parameters have not the same length'
                );
            }

            $query .= implode(', ', $valuesInsert);

            return $this->executeStatement($query, $queryParametersToInsert);
        } catch (\Throwable $exception) {
            throw ConnectionException::batchInsertQueryFailed(
                previous: $exception,
                tableName: $tableName,
                columns: $columns,
                batchInsertParameters: $batchInsertParameters,
                query: $query ?? ''
            );
        }
    }

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be only used for UPDATE.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return int
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::int('id', 1), QueryParameter::string('name', 'John')]);
     *          $nbAffectedRows = $db->update('UPDATE table SET name = :name WHERE id = :id', $queryParameters);
     *          // $nbAffectedRows = 1
     */
    public function update(string $query, ?QueryParameters $queryParameters = null): int
    {
        try {
            if (! str_starts_with($query, 'UPDATE ')
                && ! str_starts_with($query, 'update ')
            ) {
                throw ConnectionException::updateQueryBadFormat($query);
            }

            return $this->executeStatement($query, $queryParameters);
        } catch (\Throwable $exception) {
            throw ConnectionException::executeStatementFailed($exception, $query, $queryParameters);
        }
    }

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be only used for DELETE.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return int
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::int('id', 1)]);
     *          $nbAffectedRows = $db->delete('DELETE FROM table WHERE id = :id', $queryParameters);
     *          // $nbAffectedRows = 1
     */
    public function delete(string $query, ?QueryParameters $queryParameters = null): int
    {
        try {
            if (! str_starts_with($query, 'DELETE ')
                && ! str_starts_with($query, 'delete ')
            ) {
                throw ConnectionException::deleteQueryBadFormat($query);
            }

            return $this->executeStatement($query, $queryParameters);
        } catch (\Throwable $exception) {
            throw ConnectionException::executeStatementFailed($exception, $query, $queryParameters);
        }
    }

    // ----------------------------------------- FETCH METHODS -----------------------------------------

    /**
     * Prepares and executes an SQL query and returns the result as an associative array with the keys mapped
     * to the first column and the values being an associative array representing the rest of the columns
     * and their values.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return array<mixed,array<string,mixed>>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->fetchAllAssociativeIndexed('SELECT id, name, surname FROM table WHERE active = :active', $queryParameters);
     *          // $result = [1 => ['name' => 'John', 'surname' => 'Doe'], 2 => ['name' => 'Jean', 'surname' => 'Dupont']]
     */
    public function fetchAllAssociativeIndexed(string $query, ?QueryParameters $queryParameters = null): array
    {
        try {
            $this->validateSelectQuery($query);

            return $this->fetchAllAssociativeIndexed($query, $queryParameters);
        } catch (\Throwable $exception) {
            throw ConnectionException::fetchAllAssociativeIndexedQueryFailed($exception, $query, $queryParameters);
        }
    }

    // ----------------------------------------- PRIVATE METHODS -----------------------------------------

    /**
     * @param string $query
     *
     * @throws ConnectionException
     * @return void
     */
    private function validateSelectQuery(string $query): void
    {
        if (empty($query)) {
            throw ConnectionException::notEmptyQuery();
        }
        if (! str_starts_with($query, 'SELECT') && ! str_starts_with($query, 'select')) {
            throw ConnectionException::selectQueryBadFormat($query);
        }
    }

}
