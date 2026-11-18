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
    /** Fleet token: waits for the central to create the poller and assign it. */
    public const STATE_PENDING = 'pending';

    /** Bound to its poller (node), which presents its own credential. */
    public const STATE_ENROLLED = 'enrolled';

    /** The poller is set up and got its first configuration. */
    public const STATE_COMPLETED = 'completed';

    /** The poller was deleted: its credential is refused. */
    public const STATE_REVOKED = 'revoked';

    /**
     * @param string $id
     * @param string $state
     * @param int|null $pollerId poller (Gorgone node) it is bound to, null while pending
     * @param string $name name the token gave or the machine reported (its host name)
     * @param string $address address the machine reported
     * @param string $agentVersion
     */
    public function __construct(
        public readonly string $id,
        public readonly string $state,
        public readonly ?int $pollerId,
        public readonly string $name,
        public readonly string $address,
        public readonly string $agentVersion,
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
}
