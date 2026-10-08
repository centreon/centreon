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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostTemplateCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Service\InheritedHostMacrosResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateSkeletonOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostMacroTransformer;
use App\Shared\Domain\Collection;
use Webmozart\Assert\Assert;

/**
 * Returns a host template's macros for the host form, before the host exists: the template's own
 * macros (direct, parent null), then those it inherits from its ancestor templates and check
 * commands that it does not override (parent "template" or "command"), resolved like GetHost.
 *
 * Scoped like the host-form choices list (ListHostTemplatesChoicesProvider): no viewer scoping, and
 * locked (paid plugin-pack) templates are not found. An inactive template is not found either.
 *
 * @implements ProviderInterface<HostTemplateSkeletonOutput>
 */
final readonly class GetHostTemplateSkeletonProvider implements ProviderInterface
{
    public function __construct(
        private HostTemplateRepository $repository,
        private InheritedHostMacrosResolver $inheritedHostMacrosResolver,
        private HostMacroTransformer $macroTransformer,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): HostTemplateSkeletonOutput
    {
        Assert::integer($uriVariables['id']);
        $templateId = new HostTemplateId($uriVariables['id']);

        $criteria = (new HostTemplateCriteria())->withId($templateId)->withExcludeLocked(true);
        if (count($this->repository->findAll($criteria)) === 0) {
            throw new HostTemplateNotFoundException([$templateId->value], 'id');
        }

        // The line starts with the template itself (when active), followed by its ancestors.
        /** @var list<HostTemplate> $inheritanceLine */
        $inheritanceLine = array_values(
            $this->repository->findInheritanceLine(new Collection([$templateId], HostTemplateId::class))->toArray(),
        );
        $template = $inheritanceLine[0] ?? null;
        if (! $template instanceof HostTemplate || $template->id()->value !== $templateId->value) {
            throw new HostTemplateNotFoundException([$templateId->value], 'id');
        }

        /** @var list<HostMacro> $ownMacros */
        $ownMacros = array_values($template->macros->toArray());
        // The template's own check command falls back to the first ancestor's, as for a host.
        $inherited = $this->inheritedHostMacrosResolver->resolveLine($inheritanceLine, null);

        return new HostTemplateSkeletonOutput(
            id: $templateId->value,
            name: $template->name->value,
            macros: array_map(
                $this->macroTransformer->transform(...),
                [...$ownMacros, ...$inherited->notOverriddenBy($ownMacros)],
            ),
        );
    }
}
