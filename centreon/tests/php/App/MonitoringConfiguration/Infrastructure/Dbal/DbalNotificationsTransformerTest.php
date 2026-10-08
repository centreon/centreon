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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalNotificationsTransformer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DbalNotificationsTransformerTest extends TestCase
{
    /**
     * @param list<NotificationOptionEnum> $expected
     */
    #[DataProvider('columns')]
    public function testOptionsFromColumn(?string $column, array $expected): void
    {
        self::assertSame($expected, DbalNotificationsTransformer::optionsFromColumn($column));
    }

    /**
     * @return iterable<string, array{?string, list<NotificationOptionEnum>}>
     */
    public static function columns(): iterable
    {
        yield 'null' => [null, []];

        yield 'empty' => ['', []];

        yield 'none alone' => ['n', [NotificationOptionEnum::None]];

        yield 'every option' => [
            'd,u,r,f,s',
            [
                NotificationOptionEnum::Down,
                NotificationOptionEnum::Unreachable,
                NotificationOptionEnum::Recovery,
                NotificationOptionEnum::Flapping,
                NotificationOptionEnum::DowntimeScheduled,
            ],
        ];

        yield 'padded letters' => ['d, u', [NotificationOptionEnum::Down, NotificationOptionEnum::Unreachable]];

        yield 'duplicated letter' => ['d,d', [NotificationOptionEnum::Down]];

        yield 'none next to an option' => ['d,n', [NotificationOptionEnum::Down]];

        yield 'none in the middle' => [
            'd,n,u',
            [NotificationOptionEnum::Down, NotificationOptionEnum::Unreachable],
        ];

        yield 'unknown letter' => ['d,x', [NotificationOptionEnum::Down]];

        yield 'only unknown letters' => ['x,y', []];
    }
}
