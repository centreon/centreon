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

namespace Tests\App\AiConfiguration\Infrastructure\Dbal;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ReasoningModeEnum;
use App\AiConfiguration\Infrastructure\Dbal\DbalAiProviderTransformer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DbalAiProviderTransformerTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, string|null>, ReasoningModeEnum, ReasoningModeEnum}>
     */
    public static function provideReasoningSettings(): iterable
    {
        yield 'saved before the settings existed' => [[], ReasoningModeEnum::Minimal, ReasoningModeEnum::Minimal];

        yield 'both set' => [
            ['default_model_reasoning' => 'default', 'fast_model_reasoning' => 'minimal'],
            ReasoningModeEnum::Default,
            ReasoningModeEnum::Minimal,
        ];

        yield 'only the fast model set' => [
            ['fast_model_reasoning' => 'default'],
            ReasoningModeEnum::Minimal,
            ReasoningModeEnum::Default,
        ];

        yield 'stored as null' => [
            ['default_model_reasoning' => null, 'fast_model_reasoning' => null],
            ReasoningModeEnum::Minimal,
            ReasoningModeEnum::Minimal,
        ];
    }

    /**
     * @param array<string, string|null> $settings
     */
    #[DataProvider('provideReasoningSettings')]
    public function testTheReasoningSettingsAreRead(
        array $settings,
        ReasoningModeEnum $defaultModelReasoning,
        ReasoningModeEnum $fastModelReasoning,
    ): void {
        $aiProvider = $this->transform($settings);

        self::assertSame('mistral-large-latest', $aiProvider->defaultModel()->value);
        self::assertSame($defaultModelReasoning, $aiProvider->defaultModelReasoning());
        self::assertSame($fastModelReasoning, $aiProvider->fastModelReasoning());
    }

    public function testAServerThatNeedsNoTokenIsReadWithoutOne(): void
    {
        self::assertNull($this->transform(['api_token' => null])->apiToken());
        self::assertSame('a-token', $this->transform([])->apiToken()?->value);
    }

    public function testAnUnknownReasoningValueFailsLikeAnUnknownType(): void
    {
        // read as another value, it would be overwritten on the next save
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('The AI provider 1 has an unknown default_model_reasoning: "high"');

        $this->transform(['default_model_reasoning' => 'high']);
    }

    /**
     * @param array<string, string|null> $settings
     */
    private function transform(array $settings): AiProvider
    {
        return new DbalAiProviderTransformer()->transform([
            'ap_id' => '1',
            'ap_type' => 'mistral',
            'ap_name' => 'Mistral',
            'ap_description' => null,
            'ap_is_activated' => '1',
            'ap_configuration' => json_encode([
                'endpoint_url' => 'https://api.mistral.ai',
                'api_token' => 'a-token',
                'default_model' => 'mistral-large-latest',
                'fast_model' => 'mistral-small-latest',
                ...$settings,
            ], \JSON_THROW_ON_ERROR),
        ]);
    }
}
