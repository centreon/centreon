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

namespace Core\Notification\Domain\Model;

use Centreon\Domain\Common\Assertion\Assertion;

class Message
{
    public const MAX_SUBJECT_LENGTH = 255;
    public const MAX_MESSAGE_LENGTH = 65535;

    /**
     * @param Channel $channel
     * @param string $subject
     * @param string $message
     * @param string $formattedMessage
     *
     * @throws \Assert\AssertionFailedException
     */
    public function __construct(
        protected Channel $channel,
        protected string $subject = '',
        protected string $message = '',
        protected string $formattedMessage = ''
    ) {
        $shortName = (new \ReflectionClass($this))->getShortName();

        $this->subject = trim($subject);
        $this->message = trim($message);
        $this->formattedMessage = trim($formattedMessage);

        Assertion::notEmptyString($this->subject, "{$shortName}::subject");
        Assertion::notEmptyString($this->message, "{$shortName}::message");
        Assertion::maxLength($this->subject, self::MAX_SUBJECT_LENGTH, "{$shortName}::subject");
        Assertion::maxLength($this->message, self::MAX_MESSAGE_LENGTH, "{$shortName}::message");
        Assertion::maxLength($this->formattedMessage, self::MAX_MESSAGE_LENGTH, "{$shortName}::formattedMessage");
    }

    public function getChannel(): Channel
    {
        return $this->channel;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getRawMessage(): string
    {
        return $this->message;
    }

    public function getFormattedMessage(): string
    {
        return $this->formattedMessage;
    }
}
