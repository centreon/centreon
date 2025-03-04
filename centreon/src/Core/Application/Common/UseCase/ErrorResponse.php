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

use Core\Common\Domain\Exception\DomainException;

/**
 * Class
 *
 * @class ErrorResponse
 * @package Core\Application\Common\UseCase
 *
 * @description This is a standard error response that has three properties to manage errors in the use cases:
 * - message : accepts either a string to be translated, or a Throwable object from which to obtain the message
 * - context : is an array to contain information of the use case
 * - exception : is a DomainException object to throw the use case exceptions in the presenter
 */
class ErrorResponse extends AbstractResponse
{
    /**
     * ErrorResponse constructor
     *
     * @param string|\Throwable $message Only to have a message
     * @param array<string,mixed> $context
     * @param DomainException|null $exception
     */
    public function __construct(
        string|\Throwable $message,
        private readonly array $context = [],
        private readonly ?DomainException $exception = null
    ) {
        parent::__construct($message);
    }

    /**
     * @return bool
     */
    public function hasException(): bool
    {
        return $this->exception !== null;
    }

    /**
     * @return array<string,mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @return DomainException|null
     */
    public function getException(): ?DomainException
    {
        return $this->exception;
    }
}
