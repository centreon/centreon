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
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Domain\Exception\AiProviderCanNotBeUpdatedException;
use App\AiConfiguration\Domain\Exception\InvalidAiProviderConfigurationException;
use PHPUnit\Framework\TestCase;

final class AiProviderTest extends TestCase
{
    public function testRetargetingKeepsTheTokenForTheSameEndpoint(): void
    {
        $aiProvider = $this->aiProvider();

        // a trailing slash does not change the target
        $aiProvider->retarget(new EndpointUrl('http://ollama.internal:11434/'), null);

        self::assertSame('stored-token', $aiProvider->apiToken()->value);
        self::assertSame('http://ollama.internal:11434/', $aiProvider->endpointUrl()->value);
    }

    public function testRetargetingToAnotherEndpointRequiresANewToken(): void
    {
        $aiProvider = $this->aiProvider();

        $this->expectException(AiProviderCanNotBeUpdatedException::class);

        $aiProvider->retarget(new EndpointUrl('https://attacker.example.org'), null);
    }

    public function testRetargetingWithANewToken(): void
    {
        $aiProvider = $this->aiProvider();

        $aiProvider->retarget(new EndpointUrl('http://vllm.internal:8000'), new StoredApiToken('new-token'));

        self::assertSame('new-token', $aiProvider->apiToken()->value);
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

        $this->aiProvider(AiProviderTypeEnum::Anthropic, 'https://llm.example.org');
    }

    public function testABedrockProviderIsOnlyReachedAtAMantleEndpoint(): void
    {
        $this->aiProvider(AiProviderTypeEnum::Bedrock, 'https://bedrock-mantle.eu-west-3.api.aws');

        $this->expectException(InvalidAiProviderConfigurationException::class);

        $this->aiProvider(AiProviderTypeEnum::Bedrock, 'https://llm.example.org');
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
