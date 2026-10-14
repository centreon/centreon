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
 * Identifies a macro within the table its {@see HostMacroParentEnum} points to: a direct or template
 * macro's `on_demand_macro_host.host_macro_id`, a check-command macro's
 * `on_demand_macro_command.command_macro_id`. Only meaningful together with that parent.
 */
final readonly class HostMacroId
{
    public function __construct(public int $value)
    {
        Assert::positiveInteger($value);
    }
}
