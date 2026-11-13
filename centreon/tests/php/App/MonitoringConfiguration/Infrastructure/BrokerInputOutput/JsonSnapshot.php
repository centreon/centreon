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

namespace Tests\App\MonitoringConfiguration\Infrastructure\BrokerInputOutput;

use PHPUnit\Framework\Assert;

/**
 * Golden-file assertion for characterization tests.
 *
 * Snapshots are recorded from the current production code with BROKER_IO_SNAPSHOT_UPDATE=1 and must be
 * reviewed before being committed. A missing snapshot fails instead of being silently created.
 *
 * Comparison ignores the order of keys inside JSON objects (meaningless for the consumers, and not
 * preserved by MySQL JSON columns) but keeps the order of list items.
 */
final class JsonSnapshot
{
    private const DIRECTORY = __DIR__ . '/snapshots';

    public static function assertMatches(string $name, mixed $actual): void
    {
        $file = self::DIRECTORY . '/' . $name . '.json';

        if (($_ENV['BROKER_IO_SNAPSHOT_UPDATE'] ?? getenv('BROKER_IO_SNAPSHOT_UPDATE')) === '1') {
            if (! is_dir(self::DIRECTORY)) {
                mkdir(self::DIRECTORY, 0775, true);
            }
            file_put_contents(
                $file,
                json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
            );
        }

        Assert::assertFileExists($file, sprintf('Snapshot "%s" is missing, record it with BROKER_IO_SNAPSHOT_UPDATE=1', $name));
        $expected = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        Assert::assertSame(
            self::canonicalize($expected),
            self::canonicalize(json_decode(json_encode($actual, JSON_THROW_ON_ERROR), true)),
            sprintf('Output differs from snapshot "%s"', $name)
        );
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $value = array_map(self::canonicalize(...), $value);
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
