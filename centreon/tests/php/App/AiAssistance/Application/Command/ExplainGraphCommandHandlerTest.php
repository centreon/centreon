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

use App\AiAssistance\Application\Command\ExplainGraphCommand;
use App\AiAssistance\Application\Command\ExplainGraphCommandHandler;
use App\AiAssistance\Application\Command\GraphMetric;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\LlmModelTierEnum;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use App\AiConfiguration\Domain\Llm\LlmUsage;
use PHPUnit\Framework\TestCase;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClient;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClientFactory;

final class ExplainGraphCommandHandlerTest extends TestCase
{
    public function testReturnsTheExplanationAndModel(): void
    {
        $client = new FakeLlmClient(
            response: new LlmResponse('## Reading\n...', 'claude-sonnet-4-6', new LlmUsage(120, 60))
        );
        $handler = new ExplainGraphCommandHandler(new FakeLlmClientFactory($client));

        $result = $handler($this->command());

        self::assertSame('## Reading\n...', $result->explanation);
        self::assertSame('claude-sonnet-4-6', $result->model);
    }

    public function testFactsAreForwardedToTheModel(): void
    {
        $client = new FakeLlmClient();
        $handler = new ExplainGraphCommandHandler(new FakeLlmClientFactory($client));

        $handler($this->command());

        $userMessage = $client->lastRequest?->messages[0]->content ?? '';
        self::assertStringContainsString('Name: Ping', $userMessage);
        self::assertStringContainsString('Host: db-server-01', $userMessage);
        self::assertStringContainsString('Round-Trip-Time Average (ms)', $userMessage);
        // metric summary figures are forwarded
        self::assertStringContainsString('current 5 ms', $userMessage);
        self::assertStringContainsString('max 12 ms', $userMessage);
        // the displayed window and thresholds are forwarded
        self::assertStringContainsString('last 24 hours', $userMessage);
        self::assertStringContainsString('rta=5ms;200;500', $userMessage);
        self::assertSame(LlmModelTierEnum::Default, $client->lastRequest?->tier);
    }

    public function testProviderFailurePropagates(): void
    {
        $client = new FakeLlmClient(
            exception: LlmRequestFailedException::fromProviderError(401, 'invalid token')
        );
        $handler = new ExplainGraphCommandHandler(new FakeLlmClientFactory($client));

        $this->expectException(LlmRequestFailedException::class);

        $handler($this->command());
    }

    private function command(): ExplainGraphCommand
    {
        return new ExplainGraphCommand(
            name: 'Ping',
            resourceType: 'service',
            metrics: [
                new GraphMetric(
                    name: 'Round-Trip-Time Average (ms)',
                    unit: 'ms',
                    minimum: 2.0,
                    maximum: 12.0,
                    average: 6.5,
                    current: 5.0,
                ),
            ],
            parentName: 'db-server-01',
            timeRange: 'the last 24 hours',
            performanceData: 'rta=5ms;200;500;0; pl=0%;20;60;0;100',
        );
    }
}
