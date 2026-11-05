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

namespace App\MonitoringConfiguration\Domain\Aggregate\HostTemplate;

use Webmozart\Assert\Assert;

/**
 * The short, upper-cased name of a host template custom macro; stored and referenced in commands as
 * `$_HOST<NAME>$`.
 */
final readonly class HostTemplateMacroName
{
    public const MAX_STORAGE_LENGTH = 255;

    public string $value;

    public function __construct(string $value)
    {
        $value = mb_strtoupper(trim($value));
        Assert::notEmpty($value, 'The macro name cannot be empty.');
        Assert::maxLength('$_HOST' . $value . '$', self::MAX_STORAGE_LENGTH);

        $this->value = $value;
    }

    public function toStorageName(): string
    {
        return '$_HOST' . $this->value . '$';
    }
}
