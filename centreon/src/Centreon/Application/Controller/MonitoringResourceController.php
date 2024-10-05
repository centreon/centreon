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

namespace Centreon\Application\Controller;

use Centreon\Domain\Monitoring\Resource as ResourceEntity;
use Centreon\Domain\Monitoring\Exception\ResourceException;
use Core\Infrastructure\RealTime\Hypermedia\HypermediaProviderInterface;

/**
 * Resource APIs for the Unified View page
 *
 * @package Centreon\Application\Controller
 */
class MonitoringResourceController extends AbstractController
{
    /*
     * @var HypermediaProviderInterface[]
     */
    private array $hyperMediaProviders = [];

    private const RESOURCE_LISTING_URI = '/monitoring/resources';

    public const TAB_DETAILS_NAME = 'details';
    public const TAB_GRAPH_NAME = 'graph';
    public const TAB_SERVICES_NAME = 'services';
    public const TAB_TIMELINE_NAME = 'timeline';
    public const TAB_SHORTCUTS_NAME = 'shortcuts';

    private const ALLOWED_TABS = [
        self::TAB_DETAILS_NAME,
        self::TAB_GRAPH_NAME,
        self::TAB_SERVICES_NAME,
        self::TAB_TIMELINE_NAME,
        self::TAB_SHORTCUTS_NAME,
    ];

    /**
     * @param \Traversable<HypermediaProviderInterface> $hyperMediaProviders
     */
    public function __construct(
        \Traversable $hyperMediaProviders
    ) {
        $this->hasProviders($hyperMediaProviders);
        $this->hyperMediaProviders = iterator_to_array($hyperMediaProviders);
    }

    /**
     * @param \Traversable<mixed> $providers
     * @return void
     */
    private function hasProviders(\Traversable $providers): void
    {
        if ($providers instanceof \Countable && count($providers) === 0) {
            throw new \InvalidArgumentException(
                _('You must add at least one provider')
            );
        }
    }

    /**
     * Generates a resource details redirection link
     *
     * @param string $resourceType
     * @param integer $resourceId
     * @param array<string, integer> $parameters
     * @return string
     */
    private function buildResourceDetailsUri(string $resourceType, int $resourceId, array $parameters): string
    {
        $resourceDetailsEndpoint = null;
        foreach ($this->hyperMediaProviders as $hyperMediaProvider) {
            if ($hyperMediaProvider->isValidFor($resourceType)) {
                $resourceDetailsEndpoint = $hyperMediaProvider->generateResourceDetailsUri($parameters);
            }
        }

        return $this->buildListingUri([
            'details' => json_encode([
                'id' => $resourceId,
                'tab' => self::TAB_DETAILS_NAME,
                'resourcesDetailsEndpoint' => $this->getBaseUri() . $resourceDetailsEndpoint
            ])
        ]);
    }

    /**
     * Build uri to access host panel with details tab
     *
     * @param integer $hostId
     * @return string
     */
    public function buildHostDetailsUri(int $hostId): string
    {
        return $this->buildResourceDetailsUri(
            ResourceEntity::TYPE_HOST,
            $hostId,
            ['hostId' => $hostId]
        );
    }

    /**
     * Build uri to access host panel
     *
     * @param integer $hostId
     * @param string $tab tab name
     * @return string
     */
    public function buildHostUri(int $hostId, string $tab = self::TAB_DETAILS_NAME): string
    {
        if (!in_array($tab, self::ALLOWED_TABS)) {
            throw new ResourceException(sprintf(_('Cannot build uri to unknown tab : %s'), $tab));
        }

        return $this->buildListingUri([
            'details' => json_encode([
                'type' => ResourceEntity::TYPE_HOST,
                'id' => $hostId,
                'tab' => $tab,
                'uuid' => 'h' . $hostId
            ]),
        ]);
    }

    /**
     * Build uri to access service service panel with details tab
     *
     * @param integer $hostId
     * @param integer $serviceId
     * @return string
     */
    public function buildServiceDetailsUri(int $hostId, int $serviceId): string
    {
        return $this->buildResourceDetailsUri(
            ResourceEntity::TYPE_SERVICE,
            $serviceId,
            [
                'hostId' => $hostId,
                'serviceId' => $serviceId
            ]
        );
    }

    /**
     * Build uri to access service panel
     *
     * @param integer $hostId
     * @param integer $serviceId
     * @param string $tab tab name
     * @return string
     */
    public function buildServiceUri(int $hostId, int $serviceId, string $tab = self::TAB_DETAILS_NAME): string
    {
        if (!in_array($tab, self::ALLOWED_TABS)) {
            throw new ResourceException(sprintf(_('Cannot build uri to unknown tab : %s'), $tab));
        }

        return $this->buildListingUri([
            'details' => json_encode([
                'parentType' => ResourceEntity::TYPE_HOST,
                'parentId' => $hostId,
                'type' => ResourceEntity::TYPE_SERVICE,
                'id' => $serviceId,
                'tab' => $tab,
                'uuid' => 'h' . $hostId . '-s' . $serviceId
            ]),
        ]);
    }

    /**
     * Build uri to access meta service panel
     *
     * @param integer $metaId
     * @return string
     */
    public function buildMetaServiceDetailsUri(int $metaId): string
    {
        return $this->buildResourceDetailsUri(
            ResourceEntity::TYPE_META,
            $metaId,
            [
                'metaId' => $metaId
            ]
        );
    }

    /**
     * Build uri to access listing page of resources with specific parameters
     *
     * @param string[] $parameters
     * @return string
     */
    public function buildListingUri(array $parameters): string
    {
        $baseListingUri = $this->getBaseUri() . self::RESOURCE_LISTING_URI;

        if ($parameters !== []) {
            $baseListingUri .= '?' . http_build_query($parameters);
        }

        return $baseListingUri;
    }
}
