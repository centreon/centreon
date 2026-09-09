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

use App\MonitoringConfiguration\Domain\Model\UrlPath;
use App\Shared\Domain\Assert\Assert as CentreonAssert;
use Webmozart\Assert\Assert;

/**
 * Client-visible entry point of the central server: hostname or IP address,
 * optional port, optional base path — e.g. "central.example.com",
 * "10.0.0.1:8443", "[2001:db8::1]:8443" or "orga.region.example.com/platform".
 * Unlike PollerAddress, it may carry a base path: on cloud platforms the whole
 * application (including /poller/install.sh) is served under the platform path.
 * No scheme.
 *
 * Behind proxies and NAT, only the browser knows the client-reachable form of
 * this address, so the value is provided by the frontend, then normalized.
 */
final readonly class CentralAddress
{
    public const MIN_LENGTH = 1;
    public const MAX_LENGTH = 255;

    /**
     * An IP-literal, the only form able to carry both an IPv6 host and a web port: the brackets
     * are what tell the colons of the address from the port separator. Dots belong to the alphabet
     * for the IPv4-mapped form ("::ffff:10.0.0.1"), and that alphabet is all a pattern can express
     * — "[::::]" and "[10.0.0.1]" match it — so the capture goes through FILTER_VALIDATE_IP, which
     * is what actually rejects a non-IPv6 between brackets.
     */
    private const IP_LITERAL_PATTERN = '/^\[(?<host>[0-9A-Fa-f:.]+)\](?::(?<port>\d{1,5}))?$/';
    private const HOST_PORT_PATTERN = '/^(?<host>.+):(?<port>\d{1,5})$/';
    private const MIN_PORT = 1;
    private const MAX_PORT = 65535;

    /**
     * The canonical form of the address: what gets persisted, echoed back by the API and quoted in
     * error messages. Also what must appear right after "scheme://", so an IPv6 host is bracketed
     * here even when the admin typed it bare — a bare literal yields an authority curl cannot parse.
     */
    public string $value;

    /** Authority host, port and brackets stripped: hostname, IPv4, or bare IPv6. */
    public string $host;

    /** Base path without surrounding slashes ("platform", "base/path"), null when the address has none. */
    public ?string $basePath;

    public function __construct(string $value)
    {
        $normalized = rtrim(trim($value), '/');
        Assert::lengthBetween($normalized, self::MIN_LENGTH, self::MAX_LENGTH);

        $slashPosition = mb_strpos($normalized, '/');
        $authority = $slashPosition === false ? $normalized : mb_substr($normalized, 0, $slashPosition);

        [$host, $port] = $this->parseAuthority($authority);

        // The separator is kept rather than skipped: UrlPath takes the canonical leading-slash
        // form, so "host//path" reaches it as "//path" and is rejected as an empty segment.
        $basePath = $slashPosition === false
            ? null
            : new UrlPath(mb_substr($normalized, $slashPosition), 'CentralAddress::value');

        $canonical = $this->canonicalize($host, $port, $basePath);
        // Bracketing a bare IPv6 host grows the value, and it is the canonical form that has to fit
        // platform_topology.central_address.
        Assert::lengthBetween($canonical, self::MIN_LENGTH, self::MAX_LENGTH);

        $this->host = $host;
        $this->basePath = $basePath?->segments();
        $this->value = $canonical;
    }

    /**
     * Validate the authority and split it into host + optional port.
     *
     * The host is kept rather than discarded because consumers that reach the central over a
     * protocol other than HTTP (the broker output, which dials its own port) need it bare.
     * They cannot re-derive it with parse_url(): on a scheme-less value it reports no host at all
     * unless a port happens to be present. The port is what {@see canonicalize()} puts back.
     *
     * @return array{0: string, 1: int|null}
     */
    private function parseAuthority(string $authority): array
    {
        if (preg_match(self::IP_LITERAL_PATTERN, $authority, $matches, PREG_UNMATCHED_AS_NULL) === 1) {
            Assert::true(
                filter_var($matches['host'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
                sprintf('[CentralAddress::value] "%s" is not a valid IPv6 address', $matches['host'])
            );

            return [$matches['host'], $this->parsePort($matches['port'])];
        }

        // A bare IP address (IPv4, or IPv6 whose colons would confuse port detection). An IPv6
        // typed this way carries no port — its last group is indistinguishable from one — and
        // canonicalize() brackets it back.
        if (filter_var($authority, FILTER_VALIDATE_IP) !== false) {
            return [$authority, null];
        }

        if (preg_match(self::HOST_PORT_PATTERN, $authority, $matches) === 1) {
            $port = $this->parsePort($matches['port']);
            CentreonAssert::ipOrHostname($matches['host'], 'CentralAddress::value');

            return [$matches['host'], $port];
        }

        CentreonAssert::ipOrHostname($authority, 'CentralAddress::value');

        return [$authority, null];
    }

    /**
     * @param string|null $port digits captured by the authority patterns, null when there is no port
     */
    private function parsePort(?string $port): ?int
    {
        if ($port === null) {
            return null;
        }

        $parsed = (int) $port;
        // The capture is interpolated rather than the whole authority: it holds nothing but digits,
        // so the message cannot carry a "%" the assertion library re-expands.
        Assert::range(
            $parsed,
            self::MIN_PORT,
            self::MAX_PORT,
            sprintf('[CentralAddress::value] The port "%s" is out of range', $port)
        );

        return $parsed;
    }

    private function canonicalize(string $host, ?int $port, ?UrlPath $basePath): string
    {
        // An IPv6 literal only stands as a URL authority once bracketed: without brackets its
        // colons are read as the port separator.
        $authority = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            ? sprintf('[%s]', $host)
            : $host;

        return $authority
            . ($port === null ? '' : ':' . $port)
            . ($basePath->value ?? '');
    }
}
