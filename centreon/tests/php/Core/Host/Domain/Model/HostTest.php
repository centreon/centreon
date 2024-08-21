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

namespace Tests\Core\Host\Domain\Model;

use Assert\InvalidArgumentException;
use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Common\Domain\YesNoDefault;
use Core\Domain\Common\GeoCoords;
use Core\Host\Domain\Model\Host;
use Core\Host\Domain\Model\HostEvent;
use Core\Host\Domain\Model\SnmpVersion;

beforeEach(function (): void {
    $this->createHost = static fn(array $fields = []): Host => new Host(
        ...[
            'id' => 1,
            'monitoringServerId' => 1,
            'name' => 'host-name',
            'address' => '127.0.0.1',
            'snmpVersion' => SnmpVersion::Two,
            'geoCoordinates' => GeoCoords::fromString('48.51,2.20'),
            'alias' => 'host-alias',
            'snmpCommunity' => 'snmpCommunity-value',
            'noteUrl' => 'noteUrl-value',
            'note' => 'note-value',
            'actionUrl' => 'actionUrl-value',
            'iconAlternative' => 'iconAlternative-value',
            'comment' => 'comment-value',
            'checkCommandArgs' => ['arg1', 'arg2'],
            'eventHandlerCommandArgs' => ['arg3', 'arg4'],
            'notificationOptions' => [HostEvent::Down, HostEvent::Unreachable],
            'timezoneId' => 1,
            'severityId' => 1,
            'checkCommandId' => 1,
            'checkTimeperiodId' => 1,
            'notificationTimeperiodId' => 1,
            'eventHandlerCommandId' => 1,
            'iconId' => 1,
            'maxCheckAttempts' => 5,
            'normalCheckInterval' => 5,
            'retryCheckInterval' => 5,
            'notificationInterval' => 5,
            'firstNotificationDelay' => 5,
            'recoveryNotificationDelay' => 5,
            'acknowledgementTimeout' => 5,
            'freshnessThreshold' => 5,
            'lowFlapThreshold' => 5,
            'highFlapThreshold' => 5,
            'activeCheckEnabled' => YesNoDefault::Yes,
            'passiveCheckEnabled' => YesNoDefault::Yes,
            'notificationEnabled' => YesNoDefault::Yes,
            'freshnessChecked' => YesNoDefault::Yes,
            'flapDetectionEnabled' => YesNoDefault::Yes,
            'eventHandlerEnabled' => YesNoDefault::Yes,
            'addInheritedContactGroup' => true,
            'addInheritedContact' => true,
            'isActivated' => false,
            ...$fields,
        ]
    );
});

it('should return properly set host instance (all properties)', function (): void {
    $host = ($this->createHost)();

    expect($host->getId())->toBe(1)
        ->and($host->getMonitoringServerId())->toBe(1)
        ->and($host->getName())->toBe('host-name')
        ->and($host->getAddress())->toBe('127.0.0.1')
        ->and($host->getAlias())->toBe('host-alias')
        ->and($host->getSnmpVersion())->toBe(SnmpVersion::Two)
        ->and($host->getSnmpCommunity())->toBe('snmpCommunity-value')
        ->and($host->getNoteUrl())->toBe('noteUrl-value')
        ->and($host->getNote())->toBe('note-value')
        ->and($host->getActionUrl())->toBe('actionUrl-value')
        ->and($host->getIconAlternative())->toBe('iconAlternative-value')
        ->and($host->getComment())->toBe('comment-value')
        ->and($host->getGeoCoordinates()?->__toString())->toBe('48.51,2.20')
        ->and($host->getCheckCommandArgs())->toBe(['arg1', 'arg2'])
        ->and($host->getEventHandlerCommandArgs())->toBe(['arg3', 'arg4'])
        ->and($host->getNotificationOptions())->toBe([HostEvent::Down, HostEvent::Unreachable])
        ->and($host->getTimezoneId())->toBe(1)
        ->and($host->getSeverityId())->toBe(1)
        ->and($host->getCheckCommandId())->toBe(1)
        ->and($host->getCheckTimeperiodId())->toBe(1)
        ->and($host->getNotificationTimeperiodId())->toBe(1)
        ->and($host->getEventHandlerCommandId())->toBe(1)
        ->and($host->getIconId())->toBe(1)
        ->and($host->getMaxCheckAttempts())->toBe(5)
        ->and($host->getNormalCheckInterval())->toBe(5)
        ->and($host->getRetryCheckInterval())->toBe(5)
        ->and($host->getNotificationInterval())->toBe(5)
        ->and($host->getFirstNotificationDelay())->toBe(5)
        ->and($host->getRecoveryNotificationDelay())->toBe(5)
        ->and($host->getAcknowledgementTimeout())->toBe(5)
        ->and($host->getFreshnessThreshold())->toBe(5)
        ->and($host->getLowFlapThreshold())->toBe(5)
        ->and($host->getHighFlapThreshold())->toBe(5)
        ->and($host->getActiveCheckEnabled())->toBe(YesNoDefault::Yes)
        ->and($host->getPassiveCheckEnabled())->toBe(YesNoDefault::Yes)
        ->and($host->getNotificationEnabled())->toBe(YesNoDefault::Yes)
        ->and($host->getFreshnessChecked())->toBe(YesNoDefault::Yes)
        ->and($host->getFlapDetectionEnabled())->toBe(YesNoDefault::Yes)
        ->and($host->getEventHandlerEnabled())->toBe(YesNoDefault::Yes)
        ->and($host->addInheritedContactGroup())->toBe(true)
        ->and($host->addInheritedContact())->toBe(true)
        ->and($host->isActivated())->toBe(false);
});

it('should return properly set host instance (mandatory properties only)', function (): void {
    $host = new Host(
        id: 1,
        monitoringServerId: 1,
        name: 'host-name',
        address: '127.0.0.1'
    );

    expect($host->getId())->toBe(1)
        ->and($host->getMonitoringServerId())->toBe(1)
        ->and($host->getName())->toBe('host-name')
        ->and($host->getAddress())->toBe('127.0.0.1')
        ->and($host->getGeoCoordinates())->toBe(null)
        ->and($host->getSnmpVersion())->toBe(null)
        ->and($host->getSnmpCommunity())->toBe('')
        ->and($host->getAlias())->toBe('')
        ->and($host->getNoteUrl())->toBe('')
        ->and($host->getNote())->toBe('')
        ->and($host->getActionUrl())->toBe('')
        ->and($host->getIconAlternative())->toBe('')
        ->and($host->getComment())->toBe('')
        ->and($host->getCheckCommandArgs())->toBe([])
        ->and($host->getEventHandlerCommandArgs())->toBe([])
        ->and($host->getNotificationOptions())->toBe([])
        ->and($host->getTimezoneId())->toBe(null)
        ->and($host->getSeverityId())->toBe(null)
        ->and($host->getCheckCommandId())->toBe(null)
        ->and($host->getCheckTimeperiodId())->toBe(null)
        ->and($host->getNotificationTimeperiodId())->toBe(null)
        ->and($host->getEventHandlerCommandId())->toBe(null)
        ->and($host->getIconId())->toBe(null)
        ->and($host->getMaxCheckAttempts())->toBe(null)
        ->and($host->getNormalCheckInterval())->toBe(null)
        ->and($host->getRetryCheckInterval())->toBe(null)
        ->and($host->getNotificationInterval())->toBe(null)
        ->and($host->getFirstNotificationDelay())->toBe(null)
        ->and($host->getRecoveryNotificationDelay())->toBe(null)
        ->and($host->getAcknowledgementTimeout())->toBe(null)
        ->and($host->getFreshnessThreshold())->toBe(null)
        ->and($host->getLowFlapThreshold())->toBe(null)
        ->and($host->getHighFlapThreshold())->toBe(null)
        ->and($host->getActiveCheckEnabled())->toBe(YesNoDefault::Default)
        ->and($host->getPassiveCheckEnabled())->toBe(YesNoDefault::Default)
        ->and($host->getNotificationEnabled())->toBe(YesNoDefault::Default)
        ->and($host->getFreshnessChecked())->toBe(YesNoDefault::Default)
        ->and($host->getFlapDetectionEnabled())->toBe(YesNoDefault::Default)
        ->and($host->getEventHandlerEnabled())->toBe(YesNoDefault::Default)
        ->and($host->addInheritedContactGroup())->toBe(false)
        ->and($host->addInheritedContact())->toBe(false)
        ->and($host->isActivated())->toBe(true);
});

// mandatory fields
it(
    'should throw an exception when host name is an empty string',
    fn() => ($this->createHost)(['name' => '    '])
)->throws(
    InvalidArgumentException::class,
    AssertionException::notEmptyString('Host::name')->getMessage()
);
it(
    'should throw an exception when host name is set to an empty string', function (): void {
        $host = ($this->createHost)();
        $host->setName('   ');
})->throws(
    InvalidArgumentException::class,
    AssertionException::notEmptyString('Host::name')->getMessage()
);

it(
    'should throw an exception when host address does not respect format',
    fn() => ($this->createHost)(['address' => 'hello world'])
)->throws(
    InvalidArgumentException::class,
    AssertionException::ipOrDomain('hello world', 'Host::address')->getMessage()
);
it(
    'should throw an exception when host address does not respect format in setter', function (): void {
        $host = ($this->createHost)();
        $host->setAddress('hello world');
    }
)->throws(
    InvalidArgumentException::class,
    AssertionException::ipOrDomain('hello world', 'Host::address')->getMessage()
);

// name and conmmands args should be formated
it('should return trimmed and formatted field name after construct', function (): void {
    $host = ($this->createHost)(['name' => '    host name   ']);

    expect($host->getName())->toBe('host_name');
});

it('should trim and format field name when set', function (): void {
    $host = ($this->createHost)();
    $host->setName('    some new name   ');

    expect($host->getName())->toBe('some_new_name');
});

foreach (
    [
        'checkCommandArgs',
        'eventHandlerCommandArgs',
    ] as $field
) {
    it(
        "should return a trimmed field {$field}",
        function () use ($field): void {
            $host = ($this->createHost)([$field => ['  arg1  ', '  arg2  ']]);
            $valueFromGetter = $host->{'get' . $field}();

            expect($valueFromGetter)->toBe(['arg1', 'arg2']);
        }
    );
}

foreach (
    [
        'checkCommandArgs',
        'eventHandlerCommandArgs',
    ] as $field
) {
    it(
        "should set a trimmed field {$field}",
        function () use ($field): void {
            $host = ($this->createHost)();
            $host->{'set' . $field}(['  arg1  ', '  arg2  ']);

            expect($host->{'get' . $field}())->toBe(['arg1', 'arg2']);
        }
    );
}

// string field trimmed
foreach (
    [
        'alias',
        'snmpCommunity',
        'noteUrl',
        'note',
        'actionUrl',
        'iconAlternative',
        'comment',
    ] as $field
) {
    it(
        "should return trimmed field {$field} after construct",
        function () use ($field): void {
            $host = ($this->createHost)([$field => '  abcd ']);
            $valueFromGetter = $host->{'get' . $field}();

            expect($valueFromGetter)->toBe('abcd');
        }
    );
}

foreach (
    [
        'alias',
        'snmpCommunity',
        'noteUrl',
        'note',
        'actionUrl',
        'iconAlternative',
        'comment',
    ] as $field
) {
    it(
        "should set a trimmed field {$field}",
        function () use ($field): void {
            $host = ($this->createHost)();
            $host->{'set' . $field}('  abcd ');

            expect($host->{'get' . $field}())->toBe('abcd');
        }
    );
}

// too long fields
foreach (
    [
        'name' => Host::MAX_NAME_LENGTH,
        'alias' => Host::MAX_ALIAS_LENGTH,
        'snmpCommunity' => Host::MAX_SNMP_COMMUNITY_LENGTH,
        'noteUrl' => Host::MAX_NOTE_URL_LENGTH,
        'note' => Host::MAX_NOTE_LENGTH,
        'actionUrl' => Host::MAX_ACTION_URL_LENGTH,
        'iconAlternative' => Host::MAX_ICON_ALT_LENGTH,
        'comment' => Host::MAX_COMMENT_LENGTH,
    ] as $field => $length
) {
    $tooLong = str_repeat('a', $length + 1);
    it(
        "should throw an exception when host {$field} is too long",
        fn() => ($this->createHost)([$field => $tooLong])
    )->throws(
        InvalidArgumentException::class,
        AssertionException::maxLength($tooLong, $length + 1, $length, "Host::{$field}")->getMessage()
    );
}

foreach (
    [
        'name' => Host::MAX_NAME_LENGTH,
        'alias' => Host::MAX_ALIAS_LENGTH,
        'snmpCommunity' => Host::MAX_SNMP_COMMUNITY_LENGTH,
        'noteUrl' => Host::MAX_NOTE_URL_LENGTH,
        'note' => Host::MAX_NOTE_LENGTH,
        'actionUrl' => Host::MAX_ACTION_URL_LENGTH,
        'iconAlternative' => Host::MAX_ICON_ALT_LENGTH,
        'comment' => Host::MAX_COMMENT_LENGTH,
    ] as $field => $length
) {
    $tooLongStr = str_repeat('a', $length + 1);
    it(
        "should throw an exception when host {$field} is set too long",
        function () use ($field, $tooLongStr): void {
            $host = ($this->createHost)();
            $host->{'set' . $field}($tooLongStr);
        }
    )->throws(
        InvalidArgumentException::class,
        AssertionException::maxLength($tooLongStr, $length + 1, $length, "Host::{$field}")->getMessage()
    );
}

// foreign keys fields
foreach (
    [
        'monitoringServerId',
        'timezoneId',
        'severityId',
        'checkCommandId',
        'checkTimeperiodId',
        'notificationTimeperiodId',
        'eventHandlerCommandId',
        'iconId',
    ] as $field
) {
    it(
        "should throw an exception when host {$field} is not > 0",
        fn() => ($this->createHost)([$field => 0])
    )->throws(
        InvalidArgumentException::class,
        AssertionException::positiveInt(0, "Host::{$field}")->getMessage()
    );
}

foreach (
    [
        'monitoringServerId',
        'timezoneId',
        'severityId',
        'checkCommandId',
        'checkTimeperiodId',
        'notificationTimeperiodId',
        'eventHandlerCommandId',
        'iconId',
    ] as $field
) {
    it(
        "should throw an exception when host {$field} set value is not > 0",
        function () use ($field): void {
            $host = ($this->createHost)();
            $host->{'set' . $field}(0);
        }
    )->throws(
        InvalidArgumentException::class,
        AssertionException::positiveInt(0, "Host::{$field}")->getMessage()
    );
}

// integer >= 0 field
foreach (
    [
        'maxCheckAttempts',
        'normalCheckInterval',
        'retryCheckInterval',
        'notificationInterval',
        'firstNotificationDelay',
        'recoveryNotificationDelay',
        'acknowledgementTimeout',
        'freshnessThreshold',
        'lowFlapThreshold',
        'highFlapThreshold',
    ] as $field
) {
    it(
        "should throw an exception when host {$field} is not >= 0",
        fn() => ($this->createHost)([$field => -1])
    )->throws(
        InvalidArgumentException::class,
        AssertionException::min(-1, 0, "Host::{$field}")->getMessage()
    );
}

foreach (
    [
        'maxCheckAttempts',
        'normalCheckInterval',
        'retryCheckInterval',
        'notificationInterval',
        'firstNotificationDelay',
        'recoveryNotificationDelay',
        'acknowledgementTimeout',
        'freshnessThreshold',
        'lowFlapThreshold',
        'highFlapThreshold',
    ] as $field
) {
    it(
        "should throw an exception when host {$field} set value is not >= 0",
        function () use ($field): void {
            $host = ($this->createHost)();
            $host->{'set' . $field}(-1);
        }
    )->throws(
        InvalidArgumentException::class,
        AssertionException::min(-1, 0, "Host::{$field}")->getMessage()
    );
}