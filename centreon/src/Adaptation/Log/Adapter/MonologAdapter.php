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

namespace Adaptation\Log\Adapter;

use Adaptation\Log\Channel\LogChannelInterface;
use Adaptation\Log\Exception\LoggerException;
use Adaptation\Log\Logger;
use App\Shared\Infrastructure\Logging\ExceptionFormatterProcessor;
use App\Shared\Infrastructure\Logging\PayloadSanitizer;
use App\Shared\Infrastructure\Logging\SanitizingProcessor;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\WebProcessor;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final class MonologAdapter implements LoggerInterface
{
    private static ?UidProcessor $uidProcessor = null;

    /**
     * @throws LoggerException
     */
    private function __construct(
        private readonly MonologLogger $logger,
        private readonly LogChannelInterface $channel,
    ) {
        $this->createLoggerFromChannel();
    }

    /**
     * @throws LoggerException
     */
    public static function create(LogChannelInterface $channel): LoggerInterface
    {
        $logger = new MonologLogger($channel->getChannelName());

        return new self($logger, $channel);
    }

    /**
     * Regenerates the shared UidProcessor uid.
     *
     * {@see self::$uidProcessor} is a process-lived static, which is correct under
     * php-fpm and CLI (one process == one request/command). It would become wrong
     * with a long-running consumer (e.g. an async Messenger transport), where the
     * uid must be reset between messages. Wire this through a `kernel.reset` tagged
     * service so the framework resets it on each work unit.
     */
    public static function reset(): void
    {
        self::$uidProcessor?->reset();
    }

    public function emergency(\Stringable|string $message, array $context = []): void
    {
        $this->logger->emergency($message, $context);
    }

    public function alert(\Stringable|string $message, array $context = []): void
    {
        $this->logger->alert($message, $context);
    }

    public function critical(\Stringable|string $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->logger->notice($message, $context);
    }

    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function debug(\Stringable|string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    /**
     * @param 'alert'|'critical'|'debug'|'emergency'|'error'|'info'|'notice'|'warning'|Level $level
     *
     * @throws LoggerException
     */
    public function log(mixed $level, \Stringable|string $message, array $context = []): void
    {
        try {
            $this->logger->log($level, $message, $context);
        } catch (\InvalidArgumentException $e) {
            throw new LoggerException(
                message: sprintf('Logging failed: %s', $e->getMessage()),
                previous: $e,
            );
        }
    }

    /**
     * @throws LoggerException
     */
    private function createLoggerFromChannel(): void
    {
        try {
            $handler = new StreamHandler(
                $this->getLogFileFromChannel($this->channel),
                LogLevel::INFO
            );

            $handler->setFormatter(new LineFormatter(null, Logger::DATE_FORMAT));

            $this->logger->pushHandler($handler);

            $this->pushPlatformProcessors();
        } catch (\InvalidArgumentException $e) {
            throw LoggerException::loggerCreationFailed($this->channel->getChannelName(), $e);
        }
    }

    private function pushPlatformProcessors(): void
    {
        // pushProcessor is LIFO: the first one pushed runs LAST. The sanitiser
        // must run after WebProcessor (which fills `extra.url`, query string
        // included), so it is pushed first to redact as the final step.
        $this->logger->pushProcessor(new SanitizingProcessor(new PayloadSanitizer()));
        $this->logger->pushProcessor(new ExceptionFormatterProcessor());
        $this->logger->pushProcessor(new WebProcessor());
        $this->logger->pushProcessor(self::$uidProcessor ??= new UidProcessor());
    }

    private function getLogFileFromChannel(LogChannelInterface $channel): string
    {
        $appEnv = (isset($_SERVER['APP_ENV']) && is_scalar($_SERVER['APP_ENV']))
            ? (string) $_SERVER['APP_ENV'] : 'prod';

        return sprintf('%s/%s', _CENTREON_LOG_, $channel->getLogFileName($appEnv));
    }
}
