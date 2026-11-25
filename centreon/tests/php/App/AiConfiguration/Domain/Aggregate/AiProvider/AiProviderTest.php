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

namespace Tests\App\AiConfiguration\Domain\Aggregate\AiProvider;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ReasoningModeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Domain\Exception\AiProviderCanNotBeUpdatedException;
use App\AiConfiguration\Domain\Exception\InvalidAiProviderConfigurationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AiProviderTest extends TestCase
{
    public function testRetargetingKeepsTheTokenForTheSameEndpoint(): void
    {
        $aiProvider = $this->aiProvider();

        // a trailing slash does not change the target
        $aiProvider->retarget(new EndpointUrl('http://ollama.internal:11434/'), null);

        self::assertSame('stored-token', $aiProvider->apiToken()?->value);
        self::assertSame('http://ollama.internal:11434/', $aiProvider->endpointUrl()->value);
    }

    public function testRetargetingToAnotherEndpointRequiresANewTokenForATypeThatNeedsOne(): void
    {
        $aiProvider = $this->aiProvider(AiProviderTypeEnum::Bedrock, 'https://bedrock-mantle.us-east-1.api.aws');

        $this->expectException(AiProviderCanNotBeUpdatedException::class);

        $aiProvider->retarget(new EndpointUrl('https://bedrock-mantle.eu-west-1.api.aws'), null);
    }

    public function testRetargetingAServerThatMayNeedNoTokenDropsTheStoredOne(): void
    {
        $aiProvider = $this->aiProvider();

        $aiProvider->retarget(new EndpointUrl('https://attacker.example.org'), null);

        // never sent to the new server
        self::assertNull($aiProvider->apiToken());
        self::assertSame('https://attacker.example.org', $aiProvider->endpointUrl()->value);
    }

    public function testAnOpenAiCompatibleServerMayBeCreatedWithoutAToken(): void
    {
        $aiProvider = AiProvider::create(
            name: new AiProviderName('Local Ollama'),
            type: AiProviderTypeEnum::OpenAiCompatible,
            endpointUrl: new EndpointUrl('http://ollama.internal:11434'),
            apiToken: null,
            defaultModel: new ModelName('llama3'),
            fastModel: null,
            description: null,
            isActivated: false,
        );

        self::assertNull($aiProvider->apiToken());
    }

    /**
     * @return iterable<string, array{AiProviderTypeEnum, string}>
     */
    public static function provideTypesThatNeedAToken(): iterable
    {
        yield 'Anthropic' => [AiProviderTypeEnum::Anthropic, 'https://api.anthropic.com'];

        yield 'OpenAI' => [AiProviderTypeEnum::OpenAi, 'https://api.openai.com'];

        yield 'Mistral' => [AiProviderTypeEnum::Mistral, 'https://api.mistral.ai'];

        yield 'Google' => [AiProviderTypeEnum::Google, 'https://generativelanguage.googleapis.com'];

        yield 'Bedrock' => [AiProviderTypeEnum::Bedrock, 'https://bedrock-mantle.us-east-1.api.aws'];
    }

    #[DataProvider('provideTypesThatNeedAToken')]
    public function testAHostedProviderCanNotBeCreatedWithoutAToken(AiProviderTypeEnum $type, string $endpointUrl): void
    {
        $this->expectException(InvalidAiProviderConfigurationException::class);
        $this->expectExceptionMessage(sprintf("An API token is required for providers of type '%s'.", $type->value));

        AiProvider::create(
            name: new AiProviderName('Hosted'),
            type: $type,
            endpointUrl: new EndpointUrl($endpointUrl),
            apiToken: null,
            defaultModel: new ModelName('a-model'),
            fastModel: null,
            description: null,
            isActivated: false,
        );
    }

    public function testRetargetingWithANewToken(): void
    {
        $aiProvider = $this->aiProvider();

        $aiProvider->retarget(new EndpointUrl('http://vllm.internal:8000'), new StoredApiToken('new-token'));

        self::assertSame('new-token', $aiProvider->apiToken()?->value);
        self::assertSame('http://vllm.internal:8000', $aiProvider->endpointUrl()->value);
    }

    public function testTheStoredTokenIsOnlyReusedForTheSameTypeAndEndpoint(): void
    {
        $aiProvider = $this->aiProvider();
        $endpointUrl = new EndpointUrl('http://ollama.internal:11434');

        self::assertTrue($aiProvider->canReuseApiTokenFor(AiProviderTypeEnum::OpenAiCompatible, $endpointUrl));
        self::assertFalse($aiProvider->canReuseApiTokenFor(AiProviderTypeEnum::Mistral, $endpointUrl));
    }

    public function testACuratedProviderIsOnlyReachedAtItsOfficialEndpoint(): void
    {
        $this->expectException(InvalidAiProviderConfigurationException::class);

        $this->createdAiProvider(AiProviderTypeEnum::Anthropic, 'https://llm.example.org');
    }

    public function testABedrockProviderIsOnlyReachedAtAMantleEndpoint(): void
    {
        $this->createdAiProvider(AiProviderTypeEnum::Bedrock, 'https://bedrock-mantle.eu-west-3.api.aws');

        $this->expectException(InvalidAiProviderConfigurationException::class);

        $this->createdAiProvider(AiProviderTypeEnum::Bedrock, 'https://llm.example.org');
    }

    public function testAProviderIsNotCreatedForAMetadataService(): void
    {
        $this->expectException(InvalidAiProviderConfigurationException::class);

        $this->createdAiProvider(AiProviderTypeEnum::OpenAiCompatible, 'http://169.254.169.254');
    }

    public function testAStoredProviderLoadsEvenWhenItsEndpointIsNoLongerAccepted(): void
    {
        $aiProvider = $this->aiProvider(AiProviderTypeEnum::Anthropic, 'https://llm.example.org');

        self::assertSame('https://llm.example.org', $aiProvider->endpointUrl()->value);
    }

    public function testRetargetingACuratedProviderElsewhereIsRejected(): void
    {
        $aiProvider = $this->aiProvider(AiProviderTypeEnum::Mistral, 'https://api.mistral.ai');

        $this->expectException(InvalidAiProviderConfigurationException::class);

        $aiProvider->retarget(new EndpointUrl('https://llm.example.org'), new StoredApiToken('new-token'));
    }

    public function testActivation(): void
    {
        $aiProvider = $this->aiProvider();

        $aiProvider->deactivate();
        self::assertFalse($aiProvider->isActivated());

        $aiProvider->activate();
        self::assertTrue($aiProvider->isActivated());
    }

    public function testModelsReasonMinimallyByDefault(): void
    {
        foreach ([$this->aiProvider(), $this->createdAiProvider(AiProviderTypeEnum::OpenAi, 'https://api.openai.com')] as $aiProvider) {
            self::assertSame(ReasoningModeEnum::Minimal, $aiProvider->defaultModelReasoning());
            self::assertSame(ReasoningModeEnum::Minimal, $aiProvider->fastModelReasoning());
        }
    }

    public function testTheReasoningSettingsAreSetOnCreation(): void
    {
        $aiProvider = AiProvider::create(
            name: new AiProviderName('A provider'),
            type: AiProviderTypeEnum::Mistral,
            endpointUrl: new EndpointUrl('https://api.mistral.ai'),
            apiToken: new StoredApiToken('a-token'),
            defaultModel: new ModelName('mistral-large-latest'),
            fastModel: new ModelName('mistral-small-latest'),
            description: null,
            isActivated: false,
            defaultModelReasoning: ReasoningModeEnum::Default,
            fastModelReasoning: ReasoningModeEnum::Minimal,
        );

        self::assertSame(ReasoningModeEnum::Default, $aiProvider->defaultModelReasoning());
        self::assertSame(ReasoningModeEnum::Minimal, $aiProvider->fastModelReasoning());
    }

    public function testTheReasoningSettingsChangeWithTheModels(): void
    {
        $aiProvider = $this->aiProvider();

        $aiProvider->changeModels(
            new ModelName('qwen3:32b'),
            new ModelName('qwen3:8b'),
            ReasoningModeEnum::Minimal,
            ReasoningModeEnum::Default,
        );

        self::assertSame('qwen3:32b', $aiProvider->defaultModel()->value);
        self::assertSame('qwen3:8b', $aiProvider->fastModel()?->value);
        self::assertSame(ReasoningModeEnum::Minimal, $aiProvider->defaultModelReasoning());
        self::assertSame(ReasoningModeEnum::Default, $aiProvider->fastModelReasoning());
    }

    public function testTheReasoningModesAreTheContractValues(): void
    {
        self::assertSame(['minimal', 'default'], ReasoningModeEnum::values());
    }

    private function createdAiProvider(AiProviderTypeEnum $type, string $endpointUrl): AiProvider
    {
        return AiProvider::create(
            name: new AiProviderName('A provider'),
            type: $type,
            endpointUrl: new EndpointUrl($endpointUrl),
            apiToken: new StoredApiToken('a-token'),
            defaultModel: new ModelName('a-model'),
            fastModel: null,
            description: null,
            isActivated: false,
        );
    }

    private function aiProvider(
        AiProviderTypeEnum $type = AiProviderTypeEnum::OpenAiCompatible,
        string $endpointUrl = 'http://ollama.internal:11434',
    ): AiProvider {
        return new AiProvider(
            id: new AiProviderId(1),
            name: new AiProviderName('Local Ollama'),
            type: $type,
            endpointUrl: new EndpointUrl($endpointUrl),
            apiToken: new StoredApiToken('stored-token'),
            defaultModel: new ModelName('llama3'),
            fastModel: null,
            description: null,
            isActivated: true,
        );
    }
}
