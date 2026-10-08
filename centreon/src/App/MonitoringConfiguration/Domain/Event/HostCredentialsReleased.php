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
 * Deferred because a vault deletion cannot be rolled back: it must only happen once the update that
 * stopped using the secrets is committed.
 *
 * $host is the host as it was before the update, the one still pointing to its vault entry.
 */
final readonly class HostCredentialsReleased implements DeliveredAfterCommitInterface, EventInterface
{
    /**
     * @param list<string> $keys the keys to remove from the host's vault entry; empty means the whole
     *                           entry, because the host keeps no secret in it
     */
    public function __construct(
        public Host $host,
        public array $keys,
        public \DateTimeImmutable $firedAt = new \DateTimeImmutable(),
    ) {
    }

    public function firedAt(): \DateTimeImmutable
    {
        return $this->firedAt;
    }
}
