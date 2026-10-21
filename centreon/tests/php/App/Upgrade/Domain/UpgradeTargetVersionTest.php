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

namespace Tests\App\Upgrade\Domain;

use App\Upgrade\Domain\UpgradeTargetVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UpgradeTargetVersionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool, string}>
     */
    public static function provideVersions(): iterable
    {
        yield 'the platform version, ahead of the legacy scripts' => ['26.11.0', '26.09.1', false, '26.11.0'];

        yield 'a legacy hotfix ahead of the platform version' => ['26.11.0', '26.11.1', false, '26.11.1'];

        yield 'the same version' => ['26.11.0', '26.11.0', false, '26.11.0'];

        yield 'the legacy version while Update-next.php is pending' => ['26.11.0', '26.09.1', true, '26.09.1'];
    }

    #[DataProvider('provideVersions')]
    public function testResolvesTheVersionReachedByTheUpgrade(string $platformVersion, string $legacyVersion, bool $nextUpdatePending, string $expected): void
    {
        self::assertSame($expected, UpgradeTargetVersion::resolve($platformVersion, $legacyVersion, $nextUpdatePending));
    }
}
