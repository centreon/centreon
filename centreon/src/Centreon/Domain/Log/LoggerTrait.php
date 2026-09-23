<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Centreon\Domain\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Deprecated (no @deprecated tag, to avoid DebugClassLoader cascade warnings):
 * write to {@see \Adaptation\Log\Logger} directly.
 */
trait LoggerTrait
{
    private ?LoggerInterface $logger = null;

    #[Required]
    public function setLogger(LoggerInterface $centreonLogger): void
    {
        $this->logger = $centreonLogger;
    }

    /**
     * @param array<string,mixed> $context
     *
     * @see LoggerInterface::emergency
     */
    private function emergency(string $message, array $context = [], ?callable $callable = null): void
    {
        $this->executeLog(LogLevel::EMERGENCY, $message, $context, $callable);
    }

    /**
     * @param array<string,mixed> $context
     *
     * @see LoggerInterface::alert
     */
    private function alert(string $message, array $context = [], ?callable $callable = null): void
    {
        $this->executeLog(LogLevel::ALERT, $message, $context, $callable);
    }

    /**
     * @param array<string,mixed> $context
     *
     * @see LoggerInterface::critical
     */
    private function critical(string $message, array $context = [], ?callable $callable = null): void
    {
        $this->executeLog(LogLevel::CRITICAL, $message, $context, $callable);
    }

    /**
     * @param array<string,mixed> $context
     *
     * @see LoggerInterface::error
     */
    private function error(string $message, array $context = [], ?callable $callable = null): void
    {
        $this->executeLog(LogLevel::ERROR, $message, $context, $callable);
    }

    /**
     * @param array<string,mixed> $context
     *
     * @see LoggerInterface::warning
     */
    private function warning(string $message, array $context = [], ?callable $callable = null): void
    {
        $this->executeLog(LogLevel::WARNING, $message, $context, $callable);
    }

    /**
     * @param array<string,mixed> $context
     *
     * @see LoggerInterface::notice
     */
    private function notice(string $message, array $context = [], ?callable $callable = null): void
    {
        $this->executeLog(LogLevel::NOTICE, $message, $context, $callable);
    }

    /**
     * @param array<string,mixed> $context
     *
     * @see LoggerInterface::info
     */
    private function info(string $message, array $context = [], ?callable $callable = null): void
    {
        $this->executeLog(LogLevel::INFO, $message, $context, $callable);
    }

    /**
     * @param array<string,mixed> $context
     *
     * @see LoggerInterface::debug
     */
    private function debug(string $message, array $context = [], ?callable $callable = null): void
    {
        $this->executeLog(LogLevel::DEBUG, $message, $context, $callable);
    }

    /**
     * @param array<string,mixed> $context
     *
     * @see LoggerInterface::log
     */
    private function log(int|string $level, string $message, array $context = [], ?callable $callable = null): void
    {
        $this->executeLog($level, $message, $context, $callable);
    }

    private function canBeLogged(): bool
    {
        return $this->logger !== null;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function executeLog(
        int|string $level,
        string $message,
        array $context = [],
        ?callable $callable = null,
    ): void {
        if (! $this->canBeLogged()) {
            return;
        }

        if ($callable !== null) {
            $context = array_merge($context, $callable());
        }

        $this->logger->log($level, $message, $context);
    }
}
