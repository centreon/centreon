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

namespace App\MonitoringConfiguration\Domain\Model\ServiceListing;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use Webmozart\Assert\Assert;

/**
 * One row of the service listing: a service as seen through one of the hosts it is directly linked to, so a
 * service linked to several hosts gives one item per host. A read model, never written back.
 *
 * Names are kept as stored (no trim, no decoding). The icon and the check intervals are the effective ones,
 * inherited from the service template chain when the service has none of its own; the host icon is the
 * host's own one only, its inheritance being resolved by the caller.
 */
final readonly class ServiceListItem
{
    public function __construct(
        public ServiceId $serviceId,
        public string $name,
        public bool $activated,
        public HostId $hostId,
        public string $hostName,
        public ?MediaId $hostIconId = null,
        public ?MediaId $iconId = null,
        public ?int $normalCheckInterval = null,
        public ?int $retryCheckInterval = null,
        public ?int $templateId = null,
        public ?string $templateName = null,
    ) {
        Assert::same($templateId === null, $templateName === null, 'A template carries both its id and its name, or neither.');
        if ($templateId !== null) {
            Assert::positiveInteger($templateId);
        }
    }
}
