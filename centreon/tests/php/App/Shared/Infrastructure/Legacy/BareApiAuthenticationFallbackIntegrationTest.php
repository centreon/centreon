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

namespace Tests\App\Shared\Infrastructure\Legacy;

use Tests\App\Shared\ApiTestCase;

final class BareApiAuthenticationFallbackIntegrationTest extends ApiTestCase
{
    private const ADMIN_PASSWORD = 'Centreon!2021';

    public function testLoginWithInvalidCredentialsAtBarePrefix(): void
    {
        $response = $this->request('POST', '/api/login', [
            'json' => ['security' => ['credentials' => ['login' => 'admin', 'password' => 'wrong-password']]],
        ]);

        self::assertSame(401, $response->getStatusCode(), $response->getContent(false));
    }

    public function testLoginThenLogoutAtBarePrefix(): void
    {
        $loginResponse = $this->request('POST', '/api/login', [
            'json' => ['security' => ['credentials' => ['login' => 'admin', 'password' => self::ADMIN_PASSWORD]]],
        ]);
        self::assertSame(200, $loginResponse->getStatusCode(), $loginResponse->getContent(false));

        /** @var array{security: array{token: string}} $body */
        $body = $loginResponse->toArray();
        self::assertNotEmpty($body['security']['token']);

        $logoutResponse = $this->request('GET', '/api/logout', [
            'headers' => ['X-AUTH-TOKEN' => $body['security']['token']],
        ]);
        self::assertSame(200, $logoutResponse->getStatusCode(), $logoutResponse->getContent(false));
    }

    public function testLoginSamlBehavesIdenticallyOnBothPrefixes(): void
    {
        // Core\Infrastructure\Common\Api\Router / HttpUrlTrait read $_SERVER directly instead of the Request object.
        $_SERVER['REQUEST_SCHEME'] = 'http';
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['HTTP_HOST'] = 'localhost';

        $legacyResponse = $this->request('GET', '/api/latest/login/saml');
        $bareResponse = $this->request('GET', '/api/login/saml');

        self::assertSame($legacyResponse->getStatusCode(), $bareResponse->getStatusCode());
    }
}
