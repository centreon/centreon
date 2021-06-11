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

use Iterator;
use Traversable;
use FOS\RestBundle\View\View;
use FOS\RestBundle\Context\Context;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Monitoring\Host;
use Centreon\Domain\Monitoring\Icon;
use Centreon\Domain\Downtime\Downtime;
use Centreon\Domain\Monitoring\Service;
use JMS\Serializer\SerializerInterface;
use Centreon\Domain\Entity\EntityValidator;
use Symfony\Component\HttpFoundation\Request;
use Centreon\Domain\Monitoring\ResourceFilter;
use Centreon\Domain\Monitoring\ResourceStatus;
use Symfony\Component\HttpFoundation\Response;
use Centreon\Domain\Acknowledgement\Acknowledgement;
use Centreon\Application\Normalizer\IconUrlNormalizer;
use JMS\Serializer\Exception\ValidationFailedException;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Domain\Monitoring\Exception\ResourceException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Centreon\Domain\Monitoring\Serializer\ResourceExclusionStrategy;
use Centreon\Domain\Monitoring\Interfaces\MonitoringServiceInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Domain\Monitoring\MonitoringResource\UseCase\V2110\FindMonitoringResources;
use Centreon\Domain\Monitoring\MonitoringResource\Interfaces\HyperMediaProviderInterface;
use Centreon\Domain\Monitoring\MonitoringResource\UseCase\V2110\FindMonitoringResourcesResponse;
use Centreon\Infrastructure\Monitoring\MonitoringResource\API\Model_2110\MonitoringResourceFormatter;
use Centreon\Infrastructure\Monitoring\MonitoringResource\API\Model_2110\MonitoringResourceFactory;

/**
 * Resource APIs for the Unified View page
 *
 * @package Centreon\Application\Controller
 */
class MonitoringResourceController extends AbstractController
{
    /**
     * List of external parameters for list action
     *
     * @var array
     */
    public const EXTRA_PARAMETERS_LIST = [
        'types',
        'states',
        'statuses',
        'hostgroup_ids',
        'servicegroup_ids',
        'monitoring_server_ids',
    ];

    public const FILTER_RESOURCES_ON_PERFORMANCE_DATA_AVAILABILITY = 'only_with_performance_data';


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
     * @var MonitoringServiceInterface
     */
    private $monitoring;

    /**
     * @var ResourceServiceInterface
     */
    protected $resource;

    /**
     * @var UrlGeneratorInterface
     */
    protected $router;

    /**
     * @var IconUrlNormalizer
     */
    protected $iconUrlNormalizer;

    /**
     * @var HyperMediaProviderInterface[]
     */
    public $hyperMediaProviders = [];

    /**
     * @param MonitoringServiceInterface $monitoringService
     * @param ResourceServiceInterface $resource
     * @param UrlGeneratorInterface $router
     * @param IconUrlNormalizer $iconUrlNormalizer
     */
    public function __construct(
        MonitoringServiceInterface $monitoringService,
        UrlGeneratorInterface $router,
        IconUrlNormalizer $iconUrlNormalizer
    ) {
        $this->monitoring = $monitoringService;
        $this->router = $router;
        $this->iconUrlNormalizer = $iconUrlNormalizer;
    }

    public function findMonitoringResources(
        RequestParametersInterface $requestParameters,
        Request $request,
        FindMonitoringResources $findMonitoringResources,
        EntityValidator $entityValidator,
        SerializerInterface $serializer
    ): View {
        $this->denyAccessUnlessGrantedForApiRealtime();

        /**
         * @var Contact $contact
         */
        $contact = $this->getUser();

        // set default values of filter data
        $filterData = [];
        foreach (static::EXTRA_PARAMETERS_LIST as $param) {
            $filterData[$param] = [];
        }

        $filterData[static::FILTER_RESOURCES_ON_PERFORMANCE_DATA_AVAILABILITY] = false;

        // load filter data with the query parameters
        foreach ($request->query->all() as $param => $data) {
            // skip pagination parameters
            if (in_array($param, ['search', 'limit', 'page', 'sort_by'])) {
                continue;
            }

            $filterData[$param] = json_decode($data, true) ?: $data;
        }
        // validate the filter data
        $errors = $entityValidator->validateEntity(
            ResourceFilter::class,
            $filterData,
            ['Default'],
            false // We don't allow extra fields
        );

        if ($errors->count() > 0) {
            throw new ValidationFailedException($errors);
        }

        // Parse the filter data into filter object
        $filter = $serializer->deserialize(
            json_encode($filterData),
            ResourceFilter::class,
            'json'
        );
        $response = $findMonitoringResources->execute($filter);
        /**
         * loop foreach on providers to identify the one to call
         */
        $monitoringResourceObject = null;
        foreach ($response->getMonitoringResources() as $resource) {
            foreach ($this->hyperMediaProviders as $provider) {
                if ($resource['type'] === $provider->getType()) {
                    $monitoringResourceObject = MonitoringResourceFactory::createFromResponse($resource);
                    $provider->setUris($monitoringResourceObject, $contact);
                    $provider->setEndpoints($monitoringResourceObject);
                }
            }
        }
        return $this->view(
            [
                'result' => MonitoringResourceFormatter::createFromResponse($response),
                'meta' => $requestParameters->toArray()
            ]
        );
    }

    /**
     * @param \Traversable $providers
     * @return void
     */
    public function setHyperMediaProviders(\Traversable $providers): void
    {
        if (count($providers) === 0) {
            throw new \InvalidArgumentException(
                _('You must at least add one hyper media provider')
            );
        }

        $this->hyperMediaProviders = iterator_to_array($providers);
    }
}