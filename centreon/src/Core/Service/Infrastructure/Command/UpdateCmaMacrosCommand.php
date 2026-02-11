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

namespace Core\Service\Infrastructure\Command;

use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteServiceMacroRepositoryInterface;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'cma:update-macros',
    description: 'Update CMA service macros by adding colon suffix to specific macro values when they are not empty'
)]
final class UpdateCmaMacrosCommand
{
    /** @var array<string, string[]> */
    private const CMA_COMMAND_MACROS = [
        'OS-Windows-Centreon-Monitoring-Agent-Uptime' => [
            'WARNINGUPTIME',
            'CRITICALUPTIME',
        ],
        'OS-Windows-Centreon-Monitoring-Agent-Services' => [
            'WARNINGTOTALRUNNING',
            'CRITICALTOTALRUNNING',
        ],
        'OS-Windows-Centreon-Monitoring-Agent-Memory' => [
            'WARNINGUSAGEFREE',
            'CRITICALUSAGEFREE',
            'WARNINGUSAGEFREEPRCT',
            'CRITICALUSAGEFREEPRCT',
            'WARNINGSWAPFREE',
            'CRITICALSWAPFREE',
            'WARNINGSWAPFREEPRCT',
            'CRITICALSWAPFREEPRCT',
            'WARNINGVIRTUALFREE',
            'CRITICALVIRTUALFREE',
            'WARNINGVIRTUALFREEPRCT',
            'CRITICALVIRTUALFREEPRCT',
        ],
    ];

    public function __construct(
        private readonly ReadServiceRepositoryInterface $readServiceRepository,
        private readonly ReadServiceTemplateRepositoryInterface $readServiceTemplateRepository,
        private readonly ReadServiceMacroRepositoryInterface $readServiceMacroRepository,
        private readonly WriteServiceMacroRepositoryInterface $writeServiceMacroRepository,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(name: 'poller-ids', description: 'Comma-separated list of poller IDs to filter services')]
        ?string $pollerIds = null,
        #[Option(name: 'host-ids', description: 'Comma-separated list of host IDs to filter services')]
        ?string $hostIds = null,
    ): int {
        $io->title('Update CMA service macros thresholds.');

        $pollerIds = $this->parseIdList($pollerIds);
        $hostIds = $this->parseIdList($hostIds);

        $serviceIds = $this->readServiceRepository->findIdsByCommandNames(
            array_keys(self::CMA_COMMAND_MACROS),
            $pollerIds,
            $hostIds
        );

        $serviceTemplateIds = [];
        if ($pollerIds === [] && $hostIds === []) {
            $serviceTemplateIds = $this->readServiceTemplateRepository->findIdsByCommandNames(
                array_keys(self::CMA_COMMAND_MACROS)
            );
        }

        $io->info(sprintf(
            'Found %d services [%s] and %d service templates [%s] using CMA commands.',
            count($serviceIds),
            implode(', ', $serviceIds),
            count($serviceTemplateIds),
            implode(', ', $serviceTemplateIds)
        ));

        $servicesMacros = $serviceIds !== [] ? $this->readServiceMacroRepository->findByServiceIds(...$serviceIds) : [];
        $serviceTemplatesMacros = $serviceTemplateIds !== [] ? $this->readServiceMacroRepository->findByServiceIds(...$serviceTemplateIds) : [];
        $macros = array_merge($servicesMacros, $serviceTemplatesMacros);

        if ($macros === []) {
            $io->success('No macros found to update.');

            return Command::SUCCESS;
        }

        $macroNamesToUpdate = array_merge(...array_values(self::CMA_COMMAND_MACROS));
        $macrosToUpdate = array_filter(
            $macros,
            fn ($macro) => in_array($macro->getName(), $macroNamesToUpdate, true)
        );

        $io->info(sprintf(
            'Found %d macros to process.',
            count($macrosToUpdate)
        ));

        $updatedMacros = [];
        foreach ($macrosToUpdate as $macro) {
            $value = $macro->getValue();
            if ($value !== '' && ! str_ends_with($value, ':')) {
                $macro->setValue($value . ':');
                $this->writeServiceMacroRepository->update($macro);
                $updatedMacros[] = [
                    'name' => $macro->getName(),
                    'previous' => $value,
                    'updated' => $macro->getValue(),
                ];
            }
        }

        if ($updatedMacros !== []) {
            $io->section('Updated Macros:');
            foreach ($updatedMacros as $macroInfo) {
                $io->text(sprintf(
                    'Macro "%s": "%s" => "%s"',
                    $macroInfo['name'],
                    $macroInfo['previous'],
                    $macroInfo['updated']
                ));
            }
        } else {
            $io->info('No macros required updating.');
        }

        $io->success('CMA service macros update completed successfully.');

        return Command::SUCCESS;
    }

    /**
     * Parse comma-separated ID list.
     *
     * @param string|null $ids
     *
     * @return int[]
     */
    private function parseIdList(?string $ids): array
    {
        if ($ids === null || trim($ids) === '') {
            return [];
        }

        return array_filter(
            array_map('intval', explode(',', $ids)),
            fn ($id) => $id > 0
        );
    }
}
