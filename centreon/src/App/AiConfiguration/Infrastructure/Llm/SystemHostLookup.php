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

namespace App\AiConfiguration\Infrastructure\Llm;

final readonly class SystemHostLookup implements HostLookup
{
    public function findIpv4Address(string $host): ?string
    {
        $addresses = gethostbynamel($host);

        return $addresses === false ? null : $addresses[0];
    }

    public function hasIpv6Address(string $host): bool
    {
        return checkdnsrr($host, 'AAAA');
    }
}
