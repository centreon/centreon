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
 * every template inheriting from it, directly or not, are not offered as its parent.
 */
final class HostTemplateParentChoicesExclusionTest extends ApiTestCase
{
    use InsertsHostTemplateInheritance;
    use InsertsMonitoringConnectorTemplates;
    private const ENDPOINT = '/api/configuration/host_templates/host_templates';
    private const HOST_FORM_ENDPOINT = '/api/configuration/hosts/host_templates';
    private const INVALID_MESSAGE = 'The "exclude_template_id" filter must be a positive integer.';

    // about 100 times a warm response (under 30 ms), so a loaded runner cannot flake
    private const MAX_SECONDS = 2.0;

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

    /**
     * A loop is refused on save, but older data may hold one: the route must still answer, at once.
     */
    public function testItAnswersPromptlyOnALoopBetweenTwoTemplates(): void
    {
        [$first, $second, $belowSecond, $unrelated] = $this->templates(4);
        $this->linkTemplates($this->connection, [$first, $second], [$second, $first], [$belowSecond, $second]);
        $this->login();

        [$seconds, $offered] = $this->timedOfferedIds($first);

        self::assertLessThan(self::MAX_SECONDS, $seconds);
        self::assertSame([$unrelated], $offered);
    }

    public function testItAnswersPromptlyOnATemplateInheritingFromItself(): void
    {
        [$template, $child, $unrelated] = $this->templates(3);
        $this->linkTemplates($this->connection, [$template, $template], [$child, $template]);
        $this->login();

        [$seconds, $offered] = $this->timedOfferedIds($template);

        self::assertLessThan(self::MAX_SECONDS, $seconds);
        self::assertSame([$unrelated], $offered);
    }

    public function testItHandlesA200LevelChainWithOneMoreQuery(): void
    {
        $chain = $this->templates(201);
        $links = [];
        for ($level = 1; $level < 201; ++$level) {
            $links[] = [$chain[$level], $chain[$level - 1]];
        }
        $this->linkTemplates($this->connection, ...$links);
        $this->login();

        [$extraQueries, $seconds, $total] = $this->measureAgainstPlainListing($chain[100]);

        self::assertSame(1, $extraQueries, 'the descendants cost one query, whatever the depth');
        self::assertLessThan(self::MAX_SECONDS, $seconds);
        // the 100 ancestors stay, the template and its 100 descendants go
        self::assertSame(100, $total);
    }

    public function testItHandlesA2000TemplateTreeWithOneMoreQuery(): void
    {
        [$root] = $this->templates(1, prefix: 'root');
        $children = $this->templates(40, prefix: 'child');
        $grandchildren = $this->templates(40 * 49, prefix: 'grandchild');
        $this->templates(1, prefix: 'unrelated');
        $links = array_map(static fn (int $child): array => [$child, $root], $children);
        foreach ($grandchildren as $index => $grandchild) {
            $links[] = [$grandchild, $children[intdiv($index, 49)]];
        }
        $this->linkTemplates($this->connection, ...$links);
        $this->login();

        [$extraQueries, $seconds, $total] = $this->measureAgainstPlainListing($root);

        self::assertSame(1, $extraQueries, 'the descendants cost one query, whatever the width');
        self::assertLessThan(self::MAX_SECONDS, $seconds);
        self::assertSame(1, $total);
    }

    #[DataProvider('provideInvalidValues')]
    public function testAnInvalidValueIsABadRequest(string $value): void
    {
        $this->login();

        $response = $this->request('GET', self::ENDPOINT . '?exclude_template_id=' . rawurlencode($value));

        self::assertResponseStatusCodeSame(400);
        $body = $response->toArray(false);
        self::assertSame(['code', 'message'], array_keys($body));
        self::assertSame(self::INVALID_MESSAGE, $body['message']);
    }

    /**
     * The host form's list takes no such parameter: it neither filters nor rejects it.
     */
    #[DataProvider('provideValuesTheHostFormListIgnores')]
    public function testTheHostFormListIgnoresTheParameter(string $value): void
    {
        [$template, $child] = $this->templates(2);
        $this->linkTemplates($this->connection, [$child, $template]);
        $this->login();

        $response = $this->request('GET', self::HOST_FORM_ENDPOINT, ['query' => [
            'exclude_template_id' => $value === '<template>' ? (string) $template : $value,
            'name' => ['lk' => $this->tag],
        ]]);

        self::assertResponseIsSuccessful();
        self::assertSame([$template, $child], array_column((array) $response->toArray()['member'], 'id'));
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

        yield 'negative' => ['-1'];

        yield 'letters' => ['abc'];

        yield 'empty' => [''];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValuesTheHostFormListIgnores(): iterable
    {
        yield 'an existing template' => ['<template>'];

        yield 'an invalid value' => ['abc'];
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
     * @return array{float, list<int>} the wall-clock time of the excluding request, and what it offers
     */
    private function timedOfferedIds(int $editedId): array
    {
        $this->warmUp();
        $this->offeredIds($editedId);
        $start = hrtime(true);
        $offered = $this->offeredIds($editedId);

        return [(hrtime(true) - $start) / 1e9, $offered];
    }

    /**
     * The first requests of a test process are slow whatever their query: they are not the cost
     * being measured.
     */
    private function warmUp(): void
    {
        $this->request('GET', self::ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);
        self::assertResponseIsSuccessful();
    }

    /**
     * The same listing is requested without, then with, the parameter: the difference in statements
     * the server received is the cost of the exclusion.
     *
     * @return array{int, float, int} the extra statements, the wall-clock time and totalItems of the excluding request
     */
    private function measureAgainstPlainListing(int $editedId): array
    {
        $query = ['name' => ['lk' => $this->tag]];
        $this->warmUp();
        $this->request('GET', self::ENDPOINT, ['query' => $query + ['exclude_template_id' => (string) $editedId]]);

        $before = $this->sessionQuestions();
        $this->request('GET', self::ENDPOINT, ['query' => $query]);
        $plain = $this->sessionQuestions() - $before;
        self::assertResponseIsSuccessful();

        $before = $this->sessionQuestions();
        $start = hrtime(true);
        $response = $this->request('GET', self::ENDPOINT, ['query' => $query + ['exclude_template_id' => (string) $editedId]]);
        $seconds = (hrtime(true) - $start) / 1e9;
        $excluding = $this->sessionQuestions() - $before;
        self::assertResponseIsSuccessful();

        $total = $response->toArray()['totalItems'];
        Assert::integer($total);

        return [$excluding - $plain, $seconds, $total];
    }

    private function sessionQuestions(): int
    {
        $row = $this->connection->fetchAssociative("SHOW SESSION STATUS LIKE 'Questions'");
        Assert::isArray($row);
        Assert::numeric($row['Value']);

        return (int) $row['Value'];
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
