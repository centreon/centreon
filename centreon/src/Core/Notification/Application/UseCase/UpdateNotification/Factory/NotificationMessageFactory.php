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

namespace Core\Notification\Application\UseCase\UpdateNotification\Factory;

use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Domain\Model\Channel;
use Core\Notification\Domain\Model\Message;

class NotificationMessageFactory
{
    /**
     * Create a NotificationMessage.
     *
     * @param Channel $messageType
     * @param array{
     *     channel: string,
     *     subject: string,
     *     message: string,
     *     formatted_message: string
     * } $message
     *
     * @throws \Assert\AssertionFailedException
     *
     * @return Message
     */
    public static function create(Channel $messageType, array $message): Message
    {
        return new Message(
            $messageType,
            $message['subject'],
            $message['message'],
            $message['formatted_message']
        );
    }

    /**
     * Create multiple NotificationMessage.
     *
     * @param array<array{
     *      channel: string,
     *      subject: string,
     *      message: string,
     *      formatted_message: string
     * }> $messages
     *
     * @throws \Assert\AssertionFailedException
     *
     * @return Message[]
     */
    public static function createMultipleMessage(array $messages): array
    {
        if (empty($messages)) {
            throw NotificationException::emptyArrayNotAllowed('message');
        }

        $newMessages = [];
        foreach ($messages as $message) {
            $messageType = Channel::from($message['channel']);
            // If multiple message with same type are defined, only the last one of each type is kept
            $newMessages[$messageType->value] = self::create($messageType, $message);
        }

        return $newMessages;
    }
}
