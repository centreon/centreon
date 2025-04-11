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
use Core\Common\Domain\Exception\ExceptionFormatter;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class
 *
 * @class ExceptionLogger
 * @package Core\Common\Infrastructure
 */
final readonly class ExceptionLogger
{
    /**
     * ExceptionLogger constructor
     *
     * @param LoggerInterface $logger
     */
    public function __construct(
        private LoggerInterface $logger
    ) {}

    /**
     * @param \Throwable $throwable
     * @param array<string,mixed> $context
     * @param string $level {@see LogLevel}
     *
     * @return void
     */
    public function log(\Throwable $throwable, array $context = [], string $level = LogLevel::ERROR): void
    {
        $context = $this->prepareContext($context, $throwable);
        $this->logger->log($level, $throwable->getMessage(), $context);
    }

    // ----------------------------------------- PRIVATE METHODS -----------------------------------------

    /**
     * @param array<string,mixed> $customContext
     * @param \Throwable $throwable
     *
     * @return array<string,mixed>
     */
    private function prepareContext(array $customContext, \Throwable $throwable): array
    {
        $customContext = $this->formatCustomContext($throwable, $customContext);
        $exceptionContext = $this->formatExceptionContext($throwable);
        $context['custom'] = ! empty($customContext) ? $customContext : null;
        $context['exception'] = ! empty($exceptionContext) ? $exceptionContext : null;

        return $context;
    }

    /**
     * @param \Throwable $throwable
     *
     * @return array<string,mixed>
     */
    private function formatExceptionContext(\Throwable $throwable): array
    {
        $exceptionContext = [];

        if ($throwable instanceof BusinessLogicException) {
            $firstThrowable = $throwable->getExceptionContext();
        } else {
            $firstThrowable = ExceptionFormatter::format($throwable);
        }

        $previousList = $this->cleanPreviousCollection($this->getPreviousCollection($firstThrowable));

        if (array_key_exists('previous', $firstThrowable)) {
            unset($firstThrowable['previous']);
        }

        $exceptionContext['exceptions'] = array_merge([$firstThrowable], $previousList);
        $exceptionContext['traces'] = $throwable->getTrace();

        return $exceptionContext;
    }

    /**
     * @param \Throwable $throwable
     * @param array<string,mixed> $customContext
     *
     * @return array<string,mixed>
     */
    private function formatCustomContext(\Throwable $throwable, array $customContext): array
    {
        if ($throwable instanceof BusinessLogicException) {
            $firstThrowableContext = $throwable->getBusinessContext();
            $previousListContext = $this->cleanPreviousCollection($this->getPreviousCollection($firstThrowableContext));

            if (array_key_exists('previous', $firstThrowableContext)) {
                unset($firstThrowableContext['previous']);
            }
            $firstThrowableContext = ! empty($firstThrowableContext) ? [$firstThrowableContext] : [];
            $contextExceptions = array_merge($firstThrowableContext, $previousListContext);

            $customContext['from_exception'] = $contextExceptions;
        }

        return $customContext;
    }

    private function getPreviousCollection(array $context): array
    {
        $previousList = [];
        if (isset($context['previous'])) {
            $previousList[] = $context['previous'];
            $previousList = array_merge($previousList, $this->getPreviousCollection($context['previous']));
        }

        return $previousList;
    }

    /**
     * @param array<int,array<string,mixed>> $previousList
     *
     * @return array
     */
    private function cleanPreviousCollection(array $previousList): array
    {
        $previousListCleaned = [];
        foreach ($previousList as $previous) {
            if (array_key_exists('previous', $previous)) {
                unset($previous['previous']);
            }
            if (! empty($previous)) {
                $previousListCleaned[] = $previous;
            }
        }

        return $previousListCleaned;
    }
}
