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
 *      hg_notes: ?string,
 *      hg_notes_url: ?string,
 *      hg_action_url: ?string,
 *      hg_icon_image: ?int,
 *      hg_map_icon_image: ?int,
 *      hg_rrd_retention: ?int,
 *      geo_coords: ?string,
 *      hg_comment: ?string,
 *      hg_activate: '0'|'1'
 *  }
 * @phpstan-type _ApiHostGroup array{
 *       id: int,
 *       name: string,
 *       alias: ?string,
 *       notes: ?string,
 *       notes_url: ?string,
 *       action_url: ?string,
 *       icon_id: ?int,
 *       icon_map_id: ?int,
 *       rrd: ?int,
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
            $data['hg_id'],
            $data['hg_name'],
            (string) $data['hg_alias'],
            (string) $data['hg_notes'],
            (string) $data['hg_notes_url'],
            (string) $data['hg_action_url'],
            $data['hg_icon_image'],
            $data['hg_map_icon_image'],
            $data['hg_rrd_retention'],
            match ($geoCoords = $data['geo_coords']) {
                null, '' => null,
                default => GeoCoords::fromString($geoCoords),
            },
            (string) $data['hg_comment'],
            (bool) $data['hg_activate'],
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
        if (isset($data['notes'])) {
            return new HostGroup(
                $data['id'],
                $data['name'],
                (string) $data['alias'],
                (string) $data['notes'],
                (string) $data['notes_url'],
                (string) $data['action_url'],
                $data['icon_id'],
                $data['icon_map_id'],
                $data['rrd'],
                match ($geoCoords = $data['geo_coords']) {
                    null, '' => null,
                    default => GeoCoords::fromString($geoCoords),
                },
                (string) $data['comment'],
                (bool) $data['is_activated'],
            );
        }
  
        // Cloud Platform
        return new HostGroup(
            $data['id'],
            $data['name'],
            (string) $data['alias'],
            '',
            '',
            '',
            $data['icon_id'],
            null,
            null,
            match ($geoCoords = $data['geo_coords']) {
                null, '' => null,
                default => GeoCoords::fromString($geoCoords),
            },
            '',
            (bool) $data['is_activated'],
        );
        
    }
}
