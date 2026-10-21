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

use App\Upgrade\Application\MigrationRunner;
use App\Upgrade\Domain\MigrationSet;

final class FakeMigrationRunner implements MigrationRunner
{
    /** @var list<string> names of the sets whose migration fails */
    public array $failingSets = [];

    /**
     * @param \ArrayObject<int, string> $journal ordered calls, shareable with other doubles to assert sequencing
     */
    public function __construct(
        public \ArrayObject $journal = new \ArrayObject(),
    ) {
    }

    public function migrate(MigrationSet $set): array
    {
        $this->journal[] = 'migrate:' . $set->name;

        if (\in_array($set->name, $this->failingSets, true)) {
            throw new \RuntimeException(\sprintf('Migration of "%s" failed', $set->name));
        }

        return [$set->name . '\Version1'];
    }

    public function rollback(MigrationSet $set, string $versionOrAlias): array
    {
        $this->journal[] = 'rollback:' . $set->name . ':' . $versionOrAlias;

        return [$set->name . '\Version1'];
    }

    public function status(?MigrationSet $set = null): array
    {
        return [];
    }
}
