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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity;

use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServiceSeverityLevelTest extends TestCase
{
    // Literal bounds: they must equal the range of the signed `service_categories.level` TINYINT column.
    /**
     * @return iterable<string, array{int}>
     */
    public static function provideBounds(): iterable
    {
        yield 'minimum' => [-128];

        yield 'maximum' => [127];
    }

    #[DataProvider('provideBounds')]
    public function testItAcceptsTheBounds(int $value): void
    {
        self::assertSame($value, (new ServiceSeverityLevel($value))->value);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideOutOfBounds(): iterable
    {
        yield 'one under the minimum' => [-129];

        yield 'one over the maximum' => [128];
    }

    #[DataProvider('provideOutOfBounds')]
    public function testItRejectsOutOfBounds(int $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceSeverityLevel($value);
    }
}
