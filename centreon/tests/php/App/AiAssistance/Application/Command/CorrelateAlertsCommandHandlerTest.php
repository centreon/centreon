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
use App\AiAssistance\Application\Command\CorrelateAlertsCommandHandler;
use App\AiAssistance\Application\Command\CorrelatedResource;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use App\AiConfiguration\Domain\Llm\LlmUsage;
use PHPUnit\Framework\TestCase;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClient;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClientFactory;

final class CorrelateAlertsCommandHandlerTest extends TestCase
{
    public function testReturnsTheAnalysisAndModel(): void
    {
        $client = new FakeLlmClient(
            response: new LlmResponse('## Likely root cause...', 'claude-sonnet-4-6', new LlmUsage(300, 150))
        );
        $handler = new CorrelateAlertsCommandHandler(new FakeLlmClientFactory($client));

        $result = $handler($this->command());

        self::assertSame('## Likely root cause...', $result->analysis);
        self::assertSame('claude-sonnet-4-6', $result->model);
    }

    public function testResourcesAreForwardedToTheModel(): void
    {
        $client = new FakeLlmClient();
        $handler = new CorrelateAlertsCommandHandler(new FakeLlmClientFactory($client));

        $handler($this->command());

        $userMessage = $client->lastRequest?->messages[0]->content ?? '';
        self::assertStringContainsString('2 resources are in a non-OK state', $userMessage);
        // a service is labelled host / service, with its status and check output
        self::assertStringContainsString('db-server-01 / Ping (service) — status CRITICAL', $userMessage);
        self::assertStringContainsString('output: CRITICAL - Packet loss = 100%', $userMessage);
        self::assertStringContainsString('db-server-01 (host) — status DOWN', $userMessage);
    }

    public function testProviderFailurePropagates(): void
    {
        $client = new FakeLlmClient(
            exception: LlmRequestFailedException::fromProviderError(401, 'invalid token')
        );
        $handler = new CorrelateAlertsCommandHandler(new FakeLlmClientFactory($client));

        $this->expectException(LlmRequestFailedException::class);

        $handler($this->command());
    }

    private function command(): CorrelateAlertsCommand
    {
        return new CorrelateAlertsCommand(resources: [
            new CorrelatedResource(
                name: 'Ping',
                resourceType: 'service',
                status: 'CRITICAL',
                parentName: 'db-server-01',
                information: 'CRITICAL - Packet loss = 100%',
                duration: '2h 31m',
                tries: '3/3 (Hard)',
            ),
            new CorrelatedResource(
                name: 'db-server-01',
                resourceType: 'host',
                status: 'DOWN',
                information: 'Host unreachable',
            ),
        ]);
    }
}
