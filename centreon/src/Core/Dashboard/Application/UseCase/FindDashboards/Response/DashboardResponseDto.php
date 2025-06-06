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

namespace Core\Dashboard\Application\UseCase\FindDashboards\Response;

use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use DateTimeImmutable;

final class DashboardResponseDto
{
    /**
     * @param int $id
     * @param string $name
     * @param string|null $description
     * @param UserResponseDto|null $createdBy
     * @param UserResponseDto|null $updatedBy
     * @param DateTimeImmutable $createdAt
     * @param DateTimeImmutable $updatedAt
     * @param DashboardSharingRole $ownRole
     * @param array{
     *     contacts: array<array{
     *      id: int,
     *      name: string,
     *      email: string,
     *      role: DashboardSharingRole
     *     }>,
     *     contact_groups: array<array{
     *      id: int,
     *      name: string,
     *      role: DashboardSharingRole
     *     }>
     * } $shares
     * @param ThumbnailResponseDto $thumbnail
     * @param bool $isFavorite
     */
    public function __construct(
        public int $id = 0,
        public string $name = '',
        public ?string $description = null,
        public ?UserResponseDto $createdBy = null,
        public ?UserResponseDto $updatedBy = null,
        public DateTimeImmutable $createdAt = new DateTimeImmutable(),
        public DateTimeImmutable $updatedAt = new DateTimeImmutable(),
        public DashboardSharingRole $ownRole = DashboardSharingRole::Viewer,
        public array $shares = ['contacts' => [], 'contact_groups' => []],
        public ?ThumbnailResponseDto $thumbnail = null,
        public bool $isFavorite = false
    ) {
    }
}
