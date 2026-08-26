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

namespace App\MonitoringConfiguration\Infrastructure;

use Adaptation\Log\Enum\LogChannelEnum;
use Adaptation\Log\Logger;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\CentralAddress;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Central URL for the poller install command: the address comes from the payload
 * (see CentralAddress), the scheme and base URI from the current request — they
 * are properties of this platform, not of the payload.
 */
final readonly class CentralUrlFactory
{
    private const DEFAULT_SCHEME = 'https';

    /**
     * Entry points able to generate an install command, mirroring the paths Apache
     * proxies to PHP-FPM (packaging/src/centreon-apache.conf): what precedes them
     * in the request URI is the platform base URI.
     */
    private const ENTRY_POINT_PATTERN = '~^(?<baseUri>.*?)/(?:api/(?:latest|beta|v\d+(?:\.\d+)?)|include)/~';

    /** Path segments only: the base URI ends up in a command the admin runs in a shell. */
    private const SAFE_BASE_URI_PATTERN = '~^(?:/[A-Za-z0-9._\-]+)*$~';

    /** Rejected for the same reason as in CentralAddress: they resolve outside the base path. */
    private const DOT_SEGMENT_PATTERN = '~(?:^|/)\.{1,2}(?:/|$)~';

    public function __construct(
        private RequestStack $requestStack,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
    ) {
    }

    public function create(CentralAddress $centralAddress): string
    {
        // An address that already carries a base path — cloud, or pasted whole by the
        // admin — must not get the platform one appended on top.
        $path = $centralAddress->basePath === null ? $this->baseUri() : '';

        return sprintf('%s://%s%s', $this->scheme(), $centralAddress->value, $path);
    }

    /**
     * On-prem may still be plain HTTP; the poller derives Gorgone's TLS and port from this scheme.
     */
    private function scheme(): string
    {
        if ($this->isCloudPlatform) {
            return self::DEFAULT_SCHEME;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (! $request instanceof Request) {
            return self::DEFAULT_SCHEME;
        }

        // This kernel declares no trusted_proxies, so getScheme() cannot see through a
        // TLS-terminating proxy. Honour the header to upgrade only: a spoofed value can
        // make the command fail loudly, never downgrade a poller to plain HTTP.
        $forwarded = $request->headers->get('X-Forwarded-Proto');
        if (is_string($forwarded) && mb_strtolower(trim(explode(',', $forwarded)[0])) === self::DEFAULT_SCHEME) {
            return self::DEFAULT_SCHEME;
        }

        return $request->getScheme();
    }

    private function baseUri(): string
    {
        $requestUri = $this->requestStack->getCurrentRequest()?->getRequestUri() ?? '';

        if (preg_match(self::ENTRY_POINT_PATTERN, $requestUri, $matches) !== 1) {
            $this->logDroppedBaseUri('unrecognized entry point', $requestUri);

            return '';
        }

        $baseUri = rtrim($matches['baseUri'], '/');
        if (
            preg_match(self::SAFE_BASE_URI_PATTERN, $baseUri) !== 1
            || preg_match(self::DOT_SEGMENT_PATTERN, $baseUri) === 1
        ) {
            $this->logDroppedBaseUri('unsafe path', $requestUri);

            return '';
        }

        return $baseUri;
    }

    /**
     * Without the base URI the command still looks valid but downloads install.sh from
     * the wrong path, so the failure has to be traceable.
     */
    private function logDroppedBaseUri(string $reason, string $requestUri): void
    {
        Logger::create(LogChannelEnum::POLLER_INSTALL)->warning(
            'Central URL built without the platform base URI',
            ['reason' => $reason, 'request_uri' => $requestUri],
        );
    }
}
