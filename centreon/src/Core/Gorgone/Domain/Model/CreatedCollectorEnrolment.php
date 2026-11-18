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
 * An enrolment just created: the only place its token appears. Nothing keeps the token, so it is
 * never shown again.
 */
final class CreatedCollectorEnrolment
{
    /**
     * @param string $id
     * @param string $token
     * @param bool $isFleet
     * @param int|null $pollerId poller created (or chosen) for a named enrolment
     * @param string|null $pollerName
     * @param int $expiresAt Unix time
     * @param int|null $maxUses
     * @param array<string, string> $environment variables the collector needs, and only these
     * @param string $command command to run on the collector
     * @param bool $approvalRequired each machine waits for an admin
     */
    public function __construct(
        public readonly string $id,
        public readonly string $token,
        public readonly bool $isFleet,
        public readonly ?int $pollerId,
        public readonly ?string $pollerName,
        public readonly int $expiresAt,
        public readonly ?int $maxUses,
        public readonly array $environment,
        public readonly string $command,
        public readonly bool $approvalRequired = false,
    ) {
    }
}
