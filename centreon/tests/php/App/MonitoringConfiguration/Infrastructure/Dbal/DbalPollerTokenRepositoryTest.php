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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Exception\PollerTokenNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\PollerTokenRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalPollerTokenRepositoryTest extends KernelTestCase
{
    private PollerTokenRepository $repository;

    private Connection $connection;

    protected function setUp(): void
    {
        /** @var PollerTokenRepository $repository */
        $repository = self::getContainer()->get(PollerTokenRepository::class);
        $this->repository = $repository;

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
    }

    public function testThrowsWhenNoValidPollerTokenExists(): void
    {
        $this->expectException(PollerTokenNotFoundException::class);
        $this->repository->getFirstValidPollerToken();
    }

    public function testReturnsPollerTokenWhenValidPollerTokenExists(): void
    {
        $token = bin2hex(random_bytes(16));
        $tokenName = 'test-poller-token-' . $token;

        $this->connection->insert('authentication_tokens', [
            'token_name' => $tokenName,
            'token_string' => $token,
            'type' => 'poller',
            'is_revoked' => 0,
            'expiration_date' => null,
            'creation_date' => time(),
        ]);

        try {
            $result = $this->repository->getFirstValidPollerToken();
            self::assertSame($token, $result->value);
            self::assertSame($tokenName, $result->name);
            self::assertFalse($result->isRevoked);
            self::assertNull($result->expirationDate);
        } finally {
            $this->connection->delete('authentication_tokens', ['token_string' => $token]);
        }
    }

    public function testThrowsWhenOnlyRevokedPollerTokensExist(): void
    {
        $token = bin2hex(random_bytes(16));

        $this->connection->insert('authentication_tokens', [
            'token_name' => 'test-revoked-token-' . $token,
            'token_string' => $token,
            'type' => 'poller',
            'is_revoked' => 1,
            'expiration_date' => null,
            'creation_date' => time(),
        ]);

        try {
            $this->expectException(PollerTokenNotFoundException::class);
            $this->repository->getFirstValidPollerToken();
        } finally {
            $this->connection->delete('authentication_tokens', ['token_string' => $token]);
        }
    }

    public function testThrowsWhenOnlyExpiredPollerTokensExist(): void
    {
        $token = bin2hex(random_bytes(16));

        $this->connection->insert('authentication_tokens', [
            'token_name' => 'test-expired-token-' . $token,
            'token_string' => $token,
            'type' => 'poller',
            'is_revoked' => 0,
            'expiration_date' => time() - 3600,
            'creation_date' => time() - 7200,
        ]);

        try {
            $this->expectException(PollerTokenNotFoundException::class);
            $this->repository->getFirstValidPollerToken();
        } finally {
            $this->connection->delete('authentication_tokens', ['token_string' => $token]);
        }
    }

    public function testThrowsWhenNoTokenExistsWithThatName(): void
    {
        $this->expectException(PollerTokenNotFoundException::class);
        $this->repository->getValidPollerTokenByName('non-existent-token-name');
    }

    public function testReturnsPollerTokenForNamedToken(): void
    {
        $token = bin2hex(random_bytes(16));
        $tokenName = 'test-named-token-' . $token;

        $this->connection->insert('authentication_tokens', [
            'token_name' => $tokenName,
            'token_string' => $token,
            'type' => 'poller',
            'is_revoked' => 0,
            'expiration_date' => null,
            'creation_date' => time(),
        ]);

        try {
            $result = $this->repository->getValidPollerTokenByName($tokenName);
            self::assertSame($token, $result->value);
            self::assertSame($tokenName, $result->name);
            self::assertFalse($result->isRevoked);
            self::assertNull($result->expirationDate);
        } finally {
            $this->connection->delete('authentication_tokens', ['token_string' => $token]);
        }
    }

    public function testThrowsWhenTokenWithThatNameIsRevoked(): void
    {
        $token = bin2hex(random_bytes(16));
        $tokenName = 'test-revoked-named-token-' . $token;

        $this->connection->insert('authentication_tokens', [
            'token_name' => $tokenName,
            'token_string' => $token,
            'type' => 'poller',
            'is_revoked' => 1,
            'expiration_date' => null,
            'creation_date' => time(),
        ]);

        try {
            $this->expectException(PollerTokenNotFoundException::class);
            $this->repository->getValidPollerTokenByName($tokenName);
        } finally {
            $this->connection->delete('authentication_tokens', ['token_string' => $token]);
        }
    }

    public function testThrowsWhenTokenWithThatNameIsExpired(): void
    {
        $token = bin2hex(random_bytes(16));
        $tokenName = 'test-expired-named-token-' . $token;

        $this->connection->insert('authentication_tokens', [
            'token_name' => $tokenName,
            'token_string' => $token,
            'type' => 'poller',
            'is_revoked' => 0,
            'expiration_date' => time() - 3600,
            'creation_date' => time() - 7200,
        ]);

        try {
            $this->expectException(PollerTokenNotFoundException::class);
            $this->repository->getValidPollerTokenByName($tokenName);
        } finally {
            $this->connection->delete('authentication_tokens', ['token_string' => $token]);
        }
    }
}
