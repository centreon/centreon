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

namespace Tests\Core\Integration;

use Centreon\Infrastructure\DatabaseConnection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for Core integration tests.
 *
 * Differences vs App\Shared\ApiTestCase:
 * - Extends WebTestCase (not API Platform's ApiTestCase) — Core controllers are plain Symfony controllers.
 * - Uses DatabaseConnection (extends PDO) instead of Doctrine DBAL — DAMA cannot wrap it.
 * - Transaction isolation is managed manually: a transaction is opened in setUp() and rolled
 *   back in tearDown(), keeping the database pristine after each test.
 *
 * Execution constraints:
 * - These tests must run inside the Docker container, where /etc/centreon/centreon.conf.php
 *   is present and points to the test database:
 *     docker exec centreon-app bash -c "cd /usr/share/centreon && vendor/bin/phpunit -c phpunit.core-integration.xml"
 */
abstract class CoreApiTestCase extends WebTestCase
{
    /**
     * Shared DatabaseConnection instance, refreshed in setUp() after each kernel boot.
     */
    protected static ?DatabaseConnection $db = null;

    protected ?string $token = null;

    private KernelBrowser $client;

    // ------------------------------------------------- LIFECYCLE -------------------------------------------------

    /**
     * Creates the test users declared by apiUsers() once for the whole test class.
     * These inserts are committed so they survive across kernel reboots between tests.
     */
    public static function setUpBeforeClass(): void
    {
        static::bootKernel();
        /** @var DatabaseConnection $db */
        $db = static::getContainer()->get(DatabaseConnection::class);
        $db->startTransaction();

        try {
            foreach (static::apiUsers() as $user) {
                if (\is_string($user)) {
                    $user = ['identifier' => $user, 'admin' => true];
                }

                self::createApiUser($db, $user['identifier'], $user['admin'] ?? false, $user['actions'] ?? []);
            }

            $db->commitTransaction();
        } catch (\Throwable $exception) {
            $db->rollBackTransaction();

            throw $exception;
        } finally {
            // Must shutdown before setUp() calls createClient(), which requires a cold kernel.
            static::ensureKernelShutdown();
        }
    }

    /**
     * Deletes the test users created by setUpBeforeClass().
     * The kernel is re-booted here because tearDown() shuts it down after each test.
     */
    public static function tearDownAfterClass(): void
    {
        static::bootKernel();
        /** @var DatabaseConnection $db */
        $db = static::getContainer()->get(DatabaseConnection::class);
        $db->startTransaction();

        try {
            foreach (static::apiUsers() as $user) {
                if (\is_string($user)) {
                    $user = ['identifier' => $user];
                }

                self::deleteApiUser($db, $user['identifier']);
            }

            $db->commitTransaction();
        } catch (\Throwable $exception) {
            $db->rollBackTransaction();

            throw $exception;
        } finally {
            static::ensureKernelShutdown();
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->token = null;

        /** @var DatabaseConnection $db */
        self::$db = static::getContainer()->get(DatabaseConnection::class);
    }

    /**
     * Removes the auth token inserted by login() and shuts down the kernel.
     *
     * Note: unlike the App suite (which uses DAMA to wrap all connections in a shared
     * transaction), Core tests cannot use transaction-based isolation — the security layer
     * may use a separate DB connection that would not see uncommitted data. Tokens are
     * committed immediately by login() and deleted explicitly here.
     */
    protected function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }

    // ------------------------------------------------- PUBLIC API -------------------------------------------------

    /**
     * Sends an HTTP request through the Symfony kernel browser.
     * Automatically injects the X-AUTH-TOKEN header when login() has been called.
     *
     * @param array<string, mixed> $options  Optional extra server variables (e.g. ['HTTP_ACCEPT' => 'application/json'])
     */
    final public function request(string $method, string $url, array $options = []): Response
    {
        $server = $options['server'] ?? [];

        if ($this->token !== null) {
            $server['HTTP_X_AUTH_TOKEN'] = $this->token;
        }

        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        $this->client->request($method, $url, server: $server);

        return $this->client->getResponse();
    }

    /**
     * Inserts an authentication token for the given user directly into the database (committed).
     * The token is deleted by logout(), called automatically in tearDown().
     */
    final protected function login(string $login = 'admin'): void
    {
        $this->token = "token_for_test";

        $stmt = self::$db->prepare('SELECT contact_id FROM contact WHERE contact_alias = :login');
        $stmt->execute([':login' => $login]);
        $contactId = $stmt->fetchColumn();

        if ($contactId === false) {
            self::createApiUser(self::$db, $login, admin: true);
            $stmt = self::$db->prepare('SELECT contact_id FROM contact WHERE contact_alias = :login');
            $stmt->execute([':login' => $login]);
            $contactId = $stmt->fetchColumn();
        }

        $stmt = self::$db->prepare(
            'INSERT INTO security_token (token, creation_date, expiration_date) VALUES (:token, :creationDate, NULL)'
        );
        $stmt->execute([':token' => $this->token, ':creationDate' => time()]);
        $tokenId = (int) self::$db->lastInsertId();

        $stmt = self::$db->prepare(
            'INSERT INTO security_authentication_tokens'
            . ' (provider_token_id, user_id, token, provider_token_refresh_id, provider_configuration_id, is_revoked)'
            . ' VALUES (:tokenId, :userId, :token, NULL, 1, 0)'
        );
        $stmt->execute([':tokenId' => $tokenId, ':userId' => (int) $contactId, ':token' => $this->token]);
    }

    final protected function logout(): void
    {
        if ($this->token === null || self::$db === null) {
            return;
        }

        $stmt = self::$db->prepare('DELETE FROM security_authentication_tokens WHERE token = :token');
        $stmt->execute([':token' => $this->token]);

        $stmt = self::$db->prepare('DELETE FROM security_token WHERE token = :token');
        $stmt->execute([':token' => $this->token]);

        $this->token = null;
    }

    // ----------------------------------------------- EXTENSION POINTS -------------------------------------------

    /**
     * Override in subclasses to declare the test users to create before the suite.
     *
     * @return list<array{identifier: string, admin?: bool, actions?: array<string>}|string>
     */
    protected static function apiUsers(): array
    {
        return [];
    }

    // ------------------------------------------------- HELPERS -------------------------------------------------

    /**
     * @param array<string> $actions
     */
    private static function createApiUser(
        DatabaseConnection $db,
        string $identifier,
        bool $admin = false,
        array $actions = [],
    ): void {
        $stmt = $db->prepare(
            'INSERT INTO contact (contact_name, contact_alias, contact_admin, contact_register, contact_activate, contact_email, reach_api, reach_api_rt)'
            . " VALUES (:name, :alias, :admin, 1, '1', :email, :reachApi, :reachApiRt)"
        );
        $stmt->execute([
            ':name' => $identifier,
            ':alias' => $identifier,
            ':admin' => $admin ? '1' : '0',
            ':email' => $identifier . '@email.com',
            ':reachApi' => $admin ? 1 : 0,
            ':reachApiRt' => $admin ? 1 : 0,
        ]);
        $contactId = (int) $db->lastInsertId();

        $stmt = $db->prepare(
            'INSERT INTO contact_password (contact_id, password, creation_date) VALUES (:id, :password, :date)'
        );
        $stmt->execute([
            ':id' => $contactId,
            ':password' => password_hash('Centreon!2021', \PASSWORD_BCRYPT),
            ':date' => (new \DateTimeImmutable())->getTimestamp(),
        ]);

        if (! $admin && $actions !== []) {
            self::setupAclForUser($db, $contactId, $identifier, $actions);
        }
    }

    /**
     * @param array<string> $actions
     */
    private static function setupAclForUser(
        DatabaseConnection $db,
        int $contactId,
        string $identifier,
        array $actions,
    ): void {
        $stmt = $db->prepare(
            'INSERT INTO acl_groups (acl_group_name, acl_group_alias, acl_group_activate) VALUES (:name, :alias, 1)'
        );
        $stmt->execute([':name' => "Test ACL Group for {$identifier}", ':alias' => "test_acl_{$identifier}"]);
        $aclGroupId = (int) $db->lastInsertId();

        $stmt = $db->prepare('INSERT INTO acl_actions (acl_action_name, acl_action_activate) VALUES (:name, 1)');
        $stmt->execute([':name' => "test_actions_{$identifier}"]);
        $aclActionId = (int) $db->lastInsertId();

        $stmt = $db->prepare(
            'INSERT INTO acl_group_actions_relations (acl_group_id, acl_action_id) VALUES (:groupId, :actionId)'
        );
        $stmt->execute([':groupId' => $aclGroupId, ':actionId' => $aclActionId]);

        foreach ($actions as $action) {
            $stmt = $db->prepare(
                'INSERT INTO acl_actions_rules (acl_action_rule_id, acl_action_name) VALUES (:ruleId, :name)'
            );
            $stmt->execute([':ruleId' => $aclActionId, ':name' => $action]);
        }

        $stmt = $db->prepare(
            'INSERT INTO acl_group_contacts_relations (acl_group_id, contact_contact_id) VALUES (:groupId, :contactId)'
        );
        $stmt->execute([':groupId' => $aclGroupId, ':contactId' => $contactId]);
    }

    private static function deleteApiUser(DatabaseConnection $db, string $identifier): void
    {
        $stmt = $db->prepare('SELECT contact_id FROM contact WHERE contact_alias = :identifier');
        $stmt->execute([':identifier' => $identifier]);
        $contactId = $stmt->fetchColumn();

        if ($contactId !== false) {
            $contactId = (int) $contactId;

            $stmt = $db->prepare(
                'SELECT acl_group_id FROM acl_group_contacts_relations WHERE contact_contact_id = :contactId'
            );
            $stmt->execute([':contactId' => $contactId]);
            $aclGroupIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($aclGroupIds as $aclGroupId) {
                $stmt = $db->prepare(
                    'SELECT acl_action_id FROM acl_group_actions_relations WHERE acl_group_id = :aclGroupId'
                );
                $stmt->execute([':aclGroupId' => $aclGroupId]);
                $aclActionIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

                foreach ($aclActionIds as $aclActionId) {
                    $stmt = $db->prepare('DELETE FROM acl_actions_rules WHERE acl_action_rule_id = :id');
                    $stmt->execute([':id' => $aclActionId]);
                    $stmt = $db->prepare('DELETE FROM acl_actions WHERE acl_action_id = :id');
                    $stmt->execute([':id' => $aclActionId]);
                }

                $stmt = $db->prepare('DELETE FROM acl_group_actions_relations WHERE acl_group_id = :id');
                $stmt->execute([':id' => $aclGroupId]);
                $stmt = $db->prepare('DELETE FROM acl_group_contacts_relations WHERE acl_group_id = :id');
                $stmt->execute([':id' => $aclGroupId]);
                $stmt = $db->prepare('DELETE FROM acl_groups WHERE acl_group_id = :id');
                $stmt->execute([':id' => $aclGroupId]);
            }
        }

        $stmt = $db->prepare(
            'DELETE FROM contact_password WHERE contact_id IN (SELECT contact_id FROM contact WHERE contact_alias = :identifier)'
        );
        $stmt->execute([':identifier' => $identifier]);

        $stmt = $db->prepare('DELETE FROM contact WHERE contact_alias = :identifier');
        $stmt->execute([':identifier' => $identifier]);
    }
}
