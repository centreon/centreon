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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Media;

use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaDirectory;
use PHPUnit\Framework\TestCase;

final class MediaDirectoryTest extends TestCase
{
    public function testAcceptsAValidDirectory(): void
    {
        self::assertSame('general', (new MediaDirectory('general'))->value);
    }

    public function testAcceptsDashesAndUnderscores(): void
    {
        self::assertSame('my-folder_1', (new MediaDirectory('my-folder_1'))->value);
    }

    public function testAcceptsExactlyTheMaxLength(): void
    {
        $directory = str_repeat('a', MediaDirectory::MAX_LENGTH);

        self::assertSame($directory, (new MediaDirectory($directory))->value);
    }

    public function testRejectsAnEmptyDirectory(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MediaDirectory('');
    }

    public function testRejectsADirectoryOverTheMaxLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MediaDirectory(str_repeat('a', MediaDirectory::MAX_LENGTH + 1));
    }

    public function testRejectsASlash(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MediaDirectory('foo/bar');
    }

    public function testRejectsASpace(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MediaDirectory('foo bar');
    }
}
