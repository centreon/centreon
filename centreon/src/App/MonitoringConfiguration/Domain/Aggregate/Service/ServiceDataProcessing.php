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

namespace App\MonitoringConfiguration\Domain\Aggregate\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\CommandArguments;
use App\MonitoringConfiguration\Domain\Aggregate\DataProcessing;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Logging\Attribute\Sensitive;
use Webmozart\Assert\Assert;

/**
 * The service's freshness, flap detection, event handler, obsession, retention and stalking directives.
 *
 * The three-state directives default to {@see TriStateEnum::UseDefault}, never null.
 */
final readonly class ServiceDataProcessing
{
    /** Seconds a Centreon Monitoring Agent service may stay silent. */
    public const CENTREON_MONITORING_AGENT_FRESHNESS_THRESHOLD = DataProcessing::CENTREON_MONITORING_AGENT_FRESHNESS_THRESHOLD;

    /** @var list<string> */
    #[Sensitive]
    public array $eventHandlerArgs;

    /** @var list<ServiceStalkingOptionEnum> */
    public array $stalkingOptions;

    /**
     * @param array<int, string> $eventHandlerArgs ordered event handler arguments, empty when they are all empty
     * @param list<ServiceStalkingOptionEnum> $stalkingOptions duplicates are collapsed
     */
    public function __construct(
        public TriStateEnum $checkFreshness = TriStateEnum::UseDefault,
        public TriStateEnum $flapDetectionEnabled = TriStateEnum::UseDefault,
        public TriStateEnum $eventHandlerEnabled = TriStateEnum::UseDefault,
        public TriStateEnum $obsessOverService = TriStateEnum::UseDefault,
        public TriStateEnum $retainStatusInformation = TriStateEnum::UseDefault,
        public TriStateEnum $retainNonStatusInformation = TriStateEnum::UseDefault,
        public ?int $acknowledgmentTimeout = null,
        public ?int $freshnessThreshold = null,
        public ?int $lowFlapThreshold = null,
        public ?int $highFlapThreshold = null,
        public ?CommandId $eventHandlerCommandId = null,
        array $eventHandlerArgs = [],
        array $stalkingOptions = [],
    ) {
        Assert::nullOrGreaterThanEq($acknowledgmentTimeout, 1, 'ServiceDataProcessing::acknowledgmentTimeout expected to be >= 1, got %s.');
        Assert::nullOrGreaterThanEq($freshnessThreshold, 0, 'ServiceDataProcessing::freshnessThreshold expected to be >= 0, got %s.');
        Assert::nullOrRange($lowFlapThreshold, 0, 100, 'ServiceDataProcessing::lowFlapThreshold expected to be between 0 and 100, got %s.');
        Assert::nullOrRange($highFlapThreshold, 0, 100, 'ServiceDataProcessing::highFlapThreshold expected to be between 0 and 100, got %s.');
        CommandArguments::assertValid($eventHandlerArgs, 'ServiceDataProcessing::eventHandlerArgs');
        // All empty is no argument, as legacy stores it: written NULL, read back empty.
        $this->eventHandlerArgs = array_all($eventHandlerArgs, static fn (string $arg): bool => $arg === '')
            ? []
            : array_values($eventHandlerArgs);

        // Normalised to the enum's own declaration order, the order legacy always writes (o,w,u,c).
        $requested = [];
        foreach ($stalkingOptions as $option) {
            $requested[$option->name] = true;
        }
        $this->stalkingOptions = array_values(array_filter(
            ServiceStalkingOptionEnum::cases(),
            static fn (ServiceStalkingOptionEnum $option): bool => isset($requested[$option->name]),
        ));
    }

    /**
     * A Centreon Monitoring Agent pushes its results, so freshness is forced on with a fixed threshold. Write side
     * only: a read returns what is stored.
     */
    public function withCentreonMonitoringAgentFreshness(): self
    {
        return new self(
            checkFreshness: TriStateEnum::True,
            flapDetectionEnabled: $this->flapDetectionEnabled,
            eventHandlerEnabled: $this->eventHandlerEnabled,
            obsessOverService: $this->obsessOverService,
            retainStatusInformation: $this->retainStatusInformation,
            retainNonStatusInformation: $this->retainNonStatusInformation,
            acknowledgmentTimeout: $this->acknowledgmentTimeout,
            freshnessThreshold: self::CENTREON_MONITORING_AGENT_FRESHNESS_THRESHOLD,
            lowFlapThreshold: $this->lowFlapThreshold,
            highFlapThreshold: $this->highFlapThreshold,
            eventHandlerCommandId: $this->eventHandlerCommandId,
            eventHandlerArgs: $this->eventHandlerArgs,
            stalkingOptions: $this->stalkingOptions,
        );
    }
}
