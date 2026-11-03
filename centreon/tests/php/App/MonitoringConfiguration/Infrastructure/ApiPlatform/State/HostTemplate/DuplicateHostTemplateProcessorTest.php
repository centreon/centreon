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

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\HostTemplateActionsFixtures;
use Tests\App\Shared\ApiTestCase;

final class DuplicateHostTemplateProcessorTest extends ApiTestCase
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
        $this->request('POST', '/api/configuration/host_templates/1/_duplicate');

        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenWithoutAnyHostTemplatePermission(): void
    {
        $username = bin2hex(random_bytes(8));
        $this->createApiUser($this->connection, $username, admin: false);
        $this->login($username);

        $this->request('POST', '/api/configuration/host_templates/1/_duplicate');

        self::assertResponseStatusCodeSame(403);
    }

    public function testItIsForbiddenWithTheReadPermissionAlone(): void
    {
        $name = $this->uniqueName();
        $templateId = $this->insertTemplate(['host_name' => $name]);
        $this->loginAsHostTemplateReader();

        $this->request('POST', "/api/configuration/host_templates/{$templateId}/_duplicate");

        self::assertResponseStatusCodeSame(403);
        self::assertFalse($this->templateIdByName("{$name}_1"));
    }

    #[DataProvider('invalidIds')]
    public function testItReturns404ForAnInvalidId(string $id): void
    {
        $this->login();

        $this->request('POST', '/api/configuration/host_templates/' . $id . '/_duplicate');

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

    public function testItReturns404ForAHost(): void
    {
        $hostName = $this->uniqueName();
        $hostId = $this->insertHost($hostName);
        $this->login();

        $this->request('POST', "/api/configuration/host_templates/{$hostId}/_duplicate");

        self::assertResponseStatusCodeSame(404);
        self::assertFalse($this->connection->fetchOne('SELECT 1 FROM host WHERE host_name = ?', ["{$hostName}_1"]));
    }

    public function testItRefusesALockedTemplate(): void
    {
        $name = $this->uniqueName();
        $templateId = $this->insertTemplate(['host_name' => $name, 'host_locked' => 1]);
        $this->login();

        $this->request('POST', "/api/configuration/host_templates/{$templateId}/_duplicate");

        self::assertResponseStatusCodeSame(400);
        self::assertFalse($this->templateIdByName("{$name}_1"));
    }

    /**
     * @param array{headers?: array<string, mixed>, json?: mixed, body?: string} $options
     */
    #[DataProvider('autoNamingBodies')]
    public function testWithoutANameTheCopyIsAutoNamed(array $options): void
    {
        $name = $this->uniqueName();
        $templateId = $this->insertTemplate(['host_name' => $name]);
        $this->insertTemplate(['host_name' => "{$name}_1"]);
        $this->login();

        $this->request('POST', "/api/configuration/host_templates/{$templateId}/_duplicate", $options);

        self::assertResponseStatusCodeSame(204);
        self::assertNotFalse($this->templateIdByName("{$name}_2"));
    }

    /**
     * @return iterable<string, array{array{headers?: array<string, mixed>, json?: mixed, body?: string}}>
     */
    public static function autoNamingBodies(): iterable
    {
        yield 'no body' => [[]];

        yield 'empty object' => [['json' => new \ArrayObject()]];

        yield 'unknown field only' => [['json' => ['alias' => 'ignored']]];
    }

    public function testTheCopyTakesTheGivenNameItsSpacesTurnedIntoUnderscores(): void
    {
        $templateId = $this->insertTemplate();
        $wanted = $this->uniqueName();
        $this->login();

        $this->request('POST', "/api/configuration/host_templates/{$templateId}/_duplicate", ['json' => ['name' => "  {$wanted} copy  "]]);

        self::assertResponseStatusCodeSame(204);
        $response = $this->request('GET', '/api/configuration/host_templates', ['query' => ['name' => ['lk' => $wanted]]]);
        $member = $response->toArray()['member'];
        self::assertIsArray($member);
        self::assertSame(["{$wanted}_copy"], array_column($member, 'name'));
    }

    public function testANameOfTheMaximumLengthIsAccepted(): void
    {
        $templateId = $this->insertTemplate();
        $name = mb_substr($this->uniqueName() . str_repeat('x', HostTemplateName::MAX_LENGTH), 0, HostTemplateName::MAX_LENGTH);
        $this->login();

        $this->request('POST', "/api/configuration/host_templates/{$templateId}/_duplicate", ['json' => ['name' => $name]]);

        self::assertResponseStatusCodeSame(204);
        self::assertNotFalse($this->templateIdByName($name));
    }

    /**
     * @param array{headers?: array<string, mixed>, json?: mixed, body?: string} $options
     */
    #[DataProvider('invalidNames')]
    public function testAnInvalidNameIsRefusedAndNothingIsCreated(array $options, int $status): void
    {
        $name = $this->uniqueName();
        $templateId = $this->insertTemplate(['host_name' => $name]);
        $copies = $this->copiesOf($name);
        $this->login();

        $this->request('POST', "/api/configuration/host_templates/{$templateId}/_duplicate", $options);

        self::assertResponseStatusCodeSame($status);
        self::assertSame($copies, $this->copiesOf($name));
    }

    /**
     * @return iterable<string, array{array{headers?: array<string, mixed>, json?: mixed, body?: string}, int}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [['json' => ['name' => '']], 422];

        yield 'whitespace only' => [['json' => ['name' => '   ']], 422];

        yield 'null' => [['json' => ['name' => null]], 422];

        yield 'over the maximum length' => [['json' => ['name' => str_repeat('a', HostTemplateName::MAX_LENGTH + 1)]], 422];

        yield 'not a string' => [['json' => ['name' => 5]], 422];

        yield 'line break inside' => [['json' => ['name' => "copy\ndefine command"]], 422];

        yield 'tab inside' => [['json' => ['name' => "copy\tname"]], 422];

        yield 'malformed JSON' => [['body' => '{"name":', 'headers' => ['Content-Type' => 'application/json']], 400];

        yield 'not JSON' => [['body' => 'name=copy', 'headers' => ['Content-Type' => 'text/plain']], 400];

        yield 'JSON string' => [['body' => '"copy"', 'headers' => ['Content-Type' => 'application/json']], 400];

        yield 'JSON list' => [['body' => '["copy"]', 'headers' => ['Content-Type' => 'application/json']], 400];
    }

    public function testANameUsedByAHostIsAConflict(): void
    {
        $taken = $this->uniqueName();
        $this->insertHost($taken);
        $templateId = $this->insertTemplate();
        $this->login();

        $this->request('POST', "/api/configuration/host_templates/{$templateId}/_duplicate", ['json' => ['name' => $taken]]);

        self::assertResponseStatusCodeSame(409);
        self::assertFalse($this->templateIdByName($taken));
    }

    public function testANameUsedByATemplateIsAConflictOnceNormalized(): void
    {
        $taken = $this->uniqueName();
        $this->insertTemplate(['host_name' => "{$taken}_copy"]);
        $templateId = $this->insertTemplate();
        $this->login();

        $this->request('POST', "/api/configuration/host_templates/{$templateId}/_duplicate", ['json' => ['name' => "{$taken} copy"]]);

        self::assertResponseStatusCodeSame(409);
        self::assertCount(1, $this->connection->fetchFirstColumn('SELECT host_id FROM host WHERE host_name = ?', ["{$taken}_copy"]));
    }

    public function testAGeneratedNameOverTheLimitIsAValidationFailure(): void
    {
        $name = mb_substr($this->uniqueName() . str_repeat('x', HostTemplateName::MAX_LENGTH), 0, HostTemplateName::MAX_LENGTH - 1);
        $templateId = $this->insertTemplate(['host_name' => $name]);
        $this->login();

        $this->request('POST', "/api/configuration/host_templates/{$templateId}/_duplicate", ['headers' => ['Accept' => '*/*']]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['message' => "The name generated from \"{$name}\" would exceed the maximum length."]);
        self::assertSame([$name], $this->copiesOf($name));
    }

    public function testErrorsKeepTheirShapeForABrowserClient(): void
    {
        $taken = $this->uniqueName();
        $this->insertHost($taken);
        $templateId = $this->insertTemplate();
        $this->login();

        $response = $this->request('POST', "/api/configuration/host_templates/{$templateId}/_duplicate", [
            'json' => ['name' => ''],
            'headers' => ['Accept' => '*/*'],
        ]);
        self::assertResponseStatusCodeSame(422);
        $body = json_decode($response->getContent(false), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame(['code', 'message'], array_keys($body));
        self::assertSame(422, $body['code']);
        self::assertIsString($body['message']);
        self::assertStringStartsWith('[name] ', $body['message']);

        $this->request('POST', "/api/configuration/host_templates/{$templateId}/_duplicate", [
            'json' => ['name' => $taken],
            'headers' => ['Accept' => '*/*'],
        ]);
        self::assertResponseStatusCodeSame(409);
        self::assertJsonContains(['message' => 'A host or host template already uses this name.']);
    }

    public function testTheCopyFlagsNoPollerLogsAnAddAndWritesNoAcl(): void
    {
        $pollerId = $this->insertPoller();
        $name = $this->uniqueName();
        $templateId = $this->insertTemplate(['host_name' => $name]);
        $this->connection->insert('ns_host_relation', ['host_host_id' => $templateId, 'nagios_server_id' => $pollerId]);
        $aclRows = $this->centreonAclRowCount();
        $this->login();

        $this->request('POST', "/api/configuration/host_templates/{$templateId}/_duplicate");

        self::assertResponseStatusCodeSame(204);
        $copyId = $this->templateIdByName("{$name}_1");
        self::assertNotFalse($copyId);
        self::assertSame([$pollerId], array_map(self::asInt(...), $this->connection->fetchFirstColumn('SELECT nagios_server_id FROM ns_host_relation WHERE host_host_id = ?', [$copyId])));
        self::assertFalse($this->isPollerFlagged($pollerId));
        self::assertSame(['a'], $this->loggedActions($copyId));
        self::assertSame($aclRows, $this->centreonAclRowCount());
    }

    /**
     * @return list<string>
     */
    private function copiesOf(string $name): array
    {
        /** @var list<string> */
        return $this->connection->fetchFirstColumn('SELECT host_name FROM host WHERE host_name LIKE ?', [$name . '%']);
    }
}
