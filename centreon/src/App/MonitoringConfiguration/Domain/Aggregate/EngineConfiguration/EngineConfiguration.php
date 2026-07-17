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

namespace App\MonitoringConfiguration\Domain\Aggregate\EngineConfiguration;

use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * @extends AggregateRoot<EngineConfigurationId>
 */
final class EngineConfiguration extends AggregateRoot
{
    public function __construct(
        ?EngineConfigurationId $engineConfigurationId,
        public readonly PollerId $pollerId,
        public readonly string $name,
        public readonly bool $isActivated,
        public readonly CheckExecutionOptions $checkExecution,
        public readonly FreshnessAndFlapOptions $freshnessAndFlap,
        public readonly LoggingOptions $logging,
        public readonly RetentionOptions $retention,
        public readonly SchedulingOptions $scheduling,
        public readonly BrokerOptions $broker,
        public readonly MiscOptions $misc,
    ) {
        parent::__construct($engineConfigurationId);
    }

    public static function createDefault(PollerId $pollerId, string $pollerName): self
    {
        $slug = self::slugify($pollerName);

        return new self(
            engineConfigurationId: null,
            pollerId: $pollerId,
            name: $pollerName,
            isActivated: true,
            checkExecution: new CheckExecutionOptions(),
            freshnessAndFlap: new FreshnessAndFlapOptions(),
            logging: new LoggingOptions(),
            retention: new RetentionOptions(),
            scheduling: new SchedulingOptions(),
            broker: new BrokerOptions(
                brokerModuleCfgFile: sprintf('/etc/centreon-broker/%s-module.json', $slug),
            ),
            misc: new MiscOptions(),
        );
    }

    private static function slugify(string $name): string
    {
        return mb_strtolower(str_replace(' ', '-', $name));
    }
}
