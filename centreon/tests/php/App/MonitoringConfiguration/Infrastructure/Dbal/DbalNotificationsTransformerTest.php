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
use PHPUnit\Framework\TestCase;

final class DbalNotificationsTransformerTest extends TestCase
{
    public function testOptionsFromColumnReadsEveryEngineLetterBackToItsEnum(): void
    {
        self::assertSame(
            [
                NotificationOptionEnum::Down,
                NotificationOptionEnum::Unreachable,
                NotificationOptionEnum::Recovery,
                NotificationOptionEnum::Flapping,
                NotificationOptionEnum::DowntimeScheduled,
            ],
            DbalNotificationsTransformer::optionsFromColumn('d,u,r,f,s'),
        );
    }

    public function testOptionsFromColumnReadsTheNoneOption(): void
    {
        self::assertSame([NotificationOptionEnum::None], DbalNotificationsTransformer::optionsFromColumn('n'));
    }

    /**
     * NULL and '' both mean "no option set" (inherit from the template chain), kept distinct from
     * the real 'n' ("notify on nothing").
     */
    public function testOptionsFromColumnTreatsNullAndEmptyAsNoOption(): void
    {
        self::assertSame([], DbalNotificationsTransformer::optionsFromColumn(null));
        self::assertSame([], DbalNotificationsTransformer::optionsFromColumn(''));
    }

    public function testOptionsColumnRoundTripsThroughBothDirections(): void
    {
        $options = [NotificationOptionEnum::Down, NotificationOptionEnum::Recovery];

        $column = DbalNotificationsTransformer::options($options);
        self::assertSame('d,r', $column);
        self::assertSame($options, DbalNotificationsTransformer::optionsFromColumn($column));
    }

    public function testOptionsFromColumnRejectsAnUnknownLetter(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        DbalNotificationsTransformer::optionsFromColumn('x');
    }
}
