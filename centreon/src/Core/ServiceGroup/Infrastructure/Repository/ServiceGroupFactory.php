<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

declare(strict_types = 1);

namespace Core\ServiceGroup\Infrastructure\Repository;

use Assert\AssertionFailedException;
use Core\Domain\Common\GeoCoords;
use Core\ServiceGroup\Domain\Model\ServiceGroup;

/**
 * @phpstan-import-type ServiceGroupResultSet from DbReadServiceGroupRepository
 */
class ServiceGroupFactory
{
    /**
     * @param ServiceGroupResultSet $data
     *
     * @throws AssertionFailedException
     *
     * @return ServiceGroup
     */
    public static function createFromDb(array $data): ServiceGroup
    {
        return new ServiceGroup(
            $data['sg_id'],
            $data['sg_name'],
            $data['sg_alias'],
            match ($geoCoords = $data['geo_coords']) {
                null, '' => null,
                default => GeoCoords::fromString($geoCoords),
            },
            (string) $data['sg_comment'],
            (bool) $data['sg_activate'],
        );
    }

    /**
     * @param array{
     *     id: int,
     *     name: string,
     *     alias: string,
     *     geo_coords: string|null,
     *     comment: string|null,
     *     is_activated: bool
     * } $data
     *
     * @throws AssertionFailedException
     *
     * @return ServiceGroup
     */
    public static function createFromApi(array $data): ServiceGroup
    {
        return new ServiceGroup(
            $data['id'],
            $data['name'],
            $data['alias'],
            match ($geoCoords = $data['geo_coords']) {
                null, '' => null,
                default => GeoCoords::fromString($geoCoords),
            },
            (string) $data['comment'],
            (bool) $data['is_activated'],
        );
    }
}
