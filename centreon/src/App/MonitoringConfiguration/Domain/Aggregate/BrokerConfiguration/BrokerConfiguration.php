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

namespace App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration;

use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;
use Webmozart\Assert\Assert;

/**
 * A centreon-broker configuration for a poller (`cfg_centreonbroker` + `cfg_centreonbroker_info`
 * + `cfg_centreonbroker_log`).
 *
 * Default construction (the `{slug}-module` config with its central-module output flow + loggers)
 * is owned by {@see \App\MonitoringConfiguration\Domain\Factory\BrokerConfigurationFactory}.
 *
 * @extends AggregateRoot<BrokerConfigurationId>
 */
final class BrokerConfiguration extends AggregateRoot
{
    /**
     * @param Collection<BrokerInputOutput> $flows the input/output flows (`cfg_centreonbroker_info`)
     * @param Collection<BrokerLog> $logs the logger category/level rows (`cfg_centreonbroker_log`)
     */
    public function __construct(
        ?BrokerConfigurationId $brokerConfigurationId,
        public readonly PollerId $pollerId,
        public readonly BrokerConfigName $name,
        public readonly BrokerConfigFileName $fileName,
        public readonly bool $isActivated,
        public readonly bool $daemon,
        public readonly bool $configWriteTimestamp,
        public readonly bool $configWriteThreadId,
        public readonly int $eventQueueMaxSize,
        public readonly string $commandFile,
        public readonly string $cacheDirectory,
        public readonly string $logDirectory,
        public readonly bool $statsActivate,
        public readonly Collection $flows,
        public readonly Collection $logs,
    ) {
        Assert::minCount($logs, 1, 'A broker configuration must define at least one logger.');
        Assert::minCount($flows, 1, 'A broker configuration must define at least one flow.');
        Assert::positiveInteger($eventQueueMaxSize, 'The broker event queue max size must be a positive integer.');

        parent::__construct($brokerConfigurationId);
    }
}
