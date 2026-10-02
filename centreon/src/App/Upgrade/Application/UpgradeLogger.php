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

namespace App\Upgrade\Application;

interface UpgradeLogger
{
    public function start(string $fromVersion, string $toVersion): void;

    public function success(string $fromVersion, string $toVersion, int $durationMs): void;

    public function failure(?string $fromVersion, ?string $toVersion, string $message, ?\Throwable $exception = null): void;

    public function error(string $version, string $message, ?\Throwable $exception = null): void;

    public function step(string $version, string $stepName, string $message): void;

    public function stepCompleted(string $version, string $stepName, int $durationMs, string $message): void;

    public function stepFailure(string $version, string $stepName, string $message, ?\Throwable $exception = null): void;
}
