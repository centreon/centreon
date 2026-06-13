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

namespace App\AiConfiguration\Infrastructure\Dbal;

use App\AiConfiguration\Domain\Usage\AiProviderUsageRepository;
use App\AiConfiguration\Domain\Usage\UsageLogEntry;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DbalAiProviderUsageRepository extends DbalRepository implements AiProviderUsageRepository
{
    public const TABLE_NAME = 'ai_provider_usage_log';

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
    ) {
    }

    public function add(UsageLogEntry $entry): void
    {
        $this->connection->insert(self::TABLE_NAME, [
            'ai_provider_id' => $entry->providerId,
            'provider_name' => $entry->providerName,
            'feature' => $entry->feature->value,
            'contact_id' => $entry->contactId,
            'model' => $entry->model,
            'tier' => $entry->tier->value,
            'input_tokens' => $entry->inputTokens,
            'output_tokens' => $entry->outputTokens,
            'cost' => $entry->cost,
            'currency' => $entry->currency,
            'requested_at' => $entry->requestedAt,
        ]);
    }

    public function sumCostSince(int $providerId, string $currency, int $sinceTimestamp): float
    {
        $sum = $this->connection->fetchOne(
            'SELECT COALESCE(SUM(cost), 0) FROM ' . self::TABLE_NAME
            . ' WHERE ai_provider_id = :provider_id AND currency = :currency AND requested_at >= :since',
            [
                'provider_id' => $providerId,
                'currency' => $currency,
                'since' => $sinceTimestamp,
            ]
        );

        return is_numeric($sum) ? (float) $sum : 0.0;
    }

    public function deleteOlderThan(int $timestamp): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE_NAME . ' WHERE requested_at < :timestamp',
            ['timestamp' => $timestamp]
        );
    }
}
