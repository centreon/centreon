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

namespace Tests\Core\Integration\Authentication\Logout;

use Tests\Core\Integration\CoreApiTestCase;

final class LogoutBarePrefixIntegrationTest extends CoreApiTestCase
{
    public function testLogoutAtLegacyPrefixRevokesToken(): void
    {
        $this->login('admin');
        $token = $this->token;

        $response = $this->request('GET', '/api/latest/logout');

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $this->assertTokenIsRevoked($token);
    }

    public function testLogoutAtBareApiPrefixRevokesToken(): void
    {
        $this->login('admin');
        $token = $this->token;

        $response = $this->request('GET', '/api/logout');

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $this->assertTokenIsRevoked($token);
    }

    public function testLogoutAtBareApiPrefixWithoutTokenReturns401(): void
    {
        $response = $this->request('GET', '/api/logout');

        $this->assertSame(401, $response->getStatusCode(), (string) $response->getContent());
    }

    private function assertTokenIsRevoked(?string $token): void
    {
        self::assertNotNull($token);
        $stmt = self::$db->prepare('SELECT COUNT(*) FROM security_token WHERE token = :token');
        $stmt->execute([':token' => $token]);
        self::assertSame(
            0,
            (int) $stmt->fetchColumn(),
            'The token should have been deleted from security_token by /logout.'
        );
    }
}
