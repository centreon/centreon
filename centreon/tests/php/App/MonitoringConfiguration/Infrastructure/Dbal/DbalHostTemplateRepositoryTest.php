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

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostTemplateCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalHostTemplateRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\HostTemplateTransformer;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Dbal\DbalResourceAccessRepository;
use App\Shared\Domain\Repository\Paginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalHostTemplateRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalHostTemplateRepository $repository;

    private string $tag;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // The repository has no ApiPlatform consumer yet (that lands with the Provider), so the
        // container would prune it; construct it directly from the always-public DBAL connection.
        $this->repository = new DbalHostTemplateRepository(
            $this->connection,
            new HostTemplateTransformer(),
            new DbalResourceAccessRepository($this->connection),
        );

        // unique per test run so assertions are isolated from any pre-seeded host templates
        $this->tag = Uuid::v4()->toRfc4122();
    }

    public function testFindAllReturnsHostTemplatesAndExcludesRegularHosts(): void
    {
        $templateName = "tpl-{$this->tag}";
        $hostName = "host-{$this->tag}";
        $this->insertHostTemplate($templateName);
        $this->insertRegularHost($hostName);

        $names = $this->names($this->repository->findAll());

        self::assertContains($templateName, $names);
        self::assertNotContains($hostName, $names, 'A regular host (host_register = 1) must not appear among host templates.');
    }

    public function testFindAllFiltersByNameUsingLike(): void
    {
        $this->insertHostTemplate("match-{$this->tag}");
        $this->insertHostTemplate("other-{$this->tag}");

        $names = $this->names($this->repository->findAll((new HostTemplateCriteria())->withName("match-{$this->tag}")));

        self::assertSame(["match-{$this->tag}"], $names);
    }

    public function testFindAllPaginatesAndReturnsATotalAcrossAllPages(): void
    {
        $this->insertHostTemplate("pg-{$this->tag}-A");
        $this->insertHostTemplate("pg-{$this->tag}-B");
        $this->insertHostTemplate("pg-{$this->tag}-C");

        // scope to our own rows via the name filter so pre-seeded templates cannot skew the total.
        // page 2 @ 1 item/page proves the OFFSET arithmetic (a page-1 request cannot).
        $result = $this->repository->findAll(
            (new HostTemplateCriteria())->withName("pg-{$this->tag}-")->withPagination(2, 1)
        );

        self::assertInstanceOf(Paginator::class, $result);
        self::assertSame(3, $result->getTotalItems());
        // rows are ordered by host_id (insertion order A, B, C), so page 2 is the second row
        self::assertSame(["pg-{$this->tag}-B"], $this->names($result));
    }

    public function testFindAllRestrictsToAccessibleSeveritiesForARestrictedViewer(): void
    {
        // legacy scopes restricted viewers by host *severities* (categories with a level), not by
        // regular host categories, even when the regular category is in the viewer's ACL.
        $severityId = $this->insertHostCategory("sev-{$this->tag}", level: 1);
        $regularId = $this->insertHostCategory("reg-{$this->tag}");

        $inSeverity = $this->insertHostTemplate("acl-severity-{$this->tag}");
        $this->linkTemplateToCategory($inSeverity, $severityId);
        $inRegular = $this->insertHostTemplate("acl-regular-{$this->tag}");
        $this->linkTemplateToCategory($inRegular, $regularId);
        $uncategorizedName = "acl-none-{$this->tag}";
        $this->insertHostTemplate($uncategorizedName);

        // the viewer's ACL grants BOTH the severity and the regular category
        $viewerId = new UserId($this->createContactRestrictedToHostCategories([$severityId, $regularId]));

        $names = $this->names($this->repository->findAll((new HostTemplateCriteria())->withViewerId($viewerId)));

        self::assertContains("acl-severity-{$this->tag}", $names);
        self::assertNotContains("acl-regular-{$this->tag}", $names, 'A template linked only to a regular (levelless) category is excluded even though that category is in the ACL — legacy scopes by severities.');
        self::assertNotContains($uncategorizedName, $names, 'A template with no category is excluded for a restricted viewer.');
    }

    public function testFindAllReturnsAllTemplatesForAViewerWithNoCategoryRestriction(): void
    {
        $severityId = $this->insertHostCategory("sev-{$this->tag}", level: 1);
        $categorizedId = $this->insertHostTemplate("acl-in-{$this->tag}");
        $this->linkTemplateToCategory($categorizedId, $severityId);
        $uncategorizedName = "acl-out-{$this->tag}";
        $this->insertHostTemplate($uncategorizedName);

        // ACL resource without any host-category relation → no restriction → sees everything
        $viewerId = new UserId($this->createContactRestrictedToHostCategories([]));

        $names = $this->names($this->repository->findAll((new HostTemplateCriteria())->withViewerId($viewerId)));

        self::assertContains("acl-in-{$this->tag}", $names);
        self::assertContains($uncategorizedName, $names);
    }

    /**
     * @param \IteratorAggregate<int, HostTemplate>&\Countable $result
     *
     * @return list<string>
     */
    private function names(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (HostTemplate $hostTemplate): string => $hostTemplate->name->value,
            iterator_to_array($result)
        ));
    }

    private function insertHostTemplate(string $name): int
    {
        $this->connection->insert('host', ['host_name' => $name, 'host_register' => '0', 'host_activate' => '1']);

        return (int) $this->connection->lastInsertId();
    }

    private function insertRegularHost(string $name): int
    {
        $this->connection->insert('host', ['host_name' => $name, 'host_register' => '1', 'host_activate' => '1']);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHostCategory(string $name, ?int $level = null): int
    {
        // a host category with a level is a host "severity"; a levelless one is a regular category
        $this->connection->insert('hostcategories', ['hc_name' => $name, 'hc_activate' => '1', 'level' => $level]);

        return (int) $this->connection->lastInsertId();
    }

    private function linkTemplateToCategory(int $hostTemplateId, int $hostCategoryId): void
    {
        $this->connection->insert('hostcategories_relation', [
            'hostcategories_hc_id' => $hostCategoryId,
            'host_host_id' => $hostTemplateId,
        ]);
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
