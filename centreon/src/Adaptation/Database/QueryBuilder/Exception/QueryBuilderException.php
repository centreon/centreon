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

namespace Adaptation\Database\QueryBuilder\Exception;

use Adaptation\Database\Exception\DatabaseException;

/**
 * Class
 *
 * @class QueryBuilderException
 * @package Adaptation\Database\QueryBuilder\Exception
 */
class QueryBuilderException extends DatabaseException
{
    /**
     * @param \Throwable|null $previous
     *
     * @return QueryBuilderException
     */
    public static function createFromConnectionConfigFailed(?\Throwable $previous = null): self
    {
        $message = 'Error while instantiate the query builder';
        if (! is_null($previous) && ! empty($previous->getMessage())) {
            $message .= " : {$previous->getMessage()}";
        }

        return new self($message, self::ERROR_CODE_QUERY_BUILDER, previous: $previous);
    }

    /**
     * @param \Throwable|null $previous
     *
     * @return QueryBuilderException
     */
    public static function getExpressionBuilderFailed(?\Throwable $previous = null): self {
        $message = 'Error while getting the expression builder';
        if (! is_null($previous) && ! empty($previous->getMessage())) {
            $message .= " : {$previous->getMessage()}";
        }

        return new self($message, self::ERROR_CODE_QUERY_BUILDER, previous: $previous);
    }
}
