<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Resources\Application\UseCase\FindResources\Response;

final class ResourceResponseDto
{
    /**
     * @param int|null $id
     * @param string|null $name
     * @param string|null $type
     * @param int|null $serviceId
     * @param int|null $hostId
     * @param string|null $alias
     * @param string|null $fqdn
     * @param IconResponseDto|null $icon
     * @param ResourceStatusResponseDto|null $status
     * @param bool $isInDowntime
     * @param bool $isAcknowledged
     * @param bool $withActiveChecks
     * @param bool $withPassiveChecks
     * @param \DateTimeImmutable|null $lastStatusChange
     * @param string|null $tries
     * @param string|null $information
     * @param bool $areNotificationsEnabled
     * @param string $monitoringServerName
     * @param SeverityResponseDto|null $severity
     * @param string|null $shortType
     * @param string|null $uuid
     * @param ParentResourceResponseDto|null $parent
     * @param \DateTimeImmutable|null $lastCheck
     * @param string|null $actionUrl
     * @param NotesResponseDto|null $notes
     * @param bool $hasGraphData
     */
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?string $type = null,
        public ?int $serviceId = null,
        public ?int $hostId = null,
        public ?string $alias = null,
        public ?string $fqdn = null,
        public ?IconResponseDto $icon = null,
        public ?ResourceStatusResponseDto $status = null,
        public bool $isInDowntime = false,
        public bool $isAcknowledged = false,
        public bool $withActiveChecks = false,
        public bool $withPassiveChecks = false,
        public ?\DateTimeImmutable $lastStatusChange = null,
        public ?string $tries = null,
        public ?string $information = null,
        public bool $areNotificationsEnabled = false,
        public string $monitoringServerName = '',
        public ?SeverityResponseDto $severity = null,
        public ?string $shortType = null,
        public ?string $uuid = null,
        public ?ParentResourceResponseDto $parent = null,
        public ?\DateTimeImmutable $lastCheck = null,
        public ?string $actionUrl = null,
        public ?NotesResponseDto $notes = null,
        public bool $hasGraphData = false,
    ) {
    }
}
