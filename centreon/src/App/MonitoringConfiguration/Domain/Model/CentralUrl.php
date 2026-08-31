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
    /** Hostname or IPv4 — indistinguishable here: both are letters, digits, dots and hyphens. */
    private const HOST = '[A-Za-z0-9._\-]+';

    /** IPv6 needs a branch of its own: its brackets and colons fall outside HOST. */
    private const BRACKETED_IPV6 = '\[[0-9A-Fa-f:]+\]';

    /** Web port, never a protocol port such as the broker's. Range checked by CentralAddress. */
    private const PORT = '(?::\d{1,5})?';

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
        Assert::regex(
            $normalized,
            self::PATTERN,
            sprintf('[CentralUrl::value] "%s" is not an absolute HTTP(S) URL made of safe segments', $value)
        );
        // "." and ".." match the path part of PATTERN, so they need their own check.
        Assert::false(
            (bool) preg_match(self::DOT_SEGMENT_PATTERN, $normalized),
            sprintf('[CentralUrl::value] The URL "%s" must not contain dot segments', $value)
        );

        $this->value = $normalized;
    }
}
