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

namespace Tests\Core\Integration\Authentication\Login;

use Tests\Core\Integration\CoreApiTestCase;

final class LoginBarePrefixIntegrationTest extends CoreApiTestCase
{
    private const PASSWORD = 'Centreon!2021';

    public function testLoginAtLegacyPrefixWithValidCredentials(): void
    {
        $response = $this->request('POST', '/api/latest/login', [
            'body' => json_encode([
                'security' => ['credentials' => ['login' => 'admin', 'password' => self::PASSWORD]],
            ]),
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        /** @var array{security?: array{token?: string}} $body */
        $body = json_decode((string) $response->getContent(), associative: true);
        $this->assertArrayHasKey('security', $body);
        $this->assertNotEmpty($body['security']['token'] ?? null);
    }

    public function testLoginAtBareApiPrefixWithValidCredentials(): void
    {
        $response = $this->request('POST', '/api/login', [
            'body' => json_encode([
                'security' => ['credentials' => ['login' => 'admin', 'password' => self::PASSWORD]],
            ]),
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        /** @var array{security?: array{token?: string}} $body */
        $body = json_decode((string) $response->getContent(), associative: true);
        $this->assertArrayHasKey('security', $body);
        $this->assertNotEmpty($body['security']['token'] ?? null);
    }

    public function testLoginAtBareApiPrefixWithInvalidCredentialsReturns401(): void
    {
        $response = $this->request('POST', '/api/login', [
            'body' => json_encode([
                'security' => ['credentials' => ['login' => 'admin', 'password' => 'wrong-password']],
            ]),
        ]);

        $this->assertSame(401, $response->getStatusCode(), (string) $response->getContent());
    }
}
