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

namespace App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro;

use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;

/**
 * @extends AggregateRoot<GlobalMacroId>
 */
final class GlobalMacro extends AggregateRoot
{
    /**
     * @param Collection<Poller> $pollers
     */
    public function __construct(
        ?GlobalMacroId $id,
        public readonly GlobalMacroName $name,
        public readonly GlobalMacroExpression $expression,
        public readonly ?GlobalMacroComment $comment,
        public readonly bool $isPassword,
        public readonly bool $activated,
        public readonly Collection $pollers,
    ) {
        parent::__construct($id);
    }

    public function addPoller(Poller $poller): void
    {
        if ($this->pollers->contains($poller)) {
            return;
        }

        $this->pollers->add($poller);
        $poller->addGlobalMacro($this);
    }
}
