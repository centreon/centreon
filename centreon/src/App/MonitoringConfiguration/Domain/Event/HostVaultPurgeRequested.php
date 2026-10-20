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

namespace App\MonitoringConfiguration\Domain\Event;

use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\Shared\Domain\Event\DeliveredAfterCommitInterface;
use App\Shared\Domain\Event\EventInterface;

/**
 * Deferred because a vault purge cannot be rolled back: it must only happen once the write that
 * makes it safe is committed.
 *
 * Fired for a deleted host, and for a host whose save released its vault entry (see
 * {@see Host::releasesVaultEntryOf()}) or only some of its secrets: $host is then the host as it was
 * before the save, the one still pointing to the entry.
 */
final readonly class HostVaultPurgeRequested implements DeliveredAfterCommitInterface, EventInterface
{
    /**
     * @param bool $bestEffort a failed purge is only logged instead of reported to the caller: set it
     *                         when the purge merely tidies up secrets left by a successful save,
     *                         an orphan secret being harmless
     * @param list<string> $keys the keys to remove from the host's vault entry; empty means the whole
     *                           entry
     */
    public function __construct(
        public Host $host,
        public \DateTimeImmutable $firedAt = new \DateTimeImmutable(),
        public bool $bestEffort = false,
        public array $keys = [],
    ) {
    }

    public function firedAt(): \DateTimeImmutable
    {
        return $this->firedAt;
    }
}
