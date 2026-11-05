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

use App\Upgrade\Application\UpgradeLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Writes the context keys of the legacy LoggerUpgrade, so that both read as one in the upgrade log.
 */
final readonly class UpgradeChannelLogger implements UpgradeLogger
{
    public function __construct(
        private LoggerInterface $upgradeLogger,
    ) {
    }

    public function start(string $fromVersion, string $toVersion): void
    {
        $this->upgradeLogger->log(LogLevel::INFO, sprintf('Upgrade started from %s to %s', $fromVersion, $toVersion), [
            'event' => 'upgrade.start',
            'status' => 'started',
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
        ]);
    }

    public function success(string $fromVersion, string $toVersion, int $durationMs): void
    {
        $this->upgradeLogger->log(LogLevel::INFO, sprintf('Upgrade from %s to %s completed successfully', $fromVersion, $toVersion), [
            'event' => 'upgrade.success',
            'status' => 'success',
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'duration_ms' => $durationMs,
        ]);
    }

    public function failure(?string $fromVersion, ?string $toVersion, string $message, ?\Throwable $exception = null): void
    {
        $this->upgradeLogger->log(LogLevel::ERROR, $message, [
            'event' => 'upgrade.failure',
            'status' => 'failure',
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            ...$this->exception($exception),
        ]);
    }

    public function info(string $version, string $message): void
    {
        $this->upgradeLogger->log(LogLevel::INFO, $message, ['event' => 'upgrade.info', 'status' => 'info', 'version' => $version]);
    }

    public function error(string $version, string $message, ?\Throwable $exception = null): void
    {
        $this->upgradeLogger->log(LogLevel::ERROR, $message, [
            'event' => 'upgrade.error',
            'status' => 'error',
            'version' => $version,
            ...$this->exception($exception),
        ]);
    }

    public function step(string $version, string $stepName, string $message): void
    {
        $this->upgradeLogger->log(LogLevel::INFO, $message, [
            'event' => 'upgrade.step',
            'status' => 'running',
            'version' => $version,
            'step' => $stepName,
        ]);
    }

    public function stepCompleted(string $version, string $stepName, int $durationMs, string $message): void
    {
        $this->upgradeLogger->log(LogLevel::INFO, $message, [
            'event' => 'upgrade.step_completed',
            'status' => 'completed',
            'version' => $version,
            'step' => $stepName,
            'duration_ms' => $durationMs,
        ]);
    }

    public function stepFailure(string $version, string $stepName, string $message, ?\Throwable $exception = null): void
    {
        $this->upgradeLogger->log(LogLevel::ERROR, $message, [
            'event' => 'upgrade.step_failure',
            'status' => 'failure',
            'version' => $version,
            'step' => $stepName,
            ...$this->exception($exception),
        ]);
    }

    /**
     * @return array{exception?: \Throwable}
     */
    private function exception(?\Throwable $exception): array
    {
        return $exception instanceof \Throwable ? ['exception' => $exception] : [];
    }
}
