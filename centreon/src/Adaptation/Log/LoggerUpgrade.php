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
            $this->baseContext('upgrade.start', 'started', $fromVersion, $toVersion)
        );
    }

    public function success(string $fromVersion, string $toVersion, int $durationMs): void
    {
        $context = $this->baseContext('upgrade.success', 'success', $fromVersion, $toVersion);
        $context['duration_ms'] = $durationMs;
        $this->write(LogLevel::INFO, "Upgrade from {$fromVersion} to {$toVersion} completed successfully", $context);
    }

    public function failure(
        string $message,
        ?string $fromVersion,
        ?string $toVersion,
        ?\Throwable $exception = null,
    ): void {
        $this->write(
            LogLevel::ERROR,
            $message,
            $this->baseContext('upgrade.failure', 'failure', $fromVersion, $toVersion, $exception)
        );
    }

    public function info(string $version, string $message): void
    {
        $this->write(
            LogLevel::INFO,
            $message,
            $this->baseContext('upgrade.info', 'info', null, $version)
        );
    }

    public function error(string $version, string $message, ?\Throwable $exception = null): void
    {
        $this->write(
            LogLevel::ERROR,
            $message,
            $this->baseContext('upgrade.error', 'error', null, $version, $exception)
        );
    }

    public function step(string $version, string $stepName, string $message): void
    {
        $context = $this->baseContext('upgrade.step', 'running', null, $version);
        $context['step'] = $stepName;
        $this->write(LogLevel::INFO, $message, $context);
    }

    public function stepFailure(
        string $message,
        string $version,
        string $stepName,
        ?\Throwable $exception = null,
    ): void {
        $context = $this->baseContext('upgrade.step_failure', 'failure', null, $version, $exception);
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
            error_log(sprintf('LoggerUpgrade: failed to write the upgrade log: %s', $exception->getMessage()));
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function baseContext(
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
}
