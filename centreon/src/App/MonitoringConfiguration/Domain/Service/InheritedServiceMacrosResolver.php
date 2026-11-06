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
use App\MonitoringConfiguration\Domain\Aggregate\Service\InheritedServiceMacros;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;

/**
 * Resolves the macros a service inherits, the way legacy CentreonService::getMacros() does:
 *
 * - the macros of its template chain, starting at its template, nearest first;
 * - then the service macros of its effective check command: its own check command or, when it has none, the
 *   check command of the first template of the chain that has one.
 */
final readonly class InheritedServiceMacrosResolver
{
    public function __construct(
        private ServiceTemplateRepository $serviceTemplateRepository,
        private CommandRepository $commandRepository,
    ) {
    }

    /**
     * @param ?ServiceTemplateId $templateId the service's template
     * @param ?CommandId $checkCommandId the service's own check command
     */
    public function resolve(?ServiceTemplateId $templateId, ?CommandId $checkCommandId): InheritedServiceMacros
    {
        /** @var list<ServiceTemplate> $inheritanceLine */
        $inheritanceLine = $templateId instanceof ServiceTemplateId
            ? array_values($this->serviceTemplateRepository->findInheritanceLine($templateId)->toArray())
            : [];

        return $this->resolveLine($inheritanceLine, $checkCommandId);
    }

    /**
     * Same resolution, from an inheritance line the caller already loaded
     * ({@see ServiceTemplateRepository::findInheritanceLine()}), so it is not fetched twice.
     *
     * @param list<ServiceTemplate> $inheritanceLine the template chain, nearest to the service first
     * @param ?CommandId $checkCommandId the service's own check command
     */
    public function resolveLine(array $inheritanceLine, ?CommandId $checkCommandId): InheritedServiceMacros
    {
        $checkCommandId ??= array_find(
            $inheritanceLine,
            static fn (ServiceTemplate $template): bool => $template->checkCommandId instanceof CommandId,
        )?->checkCommandId;

        return InheritedServiceMacros::resolve($inheritanceLine, $this->loadCommand($checkCommandId));
    }

    /**
     * @return list<Command> the command, or none when there is no command or a dangling reference
     */
    private function loadCommand(?CommandId $commandId): array
    {
        if (! $commandId instanceof CommandId) {
            return [];
        }

        try {
            return [$this->commandRepository->getById($commandId)];
        } catch (CommandNotFoundException) {
            // A dangling command reference contributes no macro, as in legacy.
            return [];
        }
    }
}
