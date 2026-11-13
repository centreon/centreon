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

namespace App\Upgrade\Domain\Exception;

use App\Upgrade\Domain\Model\MigrationSet;

/**
 * The migrations to revert are not the confirmed ones anymore: nothing was reverted.
 */
final class RollbackPlanChangedException extends \RuntimeException
{
    /**
     * @param list<string> $planned what the rollback would now revert
     */
    public function __construct(
        MigrationSet $set,
        public readonly array $planned,
    ) {
        parent::__construct(sprintf(
            'The migrations of the %s changed since they were confirmed: it would now revert %s. Nothing was reverted.',
            $set->label(),
            $planned === [] ? 'none' : implode(', ', $planned),
        ));
    }
}
