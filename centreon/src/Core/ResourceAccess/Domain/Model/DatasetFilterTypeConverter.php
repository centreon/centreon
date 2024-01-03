<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\ResourceAccess\Domain\Model;

use InvalidArgumentException;

class DatasetFilterTypeConverter
{
    /**
     * @param DatasetFilterType $type
     *
     * @return string
     */
    public static function toString(DatasetFilterType $type): string
    {
        return match ($type) {
            DatasetFilterType::Host => 'host',
            DatasetFilterType::Hostgroup => 'hostgroup',
            DatasetFilterType::HostCategory => 'host_category',
            DatasetFilterType::Service => 'service',
            DatasetFilterType::Servicegroup => 'servicegroup',
            DatasetFilterType::ServiceCategory => 'service_category',
            DatasetFilterType::MetaService => 'meta_service'
        };
    }

    /**
     * @param string $type
     *
     * @throws InvalidArgumentException
     *
     * @return DatasetFilterType
     */
    public static function fromString(string $type): DatasetFilterType
    {
        return match ($type) {
            'host' => DatasetFilterType::Host,
            'hostgroup' => DatasetFilterType::Hostgroup,
            'host_category' => DatasetFilterType::HostCategory,
            'servicegroup' => DatasetFilterType::Servicegroup,
            'service_category' => DatasetFilterType::ServiceCategory,
            'meta_service' => DatasetFilterType::MetaService,
            'service' => DatasetFilterType::Service,
            default => throw new \InvalidArgumentException("\"{$type}\" is not a valid string for enum DatasetFilterType")
        };
    }
}
