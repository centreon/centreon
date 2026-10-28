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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Model\ServiceListing\ServiceListItem;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-type ServiceListItemRowTypeAlias array{
 *   service_id: int|string,
 *   name: string,
 *   activated: string,
 *   host_id: int|string,
 *   host_name: string,
 *   host_icon_id: int|string|null,
 *   template_id: int|string|null,
 *   template_name: string|null,
 *   icon_id: int|null,
 *   normal_check_interval: int|null,
 *   retry_check_interval: int|null,
 * }
 *
 * @implements TransformerInterface<ServiceListItemRowTypeAlias, ServiceListItem>
 */
final readonly class ServiceListItemTransformer implements TransformerInterface
{
    public function transform(mixed $from): ServiceListItem
    {
        return new ServiceListItem(
            serviceId: new ServiceId((int) $from['service_id']),
            name: $from['name'],
            activated: $from['activated'] === '1',
            hostId: new HostId((int) $from['host_id']),
            hostName: $from['host_name'],
            hostIconId: $from['host_icon_id'] !== null ? new MediaId((int) $from['host_icon_id']) : null,
            iconId: $from['icon_id'] !== null ? new MediaId($from['icon_id']) : null,
            normalCheckInterval: $from['normal_check_interval'],
            retryCheckInterval: $from['retry_check_interval'],
            templateId: $from['template_id'] !== null ? (int) $from['template_id'] : null,
            templateName: $from['template_name'],
        );
    }
}
