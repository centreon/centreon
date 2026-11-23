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

namespace Core\RemoteChannel\Infrastructure\Repository;

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\RemoteChannel\Application\Repository\ReadPollerTargetRepositoryInterface;
use Core\RemoteChannel\Domain\Model\PollerTarget;
use Core\RemoteChannel\Domain\Model\RemoteChannel;

final class DbReadPollerTargetRepository implements ReadPollerTargetRepositoryInterface
{
    /** @var array<string, PollerTarget|null> */
    private array $cache = [];

    private ?string $illegalCharacters = null;

    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function findByIdOrUid(int|string $key): ?PollerTarget
    {
        $key = (string) $key;
        if (! ctype_digit($key)) {
            return null;
        }
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        // uid is a 64-bit snowflake: never equal to a configuration id in practice. The id wins when both match.
        $row = $this->connection->fetchAssociative(
            'SELECT id, uid, name, remote_channel FROM nagios_server
            WHERE id = :key OR uid = :key
            ORDER BY id = :key DESC
            LIMIT 1',
            QueryParameters::create([QueryParameter::string('key', $key)])
        );

        $target = null;
        if (is_array($row)) {
            $target = new PollerTarget(
                (int) $row['id'],
                (string) $row['uid'],
                (string) $row['name'],
                RemoteChannel::tryFrom((string) $row['remote_channel']) ?? RemoteChannel::Gorgone,
            );
        }

        return $this->cache[$key] = $target;
    }

    public function findIllegalCharacters(): string
    {
        if ($this->illegalCharacters === null) {
            $value = $this->connection->fetchOne(
                "SELECT `value` FROM options WHERE `key` = 'gorgone_illegal_characters'"
            );
            $this->illegalCharacters = is_string($value) ? $value : '';
        }

        return $this->illegalCharacters;
    }
}
