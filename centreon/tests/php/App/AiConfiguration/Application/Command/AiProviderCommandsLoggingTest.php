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

namespace Tests\App\AiConfiguration\Application\Command;

use App\AiConfiguration\Application\Command\CreateAiProviderCommand;
use App\AiConfiguration\Application\Command\DiscoverModelsCommand;
use App\AiConfiguration\Application\Command\TestAiProviderConnectionCommand;
use App\AiConfiguration\Application\Command\UpdateAiProviderCommand;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Billing;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Currency;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Pricing;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ReasoningModeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\SpendLimit;
use App\AiConfiguration\Domain\Aggregate\AiProvider\TierPricing;
use App\Shared\Infrastructure\Logging\Attribute\SensitivityScanner;
use App\Shared\Infrastructure\Logging\LogPayloadNormalizer;
use App\Shared\Infrastructure\Logging\PayloadSanitizer;
use App\Shared\Infrastructure\Logging\SensitiveKeywordDenylist;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * `LoggingMiddleware` logs the command payload on every dispatch, and the plain
 * API token travels on these commands until the handler vaults or uses it.
 */
final class AiProviderCommandsLoggingTest extends TestCase
{
    private const TOKEN = 'sk-plain-provider-token-1234';

    /**
     * @return iterable<string, array{object}>
     */
    public static function provideCommandsCarryingAToken(): iterable
    {
        yield 'create' => [new CreateAiProviderCommand(
            name: new AiProviderName('OpenAI production'),
            type: AiProviderTypeEnum::OpenAi,
            apiToken: new ApiToken(self::TOKEN),
            endpointUrl: new EndpointUrl('https://api.openai.com'),
            defaultModel: new ModelName('gpt-5'),
            fastModel: null,
            description: null,
            isActivated: true,
            creatorId: 1,
            billing: new Billing(
                currency: new Currency('USD'),
                pricing: new Pricing(new TierPricing(3.0, 15.0)),
                limit: new SpendLimit(daily: 10.0),
            ),
        )];

        yield 'update' => [new UpdateAiProviderCommand(
            id: new AiProviderId(1),
            name: new AiProviderName('OpenAI production'),
            endpointUrl: new EndpointUrl('https://api.openai.com'),
            apiToken: new ApiToken(self::TOKEN),
            defaultModel: new ModelName('gpt-5'),
            fastModel: null,
            defaultModelReasoning: ReasoningModeEnum::Minimal,
            fastModelReasoning: ReasoningModeEnum::Minimal,
            description: null,
            isActivated: true,
            updatedBy: 1,
        )];

        yield 'connection test' => [new TestAiProviderConnectionCommand(
            type: AiProviderTypeEnum::OpenAi,
            model: new ModelName('gpt-5'),
            endpointUrl: new EndpointUrl('https://api.openai.com'),
            apiToken: new ApiToken(self::TOKEN),
        )];

        yield 'model discovery' => [new DiscoverModelsCommand(
            type: AiProviderTypeEnum::OpenAi,
            endpointUrl: new EndpointUrl('https://api.openai.com'),
            apiToken: new ApiToken(self::TOKEN),
        )];
    }

    #[DataProvider('provideCommandsCarryingAToken')]
    public function testThePlainTokenNeverReachesTheLogs(object $command): void
    {
        SensitivityScanner::reset();
        SensitiveKeywordDenylist::reset();

        $payload = new LogPayloadNormalizer(new CamelCaseToSnakeCaseNameConverter())->normalize($command);
        $sanitised = new PayloadSanitizer()->sanitize($payload, $command::class);

        self::assertIsArray($sanitised);
        self::assertSame('***', $sanitised['api_token']);
        self::assertStringNotContainsString(self::TOKEN, json_encode($sanitised, flags: \JSON_THROW_ON_ERROR));
        // the masking is targeted: the rest of the payload still goes through
        self::assertSame(['value' => 'https://api.openai.com'], $sanitised['endpoint_url']);
    }
}
