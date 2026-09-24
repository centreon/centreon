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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use App\MonitoringConfiguration\Domain\Aggregate\Host\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodName;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostSchedulingOptionsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostTimePeriodOutput;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements TransformerInterface<SchedulingOptions, HostSchedulingOptionsOutput>
 */
final readonly class HostSchedulingOptionsOutputTransformer implements TransformerInterface
{
    public function __construct(
        private TimePeriodRepository $timePeriodRepository,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
    ) {
    }

    public function transform(mixed $from, array $extraData = []): HostSchedulingOptionsOutput
    {
        return new HostSchedulingOptionsOutput(
            checkPeriod: $this->resolveCheckPeriod($from->checkTimeperiodId),
            maxCheckAttempts: $from->maxCheckAttempts,
            normalCheckInterval: $from->normalCheckInterval,
            retryCheckInterval: $from->retryCheckInterval,
            activeCheckEnabled: $this->isCloudPlatform ? null : $from->activeCheckEnabled,
            passiveCheckEnabled: $this->isCloudPlatform ? null : $from->passiveCheckEnabled,
        );
    }

    private function resolveCheckPeriod(?TimePeriodId $checkTimeperiodId): ?HostTimePeriodOutput
    {
        if (! $checkTimeperiodId instanceof TimePeriodId) {
            return null;
        }

        $name = $this->timePeriodRepository
            ->findNamesByIds(new Collection([$checkTimeperiodId], TimePeriodId::class))
            ->toArray()[$checkTimeperiodId->value] ?? null;

        return $name instanceof TimePeriodName
            ? new HostTimePeriodOutput($checkTimeperiodId->value, $name->value)
            : null;
    }
}
