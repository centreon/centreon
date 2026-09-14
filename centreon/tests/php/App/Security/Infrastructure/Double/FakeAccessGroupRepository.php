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

namespace Tests\App\Security\Infrastructure\Double;

use App\Security\Domain\Aggregate\AccessGroupId;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\AccessGroupRepository;
use App\Shared\Domain\Collection;

final class FakeAccessGroupRepository implements AccessGroupRepository
{
    /** @var array<int, list<string>> */
    public array $groupNamesByUserId = [];

    /** @var array<int, list<int>> */
    public array $groupIdsByUserId = [];

    public function userHasGroup(UserId $userId, string $groupName): bool
    {
        return in_array($groupName, $this->groupNamesByUserId[$userId->value] ?? [], true);
    }

    public function findActiveGroupIdsForUser(UserId $userId): Collection
    {
        $ids = array_map(
            static fn (int $id): AccessGroupId => new AccessGroupId($id),
            $this->groupIdsByUserId[$userId->value] ?? [],
        );

        return new Collection($ids, AccessGroupId::class);
    }
}
