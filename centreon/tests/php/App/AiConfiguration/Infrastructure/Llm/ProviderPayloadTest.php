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

use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Infrastructure\Llm\ProviderPayload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProviderPayloadTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, int}>
     */
    public static function tokenCounts(): iterable
    {
        yield 'positive int' => [42, 42];

        yield 'zero' => [0, 0];

        yield 'negative int' => [-5, 0];

        yield 'integral string' => ['42', 42];

        yield 'negative string' => ['-5', 0];

        yield 'decimal string' => ['4.2', 0];

        yield 'integral float' => [42.0, 42];

        yield 'float' => [4.2, 0];

        yield 'null' => [null, 0];

        yield 'array' => [[42], 0];
    }

    #[DataProvider('tokenCounts')]
    public function testTokenCountKeepsOnlyNonNegativeIntegralCounts(mixed $value, int $expected): void
    {
        self::assertSame($expected, ProviderPayload::tokenCount($value));
    }

    /**
     * @return iterable<string, array{mixed, string|null}>
     */
    public static function errorDocuments(): iterable
    {
        yield 'error object' => [['error' => ['message' => 'Invalid API key']], 'Invalid API key'];

        yield 'error string' => [['error' => 'model not found'], 'model not found'];

        yield 'whitespace-only message' => [['error' => ['message' => "  \n"]], null];

        yield 'whitespace-only error string' => [['error' => ' '], null];

        yield 'non-string message' => [['error' => ['message' => 42]], null];

        yield 'error object without message' => [['error' => ['code' => 'invalid']], null];

        yield 'no error' => [['choices' => []], null];

        yield 'not an array' => ['error', null];

        yield 'null' => [null, null];
    }

    #[DataProvider('errorDocuments')]
    public function testErrorMessageIsExtractedFromKnownShapes(mixed $data, ?string $expected): void
    {
        self::assertSame($expected, ProviderPayload::errorMessage($data, new ApiToken('sk-secret')));
    }

    public function testTheApiTokenIsRemovedFromAnErrorMessage(): void
    {
        self::assertSame(
            'Invalid API key: [API token]',
            ProviderPayload::errorMessage(['error' => ['message' => 'Invalid API key: sk-secret']], new ApiToken('sk-secret'))
        );
    }

    public function testAPlaceholderTokenIsNotMasked(): void
    {
        self::assertSame(
            "model 'llama3' not found",
            ProviderPayload::errorMessage(['error' => "model 'llama3' not found"], new ApiToken('a'))
        );
    }

    public function testStringAndArrayKeepOnlyTheirType(): void
    {
        self::assertSame('text', ProviderPayload::string('text'));
        self::assertNull(ProviderPayload::string(42));
        self::assertSame(['a' => 1], ProviderPayload::array(['a' => 1]));
        self::assertSame([], ProviderPayload::array('text'));
    }
}
