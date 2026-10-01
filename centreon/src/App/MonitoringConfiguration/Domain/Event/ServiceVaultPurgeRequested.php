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

use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\Shared\Domain\Event\DeliveredAfterCommitInterface;
use App\Shared\Domain\Event\EventInterface;

/**
 * Deferred because a vault purge cannot be rolled back: it must only happen once the deletion is
 * committed.
 */
final readonly class ServiceVaultPurgeRequested implements DeliveredAfterCommitInterface, EventInterface
{
    public function __construct(
        public Service $service,
        public \DateTimeImmutable $firedAt = new \DateTimeImmutable(),
    ) {
    }

    public function firedAt(): \DateTimeImmutable
    {
        return $this->firedAt;
    }
}
