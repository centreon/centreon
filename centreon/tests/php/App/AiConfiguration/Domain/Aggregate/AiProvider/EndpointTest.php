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

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Exception\InvalidAiProviderConfigurationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EndpointTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNonBaseUrls(): iterable
    {
        yield 'credentials' => ['https://user:password@llm.example.org'];

        yield 'user only' => ['https://user@llm.example.org'];

        yield 'query string' => ['https://llm.example.org/?key=value'];

        yield 'empty query string' => ['https://llm.example.org/?'];

        yield 'fragment' => ['https://llm.example.org/#part'];

        yield 'not http' => ['ftp://llm.example.org'];
    }

    #[DataProvider('provideNonBaseUrls')]
    public function testAnEndpointIsABaseUrl(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EndpointUrl($url);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function linkLocalEndpoints(): iterable
    {
        yield 'IPv4 metadata service' => ['http://169.254.169.254'];

        yield 'IPv6 link-local' => ['http://[fe80::1]:8080'];
    }

    #[DataProvider('linkLocalEndpoints')]
    public function testALinkLocalEndpointIsRejected(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('link-local');

        new EndpointUrl($url);
    }

    public function testAPrivateNetworkEndpointIsAccepted(): void
    {
        self::assertSame('http://10.0.0.5:11434', new EndpointUrl('http://10.0.0.5:11434')->value);
        self::assertFalse(EndpointUrl::isLinkLocal('10.0.0.5'));
        self::assertFalse(EndpointUrl::isLinkLocal('fd00::1'));
        self::assertFalse(EndpointUrl::isLinkLocal('ollama.example.com'));
    }

    public function testAnEndpointMayHaveAPortAndAPath(): void
    {
        self::assertSame('http://ollama.internal:11434/api', new EndpointUrl('http://ollama.internal:11434/api')->value);
    }

    public function testACuratedProviderIgnoresTheInput(): void
    {
        self::assertSame(
            'https://api.anthropic.com',
            AiProviderTypeEnum::Anthropic->resolveEndpoint('https://attacker.example.org')->value
        );
    }

    public function testAnOpenAiCompatibleProviderUsesTheInput(): void
    {
        self::assertSame(
            'http://ollama.internal:11434',
            AiProviderTypeEnum::OpenAiCompatible->resolveEndpoint('http://ollama.internal:11434')->value
        );
    }

    /**
     * @return iterable<string, array{AiProviderTypeEnum, string|null}>
     */
    public static function provideRejectedInputs(): iterable
    {
        yield 'OpenAI-compatible without endpoint' => [AiProviderTypeEnum::OpenAiCompatible, null];

        yield 'OpenAI-compatible with a blank endpoint' => [AiProviderTypeEnum::OpenAiCompatible, ' '];

        yield 'OpenAI-compatible with credentials' => [AiProviderTypeEnum::OpenAiCompatible, 'https://user:pass@llm.example.org'];

        yield 'Bedrock without endpoint' => [AiProviderTypeEnum::Bedrock, null];

        yield 'Bedrock with another host' => [AiProviderTypeEnum::Bedrock, 'https://attacker.example.org'];

        yield 'Bedrock over http' => [AiProviderTypeEnum::Bedrock, 'http://bedrock-mantle.us-east-1.api.aws'];

        yield 'Bedrock with a path' => [AiProviderTypeEnum::Bedrock, 'https://bedrock-mantle.us-east-1.api.aws/other'];

        yield 'Bedrock on a look-alike host' => [AiProviderTypeEnum::Bedrock, 'https://bedrock-mantle.us-east-1.api.aws.example.org'];
    }

    #[DataProvider('provideRejectedInputs')]
    public function testAnEndpointTheTypeDoesNotAcceptIsRejected(AiProviderTypeEnum $type, ?string $input): void
    {
        $this->expectException(InvalidAiProviderConfigurationException::class);

        $type->resolveEndpoint($input);
    }

    public function testBedrockAcceptsTheMantleEndpointOfARegion(): void
    {
        self::assertSame(
            'https://bedrock-mantle.eu-west-3.api.aws',
            AiProviderTypeEnum::Bedrock->resolveEndpoint('https://bedrock-mantle.eu-west-3.api.aws')->value
        );
    }
}
