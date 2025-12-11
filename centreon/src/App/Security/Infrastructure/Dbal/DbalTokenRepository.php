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

namespace App\Security\Infrastructure\Dbal;

use App\Security\Domain\Aggregate\Token;
use App\Security\Domain\Aggregate\TokenProviderEnum;
use App\Security\Domain\Exception\TokenDoesNotExistException;
use App\Security\Domain\Repository\TokenRepository;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DbalTokenRepository extends DbalRepository implements TokenRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
    ) {
    }

    public function get(string $token): Token
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('sat.token', 'sat.token_type AS type', 'st.expiration_date AS expiresAt', 'pc.name AS pc_name')
            ->from('security_authentication_tokens', 'sat')
            ->join('sat', 'security_token', 'st', 'sat.provider_token_id = st.id')
            ->join('sat', 'provider_configuration', 'pc', 'sat.provider_configuration_id = pc.id')
            ->where('sat.token = :token')
            ->setParameter('token', $token)
            ->setMaxResults(1);

        /** @var array{token: string, type: string, expiresAt: ?int, pc_name: string}|false $row */
        $row = $qb->executeQuery()->fetchAssociative();
        if ($row === false) {
            throw new TokenDoesNotExistException(['token' => $token]);
        }

        return $this->createToken($row);
    }

    public function getRefreshToken(Token $token): Token
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('sat.token', 'sat.token_type AS type', 'rst.expiration_date AS expiresAt', 'pc.name AS pc_name')
            ->from('security_authentication_tokens', 'sat')
            ->join('sat', 'security_token', 'rst', 'sat.provider_token_refresh_id = rst.id')
            ->join('sat', 'provider_configuration', 'pc', 'sat.provider_configuration_id = pc.id')
            ->where('sat.token = :token')
            ->setParameter('token', $token)
            ->setMaxResults(1);

        /** @var array{token: string, type: string, expiresAt: ?int, pc_name: string}|false $row */
        $row = $qb->executeQuery()->fetchAssociative();
        if ($row === false) {
            throw new TokenDoesNotExistException(['token' => $token->token]);
        }

        return $this->createToken($row);
    }

    public function update(Token $token): void
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->update('security_authentication_tokens')
            ->set('token_type', ':type')
            ->where('token = :token')
            ->setParameter('type', $token->auto ? 'auto' : 'manual')
            ->setParameter('token', $token->token)
            ->executeStatement();

        $qb = $this->connection->createQueryBuilder();
        $providerTokenId = $qb->select('provider_token_id')
            ->from('security_authentication_tokens')
            ->where('token = :token')
            ->setParameter('token', $token->token)
            ->executeQuery()
            ->fetchOne();

        if ($providerTokenId === false) {
            return;
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->update('security_token')
            ->set('expiration_date', ':expiresAt')
            ->where('id = :id')
            ->setParameter('expiresAt', $token->expiresAt->getTimestamp())
            ->setParameter('id', $providerTokenId)
            ->executeStatement();
    }

    public function getTokenExpirationShift(): int
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('o.value AS delay')
            ->from('options', 'o')
            ->where('o.`key` = "session_expire"')
            ->setMaxResults(1);

        /** @var array{delay: string}|false $row */
        $row = $qb->executeQuery()->fetchAssociative();
        if ($row === false) {
            throw new \RuntimeException('Cannot find option "session_expire".');
        }

        return (int) $row['delay'] * 60;
    }

    /** 
     * @param array{token: string, type: string, expiresAt: ?int, pc_name: string} $row
     */
    private function createToken(array $row): Token
    {
        return new Token(
            token: $row['token'],
            provider: TokenProviderEnum::from($row['pc_name']),
            expiresAt: $row['expiresAt'] ? (new \DateTimeImmutable())->setTimestamp($row['expiresAt']) : (new \DateTimeImmutable())->add(new \DateInterval('P10M')),
            auto: $row['type'] === 'auto',
        );
    }
}
