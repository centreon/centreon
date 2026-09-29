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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Double;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Service\HostServiceDuplicator;

final class FakeHostServiceDuplicator implements HostServiceDuplicator
{
    /** @var list<array{sourceHostId: int, newHostId: int}> */
    public array $duplicateCalls = [];

    public bool $duplicateThrows = false;

    public function duplicate(HostId $sourceHostId, HostId $newHostId): void
    {
        $this->duplicateCalls[] = ['sourceHostId' => $sourceHostId->value, 'newHostId' => $newHostId->value];

        if ($this->duplicateThrows) {
            throw new \RuntimeException('Unable to duplicate services');
        }
    }
}
