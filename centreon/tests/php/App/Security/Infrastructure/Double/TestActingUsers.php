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

use App\Security\Domain\Aggregate\ActingUser;
use App\Security\Domain\Aggregate\UserId;

/**
 * Builds the acting user of a test: an unrestricted one escapes ACL scoping, a restricted one is scoped.
 */
final class TestActingUsers
{
    public static function unrestricted(int $id = 1): ActingUser
    {
        return new ActingUser(new UserId($id), unrestrictedResourceAccess: true);
    }

    public static function restricted(int $id): ActingUser
    {
        return new ActingUser(new UserId($id), unrestrictedResourceAccess: false);
    }
}
