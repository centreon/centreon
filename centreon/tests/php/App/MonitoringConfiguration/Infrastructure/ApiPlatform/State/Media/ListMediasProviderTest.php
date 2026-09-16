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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media;

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Media\MediaResource;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

final class ListMediasProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/medias';

    private Connection $connection;

    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->tag = Uuid::v4()->toRfc4122();
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseStatusCodeSame(401);
    }

    public function testItAllowsAnyAuthenticatedUserRegardlessOfAcl(): void
    {
        // no dedicated permission for this endpoint: only "logged in" is required, ACL scoping
        // (which media a non-admin actually sees) is a data-scoping concern, not an access gate.
        $username = bin2hex(random_bytes(8));
        $this->createApiUser($this->connection, $username, admin: false);

        $this->login($username);

        $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();
    }

    public function testItListsMediaAsAdminWithIdNameAndUrl(): void
    {
        $dirId = $this->insertFolder("dir-{$this->tag}");
        $this->insertMedia("media-A-{$this->tag}.png", $dirId);

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "media-A-{$this->tag}"]]]);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(MediaResource::class);
        self::assertJsonContains([
            'member' => [
                ['name' => "media-A-{$this->tag}.png", 'url' => "/img/media/dir-{$this->tag}/media-A-{$this->tag}.png"],
            ],
        ]);

        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        self::assertEqualsCanonicalizing(['@id', '@type', 'id', 'name', 'url'], array_keys($member[0]));
    }

    public function testItFiltersMediaByNameUsingLikeOperator(): void
    {
        $dirId = $this->insertFolder("dir-{$this->tag}");
        $this->insertMedia("match-{$this->tag}.png", $dirId);
        $this->insertMedia("other-{$this->tag}.png", $dirId);

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "match-{$this->tag}"]]]);
        self::assertResponseIsSuccessful();
        self::assertSame(["match-{$this->tag}.png"], array_column((array) $response->toArray()['member'], 'name'));
    }

    public function testItPaginatesMedia(): void
    {
        $dirId = $this->insertFolder("dir-{$this->tag}");
        $this->insertMedia("pg-{$this->tag}-A.png", $dirId);
        $this->insertMedia("pg-{$this->tag}-B.png", $dirId);
        $this->insertMedia("pg-{$this->tag}-C.png", $dirId);

        $this->login();

        // scope to our own rows via the name filter so pre-seeded media cannot skew the total
        $response = $this->request('GET', self::BASE_ENDPOINT, [
            'query' => ['name' => ['lk' => "pg-{$this->tag}-"], 'page' => '1', 'itemsPerPage' => '2'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertCount(2, (array) $response->toArray()['member']);
        self::assertEquals(3, $response->toArray()['totalItems']);
    }

    public function testItReturnsNoMediaForARestrictedUserWithNoAccessGroup(): void
    {
        $dirId = $this->insertFolder("dir-{$this->tag}");
        $this->insertMedia("anymedia-{$this->tag}.png", $dirId);

        $username = bin2hex(random_bytes(8));
        $this->createApiUser($this->connection, $username, admin: false);

        $this->login($username);

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);
        self::assertResponseIsSuccessful();
        self::assertSame([], (array) $response->toArray()['member'], 'A viewer with no active Access Group is restricted and sees no media.');
    }

    public function testItAllowsEverythingForAUserWithAnAllImageFoldersAclResource(): void
    {
        $dirId = $this->insertFolder("dir-{$this->tag}");
        $accessibleName = "all-folders-{$this->tag}.png";
        $this->insertMedia($accessibleName, $dirId);

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->linkContactToAclResourceForImageFolders($contactId, restrictToImageFolderIds: [], allImageFolders: true);

        $this->login($username);

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);
        self::assertResponseIsSuccessful();
        self::assertSame(
            [$accessibleName],
            array_column((array) $response->toArray()['member'], 'name'),
            'An ACL resource flagged all_image_folders grants visibility on every media, like an admin.'
        );
    }

    public function testItRestrictsListingToMediaInAnAccessibleFolderForARestrictedUser(): void
    {
        $accessibleDirId = $this->insertFolder("accessible-dir-{$this->tag}");
        $inaccessibleDirId = $this->insertFolder("inaccessible-dir-{$this->tag}");
        $accessibleName = "folder-in-{$this->tag}.png";
        $this->insertMedia($accessibleName, $accessibleDirId);
        $inaccessibleName = "folder-out-{$this->tag}.png";
        $this->insertMedia($inaccessibleName, $inaccessibleDirId);

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->linkContactToAclResourceForImageFolders($contactId, restrictToImageFolderIds: [$accessibleDirId], allImageFolders: false);

        $this->login($username);

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);
        self::assertResponseIsSuccessful();
        self::assertSame(
            [$accessibleName],
            array_column((array) $response->toArray()['member'], 'name'),
            'Only media in the folder granted to the viewer is returned.'
        );
    }

    public function testItIgnoresAnEmptyNameFilter(): void
    {
        $dirId = $this->insertFolder("dir-{$this->tag}");
        $this->insertMedia("empty-{$this->tag}.png", $dirId);

        $this->login();

        // an empty "like" value must be ignored (not applied, not rejected, not a 500 from the VO)
        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => '']]]);
        self::assertResponseIsSuccessful();
    }

    public function testItRejectsAScalarNameFilter(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => "media-{$this->tag}"]]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItRejectsZeroItemsPerPage(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['itemsPerPage' => '0']]);
        self::assertResponseStatusCodeSame(400);
    }

    private function insertFolder(string $name): int
    {
        $this->connection->insert('view_img_dir', ['dir_name' => $name]);

        return (int) $this->connection->lastInsertId();
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

    private function createNonAdminContact(string $alias): int
    {
        $this->createApiUser($this->connection, $alias, admin: false);

        $contactId = $this->connection->fetchOne(
            'SELECT contact_id FROM contact WHERE contact_alias = :alias',
            ['alias' => $alias]
        );
        Assert::notFalse($contactId);
        Assert::scalar($contactId);

        return (int) $contactId;
    }

    /**
     * @param list<int> $restrictToImageFolderIds image folders explicitly granted through this
     *                                            resource's relations; irrelevant once $allImageFolders is true
     */
    private function linkContactToAclResourceForImageFolders(int $contactId, array $restrictToImageFolderIds, bool $allImageFolders): void
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => "group-{$this->tag}-" . $contactId,
            'acl_group_alias' => "group-{$this->tag}-" . $contactId,
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_resources', [
            'acl_res_name' => "resource-{$this->tag}-" . $aclGroupId,
            'acl_res_alias' => "resource-{$this->tag}-" . $aclGroupId,
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
