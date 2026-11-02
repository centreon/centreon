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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceStalkingOptionEnum;
use App\Shared\Domain\Aggregate\TriStateEnum;
use PHPUnit\Framework\TestCase;

final class ServiceDataProcessingTest extends TestCase
{
    public function testDefaultsToUseDefaultAndNulls(): void
    {
        $dataProcessing = new ServiceDataProcessing();

        self::assertSame(TriStateEnum::UseDefault, $dataProcessing->checkFreshness);
        self::assertSame(TriStateEnum::UseDefault, $dataProcessing->flapDetectionEnabled);
        self::assertSame(TriStateEnum::UseDefault, $dataProcessing->eventHandlerEnabled);
        self::assertSame(TriStateEnum::UseDefault, $dataProcessing->obsessOverService);
        self::assertSame(TriStateEnum::UseDefault, $dataProcessing->retainStatusInformation);
        self::assertSame(TriStateEnum::UseDefault, $dataProcessing->retainNonStatusInformation);
        self::assertNull($dataProcessing->acknowledgmentTimeout);
        self::assertNull($dataProcessing->freshnessThreshold);
        self::assertNull($dataProcessing->lowFlapThreshold);
        self::assertNull($dataProcessing->highFlapThreshold);
        self::assertNull($dataProcessing->eventHandlerCommandId);
        self::assertSame([], $dataProcessing->eventHandlerArgs);
        self::assertSame([], $dataProcessing->stalkingOptions);
    }

    public function testAcceptsValidValues(): void
    {
        $dataProcessing = new ServiceDataProcessing(
            checkFreshness: TriStateEnum::True,
            flapDetectionEnabled: TriStateEnum::False,
            eventHandlerEnabled: TriStateEnum::True,
            obsessOverService: TriStateEnum::False,
            retainStatusInformation: TriStateEnum::True,
            retainNonStatusInformation: TriStateEnum::False,
            acknowledgmentTimeout: 1,
            freshnessThreshold: 0,
            lowFlapThreshold: 0,
            highFlapThreshold: 100,
            eventHandlerCommandId: new CommandId(42),
            eventHandlerArgs: ['warn', 'crit'],
            stalkingOptions: [ServiceStalkingOptionEnum::Ok, ServiceStalkingOptionEnum::Critical],
        );

        self::assertSame(TriStateEnum::False, $dataProcessing->obsessOverService);
        self::assertSame(TriStateEnum::True, $dataProcessing->retainStatusInformation);
        self::assertSame(TriStateEnum::False, $dataProcessing->retainNonStatusInformation);
        self::assertSame(1, $dataProcessing->acknowledgmentTimeout);
        self::assertSame(0, $dataProcessing->freshnessThreshold);
        self::assertSame(0, $dataProcessing->lowFlapThreshold);
        self::assertSame(100, $dataProcessing->highFlapThreshold);
        self::assertSame(42, $dataProcessing->eventHandlerCommandId?->value);
        self::assertSame(['warn', 'crit'], $dataProcessing->eventHandlerArgs);
        self::assertSame([ServiceStalkingOptionEnum::Ok, ServiceStalkingOptionEnum::Critical], $dataProcessing->stalkingOptions);
    }

    public function testRejectsAcknowledgmentTimeoutBelowOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceDataProcessing(acknowledgmentTimeout: 0);
    }

    public function testRejectsNegativeFreshnessThreshold(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceDataProcessing(freshnessThreshold: -1);
    }

    public function testRejectsLowFlapThresholdAboveOneHundred(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceDataProcessing(lowFlapThreshold: 101);
    }

    public function testRejectsLowFlapThresholdBelowZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceDataProcessing(lowFlapThreshold: -1);
    }

    public function testRejectsHighFlapThresholdAboveOneHundred(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceDataProcessing(highFlapThreshold: 101);
    }

    public function testRejectsHighFlapThresholdBelowZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceDataProcessing(highFlapThreshold: -1);
    }

    public function testRejectsAnEventHandlerArgContainingTheStorageDelimiter(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceDataProcessing(eventHandlerArgs: ['a!b']);
    }

    public function testRejectsAnEventHandlerArgContainingAnEscapeToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceDataProcessing(eventHandlerArgs: ['a#BR#b']);
    }

    /**
     * Legacy stores the options in this order whatever was ticked: the column must not get an order legacy
     * cannot write.
     */
    public function testItCollapsesDuplicatedStalkingOptionsAndNormalisesTheirOrder(): void
    {
        $dataProcessing = new ServiceDataProcessing(stalkingOptions: [
            ServiceStalkingOptionEnum::Critical,
            ServiceStalkingOptionEnum::Ok,
            ServiceStalkingOptionEnum::Unknown,
            ServiceStalkingOptionEnum::Critical,
            ServiceStalkingOptionEnum::Warning,
        ]);

        self::assertSame(
            [
                ServiceStalkingOptionEnum::Ok,
                ServiceStalkingOptionEnum::Warning,
                ServiceStalkingOptionEnum::Unknown,
                ServiceStalkingOptionEnum::Critical,
            ],
            $dataProcessing->stalkingOptions,
        );
    }
}
