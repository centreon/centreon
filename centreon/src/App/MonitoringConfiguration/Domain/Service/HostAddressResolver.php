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

namespace App\MonitoringConfiguration\Domain\Service;

use App\MonitoringConfiguration\Domain\Model\HostAddressResolution;
use App\MonitoringConfiguration\Domain\Model\ResolvableAddress;

/**
 * Resolves an address to an IPv4, for the host form's "Resolve" button (legacy resolveHostName.php).
 * An IPv4 is already resolved, so only a hostname reaches the DNS.
 */
final readonly class HostAddressResolver
{
    public function __construct(
        private DnsResolver $dnsResolver,
    ) {
    }

    public function resolve(ResolvableAddress $address): HostAddressResolution
    {
        if (filter_var($address->value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return new HostAddressResolution($address, $address->value);
        }

        return new HostAddressResolution($address, $this->dnsResolver->findIpv4($address));
    }
}
