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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * This class is design to provide all the methods for recording events.
 */
trait LoggerTrait
{
    private ?ContactInterface $loggerContact = null;

    private ?LoggerInterface $logger = null;

    private ?ContactForDebug $loggerContactForDebug = null;

    /**
     * @param ContactInterface $loggerContact
     */
    #[Required]
    public function setLoggerContact(ContactInterface $loggerContact): void
    {
        $this->loggerContact = $loggerContact;
    }

    /**
     * @param ContactForDebug $loggerContactForDebug
     */
    #[Required]
    public function setLoggerContactForDebug(ContactForDebug $loggerContactForDebug): void
    {
        $this->loggerContactForDebug = $loggerContactForDebug;
    }

    /**
     * @param LoggerInterface $centreonLogger
     */
    #[Required]
    public function setLogger(LoggerInterface $centreonLogger): void
    {
        $this->logger = $centreonLogger;
    }

    /**
     * @param string $message
     * @param array<string,mixed> $context
     * @param callable|null $callable
     *
     * @see LoggerInterface::emergency
     */
    private function emergency(string $message, array $context = [], ?callable $callable = null): void
    {
        $this->executeLog(LogLevel::EMERGENCY, $message, $context, $callable);
    }

    /**
     * @param string $message
     * @param array<string,mixed> $context
     * @param callable|null $callable
     *
     * @see LoggerInterface::alert
     */
    private function alert(string $message, array $context = [], ?callable $callable = null): void
    {
        $this->executeLog(LogLevel::ALERT, $message, $context, $callable);
    }

    /**
     * @param string $message
     * @param array<string,mixed> $context
     * @param callable|null $callable
     *
     * @see LoggerInterface::critical
     */
    private function critical(string $message, array $context = [], ?callable $callable = null): void
    {
        $this->executeLog(LogLevel::CRITICAL, $message, $context, $callable);
    }

    /**
     * @param string $message
     * @param array<string,mixed> $context
     * @param callable|null $callable
     *
     * @see LoggerInterface::error
     */
    private function error(string $message, array $context = [], ?callable $callable = null): void
    {
        $this->executeLog(LogLevel::ERROR, $message, $context, $callable);
    }

    /**
     * @param string $message
     * @param array<string,mixed> $context
     * @param callable|null $callable
     *
     * @see LoggerInterface::warning
     */
    private function warning(string $message, array $context = [], ?callable $callable = null): void
    {
        $this->executeLog(LogLevel::WARNING, $message, $context, $callable);
    }

    /**
     * @param string $message
     * @param array<string,mixed> $context
     * @param callable|null $callable
     *
     * @see LoggerInterface::notice
     */
    private function notice(string $message, array $context = [], ?callable $callable = null): void
    {
        $this->executeLog(LogLevel::NOTICE, $message, $context, $callable);
    }

    /**
     * @param string $message
     * @param array<string,mixed> $context
     * @param callable|null $callable
     *
     * @see LoggerInterface::info
     */
    private function info(string $message, array $context = [], ?callable $callable = null): void
    {
        $this->executeLog(LogLevel::INFO, $message, $context, $callable);
    }

    /**
     * @param string $message
     * @param array<string,mixed> $context
     * @param callable|null $callable
     *
     * @see LoggerInterface::debug
     */
    private function debug(string $message, array $context = [], ?callable $callable = null): void
    {
        $this->executeLog(LogLevel::DEBUG, $message, $context, $callable);
    }

    /**
     * @param mixed $level
     * @param string $message
     * @param array<string,mixed> $context
     * @param callable|null $callable
     *
     * @throws InvalidArgumentException
     *
     * @see LoggerInterface::log
     */
    private function log($level, string $message, array $context = [], ?callable $callable = null): void
    {
        $this->executeLog($level, $message, $context, $callable);
    }

    /**
     * @return bool
     */
    private function canBeLogged(): bool
    {
        return $this->logger !== null
            && $this->loggerContactForDebug !== null
            && $this->loggerContact !== null
            && $this->loggerContactForDebug->isValidForContact($this->loggerContact);
    }

    /**
     * @param string $level
     * @param string $message
     * @param array<string,mixed> $context
     * @param callable|null $callable
     *
     * @return void
     */
    private function executeLog(
        string $level,
        string $message,
        array $context = [],
        ?callable $callable = null
    ): void {
        if ($this->canBeLogged()) {
            if ($callable !== null) {
                $context = array_merge($context, $callable());
            }
            $normalizedContext = $this->normalizeContext($context);
            $this->logger->log($level, $message, $normalizedContext);
        }
    }

    /**
     * @param array<string,mixed> $customContext
     *
     * @return array<string,mixed>
     */
    private function normalizeContext(array $customContext): array
    {
        // Add default context with request infos
        $defaultContext = [
            'request_infos' => [
                'uri' => isset($_SERVER['REQUEST_URI']) ? urldecode($_SERVER['REQUEST_URI']) : null,
                'http_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'server' => $_SERVER['SERVER_NAME'] ?? null,
            ],
        ];

        $exceptionContext = [];
        if (isset($customContext['exception'])) {
            $exceptionContext = $customContext['exception'];
            unset($customContext['exception']);
        }

        return [
            'custom' => $customContext !== [] ? $customContext : null,
            'exception' => $exceptionContext !== [] ? $exceptionContext : null,
            'default' => $defaultContext,
        ];
    }

}
