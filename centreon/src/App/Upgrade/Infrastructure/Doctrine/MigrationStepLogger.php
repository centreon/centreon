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

namespace App\Upgrade\Infrastructure\Doctrine;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Gives the lines Doctrine writes the context keys of the upgrade steps.
 */
final class MigrationStepLogger extends AbstractLogger
{
    private const array FAILURE_LEVELS = [LogLevel::ERROR, LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $platformVersion,
        private readonly string $step,
    ) {
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        // Interpolated here: the "version" placeholder of Doctrine names the migration, not the platform version.
        $replacements = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value instanceof \Stringable) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        if (array_key_exists('version', $context)) {
            $context['migration_version'] = $context['version'];
            unset($context['version']);
        }

        $this->logger->log($level, strtr((string) $message, $replacements), [
            ...$context,
            'event' => 'upgrade.migration',
            'status' => in_array($level, self::FAILURE_LEVELS, true) ? 'failure' : 'running',
            'version' => $this->platformVersion,
            'step' => $this->step,
        ]);
    }
}
