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
 * Enrolment of a collector, as gorgone-server reports it: a machine presented an enrolment token
 * (never stored, only its hash) and the credential it will authenticate with.
 */
final class CollectorEnrolment
{
    /** Fleet token: waits for the central to create the poller (and for an admin when the token says so). */
    public const STATE_PENDING = 'pending';

    /** Fleet token with approval: an admin accepted it, the cron creates its poller. */
    public const STATE_APPROVED = 'approved';

    /** Bound to its poller (node), which presents its own credential. */
    public const STATE_ENROLLED = 'enrolled';

    /** The poller is set up and got its first configuration. */
    public const STATE_COMPLETED = 'completed';

    /** Refused by an admin: the collector is told so and gets no poller. */
    public const STATE_REJECTED = 'rejected';

    /** The poller was deleted: its credential is refused. */
    public const STATE_REVOKED = 'revoked';

    /**
     * @param string $id
     * @param string $state
     * @param int|null $pollerId poller (Gorgone node) it is bound to, null while pending
     * @param string $name name the token gave or the machine reported (its host name)
     * @param string $address address the machine reported
     * @param string $agentVersion
     * @param string $tokenId token it was made with
     * @param bool $isFleet made with a fleet token (the poller is created by the cron)
     * @param bool $approvalRequired waits for an admin before the cron creates its poller
     * @param int $createdAt Unix time in milliseconds
     * @param int $updatedAt Unix time in milliseconds
     */
    public function __construct(
        public readonly string $id,
        public readonly string $state,
        public readonly ?int $pollerId,
        public readonly string $name,
        public readonly string $address,
        public readonly string $agentVersion,
        public readonly string $tokenId = '',
        public readonly bool $isFleet = true,
        public readonly bool $approvalRequired = false,
        public readonly int $createdAt = 0,
        public readonly int $updatedAt = 0,
    ) {
    }

    /**
     * @return bool true when it gave its poller a credential that is still valid
     */
    public function isBound(): bool
    {
        return $this->pollerId !== null
            && in_array($this->state, [self::STATE_ENROLLED, self::STATE_COMPLETED], true);
    }

    /**
     * @return bool true when the cron may create its poller: approved, or pending without approval
     */
    public function isAssignable(): bool
    {
        return $this->state === self::STATE_APPROVED
            || ($this->state === self::STATE_PENDING && ! $this->approvalRequired);
    }

    /**
     * @return bool true when an admin has to approve or reject it
     */
    public function awaitsApproval(): bool
    {
        return $this->state === self::STATE_PENDING && $this->approvalRequired;
    }
}
