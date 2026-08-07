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

namespace App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\AgentConfiguration\AgentConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Exception\AgentConfigurationNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\AgentConfigurationRepository;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *   ac_id: int,
 *   ac_name: string,
 *   ac_type: string,
 *   ac_connection_mode: string,
 *   ac_configuration: string,
 * }
 */
final readonly class DbalAgentConfigurationRepository extends DbalRepository implements AgentConfigurationRepository
{
    public const TABLE_NAME = 'agent_configuration';
    public const POLLER_RELATION_TABLE_NAME = 'ac_poller_relation';

    /**
     * @param TransformerInterface<RowTypeAlias, AgentConfiguration> $agentConfigurationTransformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: DbalAgentConfigurationTransformer::class)]
        private TransformerInterface $agentConfigurationTransformer,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getByPollerId(PollerId $pollerId): AgentConfiguration
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(...self::getSelectColumns())
            ->from(self::TABLE_NAME, 'ac')
            ->innerJoin('ac', self::POLLER_RELATION_TABLE_NAME, 'rel', 'rel.ac_id = ac.id')
            ->where('rel.poller_id = :poller_id')
            ->setParameter('poller_id', $pollerId->value);

        /** @var RowTypeAlias|false $row */
        $row = $qb->executeQuery()->fetchAssociative();

        if ($row === false) {
            throw new AgentConfigurationNotFoundException(['poller_id' => $pollerId->value]);
        }

        return $this->agentConfigurationTransformer->transform($row);
    }

    /**
     * @return array<string>
     */
    public static function getSelectColumns(string $alias = 'ac'): array
    {
        return [
            "{$alias}.id AS ac_id",
            "{$alias}.name AS ac_name",
            "{$alias}.type AS ac_type",
            "{$alias}.connection_mode AS ac_connection_mode",
            "{$alias}.configuration AS ac_configuration",
        ];
    }
}
