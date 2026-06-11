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

namespace Tests\Centreon\Domain\Gorgone;

use Centreon\Domain\Gorgone\GorgoneTransport;
use Centreon\Domain\Option\Interfaces\OptionServiceInterface;
use Centreon\Domain\Option\Option;
use PHPUnit\Framework\TestCase;

class GorgoneTransportTest extends TestCase
{
    private function option(string $value): Option
    {
        return (new Option())->setName(GorgoneTransport::OPTION_KEY)->setValue($value);
    }

    private function transport(array $selectedOptions): GorgoneTransport
    {
        $optionService = $this->createMock(OptionServiceInterface::class);
        $optionService->method('findSelectedOptions')->willReturn($selectedOptions);

        return new GorgoneTransport($optionService);
    }

    public function testUseGorgoneIsFalseWhenOptionIsAbsent(): void
    {
        // The monolith-safe default: no option set => historical local transport.
        $this->assertFalse($this->transport([])->useGorgone());
    }

    public function testUseGorgoneIsTrueOnlyForGorgoneValue(): void
    {
        $this->assertTrue($this->transport([$this->option('gorgone')])->useGorgone());
    }

    public function testUseGorgoneIsFalseForAnyOtherValue(): void
    {
        $this->assertFalse($this->transport([$this->option('centcore')])->useGorgone());
    }

    public function testOptionIsReadOnlyOnceAcrossCalls(): void
    {
        $optionService = $this->createMock(OptionServiceInterface::class);
        $optionService->expects($this->once())
            ->method('findSelectedOptions')
            ->willReturn([$this->option('gorgone')]);

        $transport = new GorgoneTransport($optionService);
        $transport->useGorgone();
        $transport->useGorgone();
    }
}
