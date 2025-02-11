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

namespace Adaptation\Database\Exception;

use Adaptation\Database\Collection\QueryParameters;

/**
 * Class
 *
 * @class   ConnectionException
 * @package Adaptation\Database\Exception
 */
class ConnectionException extends DatabaseException
{
    /**
     * @param \Throwable $previous
     *
     * @return ConnectionException
     */
    public static function connectionFailed(\Throwable $previous): ConnectionException
    {
        return new self(
            message: "Error during the initialization of the connection : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            previous: $previous
        );
    }

    /**
     * @param \Throwable $previous
     *
     * @return ConnectionException
     */
    public static function getNativeConnectionFailed(\Throwable $previous): ConnectionException
    {
        return new self(
            "Error while getting the native connection : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            previous: $previous
        );
    }

    /**
     * @param \Throwable $previous
     *
     * @return ConnectionException
     */
    public static function getDatabaseFailed(\Throwable $previous): ConnectionException
    {
        return new self(
            "Error while getting the database",
            code: self::ERROR_CODE_DATABASE,
            previous: $previous
        );
    }

    /**
     * @return ConnectionException
     */
    public static function getDatabaseNameFailed(): ConnectionException
    {
        return new self(
            "Error while getting the database name",
            code: self::ERROR_CODE_DATABASE
        );
    }

    /**
     * @param \Throwable $previous
     *
     * @return ConnectionException
     */
    public static function getLastInsertFailed(\Throwable $previous): ConnectionException
    {
        return new self(
            "Error while retrieving the last auto-incremented id inserted.",
            code: self::ERROR_CODE_DATABASE,
            previous: $previous
        );
    }

    // --------------------------------------- CRUD METHODS -----------------------------------------

    /**
     * @return ConnectionException
     */
    public static function notEmptyQuery(): ConnectionException
    {
        return new self(
            message: "The query is empty",
            code: self::ERROR_CODE_BAD_USAGE
        );
    }

    public static function executeStatementBadFormat(string $message, string $query): ConnectionException
    {
        return new self(
            message: "Query format is not correct to use executeStatement : {$message}",
            code: self::ERROR_CODE_BAD_USAGE,
            context: ['query' => $query]
        );
    }

    /**
     * @param \Throwable           $previous
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function executeStatementFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): ConnectionException {
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
    public static function insertQueryBadFormat(string $query): ConnectionException
    {
        return new self(
            message: "The query need to start by 'INSERT INTO '",
            code: self::ERROR_CODE_BAD_USAGE,
            context: ['query' => $query]
        );
    }

    /**
     * @param \Throwable           $previous
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function insertQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): ConnectionException {
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
    public static function batchInsertQueryBadUsage(string $message): ConnectionException
    {
        return new self(
            message: "Bad usage of batch insert query : {$message}",
            code: self::ERROR_CODE_BAD_USAGE
        );
    }

    /**
     * @param \Throwable $previous
     *
     * @return ConnectionException
     */
    public static function batchInsertQueryFailed(\Throwable $previous): ConnectionException
    {
        return new self(
            message: "Error while executing the batch insert query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            previous: $previous
        );
    }

    /**
     * @param string $query
     *
     * @return ConnectionException
     */
    public static function updateQueryBadFormat(string $query): ConnectionException
    {
        return new self(
            message: "The query need to start by 'UPDATE '",
            code: self::ERROR_CODE_BAD_USAGE,
            context: ['query' => $query]
        );
    }

    /**
     * @param \Throwable           $previous
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function updateQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): ConnectionException {
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
    public static function deleteQueryBadFormat(string $query): ConnectionException
    {
        return new self(
            message: "The query need to start by 'DELETE '",
            code: self::ERROR_CODE_BAD_USAGE,
            context: ['query' => $query]
        );
    }

    /**
     * @param \Throwable           $previous
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function deleteQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): ConnectionException {
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
    public static function selectQueryBadFormat(string $query): ConnectionException
    {
        return new self(
            message: "The query need to start by 'SELECT '",
            code: self::ERROR_CODE_BAD_USAGE,
            context: ['query' => $query]
        );
    }

    /**
     * @param \Throwable $previous
     * @param string     $query
     * @param array      $context
     *
     * @return ConnectionException
     */
    public static function selectQueryFailed(
        \Throwable $previous,
        string $query,
        array $context = []
    ): ConnectionException {
        $context['query'] = $query;

        return new self(
            message: "Error while executing the select query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param \Throwable           $previous
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function fetchNumericQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): ConnectionException {
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
     * @param \Throwable           $previous
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function fetchAssociativeQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): ConnectionException {
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
     * @param \Throwable           $previous
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function fetchOneQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): ConnectionException {
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
     * @param \Throwable           $previous
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function fetchByColumnQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): ConnectionException {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing fetch by column query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param \Throwable           $previous
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function fetchAllNumericQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): ConnectionException {
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
     * @param \Throwable           $previous
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function fetchAllAssociativeQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): ConnectionException {
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
     * @param \Throwable           $previous
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function fetchAllByColumnQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): ConnectionException {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing fetch all by column query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param \Throwable           $previous
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function fetchAllKeyValueQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): ConnectionException {
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
     * @param \Throwable           $previous
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function fetchAllAssociativeIndexedQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): ConnectionException {
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
     * @param \Throwable           $previous
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function iterateNumericQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): ConnectionException {
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
     * @param \Throwable           $previous
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function iterateAssociativeQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): ConnectionException {
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
     * @param \Throwable           $previous
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function iterateByColumnQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): ConnectionException {
        $context['query'] = $query;
        $context['query_parameters'] = $queryParameters;

        return new self(
            message: "Error while executing iterate by column query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $context,
            previous: $previous
        );
    }

    /**
     * @param \Throwable           $previous
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function iterateKeyValueQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): ConnectionException {
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
     * @param \Throwable           $previous
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     *
     * @return ConnectionException
     */
    public static function iterateAssociativeIndexedQueryFailed(
        \Throwable $previous,
        string $query,
        ?QueryParameters $queryParameters = null
    ): ConnectionException {
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
     * @param \Throwable $previous
     *
     * @return ConnectionException
     */
    public static function startTransactionFailed(\Throwable $previous): ConnectionException
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
    public static function commitTransactionFailed(?\Throwable $previous = null): ConnectionException
    {
        $message = "Error during the transaction commit";
        if (! is_null($previous) && $previous->getMessage() !== '') {
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
    public static function rollbackTransactionFailed(?\Throwable $previous = null): ConnectionException
    {
        $message = "Error during the transaction rollback";
        if ($previous->getMessage() !== '') {
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
     * @return ConnectionException
     */
    public static function startUnbufferedQueryFailed(): ConnectionException
    {
        return new self(
            message: "Starting unbuffered queries failed.",
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
    ): ConnectionException {
        return new self(
            message: "Stopping unbuffered queries failed : {$message}",
            code: self::ERROR_CODE_UNBUFFERED_QUERY
        );
    }

}
