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

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneName;
use App\MonitoringConfiguration\Domain\Exception\HostSeverityNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\MediaNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\PollerNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimePeriodNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimezoneNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Domain\Collection;

/**
 * The authoritative check of the resources a host write refers to, run by the command handler after
 * the same rules ran, earlier, on the input. It catches what changed in between.
 *
 * A resource the viewer cannot access is reported like one that does not exist, so a restricted
 * viewer cannot probe for what they cannot see. A null $viewerId means the requester is unrestricted.
 */
final readonly class HostReferencesChecker
{
    public function __construct(
        private PollerRepository $pollerRepository,
        private HostSeverityRepository $hostSeverityRepository,
        private TimezoneRepository $timezoneRepository,
        private TimePeriodRepository $timePeriodRepository,
        private MediaRepository $mediaRepository,
        private CommandRepository $commandRepository,
        private ResourceAccessRepository $resourceAccessRepository,
    ) {
    }

    public function assertPollerAccessible(PollerId $pollerId, ?UserId $viewerId): void
    {
        $this->pollerRepository->get($pollerId);

        if ($viewerId instanceof UserId && ! $this->resourceAccessRepository->hasAccessToPoller($pollerId, $viewerId)) {
            throw new PollerNotFoundException(['id' => $pollerId->value]);
        }
    }

    public function assertSeverityAccessible(HostSeverityId $severityId, ?UserId $viewerId): void
    {
        if (! $this->hostSeverityRepository->findNameById($severityId) instanceof HostSeverityName) {
            throw new HostSeverityNotFoundException($severityId->value);
        }

        if (! $viewerId instanceof UserId) {
            return;
        }

        $accessibleIds = $this->resourceAccessRepository->findAccessibleHostSeverityIds($viewerId);
        if (
            $accessibleIds instanceof Collection
            && ! array_any(
                $accessibleIds->toArray(),
                static fn (HostSeverityId $accessibleId): bool => $accessibleId->value === $severityId->value,
            )
        ) {
            throw new HostSeverityNotFoundException($severityId->value);
        }
    }

    public function assertTimezoneExists(TimezoneId $timezoneId): void
    {
        if (! $this->timezoneRepository->findNameById($timezoneId) instanceof TimezoneName) {
            throw new TimezoneNotFoundException($timezoneId->value);
        }
    }

    public function assertTimePeriodExists(TimePeriodId $timePeriodId): void
    {
        if (! $this->timePeriodRepository->existsOne($timePeriodId)) {
            throw new TimePeriodNotFoundException($timePeriodId->value);
        }
    }

    public function assertMediaExists(MediaId $mediaId): void
    {
        if (! $this->mediaRepository->existsOne($mediaId)) {
            throw new MediaNotFoundException($mediaId->value);
        }
    }

    /**
     * Throws CommandNotFoundException when the command does not exist.
     */
    public function assertCommandExists(CommandId $commandId): void
    {
        $this->commandRepository->getById($commandId);
    }
}
