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

namespace Core\Dashboard\Application\UseCase\AddDashboard;

use Core\Dashboard\Domain\Model\Refresh\RefreshType;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;

final class AddDashboardResponse
{
    /**
     * @param int $id
     * @param string $name
     * @param string|null $description
     * @param array{id:int, name:string} $createdBy
     * @param array{id:int, name:string} $updatedBy
     * @param \DateTimeImmutable $createdAt
     * @param \DateTimeImmutable $updatedAt
     * @param DashboardSharingRole $ownRole
     * @param array<array{
     *          id: int,
     *          name: string,
     *          layout: array{
     *              x: int,
     *              y: int,
     *              width: int,
     *              height: int,
     *              min_width: int,
     *              min_height: int
     *          },
     *          widget_type: string,
     *          widget_settings: array<mixed>,
     * }> $panels,
     * @param array{type: RefreshType, interval: int|null} $refresh
     */
    public function __construct(
        public int $id = 0,
        public string $name = '',
        public ?string $description = null,
        public array $createdBy = ['id' => 0, 'name' => ''],
        public array $updatedBy = ['id' => 0, 'name' => ''],
        public \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        public \DateTimeImmutable $updatedAt = new \DateTimeImmutable(),
        public DashboardSharingRole $ownRole = DashboardSharingRole::Viewer,
        public array $panels = [],
        public array $refresh = [
            'type' => RefreshType::Global,
            'interval' => null,
        ]
    ) {
    }
}
