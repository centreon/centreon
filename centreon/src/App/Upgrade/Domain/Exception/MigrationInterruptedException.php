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
 * A Centreon Web set failed after others were migrated: a new run resumes with it.
 */
final class MigrationInterruptedException extends \RuntimeException
{
    /**
     * @param non-empty-list<string> $migrated the labels of the sets already migrated
     * @param list<string> $warnings what their migrations warned of
     */
    public function __construct(array $migrated, MigrationSet $failedSet, \Throwable $previous, array $warnings = [])
    {
        parent::__construct(sprintf(
            '%s. Already migrated: %s. A new run resumes with the %s.%s',
            rtrim($previous->getMessage(), '.'),
            implode(', ', $migrated),
            $failedSet->label(),
            $warnings === [] ? '' : sprintf(' Warnings: %s.', implode('; ', $warnings)),
        ), 0, $previous);
    }
}
