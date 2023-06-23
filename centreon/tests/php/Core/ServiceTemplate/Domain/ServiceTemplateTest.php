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
use Core\ServiceTemplate\Domain\Model\NotificationType;
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;

/**
 * @param $values*
 *
 * @throws \Assert\AssertionFailedException
 *
 * @return ServiceTemplate
 */
function createServiceTemplate($values): ServiceTemplate
{
    return new ServiceTemplate(...['id' => 1, 'name' => 'fake_name', 'alias' => 'fake_alias', ...$values]);
}

foreach (
    [
        'name' => ServiceTemplate::MAX_NAME_LENGTH,
        'alias' => ServiceTemplate::MAX_ALIAS_LENGTH,
        'comment' => ServiceTemplate::MAX_COMMENT_LENGTH,
        'note' => ServiceTemplate::MAX_NOTES_LENGTH,
        'noteUrl' => ServiceTemplate::MAX_NOTES_URL_LENGTH,
    ] as $field => $length
) {
    it(
        "should throw an exception when service template {$field} is an empty string",
        fn() => (createServiceTemplate([$field => ' ']))
    )->throws(
        AssertionException::class,
        AssertionException::notEmptyString("ServiceTemplate::{$field}")->getMessage()
    );

    $tooLongString = str_repeat('a', $length + 1);
    it(
        "should throw an exception when service template {$field} is too long",
        fn() => (
            createServiceTemplate([$field => $tooLongString]))
    )->throws(
        AssertionException::class,
        AssertionException::maxLength(
            $tooLongString,
            $length + 1,
            $length,
            "ServiceTemplate::{$field}"
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
        'highFlapThreshold',
    ] as $field
) {
    it(
        "should throw an exception when service template {$field} is less than 0",
        fn() => (createServiceTemplate([$field => -1]))
    )->throws(
        AssertionException::class,
        AssertionException::min(
            -1,
            0,
            "ServiceTemplate::{$field}"
        )->getMessage()
    );
}

foreach (
    [
        'id',
        'serviceTemplateParentId',
        'commandId',
        'eventHandlerId',
        'notificationTimePeriodId',
        'checkTimePeriodId',
        'iconId',
        'graphTemplateId',
        'severityId',
    ] as $field
) {
    it(
        "should throw an exception when service template {$field} is less than 1",
        fn() => (createServiceTemplate([$field => 0]))
    )->throws(
        AssertionException::class,
        AssertionException::min(
            0,
            1,
            "ServiceTemplate::{$field}"
        )->getMessage()
    );
}

foreach (
    [
        'hostTemplateIds',
    ] as $field
) {
    it(
        "should throw an exception when service template {$field} contains a list of integers less than 1",
        fn() => (createServiceTemplate([$field => [0]]))
    )->throws(
        AssertionException::class,
        AssertionException::min(
            0,
            1,
            "ServiceTemplate::{$field}"
        )->getMessage()
    );
}

foreach (['commandArguments', 'eventHandlerArguments'] as $field) {
    it(
        "should convert all argument values of the {$field} field to strings only if they are of scalar type",
        function () use ($field): void {
            $arguments = [1, 2, '3', new \Exception()];
            $serviceTemplate = new ServiceTemplate(1, 'fake_name', 'fake_alias', ...[$field => $arguments]);
            $methodName = 'get' . ucfirst($field);
            expect($serviceTemplate->{$methodName}())->toBe(['1', '2', '3']);
        }
    );
}

it(
    'should throw an exception when one of the arguments in the notification list is not of the correct type',
    fn() => (new ServiceTemplate(1, 'fake_name', 'fake_alias', ...['notificationTypes' => ['fake']]))
)->throws(
    AssertionException::class,
    AssertionException::badInstanceOfObject(
        'string',
        NotificationType::class,
        'ServiceTemplate::notificationTypes'
    )->getMessage()
);

it(
    'should throw an exception when name contains illegal characters',
    fn() => (new ServiceTemplate(1, 'fake_name' . MonitoringServer::ILLEGAL_CHARACTERS[0], 'fake_alias'))
)->throws(
    AssertionException::class,
    AssertionException::unauthorizedCharacters(
        'fake_name' . MonitoringServer::ILLEGAL_CHARACTERS[0],
        MonitoringServer::ILLEGAL_CHARACTERS[0],
        'ServiceTemplate::name'
    )->getMessage()
);

it(
    'should throw an exception when alias contains illegal characters',
    fn() => (new ServiceTemplate(1, 'fake_name', 'fake_alias' . MonitoringServer::ILLEGAL_CHARACTERS[0]))
)->throws(
    AssertionException::class,
    AssertionException::unauthorizedCharacters(
        'fake_alias' . MonitoringServer::ILLEGAL_CHARACTERS[0],
        MonitoringServer::ILLEGAL_CHARACTERS[0],
        'ServiceTemplate::alias'
    )->getMessage()
);

it(
    'should remove spaces that are too long in the alias',
    function (): void {
        $serviceTemplate = new ServiceTemplate(1, 'fake_name', '   fake   alias       ok    ');
        expect($serviceTemplate->getAlias())->toBe('fake alias ok');
    }
);
