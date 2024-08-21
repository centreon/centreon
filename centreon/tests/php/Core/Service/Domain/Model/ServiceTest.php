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
use Core\Common\Domain\YesNoDefault;
use Core\Domain\Common\GeoCoords;
use Core\Service\Domain\Model\NotificationType;
use Core\Service\Domain\Model\Service;

beforeEach(function (): void {
    $this->createService = static fn(array $fields = []): Service => new Service(
        ...[
            'id' => 1,
            'name' => 'service-name',
            'hostId' => 1,
            'commandId' => 1,
            'commandArguments' => ['args1', 'args2'],
            'eventHandlerArguments' => ['args3', 'args4'],
            'notificationTypes' => [NotificationType::Warning, NotificationType::Critical],
            'contactAdditiveInheritance' => false,
            'contactGroupAdditiveInheritance' => false,
            'isActivated' => true,
            'activeChecks' => YesNoDefault::Yes,
            'passiveCheck' => YesNoDefault::No,
            'volatility' => YesNoDefault::Yes,
            'checkFreshness' => YesNoDefault::No,
            'eventHandlerEnabled' => YesNoDefault::Yes,
            'flapDetectionEnabled' => YesNoDefault::No,
            'notificationsEnabled' => YesNoDefault::Yes,
            'comment' => 'some-comment',
            'note' => 'some-note',
            'noteUrl' => 'some-url',
            'actionUrl' => 'some-action-url',
            'iconAlternativeText' => 'icon-alt',
            'graphTemplateId' => 12,
            'serviceTemplateParentId' => 52,
            'eventHandlerId' => 14,
            'notificationTimePeriodId' => 65,
            'checkTimePeriodId' => 82,
            'iconId' => 27,
            'severityId' => 16,
            'maxCheckAttempts' => 3,
            'normalCheckInterval' => 5,
            'retryCheckInterval' => 1,
            'freshnessThreshold' => 12,
            'lowFlapThreshold' => 6,
            'highFlapThreshold' => 8,
            'notificationInterval' => 15,
            'recoveryNotificationDelay' => 10,
            'firstNotificationDelay' => 5,
            'acknowledgementTimeout' => 20,
            'geoCoords' => new GeoCoords('12.25', '46.8'),
            ...$fields,
        ]
    );
});

it('should return properly set service instance (all properties)', function (): void {
    $service = ($this->createService)();

    expect($service->getName())->toBe('service-name')
        ->and($service->getHostId())->toBe(1)
        ->and($service->getCommandId())->toBe(1)
        ->and($service->getCommandArguments())->toBe(['args1', 'args2'])
        ->and($service->getEventHandlerArguments())->toBe(['args3', 'args4'])
        ->and($service->getNotificationTypes())->toBe([NotificationType::Warning, NotificationType::Critical])
        ->and($service->isContactAdditiveInheritance())->toBe(false)
        ->and($service->isContactGroupAdditiveInheritance())->toBe(false)
        ->and($service->isActivated())->toBe(true)
        ->and($service->getActiveChecks())->toBe(YesNoDefault::Yes)
        ->and($service->getPassiveCheck())->toBe(YesNoDefault::No)
        ->and($service->getVolatility())->toBe(YesNoDefault::Yes)
        ->and($service->getCheckFreshness())->toBe(YesNoDefault::No)
        ->and($service->getEventHandlerEnabled())->toBe(YesNoDefault::Yes)
        ->and($service->getFlapDetectionEnabled())->toBe(YesNoDefault::No)
        ->and($service->getNotificationsEnabled())->toBe(YesNoDefault::Yes)
        ->and($service->getComment())->toBe('some-comment')
        ->and($service->getNote())->toBe('some-note')
        ->and($service->getNoteUrl())->toBe('some-url')
        ->and($service->getActionUrl())->toBe('some-action-url')
        ->and($service->getIconAlternativeText())->toBe('icon-alt')
        ->and($service->getGraphTemplateId())->toBe(12)
        ->and($service->getServiceTemplateParentId())->toBe(52)
        ->and($service->getEventHandlerId())->toBe(14)
        ->and($service->getNotificationTimePeriodId())->toBe(65)
        ->and($service->getCheckTimePeriodId())->toBe(82)
        ->and($service->getIconId())->toBe(27)
        ->and($service->getSeverityId())->toBe(16)
        ->and($service->getMaxCheckAttempts())->toBe(3)
        ->and($service->getNormalCheckInterval())->toBe(5)
        ->and($service->getRetryCheckInterval())->toBe(1)
        ->and($service->getFreshnessThreshold())->toBe(12)
        ->and($service->getLowFlapThreshold())->toBe(6)
        ->and($service->getHighFlapThreshold())->toBe(8)
        ->and($service->getNotificationInterval())->toBe(15)
        ->and($service->getRecoveryNotificationDelay())->toBe(10)
        ->and($service->getFirstNotificationDelay())->toBe(5)
        ->and($service->getAcknowledgementTimeout())->toBe(20)
        ->and($service->getGeoCoords()->__toString())->toBe((new GeoCoords('12.25', '46.8'))->__toString());
});

it('should return properly set host instance (mandatory properties only)', function (): void {
    $service = new Service(id: 1, name: 'service-name', hostId: 1);

    expect($service->getName())->toBe('service-name')
        ->and($service->getHostId())->toBe(1)
        ->and($service->getCommandId())->toBe(null)
        ->and($service->getCommandArguments())->toBe([])
        ->and($service->getEventHandlerArguments())->toBe([])
        ->and($service->getNotificationTypes())->toBe([])
        ->and($service->isContactAdditiveInheritance())->toBe(false)
        ->and($service->isContactGroupAdditiveInheritance())->toBe(false)
        ->and($service->isActivated())->toBe(true)
        ->and($service->getActiveChecks())->toBe(YesNoDefault::Default)
        ->and($service->getPassiveCheck())->toBe(YesNoDefault::Default)
        ->and($service->getVolatility())->toBe(YesNoDefault::Default)
        ->and($service->getCheckFreshness())->toBe(YesNoDefault::Default)
        ->and($service->getEventHandlerEnabled())->toBe(YesNoDefault::Default)
        ->and($service->getFlapDetectionEnabled())->toBe(YesNoDefault::Default)
        ->and($service->getNotificationsEnabled())->toBe(YesNoDefault::Default)
        ->and($service->getComment())->toBe(null)
        ->and($service->getNote())->toBe(null)
        ->and($service->getNoteUrl())->toBe(null)
        ->and($service->getActionUrl())->toBe(null)
        ->and($service->getIconAlternativeText())->toBe(null)
        ->and($service->getGraphTemplateId())->toBe(null)
        ->and($service->getServiceTemplateParentId())->toBe(null)
        ->and($service->getEventHandlerId())->toBe(null)
        ->and($service->getNotificationTimePeriodId())->toBe(null)
        ->and($service->getCheckTimePeriodId())->toBe(null)
        ->and($service->getIconId())->toBe(null)
        ->and($service->getSeverityId())->toBe(null)
        ->and($service->getMaxCheckAttempts())->toBe(null)
        ->and($service->getNormalCheckInterval())->toBe(null)
        ->and($service->getRetryCheckInterval())->toBe(null)
        ->and($service->getFreshnessThreshold())->toBe(null)
        ->and($service->getLowFlapThreshold())->toBe(null)
        ->and($service->getHighFlapThreshold())->toBe(null)
        ->and($service->getNotificationInterval())->toBe(null)
        ->and($service->getRecoveryNotificationDelay())->toBe(null)
        ->and($service->getFirstNotificationDelay())->toBe(null)
        ->and($service->getAcknowledgementTimeout())->toBe(null)
        ->and($service->getGeoCoords())->toBe(null);
});

// mandatory fields
it(
    'should throw an exception when service name is an empty string',
    fn() => ($this->createService)(['name' => '    '])
)->throws(
    InvalidArgumentException::class,
    AssertionException::notEmptyString('Service::name')->getMessage()
);

// foreign keys fields
foreach (
    [
        'hostId',
        'graphTemplateId',
        'serviceTemplateParentId',
        'commandId',
        'eventHandlerId',
        'notificationTimePeriodId',
        'checkTimePeriodId',
        'iconId',
        'severityId',
    ] as $field
) {
    it(
        "should throw an exception when service {$field} is not > 0",
        fn() => ($this->createService)([$field => 0])
    )->throws(
        InvalidArgumentException::class,
        AssertionException::positiveInt(0, "Service::{$field}")->getMessage()
    );
}

// name and commands args should be formated
it('should return trimmed and formatted name field after construct', function (): void {
    $service = ($this->createService)(['name' => '    service     name   ']);

    expect($service->getName())->toBe('service name');
});

foreach (
    [
        'name',
        'comment',
        'note',
        'noteUrl',
        'actionUrl',
        'iconAlternativeText',
    ] as $field
) {
    it(
        "should return trimmed field {$field} after construct",
        function () use ($field): void {
            $service = ($this->createService)([$field => '  abc ']);
            $valueFromGetter = $service->{'get' . $field}();

            expect($valueFromGetter)->toBe('abc');
        }
    );
}

// too long fields
foreach (
    [
        'name' => Service::MAX_NAME_LENGTH,
        'comment' => Service::MAX_COMMENT_LENGTH,
        'note' => Service::MAX_NOTES_LENGTH,
        'noteUrl' => Service::MAX_NOTES_URL_LENGTH,
        'actionUrl' => Service::MAX_ACTION_URL_LENGTH,
        'iconAlternativeText' => Service::MAX_ICON_ALT_LENGTH,
    ] as $field => $length
) {
    $tooLong = str_repeat('a', $length + 1);
    it(
        "should throw an exception when service {$field} is too long",
        fn() => ($this->createService)([$field => $tooLong])
    )->throws(
        InvalidArgumentException::class,
        AssertionException::maxLength($tooLong, $length + 1, $length, "Service::{$field}")->getMessage()
    );
}

// integer >= 0 field
foreach (
    [
        'maxCheckAttempts',
        'normalCheckInterval',
        'retryCheckInterval',
        'freshnessThreshold',
        'lowFlapThreshold',
        'highFlapThreshold',
        'notificationInterval',
        'recoveryNotificationDelay',
        'firstNotificationDelay',
        'acknowledgementTimeout',
    ] as $field
) {
    it(
        "should throw an exception when service {$field} is not >= 0",
        fn() => ($this->createService)([$field => -1])
    )->throws(
        InvalidArgumentException::class,
        AssertionException::min(-1, 0, "Service::{$field}")->getMessage()
    );
}
