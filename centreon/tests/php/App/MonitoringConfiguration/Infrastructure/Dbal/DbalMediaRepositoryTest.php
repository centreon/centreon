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

use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\MediaCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalMediaRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\MediaTransformer;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Dbal\DbalResourceAccessRepository;
use App\Shared\Domain\Repository\Paginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalMediaRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalMediaRepository $repository;

    private string $tag;

    private int $defaultDirId;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // The repository has no ApiPlatform consumer yet (that lands with the Provider), so the
        // container would prune it; construct it directly from the always-public DBAL connection.
        $this->repository = new DbalMediaRepository(
            $this->connection,
            new MediaTransformer(),
            new DbalResourceAccessRepository($this->connection, $this->connection),
        );

        // unique per test run so assertions are isolated from any pre-seeded media
        $this->tag = Uuid::v4()->toRfc4122();
        $this->connection->insert('view_img_dir', ['dir_name' => "dir-{$this->tag}"]);
        $this->defaultDirId = (int) $this->connection->lastInsertId();
    }

    public function testExistsOneReturnsTrueForAnExistingMedia(): void
    {
        $imgId = $this->insertMedia("existing-{$this->tag}.png", $this->defaultDirId);

        self::assertTrue($this->repository->existsOne(new MediaId($imgId)));
    }

    public function testExistsOneReturnsFalseForAnUnknownMedia(): void
    {
        self::assertFalse($this->repository->existsOne(new MediaId(999999)));
    }

    public function testFindAllReturnsAllMediaWithoutAViewer(): void
    {
        $name = "media-{$this->tag}.png";
        $this->insertMedia($name, $this->defaultDirId);

        $names = $this->names($this->repository->findAll());

        self::assertContains($name, $names);
    }

    public function testFindAllFiltersByNameUsingLike(): void
    {
        $this->insertMedia("match-{$this->tag}.png", $this->defaultDirId);
        $this->insertMedia("other-{$this->tag}.png", $this->defaultDirId);

        $names = $this->names($this->repository->findAll((new MediaCriteria())->withName("match-{$this->tag}")));

        self::assertSame(["match-{$this->tag}.png"], $names);
    }

    public function testFindAllPaginatesAndReturnsATotalAcrossAllPages(): void
    {
        $this->insertMedia("pg-{$this->tag}-A.png", $this->defaultDirId);
        $this->insertMedia("pg-{$this->tag}-B.png", $this->defaultDirId);
        $this->insertMedia("pg-{$this->tag}-C.png", $this->defaultDirId);

        // scope to our own rows via the name filter so pre-seeded media cannot skew the total.
        // page 2 @ 1 item/page proves the OFFSET arithmetic (a page-1 request cannot).
        $result = $this->repository->findAll(
            (new MediaCriteria())->withName("pg-{$this->tag}-")->withPagination(2, 1)
        );

        self::assertInstanceOf(Paginator::class, $result);
        self::assertSame(3, $result->getTotalItems());
        // rows are ordered by img_path (A, B, C), so page 2 is the second row
        self::assertSame(["pg-{$this->tag}-B.png"], $this->names($result));
    }

    public function testFindAllBuildsTheDirectoryFromTheJoinedFolder(): void
    {
        $this->connection->insert('view_img_dir', ['dir_name' => "custom-dir-{$this->tag}"]);
        $dirId = (int) $this->connection->lastInsertId();
        $this->insertMedia("with-dir-{$this->tag}.png", $dirId);

        /** @var list<Media> $medias */
        $medias = iterator_to_array($this->repository->findAll((new MediaCriteria())->withName("with-dir-{$this->tag}")));

        self::assertCount(1, $medias);
        self::assertSame("custom-dir-{$this->tag}", $medias[0]->directory->value);
    }

    public function testFindAllReturnsEveryMediaForAnUnrestrictedViewer(): void
    {
        $accessibleName = "unrestricted-in-{$this->tag}";
        $this->insertMedia("{$accessibleName}.png", $this->defaultDirId);

        $viewerId = $this->createViewer();
        // one Access Group flagged all_image_folders='1': matches legacy's "sees everything" shortcut
        $this->linkContactToAclResourceForImageFolders($viewerId, restrictToImageFolderIds: [], allImageFolders: true);

        $names = $this->names($this->repository->findAll((new MediaCriteria())->withViewerId(new UserId($viewerId))));

        self::assertContains("{$accessibleName}.png", $names);
    }

    public function testFindAllRestrictsToMediaInAnAccessibleFolder(): void
    {
        $this->connection->insert('view_img_dir', ['dir_name' => "restricted-dir-{$this->tag}"]);
        $restrictedDirId = (int) $this->connection->lastInsertId();

        $accessibleName = "folder-in-{$this->tag}.png";
        $this->insertMedia($accessibleName, $restrictedDirId);
        $inaccessibleName = "folder-out-{$this->tag}.png";
        $this->insertMedia($inaccessibleName, $this->defaultDirId);

        $viewerId = $this->createViewer();
        $this->linkContactToAclResourceForImageFolders($viewerId, restrictToImageFolderIds: [$restrictedDirId], allImageFolders: false);

        $names = $this->names($this->repository->findAll((new MediaCriteria())->withViewerId(new UserId($viewerId))));

        self::assertContains($accessibleName, $names);
        self::assertNotContains($inaccessibleName, $names, 'Media in a folder not granted to the viewer must be excluded.');
    }

    public function testFindAllReturnsNoMediaForARestrictedViewerWithNoAccessGroup(): void
    {
        $this->insertMedia("anymedia-{$this->tag}.png", $this->defaultDirId);

        // a viewer whose own contact record carries no ACL group relation at all
        $viewerId = $this->createViewer();

        $names = $this->names($this->repository->findAll((new MediaCriteria())->withViewerId(new UserId($viewerId))));

        self::assertSame([], $names, 'A viewer with no active Access Group is restricted and sees no media.');
    }

    public function testFindAllReturnsAnEmptyPaginatorForARestrictedViewerWithNoAccessGroup(): void
    {
        $this->insertMedia("anymedia-{$this->tag}.png", $this->defaultDirId);

        $viewerId = $this->createViewer();

        $result = $this->repository->findAll(
            (new MediaCriteria())->withViewerId(new UserId($viewerId))->withPagination(1, 10)
        );

        self::assertInstanceOf(
            Paginator::class,
            $result,
            'Pagination metadata (totalItems, page, page size) must survive an empty ACL result, not degrade to a bare array.'
        );
        self::assertSame(0, $result->getTotalItems());
        self::assertSame(1, $result->getCurrentPage());
        self::assertSame(10, $result->getItemsPerPage());
        self::assertSame([], $this->names($result));
    }

    /**
     * @param \IteratorAggregate<int, Media>&\Countable $result
     *
     * @return list<string>
     */
    private function names(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (Media $media): string => $media->name->value,
            iterator_to_array($result)
        ));
    }

    private function insertMedia(string $name, int $dirId): int
    {
        $this->connection->insert('view_img', ['img_name' => $name, 'img_path' => $name]);
        $imgId = (int) $this->connection->lastInsertId();

        $this->connection->insert('view_img_dir_relation', [
            'dir_dir_parent_id' => $dirId,
            'img_img_id' => $imgId,
        ]);

        return $imgId;
    }

    private function createViewer(): int
    {
        $this->connection->insert('contact', [
            'contact_name' => "viewer-{$this->tag}-" . Uuid::v4()->toRfc4122(),
            'contact_alias' => "viewer-{$this->tag}-" . Uuid::v4()->toRfc4122(),
            'contact_admin' => '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => Uuid::v4()->toRfc4122() . '@email.com',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param list<int> $restrictToImageFolderIds image folders explicitly granted through this
     *                                            resource's relations; irrelevant once $allImageFolders is true
     */
    private function linkContactToAclResourceForImageFolders(int $contactId, array $restrictToImageFolderIds, bool $allImageFolders): void
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'group-' . Uuid::v4()->toRfc4122(),
            'acl_group_alias' => 'group-' . Uuid::v4()->toRfc4122(),
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_resources', [
            'acl_res_name' => 'resource-' . $aclGroupId,
            'acl_res_alias' => 'resource-' . $aclGroupId,
            'acl_res_activate' => '1',
            'all_image_folders' => $allImageFolders ? '1' : '0',
        ]);
        $aclResId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_res_group_relations', [
            'acl_res_id' => $aclResId,
            'acl_group_id' => $aclGroupId,
        ]);

        foreach ($restrictToImageFolderIds as $imageFolderId) {
            $this->connection->insert('acl_resources_image_folder_relations', [
                'acl_res_id' => $aclResId,
                'dir_id' => $imageFolderId,
            ]);
        }
    }
}
