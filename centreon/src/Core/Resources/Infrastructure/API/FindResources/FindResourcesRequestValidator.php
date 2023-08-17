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
use Core\Domain\RealTime\ResourceTypeInterface;
use Symfony\Component\HttpFoundation\Request;

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
    private const EXTRA_PARAMETERS_LIST = [
        self::RESOURCE_TYPE_PARAM_FILTER,
        self::STATES_PARAM_FILTER,
        self::STATUSES_PARAM_FILTER,
        self::HOSTGROUP_NAMES_PARAM_FILTER,
        self::SERVICEGROUP_NAMES_PARAM_FILTER,
        self::MONITORING_SERVER_NAMES_PARAM_FILTER,
        self::SERVICE_CATEGORY_NAMES_PARAM_FILTER,
        self::HOST_CATEGORY_NAMES_PARAM_FILTER,
        self::SERVICE_SEVERITY_NAMES_PARAM_FILTER,
        self::HOST_SEVERITY_NAMES_PARAM_FILTER,
        self::HOST_SEVERITY_LEVELS_PARAM_FILTER,
        self::SERVICE_SEVERITY_LEVELS_PARAM_FILTER,
        self::STATUS_TYPES_PARAM_FILTER,
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
    private const AVAILABLE_STATUS_TYPES = ['hard', 'soft'];

    /** @var string[] */
    private array $resourceTypes = [];

    /**
     * @param \Traversable<ResourceTypeInterface> $resourceTypes
     */
    public function __construct(\Traversable $resourceTypes)
    {
        $this->hasProviders($resourceTypes);

        $this->resourceTypes = array_map(
            fn(ResourceTypeInterface $resourceType) => $resourceType->getName(),
            iterator_to_array($resourceTypes)
        );
    }

    /**
     * @param Request $request
     *
     * @return array<string, array<int, string|int>|false>
     */
    public function validateAndRetrieveRequestParameters(Request $request): array
    {
        $filterData = [];
        foreach (self::EXTRA_PARAMETERS_LIST as $param) {
            $filterData[$param] = [];
        }

        $filterData[self::FILTER_RESOURCES_ON_PERFORMANCE_DATA_AVAILABILITY] = false;

        // Do not handle pagination query parameters and check that parameters are handled
        foreach ($request->query->all() as $param => $data) {
            // skip pagination parameters
            if (in_array($param, ['search', 'limit', 'page', 'sort_by'], true)) {
                continue;
            }

            // handle short list of query parameters allowed
            if (
                ! in_array($param, [...self::EXTRA_PARAMETERS_LIST, self::FILTER_RESOURCES_ON_PERFORMANCE_DATA_AVAILABILITY], true)
            ) {
                throw new \InvalidArgumentException('Request parameter provided not handled');
            }

            $filterData[$param] = json_decode($data, true) ?: $data;
        }

        $this->validateParameters($filterData);

        return $filterData;
    }

    /**
     * @param \Traversable<ResourceTypeInterface> $providers
     */
    private function hasProviders(\Traversable $providers): void
    {
        if ($providers instanceof \Countable && count($providers) === 0) {
            throw new \InvalidArgumentException('You must add at least one provider');
        }
    }

    /**
     * @param array<string, array<int, string|int>|false> $parameters
     *
     * @throws \InvalidArgumentException
     */
    private function validateParameters(array $parameters): void
    {
        foreach ($parameters as $name => $value) {
            switch ($name) {
                case self::RESOURCE_TYPE_PARAM_FILTER:
                    $this->validateResourceTypeFilterOrFail($name, $value);
                    break;
                case self::HOSTGROUP_NAMES_PARAM_FILTER:
                case self::HOST_CATEGORY_NAMES_PARAM_FILTER:
                case self::SERVICEGROUP_NAMES_PARAM_FILTER:
                case self::SERVICE_CATEGORY_NAMES_PARAM_FILTER:
                case self::MONITORING_SERVER_NAMES_PARAM_FILTER:
                    $this->validateArrayOfStringOrFail($name, $value);
                    break;
                case self::SERVICE_SEVERITY_LEVELS_PARAM_FILTER:
                case self::HOST_SEVERITY_LEVELS_PARAM_FILTER:
                    $this->validateArrayOfIntOrFail($name, $value);
                    break;
                case self::STATUSES_PARAM_FILTER:
                    $this->validateStatusesFilterOrFail($name, $value);
                    break;
                case self::STATES_PARAM_FILTER:
                    $this->validateStatesFilterOrFail($name, $value);
                    break;
                case self::STATUS_TYPES_PARAM_FILTER:
                    $this->validateStatusTypesOrFail($name, $value);
                    break;
                case self::FILTER_RESOURCES_ON_PERFORMANCE_DATA_AVAILABILITY:
                    if (! is_bool($value)) {
                        throw new \InvalidArgumentException(
                            sprintf('Value provided for %s is not correctly formatted. Boolean expected', $name)
                        );
                    }
                    break;
            }
        }
    }

    /**
     * Checks that value provided is an array.
     *
     * @param string $parameterName
     * @param array<mixed> $value
     *
     * @throws \InvalidArgumentException
     */
    private function validateArray(string $parameterName, array $value): void
    {
        if (! is_array($value)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Value provided for %s is not correctly formatted. Array expected.',
                    $parameterName
                )
            );
        }
    }

    /**
     * Checks that array provided is only made of strings.
     *
     * @param string $parameterName
     * @param array<int, string|int> $strings
     *
     * @throws \InvalidArgumentException
     */
    private function validateArrayOfStringOrFail(string $parameterName, array $strings): void
    {
        $this->validateArray($parameterName, $strings);

        try {
            (fn(string ...$items): array => $items)(...$strings);
        } catch (\TypeError) {
            $this->error(sprintf('values provided for %s should only be strings', $parameterName));

            throw new \InvalidArgumentException(
                sprintf('values provided for %s should only be strings', $parameterName)
            );
        }
    }

    /**
     * Checks that array provided is only made of integers.
     *
     * @param string $parameterName
     * @param array<int, string|int> $integers
     *
     * @throws \InvalidArgumentException
     */
    private function validateArrayOfIntOrFail(string $parameterName, array $integers): void
    {
        $this->validateArray($parameterName, $integers);

        try {
            (fn(int ...$items): array => $items)(...$integers);
        } catch (\TypeError) {
            $this->error(sprintf('Values provided for %s should only be integers', $parameterName));

            throw new \InvalidArgumentException(
                sprintf('Values provided for %s should only be integers', $parameterName)
            );
        }
    }

    /**
     * Checks that resource types filter provided in the payload are supported.
     *
     * @param string $parameterName
     * @param array<int, string|int> $types
     *
     * @throws \InvalidArgumentException
     */
    private function validateResourceTypeFilterOrFail(string $parameterName, array $types): void
    {
        $this->validateArrayOfStringOrFail($parameterName, $types);
        foreach ($types as $type) {
            if (! in_array($type, $this->resourceTypes, true)) {
                throw new \InvalidArgumentException(
                    sprintf('Value provided for %s parameter is not supported (was: %s)', $parameterName, $type)
                );
            }
        }
    }

    /**
     * Checks that statuses filter provided in the payload are supported.
     *
     * @param string $parameterName
     * @param array<int, string|int> $statuses
     *
     * @throws \InvalidArgumentException
     */
    private function validateStatusesFilterOrFail(string $parameterName, array $statuses): void
    {
        $this->validateArrayOfStringOrFail($parameterName, $statuses);
        foreach ($statuses as $status) {
            if (! in_array($status, self::AVAILABLE_STATUSES, true)) {
                throw new \InvalidArgumentException(
                    sprintf('Value provided for %s parameter is not supported (was: %s)', $parameterName, $status)
                );
            }
        }
    }

    /**
     * Checks that states filter provided in the payload are supported.
     *
     * @param string $parameterName
     * @param array<int, string|int> $states
     *
     * @throws \InvalidArgumentException
     */
    private function validateStatesFilterOrFail(string $parameterName, array $states): void
    {
        $this->validateArrayOfStringOrFail($parameterName, $states);
        foreach ($states as $state) {
            if (! in_array($state, self::AVAILABLE_STATES, true)) {
                throw new \InvalidArgumentException(
                    sprintf('Value provided for %s parameter is not supported (was: %s)', $parameterName, $state)
                );
            }
        }
    }

    /**
     * Checks that status types filter provided in the payload are supported.
     *
     * @param string $parameterName
     * @param array<int, string|int> $statusTypes
     *
     * @throws \InvalidArgumentException
     */
    private function validateStatusTypesOrFail(string $parameterName, array $statusTypes): void
    {
        $this->validateArrayOfStringOrFail($parameterName, $statusTypes);
        foreach ($statusTypes as $statusType) {
            if (! in_array($statusType, self::AVAILABLE_STATUS_TYPES, true)) {
                throw new \InvalidArgumentException(
                    sprintf('Value provided for %s parameter is not supported (was: %s)', $parameterName, $statusType)
                );
            }
        }
    }
}
