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

namespace Adaptation\Database\Connection\Collection;

use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Collection\ObjectCollection;
use Core\Common\Domain\Exception\CollectionException;

/**
 * Class
 *
 * @class   QueryParameters
 * @package Adaptation\Database\Connection\Collection
 * @extends ObjectCollection<QueryParameter>
 */
class QueryParameters extends ObjectCollection
{
    /**
     * @throws CollectionException
     * @return static
     */
    public function getIntQueryParameters(): static
    {
        return $this->filterOnValue(
            fn(QueryParameter $queryParameter) => $queryParameter->type === QueryParameterTypeEnum::INTEGER
        );
    }

    /**
     * @throws CollectionException
     * @return static
     */
    public function getStringQueryParameters(): static
    {
        return $this->filterOnValue(
            fn(QueryParameter $queryParameter) => $queryParameter->type === QueryParameterTypeEnum::STRING
        );
    }

    /**
     * @throws CollectionException
     * @return static
     */
    public function getBoolQueryParameters(): static
    {
        return $this->filterOnValue(
            fn(QueryParameter $queryParameter) => $queryParameter->type === QueryParameterTypeEnum::BOOLEAN
        );
    }

    /**
     * @throws CollectionException
     * @return static
     */
    public function getNullQueryParameters(): static
    {
        return $this->filterOnValue(
            fn(QueryParameter $queryParameter) => $queryParameter->type === QueryParameterTypeEnum::NULL
        );
    }

    /**
     * @throws CollectionException
     * @return static
     */
    public function getLargeObjectQueryParameters(): static
    {
        return $this->filterOnValue(
            fn(QueryParameter $queryParameter) => $queryParameter->type === QueryParameterTypeEnum::LARGE_OBJECT
        );
    }

    /**
     * @return class-string<QueryParameter>
     */
    protected function itemClass(): string
    {
        return QueryParameter::class;
    }
}
