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

namespace Tests\App\Upgrade\Application\Query;

use App\Upgrade\Application\Query\GetUpgradeTargetVersionQuery;
use App\Upgrade\Application\Query\GetUpgradeTargetVersionQueryHandler;
use App\Upgrade\Application\Service\TargetVersionRecorder;
use PHPUnit\Framework\TestCase;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateScriptFinder;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpgradeLogger;

final class GetUpgradeTargetVersionQueryHandlerTest extends TestCase
{
    public function testAnswersTheVersionOfTheInstalledCode(): void
    {
        self::assertSame('26.12.0', $this->handler(new FakeUpdateScriptFinder())(new GetUpgradeTargetVersionQuery('26.10.0')));
    }

    public function testKeepsTheLegacyVersionOnADevelopmentBuild(): void
    {
        $updateScriptFinder = new FakeUpdateScriptFinder();
        $updateScriptFinder->nextUpdatePending = true;

        self::assertSame('26.10.0', $this->handler($updateScriptFinder)(new GetUpgradeTargetVersionQuery('26.10.0')));
    }

    private function handler(FakeUpdateScriptFinder $updateScriptFinder): GetUpgradeTargetVersionQueryHandler
    {
        return new GetUpgradeTargetVersionQueryHandler(
            new TargetVersionRecorder(new FakeUpdateRepository(), $updateScriptFinder, '26.12.0', new FakeUpgradeLogger()),
        );
    }
}
