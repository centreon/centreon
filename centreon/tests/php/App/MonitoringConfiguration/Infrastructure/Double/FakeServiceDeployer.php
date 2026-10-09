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
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Service\ServiceDeployer;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Domain\Collection;

final class FakeServiceDeployer implements ServiceDeployer
{
    /** @var list<array{hostId: int, requestedBy: int}> */
    public array $deployCalls = [];

    public bool $deployThrows = false;

    /** @var list<array{hostId: int, previousTemplateIds: list<int>, templateIds: list<int>, requestedBy: int}> */
    public array $removeCalls = [];

    public bool $removeThrows = false;

    public function deployFromTemplates(HostId $hostId, UserId $requestedBy): void
    {
        $this->deployCalls[] = ['hostId' => $hostId->value, 'requestedBy' => $requestedBy->value];

        if ($this->deployThrows) {
            throw new \RuntimeException('Unable to deploy services');
        }
    }

    public function removeFromRemovedTemplates(
        HostId $hostId,
        Collection $previousTemplateIds,
        Collection $templateIds,
        UserId $requestedBy,
    ): void {
        $this->removeCalls[] = [
            'hostId' => $hostId->value,
            'previousTemplateIds' => array_values(array_map(static fn (HostTemplateId $id): int => $id->value, $previousTemplateIds->toArray())),
            'templateIds' => array_values(array_map(static fn (HostTemplateId $id): int => $id->value, $templateIds->toArray())),
            'requestedBy' => $requestedBy->value,
        ];

        if ($this->removeThrows) {
            throw new \RuntimeException('Unable to remove services');
        }
    }
}
