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
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-import-type RowTypeAlias from DbalServiceTemplateRepository
 *
 * @implements TransformerInterface<RowTypeAlias,ServiceTemplate>
 */
final readonly class ServiceTemplateTransformer implements TransformerInterface
{
    /**
     * A listing row carries the id and the name only; the parent, the commands and the macros are only on the
     * rows of {@see DbalServiceTemplateRepository::findInheritanceLine()}.
     *
     * @param RowTypeAlias $from
     */
    public function transform(mixed $from): ServiceTemplate
    {
        $parentId = $from['parent_id'] ?? null;
        $checkCommandId = $from['check_command_id'] ?? null;
        $eventHandlerCommandId = $from['event_handler_command_id'] ?? null;

        return new ServiceTemplate(
            id: new ServiceTemplateId((int) $from['service_id']),
            name: new ServiceTemplateName($from['service_description']),
            parentId: $parentId !== null ? new ServiceTemplateId((int) $parentId) : null,
            checkCommandId: $checkCommandId !== null ? new CommandId((int) $checkCommandId) : null,
            eventHandlerCommandId: $eventHandlerCommandId !== null ? new CommandId((int) $eventHandlerCommandId) : null,
            macros: new Collection(array_map(ServiceTransformer::createMacro(...), $from['macros'] ?? []), ServiceMacro::class),
        );
    }
}
