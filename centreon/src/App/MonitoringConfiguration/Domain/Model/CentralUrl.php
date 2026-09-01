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
 * Absolute HTTP(S) URL of the central web entry point, platform base URI included — e.g.
 * "https://central.example.com", "http://10.0.0.1:8443/centreon" or "https://[2001:db8::1]/platform".
 *
 * The type exists so the guarantee travels with the value instead of being re-asserted by every
 * consumer: this URL is interpolated unquoted, twice, into the `curl … | bash` one-liner an admin
 * runs as root to install a poller. A scheme-less value there would download install.sh over plain
 * HTTP, and anything carrying shell metacharacters would run as part of the command.
 *
 * Not a poller property: it is derived per request from a CentralAddress plus the current platform
 * context (see CentralUrlFactory, the only intended producer), never persisted.
 */
final readonly class CentralUrl
{
    private const MIN_PORT = 1;
    private const MAX_PORT = 65535;

    /** Hostname or IPv4 — indistinguishable here: both are letters, digits, dots and hyphens. */
    private const HOST = '[A-Za-z0-9._\-]+';

    /**
     * IPv6 needs a branch of its own: its brackets and colons fall outside HOST. The alphabet is
     * all a pattern can express here — "[::::]" matches it — so the capture is handed to
     * FILTER_VALIDATE_IP below.
     */
    private const BRACKETED_IPV6 = '\[(?<ipv6>[0-9A-Fa-f:]+)\]';

    /** Web port, never a protocol port such as the broker's. Digit count only; the range is checked below. */
    private const PORT = '(?::(?<port>\d{1,5}))?';

    /** The slash belongs to each segment, so an empty segment ("//") cannot pass. */
    private const PATH = '(?:/[A-Za-z0-9._\-]+)*';

    /**
     * Scheme, then authority with an optional web port, then path segments.
     *
     * An allowlist rather than an escape, and deliberately narrower than FILTER_VALIDATE_URL, which
     * accepts `$(id)` and backticks because they are legal RFC 3986 sub-delims. This is what makes
     * "safe to interpolate unquoted" checkable in a single place.
     */
    private const PATTERN = '~^https?://(?:' . self::HOST . '|' . self::BRACKETED_IPV6 . ')'
        . self::PORT . self::PATH . '$~';

    /** Rejected for the same reason as in CentralAddress: they resolve outside the base path. */
    private const DOT_SEGMENT_PATTERN = '~(?:^|/)\.{1,2}(?:/|$)~';

    public string $value;

    public function __construct(string $value)
    {
        $normalized = rtrim(trim($value), '/');
        // PREG_UNMATCHED_AS_NULL, or the branch that did not participate would come back as an
        // empty string whenever a later group did — "" is not an absent capture.
        Assert::true(
            preg_match(self::PATTERN, $normalized, $matches, PREG_UNMATCHED_AS_NULL) === 1,
            sprintf('[CentralUrl::value] "%s" is not an absolute HTTP(S) URL made of safe segments', $value)
        );
        // "." and ".." match the path part of PATTERN, so they need their own check.
        Assert::false(
            (bool) preg_match(self::DOT_SEGMENT_PATTERN, $normalized),
            sprintf('[CentralUrl::value] The URL "%s" must not contain dot segments', $value)
        );
        // The two checks a character class cannot carry. CentralUrlFactory already builds this URL
        // from an equally validated CentralAddress, but the guarantee is meant to hold for the value
        // itself, not for the path it happened to travel.
        $this->assertIpv6IsWellFormed($matches['ipv6'] ?? null);
        $this->assertPortIsInRange($matches['port'] ?? null);

        $this->value = $normalized;
    }

    /**
     * @param string|null $ipv6 bracket contents, null when the authority is a hostname or an IPv4
     */
    private function assertIpv6IsWellFormed(?string $ipv6): void
    {
        if ($ipv6 === null) {
            return;
        }

        // The capture is interpolated rather than $value: BRACKETED_IPV6 admits nothing but hex
        // digits and colons, so the message cannot carry a "%" the assertion library re-expands.
        Assert::true(
            filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
            sprintf('[CentralUrl::value] "%s" is not a valid IPv6 address', $ipv6)
        );
    }

    /**
     * @param string|null $port digits captured by PORT, null when the authority carries no port
     */
    private function assertPortIsInRange(?string $port): void
    {
        if ($port === null) {
            return;
        }

        Assert::range(
            (int) $port,
            self::MIN_PORT,
            self::MAX_PORT,
            sprintf('[CentralUrl::value] The port "%s" is out of range', $port)
        );
    }
}
