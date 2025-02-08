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
     * @param \Throwable $previous
     * @param string    $query
     * @param array     $queryParams
     * @param array     $queryParamTypes
     *
     * @return ConnectionException
     */
    public static function executeQueryFailed(
        \Throwable $previous,
        string $query,
        array $queryParams,
        array $queryParamTypes
    ): ConnectionException {
        $options = [
            'query' => $query,
            'query_params' => $queryParams,
            'query_params_types' => $queryParamTypes
        ];

        return new self(
            message: "Error while executing the query : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            context: $options,
            previous: $previous
        );
    }

    // ----------------------------------------- TRANSACTIONS -----------------------------------------

    /**
     * @param \Throwable $previous
     *
     * @return ConnectionException
     */
    public static function setAutoCommitFailed(\Throwable $previous): ConnectionException
    {
        return new self(
            message: "Error while setting auto-commit option : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE,
            previous: $previous
        );
    }

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
    public static function startNestedTransactionFailed(?\Throwable $previous = null): ConnectionException
    {
        return new self(
            message: "Error while starting a nested transaction.",
            code: self::ERROR_CODE_DATABASE_TRANSACTION,
            previous: $previous
        );
    }

    /**
     * @param \Throwable $previous
     *
     * @return ConnectionException
     */
    public static function commitTransactionFailed(\Throwable $previous): ConnectionException
    {
        return new self(
            message: "Error while committing the transaction : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE_TRANSACTION,
            previous: $previous
        );
    }

    /**
     * @param \Throwable $previous
     *
     * @return ConnectionException
     */
    public static function rollbackTransactionFailed(\Throwable $previous): ConnectionException
    {
        return new self(
            message: "Error during the transaction rollback : {$previous->getMessage()}",
            code: self::ERROR_CODE_DATABASE_TRANSACTION,
            previous: $previous
        );
    }

    // ------------------------------------- UNBUFFERED QUERIES -----------------------------------------

    /**
     * @param string $nativeConnectionClass
     *
     * @return ConnectionException
     */
    public static function allowUnbufferedQueryFailed(string $nativeConnectionClass): ConnectionException
    {
        return new self(
            message: "Unbuffered queries not allowed for native connection class '{$nativeConnectionClass}'.",
            code: self::ERROR_CODE_UNBUFFERED_QUERY,
            context: ['native_connection_class' => $nativeConnectionClass]
        );
    }

    /**
     * @param string $nativeConnectionClass
     *
     * @return ConnectionException
     */
    public static function startUnbufferedQueryFailed(string $nativeConnectionClass): ConnectionException
    {
        return new self(
            message: "Starting unbuffered queries failed for native connection class '{$nativeConnectionClass}'.",
            code: self::ERROR_CODE_UNBUFFERED_QUERY,
            context: ['native_connection_class' => $nativeConnectionClass]
        );
    }

    /**
     * @param string $message
     * @param string $nativeConnectionClass
     *
     * @return ConnectionException
     */
    public static function stopUnbufferedQueryFailed(
        string $message,
        string $nativeConnectionClass
    ): ConnectionException {
        return new self(
            message: "Stopping unbuffered queries failed for native connection class '{$nativeConnectionClass}' with this message : {$message}",
            code: self::ERROR_CODE_UNBUFFERED_QUERY,
            context: ['native_connection_class' => $nativeConnectionClass]
        );
    }
}
