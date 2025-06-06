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

namespace Core\Application\Common\UseCase;

/**
 * This is standard Error Response which accepts either
 * - a string which will be translated
 * - a Throwable from which we will get the message.
 */
abstract class AbstractResponse implements ResponseStatusInterface
{
    /**
     * @param string|\Throwable $message
     * @param array<string,mixed> $context
     */
    public function __construct(
        private readonly string|\Throwable $message,
        private readonly array $context = [],
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): string
    {
        return \is_string($this->message)
            ? _($this->message)
            : $this->message->getMessage();
    }
}
