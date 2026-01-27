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

namespace Adaptation\Log;

use Adaptation\Log\Adapter\MonologAdapter;
use Adaptation\Log\Enum\LogChannelEnum;
use Adaptation\Log\Exception\LoggerException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class Logger implements LoggerInterface
{
    public const ROTATING_MAX_FILES = 7;
    public const DATE_FORMAT = \DateTimeInterface::RFC3339;

    private function __construct(private LoggerInterface $logger)
    {
    }

    public static function create(LogChannelEnum $channel): LoggerInterface
    {
        try {
            return new self(MonologAdapter::create($channel));
        } catch (LoggerException $e) {
            error_log(sprintf('Create logger failed: %s', $e->getMessage()));

            return new self(new NullLogger());
        }
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

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        try {
            $this->logger->log($level, $message, $context);
        } catch (\Throwable $e) {
            error_log($e->getMessage());
        }
    }
}
