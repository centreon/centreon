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

namespace Core\Adaptation\Database\Exception;

use Throwable;

/**
 * Class
 *
 * @class ConnectionException
 * @package Core\Adaptation\Database\Exception
 */
class ConnectionException extends DatabaseException
{
    /**
     * @param Throwable $e
     *
     * @return ConnectionException
     */
    public static function connectionFailed(Throwable $e): ConnectionException
    {
        return new self(
            "Error during the initialization of the connection : {$e->getMessage()}",
            self::ERROR_CODE_DATABASE,
            [],
            $e
        );
    }

    /**
     * @param Throwable $e
     *
     * @return ConnectionException
     */
    public static function getNativeConnectionFailed(Throwable $e): ConnectionException
    {
        return new self(
            "Error while getting the native connection : {$e->getMessage()}",
            self::ERROR_CODE_DATABASE,
            [],
            $e
        );
    }

    /**
     * @param Throwable $e
     *
     * @return ConnectionException
     */
    public static function getDatabaseFailed(Throwable $e): ConnectionException
    {
        return new self(
            "Error while getting the database",
            self::ERROR_CODE_DATABASE,
            [],
            $e
        );
    }

    /**
     * @param Throwable $e
     *
     * @return ConnectionException
     */
    public static function getLastInsertFailed(Throwable $e): ConnectionException
    {
        return new self(
            "Error while retrieving the last auto-incremented id inserted.",
            self::ERROR_CODE_DATABASE,
            [],
            $e
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
            "The query need to start by 'INSERT INTO '",
            self::ERROR_CODE_BAD_USAGE,
            ['query' => $query]
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
            "The query need to start by 'UPDATE '",
            self::ERROR_CODE_BAD_USAGE,
            ['query' => $query]
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
            "The query need to start by 'DELETE '",
            self::ERROR_CODE_BAD_USAGE,
            ['query' => $query]
        );
    }

    /**
     * @param Throwable $e
     * @param string $query
     * @param array $queryParams
     * @param array $queryParamTypes
     *
     * @return ConnectionException
     */
    public static function executeQueryFailed(
        Throwable $e,
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
            "Error while executing the query : {$e->getMessage()}",
            self::ERROR_CODE_DATABASE,
            $options,
            $e
        );
    }

    // ----------------------------------------- TRANSACTIONS -----------------------------------------

    /**
     * @param Throwable $e
     *
     * @return ConnectionException
     */
    public static function setAutoCommitFailed(Throwable $e): ConnectionException
    {
        return new self(
            "Error while setting auto-commit option",
            self::ERROR_CODE_DATABASE,
            [],
            $e);
    }

    /**
     * @param Throwable $e
     *
     * @return ConnectionException
     */
    public static function startTransactionFailed(Throwable $e): ConnectionException
    {
        return new self(
            "Error while starting a transaction.",
            self::ERROR_CODE_DATABASE_TRANSACTION,
            [],
            $e
        );
    }

    /**
     * @param Throwable|null $e
     *
     * @return ConnectionException
     */
    public static function startNestedTransactionFailed(?Throwable $e = null): ConnectionException
    {
        return new self(
            "Error while starting a nested transaction.",
            self::ERROR_CODE_DATABASE_TRANSACTION,
            [],
            $e
        );
    }

    /**
     * @param Throwable $e
     *
     * @return ConnectionException
     */
    public static function commitTransactionFailed(Throwable $e): ConnectionException
    {
        return new self(
            "Error while committing the transaction",
            self::ERROR_CODE_DATABASE_TRANSACTION,
            [],
            $e
        );
    }

    /**
     * @param Throwable $e
     *
     * @return ConnectionException
     */
    public static function rollbackTransactionFailed(Throwable $e): ConnectionException
    {
        return new self(
            "Error during the transaction rollback",
            self::ERROR_CODE_DATABASE_TRANSACTION,
            [],
            $e
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
            "Unbuffered queries not allowed for native connection class '{$nativeConnectionClass}'.",
            self::ERROR_CODE_UNBUFFERED_QUERY,
            ['native_connection_class' => $nativeConnectionClass]
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
            "Starting unbuffered queries failed for native connection class '{$nativeConnectionClass}'.",
            self::ERROR_CODE_UNBUFFERED_QUERY,
            ['native_connection_class' => $nativeConnectionClass]
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
            "Stopping unbuffered queries failed for native connection class '{$nativeConnectionClass}' with this message : {$message}",
            self::ERROR_CODE_UNBUFFERED_QUERY,
            ['native_connection_class' => $nativeConnectionClass]
        );
    }
}
