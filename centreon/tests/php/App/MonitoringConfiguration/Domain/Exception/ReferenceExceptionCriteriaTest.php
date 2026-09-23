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

namespace Tests\App\MonitoringConfiguration\Domain\Exception;

use App\MonitoringConfiguration\Domain\Exception\HostCategoryNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostSeverityNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\PollerNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimezoneNotFoundException;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreateHostInput;
use App\Shared\Domain\Exception\AggregateNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReferenceExceptionCriteriaTest extends TestCase
{
    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('criteriaProvider')]
    public function testTheFactoryNamesTheCriterionAfterTheInputField(
        AggregateNotFoundException $exception,
        array $expected,
    ): void {
        self::assertSame($expected, $exception->criteria);

        // The criterion is what InvalidReferenceExceptionListener matches against the operation's
        // input DTO to decide on a 422; a rename that misses CreateHostInput degrades it silently
        // back to the exception's own status.
        foreach (array_keys($expected) as $criterion) {
            self::assertTrue(
                property_exists(CreateHostInput::class, $criterion),
                sprintf('"%s" must name a property of CreateHostInput.', $criterion),
            );
        }
    }

    /**
     * The counterpart: a criterion that is not a payload field is what keeps a missing target
     * aggregate answering 404 rather than 422.
     */
    public function testThePollerFactoryNamesNoInputField(): void
    {
        foreach (array_keys(new PollerNotFoundException(['id' => 1])->criteria) as $criterion) {
            self::assertFalse(property_exists(CreateHostInput::class, (string) $criterion));
        }
    }

    /**
     * @return iterable<string, array{AggregateNotFoundException, array<string, mixed>}>
     */
    public static function criteriaProvider(): iterable
    {
        yield 'categories' => [new HostCategoryNotFoundException([3]), ['categoryIds' => [3]]];

        yield 'severity' => [new HostSeverityNotFoundException(4), ['severityId' => 4]];

        yield 'timezone' => [new TimezoneNotFoundException(5), ['timezoneId' => 5]];

    }
}
