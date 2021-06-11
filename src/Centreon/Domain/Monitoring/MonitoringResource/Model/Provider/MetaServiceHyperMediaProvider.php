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
use Centreon\Domain\Monitoring\MonitoringResource\Model\MonitoringResource;
use Centreon\Domain\Monitoring\MonitoringResource\Interfaces\HyperMediaProviderInterface;

class MetaServiceHyperMediaProvider implements HyperMediaProviderInterface
{
    public const PROVIDER_TYPE = 'metaservice';

    public const URIS = [
        'configuration' => '/main.php?p=60204&o=c&meta_id={resource_id}',
        'logs' => '/main.php?p=20301&svc={host_id}_{service_id}',
    ];

    public const ENDPOINTS = [
        'detail' => 'centreon_application_monitoring_resource_details_meta_service',
        'downtime' => 'monitoring.downtime.addMetaServiceDowntime',
        'acknowledgement' => 'centreon_application_acknowledgement_addmetaserviceacknowledgement',
        'timeline' => 'centreon_application_monitoring_gettimelinebymetaservices',
        'status_graph' => 'monitoring.metric.getMetaServiceStatusMetrics',
        'performance_graph' => 'monitoring.metric.getMetaServicePerformanceMetrics',
        'metric_list' => 'centreon_application_find_meta_service_metrics',
    ];

    public function setEndpoints(MonitoringResource $resource): void
    {
        // @todo add code
    }

    public function setUris(MonitoringResource $resource, Contact $contact): void
    {
        // @todo add code
    }

    public function getType(): string
    {
        return self::PROVIDER_TYPE;
    }
}
