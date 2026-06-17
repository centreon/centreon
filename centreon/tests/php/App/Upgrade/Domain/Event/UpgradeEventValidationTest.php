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

namespace Tests\App\Upgrade\Domain\Event;

use App\Upgrade\Domain\Event\UpgradeCompleted;
use App\Upgrade\Domain\Event\UpgradeFailed;
use App\Upgrade\Domain\Event\UpgradeStarted;
use App\Upgrade\Domain\Event\UpgradeStepCompleted;
use App\Upgrade\Domain\Event\UpgradeStepFailed;
use App\Upgrade\Domain\Event\UpgradeStepStarted;
use PHPUnit\Framework\TestCase;

final class UpgradeEventValidationTest extends TestCase
{
    public function testUpgradeStartedRejectsEmptyVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UpgradeStarted('', '24.10.1');
    }

    public function testUpgradeCompletedRejectsNegativeDuration(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UpgradeCompleted('24.10.0', '24.10.1', -1);
    }

    public function testUpgradeStepStartedRejectsEmptyStep(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UpgradeStepStarted('24.10.1', '   ');
    }

    public function testUpgradeStepCompletedRejectsNegativeDuration(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UpgradeStepCompleted('24.10.1', 'php_script', -5);
    }

    public function testUpgradeStepFailedRejectsEmptyVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UpgradeStepFailed('boom', '', 'php_script');
    }

    public function testUpgradeStepFailedAcceptsAnyMessageIncludingEmpty(): void
    {
        // The message is exception-derived and must never be validated (it is built inside a catch block).
        $event = new UpgradeStepFailed('', '24.10.1', 'php_script');

        self::assertSame('', $event->message);
    }

    public function testUpgradeFailedAllowsNullVersionsButRejectsEmptyString(): void
    {
        $event = new UpgradeFailed('boom', null, null);
        self::assertNull($event->fromVersion);

        $this->expectException(\InvalidArgumentException::class);
        new UpgradeFailed('boom', '', null);
    }
}
