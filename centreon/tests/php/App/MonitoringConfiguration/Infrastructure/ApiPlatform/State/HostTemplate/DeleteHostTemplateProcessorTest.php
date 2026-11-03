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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\HostTemplateActionsFixtures;
use Tests\App\Shared\ApiTestCase;

final class DeleteHostTemplateProcessorTest extends ApiTestCase
{
    use HostTemplateActionsFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');
        $this->realTimeConnection = $realTimeConnection;
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('DELETE', '/api/configuration/host_templates/1');

        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenWithoutAnyHostTemplatePermission(): void
    {
        $username = bin2hex(random_bytes(8));
        $this->createApiUser($this->connection, $username, admin: false);
        $this->login($username);

        $this->request('DELETE', '/api/configuration/host_templates/1');

        self::assertResponseStatusCodeSame(403);
    }

    public function testItIsForbiddenWithTheReadPermissionAlone(): void
    {
        $templateId = $this->insertTemplate();
        $this->loginAsHostTemplateReader();

        $this->request('DELETE', '/api/configuration/host_templates/' . $templateId);

        self::assertResponseStatusCodeSame(403);
        self::assertNotFalse($this->templateIdByNameOfId($templateId));
    }

    #[DataProvider('invalidIds')]
    public function testItReturns404ForAnInvalidId(string $id): void
    {
        $this->login();

        $this->request('DELETE', '/api/configuration/host_templates/' . $id);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIds(): iterable
    {
        yield 'non-numeric' => ['not-a-number'];

        yield 'zero' => ['0'];

        yield 'leading zero' => ['007'];
    }

    public function testItReturns404ForAHostAndLeavesTheHost(): void
    {
        $hostId = $this->insertHost($this->uniqueName());
        $this->login();

        $this->request('DELETE', '/api/configuration/host_templates/' . $hostId);

        self::assertResponseStatusCodeSame(404);
        self::assertNotFalse($this->connection->fetchOne('SELECT 1 FROM host WHERE host_id = ?', [$hostId]));
    }

    public function testItRefusesALockedTemplate(): void
    {
        $templateId = $this->insertTemplate(['host_locked' => 1]);
        $this->login();

        $this->request('DELETE', '/api/configuration/host_templates/' . $templateId, ['headers' => ['Accept' => '*/*']]);

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains(['message' => "Host template {$templateId} is locked and cannot be changed."]);
        self::assertNotFalse($this->templateIdByNameOfId($templateId));
    }

    public function testItDeletesTheTemplateDetachesItsInheritorsAndFlagsTheirPollers(): void
    {
        $pollerId = $this->insertPoller();
        $otherPollerId = $this->insertPoller();
        $templateId = $this->insertTemplate();
        $this->connection->insert('ns_host_relation', ['host_host_id' => $templateId, 'nagios_server_id' => $otherPollerId]);
        $hostId = $this->insertHost($this->uniqueName(), $pollerId);
        $this->connection->insert('host_template_relation', ['host_host_id' => $hostId, 'host_tpl_id' => $templateId, '`order`' => 1]);
        $aclRows = $this->centreonAclRowCount();
        $this->login();

        $this->request('DELETE', '/api/configuration/host_templates/' . $templateId);

        self::assertResponseStatusCodeSame(204);
        self::assertFalse($this->templateIdByNameOfId($templateId));
        self::assertSame([], $this->connection->fetchFirstColumn('SELECT host_tpl_id FROM host_template_relation WHERE host_host_id = ?', [$hostId]));
        self::assertTrue($this->isPollerFlagged($pollerId));
        self::assertFalse($this->isPollerFlagged($otherPollerId), "The template's own poller generates nothing for it.");
        self::assertSame(['d'], $this->loggedActions($templateId));
        self::assertSame($aclRows, $this->centreonAclRowCount());
    }

    private function templateIdByNameOfId(int $id): int|false
    {
        $id = $this->connection->fetchOne("SELECT host_id FROM host WHERE host_id = ? AND host_register = '0'", [$id]);

        return $id === false ? false : self::asInt($id);
    }
}
