<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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
use Psr\Log\LoggerInterface;

/**
 * This class is design to provide all the methods for recording events.
 */
trait LoggerTrait
{
    private ?ContactInterface $loggerContact = null;

    private ?LoggerInterface $logger = null;

    private ?ContactForDebug $loggerContactForDebug = null;

    /** @var string[] */
    private array $ignoredFiles = [];

    /**
     * @param ContactInterface $loggerContact
     *
     * @required
     */
    public function setLoggerContact(ContactInterface $loggerContact): void
    {
        $this->loggerContact = $loggerContact;
    }

    /**
     * @param ContactForDebug $loggerContactForDebug
     *
     * @required
     */
    public function setLoggerContactForDebug(ContactForDebug $loggerContactForDebug): void
    {
        $this->loggerContactForDebug = $loggerContactForDebug;
    }

    /**
     * @param LoggerInterface $centreonLogger
     *
     * @required
     */
    public function setLogger(LoggerInterface $centreonLogger): void
    {
        $this->logger = $centreonLogger;
        $this->addIgnoreClass(LoggerTrait::class);
    }

    public function addIgnoreClass(string $className): void
    {
        if (class_exists($className) || trait_exists($className)) {
            $reflexion = new \ReflectionClass($className);
            if ($filename = $reflexion->getFileName()) {
                $this->ignoredFiles[] = $filename;
            }
        }
    }

    /**
     * @param string $message
     * @param mixed[] $context
     * @param callable|null $callable
     *
     * @see \Psr\Log\LoggerInterface::emergency()
     */
    private function emergency(string $message, array $context = [], ?callable $callable = null): void
    {
        if ($this->canBeLogged()) {
            if ($callable !== null) {
                $context = array_merge($context, $callable());
            }
            $this->logger->emergency($this->prefixMessage($message), $context);
        }
    }

    /**
     * @param string $message
     * @param mixed[] $context
     * @param callable|null $callable
     *
     * @see \Psr\Log\LoggerInterface::alert()
     */
    private function alert(string $message, array $context = [], ?callable $callable = null): void
    {
        if ($this->canBeLogged()) {
            if ($callable !== null) {
                $context = array_merge($context, $callable());
            }
            $this->logger->alert($this->prefixMessage($message), $context);
        }
    }

    /**
     * @param string $message
     * @param mixed[] $context
     * @param callable|null $callable
     *
     * @see \Psr\Log\LoggerInterface::critical()
     */
    private function critical(string $message, array $context = [], ?callable $callable = null): void
    {
        if ($this->canBeLogged()) {
            if ($callable !== null) {
                $context = array_merge($context, $callable());
            }
            $this->logger->critical($this->prefixMessage($message), $context);
        }
    }

    /**
     * @param string $message
     * @param mixed[] $context
     * @param callable|null $callable
     *
     * @see \Psr\Log\LoggerInterface::error()
     */
    private function error(string $message, array $context = [], ?callable $callable = null): void
    {
        if ($this->canBeLogged()) {
            if ($callable !== null) {
                $context = array_merge($context, $callable());
            }
            $this->logger->error($this->prefixMessage($message), $context);
        }
    }

    /**
     * @param string $message
     * @param mixed[] $context
     * @param callable|null $callable
     *
     * @see \Psr\Log\LoggerInterface::warning()
     */
    private function warning(string $message, array $context = [], ?callable $callable = null): void
    {
        if ($this->canBeLogged()) {
            if ($callable !== null) {
                $context = array_merge($context, $callable());
            }
            $this->logger->warning($this->prefixMessage($message), $context);
        }
    }

    /**
     * @param string $message
     * @param mixed[] $context
     * @param callable|null $callable
     *
     * @see \Psr\Log\LoggerInterface::notice()
     */
    private function notice(string $message, array $context = [], ?callable $callable = null): void
    {
        if ($this->canBeLogged()) {
            if ($callable !== null) {
                $context = array_merge($context, $callable());
            }
            $this->logger->notice($this->prefixMessage($message), $context);
        }
    }

    /**
     * @param string $message
     * @param mixed[] $context
     * @param callable|null $callable
     *
     * @see \Psr\Log\LoggerInterface::info()
     */
    private function info(string $message, array $context = [], ?callable $callable = null): void
    {
        if ($this->canBeLogged()) {
            if ($callable !== null) {
                $context = array_merge($context, $callable());
            }
            $this->logger->info($this->prefixMessage($message), $context);
        }
    }

    /**
     * @param string $message
     * @param mixed[] $context
     * @param callable|null $callable
     *
     * @see \Psr\Log\LoggerInterface::debug()
     */
    private function debug(string $message, array $context = [], ?callable $callable = null): void
    {
        if ($this->canBeLogged()) {
            if ($callable !== null) {
                $context = array_merge($context, $callable());
            }
            $this->logger->debug($this->prefixMessage($message), $context);
        }
    }

    /**
     * @param mixed $level
     * @param string $message
     * @param mixed[] $context
     * @param callable|null $callable
     *
     * @throws \Psr\Log\InvalidArgumentException
     *
     * @see \Psr\Log\LoggerInterface::log()
     */
    private function log($level, string $message, array $context = [], ?callable $callable = null): void
    {
        if ($this->canBeLogged()) {
            if ($callable !== null) {
                $context = array_merge($context, $callable());
            }
            $this->logger->log($level, $this->prefixMessage($message), $context);
        }
    }

    /**
     * @param string $message
     *
     * @return string
     */
    private function prefixMessage(string $message): string
    {
        $debugTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        /**
         * @var array{file: string, line: int} $trace
         */
        foreach ($debugTrace as $trace) {
            $file = $trace['file'];
            if (in_array($file, $this->ignoredFiles, true)) {
                continue;
            }
            if (str_starts_with($file, _CENTREON_PATH_)) {
                $file = mb_substr($file, mb_strlen(_CENTREON_PATH_));
            }
            $callingClass = $file . ':' . $trace['line'];

            return sprintf('[%s]: %s', $callingClass, $message);
        }

        return '';
    }

    /**
     * @return bool
     */
    private function canBeLogged(): bool
    {
        return $this->logger !== null
            && $this->loggerContact !== null
            && $this->loggerContactForDebug !== null
            && $this->loggerContactForDebug->isValidForContact($this->loggerContact);
    }
}
