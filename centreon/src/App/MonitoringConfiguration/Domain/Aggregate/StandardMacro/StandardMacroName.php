<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace App\MonitoringConfiguration\Domain\Aggregate\StandardMacro;

use Webmozart\Assert\Assert;

final readonly class StandardMacroName
{
    public const NAMING_VALIDATION_REGEX = '/^\$.*\$$/';

    public function __construct(public string $value)
    {
        Assert::maxLength($value, 255);
        Assert::regex($value, self::NAMING_VALIDATION_REGEX);
    }
}
