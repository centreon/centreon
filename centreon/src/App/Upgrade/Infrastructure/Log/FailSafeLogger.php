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

namespace App\Upgrade\Infrastructure\Log;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The upgrade channel, falling back to the PHP error log when it cannot write: a log line must not fail an upgrade.
 */
final class FailSafeLogger extends AbstractLogger
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.upgrade')]
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        try {
            $this->logger->log($level, $message, $context);
        } catch (\Throwable $exception) {
            $original = $context['exception'] ?? null;
            $keys = array_filter(
                array_intersect_key($context, array_flip(['step', 'version', 'from_version', 'to_version', 'migration_version'])),
                is_scalar(...),
            );
            error_log(sprintf(
                'Upgrade log unavailable (%s): [%s] %s%s%s',
                $exception->getMessage(),
                is_string($level) ? $level : get_debug_type($level),
                $message,
                $keys === [] ? '' : ' ' . json_encode($keys),
                // With its trace and previous exceptions.
                $original instanceof \Throwable ? "\n" . $original : '',
            ));
        }
    }
}
