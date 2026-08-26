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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller;

use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Poller\PollerResource;
use Doctrine\DBAL\Connection;
use Tests\App\Shared\ApiTestCase;

final class CreatePollerProcessorTest extends ApiTestCase
{
    private string $tokenName;

    private string $tokenString;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenName = 'test-poller-token-' . bin2hex(random_bytes(8));
        $this->tokenString = bin2hex(random_bytes(16));

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $connection->insert('authentication_tokens', [
            'token_name' => $this->tokenName,
            'token_string' => $this->tokenString,
            'type' => 'poller',
            'is_revoked' => 0,
            'expiration_date' => null,
            'creation_date' => time(),
        ]);
    }

    protected function tearDown(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $connection->delete('authentication_tokens', ['token_string' => $this->tokenString]);

        parent::tearDown();
    }

    public function testCreatePoller(): void
    {
        /** @var PollerRepository $repository */
        $repository = self::getContainer()->get(PollerRepository::class);
        $name = $this->uniqueName('Test');

        $poller = $repository->findOneByName(new PollerName($name));
        self::assertNull($poller);

        $this->login();

        $address = '10.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(1, 254);

        $response = $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $name,
                'poller_type' => 'vm',
                'address' => $address,
                'poller_token_name' => $this->tokenName,
                'central_address' => '192.168.1.254',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(PollerResource::class);
        self::assertJsonContains([
            'name' => $name,
            'poller_type' => 'vm',
            'address' => $address,
        ]);

        $responseData = $response->toArray();
        self::assertArrayHasKey('id', $responseData);
        self::assertArrayHasKey('uid', $responseData);
        self::assertNotNull($responseData['uid']);
        self::assertArrayHasKey('installation_command', $responseData);
        self::assertNotNull($responseData['installation_command']);

        $poller = $repository->findOneByName(new PollerName($name));
        self::assertNotNull($poller);
    }

    public function testCreatePollerWithAddress(): void
    {
        $this->login();
        $name = $this->uniqueName('WithAddr');
        $address = '10.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(1, 254);

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $name,
                'poller_type' => 'vm',
                'address' => $address,
                'poller_token_name' => $this->tokenName,
                'central_address' => '192.168.1.254',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'name' => $name,
            'address' => $address,
        ]);
    }

    public function testCreatePollerWithDockerType(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $this->uniqueName('Docker'),
                'poller_type' => 'docker',
                'address' => '10.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(1, 254),
                'poller_token_name' => $this->tokenName,
                'central_address' => '192.168.1.254',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'poller_type' => 'docker',
        ]);
    }

    public function testCannotCreatePollerWithSameName(): void
    {
        $this->login();
        $name = $this->uniqueName('Dup');

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $name,
                'poller_type' => 'vm',
                'address' => '10.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(1, 254),
                'poller_token_name' => $this->tokenName,
                'central_address' => '192.168.1.254',
            ],
        ]);
        self::assertResponseIsSuccessful();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $name,
                'poller_type' => 'vm',
                'address' => '10.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(1, 254),
                'poller_token_name' => $this->tokenName,
                'central_address' => '192.168.1.254',
            ],
        ]);
        self::assertResponseStatusCodeSame(409);
    }

    public function testCannotCreatePollerWithInvalidType(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $this->uniqueName('Invalid'),
                'poller_type' => 'invalid',
                'address' => '192.168.1.1',
                'poller_token_name' => $this->tokenName,
                'central_address' => '192.168.1.254',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testCannotCreatePollerWithEmptyName(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => '',
                'poller_type' => 'vm',
                'address' => '192.168.1.1',
                'poller_token_name' => $this->tokenName,
                'central_address' => '192.168.1.254',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testCannotCreatePollerWithNameTooLong(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => str_repeat('a', 41),
                'poller_type' => 'vm',
                'address' => '192.168.1.1',
                'poller_token_name' => $this->tokenName,
                'central_address' => '192.168.1.254',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testCannotCreatePollerWithoutPollerTokenName(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $this->uniqueName('NoToken'),
                'poller_type' => 'vm',
                'address' => '192.168.1.1',
                'central_address' => '192.168.1.254',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testCannotCreatePollerWithUnknownPollerTokenName(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $this->uniqueName('UnknownToken'),
                'poller_type' => 'vm',
                'address' => '192.168.1.1',
                'poller_token_name' => 'non-existent-token-' . bin2hex(random_bytes(4)),
                'central_address' => '192.168.1.254',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testCannotCreatePollerIfNotLogged(): void
    {
        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $this->uniqueName('Unauth'),
                'poller_type' => 'vm',
                'address' => '192.168.1.1',
                'poller_token_name' => $this->tokenName,
                'central_address' => '192.168.1.254',
            ],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testNonAdminWithPermissionCanCreatePoller(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $username = bin2hex(random_bytes(8));

        $this->createApiUser($connection, $username, admin: false, actions: [
            'create_edit_poller_cfg',
        ]);
        $this->login($username);

        $name = $this->uniqueName('NonAdmin');

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $name,
                'poller_type' => 'vm',
                'address' => '10.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(1, 254),
                'poller_token_name' => $this->tokenName,
                'central_address' => '192.168.1.254',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'name' => $name,
        ]);
    }

    public function testCannotCreatePollerIfNotEnoughPermission(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $username = bin2hex(random_bytes(8));

        $this->createApiUser($connection, $username, admin: false);
        $this->login($username);

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $this->uniqueName('Forbidden'),
                'poller_type' => 'vm',
                'address' => '192.168.1.1',
                'poller_token_name' => $this->tokenName,
                'central_address' => '192.168.1.254',
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertJsonContains([
            'message' => 'You are not allowed to create pollers',
        ]);
    }

    public function testCannotCreatePollerWithoutCentralAddress(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $this->uniqueName('NoCentral'),
                'poller_type' => 'vm',
                'address' => '192.168.1.1',
                'poller_token_name' => $this->tokenName,
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testCannotCreatePollerWithEmptyCentralAddress(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $this->uniqueName('EmptyCentral'),
                'poller_type' => 'vm',
                'address' => '192.168.1.1',
                'poller_token_name' => $this->tokenName,
                'central_address' => '',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testCannotCreatePollerWithWhitespaceCentralAddress(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $this->uniqueName('WsCentral'),
                'poller_type' => 'vm',
                'address' => '192.168.1.1',
                'poller_token_name' => $this->tokenName,
                'central_address' => '   ',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testCannotCreatePollerWithCentralAddressTooLong(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $this->uniqueName('LongCentral'),
                'poller_type' => 'vm',
                'address' => '192.168.1.1',
                'poller_token_name' => $this->tokenName,
                'central_address' => str_repeat('a', 256),
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testCannotCreatePollerWithProtocolSchemeInCentralAddress(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $this->uniqueName('SchemeCentral'),
                'poller_type' => 'vm',
                'address' => '192.168.1.1',
                'poller_token_name' => $this->tokenName,
                'central_address' => 'https://central.example.com',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testCannotCreatePollerWithProtocolSchemeInAddress(): void
    {
        $this->login();

        $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $this->uniqueName('SchemeAddress'),
                'poller_type' => 'vm',
                'address' => 'http://192.168.1.1',
                'poller_token_name' => $this->tokenName,
                'central_address' => '192.168.1.1',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testCreatePollerWithBasePathInCentralAddress(): void
    {
        $this->login();

        $response = $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $this->uniqueName('BasePath'),
                'poller_type' => 'vm',
                'address' => '192.168.1.1',
                'poller_token_name' => $this->tokenName,
                'central_address' => 'staging.euwest1.centreon.click/funky-donkey',
            ],
        ]);

        self::assertResponseIsSuccessful();

        $responseData = $response->toArray();
        self::assertArrayHasKey('installation_command', $responseData);
        self::assertIsString($responseData['installation_command']);
        self::assertStringContainsString(
            'http://staging.euwest1.centreon.click/funky-donkey/poller/install.sh',
            $responseData['installation_command']
        );
    }

    public function testCreatePollerWithTrailingSlashInCentralAddressUsesNormalizedValue(): void
    {
        $this->login();

        $response = $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $this->uniqueName('TrailingSlash'),
                'poller_type' => 'vm',
                'address' => '192.168.1.1',
                'poller_token_name' => $this->tokenName,
                'central_address' => 'staging.euwest1.centreon.click/funky-donkey/',
            ],
        ]);

        self::assertResponseIsSuccessful();

        $responseData = $response->toArray();
        self::assertArrayHasKey('installation_command', $responseData);
        self::assertIsString($responseData['installation_command']);
        self::assertStringContainsString(
            'http://staging.euwest1.centreon.click/funky-donkey/poller/install.sh',
            $responseData['installation_command']
        );
        self::assertStringNotContainsString('funky-donkey//', $responseData['installation_command']);
        self::assertStringContainsString(
            '--central_url http://staging.euwest1.centreon.click/funky-donkey ',
            $responseData['installation_command']
        );
    }

    public function testCreatePollerReturnsCommandWithASingleSchemeOnBothUrls(): void
    {
        $this->login();

        $response = $this->request('POST', '/api/latest/configuration/pollers', [
            'json' => [
                'name' => $this->uniqueName('SingleScheme'),
                'poller_type' => 'vm',
                'address' => '192.168.1.1',
                'poller_token_name' => $this->tokenName,
                'central_address' => '192.168.1.254',
            ],
        ]);

        self::assertResponseIsSuccessful();

        $responseData = $response->toArray();
        self::assertIsString($responseData['installation_command']);
        $command = $responseData['installation_command'];

        self::assertStringContainsString('curl -fsSL http://192.168.1.254/poller/install.sh', $command);
        self::assertStringContainsString('--central_url http://192.168.1.254 ', $command);
        self::assertSame(2, mb_substr_count($command, '://'));
    }

    private function uniqueName(string $prefix = 'Poller'): string
    {
        return $prefix . '_' . bin2hex(random_bytes(4));
    }
}
