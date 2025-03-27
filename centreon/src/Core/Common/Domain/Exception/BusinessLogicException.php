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
 * @class   BusinessLogicException
 * @package Core\Common\Domain\Exception
 */
abstract class BusinessLogicException extends \Exception
{
    public const ERROR_CODE_INTERNAL = 0;
    public const ERROR_CODE_REPOSITORY = 1;
    public const ERROR_CODE_BAD_USAGE = 4;

    /** @var array<string,mixed> */
    private array $context = [];

    /** @var array<string,mixed> */
    private array $businessContext;

    /** @var array<string,mixed> */
    private array $exceptionContext;

    /**
     * BusinessLogicException constructor
     *
     * @param string $message
     * @param int $code
     * @param array<string,mixed> $context
     * @param \Throwable|null $previous
     */
    public function __construct(string $message, int $code, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->setBusinessContext($context);
        $this->setExceptionContext();
        $this->setGlobalContext();
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

    /**
     * @return array<string,mixed>
     */
    public function getBusinessContext(): array
    {
        return $this->businessContext;
    }

    /**
     * @return array<string,mixed>
     */
    public function getExceptionContext(): array
    {
        return $this->exceptionContext;
    }

    // ----------------------------------------- PRIVATE METHODS -----------------------------------------

    /**
     * @return void
     */
    private function setGlobalContext(): void
    {
        $this->context = array_merge(
            $this->getExceptionContext(),
            ['context' => $this->getBusinessContext()]
        );
    }

    /**
     * @param array<string,mixed> $context
     *
     * @return void
     */
    private function setBusinessContext(array $context): void
    {
        $previousContext = null;
        if ($this->getPrevious() instanceof self) {
            $previousContext = $this->getPrevious()->getBusinessContext();
        }
        $this->businessContext = array_merge(
            $context, ['previous' => $previousContext]
        );
    }

    /**
     * @return void
     */
    private function setExceptionContext(): void {
        $this->exceptionContext = ExceptionFormatter::format($this);
    }
}
