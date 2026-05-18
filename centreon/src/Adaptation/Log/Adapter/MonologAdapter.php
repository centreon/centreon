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

use Adaptation\Log\Enum\LogChannelEnum;
use Adaptation\Log\Exception\LoggerException;
use Adaptation\Log\Logger;
use App\Shared\Infrastructure\Logging\ExceptionFormatterProcessor;
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
    /**
     * Shared across every channel-specific logger created in the
     * current process so that records produced on different channels
     * carry the same `extra.uid` and can be correlated across files —
     * mirrors the platform-wide UidProcessor wired in
     * `config.new/services/monolog.php` for the new kernel.
     */
    private static ?UidProcessor $uidProcessor = null;

    /**
     * @throws LoggerException
     */
    private function __construct(
        private readonly MonologLogger $logger,
        private readonly LogChannelEnum $channel,
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
            $handler = new StreamHandler(
                $this->getLogFileFromChannel($this->channel),
                LogLevel::INFO
            );

            $handler->setFormatter(new LineFormatter(null, Logger::DATE_FORMAT));

            $this->logger->pushHandler($handler);

            $this->pushPlatformProcessors();
        } catch (\InvalidArgumentException $e) {
            throw LoggerException::loggerCreationFailed($this->channel->value, $e);
        }
    }

    /**
     * Attach the autonomous halves of the platform processor stack so
     * legacy records carry the same `extra.uid`, the same HTTP context
     * and the same exception layout as records produced by the new
     * kernel (cf. MON-151077, `config.new/services/monolog.php`).
     *
     * RouteProcessor and TokenProcessor depend on the Symfony
     * RequestStack / TokenStorage services and are therefore out of
     * reach from the legacy stack — records emitted here will not
     * carry `extra.controller`, `extra.route` or `extra.token`.
     */
    private function pushPlatformProcessors(): void
    {
        $this->logger->pushProcessor(new ExceptionFormatterProcessor());
        $this->logger->pushProcessor(new WebProcessor());
        $this->logger->pushProcessor(self::$uidProcessor ??= new UidProcessor());
    }

    /**
     * Pattern: _CENTREON_LOG_/<APP_ENV>.<slug>.log
     *  - _CENTREON_LOG_ is defined in the main Centreon configuration file (centreon.conf.php)
     *  - <APP_ENV> is defined by the current Symfony mode (prod, dev, test)
     *  - <slug> is the file-name slug carried by LogChannelEnum (cf. authentication → access)
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
            $channelEnum->getLogFileSlug()
        );
    }
}
