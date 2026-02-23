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

namespace Tests\App\Shared;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase as SymfonyApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\ResponseInterface;

abstract class ApiTestCase extends SymfonyApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    protected ?string $token = null;

    private Client $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->token = null;
    }

    /**
     * @param array{headers?: array<string, mixed>, ...<string, mixed>} $options
     */
    final public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if ($this->token) {
            $options['headers']['X-AUTH-TOKEN'] = $this->token;
        }

        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        return $this->client->request($method, $url, $options);
    }

    final protected function login(string $login = 'admin'): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');

        $this->token = base64_encode(random_bytes(48));
        $qb = $connection->createQueryBuilder();
        /** @var string|false $contact */
        $contact = $qb->select('contact_id')
            ->from('contact')
            ->where('contact_alias = :login')
            ->setParameter('login', $login)
            ->executeQuery()
            ->fetchOne();

        if ($contact === false) {
            $this->createApiUser($login, admin: false);
            $qb = $connection->createQueryBuilder();
            /** @var string $contact */
            $contact = $qb->select('contact_id')
                ->from('contact')
                ->where('contact_alias = :login')
                ->setParameter('login', $login)
                ->executeQuery()
                ->fetchOne();
        }

        // create authentication token directly in the database
        $connection->insert('security_token', [
            'token' => $this->token,
            'creation_date' => time(),
            'expiration_date' => null,
        ]);

        $tokenId = (int) $connection->lastInsertId();

        $connection->insert('security_authentication_tokens', [
            'provider_token_id' => $tokenId,
            'user_id' => (int) $contact,
            'token' => $this->token,
            'provider_token_refresh_id' => null,
            'provider_configuration_id' => 1,
            'is_revoked' => 0,
        ]);
    }

    final protected function logout(): void
    {
        if (! $this->token) {
            return;
        }

        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');

        $qb = $connection->createQueryBuilder();
        $qb->delete('security_authentication_tokens')
            ->where('token = :token')
            ->setParameter('token', $this->token)
            ->executeStatement();

        $qb = $connection->createQueryBuilder();
        $qb->delete('security_token')
            ->where('token = :token')
            ->setParameter('token', $this->token)
            ->executeStatement();

        $this->token = null;
    }

    final protected function createApiUser(string $identifier, bool $admin = false): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');

        $connection->insert('contact', [
            'contact_name' => $identifier,
            'contact_alias' => $identifier,
            'contact_admin' => $admin ? '1' : '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => $identifier . '@email.com',
        ]);

        $connection->insert('contact_password', [
            'contact_id' => (int) $connection->lastInsertId(),
            'password' => password_hash('Centreon!2021', \PASSWORD_BCRYPT),
            'creation_date' => (new \DateTimeImmutable())->getTimestamp(),
        ]);
    }
}
