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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver;

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceStalkingOptionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\ServiceStalkingOptionEnumResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The strings are the public API contract: pinning each one catches two swapped match arms.
 */
final class ServiceStalkingOptionEnumResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{ServiceStalkingOptionEnum, string}>
     */
    public static function optionProvider(): iterable
    {
        yield 'ok' => [ServiceStalkingOptionEnum::Ok, 'ok'];

        yield 'warning' => [ServiceStalkingOptionEnum::Warning, 'warning'];

        yield 'unknown' => [ServiceStalkingOptionEnum::Unknown, 'unknown'];

        yield 'critical' => [ServiceStalkingOptionEnum::Critical, 'critical'];
    }

    #[DataProvider('optionProvider')]
    public function testItMapsEachOptionToItsApiString(ServiceStalkingOptionEnum $option, string $apiValue): void
    {
        self::assertSame($apiValue, ServiceStalkingOptionEnumResolver::toString($option));
    }

    public function testItPublishesTheApiStringOfEveryOptionInTheEnumOrder(): void
    {
        self::assertSame(
            array_map(ServiceStalkingOptionEnumResolver::toString(...), ServiceStalkingOptionEnum::cases()),
            ServiceStalkingOptionEnumResolver::API_VALUES,
        );
    }

    #[DataProvider('optionProvider')]
    public function testItMapsEachApiStringBackToItsOption(ServiceStalkingOptionEnum $option, string $apiValue): void
    {
        self::assertSame($option, ServiceStalkingOptionEnumResolver::toDomain($apiValue));
    }

    public function testItRejectsAnUnknownApiString(): void
    {
        $this->expectException(\ValueError::class);

        ServiceStalkingOptionEnumResolver::toDomain('down');
    }
}
