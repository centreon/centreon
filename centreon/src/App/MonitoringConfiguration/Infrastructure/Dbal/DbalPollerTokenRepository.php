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

use App\MonitoringConfiguration\Domain\Exception\PollerTokenNotFoundException;
use App\MonitoringConfiguration\Domain\Model\PollerToken;
use App\MonitoringConfiguration\Domain\Repository\PollerTokenRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DbalPollerTokenRepository implements PollerTokenRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
    ) {
    }

    public function getFirstValidPollerToken(): PollerToken
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('token_string', 'token_name', 'creation_date', 'expiration_date', 'is_revoked')
            ->from('authentication_tokens')
            ->where("type = 'poller'")
            ->andWhere('is_revoked = 0')
            ->andWhere('(expiration_date IS NULL OR expiration_date > :nowEpoch)')
            ->setParameter('nowEpoch', time())
            // Oldest valid token first: returning the earliest-created token is intentional.
            ->orderBy('creation_date', 'ASC')
            ->setMaxResults(1);

        /** @var array{token_string: string, token_name: string, creation_date: numeric-string, expiration_date: numeric-string|null, is_revoked: int}|false $row */
        $row = $qb->executeQuery()->fetchAssociative();

        if ($row === false) {
            throw new PollerTokenNotFoundException([], 'No valid poller token found.');
        }

        return $this->hydrateToken($row);
    }

    public function getValidPollerTokenByName(string $name): PollerToken
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('token_string', 'token_name', 'creation_date', 'expiration_date', 'is_revoked')
            ->from('authentication_tokens')
            ->where("type = 'poller'")
            ->andWhere('is_revoked = 0')
            ->andWhere('(expiration_date IS NULL OR expiration_date > :nowEpoch)')
            ->andWhere('token_name = :name')
            ->setParameter('nowEpoch', time())
            ->setParameter('name', $name)
            ->setMaxResults(1);

        /** @var array{token_string: string, token_name: string, creation_date: numeric-string, expiration_date: numeric-string|null, is_revoked: int}|false $row */
        $row = $qb->executeQuery()->fetchAssociative();

        if ($row === false) {
            throw new PollerTokenNotFoundException([], sprintf('No valid poller token found with name "%s".', $name));
        }

        return $this->hydrateToken($row);
    }

    /**
     * @param array{token_string: string, token_name: string, creation_date: numeric-string, expiration_date: numeric-string|null, is_revoked: int} $row
     */
    private function hydrateToken(array $row): PollerToken
    {
        return new PollerToken(
            name: $row['token_name'],
            value: $row['token_string'],
            creationDate: new \DateTimeImmutable('@' . (int) $row['creation_date']),
            expirationDate: $row['expiration_date'] !== null
                ? new \DateTimeImmutable('@' . (int) $row['expiration_date'])
                : null,
            isRevoked: (bool) $row['is_revoked'],
        );
    }
}
