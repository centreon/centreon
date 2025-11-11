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
    public const CAN_READ_CHECK_COMMANDS = 'see_check_commands';
    public const CAN_READ_AND_WRITE_NOTIFICATION_COMMANDS = 'manage_notification_commands';
    private const TEST_PASSWORD = 'Centreon!2021';

    protected static ?bool $alwaysBootKernel = true;

    private Client $client;

    private ?string $token = null;

    public static function setUpBeforeClass(): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.test_setup_default_connection');
        $connection->beginTransaction();

        try {
            foreach (static::apiUsers() as $apiUser) {
                if (\is_string($apiUser)) {
                    $apiUser = ['identifier' => $apiUser, 'admin' => false];
                }

                self::createApiUser($connection, $apiUser['identifier'], $apiUser['admin'] ?? false, $apiUser['actions'] ?? []);
            }

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }
    }

    public static function tearDownAfterClass(): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.test_setup_default_connection');
        $connection->beginTransaction();

        try {
            foreach (static::apiUsers() as $apiUser) {
                if (\is_string($apiUser)) {
                    $apiUser = ['identifier' => $apiUser];
                }

                self::deleteApiUser($connection, $apiUser['identifier']);
            }

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }

        parent::tearDownAfterClass();
    }

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

    /**
     * Define API users to create for the current test class.
     *
     * @return list<array{identifier: string, admin?: bool, actions?: array<string>}|string>
     */
    protected static function apiUsers(): array
    {
        return [];
    }

    final protected function login(string $login = 'admin'): void
    {
        $this->request('POST', '/api/latest/login', [
            'json' => [
                'security' => [
                    'credentials' => [
                        'login' => $login,
                        'password' => self::TEST_PASSWORD,
                    ],
                ],
            ],
        ]);

        $response = $this->client->getResponse();

        /** @var array{security: array{token: string}}|null $content */
        $content = $response?->toArray();

        $this->token = $content['security']['token'] ?? null;
        if (! $this->token) {
            throw new \RuntimeException('Cannot find authentication token');
        }
    }

    final protected function logout(): void
    {
        $this->token = null;
    }

    /**
     * @param array<string> $actions
     */
    private static function createApiUser(Connection $connection, string $identifier, bool $admin = false, array $actions = []): void
    {
        $connection->insert('contact', [
            'contact_name' => $identifier,
            'contact_alias' => $identifier,
            'contact_admin' => $admin ? '1' : '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => $identifier . '@email.com',
        ]);

        $contactId = (int) $connection->lastInsertId();

        $connection->insert('contact_password', [
            'contact_id' => $contactId,
            'password' => password_hash('Centreon!2021', \PASSWORD_BCRYPT),
            'creation_date' => (new \DateTimeImmutable())->getTimestamp(),
        ]);

        if (! $admin && $actions !== []) {
            self::setupAclForUser($connection, $contactId, $identifier, $actions);
        }
    }

    /**
     * @param array<string> $actions
     */
    private static function setupAclForUser(Connection $connection, int $contactId, string $identifier, array $actions): void
    {
        // Create ACL group
        $connection->insert('acl_groups', [
            'acl_group_name' => "Test ACL Group for {$identifier}",
            'acl_group_alias' => "test_acl_{$identifier}",
            'acl_group_activate' => '1',
        ]);

        $aclGroupId = (int) $connection->lastInsertId();

        // Create ACL actions
        $connection->insert('acl_actions', [
            'acl_action_name' => "test_actions_{$identifier}",
            'acl_action_activate' => '1',
        ]);

        $aclActionId = (int) $connection->lastInsertId();

        // Link action to group
        $connection->insert('acl_group_actions_relations', [
            'acl_group_id' => $aclGroupId,
            'acl_action_id' => $aclActionId,
        ]);

        // Add action rules
        foreach ($actions as $action) {
            $connection->insert('acl_actions_rules', [
                'acl_action_rule_id' => $aclActionId,
                'acl_action_name' => $action,
            ]);
        }

        // Link user to group
        $connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);
    }

    private static function deleteApiUser(Connection $connection, string $identifier): void
    {
        // Get contact ID
        $contactId = $connection->fetchOne('SELECT contact_id FROM contact WHERE contact_alias = :identifier', [
            'identifier' => $identifier,
        ]);

        if ($contactId) {
            // Delete ACL relations
            $aclGroupIds = $connection->fetchFirstColumn(
                'SELECT acl_group_id FROM acl_group_contacts_relations WHERE contact_contact_id = :contactId',
                ['contactId' => $contactId]
            );

            foreach ($aclGroupIds as $aclGroupId) {
                // Delete action relations
                $aclActionIds = $connection->fetchFirstColumn(
                    'SELECT acl_action_id FROM acl_group_actions_relations WHERE acl_group_id = :aclGroupId',
                    ['aclGroupId' => $aclGroupId]
                );

                foreach ($aclActionIds as $aclActionId) {
                    $connection->delete('acl_actions_rules', ['acl_action_rule_id' => $aclActionId]);
                    $connection->delete('acl_actions', ['acl_action_id' => $aclActionId]);
                }

                $connection->delete('acl_group_actions_relations', ['acl_group_id' => $aclGroupId]);
                $connection->delete('acl_group_contacts_relations', ['acl_group_id' => $aclGroupId]);
                $connection->delete('acl_groups', ['acl_group_id' => $aclGroupId]);
            }
        }

        $connection->executeStatement('DELETE FROM contact_password WHERE contact_id IN (SELECT contact_id FROM contact WHERE contact_alias = :identifier)', [
            'identifier' => $identifier,
        ]);
        $connection->executeStatement('DELETE FROM contact WHERE contact_alias = :identifier', [
            'identifier' => $identifier,
        ]);
    }
}
