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

namespace Tests\App\Upgrade\Infrastructure\Double;

use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\Query\QueryBus;
use App\Upgrade\Domain\Model\MigrationOutcome;

/**
 * Records the rollback plan query and the rollback command in the order they are dispatched.
 */
final class RollbackBusSpy implements CommandBus, QueryBus
{
    /** @var list<object> */
    public array $dispatched = [];

    /**
     * @param list<string> $plan
     * @param list<string> $warnings
     */
    public function __construct(
        private readonly array $plan,
        private readonly array $warnings = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function ask(object $query): array
    {
        $this->dispatched[] = $query;

        return $this->plan;
    }

    public function execute(object $command): MigrationOutcome
    {
        $this->dispatched[] = $command;

        return new MigrationOutcome($this->plan, $this->warnings);
    }
}
