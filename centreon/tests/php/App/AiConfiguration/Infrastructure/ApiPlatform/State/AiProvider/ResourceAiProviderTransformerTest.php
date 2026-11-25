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

namespace Tests\App\AiConfiguration\Infrastructure\ApiPlatform\State\AiProvider;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Infrastructure\ApiPlatform\State\AiProvider\ResourceAiProviderTransformer;
use PHPUnit\Framework\TestCase;

final class ResourceAiProviderTransformerTest extends TestCase
{
    public function testTheTokenIsNeverExposedOnlyWhetherOneIsStored(): void
    {
        $withToken = new ResourceAiProviderTransformer()->transform($this->aiProvider(new StoredApiToken('a-token')));
        $withoutToken = new ResourceAiProviderTransformer()->transform($this->aiProvider(null));

        self::assertNull($withToken->apiToken);
        self::assertTrue($withToken->hasApiToken);
        self::assertNull($withoutToken->apiToken);
        self::assertFalse($withoutToken->hasApiToken);
    }

    private function aiProvider(?StoredApiToken $apiToken): AiProvider
    {
        return new AiProvider(
            id: new AiProviderId(1),
            name: new AiProviderName('Local Ollama'),
            type: AiProviderTypeEnum::OpenAiCompatible,
            endpointUrl: new EndpointUrl('http://ollama.internal:11434'),
            apiToken: $apiToken,
            defaultModel: new ModelName('qwen3:0.6b'),
            fastModel: null,
            description: null,
            isActivated: false,
        );
    }
}
