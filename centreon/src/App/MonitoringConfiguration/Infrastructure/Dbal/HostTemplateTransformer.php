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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-import-type RowTypeAlias from DbalHostTemplateRepository
 *
 * @implements TransformerInterface<RowTypeAlias,HostTemplate>
 */
final readonly class HostTemplateTransformer implements TransformerInterface
{
    /**
     * @param RowTypeAlias $from
     */
    public function transform(mixed $from): HostTemplate
    {
        // legacy stores a missing alias as NULL or as an empty string, both read as no alias
        $alias = $from['host_alias'] !== null && trim($from['host_alias']) !== '' ? new HostAlias($from['host_alias']) : null;

        return new HostTemplate(
            id: new HostTemplateId($from['host_id']),
            name: new HostTemplateName($from['host_name']),
            alias: $alias,
            isLocked: (bool) (int) ($from['host_locked'] ?? 0),
            iconId: $from['icon_id'] !== null ? new MediaId((int) $from['icon_id']) : null,
            parentTemplateIds: new Collection(
                array_map(static fn (int $id): HostTemplateId => new HostTemplateId($id), $from['parent_template_ids']),
                HostTemplateId::class,
            ),
        );
    }
}
