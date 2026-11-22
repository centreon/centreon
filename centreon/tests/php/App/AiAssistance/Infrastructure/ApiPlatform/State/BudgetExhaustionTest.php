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

use App\AiConfiguration\Infrastructure\Dbal\DbalAiProviderUsageRepository;
use Doctrine\DBAL\Connection;

/**
 * The enabled provider has spent more than its spend limit: the assistants
 * are reported unavailable and refuse to run.
 */
final class BudgetExhaustionTest extends AiAssistanceApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
    }

    public function testTheAvailabilityReportsTheExhaustedBudget(): void
    {
        $this->login();
        $this->createProviderOverItsBudget();

        $this->request('GET', '/api/latest/ai/availability');

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['is_available' => false, 'reason' => 'budget_exhausted']);
    }

    public function testAnAssistantRefusesToRunReturns409(): void
    {
        $this->login();
        $this->createProviderOverItsBudget();

        $this->request('POST', '/api/latest/ai/resource-filter', ['json' => ['prompt' => 'critical services']]);

        // refused before any call: the provider endpoint is unreachable, which would be a 502
        self::assertResponseStatusCodeSame(409);
    }

    private function createProviderOverItsBudget(): void
    {
        $response = $this->request('POST', '/api/latest/administration/ai-providers', [
            'json' => [
                'name' => 'Capped provider',
                'type' => 'openai_compatible',
                'api_token' => 'a-token',
                'endpoint_url' => 'http://127.0.0.1:1',
                'default_model' => 'a-model',
                'is_activated' => true,
                'currency' => 'USD',
                'price_default_input' => 3.0,
                'price_default_output' => 15.0,
                'limit_daily' => 1.0,
            ],
        ]);
        self::assertResponseStatusCodeSame(201);
        /** @var array{id: int} $content */
        $content = $response->toArray();

        // two calls logged a moment ago, together above the daily limit
        foreach ([0.6, 0.5] as $cost) {
            $this->connection->insert(DbalAiProviderUsageRepository::TABLE_NAME, [
                'ai_provider_id' => $content['id'],
                'provider_name' => 'Capped provider',
                'feature' => 'resource_filter',
                'contact_id' => null,
                'model' => 'a-model',
                'tier' => 'fast',
                'input_tokens' => 1000,
                'output_tokens' => 100,
                'cost' => $cost,
                'currency' => 'USD',
                'requested_at' => time(),
                'duration_ms' => 500,
            ]);
        }
    }
}
