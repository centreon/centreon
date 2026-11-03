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

namespace App\MonitoringConfiguration\Domain\Repository;

use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceTemplateCriteria;
use App\Shared\Domain\Collection;

interface ServiceTemplateRepository
{
    /**
     * Service templates only (never services), ordered by id.
     *
     * @return \IteratorAggregate<int, ServiceTemplate>&\Countable
     */
    public function findAll(?ServiceTemplateCriteria $criteria = null): \IteratorAggregate&\Countable;

    /**
     * The service templates of the Monitoring Connectors, and every template inheriting from them.
     * Empty when the plugin pack manager module, which provides the Monitoring Connectors, is not
     * installed.
     *
     * @param list<string> $excludedConnectorSlugs the Monitoring Connectors whose templates are left out
     *
     * @return Collection<ServiceTemplateId>
     */
    public function findMonitoringConnectorTemplateIds(array $excludedConnectorSlugs): Collection;
}
