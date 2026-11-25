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

namespace App\MonitoringConfiguration\Domain\Aggregate\HostSeverity;

use Webmozart\Assert\Assert;

/**
 * Bounds follow the signed `hostcategories.level` TINYINT column, not the legacy form's 1..127: CLAPI,
 * the Core API and the form before that rule stored levels of 0 or below, which must still be readable.
 */
final readonly class HostSeverityLevel
{
    public const MIN_VALUE = -128;
    public const MAX_VALUE = 127;

    public function __construct(
        public int $value,
    ) {
        Assert::range($value, self::MIN_VALUE, self::MAX_VALUE);
    }
}
