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

use App\AiAssistance\Application\Command\TranslateResourceFilterCommand;
use App\AiAssistance\Application\Command\TranslateResourceFilterCommandHandler;
use App\AiConfiguration\Domain\Llm\Exception\LlmConfigurationException;
use App\AiConfiguration\Domain\Llm\LlmModelTierEnum;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use App\AiConfiguration\Domain\Llm\LlmUsage;
use PHPUnit\Framework\TestCase;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClient;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClientFactory;

final class TranslateResourceFilterCommandHandlerTest extends TestCase
{
    public function testFilterIntentReturnsTheExpression(): void
    {
        $client = $this->clientAnswering(
            '{"intent": "filter", "query": "type:service status:critical", "scope": "query"}'
        );
        $handler = new TranslateResourceFilterCommandHandler(new FakeLlmClientFactory($client));

        $result = $handler(new TranslateResourceFilterCommand('critical services'));

        self::assertSame('filter', $result->intent);
        self::assertSame('type:service status:critical', $result->query);
        self::assertNull($result->scope);
        self::assertCount(1, $client->requests);
    }

    public function testExplainIntentOnTheCurrentSet(): void
    {
        $client = $this->clientAnswering(
            '{"intent": "explain", "query": "", "scope": "current"}'
        );
        $handler = new TranslateResourceFilterCommandHandler(new FakeLlmClientFactory($client));

        $result = $handler(new TranslateResourceFilterCommand('why is everything failing?'));

        self::assertSame('explain', $result->intent);
        self::assertSame('current', $result->scope);
        self::assertSame('', $result->query);
    }

    public function testExplainIntentWithADerivedQuery(): void
    {
        $client = $this->clientAnswering(
            '{"intent": "explain", "query": "type:host name:db", "scope": "query"}'
        );
        $handler = new TranslateResourceFilterCommandHandler(new FakeLlmClientFactory($client));

        $result = $handler(new TranslateResourceFilterCommand('what is wrong with the db hosts?'));

        self::assertSame('explain', $result->intent);
        self::assertSame('query', $result->scope);
        self::assertSame('type:host name:db', $result->query);
    }

    public function testUsesTheFastTier(): void
    {
        $client = $this->clientAnswering('{"intent": "filter", "query": "", "scope": "query"}');
        $handler = new TranslateResourceFilterCommandHandler(new FakeLlmClientFactory($client));

        $handler(new TranslateResourceFilterCommand('hello'));

        self::assertSame(LlmModelTierEnum::Fast, $client->requests[0]->tier);
    }

    public function testTheCurrentFilterIsForwarded(): void
    {
        $client = $this->clientAnswering('{"intent": "explain", "query": "", "scope": "current"}');
        $handler = new TranslateResourceFilterCommandHandler(new FakeLlmClientFactory($client));

        $handler(new TranslateResourceFilterCommand('why these alerts?', 'type:service status:critical'));

        self::assertStringContainsString(
            'Current filter: type:service status:critical',
            $client->requests[0]->messages[0]->content
        );
    }

    public function testCodeFencesAreTolerated(): void
    {
        $client = $this->clientAnswering(
            "```json\n{\"intent\": \"filter\", \"query\": \"status:critical\", \"scope\": \"query\"}\n```"
        );
        $handler = new TranslateResourceFilterCommandHandler(new FakeLlmClientFactory($client));

        $result = $handler(new TranslateResourceFilterCommand('critical resources'));

        self::assertSame('status:critical', $result->query);
    }

    public function testInvalidAnswerTriggersOneCorrection(): void
    {
        $client = new FakeLlmClient(queue: [
            $this->response('{"intent": "filter", "query": "hostgroup:Linux", "scope": "query"}'), // unknown key
            $this->response('{"intent": "filter", "query": "host_group:Linux", "scope": "query"}'),
        ]);
        $handler = new TranslateResourceFilterCommandHandler(new FakeLlmClientFactory($client));

        $result = $handler(new TranslateResourceFilterCommand('resources of the Linux group'));

        self::assertSame('host_group:Linux', $result->query);
        self::assertCount(2, $client->requests);
        // the correction request carries the previous conversation and the error
        $correctionMessages = $client->requests[1]->messages;
        self::assertCount(3, $correctionMessages);
        self::assertStringContainsString("unknown key 'hostgroup'", end($correctionMessages)->content);
    }

    public function testStillInvalidAfterCorrectionReturnsAnEmptyFilter(): void
    {
        $client = new FakeLlmClient(queue: [
            $this->response('not json at all'),
            $this->response('still not json'),
        ]);
        $handler = new TranslateResourceFilterCommandHandler(new FakeLlmClientFactory($client));

        $result = $handler(new TranslateResourceFilterCommand('something'));

        self::assertSame('filter', $result->intent);
        self::assertSame('', $result->query);
        self::assertCount(2, $client->requests);
    }

    public function testMissingProviderConfigurationPropagates(): void
    {
        $handler = new TranslateResourceFilterCommandHandler(
            new FakeLlmClientFactory(exception: LlmConfigurationException::noActivatedProvider())
        );

        $this->expectException(LlmConfigurationException::class);

        $handler(new TranslateResourceFilterCommand('anything'));
    }

    private function clientAnswering(string $content): FakeLlmClient
    {
        return new FakeLlmClient(response: $this->response($content));
    }

    private function response(string $content): LlmResponse
    {
        return new LlmResponse($content, 'fake-model', new LlmUsage(10, 5));
    }
}
