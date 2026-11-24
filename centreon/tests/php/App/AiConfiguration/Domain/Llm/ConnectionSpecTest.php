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

namespace Tests\App\AiConfiguration\Domain\Llm;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Domain\Exception\InvalidAiProviderConfigurationException;
use App\AiConfiguration\Domain\Llm\ConnectionSpec;
use PHPUnit\Framework\TestCase;

final class ConnectionSpecTest extends TestCase
{
    public function testACuratedProviderIsReachedAtItsOfficialEndpoint(): void
    {
        $spec = new ConnectionSpec(
            AiProviderTypeEnum::Mistral,
            new EndpointUrl('https://elsewhere.example.org'),
            new StoredApiToken('a-token'),
        );

        self::assertSame('https://api.mistral.ai', $spec->endpointUrl->value);
    }

    public function testACompatibleProviderKeepsItsEndpoint(): void
    {
        $spec = new ConnectionSpec(
            AiProviderTypeEnum::OpenAiCompatible,
            new EndpointUrl('http://10.0.0.12:8000'),
            new StoredApiToken('a-token'),
        );

        self::assertSame('http://10.0.0.12:8000', $spec->endpointUrl->value);
    }

    public function testABedrockEndpointMustBeAMantleOne(): void
    {
        $this->expectException(InvalidAiProviderConfigurationException::class);

        new ConnectionSpec(
            AiProviderTypeEnum::Bedrock,
            new EndpointUrl('https://elsewhere.example.org'),
            new StoredApiToken('a-token'),
        );
    }
}
