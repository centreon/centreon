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

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Repository\ModuleRepository;
use App\MonitoringConfiguration\Domain\Repository\MonitoringConnectorTemplateRepository;
use App\Shared\Domain\Collection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The Monitoring Connectors are stored in the plugin pack manager module's tables, which only exist
 * once that module is installed.
 */
final readonly class DbalMonitoringConnectorTemplateRepository implements MonitoringConnectorTemplateRepository
{
    private const PLUGIN_PACK_MANAGER_MODULE = 'centreon-pp-manager';

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        private ModuleRepository $moduleRepository,
    ) {
    }

    public function findTemplateIds(array $excludedSlugs): Collection
    {
        if (! $this->moduleRepository->isInstalled(self::PLUGIN_PACK_MANAGER_MODULE)) {
            return new Collection([], HostTemplateId::class);
        }

        // The connector templates, then every template inheriting from them (legacy getHostChain()).
        // UNION dedupes the reached ids, so a template loop already in the data cannot make the
        // recursion run forever.
        [$excludeClause, $params, $types] = $excludedSlugs === []
            ? ['', [], []]
            : ['WHERE pp.slug NOT IN (:excludedSlugs)', ['excludedSlugs' => $excludedSlugs], ['excludedSlugs' => ArrayParameterType::STRING]];
        $sql = <<<SQL
            WITH RECURSIVE connector_templates (id) AS (
                SELECT ph.host_id
                FROM mod_ppm_pluginpack_host ph
                INNER JOIN mod_ppm_pluginpack pp ON pp.pluginpack_id = ph.pluginpack_id
                {$excludeClause}
                UNION
                SELECT htr.host_host_id
                FROM host_template_relation htr
                INNER JOIN connector_templates ct ON ct.id = htr.host_tpl_id
            )
            SELECT h.host_id
            FROM host h
            INNER JOIN connector_templates ct ON ct.id = h.host_id
            WHERE h.host_register = '0'
            ORDER BY h.host_id
            SQL;

        /** @var list<int|string> $ids */
        $ids = $this->connection->executeQuery($sql, $params, $types)->fetchFirstColumn();

        return new Collection(
            array_map(static fn (int|string $id): HostTemplateId => new HostTemplateId((int) $id), $ids),
            HostTemplateId::class,
        );
    }
}
