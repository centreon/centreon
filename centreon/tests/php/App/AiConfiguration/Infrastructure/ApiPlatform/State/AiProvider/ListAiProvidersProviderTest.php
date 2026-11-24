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

use App\AiConfiguration\Infrastructure\Dbal\DbalAiProviderUsageRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;

final class ListAiProvidersProviderTest extends AiProviderApiTestCase
{
    public function testUnauthenticatedReturns401(): void
    {
        $this->request('GET', self::ENDPOINT);

        self::assertResponseStatusCodeSame(401);
    }

    public function testUserWithoutPermissionReturns403(): void
    {
        $this->login(self::USER_WITHOUT_RIGHTS);

        $this->request('GET', self::ENDPOINT);

        self::assertResponseStatusCodeSame(403);
    }

    public function testListAiProviders(): void
    {
        $this->login();

        $this->createProvider('Anthropic production');
        $this->createProvider('Mistral fallback', type: 'mistral');

        $response = $this->request('GET', self::ENDPOINT);

        self::assertResponseIsSuccessful();
        $content = $response->toArray();
        /** @var list<array<string, mixed>> $members */
        $members = $content['member'];
        self::assertCount(2, $members);
        foreach ($members as $member) {
            self::assertArrayNotHasKey('api_token', $member);
        }
    }

    public function testTheListingShowsTheSpendLimitsAndTheUsage(): void
    {
        $this->login();

        $response = $this->request('POST', self::ENDPOINT, [
            'json' => [
                'name' => 'Capped provider',
                'type' => 'anthropic',
                'api_token' => 'a-very-secret-token',
                'default_model' => 'a-default-model',
                'currency' => 'USD',
                'price_default_input' => 3.0,
                'price_default_output' => 15.0,
                'limit_daily' => 10.0,
                'limit_monthly' => 100.0,
            ],
        ]);
        self::assertResponseStatusCodeSame(201);
        /** @var array{id: int} $created */
        $created = $response->toArray();
        $this->createProvider('Mistral fallback', type: 'mistral');

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $connection->insert(DbalAiProviderUsageRepository::TABLE_NAME, [
            'ai_provider_id' => $created['id'],
            'provider_name' => 'Capped provider',
            'feature' => 'resource_filter',
            'contact_id' => null,
            'model' => 'a-default-model',
            'tier' => 'default',
            'input_tokens' => 1000,
            'output_tokens' => 100,
            'cost' => 0.75,
            'currency' => 'USD',
            'requested_at' => time(),
            'duration_ms' => 500,
        ]);

        $response = $this->request('GET', self::ENDPOINT);

        self::assertResponseIsSuccessful();
        $content = $response->toArray();
        /** @var list<array<string, mixed>> $members */
        $members = $content['member'];
        $byName = array_column($members, null, 'name');

        $capped = $byName['Capped provider'];
        self::assertSame('USD', $capped['currency']);
        self::assertEqualsWithDelta(10.0, $capped['limit_daily'], 0.0001);
        self::assertEqualsWithDelta(100.0, $capped['limit_monthly'], 0.0001);
        self::assertEqualsWithDelta(0.75, $capped['used_daily'], 0.0001);
        self::assertEqualsWithDelta(0.75, $capped['used_monthly'], 0.0001);

        // without a currency, no spend is tracked
        $uncapped = $byName['Mistral fallback'];
        // null values may be left out of the output
        self::assertNull($uncapped['limit_daily'] ?? null);
        self::assertNull($uncapped['used_daily'] ?? null);
        self::assertNull($uncapped['used_monthly'] ?? null);
    }

    public function testFilterByName(): void
    {
        $this->login();

        $this->createProvider('Anthropic production');
        $this->createProvider('Mistral fallback', type: 'mistral');

        $response = $this->request('GET', self::ENDPOINT . '?name[lk]=mistral');

        self::assertResponseIsSuccessful();
        $content = $response->toArray();
        /** @var list<array<string, mixed>> $members */
        $members = $content['member'];
        self::assertCount(1, $members);
        self::assertSame('Mistral fallback', $members[0]['name']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidFilters(): iterable
    {
        yield 'unknown type' => ['?type=unknown'];

        yield 'unknown name operator' => ['?name[like]=mistral'];

        yield 'name without operator' => ['?name=mistral'];

        yield 'non boolean is_activated' => ['?is_activated=maybe'];
    }

    #[DataProvider('provideInvalidFilters')]
    public function testInvalidFilterReturns400(string $query): void
    {
        $this->login();

        $this->request('GET', self::ENDPOINT . $query);

        self::assertResponseStatusCodeSame(400);
    }

    public function testGetItemDoesNotExposeToken(): void
    {
        $this->login();

        $id = $this->createProvider('Anthropic production');

        $response = $this->request('GET', self::ENDPOINT . '/' . $id);

        self::assertResponseIsSuccessful();
        self::assertArrayNotHasKey('api_token', $response->toArray());
    }
}
