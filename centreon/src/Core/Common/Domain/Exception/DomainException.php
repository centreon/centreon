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
        $exceptionContext['trace'] = $this->getSerializedExceptionTraces();
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

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getSerializedExceptionTraces(): array
    {
        // Retrieve traces but limit args to 100 characters to avoid bloating the logs
        $traces = $this->getTrace();
        for ($idx = 0, $idxMax = count($traces); $idx < $idxMax; $idx++) {
            $traceArguments = [];
            if (isset($traces[$idx]['args']) && is_countable($traces[$idx]['args'])) {
                foreach ($traces[$idx]['args'] as $argKey => $arg) {
                    // in the case of an object, convert it to an array with only public attributes
                    if (is_object($arg)) {
                        $arg = get_object_vars($arg);
                    }
                    // if it is an array, remove stream resources that prevent JSON encoding
                    if (is_iterable($arg)) {
                        foreach ($arg as $attributeKey => $attribute) {
                            if (is_resource($attribute)) {
                                unset($arg[$attributeKey]);
                            }
                        }
                    }
                    // rewrite the transformed arguments into a new array to avoid modifying a variable by reference
                    $traceArguments[$argKey] = $arg;
                }
            }
            // if an error occurs during JSON encoding, we put an empty array for the arguments in the log
            try {
                $encodedArgs = json_encode(
                    $traceArguments,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                );
                $traces[$idx]['args'] = mb_substr($encodedArgs, 0, 100) . '[...]';
            } catch (\JsonException) {
                $traces[$idx]['args'] = '[]';
            }
        }

        return $traces;
    }
}
