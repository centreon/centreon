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

namespace App\MonitoringConfiguration\Domain\Aggregate\Host;

use Webmozart\Assert\Assert;

/**
 * A host custom-macro name. The client supplies the short name (e.g. "MYMACRO"); the monitoring
 * engine and the `on_demand_macro_host` table know it as `$_HOST<NAME>$`, upper-cased — exactly
 * how legacy builds it (`'$_HOST' . strtoupper($name) . '$'`).
 */
final readonly class HostMacroName
{
    /** Length budget of the stored `$_HOST<NAME>$` form (on_demand_macro_host.host_macro_name). */
    public const MAX_STORAGE_LENGTH = 255;

    /** The upper-cased short name, without the `$_HOST`/`$` wrapper. */
    public string $value;

    public function __construct(string $value)
    {
        $value = mb_strtoupper(trim($value));
        Assert::notEmpty($value, 'The macro name cannot be empty.');
        // Same character set the engine accepts inside a $_HOST...$ macro (CentreonCommand's
        // extraction pattern is /\$_HOST([\w_-]+)\$/).
        Assert::regex($value, '/^[A-Z0-9_-]+$/', 'The macro name contains invalid characters.');
        Assert::maxLength('$_HOST' . $value . '$', self::MAX_STORAGE_LENGTH);

        $this->value = $value;
    }

    /**
     * The full macro name as stored and as referenced in commands: `$_HOST<NAME>$`.
     */
    public function toStorageName(): string
    {
        return '$_HOST' . $this->value . '$';
    }
}
