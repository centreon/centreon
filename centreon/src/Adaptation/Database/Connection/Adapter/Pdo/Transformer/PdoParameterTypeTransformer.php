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

namespace Adaptation\Database\Connection\Adapter\Pdo\Transformer;

use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Core\Common\Domain\Exception\TransformerException;

/**
 * Class.
 *
 * @class   PdoParameterTypeTransformer
 */
abstract class PdoParameterTypeTransformer
{
    /**
     * @param QueryParameterTypeEnum $queryParameterType
     *
     * @return int
     */
    public static function transformFromQueryParameterType(QueryParameterTypeEnum $queryParameterType): int
    {
        return match ($queryParameterType) {
            QueryParameterTypeEnum::NULL => \PDO::PARAM_NULL,
            QueryParameterTypeEnum::INTEGER => \PDO::PARAM_INT,
            QueryParameterTypeEnum::STRING => \PDO::PARAM_STR,
            QueryParameterTypeEnum::LARGE_OBJECT => \PDO::PARAM_LOB,
            QueryParameterTypeEnum::BOOLEAN => \PDO::PARAM_BOOL,
        };
    }

    /**
     * @param int $pdoParameterType
     *
     * @throws TransformerException
     *
     * @return QueryParameterTypeEnum
     */
    public static function reverseToQueryParameterType(int $pdoParameterType): QueryParameterTypeEnum
    {
        return match ($pdoParameterType) {
            \PDO::PARAM_NULL => QueryParameterTypeEnum::NULL,
            \PDO::PARAM_INT => QueryParameterTypeEnum::INTEGER,
            \PDO::PARAM_STR => QueryParameterTypeEnum::STRING,
            \PDO::PARAM_LOB => QueryParameterTypeEnum::LARGE_OBJECT,
            \PDO::PARAM_BOOL => QueryParameterTypeEnum::BOOLEAN,
            default => throw new TransformerException(
                'Unknown PDO parameter type',
                ['pdo_parameter_type' => $pdoParameterType]
            )
        };
    }
}
