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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\InsertsMonitoringConnectorTemplates;
use Tests\App\MonitoringConfiguration\Infrastructure\InsertsHostTemplateInheritance;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

/**
 * GET /configuration/host_templates/host_templates?exclude_template_id={id}: the edited template and
 * every template inheriting from it, directly or not, are not offered as its parent. The inheritance
 * walk itself (inactive templates, loops, depth) is pinned by DbalHostTemplateRepositoryDescendantsTest.
 */
final class HostTemplateParentChoicesExclusionTest extends ApiTestCase
{
    use InsertsHostTemplateInheritance;
    use InsertsMonitoringConnectorTemplates;
    private const ENDPOINT = '/api/configuration/host_templates/host_templates';

    private Connection $connection;

    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->tag = Uuid::v4()->toBase58();
    }

    protected function tearDown(): void
    {
        $this->dropMonitoringConnectorTables($this->connection);

        parent::tearDown();
    }

    public function testItLeavesOutTheTemplateAndEveryDescendantButKeepsAncestorsAndUnrelatedOnes(): void
    {
        [$grandparent, $parent, $edited, $child, $grandchild, $left, $diamond, $sibling, $unrelated] = $this->templates(9);
        $this->linkTemplates(
            $this->connection,
            [$parent, $grandparent],
            [$edited, $parent],
            [$child, $edited],
            [$grandchild, $child],
            [$left, $edited],
            // reached from the edited template through two paths
            [$diamond, $child],
            [$diamond, $left],
            [$sibling, $parent],
        );
        $this->login();

        self::assertSame($this->sorted($grandparent, $parent, $sibling, $unrelated), $this->offeredIds($edited));
    }

    public function testItAlsoLeavesOutTheTemplatesTheLicenseNoLongerAllows(): void
    {
        [$edited, $child, $unlicensed, $kept] = $this->templates(4);
        $this->linkTemplates($this->connection, [$child, $edited]);
        $this->insertMonitoringConnectorTemplates($this->connection, $unlicensed);
        $this->login();

        self::assertSame([$kept], $this->offeredIds($edited));
    }

    public function testItAppliesTheNameFilterToTheRemainingTemplates(): void
    {
        [$edited, $child, $otherWeb] = $this->templates(3, prefix: 'web');
        $this->templates(1, prefix: 'db');
        $this->linkTemplates($this->connection, [$child, $edited]);
        $this->login();

        $response = $this->request('GET', self::ENDPOINT, ['query' => [
            'exclude_template_id' => (string) $edited,
            'name' => ['lk' => "web-{$this->tag}"],
        ]]);

        self::assertResponseIsSuccessful();
        self::assertSame([$otherWeb], array_column((array) $response->toArray()['member'], 'id'));
    }

    /**
     * A host inherits from the two templates: excluding it by id must not exclude them.
     */
    #[DataProvider('provideIdsNamingNoTemplate')]
    public function testAnIdNamingNoTemplateExcludesNothing(string $kind): void
    {
        $ids = $this->templates(2);
        $this->connection->insert('host', ['host_name' => "host-{$this->tag}", 'host_register' => '1', 'host_activate' => '1']);
        $hostId = (int) $this->connection->lastInsertId();
        $this->linkTemplates($this->connection, [$hostId, $ids[0]], [$hostId, $ids[1]]);
        $this->login();

        self::assertSame($ids, $this->offeredIds($kind === 'host' ? $hostId : \PHP_INT_MAX));
    }

    #[DataProvider('provideInvalidValues')]
    public function testAnInvalidValueIsRefusedOnTheParameter(string $value): void
    {
        $this->login();

        $response = $this->request('GET', self::ENDPOINT . '?exclude_template_id=' . rawurlencode($value));

        self::assertResponseStatusCodeSame(422);
        $body = $response->toArray(false);
        self::assertSame(['code', 'message'], array_keys($body));
        self::assertIsString($body['message']);
        self::assertStringStartsWith('[exclude_template_id] ', $body['message']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIdsNamingNoTemplate(): iterable
    {
        yield 'unknown id' => ['unknown'];

        yield 'host id' => ['host'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidValues(): iterable
    {
        yield 'zero' => ['0'];

        yield 'letters' => ['abc'];
    }

    /**
     * @return list<int> the ids offered as parent of $editedId among this test's templates
     */
    private function offeredIds(int $editedId): array
    {
        $response = $this->request('GET', self::ENDPOINT, ['query' => [
            'exclude_template_id' => (string) $editedId,
            'name' => ['lk' => $this->tag],
        ]]);
        self::assertResponseIsSuccessful();

        $ids = array_column((array) $response->toArray()['member'], 'id');
        Assert::allInteger($ids);

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function sorted(int ...$ids): array
    {
        sort($ids);

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function templates(int $count, string $prefix = 'tpl'): array
    {
        return $this->insertTemplates($this->connection, $count, "{$prefix}-{$this->tag}");
    }
}
