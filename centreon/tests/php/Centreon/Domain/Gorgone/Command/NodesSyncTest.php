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

namespace Tests\Centreon\Domain\Gorgone\Command;

use Centreon\Domain\Gorgone\Command\NodesSync;
use Centreon\Domain\Gorgone\Interfaces\CommandInterface;
use LogicException;
use PHPUnit\Framework\TestCase;

class NodesSyncTest extends TestCase
{
    private NodesSync $command;

    protected function setUp(): void
    {
        $this->command = new NodesSync();
    }

    public function testUriPointsAtCentreonNodesSyncEndpoint(): void
    {
        self::assertSame('centreon/nodes/sync', $this->command->getUriRequest());
    }

    public function testNameMatchesTheGorgoneCentreonModuleAction(): void
    {
        self::assertSame('centreon::nodes::sync', $this->command->getName());
    }

    public function testMethodIsPost(): void
    {
        self::assertSame(CommandInterface::METHOD_POST, $this->command->getMethod());
    }

    public function testBodyIsAnEmptyJsonObject(): void
    {
        self::assertSame('{}', $this->command->getBodyRequest());
    }

    public function testGetMonitoringInstanceIdThrowsBecauseTheCommandIsNotPerPoller(): void
    {
        $this->expectException(LogicException::class);
        $this->command->getMonitoringInstanceId();
    }
}
