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

namespace Tests\App\AiAssistance\Infrastructure\ApiPlatform\State;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Billing;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Currency;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Pricing;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\TierPricing;
use App\AiConfiguration\Infrastructure\Dbal\DbalAiProviderRepository;
use App\AiConfiguration\Infrastructure\Dbal\DbalAiProviderUsageRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * A provider call is recorded in the usage log end to end, and the record
 * survives the failure of the request that made the call.
 */
final class UsageRecordingTest extends AiAssistanceApiTestCase
{
    private const ENDPOINT = '/api/latest/ai/resource-explanation';
    private const PAYLOAD = [
        'name' => 'Ping',
        'resource_type' => 'service',
        'status' => 'CRITICAL',
        'information' => 'CRITICAL - Packet loss = 100%',
    ];

    public function testAnAnswerIsReturnedAndItsUsageRecorded(): void
    {
        $id = $this->addActivatedProvider();
        $this->useProviderAnswering('## What this means');
        $this->login();

        $this->request('POST', self::ENDPOINT, ['json' => self::PAYLOAD]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['explanation' => '## What this means', 'model' => 'a-model', 'truncated' => false]);
        $this->assertOneUsageRowRecorded($id);
    }

    public function testAnAnswerCutAtMaxTokensIsFlaggedTruncated(): void
    {
        $id = $this->addActivatedProvider();
        $this->useProviderAnswering('## What this', finishReason: 'length');
        $this->login();

        $this->request('POST', self::ENDPOINT, ['json' => self::PAYLOAD]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['explanation' => '## What this', 'truncated' => true]);
        $this->assertOneUsageRowRecorded($id);
    }

    public function testAnEmptyAnswerFailsButItsUsageIsStillRecorded(): void
    {
        $id = $this->addActivatedProvider();
        $this->useProviderAnswering('');
        $this->login();

        $this->request('POST', self::ENDPOINT, ['json' => self::PAYLOAD]);

        self::assertResponseStatusCodeSame(502);
        // the tokens were billed: the usage row is not rolled back with the request
        $this->assertOneUsageRowRecorded($id);
    }

    /**
     * Added through the repository: the kernel is rebooted between two
     * requests, which would drop the HTTP client swapped in below.
     */
    private function addActivatedProvider(): int
    {
        /** @var DbalAiProviderRepository $repository */
        $repository = self::getContainer()->get(DbalAiProviderRepository::class);
        $aiProvider = new AiProvider(
            id: null,
            name: new AiProviderName('Metered provider'),
            type: AiProviderTypeEnum::OpenAiCompatible,
            endpointUrl: new EndpointUrl('https://203.0.113.10'),
            apiToken: new StoredApiToken('a-token'),
            defaultModel: new ModelName('a-model'),
            fastModel: null,
            description: null,
            isActivated: true,
            billing: new Billing(
                currency: new Currency('USD'),
                pricing: new Pricing(new TierPricing(3.0, 15.0)),
            ),
        );
        $repository->add($aiProvider);

        return $aiProvider->id()->value;
    }

    private function useProviderAnswering(string $content, string $finishReason = 'stop'): void
    {
        $body = json_encode([
            'model' => 'a-model',
            'choices' => [['message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => $finishReason]],
            'usage' => ['prompt_tokens' => 200_000, 'completion_tokens' => 100_000],
        ], \JSON_THROW_ON_ERROR);

        self::getContainer()->set(
            'http_client',
            new MockHttpClient(new MockResponse($body, ['response_headers' => ['content-type' => 'application/json']])),
        );
    }

    private function assertOneUsageRowRecorded(int $aiProviderId): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        /** @var list<array{feature: string, input_tokens: int|string, output_tokens: int|string, cost: float|string, currency: string}> $rows */
        $rows = $connection->fetchAllAssociative(
            'SELECT feature, input_tokens, output_tokens, cost, currency FROM '
                . DbalAiProviderUsageRepository::TABLE_NAME . ' WHERE ai_provider_id = :id',
            ['id' => $aiProviderId],
        );

        self::assertCount(1, $rows);
        self::assertSame('resource_explanation', $rows[0]['feature']);
        self::assertSame(200_000, (int) $rows[0]['input_tokens']);
        self::assertSame(100_000, (int) $rows[0]['output_tokens']);
        // 200k input tokens at 3 and 100k output tokens at 15 per million
        self::assertEqualsWithDelta(2.1, (float) $rows[0]['cost'], 0.000001);
        self::assertSame('USD', $rows[0]['currency']);
    }
}
