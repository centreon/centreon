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

namespace App\Shared\Domain\Aggregate;

/**
 * Marks an aggregate as subject to Resource Access ACL scoping: creating one must seed
 * `centreon_acl` for the creator and flag the relevant ACL tables so the `centAcl` cron
 * recomputes them, or a non-admin creator would not see their own new resource until the
 * next cron run. Purely a marker — {@see \App\Security\Application\EventHandler\ReloadAclEventHandler}
 * checks `$event->aggregate instanceof AclScopedInterface` to decide whether to act.
 */
interface AclScopedInterface
{
}
