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

final readonly class UpgradeStepCompleted
{
    public function __construct(
        public string $version,
        public string $step,
        public int $durationMs,
    ) {
        if (trim($version) === '') {
            throw new \InvalidArgumentException('The version cannot be empty.');
        }
        if (trim($step) === '') {
            throw new \InvalidArgumentException('The step name cannot be empty.');
        }
        if ($durationMs < 0) {
            throw new \InvalidArgumentException('The duration cannot be negative.');
        }
    }
}
