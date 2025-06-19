<?php

declare(strict_types=1);

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
 */

namespace Tests\App;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase as SymfonyApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use Symfony\Contracts\HttpClient\ResponseInterface;

abstract class ApiTestCase extends SymfonyApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private Client $client;
    private ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->token = null;
    }

    final protected function login(string $login, string $password): void
    {
        $this->request('POST', '/api/latest/login', [
            'json' => [
                'security' => [
                    'credentials' => [
                        'login' => $login,
                        'password' => $password,
                    ],
                ],
            ],
        ]);

        $response = $this->client->getResponse();

        $this->token = $response?->toArray()['security']['token'] ?? null;
        if (!$this->token) {
            throw new \RuntimeException('Cannot find authentication token');
        }
    }

    final protected function logout(): void
    {
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
}
