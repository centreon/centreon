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

namespace Tests\Core\Resources\Infrastructure\API\FindResources;

use Core\Domain\RealTime\ResourceTypeInterface;
use Core\Resources\Infrastructure\API\FindResources\FindResourcesRequestValidator as Validator;

$fakeProviderName = 'awesomeProvider';

beforeEach(function () use ($fakeProviderName): void {
    $this->expectInvalidArgumentException = function (callable $function, int $expectedCode): void {
        $unexpected = 'It was expected to raise an ' . \InvalidArgumentException::class . " with code {$expectedCode}";

        try {
            $function();
            $this->fail($unexpected);
        } catch (\InvalidArgumentException $exception) {
            if ($expectedCode === $exception->getCode()) {
                // We expect exactly this InvalidArgumentException with this code.
                expect(true)->toBeTrue();

                return;
            }

            $this->fail($unexpected . ' but got a code ' . $exception->getCode());
        } catch (\Throwable $exception) {
            $this->fail($unexpected . ' but got a ' . $exception::class);
        }
    };

    $this->fakeProvider = $this->createMock(ResourceTypeInterface::class);
    $this->fakeProvider->method('getName')->willReturn($fakeProviderName);
    $this->fakeValidator = new Validator(new \ArrayIterator([$this->fakeProvider]));
});

// ——— FAILING tests ———

it(
    'should fail if there are no providers',
    function (): void {
        ($this->expectInvalidArgumentException)(
            fn() => new Validator(new \EmptyIterator()),
            Validator::ERROR_NO_PROVIDERS
        );
    }
);

$dataset = [
    ['not_a_valid_field', Validator::ERROR_UNKNOWN_PARAMETER, null],
    [Validator::PARAM_RESOURCE_TYPE, Validator::ERROR_NOT_A_RESOURCE_TYPE, ['foo']],
    [Validator::PARAM_RESOURCE_TYPE, Validator::ERROR_NOT_AN_ARRAY_OF_STRING, [123]],
    [Validator::PARAM_RESOURCE_TYPE, Validator::ERROR_NOT_AN_ARRAY, 'bar'],
    [Validator::PARAM_STATES, Validator::ERROR_NOT_A_STATE, ['foo']],
    [Validator::PARAM_STATES, Validator::ERROR_NOT_AN_ARRAY_OF_STRING, [123]],
    [Validator::PARAM_STATES, Validator::ERROR_NOT_AN_ARRAY, 'bar'],
    [Validator::PARAM_STATUSES, Validator::ERROR_NOT_A_STATUS, ['foo']],
    [Validator::PARAM_STATUSES, Validator::ERROR_NOT_AN_ARRAY_OF_STRING, [123]],
    [Validator::PARAM_STATUSES, Validator::ERROR_NOT_AN_ARRAY, 'bar'],
    [Validator::PARAM_HOSTGROUP_NAMES, Validator::ERROR_NOT_AN_ARRAY_OF_STRING, [123]],
    [Validator::PARAM_SERVICEGROUP_NAMES, Validator::ERROR_NOT_AN_ARRAY_OF_STRING, [123]],
    [Validator::PARAM_MONITORING_SERVER_NAMES, Validator::ERROR_NOT_AN_ARRAY_OF_STRING, [123]],
    [Validator::PARAM_SERVICE_CATEGORY_NAMES, Validator::ERROR_NOT_AN_ARRAY_OF_STRING, [123]],
    [Validator::PARAM_HOST_CATEGORY_NAMES, Validator::ERROR_NOT_AN_ARRAY_OF_STRING, [123]],
    [Validator::PARAM_SERVICE_SEVERITY_NAMES, Validator::ERROR_NOT_AN_ARRAY_OF_STRING, [123]],
    [Validator::PARAM_HOST_SEVERITY_NAMES, Validator::ERROR_NOT_AN_ARRAY_OF_STRING, [123]],
    [Validator::PARAM_HOST_SEVERITY_LEVELS, Validator::ERROR_NOT_AN_ARRAY_OF_INTEGER, ['foo']],
    [Validator::PARAM_SERVICE_SEVERITY_LEVELS, Validator::ERROR_NOT_AN_ARRAY_OF_INTEGER, ['foo']],
    [Validator::PARAM_STATUS_TYPES, Validator::ERROR_NOT_A_STATUS_TYPE, ['foo']],
    [Validator::PARAM_STATUS_TYPES, Validator::ERROR_NOT_AN_ARRAY_OF_STRING, [123]],
    [Validator::PARAM_STATUS_TYPES, Validator::ERROR_NOT_AN_ARRAY, 'bar'],
    [Validator::PARAM_RESOURCES_ON_PERFORMANCE_DATA_AVAILABILITY, Validator::ERROR_NOT_A_BOOLEAN, 42],
    [Validator::PARAM_OPEN_TICKET_RULE_ID, Validator::ERROR_NOT_A_INT, true],
    [Validator::PARAM_RESOURCES_WITH_OPENED_TICKETS, Validator::ERROR_NOT_A_BOOLEAN, 42],
];

foreach ($dataset as [$field, $expectedCode, $value]) {
    $message = match ($expectedCode) {
        Validator::ERROR_UNKNOWN_PARAMETER => 'is an unknown parameter',
        Validator::ERROR_NOT_A_RESOURCE_TYPE => 'is not a resource type',
        Validator::ERROR_NOT_A_STATUS => 'is not a status',
        Validator::ERROR_NOT_A_STATE => 'is not a state',
        Validator::ERROR_NOT_A_STATUS_TYPE => 'is not a status_type',
        Validator::ERROR_NOT_AN_ARRAY_OF_STRING => 'is not an array of string',
        Validator::ERROR_NOT_AN_ARRAY_OF_INTEGER => 'is not an array of integer',
        Validator::ERROR_NOT_AN_ARRAY => 'is not an array',
        Validator::ERROR_NOT_A_BOOLEAN => 'is not a boolean',
        Validator::ERROR_NO_PROVIDERS => 'has no providers',
        default => 'is not valid',
    };
    it(
        "should fail if the field '{$field}' {$message}",
        function () use ($field, $expectedCode, $value): void {
            ($this->expectInvalidArgumentException)(
                fn() => $this->fakeValidator->validateAndRetrieveRequestParameters([$field => $value]),
                $expectedCode
            );
        }
    );
}

// ——— SUCCESS tests ———

$datasetGenerator = function () use ($fakeProviderName): \Generator {
    $dataset = [
        [Validator::PARAM_RESOURCE_TYPE, [$fakeProviderName]],
        [Validator::PARAM_STATES, Validator::ALLOWED_STATES],
        [Validator::PARAM_STATES, [Validator::ALLOWED_STATES[0]]],
        [Validator::PARAM_STATES, []],
        [Validator::PARAM_STATUSES, Validator::ALLOWED_STATUSES],
        [Validator::PARAM_STATUSES, [Validator::ALLOWED_STATUSES[0]]],
        [Validator::PARAM_STATUSES, []],
        [Validator::PARAM_HOSTGROUP_NAMES, ['foo', 'bar']],
        [Validator::PARAM_HOSTGROUP_NAMES, []],
        [Validator::PARAM_SERVICEGROUP_NAMES, ['foo', 'bar']],
        [Validator::PARAM_SERVICEGROUP_NAMES, []],
        [Validator::PARAM_MONITORING_SERVER_NAMES, ['foo', 'bar']],
        [Validator::PARAM_MONITORING_SERVER_NAMES, []],
        [Validator::PARAM_SERVICE_CATEGORY_NAMES, ['foo', 'bar']],
        [Validator::PARAM_SERVICE_CATEGORY_NAMES, []],
        [Validator::PARAM_HOST_CATEGORY_NAMES, ['foo', 'bar']],
        [Validator::PARAM_HOST_CATEGORY_NAMES, []],
        [Validator::PARAM_SERVICE_SEVERITY_NAMES, ['foo', 'bar']],
        [Validator::PARAM_SERVICE_SEVERITY_NAMES, []],
        [Validator::PARAM_HOST_SEVERITY_NAMES, ['foo', 'bar']],
        [Validator::PARAM_HOST_SEVERITY_NAMES, []],
        [Validator::PARAM_HOST_SEVERITY_LEVELS, [1, 2, 3]],
        [Validator::PARAM_HOST_SEVERITY_LEVELS, [1, 2, 3], ['1', '2', '3']], // autocast int
        [Validator::PARAM_HOST_SEVERITY_LEVELS, []],
        [Validator::PARAM_SERVICE_SEVERITY_LEVELS, [1, 2, 3]],
        [Validator::PARAM_SERVICE_SEVERITY_LEVELS, [1, 2, 3], ['1', '2', '3']], // autocast int
        [Validator::PARAM_SERVICE_SEVERITY_LEVELS, []],
        [Validator::PARAM_STATUS_TYPES, Validator::ALLOWED_STATUS_TYPES],
        [Validator::PARAM_STATUS_TYPES, [Validator::ALLOWED_STATUS_TYPES[0]]],
        [Validator::PARAM_STATUS_TYPES, []],
        [Validator::PARAM_RESOURCES_ON_PERFORMANCE_DATA_AVAILABILITY, true],
        [Validator::PARAM_RESOURCES_ON_PERFORMANCE_DATA_AVAILABILITY, false],
        [Validator::PARAM_RESOURCES_WITH_OPENED_TICKETS, true],
        [Validator::PARAM_RESOURCES_WITH_OPENED_TICKETS, false],
        [Validator::PARAM_OPEN_TICKET_RULE_ID, 42],
    ];
    foreach ($dataset as $args) {
        [$field, $expected, $value] = [$args[0], $args[1], $args[2] ?? $args[1]];

        yield $field . ' = ' . json_encode($value) => [$field, $expected, $value];
    }
};

it(
    'should validate',
    function ($field, $expected, $value): void {
        $validated = $this->fakeValidator->validateAndRetrieveRequestParameters([$field => $value]);

        expect($validated[$field] ?? null)->toBe($expected);
    }
)->with($datasetGenerator());
