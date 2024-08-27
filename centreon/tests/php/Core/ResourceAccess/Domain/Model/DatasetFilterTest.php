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

beforeEach(function (): void {
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
    $this->datasetFilter = new DatasetFilter(type: 'host', resourceIds: [1, 2], validator: $this->validator);
});

it('should return properly set DatasetFilter instance (all properties)', function (): void {
    $datasetFilter = new DatasetFilter(
        type: 'hostgroup',
        resourceIds: [1],
        validator: $this->validator
    );

    $datasetFilter->setDatasetFilter($this->datasetFilter);

    expect($datasetFilter->getType())->toBe('hostgroup')
        ->and($datasetFilter->getResourceIds())->toBe([1])
        ->and($datasetFilter->getDatasetFilter()->getType())->toBe('host')
        ->and($datasetFilter->getDatasetFilter()->getResourceIds())->toBe([1, 2])
        ->and($datasetFilter->getDatasetFilter()->getDatasetFilter())->toBeNull();
});

it('should throw an exception when dataset type is not an empty string', function (): void {
    new DatasetFilter(
        type: '',
        resourceIds: [1],
        validator: $this->validator
    );
})->throws(
    InvalidArgumentException::class,
    AssertionException::notEmptyString('DatasetFilter::type')->getMessage()
);

it('should throw an exception when dataset type is not part of allowed types', function (): void {
    new DatasetFilter(
        type: 'typo',
        resourceIds: [1],
        validator: $this->validator
    );
})->throws(
    \InvalidArgumentException::class,
    'Value provided is not supported for dataset filter type (was: typo)'
);

it('should throw an exception when resources is an empty array with type not compatible with all_<type> feature', function (): void {
    new DatasetFilter(
        type: 'service_category',
        resourceIds: [],
        validator: $this->validator
    );
})->throws(
    InvalidArgumentException::class,
    AssertionException::notEmpty('DatasetFilter::resourceIds')->getMessage()
);

it('should throw an exception when resources is an array of not integers', function (): void {
    new DatasetFilter(
        type: 'host',
        resourceIds: ['resource1', 'resource2'],
        validator: $this->validator
    );
})->throws(
    InvalidArgumentException::class,
    AssertionException::invalidTypeInArray('int', 'DatasetFilter::resourceIds')->getMessage()
);

// Host hierarchy validation
foreach (
    [
        HostCategoryFilterType::TYPE_NAME,
        HostGroupFilterType::TYPE_NAME,
        HostFilterType::TYPE_NAME,
        MetaServiceFilterType::TYPE_NAME,
    ] as $type
) {
    it(
        "should throw an exception for host parent filter with {$type} as child",
        function () use ($type): void {
            $datasetFilter = new DatasetFilter('host', [1, 2], $this->validator);
            $datasetFilter->setDatasetFilter(
                new DatasetFilter($type, [3, 4], $this->validator)
            );
        }
    )->throws(
        \InvalidArgumentException::class,
        "Dataset filter hierarchy assertion failed ({$type} not a sub-filter of host)"
    );
}

// Hostgroups hierarchy validation
foreach (
    [
        HostGroupFilterType::TYPE_NAME,
        MetaServiceFilterType::TYPE_NAME,
    ] as $type
) {
    it(
        "should throw an exception for hostgroup parent filter with {$type} as child",
        function () use ($type): void {
            $datasetFilter = new DatasetFilter('hostgroup', [1, 2], $this->validator);
            $datasetFilter->setDatasetFilter(
                new DatasetFilter($type, [3, 4], $this->validator)
            );
        }
    )->throws(
        \InvalidArgumentException::class,
        "Dataset filter hierarchy assertion failed ({$type} not a sub-filter of hostgroup)"
    );
}

// HostCategory hierarchy validation
foreach (
    [
        HostCategoryFilterType::TYPE_NAME,
        MetaServiceFilterType::TYPE_NAME,
    ] as $type
) {
    it(
        "should throw an exception for host_category parent filter with {$type} as child",
        function () use ($type): void {
            $datasetFilter = new DatasetFilter('host_category', [1, 2], $this->validator);
            $datasetFilter->setDatasetFilter(
                new DatasetFilter($type, [3, 4], $this->validator)
            );
        }
    )->throws(
        \InvalidArgumentException::class,
        "Dataset filter hierarchy assertion failed ({$type} not a sub-filter of host_category)"
    );
}

// Service hierarchy validation
foreach (
    [
        HostGroupFilterType::TYPE_NAME,
        HostFilterType::TYPE_NAME,
        HostCategoryFilterType::TYPE_NAME,
        ServiceGroupFilterType::TYPE_NAME,
        ServiceCategoryFilterType::TYPE_NAME,
        ServiceFilterType::TYPE_NAME,
        MetaServiceFilterType::TYPE_NAME,
    ] as $type
) {
    it(
        "should throw an exception for service parent filter with {$type} as child (no sub-filter possible)",
        function () use ($type): void {
            $datasetFilter = new DatasetFilter('service', [1, 2], $this->validator);
            $datasetFilter->setDatasetFilter(
                new DatasetFilter($type, [3, 4], $this->validator)
            );
        }
    )->throws(
        \InvalidArgumentException::class,
        "Dataset filter hierarchy assertion failed ({$type} not a sub-filter of service)"
    );
}

// MetaService hierarchy validation
foreach (
    [
        HostGroupFilterType::TYPE_NAME,
        HostFilterType::TYPE_NAME,
        HostCategoryFilterType::TYPE_NAME,
        ServiceGroupFilterType::TYPE_NAME,
        ServiceCategoryFilterType::TYPE_NAME,
        ServiceFilterType::TYPE_NAME,
        MetaServiceFilterType::TYPE_NAME,
    ] as $type
) {
    it(
        "should throw an exception for meta_service parent filter with {$type} as child (no sub-filter possible)",
        function () use ($type): void {
            $datasetFilter = new DatasetFilter('meta_service', [1, 2], $this->validator);
            $datasetFilter->setDatasetFilter(
                new DatasetFilter($type, [3, 4], $this->validator)
            );
        }
    )->throws(
        \InvalidArgumentException::class,
        "Dataset filter hierarchy assertion failed ({$type} not a sub-filter of meta_service)"
    );
}

// Servicegroup hierarchy validation
foreach (
    [
        HostGroupFilterType::TYPE_NAME,
        HostFilterType::TYPE_NAME,
        HostCategoryFilterType::TYPE_NAME,
        ServiceGroupFilterType::TYPE_NAME,
        MetaServiceFilterType::TYPE_NAME,
    ] as $type
) {
    it(
        "should throw an exception for servicegroup parent filter with {$type} as child",
        function () use ($type): void {
            $datasetFilter = new DatasetFilter('servicegroup', [1, 2], $this->validator);
            $datasetFilter->setDatasetFilter(
                new DatasetFilter($type, [3, 4], $this->validator)
            );
        }
    )->throws(
        \InvalidArgumentException::class,
        "Dataset filter hierarchy assertion failed ({$type} not a sub-filter of servicegroup)"
    );
}

// ServiceCategory hierarchy validation
foreach (
    [
        HostGroupFilterType::TYPE_NAME,
        HostFilterType::TYPE_NAME,
        HostCategoryFilterType::TYPE_NAME,
        ServiceCategoryFilterType::TYPE_NAME,
        MetaServiceFilterType::TYPE_NAME,
    ] as $type
) {
    it(
        "should throw an exception for service_category parent filter with {$type} as child",
        function () use ($type): void {
            $datasetFilter = new DatasetFilter('service_category', [1, 2], $this->validator);
            $datasetFilter->setDatasetFilter(
                new DatasetFilter($type, [3, 4], $this->validator)
            );
        }
    )->throws(
        \InvalidArgumentException::class,
        "Dataset filter hierarchy assertion failed ({$type} not a sub-filter of service_category)"
    );
}
