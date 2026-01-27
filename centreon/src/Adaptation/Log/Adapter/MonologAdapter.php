<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

use Adaptation\Log\Enum\LogChannelEnum;
use Adaptation\Log\Exception\LoggerException;
use Adaptation\Log\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final readonly class MonologAdapter implements LoggerInterface
{
    /**
     * @throws LoggerException
     */
    private function __construct(
        private MonologLogger $logger,
        private LogChannelEnum $channel,
    ) {
        $this->createLoggerFromChannel();
    }

    /**
     * @throws LoggerException
     */
    public static function create(LogChannelEnum $channel): LoggerInterface
    {
        $logger = new MonologLogger($channel->value);

        return new self($logger, $channel);
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
            $handler = match ($this->channel) {
                LogChannelEnum::PASSWORD => new StreamHandler(
                    $this->getLogFileFromChannel(LogChannelEnum::PASSWORD),
                    LogLevel::INFO
                ),
                LogChannelEnum::TOKEN => new StreamHandler(
                    $this->getLogFileFromChannel(LogChannelEnum::TOKEN),
                    LogLevel::INFO
                ),
                // TODO if another channel is needed, uncomment the following line
                // default => throw LoggerException::channelNotConfigured($this->channel->value),
            };

            $handler->setFormatter(new LineFormatter(null, Logger::DATE_FORMAT));

            $this->logger->pushHandler($handler);
        } catch (\InvalidArgumentException $e) {
            throw LoggerException::loggerCreationFailed($this->channel->value, $e);
        }
    }

    /**
     * Pattern: _CENTREON_LOG_/<APP_ENV>.<channel>.log
     *  - _CENTREON_LOG_ is defined in the main Centreon configuration file (centreon.conf.php)
     *  - <APP_ENV> is defined by the current Symfony mode (prod, dev, test)
     *  - <channel> is the channel name defined in LogChannelEnum
     * Example: /var/log/centreon/prod.password.log
     */
    private function getLogFileFromChannel(LogChannelEnum $channelEnum): string
    {
        $appEnv = (isset($_SERVER['APP_ENV']) && is_scalar($_SERVER['APP_ENV']))
            ? (string) $_SERVER['APP_ENV'] : 'prod';

        return sprintf(
            '%s/%s.%s.log',
            _CENTREON_LOG_,
            $appEnv,
            (string) $channelEnum->value
        );
    }
}
