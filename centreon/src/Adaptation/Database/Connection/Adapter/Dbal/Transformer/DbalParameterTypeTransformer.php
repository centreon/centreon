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

namespace Adaptation\Database\Connection\Adapter\Dbal\Transformer;

use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Core\Common\Domain\Exception\TransformerException;
use Doctrine\DBAL\ParameterType as DbalParameterType;

/**
 * Class.
 *
 * @class   DbalParameterTypeTransformer
 */
abstract readonly class DbalParameterTypeTransformer
{
    /**
     * @param QueryParameterTypeEnum $queryParameterTypeEnum
     *
     * @return DbalParameterType
     */
    public static function transformFromQueryParameterType(QueryParameterTypeEnum $queryParameterTypeEnum): DbalParameterType
    {
        return match ($queryParameterTypeEnum) {
            QueryParameterTypeEnum::STRING => DbalParameterType::STRING,
            QueryParameterTypeEnum::INTEGER => DbalParameterType::INTEGER,
            QueryParameterTypeEnum::BOOLEAN => DbalParameterType::BOOLEAN,
            QueryParameterTypeEnum::NULL => DbalParameterType::NULL,
            QueryParameterTypeEnum::LARGE_OBJECT => DbalParameterType::LARGE_OBJECT,
        };
    }

    /**
     * @param DbalParameterType $dbalParameterType
     *
     * @throws TransformerException
     *
     * @return QueryParameterTypeEnum
     */
    public static function reverseToQueryParameterType(DbalParameterType $dbalParameterType): QueryParameterTypeEnum
    {
        return match ($dbalParameterType) {
            DbalParameterType::STRING => QueryParameterTypeEnum::STRING,
            DbalParameterType::INTEGER => QueryParameterTypeEnum::INTEGER,
            DbalParameterType::BOOLEAN => QueryParameterTypeEnum::BOOLEAN,
            DbalParameterType::NULL => QueryParameterTypeEnum::NULL,
            DbalParameterType::LARGE_OBJECT => QueryParameterTypeEnum::LARGE_OBJECT,
            default => throw new TransformerException(
                'The type of the parameter is not supported by DbalParameterType',
                ['dbal_parameter_type' => $dbalParameterType]
            ),
        };
    }
}
