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

namespace App\AiConfiguration\Infrastructure\Llm;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Llm\Exception\LlmConfigurationException;
use App\AiConfiguration\Domain\Llm\LlmClientFactoryInterface;
use App\AiConfiguration\Domain\Llm\LlmClientInterface;
use App\AiConfiguration\Domain\Repository\AiProviderRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class LlmClientFactory implements LlmClientFactoryInterface
{
    public function __construct(
        private AiProviderRepository $repository,
        private HttpClientInterface $httpClient,
    ) {
    }

    public function forDefaultProvider(): LlmClientInterface
    {
        $aiProvider = $this->repository->findDefault()
            ?? throw LlmConfigurationException::noDefaultProvider();

        return $this->forProvider($aiProvider);
    }

    public function forProvider(AiProvider $aiProvider): LlmClientInterface
    {
        if ($aiProvider->apiToken->isStoredInVault()) {
            // vault-backed token resolution is not wired into this container yet
            throw LlmConfigurationException::unresolvableToken();
        }

        return match ($aiProvider->type) {
            AiProviderTypeEnum::Anthropic => new AnthropicLlmClient(
                $this->httpClient,
                $aiProvider->endpointUrl,
                $aiProvider->apiToken,
                $aiProvider->defaultModel,
                $aiProvider->fastModel,
            ),
            AiProviderTypeEnum::OpenAi,
            AiProviderTypeEnum::Mistral,
            AiProviderTypeEnum::OpenAiCompatible => new OpenAiCompatibleLlmClient(
                $this->httpClient,
                $aiProvider->endpointUrl,
                $aiProvider->apiToken,
                $aiProvider->defaultModel,
                $aiProvider->fastModel,
            ),
        };
    }
}
