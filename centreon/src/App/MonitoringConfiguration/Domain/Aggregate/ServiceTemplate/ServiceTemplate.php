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

namespace App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;
use Webmozart\Assert\Assert;

/**
 * Read partially: each finder hydrates what its callers need and leaves the other fields at their defaults.
 *
 * @extends AggregateRoot<ServiceTemplateId>
 */
final class ServiceTemplate extends AggregateRoot
{
    /** @var Collection<ServiceMacro> */
    public readonly Collection $macros;

    /** @var Collection<ServiceGroupId> */
    public readonly Collection $serviceGroupIds;

    /**
     * @param ?ServiceTemplateId $parentId the template it inherits from (`service_template_model_stm_id`)
     * @param ?Collection<ServiceMacro> $macros its own custom macros, in insertion order
     * @param ?string $alias the stored `service_alias`, raw: never trimmed nor validated, so that a caller
     *                       deriving a name from it can refuse it itself
     * @param ?Collection<ServiceGroupId> $serviceGroupIds the groups of its `servicegroup_relation` rows
     */
    public function __construct(
        ?ServiceTemplateId $id,
        public readonly ServiceTemplateName $name,
        public readonly ?ServiceTemplateId $parentId = null,
        public readonly ?CommandId $checkCommandId = null,
        public readonly ?CommandId $eventHandlerCommandId = null,
        ?Collection $macros = null,
        public readonly ?string $alias = null,
        ?Collection $serviceGroupIds = null,
    ) {
        parent::__construct($id);
        $this->macros = $macros ?? new Collection([], ServiceMacro::class);
        $this->serviceGroupIds = $serviceGroupIds ?? new Collection([], ServiceGroupId::class);
    }

    /**
     * The template's own macros, as a service inheriting from it sees them.
     *
     * @return list<ServiceMacro>
     */
    public function macrosAsInherited(): array
    {
        return array_values(array_map(
            static function (ServiceMacro $macro): ServiceMacro {
                Assert::false($macro->isInherited(), 'A service template only owns its own macros.');

                return $macro->inheritedFrom(ServiceMacroParentEnum::Template);
            },
            $this->macros->toArray(),
        ));
    }
}
