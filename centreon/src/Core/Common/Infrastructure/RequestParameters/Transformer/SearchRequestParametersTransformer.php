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

namespace Core\Common\Infrastructure\RequestParameters\Transformer;

use Adaptation\Database\Connection\Adapter\Pdo\Transformer\PdoParameterTypeTransformer;
use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\TransformerException;
use Core\Common\Domain\Exception\ValueObjectException;

/**
 * Class
 *
 * @class SearchRequestParametersTransformer
 * @package Centreon\Infrastructure\RequestParameters\Transformer
 */
readonly abstract class SearchRequestParametersTransformer
{
    /**
     * @param QueryParameters $queryParameters
     *
     * @return array<string,array<int,mixed>>
     */
    public static function transformFromQueryParameters(QueryParameters $queryParameters): array
    {
        $requestParameters = [];
        /** @var QueryParameter $queryParameter */
        foreach ($queryParameters->getIterator() as $queryParameter) {
            $pdoType = PdoParameterTypeTransformer::transformFromQueryParameterType(
                $queryParameter->getType() ?? QueryParameterTypeEnum::STRING
            );
            $requestParameters[$queryParameter->getName()] = [$pdoType, $queryParameter->getValue()];
        }

        return $requestParameters;
    }

    /**
     * @param array<string,array<int,mixed>> $requestParameters
     *
     * @throws TransformerException
     * @return QueryParameters
     */
    public static function reverseToQueryParameters(array $requestParameters): QueryParameters
    {
        try {
            $queryParameters = new QueryParameters();
            foreach ($requestParameters as $key => $value) {
                $queryParameterTypeEnum = PdoParameterTypeTransformer::reverseToQueryParameterType($value[0]);
                $value = $value[1];
                $queryParameters->add($key, QueryParameter::create($key, $value, $queryParameterTypeEnum));
            }

            return $queryParameters;
        } catch (CollectionException|ValueObjectException $exception) {
            throw new TransformerException(
                'Error while transforming request parameters to query parameters',
                ['requestParameters' => $requestParameters],
                $exception
            );
        }
    }
}
