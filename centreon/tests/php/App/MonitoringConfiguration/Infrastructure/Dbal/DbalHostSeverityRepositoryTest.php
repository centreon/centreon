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

use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverity;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostSeverityCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalHostSeverityRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\HostSeverityTransformer;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Dbal\DbalResourceAccessRepository;
use App\Shared\Domain\Repository\Paginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalHostSeverityRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalHostSeverityRepository $repository;

    private string $tag;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // The repository has no ApiPlatform consumer yet (that lands with the Provider), so the
        // container would prune it; construct it directly from the always-public DBAL connection.
        $this->repository = new DbalHostSeverityRepository(
            $this->connection,
            new HostSeverityTransformer(),
            new DbalResourceAccessRepository($this->connection),
        );

        // unique per test run so assertions are isolated from any pre-seeded host severities
        $this->tag = Uuid::v4()->toRfc4122();
    }

    public function testFindAllReturnsSeveritiesAndExcludesCategories(): void
    {
        $severityName = "sev-{$this->tag}";
        $categoryName = "cat-{$this->tag}";
        $this->insertHostSeverity($severityName);
        $this->insertHostSeverity($categoryName, level: null);

        $names = $this->names($this->repository->findAll());

        self::assertContains($severityName, $names);
        self::assertNotContains($categoryName, $names, 'A host category (level IS NULL) must not appear among host severities.');
    }

    public function testFindAllFiltersByNameUsingLike(): void
    {
        $this->insertHostSeverity("match-{$this->tag}");
        $this->insertHostSeverity("other-{$this->tag}");

        $names = $this->names($this->repository->findAll((new HostSeverityCriteria())->withName("match-{$this->tag}")));

        self::assertSame(["match-{$this->tag}"], $names);
    }

    public function testFindAllPaginatesAndReturnsATotalAcrossAllPages(): void
    {
        $this->insertHostSeverity("pg-{$this->tag}-A");
        $this->insertHostSeverity("pg-{$this->tag}-B");
        $this->insertHostSeverity("pg-{$this->tag}-C");

        // scope to our own rows via the name filter so pre-seeded severities cannot skew the total.
        // page 2 @ 1 item/page proves the OFFSET arithmetic (a page-1 request cannot).
        $result = $this->repository->findAll(
            (new HostSeverityCriteria())->withName("pg-{$this->tag}-")->withPagination(2, 1)
        );

        self::assertInstanceOf(Paginator::class, $result);
        self::assertSame(3, $result->getTotalItems());
        // rows are ordered by hc_id (insertion order A, B, C), so page 2 is the second row
        self::assertSame(["pg-{$this->tag}-B"], $this->names($result));
    }

    public function testFindAllRestrictsToAccessibleSeveritiesForARestrictedViewer(): void
    {
        $accessibleName = "acl-in-{$this->tag}";
        $accessibleId = $this->insertHostSeverity($accessibleName);
        $inaccessibleName = "acl-out-{$this->tag}";
        $this->insertHostSeverity($inaccessibleName);
        // a category whose id is granted to the viewer: it must stay excluded because it is a
        // category, proving a category id in the ACL set is inert against the level IS NOT NULL filter.
        $categoryName = "acl-cat-{$this->tag}";
        $categoryId = $this->insertHostSeverity($categoryName, level: null);

        $viewerId = new UserId($this->createContactRestrictedToHostCategories([$accessibleId, $categoryId]));

        $names = $this->names($this->repository->findAll((new HostSeverityCriteria())->withViewerId($viewerId)));

        self::assertContains($accessibleName, $names);
        self::assertNotContains($inaccessibleName, $names, 'A severity not in the viewer ACL is excluded.');
        self::assertNotContains($categoryName, $names, 'A category is excluded even when its id is granted in the ACL.');
    }

    public function testFindAllReturnsAllSeveritiesForAViewerWithNoCategoryRestriction(): void
    {
        $severityName = "acl-any-{$this->tag}";
        $this->insertHostSeverity($severityName);

        // ACL resource without any host-category relation → no restriction → sees every severity
        $viewerId = new UserId($this->createContactRestrictedToHostCategories([]));

        $names = $this->names($this->repository->findAll((new HostSeverityCriteria())->withViewerId($viewerId)));

        self::assertContains($severityName, $names);
    }

    public function testFindAllReturnsNoSeverityForAViewerGrantedOnlyCategories(): void
    {
        $categoryId = $this->insertHostSeverity("cat-only-{$this->tag}", level: null);
        $this->insertHostSeverity("sev-{$this->tag}"); // a real severity, but not granted to the viewer

        // the viewer's ACL grants only a levelless category → restricted, but to zero severities
        $viewerId = new UserId($this->createContactRestrictedToHostCategories([$categoryId]));

        $names = $this->names($this->repository->findAll((new HostSeverityCriteria())->withViewerId($viewerId)));

        self::assertSame([], $names, 'A viewer granted only categories is restricted and sees no severity.');
    }

    /**
     * @param \IteratorAggregate<int, HostSeverity>&\Countable $result
     *
     * @return list<string>
     */
    private function names(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (HostSeverity $hostSeverity): string => $hostSeverity->name->value,
            iterator_to_array($result)
        ));
    }

    private function insertHostSeverity(string $name, ?int $level = 1): int
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
