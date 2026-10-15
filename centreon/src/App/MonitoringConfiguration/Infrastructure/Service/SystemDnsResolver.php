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

namespace App\MonitoringConfiguration\Infrastructure\Service;

use App\MonitoringConfiguration\Domain\Model\ResolvableAddress;
use App\MonitoringConfiguration\Domain\Service\DnsResolver;

/**
 * Resolves through the operating system's resolver, so /etc/hosts entries count as legacy
 * resolveHostName.php did.
 */
final readonly class SystemDnsResolver implements DnsResolver
{
    public function findIpv4(ResolvableAddress $address): ?string
    {
        // gethostbyname() returns its input unchanged on failure, which is not an IPv4 for a hostname.
        $ipv4 = gethostbyname($address->value);

        return filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? $ipv4 : null;
    }
}
