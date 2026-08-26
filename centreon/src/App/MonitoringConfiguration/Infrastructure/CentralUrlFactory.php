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

use App\MonitoringConfiguration\Domain\Aggregate\Poller\CentralAddress;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Builds the URL a poller must use to reach the central: scheme, then the
 * client-visible central address, then the base URI the web application is
 * served under (e.g. "https://central.example.com/centreon").
 *
 * Only the address itself is client-provided: behind proxies and NAT, nothing
 * server-side knows how the central is reachable. The base URI and the scheme
 * are properties of this platform, so they are resolved from the current
 * request instead of being taken from the payload — a bare address is the
 * common case (the modal asks the admin for an address, and the upgrade
 * populates existing pollers with the one already known to the platform).
 */
final readonly class CentralUrlFactory
{
    private const DEFAULT_SCHEME = 'https';

    /**
     * Every API route is mounted under "<base_uri>/api/<version>/", so whatever
     * precedes it in the request URI is the base URI — empty when root-mounted.
     */
    private const ENTRY_POINT_PATTERN = '~^(?<baseUri>.*?)/api/(?:latest|beta|v\d+(?:\.\d+)?)/~';

    /** Path segments only: the base URI ends up in a command the admin runs in a shell. */
    private const SAFE_BASE_URI_PATTERN = '~^(?:/[A-Za-z0-9._\-]+)*$~';

    public function __construct(
        private RequestStack $requestStack,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
    ) {
    }

    public function create(CentralAddress $centralAddress): string
    {
        $address = $centralAddress->value;

        // On cloud the address already carries the platform path, taken from the
        // browser location: appending the base URI would duplicate it.
        if (! str_contains($address, '/')) {
            $address .= $this->baseUri();
        }

        return sprintf('%s://%s', $this->scheme(), $address);
    }

    /**
     * Cloud is always served over HTTPS. On-prem ships a plain-HTTP vhost until
     * the admin configures SSL, so only the request tells which one it is —
     * and the scheme drives whether the poller opens its Gorgone websocket over
     * TLS, so guessing it wrong breaks the installation.
     */
    private function scheme(): string
    {
        if ($this->isCloudPlatform) {
            return self::DEFAULT_SCHEME;
        }

        return $this->requestStack->getCurrentRequest()?->getScheme() ?? self::DEFAULT_SCHEME;
    }

    private function baseUri(): string
    {
        $requestUri = $this->requestStack->getCurrentRequest()?->getRequestUri() ?? '';

        if (preg_match(self::ENTRY_POINT_PATTERN, $requestUri, $matches) !== 1) {
            return '';
        }

        $baseUri = rtrim($matches['baseUri'], '/');

        return preg_match(self::SAFE_BASE_URI_PATTERN, $baseUri) === 1 ? $baseUri : '';
    }
}
