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

namespace CentreonLegacy\Core\Module;

use CentreonLog;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Where a module version upgrade stopped: legacy upgrade scripts are rarely idempotent.
 * Best effort: a progress that cannot be written is only reported.
 */
class UpgradeProgress
{
    public function __construct(
        private readonly string $directory,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * @return array{pre: bool, sql: int, checksum: string} whether upgrade.pre.php passed, how many upgrade.sql
     *                                                      queries did, and the checksum of that upgrade.sql
     */
    public function get(string $module, string $version): array
    {
        $file = $this->file($module, $version);
        $progress = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;

        return [
            'pre' => is_array($progress) && ($progress['pre'] ?? false) === true,
            'sql' => is_array($progress) && is_int($progress['sql'] ?? null) ? $progress['sql'] : 0,
            'checksum' => is_array($progress) && is_string($progress['checksum'] ?? null) ? $progress['checksum'] : '',
        ];
    }

    /**
     * @param array{pre: bool, sql: int, checksum: string} $progress
     */
    public function save(string $module, string $version, array $progress): void
    {
        try {
            $this->filesystem->dumpFile($this->file($module, $version), (string) json_encode($progress));
        } catch (IOException $exception) {
            CentreonLog::create()->warning(CentreonLog::TYPE_UPGRADE, 'Cannot record the module upgrade progress', [], $exception);
        }
    }

    public function clear(string $module, ?string $version = null): void
    {
        try {
            $this->filesystem->remove($version === null ? $this->moduleDirectory($module) : $this->file($module, $version));
        } catch (IOException $exception) {
            CentreonLog::create()->warning(CentreonLog::TYPE_UPGRADE, 'Cannot clear the module upgrade progress', [], $exception);
        }
    }

    private function file(string $module, string $version): string
    {
        return $this->moduleDirectory($module) . '/' . preg_replace('/[^\w-]/', '_', $version) . '.json';
    }

    private function moduleDirectory(string $module): string
    {
        return $this->directory . '/' . preg_replace('/[^\w-]/', '_', $module);
    }
}
