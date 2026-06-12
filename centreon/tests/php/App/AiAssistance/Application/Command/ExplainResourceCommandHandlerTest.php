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

use App\AiAssistance\Application\Command\ExplainResourceCommand;
use App\AiAssistance\Application\Command\ExplainResourceCommandHandler;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\LlmModelTierEnum;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use App\AiConfiguration\Domain\Llm\LlmUsage;
use PHPUnit\Framework\TestCase;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClient;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClientFactory;

final class ExplainResourceCommandHandlerTest extends TestCase
{
    public function testReturnsTheExplanationAndModel(): void
    {
        $client = new FakeLlmClient(
            response: new LlmResponse('**What this means**: ...', 'claude-sonnet-4-6', new LlmUsage(100, 50))
        );
        $handler = new ExplainResourceCommandHandler(new FakeLlmClientFactory($client));

        $result = $handler($this->command());

        self::assertSame('**What this means**: ...', $result->explanation);
        self::assertSame('claude-sonnet-4-6', $result->model);
    }

    public function testFactsAreForwardedToTheModel(): void
    {
        $client = new FakeLlmClient();
        $handler = new ExplainResourceCommandHandler(new FakeLlmClientFactory($client));

        $handler($this->command());

        $userMessage = $client->lastRequest?->messages[0]->content ?? '';
        self::assertStringContainsString('Name: Ping', $userMessage);
        self::assertStringContainsString('Current status: CRITICAL', $userMessage);
        self::assertStringContainsString('check_icmp', $userMessage);
        self::assertStringContainsString('Packet loss', $userMessage);
        self::assertStringContainsString('Host: db-server-01', $userMessage);
        // the explanation uses the default tier
        self::assertSame(LlmModelTierEnum::Default, $client->lastRequest?->tier);
    }

    public function testProviderFailurePropagates(): void
    {
        $client = new FakeLlmClient(
            exception: LlmRequestFailedException::fromProviderError(401, 'invalid token')
        );
        $handler = new ExplainResourceCommandHandler(new FakeLlmClientFactory($client));

        $this->expectException(LlmRequestFailedException::class);

        $handler($this->command());
    }

    private function command(): ExplainResourceCommand
    {
        return new ExplainResourceCommand(
            name: 'Ping',
            resourceType: 'service',
            status: 'CRITICAL',
            information: 'CRITICAL - Packet loss = 100%',
            commandLine: '/usr/lib/centreon/plugins/check_icmp -H 10.0.0.1 -w 200,20% -c 500,60%',
            performanceData: 'rta=0.000ms;200;500;0; pl=100%;20;60;0;100',
            duration: '2h 31m',
            tries: '3/3 (Hard)',
            parentName: 'db-server-01',
        );
    }
}
