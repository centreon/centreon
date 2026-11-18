<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\Gorgone\Domain\Model;

/**
 * An enrolment token as gorgone-server knows it: never the token itself, which nobody keeps.
 */
final class CollectorEnrolmentToken
{
    /**
     * @param string $id
     * @param int|null $pollerId poller a named token enrols, null for a fleet token
     * @param string|null $name
     * @param int $expiresAt Unix time in milliseconds
     * @param int|null $maxUses null for no limit until it expires
     * @param int $uses
     * @param int $createdAt Unix time in milliseconds
     * @param bool $approvalRequired its enrolments wait for an admin
     * @param int|null $lastUsedAt Unix time in milliseconds of its last enrolment
     */
    public function __construct(
        public readonly string $id,
        public readonly ?int $pollerId,
        public readonly ?string $name,
        public readonly int $expiresAt,
        public readonly ?int $maxUses,
        public readonly int $uses,
        public readonly int $createdAt,
        public readonly bool $approvalRequired,
        public readonly ?int $lastUsedAt,
    ) {
    }

    public function isFleet(): bool
    {
        return $this->pollerId === null;
    }

    /**
     * @param int $nowMs
     *
     * @return bool true when it enrols nothing more: expired or used up
     */
    public function isSpent(int $nowMs): bool
    {
        return $this->expiresAt <= $nowMs || ($this->maxUses !== null && $this->uses >= $this->maxUses);
    }
}
