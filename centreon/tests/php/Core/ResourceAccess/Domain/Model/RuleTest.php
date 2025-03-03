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

namespace Tests\Core\ResourceAccess\Domain\Model;

use Assert\InvalidArgumentException;
use Centreon\Domain\Common\Assertion\AssertionException;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilter;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilterValidator;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostCategoryFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostGroupFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\MetaServiceFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\ServiceCategoryFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\ServiceFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\ServiceGroupFilterType;
use Core\ResourceAccess\Domain\Model\Rule;

beforeEach(function (): void {
    $this->contacts = [1, 2];
    $this->contactGroups = [3, 4];
    $this->resources = [10, 11];

    foreach ([
        HostFilterType::class,
        HostGroupFilterType::class,
        HostCategoryFilterType::class,
        ServiceFilterType::class,
        ServiceGroupFilterType::class,
        ServiceCategoryFilterType::class,
        MetaServiceFilterType::class,
    ] as $className) {
        $this->filterTypes[] = new $className();
    }

    $this->validator = new DatasetFilterValidator(new \ArrayObject($this->filterTypes));
    $this->datasets = [new DatasetFilter('host', $this->resources, $this->validator)];
});

it('should return properly set Rule instance (all properties)', function (): void {
    $rule = new Rule(
        id: 1,
        name: 'FULL',
        description: 'Full access',
        linkedContacts: $this->contacts,
        linkedContactGroups: $this->contactGroups,
        datasets: $this->datasets,
        isEnabled: true
    );

    $dataset = ($rule->getDatasetFilters())[0];

    expect($rule->getId())->toBe(1)
        ->and($rule->getName())->toBe('FULL')
        ->and($rule->getDescription())->toBe('Full access')
        ->and($rule->getLinkedContactIds())->toBe([1, 2])
        ->and($rule->getLinkedContactGroupIds())->toBe([3, 4])
        ->and($rule->isEnabled())->toBe(true);

    expect($dataset->getType())->toBe('host')
        ->and($dataset->getResourceIds())->toBe([10, 11])
        ->and($dataset->getDatasetFilter())->toBeNull();
});

it('should return properly the name of the rule correctly formatted', function (): void {
    $rule = new Rule(
        id: 1,
        name: 'FULL access',
        description: 'Full access',
        linkedContacts: $this->contacts,
        linkedContactGroups: $this->contactGroups,
        datasets: $this->datasets,
        isEnabled: true
    );

    $dataset = ($rule->getDatasetFilters())[0];

    expect($rule->getId())->toBe(1)
        ->and($rule->getName())->toBe('FULL_access')
        ->and($rule->getDescription())->toBe('Full access')
        ->and($rule->getLinkedContactIds())->toBe([1, 2])
        ->and($rule->getLinkedContactGroupIds())->toBe([3, 4])
        ->and($rule->isEnabled())->toBe(true);

    expect($dataset->getType())->toBe('host')
        ->and($dataset->getResourceIds())->toBe([10, 11])
        ->and($dataset->getDatasetFilter())->toBeNull();
});

it('should throw an exception when rules id is not a positive int', function (): void {
    new Rule(
        id: 0,
        name: 'FULL',
        description: 'Full access',
        linkedContacts: $this->contacts,
        linkedContactGroups: $this->contactGroups,
        datasets: $this->datasets,
        isEnabled: true
    );
})->throws(
    InvalidArgumentException::class,
    AssertionException::positiveInt(0, 'Rule::id')->getMessage()
);

it('should throw an exception when rules name is an empty string', function (): void {
    new Rule(
        id: 1,
        name: '',
        description: 'Full access',
        linkedContacts: $this->contacts,
        linkedContactGroups: $this->contactGroups,
        datasets: $this->datasets,
        isEnabled: true
    );
})->throws(
    InvalidArgumentException::class,
    AssertionException::notEmptyString('Rule::name')->getMessage()
);

it('should throw an exception when rules name is an string exceeding max size', function (): void {
    new Rule(
        id: 1,
        name: str_repeat('a', Rule::MAX_NAME_LENGTH + 1),
        description: 'Full access',
        linkedContacts: $this->contacts,
        linkedContactGroups: $this->contactGroups,
        datasets: $this->datasets,
        isEnabled: true
    );
})->throws(
    InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', Rule::MAX_NAME_LENGTH + 1),
        Rule::MAX_NAME_LENGTH + 1,
        Rule::MAX_NAME_LENGTH,
        'Rule::name'
    )->getMessage(),
);

it('should throw an exception when linked contacts is not an array of int', function (): void {
    new Rule(
        id: 1,
        name: 'FULL',
        description: 'Full access',
        linkedContacts: ['one', 'two'],
        linkedContactGroups: $this->contactGroups,
        datasets: $this->datasets,
        isEnabled: true
    );
})->throws(
    InvalidArgumentException::class,
    AssertionException::invalidTypeInArray('int', 'Rule::linkedContactIds')->getMessage()
);

it('should throw an exception when linked contact groups is not an array of int', function (): void {
    new Rule(
        id: 1,
        name: 'FULL',
        description: 'Full access',
        linkedContacts: $this->contacts,
        linkedContactGroups: ['one', 'two'],
        datasets: $this->datasets,
        isEnabled: true
    );
})->throws(
    InvalidArgumentException::class,
    AssertionException::invalidTypeInArray('int', 'Rule::linkedContactGroupIds')->getMessage()
);

it('should throw an exception when linked dataset filters is an empty array', function (): void {
    new Rule(
        id: 1,
        name: 'FULL',
        description: 'Full access',
        linkedContacts: $this->contacts,
        linkedContactGroups: $this->contactGroups,
        datasets: [],
        isEnabled: true
    );
})->throws(
    InvalidArgumentException::class,
    AssertionException::notEmpty('Rule::datasetFilters')->getMessage()
);
