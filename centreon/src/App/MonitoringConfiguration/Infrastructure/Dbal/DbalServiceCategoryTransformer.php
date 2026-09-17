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

namespace App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategory;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryName;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-import-type RowTypeAlias from DbalServiceCategoryRepository
 *
 * @implements TransformerInterface<RowTypeAlias, ServiceCategory>
 */
final readonly class DbalServiceCategoryTransformer implements TransformerInterface
{
    public function transform(mixed $from): ServiceCategory
    {
        return new ServiceCategory(
            id: new ServiceCategoryId($from['sc_id']),
            name: new ServiceCategoryName($from['sc_name']),
            alias: new ServiceCategoryName($from['sc_description']),
            activated: $from['sc_activate'] === '1',
        );
    }
}
