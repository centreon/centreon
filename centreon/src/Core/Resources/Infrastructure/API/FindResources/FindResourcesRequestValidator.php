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

namespace Core\Resources\Infrastructure\API\FindResources;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\RequestParameters;
use Core\Domain\RealTime\ResourceTypeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @phpstan-type _RequestParameters array{
 *      types: list<string>,
 *      states: list<string>,
 *      statuses: list<string>,
 *      hostgroup_names: list<string>,
 *      servicegroup_names: list<string>,
 *      monitoring_server_names: list<string>,
 *      service_category_names: list<string>,
 *      host_category_names: list<string>,
 *      service_severity_names: list<string>,
 *      host_severity_names: list<string>,
 *      host_severity_levels: list<int>,
 *      service_severity_levels: list<int>,
 *      status_types: list<string>,
 *      only_with_performance_data: bool
 * }
 */
final class FindResourcesRequestValidator
{
    use LoggerTrait;
    public const RESOURCE_TYPE_PARAM_FILTER = 'types';
    public const STATES_PARAM_FILTER = 'states';
    public const STATUSES_PARAM_FILTER = 'statuses';
    public const HOSTGROUP_NAMES_PARAM_FILTER = 'hostgroup_names';
    public const SERVICEGROUP_NAMES_PARAM_FILTER = 'servicegroup_names';
    public const MONITORING_SERVER_NAMES_PARAM_FILTER = 'monitoring_server_names';
    public const SERVICE_CATEGORY_NAMES_PARAM_FILTER = 'service_category_names';
    public const HOST_CATEGORY_NAMES_PARAM_FILTER = 'host_category_names';
    public const SERVICE_SEVERITY_NAMES_PARAM_FILTER = 'service_severity_names';
    public const HOST_SEVERITY_NAMES_PARAM_FILTER = 'host_severity_names';
    public const HOST_SEVERITY_LEVELS_PARAM_FILTER = 'host_severity_levels';
    public const SERVICE_SEVERITY_LEVELS_PARAM_FILTER = 'service_severity_levels';
    public const STATUS_TYPES_PARAM_FILTER = 'status_types';
    public const FILTER_RESOURCES_ON_PERFORMANCE_DATA_AVAILABILITY = 'only_with_performance_data';

    /** @var _RequestParameters */
    private const EMPTY_FILTERS = [
        self::RESOURCE_TYPE_PARAM_FILTER => [],
        self::STATES_PARAM_FILTER => [],
        self::STATUSES_PARAM_FILTER => [],
        self::HOSTGROUP_NAMES_PARAM_FILTER => [],
        self::SERVICEGROUP_NAMES_PARAM_FILTER => [],
        self::MONITORING_SERVER_NAMES_PARAM_FILTER => [],
        self::SERVICE_CATEGORY_NAMES_PARAM_FILTER => [],
        self::HOST_CATEGORY_NAMES_PARAM_FILTER => [],
        self::SERVICE_SEVERITY_NAMES_PARAM_FILTER => [],
        self::HOST_SEVERITY_NAMES_PARAM_FILTER => [],
        self::HOST_SEVERITY_LEVELS_PARAM_FILTER => [],
        self::SERVICE_SEVERITY_LEVELS_PARAM_FILTER => [],
        self::STATUS_TYPES_PARAM_FILTER => [],
        self::FILTER_RESOURCES_ON_PERFORMANCE_DATA_AVAILABILITY => false,
    ];
    private const AVAILABLE_STATUSES = [
        'OK',
        'WARNING',
        'CRITICAL',
        'UNKNOWN',
        'UNREACHABLE',
        'PENDING',
        'UP',
        'DOWN',
    ];
    private const AVAILABLE_STATES = [
        'unhandled_problems',
        'resources_problems',
        'in_downtime',
        'acknowledged',
    ];
    private const AVAILABLE_STATUS_TYPES = [
        'hard',
        'soft',
    ];
    private const PAGINATION_PARAMETERS = [
        RequestParameters::NAME_FOR_LIMIT,
        RequestParameters::NAME_FOR_PAGE,
        RequestParameters::NAME_FOR_SEARCH,
        RequestParameters::NAME_FOR_SORT,
        RequestParameters::NAME_FOR_TOTAL,
    ];

    /** @var array<string> */
    private array $resourceTypes;

    /**
     * @param \Traversable<ResourceTypeInterface> $resourceTypes
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(\Traversable $resourceTypes)
    {
        $this->hasProviders($resourceTypes);

        $this->resourceTypes = array_map(
            static fn(ResourceTypeInterface $resourceType) => $resourceType->getName(),
            iterator_to_array($resourceTypes)
        );
    }

    /**
     * @param Request $request
     *
     * @throws \InvalidArgumentException
     *
     * @return _RequestParameters
     */
    public function validateAndRetrieveRequestParameters(Request $request): array
    {
        $filterData = self::EMPTY_FILTERS;

        // Do not handle pagination query parameters and check that parameters are handled
        foreach ($request->query->all() as $param => $data) {
            // skip pagination parameters
            if (\in_array($param, self::PAGINATION_PARAMETERS, true)) {
                continue;
            }

            // do not allow query parameters not managed
            if (! \array_key_exists($param, $filterData)) {
                throw new \InvalidArgumentException('Request parameter provided not handled');
            }

            $value = $this->tryJsonDecodeParameterValue($data);

            switch ($param) {
                case self::RESOURCE_TYPE_PARAM_FILTER:
                    $filterData[$param] = $this->ensureResourceTypeFilter($param, $value);
                    break;
                case self::STATES_PARAM_FILTER:
                    $filterData[$param] = $this->ensureStatesFilter($param, $value);
                    break;
                case self::STATUSES_PARAM_FILTER:
                    $filterData[$param] = $this->ensureStatusesFilter($param, $value);
                    break;
                case self::HOSTGROUP_NAMES_PARAM_FILTER:
                case self::SERVICEGROUP_NAMES_PARAM_FILTER:
                case self::MONITORING_SERVER_NAMES_PARAM_FILTER:
                case self::SERVICE_CATEGORY_NAMES_PARAM_FILTER:
                case self::HOST_CATEGORY_NAMES_PARAM_FILTER:
                case self::SERVICE_SEVERITY_NAMES_PARAM_FILTER:
                case self::HOST_SEVERITY_NAMES_PARAM_FILTER:
                    $filterData[$param] = $this->ensureArrayOfString($param, $value);
                    break;
                case self::HOST_SEVERITY_LEVELS_PARAM_FILTER:
                case self::SERVICE_SEVERITY_LEVELS_PARAM_FILTER:
                    $filterData[$param] = $this->ensureArrayOfInteger($param, $value);
                    break;
                case self::STATUS_TYPES_PARAM_FILTER:
                    $filterData[$param] = $this->ensureStatusTypes($param, $value);
                    break;
                case self::FILTER_RESOURCES_ON_PERFORMANCE_DATA_AVAILABILITY:
                    $filterData[$param] = $this->ensureBoolean($param, $value);
                    break;
            }
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
        } catch (\JsonException) {
            return $parameterValue;
        }
    }

    /**
     * Ensures that resource types filter provided in the payload are supported.
     *
     * @param string $parameterName
     * @param mixed $values
     *
     * @throws \InvalidArgumentException
     *
     * @return list<string>
     */
    private function ensureResourceTypeFilter(string $parameterName, mixed $values): array
    {
        $types = [];
        foreach ($this->ensureArrayOfString($parameterName, $values) as $string) {
            if (! \in_array($string, $this->resourceTypes, true)) {
                $message = sprintf('Value provided for %s parameter is not supported (was: %s)', $parameterName, $string);

                throw new \InvalidArgumentException($message);
            }
            $types[] = $string;
        }

        return $types;
    }

    /**
     * Ensures that statuses filter provided in the payload are supported.
     *
     * @param string $parameterName
     * @param mixed $values
     *
     * @throws \InvalidArgumentException
     *
     * @return list<value-of<self::AVAILABLE_STATUSES>>
     */
    private function ensureStatusesFilter(string $parameterName, mixed $values): array
    {
        $statuses = [];
        foreach ($this->ensureArrayOfString($parameterName, $values) as $string) {
            if (! \in_array($string, self::AVAILABLE_STATUSES, true)) {
                $message = sprintf('Value provided for %s parameter is not supported (was: %s)', $parameterName, $string);

                throw new \InvalidArgumentException($message);
            }
            $statuses[] = $string;
        }

        return $statuses;
    }

    /**
     * Ensures that states filter provided in the payload are supported.
     *
     * @param string $parameterName
     * @param mixed $values
     *
     * @throws \InvalidArgumentException
     *
     * @return list<value-of<self::AVAILABLE_STATES>>
     */
    private function ensureStatesFilter(string $parameterName, mixed $values): array
    {
        $states = [];
        foreach ($this->ensureArrayOfString($parameterName, $values) as $string) {
            if (! \in_array($string, self::AVAILABLE_STATES, true)) {
                $message = sprintf('Value provided for %s parameter is not supported (was: %s)', $parameterName, $string);

                throw new \InvalidArgumentException($message);
            }
            $states[] = $string;
        }

        return $states;
    }

    /**
     * Ensures that status types filter provided in the payload are supported.
     *
     * @param string $parameterName
     * @param mixed $values
     *
     * @throws \InvalidArgumentException
     *
     * @return list<value-of<self::AVAILABLE_STATUS_TYPES>>
     */
    private function ensureStatusTypes(string $parameterName, mixed $values): array
    {
        $statusTypes = [];
        foreach ($this->ensureArrayOfString($parameterName, $values) as $string) {
            if (! \in_array($string, self::AVAILABLE_STATUS_TYPES, true)) {
                $message = sprintf('Value provided for %s parameter is not supported (was: %s)', $parameterName, $string);

                throw new \InvalidArgumentException($message);
            }
            $statusTypes[] = $string;
        }

        return $statusTypes;
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
     * Ensures that array provided is only made of integers.
     *
     * @param string $parameterName
     * @param mixed $values
     *
     * @throws \InvalidArgumentException
     *
     * @return list<int>
     */
    private function ensureArrayOfInteger(string $parameterName, mixed $values): array
    {
        $integers = [];
        foreach ($this->ensureArray($parameterName, $values) as $value) {
            if (\is_string($value) && ctype_digit($value)) {
                // Cast strings which are string-integers.
                $value = (int) $value;
            } elseif (! \is_int($value)) {
                $message = sprintf('Values provided for %s should only be integers', $parameterName);
                $this->error($message);

                throw new \InvalidArgumentException($message);
            }
            $integers[] = $value;
        }

        return $integers;
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

    /**
     * Ensures that value provided is a boolean.
     *
     * @param string $parameterName
     * @param mixed $value
     *
     * @throws \InvalidArgumentException
     *
     * @return bool
     */
    private function ensureBoolean(string $parameterName, mixed $value): bool
    {
        if (! \is_bool($value)) {
            throw new \InvalidArgumentException(
                sprintf('Value provided for %s is not correctly formatted. Boolean expected', $parameterName)
            );
        }

        return $value;
    }

    /**
     * @param \Traversable<ResourceTypeInterface> $providers
     *
     * @throws \InvalidArgumentException
     */
    private function hasProviders(\Traversable $providers): void
    {
        if ($providers instanceof \Countable && \count($providers) === 0) {
            throw new \InvalidArgumentException('You must add at least one provider');
        }
    }
}
