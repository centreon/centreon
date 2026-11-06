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

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotificationOptionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\ServiceNotificationOptionEnumResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The strings are the public API contract: pinning each one catches two swapped match arms.
 */
final class ServiceNotificationOptionEnumResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{ServiceNotificationOptionEnum, string}>
     */
    public static function optionProvider(): iterable
    {
        yield 'warning' => [ServiceNotificationOptionEnum::Warning, 'warning'];

        yield 'unknown' => [ServiceNotificationOptionEnum::Unknown, 'unknown'];

        yield 'critical' => [ServiceNotificationOptionEnum::Critical, 'critical'];

        yield 'recovery' => [ServiceNotificationOptionEnum::Recovery, 'recovery'];

        yield 'flapping' => [ServiceNotificationOptionEnum::Flapping, 'flapping'];

        yield 'downtime scheduled' => [ServiceNotificationOptionEnum::DowntimeScheduled, 'downtime_scheduled'];

        yield 'none' => [ServiceNotificationOptionEnum::None, 'none'];
    }

    #[DataProvider('optionProvider')]
    public function testItMapsEachOptionToItsApiString(ServiceNotificationOptionEnum $option, string $apiValue): void
    {
        self::assertSame($apiValue, ServiceNotificationOptionEnumResolver::toString($option));
    }

    public function testItPublishesTheApiStringOfEveryOptionInTheEnumOrder(): void
    {
        self::assertSame(
            array_map(ServiceNotificationOptionEnumResolver::toString(...), ServiceNotificationOptionEnum::cases()),
            ServiceNotificationOptionEnumResolver::API_VALUES,
        );
    }

    #[DataProvider('optionProvider')]
    public function testItMapsEachApiStringBackToItsOption(ServiceNotificationOptionEnum $option, string $apiValue): void
    {
        self::assertSame($option, ServiceNotificationOptionEnumResolver::toDomain($apiValue));
    }

    public function testItRejectsAnUnknownApiString(): void
    {
        $this->expectException(\ValueError::class);

        ServiceNotificationOptionEnumResolver::toDomain('down');
    }
}
