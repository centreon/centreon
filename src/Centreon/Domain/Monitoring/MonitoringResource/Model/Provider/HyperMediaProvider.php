<?php

/*
 * Copyright 2005 - 2021 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Centreon\Domain\Monitoring\MonitoringResource\Model\Provider;

use Centreon\Domain\RequestParameters\RequestParameters;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Centreon\Domain\Monitoring\MonitoringResource\Model\MonitoringResource;
use Centreon\Domain\Monitoring\MonitoringResource\Interfaces\HyperMediaProviderInterface;
use Centreon\Application\Controller\AbstractController;

abstract class HyperMediaProvider implements HyperMediaProviderInterface
{
    /**
     * @var UrlGeneratorInterface
     */
    protected $router;

    public function __construct(UrlGeneratorInterface $router)
    {
        $this->router = $router;
    }

    /**
     * Generate full uri from relative path
     *
     * @param ResourceEntity $resource
     * @param string $relativeUri
     * @return string
     */
    public function generateResourceUri(MonitoringResource $resource, string $relativeUri): string
    {
        $relativeUri = str_replace('{resource_id}', (string) $resource->getId(), $relativeUri);
        $relativeUri = str_replace('{host_id}', (string) $resource->getHostId(), $relativeUri);
        $relativeUri = str_replace('{service_id}', (string) $resource->getServiceId(), $relativeUri);

        if ($resource->getParent() !== null) {
            $relativeUri = str_replace('{parent_resource_id}', (string) $resource->getParent()->getId(), $relativeUri);
        }

        return $this->getBaseUri() . $relativeUri;
    }

        /**
     * Get current base uri
     *
     * @return string
     */
    protected function getBaseUri(): string
    {
        $baseUri = '';

        if (
            isset($_SERVER['REQUEST_URI'])
            && preg_match(
                '/^(.+)\/((api|widgets|modules|include)\/|main(\.get)?\.php).+/',
                $_SERVER['REQUEST_URI'],
                $matches
            )
        ) {
            $baseUri = $matches[1];
        }

        return $baseUri;
    }
}
