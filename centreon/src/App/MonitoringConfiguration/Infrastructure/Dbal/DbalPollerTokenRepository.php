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

namespace App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerToken;
use App\MonitoringConfiguration\Domain\Exception\PollerTokenNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\PollerTokenRepository;
use Doctrine\DBAL\Connection;
use Webmozart\Assert\Assert;

final readonly class DbalPollerTokenRepository implements PollerTokenRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function getFirstValidPollerToken(): PollerToken
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT token_string, token_name, creation_date, expiration_date, is_revoked
                FROM authentication_tokens
                WHERE type = 'poller'
                  AND is_revoked = 0
                  AND (expiration_date IS NULL OR expiration_date > :nowEpoch)
                ORDER BY creation_date ASC
                LIMIT 1
                SQL,
            ['nowEpoch' => time()],
        );

        if (! is_array($row)) {
            throw new PollerTokenNotFoundException([], 'No valid poller token found.');
        }

        return $this->hydrateToken($row);
    }

    public function getValidPollerTokenByName(string $name): PollerToken
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT token_string, token_name, creation_date, expiration_date, is_revoked
                FROM authentication_tokens
                WHERE type = 'poller'
                  AND is_revoked = 0
                  AND (expiration_date IS NULL OR expiration_date > :nowEpoch)
                  AND token_name = :name
                SQL,
            ['nowEpoch' => time(), 'name' => $name],
        );

        if (! is_array($row)) {
            throw new PollerTokenNotFoundException([], sprintf('No valid poller token found with name "%s".', $name));
        }

        return $this->hydrateToken($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateToken(array $row): PollerToken
    {
        $name = $row['token_name'];
        $value = $row['token_string'];
        $creationTimestamp = $row['creation_date'];
        $expirationTimestamp = $row['expiration_date'];

        Assert::string($name);
        Assert::string($value);
        Assert::numeric($creationTimestamp);

        $expirationDate = null;
        if ($expirationTimestamp !== null) {
            Assert::numeric($expirationTimestamp);
            $expirationDate = new \DateTimeImmutable('@' . (int) $expirationTimestamp);
        }

        return new PollerToken(
            name: $name,
            value: $value,
            creationDate: new \DateTimeImmutable('@' . (int) $creationTimestamp),
            expirationDate: $expirationDate,
            isRevoked: (bool) $row['is_revoked'],
        );
    }
}
