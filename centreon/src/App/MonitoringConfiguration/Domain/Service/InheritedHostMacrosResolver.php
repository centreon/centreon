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

namespace App\MonitoringConfiguration\Domain\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\InheritedHostMacros;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\Shared\Domain\Collection;

/**
 * Resolves the macros a host (or template) inherits from its templates and commands, the way legacy
 * CentreonHost::getMacros() does:
 *
 * - the templates of its full inheritance line, nearest first;
 * - then the commands, in priority order: the host's own check command or, when it has none, the
 *   check command of the first template of the line that has one; then, for each template of the
 *   line, the check commands of the service templates linked to it.
 */
final readonly class InheritedHostMacrosResolver
{
    public function __construct(
        private HostTemplateRepository $hostTemplateRepository,
        private CommandRepository $commandRepository,
    ) {
    }

    /**
     * @param Collection<HostTemplateId> $templateIds the host's direct templates, in order
     * @param ?CommandId $checkCommandId the host's own check command
     */
    public function resolve(Collection $templateIds, ?CommandId $checkCommandId): InheritedHostMacros
    {
        /** @var list<HostTemplate> $inheritanceLine */
        $inheritanceLine = array_values($this->hostTemplateRepository->findInheritanceLine($templateIds)->toArray());

        return $this->resolveLine($inheritanceLine, $checkCommandId);
    }

    /**
     * Same resolution, from an inheritance line the caller already loaded
     * ({@see HostTemplateRepository::findInheritanceLine()}), so it is not fetched twice.
     *
     * @param list<HostTemplate> $inheritanceLine the full template line, nearest to the host first
     * @param ?CommandId $checkCommandId the host's own check command
     */
    public function resolveLine(array $inheritanceLine, ?CommandId $checkCommandId): InheritedHostMacros
    {
        $commandIds = [];
        $checkCommandId ??= array_find(
            $inheritanceLine,
            static fn (HostTemplate $template): bool => $template->checkCommandId instanceof CommandId,
        )?->checkCommandId;
        if ($checkCommandId instanceof CommandId) {
            $commandIds[$checkCommandId->value] = $checkCommandId;
        }
        foreach ($inheritanceLine as $template) {
            foreach ($template->serviceTemplateCheckCommandIds as $commandId) {
                $commandIds[$commandId->value] ??= $commandId;
            }
        }

        return InheritedHostMacros::resolve($inheritanceLine, $this->loadCommands($commandIds));
    }

    /**
     * @param array<int, CommandId> $commandIds
     *
     * @return list<Command>
     */
    private function loadCommands(array $commandIds): array
    {
        $commands = [];
        foreach ($commandIds as $commandId) {
            try {
                $commands[] = $this->commandRepository->getById($commandId);
            } catch (CommandNotFoundException) {
                // A dangling command reference contributes no macro, as in legacy.
            }
        }

        return $commands;
    }
}
