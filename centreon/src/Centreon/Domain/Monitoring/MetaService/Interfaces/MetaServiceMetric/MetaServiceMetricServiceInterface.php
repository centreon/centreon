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

namespace Centreon\Domain\Monitoring\MetaService\Interfaces\MetaServiceMetric;

use Centreon\Domain\Monitoring\MetaService\Exception\MetaServiceMetricException;
use Centreon\Domain\Monitoring\MetaService\Model\MetaServiceMetric;

/**
 * @package Centreon\Domain\Monitoring\MetaService\Interfaces\MetaServiceMetric
 */
interface MetaServiceMetricServiceInterface
{
    /**
     * Find all meta service metrics (for non admin user).
     *
     * @param int $metaId
     * @throws MetaServiceMetricException
     * @return MetaServiceMetric[]|null
     */
    public function findWithoutAcl(int $metaId): ?array;

    /**
     * Find all meta service metrics (for admin user).
     *
     * @param int $metaId
     * @throws MetaServiceMetricException
     * @return MetaServiceMetric[]|null
     */
    public function findWithAcl(int $metaId): ?array;
}
