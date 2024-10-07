<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

declare(strict_types = 1);

namespace Tests\Core\Notification\Application\UseCase\UpdateNotification\Factory;


use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Notification\Application\UseCase\UpdateNotification\Factory\NotificationMessageFactory;

it('should throw an AssertionException when a message has an empty subject', function (): void {
    $messages = [
        [
            "channel" => "Email",
            "subject" => "",
            "message" => "This is my message",
            "formatted_message" => "<h1>This is my message</h1>",
        ]
    ];

    NotificationMessageFactory::createMultipleMessage($messages);
})->throws(AssertionException::class);
