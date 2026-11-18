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

namespace Tests\App\Upgrade\Infrastructure\Double;

use App\Upgrade\Application\UpgradeLogger;

final class FakeUpgradeLogger implements UpgradeLogger
{
    /**
     * @var list<array{
     *     method: 'start'|'success'|'failure'|'error'|'step'|'stepCompleted'|'stepFailure',
     *     fromVersion?: ?string,
     *     toVersion?: ?string,
     *     durationMs?: int,
     *     message?: string,
     *     exception?: ?\Throwable,
     *     version?: string,
     *     stepName?: string,
     * }>
     */
    public array $calls = [];

    public function start(string $fromVersion, string $toVersion): void
    {
        $this->calls[] = ['method' => 'start', 'fromVersion' => $fromVersion, 'toVersion' => $toVersion];
    }

    public function success(string $fromVersion, string $toVersion, int $durationMs): void
    {
        $this->calls[] = [
            'method' => 'success',
            'fromVersion' => $fromVersion,
            'toVersion' => $toVersion,
            'durationMs' => $durationMs,
        ];
    }

    public function failure(?string $fromVersion, ?string $toVersion, string $message, ?\Throwable $exception = null): void
    {
        $this->calls[] = [
            'method' => 'failure',
            'fromVersion' => $fromVersion,
            'toVersion' => $toVersion,
            'message' => $message,
            'exception' => $exception,
        ];
    }

    public function error(string $version, string $message, ?\Throwable $exception = null): void
    {
        $this->calls[] = ['method' => 'error', 'version' => $version, 'message' => $message, 'exception' => $exception];
    }

    public function step(string $version, string $stepName, string $message): void
    {
        $this->calls[] = ['method' => 'step', 'version' => $version, 'stepName' => $stepName, 'message' => $message];
    }

    public function stepCompleted(string $version, string $stepName, int $durationMs, string $message): void
    {
        $this->calls[] = [
            'method' => 'stepCompleted',
            'version' => $version,
            'stepName' => $stepName,
            'durationMs' => $durationMs,
            'message' => $message,
        ];
    }

    public function stepFailure(string $version, string $stepName, string $message, ?\Throwable $exception = null): void
    {
        $this->calls[] = [
            'method' => 'stepFailure',
            'version' => $version,
            'stepName' => $stepName,
            'message' => $message,
            'exception' => $exception,
        ];
    }
}
