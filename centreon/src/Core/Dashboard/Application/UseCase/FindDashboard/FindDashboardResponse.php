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

namespace Core\Dashboard\Application\UseCase\FindDashboard;

use Core\Dashboard\Application\UseCase\FindDashboard\Response\{PanelResponseDto, RefreshResponseDto, ThumbnailResponseDto, UserResponseDto};
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;

final class FindDashboardResponse
{
    public int $id = 0;

    public string $name = '';

    public ?string $description = null;

    public ?UserResponseDto $createdBy = null;

    public ?UserResponseDto $updatedBy = null;

    public \DateTimeImmutable $createdAt;

    public \DateTimeImmutable $updatedAt;

    /** @var array<PanelResponseDto> */
    public array $panels = [];

    public DashboardSharingRole $ownRole = DashboardSharingRole::Viewer;

    public RefreshResponseDto $refresh;

    public ?ThumbnailResponseDto $thumbnail = null;

    /**
     * @var array{
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
     * }
     */
    public array $shares = ['contacts' => [], 'contact_groups' => []];

    public function __construct() {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->refresh = new RefreshResponseDto();
    }
}
