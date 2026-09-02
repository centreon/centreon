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
use App\MonitoringConfiguration\Domain\Model\CentralUrl;
use App\MonitoringConfiguration\Domain\Model\UrlPath;
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
     * The two entry points able to generate an install command: the versioned API, and the legacy
     * "copy installation command" script. What precedes them in the request URI is the platform
     * base URI.
     *
     * Narrower than what Apache proxies to PHP-FPM on purpose: centreon-apache.conf also routes
     * "authentication/" and every ".php" under www/, and widening this to match would let an
     * unexpected request URI contribute a base URI to a command run as root. An unrecognized
     * entry point is meant to drop the base URI, not to be added here.
     *
     * Request::getBaseUrl() would serve the API alone: api/index.php fakes SCRIPT_NAME so the
     * kernel resolves "/centreon", while copyInstallCommand.php boots no kernel and yields
     * ".../configServers/copyInstallCommand.php" instead. HttpUrlTrait::getBaseUri() covers both
     * but sits outside App/, captures the request at injection time, and validates nothing it
     * extracts.
     */
    private const ENTRY_POINT_PATTERN = '~^(?<baseUri>.*?)/(?:api/(?:latest|beta|v\d+(?:\.\d+)?)|include)/~';

    public function __construct(
        private RequestStack $requestStack,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
    ) {
    }

    public function create(CentralAddress $centralAddress): CentralUrl
    {
        // An address that already carries a base path — cloud, or pasted whole by the
        // admin — must not get the platform one appended on top.
        $path = $centralAddress->basePath === null ? $this->baseUri() : '';

        return new CentralUrl(sprintf('%s://%s%s', $this->scheme(), $centralAddress->value, $path));
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

        // X-Forwarded-Proto is already resolved here, but only from the proxies
        // config/packages/framework.yaml declares trusted.
        return $request->getScheme();
    }

    private function baseUri(): string
    {
        $requestUri = $this->requestStack->getCurrentRequest()?->getRequestUri() ?? '';

        if (preg_match(self::ENTRY_POINT_PATTERN, $requestUri, $matches) !== 1) {
            $this->logDroppedBaseUri('unrecognized entry point', $requestUri);

            return '';
        }

        // The base URI ends up in a command the admin runs in a shell, so it has to pass the
        // same segment rules as the rest of the URL.
        $baseUri = UrlPath::tryFrom(rtrim($matches['baseUri'], '/'));
        if (! $baseUri instanceof UrlPath) {
            $this->logDroppedBaseUri('unsafe path', $requestUri);

            return '';
        }

        return $baseUri->value;
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
