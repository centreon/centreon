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
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodName;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostSchedulingOptionsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\TimePeriod\TimePeriodResource;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @phpstan-type ExtraDataTypeAlias array{timePeriodNames?: array<int, TimePeriodName>}
 *
 * @implements TransformerInterface<SchedulingOptions, HostSchedulingOptionsOutput, ExtraDataTypeAlias>
 */
final readonly class HostSchedulingOptionsOutputTransformer implements TransformerInterface
{
    public function __construct(
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
    ) {
    }

    public function transform(mixed $from, array $extraData = []): HostSchedulingOptionsOutput
    {
        Assert::keyExists($extraData, 'timePeriodNames');

        $periodId = $from->checkTimeperiodId;
        $checkPeriod = $periodId !== null && isset($extraData['timePeriodNames'][$periodId->value])
            ? new TimePeriodResource($periodId->value, $extraData['timePeriodNames'][$periodId->value]->value)
            : null;

        return new HostSchedulingOptionsOutput(
            checkPeriod: $checkPeriod,
            maxCheckAttempts: $from->maxCheckAttempts,
            normalCheckInterval: $from->normalCheckInterval,
            retryCheckInterval: $from->retryCheckInterval,
            activeCheckEnabled: $this->isCloudPlatform ? null : $from->activeCheckEnabled,
            passiveCheckEnabled: $this->isCloudPlatform ? null : $from->passiveCheckEnabled,
        );
    }
}
