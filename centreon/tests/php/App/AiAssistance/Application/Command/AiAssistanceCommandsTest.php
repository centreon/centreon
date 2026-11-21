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

namespace Tests\App\AiAssistance\Application\Command;

use App\AiAssistance\Application\Command\CorrelateAlertsCommand;
use App\AiAssistance\Application\Command\CorrelatedResource;
use App\AiAssistance\Application\Command\ExplainGraphCommand;
use App\AiAssistance\Application\Command\ExplainResourceCommand;
use App\AiAssistance\Application\Command\IncidentEvent;
use App\AiAssistance\Application\Command\SummarizeIncidentCommand;
use App\AiAssistance\Application\Command\TranslateResourceFilterCommand;
use App\Shared\Application\Command\NonTransactionalCommand;
use App\Shared\Domain\Logging\Attribute\Sensitive;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AiAssistanceCommandsTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function provideCommands(): iterable
    {
        yield 'correlate alerts' => [CorrelateAlertsCommand::class];

        yield 'explain graph' => [ExplainGraphCommand::class];

        yield 'explain resource' => [ExplainResourceCommand::class];

        yield 'summarize incident' => [SummarizeIncidentCommand::class];

        yield 'translate resource filter' => [TranslateResourceFilterCommand::class];
    }

    /**
     * @param class-string $commandClass
     */
    #[DataProvider('provideCommands')]
    public function testTheCommandIsNotTransactional(string $commandClass): void
    {
        // the usage row of a call must survive a later failure, and no
        // transaction may stay open during the provider call
        self::assertTrue(new \ReflectionClass($commandClass)->implementsInterface(NonTransactionalCommand::class));
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function provideFreeTexts(): iterable
    {
        yield 'Ask AI request' => [TranslateResourceFilterCommand::class, 'prompt'];

        yield 'Ask AI current filter' => [TranslateResourceFilterCommand::class, 'currentFilter'];

        yield 'resource output' => [ExplainResourceCommand::class, 'information'];

        yield 'resource command line' => [ExplainResourceCommand::class, 'commandLine'];

        yield 'resource performance data' => [ExplainResourceCommand::class, 'performanceData'];

        yield 'incident output' => [SummarizeIncidentCommand::class, 'information'];

        yield 'incident event content' => [IncidentEvent::class, 'content'];

        yield 'correlated resource output' => [CorrelatedResource::class, 'information'];

        yield 'graph performance data' => [ExplainGraphCommand::class, 'performanceData'];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('provideFreeTexts')]
    public function testTheFreeTextsAreMaskedInLogs(string $class, string $property): void
    {
        // the bus logs every command: the prompt material must not be dumped
        self::assertNotSame([], new \ReflectionProperty($class, $property)->getAttributes(Sensitive::class));
    }
}
