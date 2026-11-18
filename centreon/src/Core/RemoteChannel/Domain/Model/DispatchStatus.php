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

namespace Core\RemoteChannel\Domain\Model;

/**
 * Status of a command or a deployment reported by the central broker remote API (contracts.md § 1).
 */
enum DispatchStatus: string
{
    case Accepted = 'ACCEPTED';
    case Transferring = 'TRANSFERRING';
    case NotConnected = 'NOT_CONNECTED';
    case PeerTooOld = 'PEER_TOO_OLD';
    case Delivered = 'DELIVERED';
    case Applied = 'APPLIED';
    case Rejected = 'REJECTED';
    case Timeout = 'TIMEOUT';

    /**
     * Statuses after which nothing more happens for the request.
     */
    public function isFinal(): bool
    {
        return in_array(
            $this,
            [self::NotConnected, self::PeerTooOld, self::Applied, self::Rejected, self::Timeout],
            true
        );
    }

    public function isFailure(): bool
    {
        return in_array($this, [self::NotConnected, self::PeerTooOld, self::Rejected, self::Timeout], true);
    }

    /**
     * HTTP status answered by the Centreon API when a request ends with this status.
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::NotConnected => 503,
            self::PeerTooOld => 409,
            self::Rejected => 422,
            self::Timeout => 504,
            default => 500,
        };
    }
}
