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
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;

/**
 * @param $values*
 *
 * @return ServiceTemplate
 *
 * @throws \Assert\AssertionFailedException
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
        'description' => ServiceTemplate::MAX_DESCRIPTION_LENGTH,
        'note' => ServiceTemplate::MAX_NOTES_LENGTH,
        'noteUrl' => ServiceTemplate::MAX_NOTES_URL_LENGTH,
    ] as $field => $length
) {
    it(
        "should throw an exception when service template {$field} is an empty string",
        fn() => (createServiceTemplate([$field => ' ']))
    )->throws(
        AssertionException::class,
        AssertionException::notEmptyString("ServiceTemplate::$field")->getMessage()
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
            "ServiceTemplate::$field"
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
        fn() => (createServiceTemplate([$field => -1]))
    )->throws(
        AssertionException::class,
        AssertionException::min(
            -1,
            0,
            "ServiceTemplate::$field"
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
        fn() => (createServiceTemplate([$field => 0]))
    )->throws(
        AssertionException::class,
        AssertionException::min(
            0,
            1,
            "ServiceTemplate::$field"
        )->getMessage()
    );
}
