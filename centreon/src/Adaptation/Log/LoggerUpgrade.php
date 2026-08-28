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

namespace Adaptation\Log;

use Adaptation\Log\Enum\LogChannelEnum;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final class LoggerUpgrade
{
    private static ?self $instance = null;

    private function __construct(private readonly LoggerInterface $logger)
    {
    }

    public static function create(): self
    {
        if (! self::$instance instanceof self) {
            self::$instance = new self(Logger::create(LogChannelEnum::UPGRADE));
        }

        return self::$instance;
    }

    public function start(string $fromVersion, string $toVersion): void
    {
        $this->write(
            LogLevel::INFO,
            "Upgrade started from {$fromVersion} to {$toVersion}",
            $this->lifecycleContext('upgrade.start', 'started', $fromVersion, $toVersion)
        );
    }

    public function success(string $fromVersion, string $toVersion, int $durationMs): void
    {
        $context = $this->lifecycleContext('upgrade.success', 'success', $fromVersion, $toVersion);
        $context['duration_ms'] = $durationMs;
        $this->write(LogLevel::INFO, "Upgrade from {$fromVersion} to {$toVersion} completed successfully", $context);
    }

    public function failure(
        ?string $fromVersion,
        ?string $toVersion,
        string $message,
        ?\Throwable $exception = null,
    ): void {
        $this->write(
            LogLevel::ERROR,
            $message,
            $this->lifecycleContext('upgrade.failure', 'failure', $fromVersion, $toVersion, $exception)
        );
    }

    public function info(string $version, string $message): void
    {
        $this->write(LogLevel::INFO, $message, $this->versionContext('upgrade.info', 'info', $version));
    }

    public function warning(string $version, string $message): void
    {
        $this->write(LogLevel::WARNING, $message, $this->versionContext('upgrade.warning', 'warning', $version));
    }

    public function error(string $version, string $message, ?\Throwable $exception = null): void
    {
        $this->write(LogLevel::ERROR, $message, $this->versionContext('upgrade.error', 'error', $version, $exception));
    }

    public function step(string $version, string $stepName, string $message): void
    {
        $context = $this->versionContext('upgrade.step', 'running', $version);
        $context['step'] = $stepName;
        $this->write(LogLevel::INFO, $message, $context);
    }

    public function stepCompleted(string $version, string $stepName, int $durationMs, string $message): void
    {
        $context = $this->versionContext('upgrade.step_completed', 'completed', $version);
        $context['step'] = $stepName;
        $context['duration_ms'] = $durationMs;
        $this->write(LogLevel::INFO, $message, $context);
    }

    public function stepFailure(
        string $version,
        string $stepName,
        string $message,
        ?\Throwable $exception = null,
    ): void {
        $context = $this->versionContext('upgrade.step_failure', 'failure', $version, $exception);
        $context['step'] = $stepName;
        $this->write(LogLevel::ERROR, $message, $context);
    }

    /**
     * Writes the record through the underlying logger, swallowing any failure.
     *
     * Logging must never break the upgrade: a failure while emitting a log line
     * (success or failure path alike) must not bubble up and abort — or worse,
     * make a successful upgrade look failed.
     *
     * @param array<string,mixed> $context
     */
    private function write(string $level, string $message, array $context): void
    {
        try {
            $this->logger->log($level, $message, $context);
        } catch (\Throwable $exception) {
            // The primary sink is unavailable; keep the upgrade event in error_log rather than
            // losing it — including the original throwable, which is often the line that matters.
            $original = $context['exception'] ?? null;
            error_log(sprintf(
                'LoggerUpgrade: failed to write the upgrade log [%s] "%s"%s: %s',
                $level,
                $message,
                $original instanceof \Throwable ? sprintf(' (%s: %s)', $original::class, $original->getMessage()) : '',
                $exception->getMessage(),
            ));
        }
    }

    /**
     * Context for the global upgrade lifecycle (start / success / failure), which spans two versions.
     *
     * @return array<string,mixed>
     */
    private function lifecycleContext(
        string $event,
        string $status,
        ?string $fromVersion,
        ?string $toVersion,
        ?\Throwable $exception = null,
    ): array {
        $context = [
            'event' => $event,
            'status' => $status,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
        ];
        if ($exception instanceof \Throwable) {
            $context['exception'] = $exception;
        }

        return $context;
    }

    /**
     * Context for per-version events (step / info / error), which carry a single `version`
     * rather than a from/to pair.
     *
     * @return array<string,mixed>
     */
    private function versionContext(
        string $event,
        string $status,
        string $version,
        ?\Throwable $exception = null,
    ): array {
        $context = [
            'event' => $event,
            'status' => $status,
            'version' => $version,
        ];
        if ($exception instanceof \Throwable) {
            $context['exception'] = $exception;
        }

        return $context;
    }
}
