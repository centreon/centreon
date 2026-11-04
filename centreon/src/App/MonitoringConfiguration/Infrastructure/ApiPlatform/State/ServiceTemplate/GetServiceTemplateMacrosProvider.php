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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\ServiceTemplate;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Exception\ServiceTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\MonitoringConfiguration\Domain\Service\InheritedServiceMacrosResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ServiceTemplate\ServiceTemplateMacrosOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceMacroTransformer;
use Webmozart\Assert\Assert;

/**
 * Returns a service template's macros to prefill a service before it is created: the template's own
 * macros (direct, parent null), then those it inherits from its ancestor templates and check command
 * that it does not override (parent "template" or "command"), resolved like GetService.
 *
 * No actor scoping, like the service template choices list (ListServiceTemplatesChoicesProvider). Like
 * that list, locked and disabled templates are found: legacy offers and inherits from them too. The
 * license host limit does not apply either: a service may already have a template the license no
 * longer allows.
 *
 * @implements ProviderInterface<ServiceTemplateMacrosOutput>
 */
final readonly class GetServiceTemplateMacrosProvider implements ProviderInterface
{
    public function __construct(
        private ServiceTemplateRepository $repository,
        private InheritedServiceMacrosResolver $inheritedServiceMacrosResolver,
        private ServiceMacroTransformer $macroTransformer,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ServiceTemplateMacrosOutput
    {
        Assert::integer($uriVariables['id']);
        $templateId = new ServiceTemplateId($uriVariables['id']);

        // The line starts with the template itself, followed by its ancestors; empty for a non-template.
        /** @var list<ServiceTemplate> $inheritanceLine */
        $inheritanceLine = array_values($this->repository->findInheritanceLine($templateId)->toArray());
        $template = $inheritanceLine[0] ?? null;
        if (! $template instanceof ServiceTemplate) {
            throw new ServiceTemplateNotFoundException($templateId->value, 'id');
        }

        /** @var list<ServiceMacro> $ownMacros */
        $ownMacros = array_values($template->macros->toArray());
        // The template's own check command falls back to the first ancestor's, as for a service.
        $inherited = $this->inheritedServiceMacrosResolver->resolveLine($inheritanceLine, null);

        return new ServiceTemplateMacrosOutput(
            id: $templateId->value,
            name: $template->name->value,
            macros: array_map($this->macroTransformer->transform(...), $inherited->effectiveWith($ownMacros)),
        );
    }
}
