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

namespace Adaptation\Database\Connection\Exception;

use Adaptation\Database\Connection\Collection\BatchInsertParameters;
use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Exception\DatabaseException;

/**
 * Class
 *
 * @class   ConnectionException
 * @package Adaptation\Database\Connection\Exception
 */
class ConnectionException extends DatabaseException
{
    /**
     * @param string $method
     *
     * @return ConnectionException
     */
    public static function notImplemented(string $method): self
    {
        return new self(
            message: "{$method} method not implemented",
            code: self::ERROR_CODE_BAD_USAGE
        );
    }

    /**
     * @param string $message
     * @param array<string,mixed> $context
     *
     * @return ConnectionException
     */
    public static function connectionBadUsage(string $message, array $context = []): self
    {
        return new self(
            message: "Bad usage of connection : {$message}",
            code: self::ERROR_CODE_BAD_USAGE,
            context: $context
        );
    }

    /**
     * @param \Throwable|null $previous
     *
     * @return ConnectionException
     */
    public static function connectionFailed(?\Throwable $previous = null): self
    {
        $message = 'Error while connecting to the database';
        if (! is_null($previous) && ! empty($previous->getMessage())) {
            $message .= " : {$previous->getMessage()}";
        }

        return new self($message, self::ERROR_CODE_DATABASE, [], $previous);
    }

    /**
     * @param \Throwable|null $previous
     *
     * @return ConnectionException
     */
    public static function getNativeConnectionFailed(?\Throwable $previous = null): self
    {
        $message = 'Error while retrieving the native connection';
        if (! is_null($previous) && ! empty($previous->getMessage())) {
            $message .= " : {$previous->getMessage()}";
        }

        return new self(
            message: $message,
            code: self::ERROR_CODE_DATABASE,
            previous: $previous
        );
    }

    /**
     * @param \Throwable|null $previous
     *
     * @return ConnectionException
     */
    public static function getDatabaseNameFailed(?\Throwable $previous = null): self
    {
        $message = 'Error while retrieving the database name';
        if (! is_null($previous) && ! empty($previous->getMessage())) {
            $message .= " : {$previous->getMessage()}";
        }

        return new self($message, self::ERROR_CODE_DATABASE);
    }

    /**
     * @param \Throwable $previous
     *
     * @return ConnectionException
     */
    public static function getLastInsertFailed(\Throwable $previous): self
    {
        return new self(
            'Error while retrieving the last auto-incremented id inserted.',
            code: self::ERROR_CODE_DATABASE,
            previous: $previous
        );
    }

    // --------------------------------------- CRUD METHODS -----------------------------------------

    /**
     * @return ConnectionException
     */
    public static function notEmptyQuery(): self
    {
        return new self(
            message: 'The query is empty',
            code: self::ERROR_CODE_BAD_USAGE
        );
    }

    public static function executeStatementBadFormat(string $message, string $query): self
    {
        return new self(
            message: "Query format is not correct to use executeStatement : {$message}",
            code: self::ERROR_CODE_BAD_USAGE,
            context: ['query' => $query]
        );
    }

    /**
     * @param \Throwable $previous
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @return self
     */
    public static function executeStatementFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): self {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing the statement : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param string $query
     *
     * @return ConnectionException
     */
    public static function insertQueryBadFormat(string $query): self
    {
        return new self(
            message: "The query need to start by 'INSERT INTO '",
            code: self::ERROR_CODE_BAD_USAGE,
            context: ['query' => $query]
        );
    }

    /**
     * @param \Throwable $previous
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function insertQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): self {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing the insert query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param string $message
     *
     * @return ConnectionException
     */
    public static function batchInsertQueryBadUsage(string $message): self
    {
        return new self(
            message: "Bad usage of batch insert query : {$message}",
            code: self::ERROR_CODE_BAD_USAGE
        );
    }

    /**
     * @param \Throwable $previous
     * @param string $tableName
     * @param array<string> $columns
     * @param BatchInsertParameters $batchInsertParameters
     * @param string $query
     *
     * @return ConnectionException
     */
    public static function batchInsertQueryFailed(
        \Throwable $previous,
        string $tableName,
        array $columns,
        BatchInsertParameters $batchInsertParameters,
        string $query = ''
    ): self {
        return new self(
            message: "Error while executing the batch insert query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: [
                'table_name' => $tableName,
                'columns' => $columns,
                'query' => $query,
                'batch_insert_parameters' => $batchInsertParameters,
            ],
            previous: $previous
        );
    }

    /**
     * @param string $query
     *
     * @return ConnectionException
     */
    public static function updateQueryBadFormat(string $query): self
    {
        return new self(
            message: "The query need to start by 'UPDATE '",
            code: self::ERROR_CODE_BAD_USAGE,
            context: ['query' => $query]
        );
    }

    /**
     * @param \Throwable $previous
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function updateQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): self {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing the update query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param string $query
     *
     * @return ConnectionException
     */
    public static function deleteQueryBadFormat(string $query): self
    {
        return new self(
            message: "The query need to start by 'DELETE '",
            code: self::ERROR_CODE_BAD_USAGE,
            context: ['query' => $query]
        );
    }

    /**
     * @param \Throwable $previous
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function deleteQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): self {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing the delete query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param string $query
     *
     * @return ConnectionException
     */
    public static function selectQueryBadFormat(string $query): self
    {
        return new self(
            message: "The query need to start by 'SELECT '",
            code: self::ERROR_CODE_BAD_USAGE,
            context: ['query' => $query]
        );
    }

    /**
     * @param \Throwable $previous
     * @param string $query
     * @param QueryParameters|null $queryParameters
     * @param array<string,mixed> $context
     *
     * @return ConnectionException
     */
    public static function selectQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null,
        array $context = []
    ): self {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing the select query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param \Throwable $previous
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function fetchNumericQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): self {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing fetch numeric query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param \Throwable $previous
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function fetchAssociativeQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): self {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing fetch associative query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param \Throwable $previous
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function fetchOneQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): self {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing fetch one query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param \Throwable $previous
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function fetchFirstColumnQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null,
    ): self {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing fetch first column query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param \Throwable $previous
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function fetchAllNumericQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): self {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing fetch all numeric query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param \Throwable $previous
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function fetchAllAssociativeQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): self {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing fetch all associative query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param \Throwable $previous
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function fetchAllKeyValueQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): self {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing fetch all key value query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param \Throwable $previous
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function fetchAllAssociativeIndexedQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): self {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing fetch all associative indexed query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param \Throwable $previous
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function iterateNumericQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): self {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing iterate numeric query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param \Throwable $previous
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function iterateAssociativeQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): self {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing iterate associative query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param \Throwable $previous
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function iterateColumnQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): self {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing iterate first column query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param string $query
     * @param string $message
     *
     * @return self
     */
    public static function iterateKeyValueQueryBadFormat(string $message, string $query): self
    {
        return new self(
            message: "Bad format of iterate key value query : {$message}",
            code: self::ERROR_CODE_BAD_USAGE,
            context: ['query' => $query]
        );
    }

    /**
     * @param \Throwable $previous
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function iterateKeyValueQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): self {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing iterate key value query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param \Throwable $previous
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function iterateAssociativeIndexedQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): self {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing iterate associative indexed query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    // ----------------------------------------- TRANSACTIONS -----------------------------------------

    /**
     * @param \Throwable|null $exception
     *
     * @return self
     */
    public static function setAutoCommitFailed(?\Throwable $exception = null): self
    {
        $message = 'Error while setting auto commit';
        if (! is_null($exception) && ! empty($exception->getMessage())) {
            $message .= " : {$exception->getMessage()}";
        }

        return new self(
            message: $message,
            code: self::ERROR_CODE_DATABASE_TRANSACTION,
            previous: $exception
        );
    }

    /**
     * @param \Throwable $previous
     *
     * @return ConnectionException
     */
    public static function startTransactionFailed(\Throwable $previous): self
    {
        return new self(
            message: "Error while starting a transaction : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE_TRANSACTION,
            previous: $previous
        );
    }

    /**
     * @param \Throwable|null $previous
     *
     * @return ConnectionException
     */
    public static function commitTransactionFailed(?\Throwable $previous = null): self
    {
        $message = 'Error during the transaction commit';
        if (! is_null($previous) && ! empty($previous->getMessage())) {
            $message .= " : {$previous->getMessage()}";
        }

        return new self(
            message: $message,
            code: self::ERROR_CODE_DATABASE_TRANSACTION,
            previous: $previous
        );
    }

    /**
     * @param \Throwable|null $previous
     *
     * @return ConnectionException
     */
    public static function rollbackTransactionFailed(?\Throwable $previous = null): self
    {
        $message = 'Error during the transaction rollback';
        if (! is_null($previous) && ! empty($previous->getMessage())) {
            $message .= " : {$previous->getMessage()}";
        }

        return new self(
            message: $message,
            code: self::ERROR_CODE_DATABASE_TRANSACTION,
            previous: $previous
        );
    }

    // ------------------------------------- UNBUFFERED QUERIES -----------------------------------------

    /**
     * @param string $nativeConnectionClass
     * @param string $currentDriverName
     *
     * @return ConnectionException
     */
    public static function allowUnbufferedQueryFailed(
        string $nativeConnectionClass,
        string $currentDriverName
    ): self {
        return new self(
            message: "Unbuffered queries not allowed for native connection class '{$nativeConnectionClass}' with this driver : {$currentDriverName}.",
            code: self::ERROR_CODE_UNBUFFERED_QUERY,
            context: ['native_connection_class' => $nativeConnectionClass, 'current_driver_name' => $currentDriverName]
        );
    }

    /**
     * @return ConnectionException
     */
    public static function startUnbufferedQueryFailed(): self
    {
        return new self(
            message: 'Starting unbuffered queries failed.',
            code: self::ERROR_CODE_UNBUFFERED_QUERY
        );
    }

    /**
     * @param string $message
     *
     * @return ConnectionException
     */
    public static function stopUnbufferedQueryFailed(
        string $message
    ): self {
        return new self(
            message: "Stopping unbuffered queries failed : {$message}",
            code: self::ERROR_CODE_UNBUFFERED_QUERY
        );
    }

    // ------------------------------------- BASE METHODS -----------------------------------------

    /**
     * @param \Throwable $previous
     * @param string $query
     *
     * @return ConnectionException
     */
    public static function closeQueryFailed(\Throwable $previous, string $query): self
    {
        return new self(
            message: "Error while closing the query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: ['query' => $query],
            previous: $previous
        );
    }
}
