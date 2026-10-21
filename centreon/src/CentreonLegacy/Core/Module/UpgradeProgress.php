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

/**
 * Where a module version upgrade stopped: legacy upgrade scripts are rarely idempotent.
 * Kept in the database, so that restoring a backup also restores where the upgrade stood.
 * Best effort: a progress that cannot be written is only reported.
 */
class UpgradeProgress
{
    private const string KEY_PREFIX = 'module_upgrade_progress:';

    public function __construct(
        private readonly \CentreonDB $db,
    ) {
    }

    /**
     * @return array{pre: bool, sql: int, checksum: string} whether upgrade.pre.php passed, how many upgrade.sql
     *                                                      queries did, and the checksum of that upgrade.sql
     */
    public function get(string $module, string $version): array
    {
        $statement = $this->db->prepare('SELECT `value` FROM `options` WHERE `key` = :key LIMIT 1');
        $statement->bindValue(':key', self::KEY_PREFIX . $module, \PDO::PARAM_STR);
        $statement->execute();

        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        $progress = is_array($row) ? json_decode((string) $row['value'], true) : null;

        // Only one version of a module is upgraded at a time: the progress of another one is stale.
        if (! is_array($progress) || ($progress['version'] ?? null) !== $version) {
            return ['pre' => false, 'sql' => 0, 'checksum' => ''];
        }

        return [
            'pre' => ($progress['pre'] ?? false) === true,
            'sql' => is_int($progress['sql'] ?? null) ? $progress['sql'] : 0,
            'checksum' => is_string($progress['checksum'] ?? null) ? $progress['checksum'] : '',
        ];
    }

    /**
     * @param array{pre: bool, sql: int, checksum: string} $progress
     */
    public function save(string $module, string $version, array $progress): void
    {
        $value = (string) json_encode(['version' => $version, ...$progress]);

        try {
            // No statement deletes the previous progress: a failure cannot lose it.
            $statement = $this->db->prepare('UPDATE `options` SET `value` = :value WHERE `key` = :key');
            $statement->bindValue(':key', self::KEY_PREFIX . $module, \PDO::PARAM_STR);
            $statement->bindValue(':value', $value, \PDO::PARAM_STR);
            $statement->execute();

            $statement = $this->db->prepare(
                'INSERT INTO `options` (`key`, `value`) SELECT :key, :value FROM DUAL'
                . ' WHERE NOT EXISTS (SELECT 1 FROM `options` WHERE `key` = :existing_key)'
            );
            $statement->bindValue(':existing_key', self::KEY_PREFIX . $module, \PDO::PARAM_STR);
            $statement->bindValue(':key', self::KEY_PREFIX . $module, \PDO::PARAM_STR);
            $statement->bindValue(':value', $value, \PDO::PARAM_STR);
            $statement->execute();
        } catch (\Exception $exception) {
            CentreonLog::create()->warning(CentreonLog::TYPE_UPGRADE, 'Cannot record the module upgrade progress', [], $exception);
        }
    }

    public function clear(string $module): void
    {
        try {
            $statement = $this->db->prepare('DELETE FROM `options` WHERE `key` = :key');
            $statement->bindValue(':key', self::KEY_PREFIX . $module, \PDO::PARAM_STR);
            $statement->execute();
        } catch (\Exception $exception) {
            CentreonLog::create()->warning(CentreonLog::TYPE_UPGRADE, 'Cannot clear the module upgrade progress', [], $exception);
        }
    }
}
