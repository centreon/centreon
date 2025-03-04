<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\Common\Domain\Exception;

/**
 * Class
 *
 * @class   DomainException
 * @package Core\Common\Domain\Exception
 */
abstract class DomainException extends \Exception
{
    public const ERROR_CODE_INTERNAL = 0;
    public const ERROR_CODE_REPOSITORY = 1;
    public const ERROR_CODE_BAD_USAGE = 4;

    /**
     * DomainException constructor
     *
     * @param string $message
     * @param int $code
     * @param array<string,mixed> $context
     * @param \Throwable|null $previous
     */
    public function __construct(string $message, int $code, protected array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->addExceptionContext();
    }

    /**
     * @return array<string,mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @param array<string,mixed> $context
     *
     * @return void
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    /**
     * @param string $name
     * @param mixed $value
     *
     * @return void
     */
    public function addContextItem(string $name, mixed $value): void
    {
        $this->context[$name] = $value;
    }

    /**
     * @param array<string,mixed> $newContext
     *
     * @return void
     */
    public function addContext(array $newContext): void
    {
        $this->context = array_merge($this->getContext(), $newContext);
    }

    // ----------------------------------------- PRIVATE METHODS -----------------------------------------

    /**
     * @return void
     */
    private function addExceptionContext(): void
    {
        $exceptionContext = $this->getExceptionContext($this);
        $exceptionContext['previous'] = ($this->getPrevious() !== null)
            ? $this->getExceptionContext($this->getPrevious()) : null;
        $this->addContextItem('exception', $exceptionContext);
    }

    /**
     * @param \Throwable $throwable
     *
     * @return array<string,mixed>
     */
    private function getExceptionContext(\Throwable $throwable): array
    {
        $exceptionContext = [
            'type' => $throwable::class,
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'code' => $throwable->getCode(),
        ];

        if (! empty($throwable->getTrace())) {
            if (isset($throwable->getTrace()[0]['class'])) {
                $exceptionContext['class'] = $throwable->getTrace()[0]['class'];
            }
            if (isset($throwable->getTrace()[0]['function'])) {
                $exceptionContext['method'] = $throwable->getTrace()[0]['function'];
            }
        }

        return $exceptionContext;
    }
}
