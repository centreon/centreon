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

namespace App\Security\Infrastructure\EventHandler;

use App\Security\Domain\Repository\AccessGroupRepository;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Aggregate\AclScopedInterface;
use App\Shared\Domain\Event\AggregateCreated;
use App\Shared\Domain\Event\AggregateUpdated;
use App\Shared\Domain\Event\AsEventHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Webmozart\Assert\Assert;

/**
 * Reacts to the create or change of any ACL-scoped resource (see {@see AclScopedInterface}): its
 * visibility may now differ, so the ACL tables are flagged for the `centAcl` cron to recompute
 * `centreon_acl` — otherwise, for instance, a disabled host stays visible there until some
 * unrelated change happens to raise a flag.
 *
 * On creation only, a non-admin creator's `centreon_acl` is additionally seeded directly, so they
 * see their own new resource immediately without waiting for the cron. On a later change (e.g. a
 * host being enabled or disabled) the flag alone drives the recompute: seeding grants access, which
 * would be wrong when disabling and redundant when enabling. Reacting to the {@see AggregateUpdated}
 * supertype also catches its enable/disable specializations.
 */
#[AsEventHandler]
final readonly class ReloadAclEventHandler
{
    public function __construct(
        private Security $security,
        private AccessGroupRepository $accessGroupRepository,
        private ResourceAccessRepository $resourceAccessRepository,
    ) {
    }

    public function __invoke(AggregateCreated|AggregateUpdated $event): void
    {
        if (! $event->aggregate instanceof AclScopedInterface) {
            return;
        }

        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);

        if ($credentialUser->credential->hasUnrestrictedResourceAccess()) {
            $this->resourceAccessRepository->flagAllResourcesAsChanged();

            return;
        }

        $accessGroupIds = $this->accessGroupRepository->findActiveGroupIdsForUser($credentialUser->credential->userId);
        if (count($accessGroupIds) === 0) {
            return;
        }

        if ($event instanceof AggregateCreated) {
            $this->resourceAccessRepository->grantResourceAccess($event->aggregate, $accessGroupIds);
        }

        $this->accessGroupRepository->flagGroupsAsChanged($accessGroupIds);
    }
}
