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

class HostHyperMediaProvider extends HyperMediaProvider
{
    public const PROVIDER_TYPE = 'host';

    public const URIS = [
        'configuration' => '/main.php?p=60101&o=c&host_id={resource_id}',
        'logs' => '/main.php?p=20301&h={resource_id}',
        'reporting' => '/main.php?p=307&host={resource_id}'
    ];

    public const ENDPOINTS = [
        'detail' => 'centreon_application_monitoring_resource_details_host',
        'downtime' => 'monitoring.downtime.addHostDowntime',
        'acknowledgement' => 'centreon_application_acknowledgement_addhostacknowledgement',
        'timeline' => 'centreon_application_monitoring_gettimelinebyhost'
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
            'hostId' => $resource->getId(),
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
    }

    /**
     * @inheritDoc
     */
    public function setUris(MonitoringResource $resource, Contact $contact): void
    {
        if (
            $contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_WRITE)
            || $contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_READ)
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

        if ($contact->hasTopologyRole(Contact::ROLE_REPORTING_DASHBOARD_HOSTS)) {
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
