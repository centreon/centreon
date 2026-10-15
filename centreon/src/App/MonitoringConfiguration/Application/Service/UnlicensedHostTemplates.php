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

namespace App\MonitoringConfiguration\Application\Service;

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\Shared\Domain\Collection;

/**
 * Legacy CentreonHost::getLimitedList(), which hides these templates from the host form.
 */
final readonly class UnlicensedHostTemplates
{
    /** Monitoring Connectors free of license, whose templates stay usable once the limit is reached. */
    public const FREE_CONNECTOR_SLUGS = [
        'applications-databases-mysql',
        'applications-monitoring-centreon-central',
        'applications-monitoring-centreon-database',
        'applications-monitoring-centreon-poller',
        'base-generic',
        'hardware-printers-standard-rfc3805-snmp',
        'hardware-ups-standard-rfc1628-snmp',
        'network-cisco-standard-snmp',
        'operatingsystems-linux-snmp',
        'operatingsystems-windows-snmp',
    ];

    public function __construct(
        private HostLicenseLimit $hostLicenseLimit,
        private HostTemplateRepository $hostTemplateRepository,
    ) {
    }

    /**
     * The host templates a host can no longer be given because the platform's license host limit is
     * reached: the templates of the paid Monitoring Connectors and every template inheriting from
     * them. Empty when the limit is not reached.
     *
     * @return Collection<HostTemplateId>
     */
    public function findIds(): Collection
    {
        if (! $this->hostLicenseLimit->isReached()) {
            return new Collection([], HostTemplateId::class);
        }

        return $this->hostTemplateRepository->findMonitoringConnectorTemplateIds(self::FREE_CONNECTOR_SLUGS);
    }
}
