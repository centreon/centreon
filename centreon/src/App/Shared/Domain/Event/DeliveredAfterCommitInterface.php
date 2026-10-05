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

namespace App\Shared\Domain\Event;

/**
 * Marks an event whose handlers run once the command that fired it has committed, instead of
 * inside its transaction. Mark one only when its handler cannot see uncommitted state, typically
 * because it reaches a separate database connection.
 *
 * Such a handler must not throw: nothing can be rolled back at that point.
 */
interface DeliveredAfterCommitInterface
{
}
