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

namespace App\MonitoringConfiguration\Domain\Aggregate\Service;

use Webmozart\Assert\Assert;

/**
 * A service's own check intervals, in engine interval units. Null means unset: the service then inherits the
 * interval from its templates.
 */
final readonly class ServiceCheckIntervals
{
    public function __construct(
        public ?int $normal = null,
        public ?int $retry = null,
    ) {
        Assert::nullOrGreaterThanEq($normal, 1);
        Assert::nullOrGreaterThanEq($retry, 1);
    }
}
