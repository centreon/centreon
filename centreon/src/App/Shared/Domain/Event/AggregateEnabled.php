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
 * The aggregate's activation was turned on.
 *
 * A specialization of {@see AggregateUpdated}: side-effect handlers that react to any
 * configuration change (poller reload, ACL recompute) type-hint the supertype and therefore
 * catch this automatically, while the activity log distinguishes it to record a dedicated
 * "enable" line instead of a generic "update".
 */
abstract readonly class AggregateEnabled extends AggregateUpdated
{
}
