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

namespace Tests\App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Application\Command\LinkGlobalMacrosToPollerCommand;
use App\MonitoringConfiguration\Application\Command\LinkGlobalMacrosToPollerCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeGlobalMacroRepository;

final class LinkGlobalMacrosToPollerCommandHandlerTest extends TestCase
{
    public function testItLinksAllGlobalMacrosToPoller(): void
    {
        $repository = new FakeGlobalMacroRepository();
        $handler = new LinkGlobalMacrosToPollerCommandHandler($repository);

        $handler(new LinkGlobalMacrosToPollerCommand(
            pollerId: new PollerId(42),
        ));

        self::assertCount(1, $repository->linkedPollerIds);
        self::assertSame(42, $repository->linkedPollerIds[0]->value);
    }
}
