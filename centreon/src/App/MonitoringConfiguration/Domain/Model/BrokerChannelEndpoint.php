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

namespace App\MonitoringConfiguration\Domain\Model;

use Webmozart\Assert\Assert;

/**
 * MON-215450 phase 2g (contracts.md § 11.W3): the address a broker-channel poller dials to reach the central
 * broker (BBDO gRPC TLS). Stored in the poller's own engine module output.
 */
final readonly class BrokerChannelEndpoint
{
    /** A DNS name, an IPv4 or an IPv6 literal: no shell or JSON metacharacter can reach the command */
    private const HOST_PATTERN = '/^[A-Za-z0-9._:\-]{1,253}$/D';
    private const IPV4_PATTERN = '/^\d{1,3}(?:\.\d{1,3}){3}$/D';

    public function __construct(
        public string $host,
        public int $port,
    ) {
        Assert::regex($host, self::HOST_PATTERN, 'Invalid broker channel host "%s".');
        Assert::range($port, 1, 65535, 'Invalid broker channel port %s.');
    }

    /**
     * An IP literal cannot be the TLS name checked against the central certificate SAN by name.
     */
    public function isIpLiteral(): bool
    {
        return preg_match(self::IPV4_PATTERN, $this->host) === 1 || str_contains($this->host, ':');
    }
}
