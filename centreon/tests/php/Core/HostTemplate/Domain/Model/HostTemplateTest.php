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

namespace Tests\Core\HostTemplate\Domain\Model;

use Assert\InvalidArgumentException;
use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Common\Domain\YesNoDefault;
use Core\Host\Domain\Model\HostEvent;
use Core\Host\Domain\Model\SnmpVersion;
use Core\HostTemplate\Domain\Model\HostTemplate;

beforeEach(function (): void {
    $this->createHostTemplate = static function (array $fields = []): HostTemplate {
        return new HostTemplate(
            ...[
                'id' => 1,
                'name' => 'host-template-name',
                'alias' => 'host-template-alias',
                'snmpVersion' => SnmpVersion::Two,
                'snmpCommunity' => 'snmpCommunity-value',
                'timezoneId' => 1,
                'severityId' => 1,
                'checkCommandId' => 1,
                'checkCommandArgs' => ['arg1', 'arg2'],
                'checkTimeperiodId' => 1,
                'maxCheckAttempts' => 5,
                'normalCheckInterval' => 5,
                'retryCheckInterval' => 5,
                'activeCheckEnabled' => YesNoDefault::Yes,
                'passiveCheckEnabled' => YesNoDefault::Yes,
                'notificationEnabled' => YesNoDefault::Yes,
                'notificationOptions' => [HostEvent::Down, HostEvent::Unreachable],
                'notificationInterval' => 5,
                'notificationTimeperiodId' => 1,
                'addInheritedContactGroup' => true,
                'addInheritedContact' => true,
                'firstNotificationDelay' => 5,
                'recoveryNotificationDelay' => 5,
                'acknowledgementTimeout' => 5,
                'freshnessChecked' => YesNoDefault::Yes,
                'freshnessThreshold' => 5,
                'flapDetectionEnabled' => YesNoDefault::Yes,
                'lowFlapThreshold' => 5,
                'highFlapThreshold' => 5,
                'eventHandlerEnabled' => YesNoDefault::Yes,
                'eventHandlerCommandId' => 1,
                'eventHandlerCommandArgs' => ['arg3', 'arg4'],
                'noteUrl' => 'noteUrl-value',
                'note' => 'note-value',
                'actionUrl' => 'actionUrl-value',
                'iconId' => 1,
                'iconAlternative' => 'iconAlternative-value',
                'comment' => 'comment-value',
                'isLocked' => true,
                ...$fields,
            ]
        );
    };
});

it('should return properly set host template instance (all properties)', function (): void {
    $hostTemplate = ($this->createHostTemplate)();

    expect($hostTemplate->getId())->toBe(1)
        ->and($hostTemplate->getName())->toBe('host-template-name')
        ->and($hostTemplate->getAlias())->toBe('host-template-alias')
        ->and($hostTemplate->getSnmpVersion())->toBe(SnmpVersion::Two)
        ->and($hostTemplate->getSnmpCommunity())->toBe('snmpCommunity-value')
        ->and($hostTemplate->getTimezoneId())->toBe(1)
        ->and($hostTemplate->getSeverityId())->toBe(1)
        ->and($hostTemplate->getCheckCommandId())->toBe(1)
        ->and($hostTemplate->getCheckCommandArgs())->toBe(['arg1', 'arg2'])
        ->and($hostTemplate->getCheckTimeperiodId())->toBe(1)
        ->and($hostTemplate->getMaxCheckAttempts())->toBe(5)
        ->and($hostTemplate->getNormalCheckInterval())->toBe(5)
        ->and($hostTemplate->getRetryCheckInterval())->toBe(5)
        ->and($hostTemplate->getActiveCheckEnabled())->toBe(YesNoDefault::Yes)
        ->and($hostTemplate->getPassiveCheckEnabled())->toBe(YesNoDefault::Yes)
        ->and($hostTemplate->getNotificationEnabled())->toBe(YesNoDefault::Yes)
        ->and($hostTemplate->getNotificationOptions())->toBe([HostEvent::Down, HostEvent::Unreachable])
        ->and($hostTemplate->getNotificationInterval())->toBe(5)
        ->and($hostTemplate->getNotificationTimeperiodId())->toBe(1)
        ->and($hostTemplate->addInheritedContactGroup())->toBe(true)
        ->and($hostTemplate->addInheritedContact())->toBe(true)
        ->and($hostTemplate->getFirstNotificationDelay())->toBe(5)
        ->and($hostTemplate->getRecoveryNotificationDelay())->toBe(5)
        ->and($hostTemplate->getAcknowledgementTimeout())->toBe(5)
        ->and($hostTemplate->getFreshnessChecked())->toBe(YesNoDefault::Yes)
        ->and($hostTemplate->getFreshnessThreshold())->toBe(5)
        ->and($hostTemplate->getFlapDetectionEnabled())->toBe(YesNoDefault::Yes)
        ->and($hostTemplate->getLowFlapThreshold())->toBe(5)
        ->and($hostTemplate->getHighFlapThreshold())->toBe(5)
        ->and($hostTemplate->getEventHandlerEnabled())->toBe(YesNoDefault::Yes)
        ->and($hostTemplate->getEventHandlerCommandId())->toBe(1)
        ->and($hostTemplate->getEventHandlerCommandArgs())->toBe(['arg3', 'arg4'])
        ->and($hostTemplate->getNoteUrl())->toBe('noteUrl-value')
        ->and($hostTemplate->getNote())->toBe('note-value')
        ->and($hostTemplate->getActionUrl())->toBe('actionUrl-value')
        ->and($hostTemplate->getIconId())->toBe(1)
        ->and($hostTemplate->getIconAlternative())->toBe('iconAlternative-value')
        ->and($hostTemplate->getComment())->toBe('comment-value')
        ->and($hostTemplate->isLocked())->toBe(true);
});

it('should return properly set host template instance (mandatory properties only)', function (): void {
    $hostTemplate = new HostTemplate(1, 'host-template-name', 'host-template-alias');

    expect($hostTemplate->getId())->toBe(1)
        ->and($hostTemplate->getName())->toBe('host-template-name')
        ->and($hostTemplate->getAlias())->toBe('host-template-alias')
        ->and($hostTemplate->getSnmpVersion())->toBe(null)
        ->and($hostTemplate->getSnmpCommunity())->toBe('')
        ->and($hostTemplate->getTimezoneId())->toBe(null)
        ->and($hostTemplate->getSeverityId())->toBe(null)
        ->and($hostTemplate->getCheckCommandId())->toBe(null)
        ->and($hostTemplate->getCheckCommandArgs())->toBe([])
        ->and($hostTemplate->getCheckTimeperiodId())->toBe(null)
        ->and($hostTemplate->getMaxCheckAttempts())->toBe(null)
        ->and($hostTemplate->getNormalCheckInterval())->toBe(null)
        ->and($hostTemplate->getRetryCheckInterval())->toBe(null)
        ->and($hostTemplate->getActiveCheckEnabled())->toBe(YesNoDefault::Default)
        ->and($hostTemplate->getPassiveCheckEnabled())->toBe(YesNoDefault::Default)
        ->and($hostTemplate->getNotificationEnabled())->toBe(YesNoDefault::Default)
        ->and($hostTemplate->getNotificationOptions())->toBe([])
        ->and($hostTemplate->getNotificationInterval())->toBe(null)
        ->and($hostTemplate->getNotificationTimeperiodId())->toBe(null)
        ->and($hostTemplate->addInheritedContactGroup())->toBe(false)
        ->and($hostTemplate->addInheritedContact())->toBe(false)
        ->and($hostTemplate->getFirstNotificationDelay())->toBe(null)
        ->and($hostTemplate->getRecoveryNotificationDelay())->toBe(null)
        ->and($hostTemplate->getAcknowledgementTimeout())->toBe(null)
        ->and($hostTemplate->getFreshnessChecked())->toBe(YesNoDefault::Default)
        ->and($hostTemplate->getFreshnessThreshold())->toBe(null)
        ->and($hostTemplate->getFlapDetectionEnabled())->toBe(YesNoDefault::Default)
        ->and($hostTemplate->getLowFlapThreshold())->toBe(null)
        ->and($hostTemplate->getHighFlapThreshold())->toBe(null)
        ->and($hostTemplate->getEventHandlerEnabled())->toBe(YesNoDefault::Default)
        ->and($hostTemplate->getEventHandlerCommandId())->toBe(null)
        ->and($hostTemplate->getEventHandlerCommandArgs())->toBe([])
        ->and($hostTemplate->getNoteUrl())->toBe('')
        ->and($hostTemplate->getNote())->toBe('')
        ->and($hostTemplate->getActionUrl())->toBe('')
        ->and($hostTemplate->getIconId())->toBe(null)
        ->and($hostTemplate->getIconAlternative())->toBe('')
        ->and($hostTemplate->getComment())->toBe('')
        ->and($hostTemplate->isLocked())->toBe(false);
});

// mandatory fields
foreach (
    [
        'name',
        'alias',
    ] as $field
) {
    it(
        "should throw an exception when host template {$field} is an empty string",
        fn() => ($this->createHostTemplate)([$field => ''])
    )->throws(
        InvalidArgumentException::class,
        AssertionException::notEmptyString("HostTemplate::{$field}")->getMessage()
    );
}

foreach (
    [
        'name',
        'alias',
    ] as $field
) {
    it("should throw an exception when host template {$field} is set to an empty string", function () use ($field): void {
        $hostTemplate = ($this->createHostTemplate)();
        $hostTemplate->{'set' . $field}('');
    })->throws(
        InvalidArgumentException::class,
        AssertionException::notEmptyString("HostTemplate::{$field}")->getMessage()
    );
}

// name and conmmands args should be formated
it('should return trimmed and formatted field name after construct', function (): void {
    $hostTemplate = new HostTemplate(1, '    host template name   ', 'alias');

    expect($hostTemplate->getName())->toBe('host_template_name');
});

it('should trimm and format field name when set', function (): void {
    $hostTemplate = ($this->createHostTemplate)();
    $hostTemplate->setName('    some new name   ');

    expect($hostTemplate->getName())->toBe('some_new_name');
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
            $hostTemplate = ($this->createHostTemplate)([$field => ['  arg1  ', '  arg2  ']]);
            $valueFromGetter = $hostTemplate->{'get' . $field}();

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
            $hostTemplate = ($this->createHostTemplate)();
            $hostTemplate->{'set' . $field}(['  arg1  ', '  arg2  ']);

            expect($hostTemplate->{'get' . $field}())->toBe(['arg1', 'arg2']);
        }
    );
}

// string field trimmed
foreach (
    [
        'name',
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
        "should return trim the field {$field} after construct",
        function () use ($field): void {
            $hostTemplate = ($this->createHostTemplate)([$field => '  abcd ']);
            $valueFromGetter = $hostTemplate->{'get' . $field}();

            expect($valueFromGetter)->toBe('abcd');
        }
    );
}

foreach (
    [
        'name',
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
            $hostTemplate = ($this->createHostTemplate)();
            $hostTemplate->{'set' . $field}('  abcd ');

            expect($hostTemplate->{'get' . $field}())->toBe('abcd');
        }
    );
}

// too long fields
foreach (
    [
        'name' => HostTemplate::MAX_NAME_LENGTH,
        'alias' => HostTemplate::MAX_ALIAS_LENGTH,
        'snmpCommunity' => HostTemplate::MAX_SNMP_COMMUNITY_LENGTH,
        'noteUrl' => HostTemplate::MAX_NOTE_URL_LENGTH,
        'note' => HostTemplate::MAX_NOTE_LENGTH,
        'actionUrl' => HostTemplate::MAX_ACTION_URL_LENGTH,
        'iconAlternative' => HostTemplate::MAX_ICON_ALT_LENGTH,
        'comment' => HostTemplate::MAX_COMMENT_LENGTH,
    ] as $field => $length
) {
    $tooLong = str_repeat('a', $length + 1);
    it(
        "should throw an exception when host template {$field} is too long",
        fn() => ($this->createHostTemplate)([$field => $tooLong])
    )->throws(
        InvalidArgumentException::class,
        AssertionException::maxLength($tooLong, $length + 1, $length, "HostTemplate::{$field}")->getMessage()
    );
}

foreach (
    [
        'name' => HostTemplate::MAX_NAME_LENGTH,
        'alias' => HostTemplate::MAX_ALIAS_LENGTH,
        'snmpCommunity' => HostTemplate::MAX_SNMP_COMMUNITY_LENGTH,
        'noteUrl' => HostTemplate::MAX_NOTE_URL_LENGTH,
        'note' => HostTemplate::MAX_NOTE_LENGTH,
        'actionUrl' => HostTemplate::MAX_ACTION_URL_LENGTH,
        'iconAlternative' => HostTemplate::MAX_ICON_ALT_LENGTH,
        'comment' => HostTemplate::MAX_COMMENT_LENGTH,
    ] as $field => $length
) {
    $tooLongStr = str_repeat('a', $length + 1);
    it(
        "should throw an exception when host template {$field} is set too long",
        function () use ($field, $tooLongStr): void {
            $hostTemplate = ($this->createHostTemplate)();
            $hostTemplate->{'set' . $field}($tooLongStr);
        }
    )->throws(
        InvalidArgumentException::class,
        AssertionException::maxLength($tooLongStr, $length + 1, $length, "HostTemplate::{$field}")->getMessage()
    );
}

// foreign keys fields
foreach (
    [
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
        "should throw an exception when host template {$field} is not > 0",
        fn() => ($this->createHostTemplate)([$field => 0])
    )->throws(
        InvalidArgumentException::class,
        AssertionException::positiveInt(0, "HostTemplate::{$field}")->getMessage()
    );
}

foreach (
    [
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
        "should throw an exception when host template {$field} set value is not > 0",
        function () use ($field): void {
            $hostTemplate = ($this->createHostTemplate)();
            $hostTemplate->{'set' . $field}(0);
        }
    )->throws(
        InvalidArgumentException::class,
        AssertionException::positiveInt(0, "HostTemplate::{$field}")->getMessage()
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
        "should throw an exception when host template {$field} is not >= 0",
        fn() => ($this->createHostTemplate)([$field => -1])
    )->throws(
        InvalidArgumentException::class,
        AssertionException::min(-1, 0, "HostTemplate::{$field}")->getMessage()
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
        "should throw an exception when host template {$field} set value is not >= 0",
        function () use ($field): void {
            $hostTemplate = ($this->createHostTemplate)();
            $hostTemplate->{'set' . $field}(-1);
        }
    )->throws(
        InvalidArgumentException::class,
        AssertionException::min(-1, 0, "HostTemplate::{$field}")->getMessage()
    );
}