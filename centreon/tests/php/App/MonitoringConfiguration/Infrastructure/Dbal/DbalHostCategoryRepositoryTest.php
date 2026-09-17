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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategory;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostCategoryCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalHostCategoryRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\HostCategoryTransformer;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Dbal\DbalResourceAccessRepository;
use App\Shared\Domain\Repository\Paginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalHostCategoryRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalHostCategoryRepository $repository;

    private string $tag;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');

        // The repository has no ApiPlatform consumer yet (that lands with the Provider), so the
        // container would prune it; construct it directly from the always-public DBAL connection.
        $this->repository = new DbalHostCategoryRepository(
            $this->connection,
            new HostCategoryTransformer(),
            new DbalResourceAccessRepository($this->connection, $realTimeConnection),
        );

        // unique per test run so assertions are isolated from any pre-seeded host categories
        $this->tag = Uuid::v4()->toRfc4122();
    }

    public function testFindAllReturnsCategoriesAndExcludesSeverities(): void
    {
        $categoryName = "cat-{$this->tag}";
        $severityName = "sev-{$this->tag}";
        $this->insertHostCategory($categoryName);
        $this->insertHostCategory($severityName, level: 1);

        $names = $this->names($this->repository->findAll());

        self::assertContains($categoryName, $names);
        self::assertNotContains($severityName, $names, 'A host severity (level IS NOT NULL) must not appear among host categories.');
    }

    public function testFindAllFiltersByNameUsingLike(): void
    {
        $this->insertHostCategory("match-{$this->tag}");
        $this->insertHostCategory("other-{$this->tag}");

        $names = $this->names($this->repository->findAll((new HostCategoryCriteria())->withName("match-{$this->tag}")));

        self::assertSame(["match-{$this->tag}"], $names);
    }

    public function testFindAllPaginatesAndReturnsATotalAcrossAllPages(): void
    {
        $this->insertHostCategory("pg-{$this->tag}-A");
        $this->insertHostCategory("pg-{$this->tag}-B");
        $this->insertHostCategory("pg-{$this->tag}-C");

        // scope to our own rows via the name filter so pre-seeded categories cannot skew the total.
        // page 2 @ 1 item/page proves the OFFSET arithmetic (a page-1 request cannot).
        $result = $this->repository->findAll(
            (new HostCategoryCriteria())->withName("pg-{$this->tag}-")->withPagination(2, 1)
        );

        self::assertInstanceOf(Paginator::class, $result);
        self::assertSame(3, $result->getTotalItems());
        // rows are ordered by hc_id (insertion order A, B, C), so page 2 is the second row
        self::assertSame(["pg-{$this->tag}-B"], $this->names($result));
    }

    public function testFindAllRestrictsToAccessibleCategoriesForARestrictedViewer(): void
    {
        $accessibleName = "acl-in-{$this->tag}";
        $accessibleId = $this->insertHostCategory($accessibleName);
        $inaccessibleName = "acl-out-{$this->tag}";
        $this->insertHostCategory($inaccessibleName);
        // a severity whose id is granted to the viewer: it must stay excluded because it is a
        // severity, proving a severity id in the ACL set is inert against the level IS NULL filter.
        $severityName = "acl-sev-{$this->tag}";
        $severityId = $this->insertHostCategory($severityName, level: 1);

        $viewerId = new UserId($this->createContactRestrictedToHostCategories([$accessibleId, $severityId]));

        $names = $this->names($this->repository->findAll((new HostCategoryCriteria())->withViewerId($viewerId)));

        self::assertContains($accessibleName, $names);
        self::assertNotContains($inaccessibleName, $names, 'A category not in the viewer ACL is excluded.');
        self::assertNotContains($severityName, $names, 'A severity is excluded even when its id is granted in the ACL.');
    }

    public function testFindAllReturnsAllCategoriesForAViewerWithNoCategoryRestriction(): void
    {
        $categoryName = "acl-any-{$this->tag}";
        $this->insertHostCategory($categoryName);

        // ACL resource without any host-category relation → no restriction → sees every category
        $viewerId = new UserId($this->createContactRestrictedToHostCategories([]));

        $names = $this->names($this->repository->findAll((new HostCategoryCriteria())->withViewerId($viewerId)));

        self::assertContains($categoryName, $names);
    }

    public function testFindAllReturnsNoCategoryForAViewerGrantedOnlySeverities(): void
    {
        $severityId = $this->insertHostCategory("sev-only-{$this->tag}", level: 1);
        $this->insertHostCategory("cat-{$this->tag}"); // a real category, but not granted to the viewer

        // the viewer's ACL grants only a severity → restricted, but to zero regular categories
        $viewerId = new UserId($this->createContactRestrictedToHostCategories([$severityId]));

        $names = $this->names($this->repository->findAll((new HostCategoryCriteria())->withViewerId($viewerId)));

        self::assertSame([], $names, 'A viewer granted only severities is restricted and sees no category.');
    }

    /**
     * @param \IteratorAggregate<int, HostCategory>&\Countable $result
     *
     * @return list<string>
     */
    private function names(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (HostCategory $hostCategory): string => $hostCategory->name->value,
            iterator_to_array($result)
        ));
    }

    private function insertHostCategory(string $name, ?int $level = null): int
    {
        // a host category with a level is a host "severity"; a levelless one is a regular category
        $this->connection->insert('hostcategories', ['hc_name' => $name, 'hc_activate' => '1', 'level' => $level]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param list<int> $accessibleHostCategoryIds empty means the ACL resource carries no host-category restriction
     */
    private function createContactRestrictedToHostCategories(array $accessibleHostCategoryIds): int
    {
        $alias = "viewer-{$this->tag}";
        $this->connection->insert('contact', [
            'contact_name' => $alias,
            'contact_alias' => $alias,
            'contact_admin' => '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => $alias . '@email.com',
        ]);
        $contactId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_groups', [
            'acl_group_name' => "group-{$this->tag}",
            'acl_group_alias' => "group-{$this->tag}",
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_resources', [
            'acl_res_name' => "resource-{$this->tag}",
            'acl_res_alias' => "resource-{$this->tag}",
            'acl_res_activate' => '1',
        ]);
        $aclResId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_res_group_relations', [
            'acl_res_id' => $aclResId,
            'acl_group_id' => $aclGroupId,
        ]);

        foreach ($accessibleHostCategoryIds as $hostCategoryId) {
            $this->connection->insert('acl_resources_hc_relations', [
                'acl_res_id' => $aclResId,
                'hc_id' => $hostCategoryId,
            ]);
        }

        return $contactId;
    }
}
