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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\NotificationContact;

use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactName;
use PHPUnit\Framework\TestCase;

final class NotificationContactNameTest extends TestCase
{
    public function testAcceptsAValidName(): void
    {
        self::assertSame('John Doe', (new NotificationContactName('John Doe'))->value);
    }

    public function testAcceptsExactlyTheMinLength(): void
    {
        self::assertSame('a', (new NotificationContactName('a'))->value);
    }

    public function testAcceptsExactlyTheMaxLength(): void
    {
        $name = str_repeat('a', NotificationContactName::MAX_LENGTH);

        self::assertSame($name, (new NotificationContactName($name))->value);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new NotificationContactName('');
    }

    public function testRejectsANameOverTheMaxLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new NotificationContactName(str_repeat('a', NotificationContactName::MAX_LENGTH + 1));
    }
}
