<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\Resources\Infrastructure\API\FindHostsStatusCount;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\RequestParameters;

/**
 * @phpstan-type _RequestParameters array{
 *      hostgroup_names: list<string>,
 *      host_category_names: list<string>,
 * }
 */
final class FindHostsStatusCountRequestValidator
{
    use LoggerTrait;
    public const PARAM_HOSTGROUP_NAMES = 'hostgroup_names';
    public const PARAM_HOST_CATEGORY_NAMES = 'host_category_names';
    private const EMPTY_FILTERS = [
        self::PARAM_HOSTGROUP_NAMES => [],
        self::PARAM_HOST_CATEGORY_NAMES => [],
    ];

    /**
     * @param array<mixed> $queryParameters
     *
     * @throws \InvalidArgumentException
     *
     * @return _RequestParameters
     */
    public function validateAndRetrieveRequestParameters(array $queryParameters): array
    {
        $filterData = self::EMPTY_FILTERS;

        foreach ($queryParameters as $parameterName => $parameterValue) {
            if ($parameterName === RequestParameters::NAME_FOR_SEARCH) {
                continue;
            }

            // do not allow query parameters not managed
            if (! array_key_exists($parameterName, $filterData)) {
                throw new \InvalidArgumentException('Request parameter provided not handled');
            }

            $value = $this->tryJsonDecodeParameterValue($parameterValue);
            $filterData[$parameterName] = $this->ensureArrayOfString($parameterName, $value);
        }

        return $filterData;
    }

    /**
     * The input data can be JSON injected in the parameter value.
     *
     * @param mixed $parameterValue
     *
     * @return mixed
     */
    private function tryJsonDecodeParameterValue(mixed $parameterValue): mixed
    {
        try {
            return \is_string($parameterValue)
                ? json_decode($parameterValue, true, 512, JSON_THROW_ON_ERROR)
                : $parameterValue;
        } catch (\JsonException $ex) {
            $this->error('An error occured while decoding json data', ['trace' => (string) $ex]);

            return $parameterValue;
        }
    }

    /**
     * Ensures that array provided is only made of strings.
     *
     * @param string $parameterName
     * @param mixed $values
     *
     * @throws \InvalidArgumentException
     *
     * @return list<string>
     */
    private function ensureArrayOfString(string $parameterName, mixed $values): array
    {
        $strings = [];
        foreach ($this->ensureArray($parameterName, $values) as $value) {
            if (! \is_string($value)) {
                $message = sprintf('Values provided for %s should only be strings', $parameterName);
                $this->error($message);

                throw new \InvalidArgumentException($message);
            }
            $strings[] = $value;
        }

        return $strings;
    }

    /**
     * Ensures that value provided is an array.
     *
     * @param string $parameterName
     * @param mixed $value
     *
     * @throws \InvalidArgumentException
     *
     * @return array<mixed>
     */
    private function ensureArray(string $parameterName, mixed $value): array
    {
        if (! \is_array($value)) {
            $message = sprintf('Value provided for %s is not correctly formatted. Array expected.', $parameterName);

            throw new \InvalidArgumentException($message);
        }

        return $value;
    }
}
