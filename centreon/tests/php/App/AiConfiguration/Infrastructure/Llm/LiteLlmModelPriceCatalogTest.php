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

use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Infrastructure\Llm\LiteLlmModelPriceCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tests\App\Shared\Double\FakeHttpProxyProvider;

final class LiteLlmModelPriceCatalogTest extends TestCase
{
    private const CATALOG = <<<'JSON'
        {
            "gpt-4o": {
                "input_cost_per_token": 2.5e-06,
                "output_cost_per_token": 1e-05,
                "litellm_provider": "openai"
            },
            "embedding-only": {
                "litellm_provider": "openai"
            }
        }
        JSON;

    public function testReturnsPerMillionPricesOnExactMatch(): void
    {
        $catalog = $this->catalog(self::CATALOG);

        $price = $catalog->priceFor('gpt-4o');

        self::assertNotNull($price);
        self::assertEqualsWithDelta(2.5, $price->inputPricePerMillion, 0.0001);
        self::assertEqualsWithDelta(10.0, $price->outputPricePerMillion, 0.0001);
    }

    public function testUnknownModelReturnsNull(): void
    {
        self::assertNull($this->catalog(self::CATALOG)->priceFor('claude-sonnet-4-6'));
    }

    public function testModelWithoutPriceReturnsNull(): void
    {
        self::assertNull($this->catalog(self::CATALOG)->priceFor('embedding-only'));
    }

    public function testFetchedOnlyOncePerInstance(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(self::CATALOG),
            new MockResponse('{}'),
        ]);
        $catalog = new LiteLlmModelPriceCatalog($httpClient, new FakeHttpProxyProvider());

        $catalog->priceFor('gpt-4o');
        $second = $catalog->priceFor('gpt-4o');

        // a second lookup must not trigger a second request (still found)
        self::assertNotNull($second);
        self::assertSame(1, $httpClient->getRequestsCount());
    }

    public function testTransportFailureBecomesARequestFailure(): void
    {
        $httpClient = new MockHttpClient(static function (): MockResponse {
            throw new \RuntimeException('network down');
        });
        $catalog = new LiteLlmModelPriceCatalog($httpClient, new FakeHttpProxyProvider());

        $this->expectException(LlmRequestFailedException::class);

        $catalog->priceFor('gpt-4o');
    }

    private function catalog(string $json): LiteLlmModelPriceCatalog
    {
        return new LiteLlmModelPriceCatalog(
            new MockHttpClient([new MockResponse($json)]),
            new FakeHttpProxyProvider()
        );
    }
}
