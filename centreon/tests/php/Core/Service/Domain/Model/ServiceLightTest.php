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

namespace Tests\Core\Service\Domain\Model;

use Assert\InvalidArgumentException;
use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Common\Domain\SimpleEntity;
use Core\Common\Domain\TrimmedString;
use Core\Service\Domain\Model\NewService;
use Core\Service\Domain\Model\ServiceLight;
use Core\ServiceGroup\Domain\Model\ServiceGroupRelation;

beforeEach(function (): void {
    $this->createService = static fn(array $fields = []): ServiceLight => new ServiceLight(
        ...[
            'id' => 1,
            'name' => new TrimmedString('service-name'),
            'hostIds' => [3],
            'categoryIds' => [],
            'groups' => [new ServiceGroupRelation(2, 1, 3)],
            'serviceTemplate' => new SimpleEntity(1, new TrimmedString('serviceTemplate-name'), 'ServiceLigth::serviceTemplate'),
            'notificationTimePeriod' => new SimpleEntity(1, new TrimmedString('notificationTimePeriod-name'), 'ServiceLigth::notificationTimePeriod'),
            'checkTimePeriod' => new SimpleEntity(1, new TrimmedString('checkTimePeriod-name'), 'ServiceLigth::checkTimePeriod'),
            'severity' => new SimpleEntity(1, new TrimmedString('severity-name'), 'ServiceLigth::severity'),
            'normalCheckInterval' => 5,
            'retryCheckInterval' => 1,
            'isActivated' => true,
            ...$fields,
        ]
    );
});

it('should return properly set service instance (all properties)', function (): void {
    $service = ($this->createService)();

    expect($service->getId())->toBe(1)
        ->and($service->getName())->toBe('service-name')
        ->and($service->getHostIds())->toBe([3])
        ->and($service->getCategoryIds())->toBe([])
        ->and($service->getGroups()[0]->getServiceGroupId())->toBe(2)
        ->and($service->getServiceTemplate()->getName())->toBe('serviceTemplate-name')
        ->and($service->getNotificationTimePeriod()->getName())->toBe('notificationTimePeriod-name')
        ->and($service->getCheckTimePeriod()->getName())->toBe('checkTimePeriod-name')
        ->and($service->getSeverity()->getName())->toBe('severity-name')
        ->and($service->getNormalCheckInterval())->toBe(5)
        ->and($service->getRetryCheckInterval())->toBe(1)
        ->and($service->isActivated())->toBe(true);
});

it('should return properly set host instance (mandatory properties only)', function (): void {
    $service = new ServiceLight(id: 1, name: new TrimmedString('service-name'), hostIds: [1]);

    expect($service->getId())->toBe(1)
        ->and($service->getName())->toBe('service-name')
        ->and($service->getHostIds())->toBe([1])
        ->and($service->getCategoryIds())->toBe([])
        ->and($service->getGroups())->toBe([])
        ->and($service->getServiceTemplate())->toBe(null)
        ->and($service->getNotificationTimePeriod())->toBe(null)
        ->and($service->getCheckTimePeriod())->toBe(null)
        ->and($service->getSeverity())->toBe(null)
        ->and($service->getNormalCheckInterval())->toBe(null)
        ->and($service->getRetryCheckInterval())->toBe(null)
        ->and($service->isActivated())->toBe(true);
});

// mandatory fields
it(
    'should throw an exception when service name is an empty string',
    fn() => ($this->createService)(['name' => new TrimmedString('  ')])
)->throws(
    InvalidArgumentException::class,
    AssertionException::notEmptyString('ServiceLight::name')->getMessage()
);

// too long field
$tooLong = str_repeat('a', NewService::MAX_NAME_LENGTH + 1);
it(
    'should throw an exception when service name is too long',
    fn() => ($this->createService)(['name' => new TrimmedString($tooLong)])
)->throws(
    InvalidArgumentException::class,
    AssertionException::maxLength(
        $tooLong,
        NewService::MAX_NAME_LENGTH + 1,
        NewService::MAX_NAME_LENGTH,
        'ServiceLight::name'
    )->getMessage()
);