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

namespace Tests\Core\Integration\Authentication\Saml;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SamlBarePrefixIntegrationTest extends WebTestCase
{
    public function testLoginSamlBehavesIdenticallyOnBothPrefixes(): void
    {
        $client = self::createClient();
        $client->catchExceptions(true);

        $client->request('GET', '/centreon/api/latest/login/saml', server: self::serverParams());
        $legacyResponse = $client->getResponse();

        $client->request('GET', '/centreon/api/login/saml', server: self::serverParams());
        $bareResponse = $client->getResponse();

        self::assertSame($legacyResponse->getStatusCode(), $bareResponse->getStatusCode());
        self::assertSame($legacyResponse->headers->get('Location'), $bareResponse->headers->get('Location'));
    }

    public function testSamlAcsBehavesIdenticallyOnBothPrefixes(): void
    {
        $client = self::createClient();
        $client->catchExceptions(true);

        $client->request('POST', '/centreon/api/latest/saml/acs', server: self::serverParams());
        $legacyResponse = $client->getResponse();

        $client->request('POST', '/centreon/api/saml/acs', server: self::serverParams());
        $bareResponse = $client->getResponse();

        self::assertSame($legacyResponse->getStatusCode(), $bareResponse->getStatusCode());
        self::assertSame($legacyResponse->headers->get('Location'), $bareResponse->headers->get('Location'));
    }

    public function testSamlSlsBehavesIdenticallyOnBothPrefixes(): void
    {
        $client = self::createClient();
        $client->catchExceptions(true);

        $client->request('GET', '/centreon/api/latest/saml/sls', server: self::serverParams());
        $legacyResponse = $client->getResponse();

        $client->request('GET', '/centreon/api/saml/sls', server: self::serverParams());
        $bareResponse = $client->getResponse();

        self::assertSame($legacyResponse->getStatusCode(), $bareResponse->getStatusCode());
        self::assertSame($legacyResponse->headers->get('Location'), $bareResponse->headers->get('Location'));
    }

    /**
     * @return array<string, string>
     */
    private static function serverParams(): array
    {
        // Core\Infrastructure\Common\Api\Router reads $_SERVER directly instead of the
        // Request object, so these must also be set on the superglobal (see CoreApiTestCase::request()).
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $_SERVER['REQUEST_SCHEME'] = 'http';
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['HTTP_HOST'] = 'localhost';

        return [
            'REMOTE_ADDR' => '8.8.8.8',
            'HTTP_HOST' => 'localhost',
            'SERVER_NAME' => 'localhost',
            'REQUEST_SCHEME' => 'http',
            'HTTPS' => '',
        ];
    }
}
