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

namespace App\Shared\Domain\Event;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\AggregateRootId;

abstract readonly class AggregateUpdated implements EventInterface
{
    /**
     * @param AggregateRoot<AggregateRootId> $aggregate
     * @param bool $loggable whether this update should produce an activity-log entry. Its side
     *                       effects (engine flag, ACL reload) run regardless — set it to false to
     *                       signal a change that must trigger those effects without being logged
     *                       (e.g. a change to a property legacy never logged).
     */
    public function __construct(
        public AggregateRoot $aggregate,
        public int $creatorId,
        public \DateTimeImmutable $firedAt = new \DateTimeImmutable(),
        public bool $loggable = true,
    ) {
    }

    public function firedAt(): \DateTimeImmutable
    {
        return $this->firedAt;
    }
}
