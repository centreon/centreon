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

namespace Core\Common\Infrastructure;

use Core\Common\Domain\Exception\BusinessLogicException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class
 *
 * @class ExceptionHandler
 * @package Core\Common\Infrastructure
 */
final readonly class ExceptionHandler
{
    /**
     * ExceptionHandler constructor
     *
     * @param LoggerInterface $logger
     */
    public function __construct(
        private LoggerInterface $logger
    ) {}

    /**
     * @param \Throwable $exception
     * @param array<string,mixed> $context
     * @param string $level see {@see LogLevel}
     *
     * @return void
     */
    public function log(\Throwable $exception, array $context = [], string $level = LogLevel::ERROR): void
    {
        $this->logger->log($level, $exception->getMessage(), $this->getContext($context, $exception));
    }

    // ----------------------------------------- PRIVATE METHODS -----------------------------------------

    /**
     * @param array<string,mixed> $context
     * @param \Throwable $exception
     *
     * @return array<string,mixed>
     */
    private function getContext(array $context, \Throwable $exception): array
    {
        $defaultContext = [
            'request_infos' => [
                'url' => $_SERVER['REQUEST'] ?? null,
                'http_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'server' => $_SERVER['SERVER_NAME'] ?? null,
                'referrer' => $_SERVER['HTTP_REFERER'] ?? null,
            ],
        ];

        if ($exception instanceof BusinessLogicException) {
            $exceptionContext = $exception->getExceptionContext();
            $customContext = array_merge($context, ['from_exception' => $exception->getBusinessContext()]);
        } else {
            $exceptionContext = $this->getExceptionContext($exception);
            $customContext = $context;
        }

        $exceptionContext['trace'] = $this->getSerializedExceptionTraces($exception);

        return [
            'context' => ['default' => $defaultContext, 'custom' => $customContext],
            'exception' => $exceptionContext,
        ];
    }

    /**
     * @param \Throwable $exception
     *
     * @return array<string,mixed>
     */
    private function getExceptionContext(\Throwable $exception): array
    {
        $exceptionContext = $this->getExceptionInfos($exception);
        $exceptionContext['previous'] = ($exception->getPrevious() !== null)
            ? $this->getExceptionInfos($exception->getPrevious()) : null;

        return $exceptionContext;
    }

    /**
     * @param \Throwable $exception
     *
     * @return array<string,mixed>
     */
    private function getExceptionInfos(\Throwable $exception): array
    {
        $exceptionContext = [
            'type' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
        ];

        if (! empty($exception->getTrace())) {
            if (isset($exception->getTrace()[0]['class'])) {
                $exceptionContext['class'] = $exception->getTrace()[0]['class'];
            }
            if (isset($exception->getTrace()[0]['function'])) {
                $exceptionContext['method'] = $exception->getTrace()[0]['function'];
            }
        }

        return $exceptionContext;
    }

    /**
     * @param \Throwable $exception
     *
     * @return array<int,array<string,mixed>>
     */
    private function getSerializedExceptionTraces(\Throwable $exception): array
    {
        // Retrieve traces but limit args to 100 characters to avoid bloating the logs
        $traces = $exception->getTrace();
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
            } catch (\JsonException $exception) {
                $this->logger->error('Error while encoding trace arguments', ['exception' => $exception]);
                $traces[$idx]['args'] = '[]';
            }
        }

        return $traces;
    }
}
