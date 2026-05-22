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

namespace App\Upgrade\Infrastructure\EventListener;

use Adaptation\Log\LoggerUpgrade;
use App\Upgrade\Domain\Event\UpgradeCompleted;
use App\Upgrade\Domain\Event\UpgradeFailed;
use App\Upgrade\Domain\Event\UpgradeStarted;
use App\Upgrade\Domain\Event\UpgradeStepCompleted;
use App\Upgrade\Domain\Event\UpgradeStepFailed;
use App\Upgrade\Domain\Event\UpgradeStepStarted;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class LogUpgradeListener
{
    #[AsEventListener]
    public function onStarted(UpgradeStarted $event): void
    {
        LoggerUpgrade::create()->start($event->fromVersion, $event->toVersion);
    }

    #[AsEventListener]
    public function onCompleted(UpgradeCompleted $event): void
    {
        LoggerUpgrade::create()->success($event->fromVersion, $event->toVersion, $event->durationMs);
    }

    #[AsEventListener]
    public function onFailed(UpgradeFailed $event): void
    {
        LoggerUpgrade::create()->failure(
            $event->message,
            $event->fromVersion,
            $event->toVersion,
            $event->exception,
        );
    }

    #[AsEventListener]
    public function onStepStarted(UpgradeStepStarted $event): void
    {
        LoggerUpgrade::create()->step(
            $event->version,
            $event->step,
            "Starting step '{$event->step}'",
        );
    }

    #[AsEventListener]
    public function onStepCompleted(UpgradeStepCompleted $event): void
    {
        LoggerUpgrade::create()->step(
            $event->version,
            $event->step,
            "Step '{$event->step}' completed in {$event->durationMs}ms",
        );
    }

    #[AsEventListener]
    public function onStepFailed(UpgradeStepFailed $event): void
    {
        LoggerUpgrade::create()->stepFailure(
            $event->message,
            $event->version,
            $event->step,
            $event->exception,
        );
    }
}
