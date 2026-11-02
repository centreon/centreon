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

namespace Tests\Centreon\Application\Controller\Administration;

use Centreon\Application\Controller\Administration\ParametersController;
use Centreon\Domain\Option\Interfaces\OptionServiceInterface;
use Centreon\Domain\Option\Option;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParametersControllerTest extends TestCase
{
    /**
     * @return array<string, array{0: string|null, 1: int}>
     */
    public static function storedLimitPerPageProvider(): array
    {
        return [
            'a positive integer' => ['50', 50],
            'surrounding spaces' => [' 12 ', 12],
            'zero' => ['0', 30],
            'a negative number' => ['-5', 30],
            'a text' => ['abc', 30],
            'the string the legacy form stores for an empty field' => ['NULL', 30],
            'an empty string' => ['', 30],
            'no value' => [null, 30],
            'an integer followed by text' => ['10abc', 30],
            'a decimal number' => ['12.7', 30],
            'an exponent' => ['1e3', 30],
        ];
    }

    #[DataProvider('storedLimitPerPageProvider')]
    public function testLimitPerPageIsTheStoredPositiveIntegerOrThirty(?string $stored, int $expected): void
    {
        $option = (new Option())->setName('maxViewConfiguration')->setValue($stored);

        $this->assertSame($expected, $this->getLimitPerPage([$option]));
    }

    public function testLimitPerPageIsThirtyWhenNothingIsStored(): void
    {
        $this->assertSame(30, $this->getLimitPerPage([]));
    }

    /**
     * @param list<Option> $options
     */
    private function getLimitPerPage(array $options): int
    {
        $optionService = $this->createMock(OptionServiceInterface::class);
        $optionService->method('findSelectedOptions')->willReturn($options);

        return (new ParametersController($optionService))->getParameters()->getData()['default_limit_per_page'];
    }
}
