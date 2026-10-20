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

namespace App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\Shared\Domain\NoValue;

/**
 * What a partial update changes in how a host is checked: its check command and the arguments it is called with.
 *
 * - `NoValue`: the caller did not provide the field, the stored value stays as it is;
 * - `null` (command only): the host no longer has a check command of its own. Its arguments are
 *   cleared with it, unless the same update provides new ones;
 * - a value: it replaces the stored one. Providing only arguments keeps the current command.
 *
 * The host's custom macros are not part of this object: they are changed separately.
 */
final readonly class CheckOptionsChanges
{
    /**
     * @param NoValue|list<string> $args
     */
    public function __construct(
        public NoValue|CommandId|null $checkCommandId = new NoValue(),
        public NoValue|array $args = new NoValue(),
    ) {
    }

    public function applyTo(CheckOptions $current): CheckOptions
    {
        return $current->with(checkCommandId: $this->checkCommandId, args: $this->args);
    }
}
