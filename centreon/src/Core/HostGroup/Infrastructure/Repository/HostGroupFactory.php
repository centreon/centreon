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

declare(strict_types = 1);

namespace Core\HostGroup\Infrastructure\Repository;

use Assert\AssertionFailedException;
use Core\Domain\Common\GeoCoords;
use Core\HostGroup\Domain\Model\HostGroup;

/**
 * @phpstan-type _DbHostGroup array{
 *      hg_id: int,
 *      hg_name: string,
 *      hg_alias: ?string,
 *      hg_icon_image: ?int,
 *      geo_coords: ?string,
 *      hg_comment: ?string,
 *      hg_activate: '0'|'1'
 *  }
 * @phpstan-type _ApiHostGroup array{
 *       id: int,
 *       name: string,
 *       alias: ?string,
 *       icon_id: ?int,
 *       geo_coords: ?string,
 *       comment: ?string,
 *       is_activated: bool
 *   }
 */
class HostGroupFactory
{
    /**
     * @param _DbHostGroup $data
     *
     * @throws AssertionFailedException
     *
     * @return HostGroup
     */
    public static function createFromDb(array $data): HostGroup
    {
        return new HostGroup(
            id: $data['hg_id'],
            name: $data['hg_name'],
            alias: (string) $data['hg_alias'],
            iconId: $data['hg_icon_image'],
            geoCoords: match ($geoCoords = $data['geo_coords']) {
                null, '' => null,
                default => GeoCoords::fromString($geoCoords),
            },
            comment: (string) $data['hg_comment'],
            isActivated: (bool) $data['hg_activate'],
        );
    }

    /**
     * @param _ApiHostGroup $data
     *
     * @throws AssertionFailedException
     *
     * @return HostGroup
     */
    public static function createFromApi(array $data): HostGroup
    {
        return new HostGroup(
            id: $data['id'],
            name: $data['name'],
            alias: (string) $data['alias'],
            iconId: $data['icon_id'],
            geoCoords: match ($geoCoords = $data['geo_coords']) {
                null, '' => null,
                default => GeoCoords::fromString($geoCoords),
            },
            comment: (string) $data['comment'],
            isActivated: (bool) $data['is_activated'],
        );
    }
}
