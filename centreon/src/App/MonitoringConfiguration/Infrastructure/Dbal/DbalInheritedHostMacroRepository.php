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
        $macros = $this->findTemplateMacros($templateIds);

        if ($checkCommandId instanceof CommandId) {
            foreach ($this->findCommandMacros($checkCommandId) as $macro) {
                $macros[] = $macro;
            }
        }

        return new Collection($macros, HostMacro::class);
    }

    /**
     * @param Collection<HostTemplateId> $templateIds
     *
     * @return list<HostMacro>
     */
    private function findTemplateMacros(Collection $templateIds): array
    {
        if (count($templateIds) === 0) {
            return [];
        }

        $ids = array_map(static fn (HostTemplateId $id): int => $id->value, $templateIds->toArray());

        $qb = $this->connection->createQueryBuilder();
        $qb->select('host_macro_name', 'host_macro_value', 'is_password')
            ->from('on_demand_macro_host')
            ->where($qb->expr()->in('host_host_id', ':templateIds'))
            ->setParameter('templateIds', $ids, ArrayParameterType::INTEGER)
            ->orderBy('macro_order');

        /** @var array<array{host_macro_name: string, host_macro_value: ?string, is_password: ?string}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $macros = [];
        foreach ($rows as $row) {
            $shortName = $this->extractShortName($row['host_macro_name']);
            if ($shortName === null) {
                continue;
            }

            $macros[] = new HostMacro(
                new HostMacroName($shortName),
                (string) $row['host_macro_value'],
                isPassword: (bool) (int) ($row['is_password'] ?? 0),
            );
        }

        return $macros;
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
