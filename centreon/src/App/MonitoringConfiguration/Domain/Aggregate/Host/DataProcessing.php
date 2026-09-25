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

namespace App\MonitoringConfiguration\Domain\Aggregate\Host;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use Webmozart\Assert\Assert;

/**
 * The "Data Processing" group of a host: freshness, flap detection and event handler directives.
 *
 * Platform availability (acknowledgment timeout / flap detection / flap thresholds / event handler
 * args are on-premise only) is a contract concern enforced at the API boundary, not here: this VO
 * stays platform-agnostic. The three-state directives default to {@see TriStateEnum::UseDefault}
 * (inherit), never null.
 */
final readonly class DataProcessing
{
    /**
     * @param list<string> $eventHandlerArgs
     */
    public function __construct(
        public TriStateEnum $checkFreshness = TriStateEnum::UseDefault,
        public TriStateEnum $flapDetectionEnabled = TriStateEnum::UseDefault,
        public TriStateEnum $eventHandlerEnabled = TriStateEnum::UseDefault,
        public ?int $acknowledgmentTimeout = null,
        public ?int $freshnessThreshold = null,
        public ?int $lowFlapThreshold = null,
        public ?int $highFlapThreshold = null,
        public ?CommandId $eventHandlerCommandId = null,
        public array $eventHandlerArgs = [],
    ) {
        if ($acknowledgmentTimeout !== null) {
            Assert::greaterThanEq($acknowledgmentTimeout, 1, 'DataProcessing::acknowledgmentTimeout expected to be >= 1, got %s.');
        }
        if ($freshnessThreshold !== null) {
            Assert::greaterThanEq($freshnessThreshold, 0, 'DataProcessing::freshnessThreshold expected to be >= 0, got %s.');
        }
        if ($lowFlapThreshold !== null) {
            Assert::range($lowFlapThreshold, 0, 100, 'DataProcessing::lowFlapThreshold expected to be between 0 and 100, got %s.');
        }
        if ($highFlapThreshold !== null) {
            Assert::range($highFlapThreshold, 0, 100, 'DataProcessing::highFlapThreshold expected to be between 0 and 100, got %s.');
        }
        Assert::allString($eventHandlerArgs);
        // Storage bang-joins the arguments and encodes \n\t\r as #BR#/#T#/#R# (CommandArgumentsFormatter),
        // matching legacy. The legacy read path splits on '!' and decodes those tokens, so an argument
        // carrying the '!' delimiter or a literal #BR#/#T#/#R# would not round-trip; raw \n\t\r are fine
        // because the formatter encodes them. Same rule as CheckOptions::$args.
        foreach (['!', '#BR#', '#T#', '#R#'] as $reserved) {
            Assert::allNotContains($eventHandlerArgs, $reserved, 'DataProcessing::eventHandlerArgs must not contain the "!" delimiter or a #BR#/#T#/#R# escape token.');
        }
    }
}
