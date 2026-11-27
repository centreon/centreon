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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Infrastructure\Repository\DatabaseRepository;

/**
 * Where a module version upgrade stopped, kept in the options table.
 *
 * @phpstan-type _Progress array{pre: bool, sql: int, checksum: string}
 */
class ModuleUpgradeProgressRepository extends DatabaseRepository
{
    public const string KEY_PREFIX = 'module_upgrade_progress:';

    /** @var array<'select'|'update'|'delete', string> built once: each query builder opens a connection */
    private array $queries = [];

    /**
     * @throws RepositoryException when the progress cannot be read, or is unreadable
     *
     * @return _Progress whether upgrade.pre.php passed, how many upgrade.sql queries did, and their chained checksum
     */
    public function get(string $module, string $version): array
    {
        $key = self::KEY_PREFIX . $module;

        try {
            $value = $this->connection->fetchOne(
                $this->queries['select'] ??= $this->connection->createQueryBuilder()
                    ->select('`value`')
                    ->from('`options`')
                    ->where('`key` = :key')
                    ->limit(1)
                    ->getQuery(),
                QueryParameters::create([QueryParameter::string('key', $key)])
            );
        } catch (\Throwable $exception) {
            throw new RepositoryException(
                'Cannot read the module upgrade progress',
                ['module' => $module, 'version' => $version],
                $exception
            );
        }

        if (! is_string($value)) {
            return ['pre' => false, 'sql' => 0, 'checksum' => ''];
        }

        try {
            $progress = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw $this->unreadable($key, $module, $version, $exception);
        }

        if (! is_array($progress) || ! is_string($progress['version'] ?? null)) {
            throw $this->unreadable($key, $module, $version);
        }

        // Only one version of a module is upgraded at a time: the progress of another one is stale.
        if ($progress['version'] !== $version) {
            return ['pre' => false, 'sql' => 0, 'checksum' => ''];
        }

        if (! is_bool($progress['pre'] ?? null) || ! is_int($progress['sql'] ?? null) || ! is_string($progress['checksum'] ?? null)) {
            throw $this->unreadable($key, $module, $version);
        }

        return ['pre' => $progress['pre'], 'sql' => $progress['sql'], 'checksum' => $progress['checksum']];
    }

    /**
     * @param _Progress $progress
     *
     * @throws RepositoryException
     */
    public function save(string $module, string $version, array $progress): void
    {
        $key = self::KEY_PREFIX . $module;

        try {
            $value = json_encode(['version' => $version, ...$progress], JSON_THROW_ON_ERROR);

            // No statement deletes the previous progress: a failure cannot lose it.
            $affectedRows = $this->connection->update(
                $this->queries['update'] ??= $this->connection->createQueryBuilder()
                    ->update('`options`')
                    ->set('`value`', ':value')
                    ->where('`key` = :key')
                    ->getQuery(),
                QueryParameters::create([
                    QueryParameter::string('key', $key),
                    QueryParameter::string('value', $value),
                ])
            );
            // No row affected may also mean an unchanged value.
            if ($affectedRows > 0) {
                return;
            }

            $this->connection->insert(
                'INSERT INTO `options` (`key`, `value`) SELECT :key, :value FROM DUAL'
                . ' WHERE NOT EXISTS (SELECT 1 FROM `options` WHERE `key` = :existing_key)',
                QueryParameters::create([
                    QueryParameter::string('key', $key),
                    QueryParameter::string('value', $value),
                    QueryParameter::string('existing_key', $key),
                ])
            );
        } catch (\Throwable $exception) {
            throw new RepositoryException(
                'Cannot record the module upgrade progress',
                ['module' => $module, 'version' => $version],
                $exception
            );
        }
    }

    /**
     * @throws RepositoryException
     */
    public function clear(string $module): void
    {
        try {
            $this->connection->delete(
                $this->queries['delete'] ??= $this->connection->createQueryBuilder()
                    ->delete('`options`')
                    ->where('`key` = :key')
                    ->getQuery(),
                QueryParameters::create([QueryParameter::string('key', self::KEY_PREFIX . $module)])
            );
        } catch (\Throwable $exception) {
            throw new RepositoryException('Cannot clear the module upgrade progress', ['module' => $module], $exception);
        }
    }

    private function unreadable(string $key, string $module, string $version, ?\Throwable $previous = null): RepositoryException
    {
        return new RepositoryException(
            \sprintf('The upgrade progress of the "%s" module is unreadable: delete the "%s" option to restart its upgrade', $module, $key),
            ['module' => $module, 'version' => $version],
            $previous
        );
    }
}
