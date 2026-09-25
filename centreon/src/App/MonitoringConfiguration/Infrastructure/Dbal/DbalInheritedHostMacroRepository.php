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

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Repository\InheritedHostMacroRepository;
use App\Shared\Domain\Collection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DbalInheritedHostMacroRepository implements InheritedHostMacroRepository
{
    /** Command macros the engine fills itself — never treated as inheritable custom host macros. */
    private const RESERVED_COMMAND_MACROS = ['SNMPVERSION', 'SNMPCOMMUNITY'];

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
    ) {
    }

    public function findInheritedMacros(Collection $templateIds, ?CommandId $checkCommandId): Collection
    {
        $directTemplateIds = array_values(
            array_map(static fn (HostTemplateId $id): int => $id->value, $templateIds->toArray()),
        );

        // Full multi-level inheritance line, nearest-to-host first (legacy getTemplateChain /
        // InheritanceManager::findInheritanceLine: depth-first, `order ASC`, first occurrence kept).
        $inheritanceLine = $this->resolveInheritanceLine($directTemplateIds);

        $macrosByOwner = $this->findTemplateMacrosByOwner($inheritanceLine);

        // One macro per name: walking nearest-to-host first, the closest definition wins over any
        // farther ancestor (legacy comparaPriority keeps the first-encountered same-source macro).
        $resolved = [];
        foreach ($inheritanceLine as $ownerId) {
            foreach ($macrosByOwner[$ownerId] ?? [] as $macro) {
                $resolved[$macro->name->value] ??= $macro;
            }
        }

        // Check-command macros are the lowest priority: they only fill names no template defines
        // (legacy comparaPriority: fromTpl(2) > fromCommand(1)).
        if ($checkCommandId instanceof CommandId) {
            foreach ($this->findCommandMacros($checkCommandId) as $macro) {
                $resolved[$macro->name->value] ??= $macro;
            }
        }

        return new Collection(array_values($resolved), HostMacro::class);
    }

    /**
     * Expands the host's direct templates into the full inheritance line: each direct template
     * (already in `order` order) followed by its own ancestors, depth-first, nearest-to-host first,
     * with a visited guard so a template shared by several branches is walked once (its nearest
     * position). Mirrors legacy CentreonHost::getTemplateChain().
     *
     * @param list<int> $directTemplateIds
     *
     * @return list<int>
     */
    private function resolveInheritanceLine(array $directTemplateIds): array
    {
        $line = [];
        $visited = [];

        $walk = function (int $templateId) use (&$walk, &$line, &$visited): void {
            if (isset($visited[$templateId])) {
                return;
            }
            $visited[$templateId] = true;
            $line[] = $templateId;

            foreach ($this->findDirectParentTemplateIds($templateId) as $parentId) {
                $walk($parentId);
            }
        };

        foreach ($directTemplateIds as $templateId) {
            $walk($templateId);
        }

        return $line;
    }

    /**
     * The active template parents of one template, in `order`. Matches legacy getTemplateChain's
     * query (activated, register = '0').
     *
     * @return list<int>
     */
    private function findDirectParentTemplateIds(int $templateId): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('htr.host_tpl_id')
            ->from('host_template_relation', 'htr')
            ->innerJoin('htr', 'host', 'h', 'h.host_id = htr.host_tpl_id')
            ->where('htr.host_host_id = :templateId')
            ->andWhere("h.host_activate = '1'")
            ->andWhere("h.host_register = '0'")
            ->setParameter('templateId', $templateId)
            ->orderBy('htr.`order`', 'ASC');

        /** @var list<int|string> $ids */
        $ids = $qb->executeQuery()->fetchFirstColumn();

        return array_map(static fn (int|string $id): int => (int) $id, $ids);
    }

    /**
     * Custom macros defined on the given templates, grouped by the template that owns them and kept
     * in `macro_order` within each template.
     *
     * @param list<int> $ownerIds
     *
     * @return array<int, list<HostMacro>>
     */
    private function findTemplateMacrosByOwner(array $ownerIds): array
    {
        if ($ownerIds === []) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        // description is loaded so an override that only changes the description can be told apart from
        // a pure duplicate (see HostMacroInheritanceResolver::keepOverridesOnly).
        $qb->select('host_host_id', 'host_macro_name', 'host_macro_value', 'is_password', 'description')
            ->from('on_demand_macro_host')
            ->where($qb->expr()->in('host_host_id', ':ownerIds'))
            ->setParameter('ownerIds', $ownerIds, ArrayParameterType::INTEGER)
            ->orderBy('macro_order');

        /** @var array<array{host_host_id: int, host_macro_name: string, host_macro_value: ?string, is_password: ?string, description: ?string}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $byOwner = [];
        foreach ($rows as $row) {
            $shortName = $this->extractShortName($row['host_macro_name']);
            if ($shortName === null) {
                continue;
            }

            $byOwner[(int) $row['host_host_id']][] = new HostMacro(
                new HostMacroName($shortName),
                (string) $row['host_macro_value'],
                isPassword: (bool) (int) ($row['is_password'] ?? 0),
                description: $row['description'] ?? null,
            );
        }

        return $byOwner;
    }

    /**
     * Extracts the `$_HOST<NAME>$` macros a check command declares in its command line, exactly as
     * legacy CentreonCommand::getMacroByIdAndType() does: engine-reserved SNMP macros are excluded
     * and each macro carries an empty value (the host supplies it).
     *
     * @return list<HostMacro>
     */
    private function findCommandMacros(CommandId $checkCommandId): array
    {
        $commandLine = $this->connection->createQueryBuilder()
            ->select('command_line')
            ->from('command')
            ->where('command_id = :id')
            // Only a check command (type 2) contributes inherited macros, like legacy getMacroByIdAndType().
            ->andWhere('command_type = :type')
            ->setParameter('id', $checkCommandId->value)
            ->setParameter('type', CommandTypeEnum::Check->value)
            ->executeQuery()
            ->fetchOne();

        if (! is_string($commandLine)) {
            return [];
        }

        preg_match_all('/\$_HOST([\w_-]+)\$/', $commandLine, $matches);

        $macros = [];
        $seen = [];
        foreach ($matches[1] as $rawName) {
            $upperName = mb_strtoupper($rawName);
            if (in_array($upperName, self::RESERVED_COMMAND_MACROS, true)) {
                continue;
            }
            if (isset($seen[$upperName])) {
                continue;
            }
            $seen[$upperName] = true;

            $macros[] = new HostMacro(new HostMacroName($rawName), '', isPassword: false);
        }

        return $macros;
    }

    private function extractShortName(string $storedName): ?string
    {
        return preg_match('/^\$_HOST(.+)\$$/', $storedName, $matches) === 1 ? $matches[1] : null;
    }
}
