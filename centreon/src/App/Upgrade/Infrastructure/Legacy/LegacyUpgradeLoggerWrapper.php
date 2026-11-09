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

namespace App\Upgrade\Infrastructure\Legacy;

use Adaptation\Log\LoggerUpgrade;
use App\Upgrade\Application\UpgradeLogger;

final readonly class LegacyUpgradeLoggerWrapper implements UpgradeLogger
{
    public function start(string $fromVersion, string $toVersion): void
    {
        LoggerUpgrade::create()->start($fromVersion, $toVersion);
    }

    public function success(string $fromVersion, string $toVersion, int $durationMs): void
    {
        LoggerUpgrade::create()->success($fromVersion, $toVersion, $durationMs);
    }

    public function failure(?string $fromVersion, ?string $toVersion, string $message, ?\Throwable $exception = null): void
    {
        LoggerUpgrade::create()->failure($fromVersion, $toVersion, $message, $exception);
    }

    public function error(string $version, string $message, ?\Throwable $exception = null): void
    {
        LoggerUpgrade::create()->error($version, $message, $exception);
    }

    public function step(string $version, string $stepName, string $message): void
    {
        LoggerUpgrade::create()->step($version, $stepName, $message);
    }

    public function stepCompleted(string $version, string $stepName, int $durationMs, string $message): void
    {
        LoggerUpgrade::create()->stepCompleted($version, $stepName, $durationMs, $message);
    }

    public function stepFailure(string $version, string $stepName, string $message, ?\Throwable $exception = null): void
    {
        LoggerUpgrade::create()->stepFailure($version, $stepName, $message, $exception);
    }
}
