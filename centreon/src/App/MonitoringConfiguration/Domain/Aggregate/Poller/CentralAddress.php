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
    private const DOT_SEGMENT_PATTERN = '~(?:^|/)\.{1,2}(?:/|$)~';

    /**
     * The admin input, verbatim minus surrounding whitespace and trailing slash: what gets
     * persisted, echoed back by the API and quoted in error messages. Not a URL authority —
     * use {@see $urlValue} for that.
     */
    public string $value;

    /** Authority host with the port stripped: hostname, IPv4, or unbracketed IPv6. */
    public string $host;

    /** Base path without surrounding slashes ("platform", "base/path"), null when the address has none. */
    public ?string $basePath;

    /**
     * The address as it must appear right after "scheme://": web port and base path kept, an IPv6
     * host bracketed.
     *
     * Assembled from the validated parts rather than reusing {@see $value}: a bare IPv6 literal
     * there yields an authority curl cannot parse.
     */
    public string $urlValue;

    public function __construct(string $value)
    {
        $normalized = rtrim(trim($value), '/');
        Assert::lengthBetween($normalized, self::MIN_LENGTH, self::MAX_LENGTH);

        $slashPosition = mb_strpos($normalized, '/');
        $authority = $slashPosition === false ? $normalized : mb_substr($normalized, 0, $slashPosition);
        $basePath = $slashPosition === false ? null : mb_substr($normalized, $slashPosition + 1);

        [$host, $port] = $this->parseAuthority($authority);
        if ($basePath !== null) {
            Assert::regex(
                $basePath,
                self::BASE_PATH_PATTERN,
                sprintf('[CentralAddress::value] The base path "%s" contains invalid characters', $basePath)
            );
            // "." and ".." match BASE_PATH_PATTERN but would make the generated URL
            // resolve outside the configured base path.
            Assert::false(
                (bool) preg_match(self::DOT_SEGMENT_PATTERN, $basePath),
                sprintf('[CentralAddress::value] The base path "%s" must not contain dot segments', $basePath)
            );
        }

        $this->host = $host;
        $this->basePath = $basePath;
        $this->value = $normalized;
        $this->urlValue = $this->buildUrlValue($host, $port, $basePath);
    }

    /**
     * Validate the authority and split it into host + optional port.
     *
     * The host is kept rather than discarded because consumers that reach the central over a
     * protocol other than HTTP (the broker output, which dials its own port) need it bare.
     * They cannot re-derive it with parse_url(): on a scheme-less value it reports no host at all
     * unless a port happens to be present. The port is what {@see $urlValue} puts back.
     *
     * @return array{0: string, 1: int|null}
     */
    private function parseAuthority(string $authority): array
    {
        // A bare IP address (IPv4, or IPv6 whose colons would confuse port detection).
        if (filter_var($authority, FILTER_VALIDATE_IP) !== false) {
            return [$authority, null];
        }

        if (preg_match(self::HOST_PORT_PATTERN, $authority, $matches) === 1) {
            $port = (int) $matches['port'];
            Assert::range(
                $port,
                1,
                65535,
                sprintf('[CentralAddress::value] The port in "%s" is out of range', $authority)
            );
            CentreonAssert::ipOrHostname($matches['host'], 'CentralAddress::value');

            return [$matches['host'], $port];
        }

        CentreonAssert::ipOrHostname($authority, 'CentralAddress::value');

        return [$authority, null];
    }

    private function buildUrlValue(string $host, ?int $port, ?string $basePath): string
    {
        // An IPv6 literal only stands as a URL authority once bracketed: without brackets its
        // colons are read as the port separator.
        $authority = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            ? sprintf('[%s]', $host)
            : $host;

        return $authority
            . ($port === null ? '' : ':' . $port)
            . ($basePath === null ? '' : '/' . $basePath);
    }
}
