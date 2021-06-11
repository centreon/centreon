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

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Domain\Monitoring\MonitoringResource\Model\MonitoringResource;
use Centreon\Domain\Monitoring\MonitoringResource\Interfaces\HyperMediaProviderInterface;

class ServiceHyperMediaProvider extends HyperMediaProvider
{
    public const PROVIDER_TYPE = 'service';

    public const URIS = [
        'configuration' => '/main.php?p=60201&o=c&service_id={resource_id}',
        'logs' => '/main.php?p=20301&svc={parent_resource_id}_{resource_id}',
        'reporting' => '/main.php?p=30702&period=yesterday&start=&end=&host_id={parent_resource_id}&item={resource_id}',
    ];

    public const ENDPOINTS = [
        'detail' => 'centreon_application_monitoring_resource_details_service',
        'downtime' => 'monitoring.downtime.addServiceDowntime',
        'acknowledgement' => 'centreon_application_acknowledgement_addserviceacknowledgement',
        'timeline' => 'centreon_application_monitoring_gettimelinebyhostandservice',
        'status_graph' => 'monitoring.metric.getServiceStatusMetrics',
        'performance_graph' => 'monitoring.metric.getServicePerformanceMetrics',
    ];

    /**
     * @inheritDoc
     */
    public function setEndpoints(MonitoringResource $resource): void
    {
        $acknowledgementFilter = ['limit' => 1];
        $downtimeFilter = [
            'search' => json_encode([
                RequestParameters::AGGREGATE_OPERATOR_AND => [
                    [
                        'start_time' => [
                            RequestParameters::OPERATOR_LESS_THAN => time(),
                        ],
                        'end_time' => [
                            RequestParameters::OPERATOR_GREATER_THAN => time(),
                        ],
                        [
                            RequestParameters::AGGREGATE_OPERATOR_OR => [
                                'is_cancelled' => [
                                    RequestParameters::OPERATOR_NOT_EQUAL => 1,
                                ],
                                'deletion_time' => [
                                    RequestParameters::OPERATOR_GREATER_THAN => time(),
                                ],
                            ],
                        ]
                    ]
                ]
            ])
        ];

        $parameters = [
            'hostId' => $resource->getParent()->getId(),
            'serviceId' => $resource->getId(),
        ];

        $resource->getLinks()->getEndpoints()->setDetails(
            $this->router->generate(
                static::ENDPOINTS['detail'],
                $parameters
            )
        );

        $resource->getLinks()->getEndpoints()->setTimeline(
            $this->router->generate(
                static::ENDPOINTS['timeline'],
                $parameters
            )
        );

        $resource->getLinks()->getEndpoints()->setAcknowledgement(
            $this->router->generate(
                static::ENDPOINTS['acknowledgement'],
                array_merge($parameters, $acknowledgementFilter)
            )
        );

        $resource->getLinks()->getEndpoints()->setDowntime(
            $this->router->generate(
                static::ENDPOINTS['downtime'],
                array_merge($parameters, $downtimeFilter)
            )
        );

        $resource->getLinks()->getEndpoints()->setStatusGraph(
            $this->router->generate(
                static::ENDPOINTS['status_graph'],
                $parameters
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function setUris(MonitoringResource $resource, Contact $contact): void
    {
        $hostResource = $resource->getParent();
        if (
            $contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_WRITE)
            || $contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_READ)
        ) {
            $hostResource->getLinks()->getUris()->setConfiguration(
                $this->generateResourceUri($resource, HostHyperMediaProvider::URIS['configuration'])
            );
        }

        if ($contact->hasTopologyRole(Contact::ROLE_MONITORING_EVENT_LOGS)) {
            $hostResource->getLinks()->getUris()->setLogs(
                $this->generateResourceUri($resource, HostHyperMediaProvider::URIS['logs'])
            );
        }

        if ($contact->hasTopologyRole(Contact::ROLE_REPORTING_DASHBOARD_HOSTS)) {
            $hostResource->getLinks()->getUris()->setReporting(
                $this->generateResourceUri($resource, HostHyperMediaProvider::URIS['reporting'])
            );
        }

        if (
            $contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_WRITE)
            || $contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_READ)
        ) {
            $resource->getLinks()->getUris()->setConfiguration(
                $this->generateResourceUri($resource, static::URIS['configuration'])
            );
        }

        if ($contact->hasTopologyRole(Contact::ROLE_MONITORING_EVENT_LOGS)) {
            $resource->getLinks()->getUris()->setLogs(
                $this->generateResourceUri($resource, static::URIS['logs'])
            );
        }

        if ($contact->hasTopologyRole(Contact::ROLE_REPORTING_DASHBOARD_SERVICES)) {
            $resource->getLinks()->getUris()->setReporting(
                $this->generateResourceUri($resource, static::URIS['reporting'])
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return self::PROVIDER_TYPE;
    }
}
