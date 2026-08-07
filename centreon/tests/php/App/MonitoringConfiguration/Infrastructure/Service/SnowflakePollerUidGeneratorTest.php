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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Service;

use App\MonitoringConfiguration\Infrastructure\Service\SnowflakePollerUidGenerator;
use PHPUnit\Framework\TestCase;

final class SnowflakePollerUidGeneratorTest extends TestCase
{
    public function testGenerateReturnsPollerUid(): void
    {
        $generator = new SnowflakePollerUidGenerator();

        $uid = $generator->generate();

        self::assertGreaterThan(0, $uid->value);
    }

    public function testGenerateReturnsUniqueIds(): void
    {
        $generator = new SnowflakePollerUidGenerator();

        $uid1 = $generator->generate();
        $uid2 = $generator->generate();

        self::assertNotSame($uid1->value, $uid2->value);
    }

    public function testGenerateReturnsIncreasingIds(): void
    {
        $generator = new SnowflakePollerUidGenerator();

        $uid1 = $generator->generate();
        $uid2 = $generator->generate();

        self::assertGreaterThan($uid1->value, $uid2->value);
    }
}
