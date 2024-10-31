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

namespace Tests\Core\ServiceTemplate\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\MonitoringServer\Model\MonitoringServer;
use Core\ServiceTemplate\Domain\Model\NewServiceTemplate;
use Core\ServiceTemplate\Domain\Model\NotificationType;

/**
 * @return NewServiceTemplate
 *
 * @throws \Assert\AssertionFailedException
 */
function createNewServiceTemplate(): NewServiceTemplate
{
    return new NewServiceTemplate('name', 'fake_name');
}

foreach (
    [
        'name' => NewServiceTemplate::MAX_NAME_LENGTH,
        'alias' => NewServiceTemplate::MAX_ALIAS_LENGTH,
        'comment' => NewServiceTemplate::MAX_COMMENT_LENGTH,
        'note' => NewServiceTemplate::MAX_NOTES_LENGTH,
        'noteUrl' => NewServiceTemplate::MAX_NOTES_URL_LENGTH,
    ] as $field => $length
) {
    it(
        "should throw an exception when service template {$field} is an empty string",
        function () use ($field): void {
            $template = createNewServiceTemplate();
            call_user_func_array([$template, 'set' . ucfirst($field)], ['']);
        }
    )->throws(
        AssertionException::class,
        AssertionException::notEmptyString("NewServiceTemplate::$field")->getMessage()
    );

    $tooLongString = str_repeat('a', $length + 1);
    it(
        "should throw an exception when service template {$field} is too long",
        function () use ($field, $tooLongString): void {
            $template = createNewServiceTemplate();
            call_user_func_array([$template, 'set' . ucfirst($field)], [$tooLongString]);
        }
    )->throws(
        AssertionException::class,
        AssertionException::maxLength(
            $tooLongString,
            $length + 1,
            $length,
            "NewServiceTemplate::$field"
        )->getMessage()
    );
}

foreach (
    [
        'maxCheckAttempts',
        'normalCheckInterval',
        'retryCheckInterval',
        'freshnessThreshold',
        'notificationInterval',
        'recoveryNotificationDelay',
        'firstNotificationDelay',
        'acknowledgementTimeout',
        'lowFlapThreshold',
        'highFlapThreshold'
    ] as $field
) {
    it(
        "should throw an exception when service template {$field} is less than 0",
        function () use ($field): void {
            $template = createNewServiceTemplate();
            call_user_func_array([$template, 'set' . ucfirst($field)], [-1]);
        }
    )->throws(
        AssertionException::class,
        AssertionException::min(
            -1,
            0,
            "NewServiceTemplate::$field"
        )->getMessage()
    );
}

foreach (
    [
        'serviceTemplateParentId',
        'commandId',
        'eventHandlerId',
        'notificationTimePeriodId',
        'checkTimePeriodId',
        'iconId',
        'graphTemplateId',
        'severityId'
    ] as $field
) {
    it(
        "should throw an exception when service template {$field} is less than 1",
        function () use ($field): void {
            $template = createNewServiceTemplate();
            call_user_func_array([$template, 'set' . ucfirst($field)], [0]);
        }
    )->throws(
        AssertionException::class,
        AssertionException::min(
            0,
            1,
            "NewServiceTemplate::$field"
        )->getMessage()
    );
}

foreach (['commandArgument', 'eventHandlerArgument'] as $field) {
    it(
        "should retrieve all arguments to the {$field} field that were previously added",
        function () use ($field): void {
            $arguments = ['1', '2', '3'];
            $serviceTemplate = createNewServiceTemplate();
            $methodName = 'get' . ucfirst($field) . 's';
            foreach ($arguments as $argument) {
                call_user_func_array([$serviceTemplate, 'add' . ucfirst($field)], [$argument]);
            }

            expect($serviceTemplate->{$methodName}())->toBe(['1', '2', '3']);
        }
    );
}

it(
    "should retrieve all notificationTypes that were previously added",
    function (): void {
        $serviceTemplate = createNewServiceTemplate();
        $notificationTypes = [
            NotificationType::Unknown,
            NotificationType::Warning,
            NotificationType::Recovery
        ];
        foreach ($notificationTypes as $notificationType) {
            call_user_func_array([$serviceTemplate, 'addNotificationType'], [$notificationType]);
        }
        expect($serviceTemplate->getNotificationTypes())->toBe($notificationTypes);
    }
);


it(
    "should throw an exception when name contains illegal characters",
    fn() => (new NewServiceTemplate('fake_name' . MonitoringServer::ILLEGAL_CHARACTERS[0], 'fake_alias'))
)->throws(
    AssertionException::class,
    AssertionException::unauthorizedCharacters(
        'fake_name' . MonitoringServer::ILLEGAL_CHARACTERS[0],
        MonitoringServer::ILLEGAL_CHARACTERS[0],
        'NewServiceTemplate::name'
    )->getMessage()
);

it(
    "should throw an exception when alias contains illegal characters",
    fn() => (new NewServiceTemplate('fake_name', 'fake_alias' . MonitoringServer::ILLEGAL_CHARACTERS[0]))
)->throws(
    AssertionException::class,
    AssertionException::unauthorizedCharacters(
        'fake_alias' . MonitoringServer::ILLEGAL_CHARACTERS[0],
        MonitoringServer::ILLEGAL_CHARACTERS[0],
        'NewServiceTemplate::alias'
    )->getMessage()
);

it(
    "should remove spaces that are too long in the alias",
    function (): void {
        $serviceTemplate = new NewServiceTemplate('fake_name', '   fake   alias       ok    ');
        expect($serviceTemplate->getAlias())->toBe('fake alias ok');
    }
);
