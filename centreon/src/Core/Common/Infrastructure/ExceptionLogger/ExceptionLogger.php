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

namespace Core\Common\Infrastructure\ExceptionLogger;

use Centreon\Domain\Log\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class
 *
 * @class ExceptionLogger
 * @package Core\Common\Infrastructure\ExceptionLogger
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
     * Factory
     *
     * @return ExceptionLogger
     */
    public static function create(): self
    {
        return new self(new Logger());
    }

    /**
     * @param \Throwable $throwable
     * @param array<string,mixed> $context
     * @param string $level {@see LogLevel}
     *
     * @return void
     */
    public function log(\Throwable $throwable, array $context = [], string $level = LogLevel::ERROR): void
    {
        $context = ExceptionLogFormatter::format($context, $throwable);
        $this->logger->log($level, $throwable->getMessage(), $context);
    }
}
