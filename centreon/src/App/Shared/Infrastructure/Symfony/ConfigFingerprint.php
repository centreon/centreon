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

namespace App\Shared\Infrastructure\Symfony;

/**
 * Modules drop configuration files after the Symfony container may already have been
 * compiled. Keying the cache directory on the configuration file set makes such a stale
 * container unreachable instead of fatal.
 *
 * The Kernel itself is final and resolves its project directory from its own location, so
 * holding the file set rules here is what makes them testable.
 */
final class ConfigFingerprint
{
    /**
     * Fingerprints every file the shared kernel imports from the given configuration directory.
     */
    public static function ofConfigDir(string $configDir): string
    {
        return self::of(
            $configDir . '/{routes,packages,services}/{*,*/*}.{yaml,php}',
            $configDir . '/bundles.php'
        );
    }

    private static function of(string ...$globPatterns): string
    {
        // filemtime() and filesize() read PHP's stat cache. Booting the kernel from the very
        // process that just wrote a configuration file must not reuse pre-write values, and
        // relying on glob() to evict them is not a documented guarantee.
        clearstatcache();

        $files = [];
        foreach ($globPatterns as $globPattern) {
            $files = array_merge($files, glob($globPattern, \GLOB_BRACE) ?: []);
        }
        sort($files);

        $entries = array_map(
            static fn (string $file): string => $file . ':' . (filemtime($file) ?: 0) . ':' . (filesize($file) ?: 0),
            $files
        );

        return mb_substr(md5(implode('|', $entries)), 0, 8);
    }
}
