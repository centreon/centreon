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

namespace App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Domain\Collection;

/**
 * All or none: the services of the copy are written in this command's own transaction, after the host copy was
 * committed, so a failure leaves the copy without any of the source's services.
 */
final readonly class DuplicateHostServicesCommand
{
    /**
     * @param Collection<ServiceTemplateId> $unlicensedServiceTemplateIds the service templates the license no longer
     *                                                                    allows, as evaluated before the host copy existed
     */
    public function __construct(
        public HostId $sourceHostId,
        public HostId $newHostId,
        public UserId $duplicatedBy,
        public Collection $unlicensedServiceTemplateIds,
    ) {
    }
}
