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

namespace Tests\Core\Integration\Media\Infrastructure\Api\FindMedias;

use Tests\Core\Integration\CoreApiTestCase;

/**
 * Integration test for the legacy media listing, which the MAP image picker uses.
 *
 * Route: GET /api/latest/configuration/medias
 * Controller: Core\Media\Infrastructure\API\FindMedias\FindMediasController
 *
 * The media of the system folders (centreon-map, dashboards, ppm) are returned to every user
 * subject to ACL; the media of the other folders only when an ACL resource of the user grants
 * their folder.
 *
 * The "all image folders" flag of an ACL resource is not tested here: the use case reads it
 * through the DBAL connection, which does not see the rows this test inserts in its transaction
 * on the legacy connection. ListMediasProviderTest covers it for the new API.
 *
 * Run inside the Docker container:
 *   docker exec centreon-app bash -c "cd /usr/share/centreon && vendor/bin/phpunit -c phpunit.core-integration.xml --filter FindMediasIntegrationTest"
 */
final class FindMediasIntegrationTest extends CoreApiTestCase
{
    private const ENDPOINT = '/api/latest/configuration/medias';
    private const USER_WITH_GROUP = 'test-media-user';
    private const USER_WITHOUT_GROUP = 'test-media-nogroup';

    /** Unique to the test, carried by every media and folder name it creates. */
    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tag = bin2hex(random_bytes(4));
    }

    public function testAdminSeesEveryMedia(): void
    {
        $systemMedia = $this->createMedia('system', $this->systemFolderId());
        $otherMedia = $this->createMedia('other', $this->createFolder('other'));

        $this->login('test-media-admin');

        self::assertSame([$otherMedia, $systemMedia], $this->listTaggedMediaNames());
    }

    public function testUserWithoutAccessGroupSeesTheSystemMediaOnly(): void
    {
        $systemMedia = $this->createMedia('system', $this->systemFolderId());
        $this->createMedia('other', $this->createFolder('other'));
        $this->allowApiAccess(self::USER_WITHOUT_GROUP);

        $this->login(self::USER_WITHOUT_GROUP);

        self::assertSame([$systemMedia], $this->listTaggedMediaNames());
    }

    public function testUserWithAccessGroupButNoImageFolderSeesTheSystemMediaOnly(): void
    {
        $systemMedia = $this->createMedia('system', $this->systemFolderId());
        $this->createMedia('other', $this->createFolder('other'));
        // an active ACL resource that grants no image folder
        $this->grantImageFolders($this->aclGroupIdOf(self::USER_WITH_GROUP), []);

        $this->login(self::USER_WITH_GROUP);

        self::assertSame([$systemMedia], $this->listTaggedMediaNames());
    }

    public function testUserWithGrantedFolderSeesTheSystemMediaAndTheMediaOfThatFolder(): void
    {
        $systemMedia = $this->createMedia('system', $this->systemFolderId());
        $grantedFolderId = $this->createFolder('granted');
        $grantedMedia = $this->createMedia('granted', $grantedFolderId);
        $this->createMedia('other', $this->createFolder('other'));
        $this->grantImageFolders($this->aclGroupIdOf(self::USER_WITH_GROUP), [$grantedFolderId]);

        $this->login(self::USER_WITH_GROUP);

        self::assertSame([$grantedMedia, $systemMedia], $this->listTaggedMediaNames());
    }

    public function testAFolderGrantedByTwoAccessGroupsReturnsItsMediaOnce(): void
    {
        $grantedFolderId = $this->createFolder('granted');
        $grantedMedia = $this->createMedia('granted', $grantedFolderId);
        $this->grantImageFolders($this->aclGroupIdOf(self::USER_WITH_GROUP), [$grantedFolderId]);
        $this->grantImageFolders($this->createAclGroupFor(self::USER_WITH_GROUP), [$grantedFolderId]);

        $this->login(self::USER_WITH_GROUP);

        self::assertSame([$grantedMedia], $this->listTaggedMediaNames());
    }

    public function testAFolderNamedLikeASystemFolderWithAnotherCaseIsNotASystemFolder(): void
    {
        $this->createMedia('upper', $this->createFolder('PPM', tagged: false));
        $this->allowApiAccess(self::USER_WITHOUT_GROUP);

        $this->login(self::USER_WITHOUT_GROUP);

        self::assertSame([], $this->listTaggedMediaNames());
    }

    public function testAnOrSearchFiltersWithinTheReadableMediaOnly(): void
    {
        $grantedFolderId = $this->createFolder('granted');
        $grantedMedia = $this->createMedia('granted', $grantedFolderId);
        // matches the search, but its folder is not granted
        $this->createMedia('other', $this->createFolder('other'));
        // readable, but neither its name nor its folder matches the search
        $this->createMedia('system-' . bin2hex(random_bytes(4)), $this->systemFolderId(), tagged: false);
        $this->grantImageFolders($this->aclGroupIdOf(self::USER_WITH_GROUP), [$grantedFolderId]);

        $this->login(self::USER_WITH_GROUP);

        // The ACL condition is "system folder OR granted folder", and the user search is appended
        // after it: each must keep its own parentheses, or the OR of one escapes the other.
        $names = $this->listMediaNames([
            '$or' => [
                ['name' => ['$lk' => "%{$this->tag}%"]],
                ['directory' => ['$lk' => "%{$this->tag}%"]],
            ],
        ]);

        self::assertSame([$grantedMedia], $names);
    }

    protected static function apiUsers(): array
    {
        return [
            ['identifier' => 'test-media-admin', 'admin' => true],
            [
                'identifier' => self::USER_WITH_GROUP,
                'admin' => false,
                // any menu access: it gives the user an access group and the API access
                'topologyPages' => [['id' => 6, 'access_right' => 1]],
            ],
            ['identifier' => self::USER_WITHOUT_GROUP, 'admin' => false],
        ];
    }

    /**
     * @return list<string> the names of the media created by this test that the user can list, sorted
     */
    private function listTaggedMediaNames(): array
    {
        return $this->listMediaNames(['name' => ['$lk' => "%{$this->tag}%"]]);
    }

    /**
     * @param array<string, mixed> $search
     *
     * @return list<string>
     */
    private function listMediaNames(array $search): array
    {
        $query = http_build_query(['limit' => 100, 'search' => json_encode($search, JSON_THROW_ON_ERROR)]);
        $response = $this->request('GET', self::ENDPOINT . '?' . $query);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        /** @var array{result: list<array{name: string}>} $body */
        $body = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $names = array_column($body['result'], 'name');
        sort($names);

        return $names;
    }

    private function systemFolderId(): int
    {
        $statement = self::$db->prepare("SELECT dir_id FROM view_img_dir WHERE CAST(dir_name AS BINARY) = 'centreon-map' LIMIT 1");
        $statement->execute();
        $folderId = $statement->fetchColumn();

        return $folderId !== false ? (int) $folderId : $this->createFolder('centreon-map', tagged: false);
    }

    private function createFolder(string $name, bool $tagged = true): int
    {
        $statement = self::$db->prepare('INSERT INTO view_img_dir (dir_name, dir_alias) VALUES (:name, :name)');
        $statement->execute([':name' => $tagged ? "{$name}-{$this->tag}" : $name]);

        return (int) self::$db->lastInsertId();
    }

    /**
     * @param bool $tagged false for a media that the tag search must not find
     *
     * @return string the name of the created media
     */
    private function createMedia(string $name, int $folderId, bool $tagged = true): string
    {
        $fileName = $tagged ? "{$name}-{$this->tag}.png" : "{$name}.png";
        $statement = self::$db->prepare('INSERT INTO view_img (img_name, img_path) VALUES (:name, :name)');
        $statement->execute([':name' => $fileName]);
        $mediaId = (int) self::$db->lastInsertId();

        $statement = self::$db->prepare(
            'INSERT INTO view_img_dir_relation (dir_dir_parent_id, img_img_id) VALUES (:folderId, :mediaId)'
        );
        $statement->execute([':folderId' => $folderId, ':mediaId' => $mediaId]);

        return $fileName;
    }

    /**
     * Links a new active ACL resource to the access group, granting exactly these image folders.
     *
     * @param list<int> $folderIds
     */
    private function grantImageFolders(int $aclGroupId, array $folderIds): void
    {
        $statement = self::$db->prepare(
            "INSERT INTO acl_resources (acl_res_name, acl_res_activate, all_image_folders) VALUES (:name, '1', 0)"
        );
        $statement->execute([':name' => "test-media-resource-{$this->tag}-" . random_int(1, PHP_INT_MAX)]);
        $resourceId = (int) self::$db->lastInsertId();

        $statement = self::$db->prepare(
            'INSERT INTO acl_res_group_relations (acl_res_id, acl_group_id) VALUES (:resourceId, :groupId)'
        );
        $statement->execute([':resourceId' => $resourceId, ':groupId' => $aclGroupId]);

        foreach ($folderIds as $folderId) {
            $statement = self::$db->prepare(
                'INSERT INTO acl_resources_image_folder_relations (acl_res_id, dir_id) VALUES (:resourceId, :folderId)'
            );
            $statement->execute([':resourceId' => $resourceId, ':folderId' => $folderId]);
        }
    }

    private function aclGroupIdOf(string $userAlias): int
    {
        $statement = self::$db->prepare(
            'SELECT agcr.acl_group_id FROM acl_group_contacts_relations agcr'
            . ' INNER JOIN contact c ON c.contact_id = agcr.contact_contact_id'
            . ' WHERE c.contact_alias = :alias LIMIT 1'
        );
        $statement->execute([':alias' => $userAlias]);
        $aclGroupId = $statement->fetchColumn();

        if ($aclGroupId === false) {
            self::fail("User '{$userAlias}' has no access group.");
        }

        return (int) $aclGroupId;
    }

    private function createAclGroupFor(string $userAlias): int
    {
        $statement = self::$db->prepare(
            "INSERT INTO acl_groups (acl_group_name, acl_group_alias, acl_group_activate) VALUES (:name, :name, '1')"
        );
        $statement->execute([':name' => "test-media-group-{$this->tag}"]);
        $aclGroupId = (int) self::$db->lastInsertId();

        $statement = self::$db->prepare(
            'INSERT INTO acl_group_contacts_relations (acl_group_id, contact_contact_id)'
            . ' SELECT :groupId, contact_id FROM contact WHERE contact_alias = :alias'
        );
        $statement->execute([':groupId' => $aclGroupId, ':alias' => $userAlias]);

        return $aclGroupId;
    }

    /**
     * The base test case grants the API access only along with an access group: give it to a user
     * without one (rolled back in tearDown).
     */
    private function allowApiAccess(string $userAlias): void
    {
        $statement = self::$db->prepare('UPDATE contact SET reach_api = 1 WHERE contact_alias = :alias');
        $statement->execute([':alias' => $userAlias]);
    }
}
