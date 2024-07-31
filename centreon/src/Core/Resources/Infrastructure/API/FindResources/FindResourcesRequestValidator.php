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
 *      only_with_performance_data: bool,
 *      only_with_opened_tickets: bool,
 *      ticket_provider_id: int|null
 * }
 */
final class FindResourcesRequestValidator
{
    use LoggerTrait;

    // Query parameters fields.
    public const PARAM_RESOURCE_TYPE = 'types';
    public const PARAM_STATES = 'states';
    public const PARAM_STATUSES = 'statuses';
    public const PARAM_HOSTGROUP_NAMES = 'hostgroup_names';
    public const PARAM_SERVICEGROUP_NAMES = 'servicegroup_names';
    public const PARAM_MONITORING_SERVER_NAMES = 'monitoring_server_names';
    public const PARAM_SERVICE_CATEGORY_NAMES = 'service_category_names';
    public const PARAM_HOST_CATEGORY_NAMES = 'host_category_names';
    public const PARAM_SERVICE_SEVERITY_NAMES = 'service_severity_names';
    public const PARAM_HOST_SEVERITY_NAMES = 'host_severity_names';
    public const PARAM_HOST_SEVERITY_LEVELS = 'host_severity_levels';
    public const PARAM_SERVICE_SEVERITY_LEVELS = 'service_severity_levels';
    public const PARAM_STATUS_TYPES = 'status_types';
    public const PARAM_RESOURCES_ON_PERFORMANCE_DATA_AVAILABILITY = 'only_with_performance_data';
    public const PARAM_RESOURCES_WITH_OPENED_TICKETS = 'only_with_opened_tickets';
    public const PARAM_OPEN_TICKET_RULE_ID = 'ticket_provider_id';

    // Errors Codes for exceptions.
    public const ERROR_UNKNOWN_PARAMETER = 1;
    public const ERROR_NOT_A_RESOURCE_TYPE = 2;
    public const ERROR_NOT_A_STATUS = 3;
    public const ERROR_NOT_A_STATE = 4;
    public const ERROR_NOT_A_STATUS_TYPE = 5;
    public const ERROR_NOT_AN_ARRAY_OF_STRING = 6;
    public const ERROR_NOT_AN_ARRAY_OF_INTEGER = 7;
    public const ERROR_NOT_AN_ARRAY = 8;
    public const ERROR_NOT_A_BOOLEAN = 9;
    public const ERROR_NO_PROVIDERS = 10;
    public const ERROR_NOT_A_INT = 11;

    /** Allowed values for statuses. */
    public const ALLOWED_STATUSES = [
        'OK',
        'WARNING',
        'CRITICAL',
        'UNKNOWN',
        'UNREACHABLE',
        'PENDING',
        'UP',
        'DOWN',
    ];

    /** Allowed values for states. */
    public const ALLOWED_STATES = [
        'unhandled_problems',
        'resources_problems',
        'in_downtime',
        'acknowledged',
    ];

    /** Allowed values for status types. */
    public const ALLOWED_STATUS_TYPES = [
        'hard',
        'soft',
    ];

    /** @var _RequestParameters */
    private const EMPTY_FILTERS = [
        self::PARAM_RESOURCE_TYPE => [],
        self::PARAM_STATES => [],
        self::PARAM_STATUSES => [],
        self::PARAM_HOSTGROUP_NAMES => [],
        self::PARAM_SERVICEGROUP_NAMES => [],
        self::PARAM_MONITORING_SERVER_NAMES => [],
        self::PARAM_SERVICE_CATEGORY_NAMES => [],
        self::PARAM_HOST_CATEGORY_NAMES => [],
        self::PARAM_SERVICE_SEVERITY_NAMES => [],
        self::PARAM_HOST_SEVERITY_NAMES => [],
        self::PARAM_HOST_SEVERITY_LEVELS => [],
        self::PARAM_SERVICE_SEVERITY_LEVELS => [],
        self::PARAM_STATUS_TYPES => [],
        self::PARAM_RESOURCES_ON_PERFORMANCE_DATA_AVAILABILITY => false,
        self::PARAM_RESOURCES_WITH_OPENED_TICKETS => false,
        self::PARAM_OPEN_TICKET_RULE_ID => null,
    ];

    /** Query parameters that should be ignored but not forbidden. */
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
        $this->resourceTypes = array_map(
            static fn(ResourceTypeInterface $resourceType): string => $resourceType->getName(),
            iterator_to_array($resourceTypes)
        );

        if ([] === $this->resourceTypes) {
            throw new \InvalidArgumentException(
                'You must add at least one provider',
                self::ERROR_NO_PROVIDERS
            );
        }
    }

    /**
     * @return array<string>
     */
    public function getResourceTypes(): array
    {
        return $this->resourceTypes;
    }

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

        // Do not handle pagination query parameters and check that parameters are handled
        foreach ($queryParameters as $param => $data) {
            // skip pagination parameters
            if (\in_array($param, self::PAGINATION_PARAMETERS, true)) {
                continue;
            }

            // do not allow query parameters not managed
            if (! \array_key_exists($param, $filterData)) {
                throw new \InvalidArgumentException(
                    'Request parameter provided not handled',
                    self::ERROR_UNKNOWN_PARAMETER
                );
            }

            $value = $this->tryJsonDecodeParameterValue($data);

            switch ($param) {
                case self::PARAM_RESOURCE_TYPE:
                    $filterData[$param] = $this->ensureResourceTypeFilter($param, $value);
                    break;
                case self::PARAM_STATES:
                    $filterData[$param] = $this->ensureStatesFilter($param, $value);
                    break;
                case self::PARAM_STATUSES:
                    $filterData[$param] = $this->ensureStatusesFilter($param, $value);
                    break;
                case self::PARAM_HOSTGROUP_NAMES:
                case self::PARAM_SERVICEGROUP_NAMES:
                case self::PARAM_MONITORING_SERVER_NAMES:
                case self::PARAM_SERVICE_CATEGORY_NAMES:
                case self::PARAM_HOST_CATEGORY_NAMES:
                case self::PARAM_SERVICE_SEVERITY_NAMES:
                case self::PARAM_HOST_SEVERITY_NAMES:
                    $filterData[$param] = $this->ensureArrayOfString($param, $value);
                    break;
                case self::PARAM_HOST_SEVERITY_LEVELS:
                case self::PARAM_SERVICE_SEVERITY_LEVELS:
                    $filterData[$param] = $this->ensureArrayOfInteger($param, $value);
                    break;
                case self::PARAM_STATUS_TYPES:
                    $filterData[$param] = $this->ensureStatusTypes($param, $value);
                    break;
                case self::PARAM_RESOURCES_ON_PERFORMANCE_DATA_AVAILABILITY:
                    $filterData[$param] = $this->ensureBoolean($param, $value);
                    break;
                case self::PARAM_RESOURCES_WITH_OPENED_TICKETS:
                    $filterData[$param] = $this->ensureBoolean($param, $value);
                    break;
                case self::PARAM_OPEN_TICKET_RULE_ID:
                    $filterData[$param] = $this->ensureInt($param, $value);
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

                throw new \InvalidArgumentException($message, self::ERROR_NOT_A_RESOURCE_TYPE);
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
     * @return list<value-of<self::ALLOWED_STATUSES>>
     */
    private function ensureStatusesFilter(string $parameterName, mixed $values): array
    {
        $statuses = [];
        foreach ($this->ensureArrayOfString($parameterName, $values) as $string) {
            if (! \in_array($string, self::ALLOWED_STATUSES, true)) {
                $message = sprintf('Value provided for %s parameter is not supported (was: %s)', $parameterName, $string);

                throw new \InvalidArgumentException($message, self::ERROR_NOT_A_STATUS);
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
     * @return list<value-of<self::ALLOWED_STATES>>
     */
    private function ensureStatesFilter(string $parameterName, mixed $values): array
    {
        $states = [];
        foreach ($this->ensureArrayOfString($parameterName, $values) as $string) {
            if (! \in_array($string, self::ALLOWED_STATES, true)) {
                $message = sprintf('Value provided for %s parameter is not supported (was: %s)', $parameterName, $string);

                throw new \InvalidArgumentException($message, self::ERROR_NOT_A_STATE);
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
     * @return list<value-of<self::ALLOWED_STATUS_TYPES>>
     */
    private function ensureStatusTypes(string $parameterName, mixed $values): array
    {
        $statusTypes = [];
        foreach ($this->ensureArrayOfString($parameterName, $values) as $string) {
            if (! \in_array($string, self::ALLOWED_STATUS_TYPES, true)) {
                $message = sprintf('Value provided for %s parameter is not supported (was: %s)', $parameterName, $string);

                throw new \InvalidArgumentException($message, self::ERROR_NOT_A_STATUS_TYPE);
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

                throw new \InvalidArgumentException($message, self::ERROR_NOT_AN_ARRAY_OF_STRING);
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

                throw new \InvalidArgumentException($message, self::ERROR_NOT_AN_ARRAY_OF_INTEGER);
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

            throw new \InvalidArgumentException($message, self::ERROR_NOT_AN_ARRAY);
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
                sprintf('Value provided for %s is not correctly formatted. Boolean expected', $parameterName),
                self::ERROR_NOT_A_BOOLEAN
            );
        }

        return $value;
    }

    /**
     * Ensures that value provided is a integer.
     *
     * @param string $parameterName
     * @param mixed $value
     *
     * @throws \InvalidArgumentException
     *
     * @return int
     */
    private function ensureInt(string $parameterName, mixed $value): int
    {
        if (! \is_int($value)) {
            throw new \InvalidArgumentException(
                sprintf('Value provided for %s is not correctly formatted. Integer expected', $parameterName),
                self::ERROR_NOT_A_INT
            );
        }

        return $value;
    }
}
