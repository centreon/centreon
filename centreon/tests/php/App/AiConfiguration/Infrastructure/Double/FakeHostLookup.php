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

namespace Tests\App\AiConfiguration\Infrastructure\Double;

use App\AiConfiguration\Infrastructure\Llm\HostLookup;

final readonly class FakeHostLookup implements HostLookup
{
    /**
     * @param array<string, string> $ipv4Addresses
     * @param list<string> $ipv6Hosts
     */
    public function __construct(
        private array $ipv4Addresses = [],
        private array $ipv6Hosts = [],
    ) {
    }

    public function findIpv4Address(string $host): ?string
    {
        return $this->ipv4Addresses[$host] ?? null;
    }

    public function hasIpv6Address(string $host): bool
    {
        return \in_array($host, $this->ipv6Hosts, true);
    }
}
