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

use App\MonitoringConfiguration\Domain\Aggregate\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * A listing row only carries the name, alias, lock, icon and parents.
 *
 * @phpstan-import-type RowTypeAlias from DbalHostTemplateRepository
 *
 * @implements TransformerInterface<RowTypeAlias, HostTemplate>
 */
final readonly class HostTemplateListingTransformer implements TransformerInterface
{
    /**
     * @param RowTypeAlias $from
     */
    public function transform(mixed $from): HostTemplate
    {
        $alias = $from['host_alias'] !== null && trim($from['host_alias']) !== '' ? $from['host_alias'] : null;

        return new HostTemplate(
            id: new HostTemplateId($from['host_id']),
            name: new HostTemplateName($from['host_name']),
            alias: $alias !== null ? new HostAlias($alias) : null,
            isLocked: (bool) (int) ($from['host_locked'] ?? 0),
            parentTemplateIds: new Collection(
                array_map(static fn (int $id): HostTemplateId => new HostTemplateId($id), $from['parent_template_ids']),
                HostTemplateId::class,
            ),
            extendedInformations: $from['icon_id'] !== null ? new ExtendedInformations(iconId: new MediaId((int) $from['icon_id'])) : null,
        );
    }
}
