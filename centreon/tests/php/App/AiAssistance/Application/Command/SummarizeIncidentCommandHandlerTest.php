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

use App\AiAssistance\Application\Command\IncidentEvent;
use App\AiAssistance\Application\Command\SummarizeIncidentCommand;
use App\AiAssistance\Application\Command\SummarizeIncidentCommandHandler;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\LlmModelTierEnum;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use App\AiConfiguration\Domain\Llm\LlmUsage;
use PHPUnit\Framework\TestCase;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClient;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClientFactory;

final class SummarizeIncidentCommandHandlerTest extends TestCase
{
    public function testReturnsTheSummaryAndModel(): void
    {
        $client = new FakeLlmClient(
            response: new LlmResponse('Summary: ...', 'claude-sonnet-4-6', new LlmUsage(200, 100))
        );
        $handler = new SummarizeIncidentCommandHandler(new FakeLlmClientFactory($client));

        $result = $handler($this->command());

        self::assertSame('Summary: ...', $result->summary);
        self::assertSame('claude-sonnet-4-6', $result->model);
    }

    public function testFactsAndEventsAreForwardedToTheModel(): void
    {
        $client = new FakeLlmClient();
        $handler = new SummarizeIncidentCommandHandler(new FakeLlmClientFactory($client));

        $handler($this->command());

        $userMessage = $client->lastRequest?->messages[0]->content ?? '';
        self::assertStringContainsString('Name: Ping', $userMessage);
        self::assertStringContainsString('Current status: CRITICAL', $userMessage);
        self::assertStringContainsString('since 2026-06-11T10:00:00+02:00', $userMessage);
        self::assertStringContainsString(
            '- [2026-06-12T08:00:00+02:00] status_change -> CRITICAL: Packet loss = 100%',
            $userMessage
        );
        self::assertStringContainsString(
            '- [2026-06-12T08:10:00+02:00] acknowledgement (by admin): Looking into it',
            $userMessage
        );
        self::assertSame(LlmModelTierEnum::Default, $client->lastRequest?->tier);
    }

    public function testTruncationIsAnnouncedToTheModel(): void
    {
        $client = new FakeLlmClient();
        $handler = new SummarizeIncidentCommandHandler(new FakeLlmClientFactory($client));

        $handler($this->command(isTruncated: true));

        $userMessage = $client->lastRequest?->messages[0]->content ?? '';
        self::assertStringContainsString('truncated: only the most recent 100 events', $userMessage);
    }

    public function testProviderFailurePropagates(): void
    {
        $client = new FakeLlmClient(
            exception: LlmRequestFailedException::fromProviderError(401, 'invalid token')
        );
        $handler = new SummarizeIncidentCommandHandler(new FakeLlmClientFactory($client));

        $this->expectException(LlmRequestFailedException::class);

        $handler($this->command());
    }

    private function command(bool $isTruncated = false): SummarizeIncidentCommand
    {
        return new SummarizeIncidentCommand(
            name: 'Ping',
            resourceType: 'service',
            status: 'CRITICAL',
            events: [
                new IncidentEvent(
                    date: '2026-06-12T08:00:00+02:00',
                    type: 'status_change',
                    content: 'Packet loss = 100%',
                    status: 'CRITICAL',
                ),
                new IncidentEvent(
                    date: '2026-06-12T08:10:00+02:00',
                    type: 'acknowledgement',
                    content: 'Looking into it',
                    author: 'admin',
                ),
            ],
            information: 'CRITICAL - Packet loss = 100%',
            duration: '2h 31m',
            parentName: 'db-server-01',
            since: '2026-06-11T10:00:00+02:00',
            isTruncated: $isTruncated,
        );
    }
}
