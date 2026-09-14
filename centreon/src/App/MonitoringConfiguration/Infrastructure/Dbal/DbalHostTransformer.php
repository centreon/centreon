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

use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-import-type RowTypeAlias from DbalHostRepository
 *
 * @implements TransformerInterface<RowTypeAlias, Host>
 */
final readonly class DbalHostTransformer implements TransformerInterface
{
    public function transform(mixed $from): Host
    {
        $templateIds = [];
        if ($from['template_ids'] !== null && $from['template_ids'] !== '') {
            foreach (explode(',', $from['template_ids']) as $templateId) {
                $templateIds[] = new HostTemplateId((int) $templateId);
            }
        }

        $groupIds = [];
        if ($from['group_ids'] !== null && $from['group_ids'] !== '') {
            foreach (explode(',', $from['group_ids']) as $groupId) {
                $groupIds[] = new HostGroupId((int) $groupId);
            }
        }

        $alias = $from['alias'] !== null ? trim($from['alias']) : '';

        return new Host(
            id: new HostId((int) $from['id']),
            name: new HostName($from['name']),
            alias: $alias !== '' ? new HostAlias($alias) : null,
            address: new HostAddress($from['ip_address']),
            activated: $from['is_activated'] === '1',
            pollerId: new PollerId((int) $from['poller_id']),
            templateIds: new Collection($templateIds, HostTemplateId::class),
            hostGroupIds: new Collection($groupIds, HostGroupId::class),
        );
    }
}
