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

namespace App\Security\Domain;

use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Repository\AccessGroupRepository;

/**
 * Resolves whether a user should be treated as unrestricted for ACL-scoping purposes.
 *
 * This is not the same thing as `ROLE_ADMIN` alone: on Cloud, a platform is single-tenant,
 * so the top-level admin of that tenant (member of the "customer_admin_acl" Access Group)
 * is also unrestricted within it, even without `ROLE_ADMIN` (which is Centreon-staff-level).
 */
final readonly class AdminResolver
{
    private const CUSTOMER_ADMIN_ACCESS_GROUP_NAME = 'customer_admin_acl';

    public function __construct(
        private AccessGroupRepository $accessGroupRepository,
        private bool $isCloudPlatform,
    ) {
    }

    public function resolve(Credential $credential): bool
    {
        if ($credential->isAdmin()) {
            return true;
        }

        return $this->isCloudPlatform
            && $this->accessGroupRepository->userHasGroup(
                $credential->userId,
                self::CUSTOMER_ADMIN_ACCESS_GROUP_NAME,
            );
    }
}
