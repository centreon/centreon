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

namespace Adaptation\Database\Adapter\Dbal\Transformer;

use Adaptation\Database\Collection\QueryParameters;
use Adaptation\Database\ValueObject\QueryParameter;
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
    public static function transform(QueryParameters $queryParameters): array
    {
        $params = [];
        $types = [];
        foreach ($queryParameters->getIterator() as $queryParameter) {
            $params[$queryParameter->getName()] = $queryParameter->getValue();
            if (! is_null($queryParameter->getType())) {
                $types[$queryParameter->getName()] = DbalParameterTypeTransformer::transform(
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
    public static function reverse(array $params, array $types): QueryParameters
    {
        try {
            $queryParameters = new QueryParameters();
            foreach ($params as $name => $value) {
                $type = DbalParameterTypeTransformer::reverse($types[$name] ?? DbalParameterType::STRING);
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
