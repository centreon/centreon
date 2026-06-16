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

namespace Tests\App\AiConfiguration\Infrastructure\Llm;

use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Llm\AiFeatureEnum;
use App\AiConfiguration\Domain\Llm\LlmMessage;
use App\AiConfiguration\Domain\Llm\LlmModelTierEnum;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Llm\LlmRoleEnum;
use App\AiConfiguration\Infrastructure\Llm\BedrockLlmClient;
use PHPUnit\Framework\TestCase;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClient;

final class BedrockLlmClientTest extends TestCase
{
    public function testRoutesAnthropicModelsToTheMessagesClient(): void
    {
        $openAi = new FakeLlmClient();
        $anthropic = new FakeLlmClient();
        $client = new BedrockLlmClient(
            $openAi,
            $anthropic,
            new ModelName('anthropic.claude-opus-4-8'),
            new ModelName('anthropic.claude-haiku-4-5'),
        );

        $client->complete($this->request());

        self::assertNotNull($anthropic->lastRequest);
        self::assertNull($openAi->lastRequest);
    }

    public function testRoutesOtherFamiliesToTheOpenAiCompatibleClient(): void
    {
        $openAi = new FakeLlmClient();
        $anthropic = new FakeLlmClient();
        $client = new BedrockLlmClient(
            $openAi,
            $anthropic,
            new ModelName('qwen.qwen3-32b'),
            null,
        );

        $client->complete($this->request());

        self::assertNotNull($openAi->lastRequest);
        self::assertNull($anthropic->lastRequest);
    }

    public function testRoutesByTheFastModelForTheFastTier(): void
    {
        $openAi = new FakeLlmClient();
        $anthropic = new FakeLlmClient();
        // default model is Anthropic, fast model is not
        $client = new BedrockLlmClient(
            $openAi,
            $anthropic,
            new ModelName('anthropic.claude-opus-4-8'),
            new ModelName('qwen.qwen3-32b'),
        );

        $client->complete($this->request(tier: LlmModelTierEnum::Fast));

        self::assertNotNull($openAi->lastRequest);
        self::assertNull($anthropic->lastRequest);
    }

    public function testAnExplicitRequestModelDecidesTheRoute(): void
    {
        $openAi = new FakeLlmClient();
        $anthropic = new FakeLlmClient();
        $client = new BedrockLlmClient(
            $openAi,
            $anthropic,
            new ModelName('anthropic.claude-opus-4-8'),
            null,
        );

        $client->complete($this->request(model: new ModelName('qwen.qwen3-32b')));

        self::assertNotNull($openAi->lastRequest);
        self::assertNull($anthropic->lastRequest);
    }

    public function testListModelsComesFromTheOpenAiCompatibleCatalog(): void
    {
        $openAi = new FakeLlmClient();
        $openAi->models = ['anthropic.claude-opus-4-8', 'qwen.qwen3-32b'];
        $anthropic = new FakeLlmClient();
        $anthropic->models = ['unused'];
        $client = new BedrockLlmClient(
            $openAi,
            $anthropic,
            new ModelName('anthropic.claude-opus-4-8'),
            null,
        );

        self::assertSame(['anthropic.claude-opus-4-8', 'qwen.qwen3-32b'], $client->listModels());
    }

    private function request(
        ?ModelName $model = null,
        LlmModelTierEnum $tier = LlmModelTierEnum::Default,
    ): LlmRequest {
        return new LlmRequest(
            messages: [new LlmMessage(LlmRoleEnum::User, 'ping')],
            feature: AiFeatureEnum::ConnectionTest,
            model: $model,
            tier: $tier,
        );
    }
}
