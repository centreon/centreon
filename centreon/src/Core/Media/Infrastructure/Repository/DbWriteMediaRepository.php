<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

declare(strict_types = 1);

namespace Core\Media\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Media\Application\Repository\WriteMediaRepositoryInterface;
use Core\Media\Domain\Model\Media;
use Core\Media\Domain\Model\NewMedia;

class DbWriteMediaRepository extends AbstractRepositoryRDB implements WriteMediaRepositoryInterface
{
    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function delete(Media $media): void
    {
        $alreadyInTransaction = $this->db->inTransaction();
        if (! $alreadyInTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $directoryId = $this->findDirectoryByName($media->getDirectory());

            if ($directoryId === null) {
                throw new \Exception('Directory linked to the media not found');
            }

            $this->unlinkMediaToDirectory($media, $directoryId);
            $this->deleteMedia($media);

            $this->db->commit();
        } catch (\Throwable $ex) {
            if (! $alreadyInTransaction) {
                $this->db->rollBack();
            }

            throw $ex;
        }
    }

    /**
     * @inheritDoc
     */
    public function add(NewMedia $media): int
    {
        $alreadyInTransaction = $this->db->inTransaction();
        if (! $alreadyInTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $mediaId = $this->addMedia($media);
            $directoryId = $this->findDirectoryByName($media->getDirectory())
                ?? $this->addDirectory($media->getDirectory());
            $this->linkMediaToDirectory($mediaId, $directoryId);
        } catch (\Throwable $ex) {
            if (! $alreadyInTransaction) {
                $this->db->rollBack();
            }

            throw $ex;
        }

        return $mediaId;
    }

    private function deleteMedia(Media $media): void
    {
        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                        DELETE FROM `:db`.view_img WHERE img_id = :imageId
                    SQL
            )
        );

        $statement->bindValue(':imageId', $media->getId(), \PDO::PARAM_INT);
        $statement->execute();
    }

    private function unlinkMediaToDirectory(Media $media, int $directoryId): void
    {
        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                        DELETE FROM `:db`.view_img_dir_relation
                        WHERE img_img_id = :imageId AND dir_dir_parent_id = :directoryId
                    SQL
            )
        );

        $statement->bindValue(':imageId', $media->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':directoryId', $directoryId, \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * @param NewMedia $media
     *
     * @throws \PDOException
     *
     * @return int
     */
    private function addMedia(NewMedia $media): int
    {
        $statement = $this->db->prepare(
            $this->translateDbName(<<<'SQL'
                INSERT INTO `:db`.`view_img`
                (`img_name`,`img_path`,`img_comment`)
                VALUES (:name, :path, :comments)
                SQL
            )
        );
        $fileInfo = explode('.', $media->getFilename());
        $statement->bindValue(':name', $fileInfo[0]);
        $statement->bindValue(':path', $media->getFilename());
        $statement->bindValue(':comments', $media->getComment());
        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    private function findDirectoryByName(string $directory): ?int
    {
        $statement = $this->db->prepare(
            $this->translateDbName('SELECT `dir_id` FROM `:db`.`view_img_dir` WHERE `dir_name` = :name')
        );
        $statement->bindValue(':name', $directory);
        $statement->execute();

        return ($id = $statement->fetchColumn()) !== false
            ? (int) $id
            : null;
    }

    /**
     * @param string $directory
     *
     * @throws \PDOException
     *
     * @return int
     */
    private function addDirectory(string $directory): int
    {
        $statement = $this->db->prepare(
            $this->translateDbName(<<<'SQL'
                INSERT INTO `:db`.`view_img_dir`
                (`dir_name`,`dir_alias`)
                VALUES (:name, :alias)
                SQL
            )
        );
        $statement->bindValue(':name', $directory);
        $statement->bindValue(':alias', $directory);
        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param int $mediaId
     * @param int $directory
     *
     * @throws \PDOException
     */
    private function linkMediaToDirectory(int $mediaId, int $directory): void
    {
        $statement = $this->db->prepare(
            $this->translateDbName(<<<'SQL'
                INSERT INTO `:db`.`view_img_dir_relation`
                (`img_img_id`,`dir_dir_parent_id`)
                VALUES (:media_id, :directory_id)
                SQL
            )
        );
        $statement->bindValue(':media_id', $mediaId, \PDO::PARAM_INT);
        $statement->bindValue(':directory_id', $directory, \PDO::PARAM_INT);
        $statement->execute();
    }
}
