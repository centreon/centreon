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

use App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\NotificationOptionEnumResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The strings are the public API contract: pinning each one catches two swapped match arms,
 * which a round trip alone would not.
 */
final class NotificationOptionEnumResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{NotificationOptionEnum, string}>
     */
    public static function optionProvider(): iterable
    {
        yield 'down' => [NotificationOptionEnum::Down, 'down'];

        yield 'unreachable' => [NotificationOptionEnum::Unreachable, 'unreachable'];

        yield 'recovery' => [NotificationOptionEnum::Recovery, 'recovery'];

        yield 'flapping' => [NotificationOptionEnum::Flapping, 'flapping'];

        yield 'downtime scheduled' => [NotificationOptionEnum::DowntimeScheduled, 'downtime_scheduled'];

        yield 'none' => [NotificationOptionEnum::None, 'none'];
    }

    #[DataProvider('optionProvider')]
    public function testItMapsEachOptionToItsApiString(NotificationOptionEnum $option, string $apiValue): void
    {
        self::assertSame($apiValue, NotificationOptionEnumResolver::toString($option));
    }

    #[DataProvider('optionProvider')]
    public function testItMapsEachApiStringBackToItsOption(NotificationOptionEnum $option, string $apiValue): void
    {
        self::assertSame($option, NotificationOptionEnumResolver::toDomain($apiValue));
    }

    public function testItRejectsAnUnknownApiString(): void
    {
        $this->expectException(\ValueError::class);

        NotificationOptionEnumResolver::toDomain('d');
    }
}
