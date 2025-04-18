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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\TransformerException;
use Core\Common\Domain\Exception\ValueObjectException;
use Doctrine\DBAL\ParameterType as DbalParameterType;

/**
 * Class
 *
 * @class   DbalParametersTransformer
 * @package Adaptation\Database\Adapter\Dbal\Transformer
 */
abstract readonly class DbalParametersTransformer
{
    /**
     * @param QueryParameters $queryParameters
     *
     * @throws TransformerException
     * @return array{0: array<string,mixed>, 1: array<string,mixed>}
     */
    public static function transformFromQueryParameters(QueryParameters $queryParameters): array
    {
        $params = [];
        $types = [];
        foreach ($queryParameters->getIterator() as $queryParameter) {
            // remove : from the key to avoid issues with named parameters, dbal doesn't accept : in the key
            $name = $queryParameter->getName();
            if (str_starts_with($queryParameter->getName(), ':')) {
                $name = mb_substr($queryParameter->getName(), 1);
            }
            $params[$name] = $queryParameter->getValue();
            if (! is_null($queryParameter->getType())) {
                $types[$name] = DbalParameterTypeTransformer::transformFromQueryParameterType(
                    $queryParameter->getType()
                );
            }
        }

        return [$params, $types];
    }

    /**
     * @param array<string,mixed> $params
     * @param array<string,mixed> $types
     *
     * @throws TransformerException
     * @return QueryParameters
     */
    public static function reverseToQueryParameters(array $params, array $types): QueryParameters
    {
        try {
            $queryParameters = new QueryParameters();
            foreach ($params as $name => $value) {
                $type = DbalParameterTypeTransformer::reverseToQueryParameterType($types[$name] ?? DbalParameterType::STRING);
                $queryParameter = QueryParameter::create($name, $value, $type);
                $queryParameters->add($queryParameter->getName(), $queryParameter);
            }

            return $queryParameters;
        } catch (CollectionException|ValueObjectException $exception) {
            throw new TransformerException(
                "Error while reversing to QueryParameters : {$exception->getMessage()}",
                ['params' => $params, 'types' => $types],
                $exception
            );
        }
    }
}
