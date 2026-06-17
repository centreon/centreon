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

namespace App\Upgrade\Domain\Event;

final readonly class UpgradeCompleted
{
    public function __construct(
        public string $fromVersion,
        public string $toVersion,
        public int $durationMs,
    ) {
        if (trim($fromVersion) === '') {
            throw new \InvalidArgumentException('The source version cannot be empty.');
        }
        if (trim($toVersion) === '') {
            throw new \InvalidArgumentException('The target version cannot be empty.');
        }
        if ($durationMs < 0) {
            throw new \InvalidArgumentException('The duration cannot be negative.');
        }
    }
}
