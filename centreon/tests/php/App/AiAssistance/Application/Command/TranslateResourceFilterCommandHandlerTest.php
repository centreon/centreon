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
    public function testValidAnswerIsReturned(): void
    {
        $client = $this->clientAnswering('{"search": "type:service status:critical"}');
        $handler = new TranslateResourceFilterCommandHandler(new FakeLlmClientFactory($client));

        $result = $handler(new TranslateResourceFilterCommand('critical services'));

        self::assertSame('type:service status:critical', $result->search);
        self::assertCount(1, $client->requests);
    }

    public function testUsesTheFastTier(): void
    {
        $client = $this->clientAnswering('{"search": ""}');
        $handler = new TranslateResourceFilterCommandHandler(new FakeLlmClientFactory($client));

        $handler(new TranslateResourceFilterCommand('hello'));

        self::assertSame(LlmModelTierEnum::Fast, $client->requests[0]->tier);
    }

    public function testCodeFencesAreTolerated(): void
    {
        $client = $this->clientAnswering("```json\n{\"search\": \"status:critical\"}\n```");
        $handler = new TranslateResourceFilterCommandHandler(new FakeLlmClientFactory($client));

        $result = $handler(new TranslateResourceFilterCommand('critical resources'));

        self::assertSame('status:critical', $result->search);
    }

    public function testInvalidAnswerTriggersOneCorrection(): void
    {
        $client = new FakeLlmClient(queue: [
            $this->response('{"search": "hostgroup:Linux"}'), // unknown key
            $this->response('{"search": "host_group:Linux"}'),
        ]);
        $handler = new TranslateResourceFilterCommandHandler(new FakeLlmClientFactory($client));

        $result = $handler(new TranslateResourceFilterCommand('resources of the Linux group'));

        self::assertSame('host_group:Linux', $result->search);
        self::assertCount(2, $client->requests);
        // the correction request carries the previous conversation and the error
        $correctionMessages = $client->requests[1]->messages;
        self::assertCount(3, $correctionMessages);
        self::assertStringContainsString("unknown key 'hostgroup'", end($correctionMessages)->content);
    }

    public function testStillInvalidAfterCorrectionReturnsEmptySearch(): void
    {
        $client = new FakeLlmClient(queue: [
            $this->response('not json at all'),
            $this->response('still not json'),
        ]);
        $handler = new TranslateResourceFilterCommandHandler(new FakeLlmClientFactory($client));

        $result = $handler(new TranslateResourceFilterCommand('something'));

        self::assertSame('', $result->search);
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
