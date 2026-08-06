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

namespace App\MonitoringConfiguration\Domain\Aggregate\Poller;

use App\Shared\Domain\Assert\Assert as CentreonAssert;
use Webmozart\Assert\Assert;

/**
 * Client-visible entry point of the central server: hostname or IP address,
 * optional port, optional base path — e.g. "central.example.com",
 * "10.0.0.1:8443" or "orga.region.example.com/platform". Unlike PollerAddress,
 * it may carry a base path: on cloud platforms the whole application
 * (including /poller/install.sh) is served under the platform path. No scheme.
 *
 * Behind proxies and NAT, only the browser knows the client-reachable form of
 * this address, so the value is provided by the frontend and stored verbatim
 * (minus trailing slash).
 */
final readonly class CentralAddress
{
    public const MIN_LENGTH = 1;
    public const MAX_LENGTH = 255;
    private const HOST_PORT_PATTERN = '/^(?<host>.+):(?<port>\d{1,5})$/';
    private const BASE_PATH_PATTERN = '~^[A-Za-z0-9._\-]+(?:/[A-Za-z0-9._\-]+)*$~';

    public string $value;

    public function __construct(string $value)
    {
        $normalized = rtrim(trim($value), '/');
        Assert::lengthBetween($normalized, self::MIN_LENGTH, self::MAX_LENGTH);

        $slashPosition = mb_strpos($normalized, '/');
        $authority = $slashPosition === false ? $normalized : mb_substr($normalized, 0, $slashPosition);
        $basePath = $slashPosition === false ? null : mb_substr($normalized, $slashPosition + 1);

        $this->assertAuthority($authority);
        if ($basePath !== null) {
            Assert::regex(
                $basePath,
                self::BASE_PATH_PATTERN,
                sprintf('[CentralAddress::value] The base path "%s" contains invalid characters', $basePath)
            );
        }

        $this->value = $normalized;
    }

    private function assertAuthority(string $authority): void
    {
        // A bare IP address (IPv4, or IPv6 whose colons would confuse port detection).
        if (filter_var($authority, FILTER_VALIDATE_IP) !== false) {
            return;
        }

        if (preg_match(self::HOST_PORT_PATTERN, $authority, $matches) === 1) {
            Assert::range(
                (int) $matches['port'],
                1,
                65535,
                sprintf('[CentralAddress::value] The port in "%s" is out of range', $authority)
            );
            CentreonAssert::ipOrHostname($matches['host'], 'CentralAddress::value');

            return;
        }

        CentreonAssert::ipOrHostname($authority, 'CentralAddress::value');
    }
}
