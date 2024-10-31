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

namespace Tests\Core\Notification\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Notification\Domain\Model\Message;
use Core\Notification\Domain\Model\Channel;

beforeEach(function (): void {
    $this->channel = Channel::Slack;
    $this->subject = 'some subject';
    $this->message = 'some message';
    $this->formattedMessage = '<p>some message</p>';
});

it('should return properly set notification message instance', function (): void {
    $message = new Message(
        $this->channel,
        $this->subject,
        $this->message,
        $this->formattedMessage
    );

    expect($message->getChannel())->toBe(Channel::Slack)
        ->and($message->getSubject())->toBe($this->subject)
        ->and($message->getRawMessage())->toBe($this->message)
        ->and($message->getFormattedMessage())->toBe($this->formattedMessage);
});

it('should trim the "subject" and "message" fields', function (): void {
    $message = new Message(
        $this->channel,
        $subjectWithSpaces = '  my-subject  ',
        $messageWithSpaces = '  my-message  ',
        $formattedMessageWithSpaces = '  <p>my-message</p>  ',
    );

    expect($message->getSubject())->toBe(trim($subjectWithSpaces))
        ->and($message->getRawMessage())->toBe(trim($messageWithSpaces))
        ->and($message->getFormattedMessage())->toBe(trim($formattedMessageWithSpaces));
});

it('should throw an exception when notification message subject is too long', function (): void {
    new Message(
        $this->channel,
        str_repeat('a', Message::MAX_SUBJECT_LENGTH + 1),
        $this->message
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', Message::MAX_SUBJECT_LENGTH + 1),
        Message::MAX_SUBJECT_LENGTH + 1,
        Message::MAX_SUBJECT_LENGTH,
        'Message::subject'
    )->getMessage()
);

it('should throw an exception when notification message content is too long', function (): void {
    new Message(
        $this->channel,
        $this->subject,
        str_repeat('a', Message::MAX_MESSAGE_LENGTH + 1),
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', Message::MAX_MESSAGE_LENGTH + 1),
        Message::MAX_MESSAGE_LENGTH + 1,
        Message::MAX_MESSAGE_LENGTH,
        'Message::message'
    )->getMessage()
);

it('should throw an exception when notification formatted message content is too long', function (): void {
    new Message(
        $this->channel,
        $this->subject,
        $this->message,
        str_repeat('a', Message::MAX_MESSAGE_LENGTH + 1),
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', Message::MAX_MESSAGE_LENGTH + 1),
        Message::MAX_MESSAGE_LENGTH + 1,
        Message::MAX_MESSAGE_LENGTH,
        'Message::formattedMessage'
    )->getMessage()
);
