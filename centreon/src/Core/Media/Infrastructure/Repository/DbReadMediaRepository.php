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
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;

class DbReadMediaRepository extends AbstractRepositoryRDB implements ReadMediaRepositoryInterface
{
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function existsByPath(string $path): bool
    {
        $pathInfo = pathInfo($path);
        if ($path === '' || $pathInfo['filename'] === '' || empty($pathInfo['dirname'])) {
            return false;
        }

        $statement = $this->db->prepare(
            $this->translateDbName(<<<'SQL'
                SELECT 1
                FROM `:db`.`view_img` img
                INNER JOIN `:db`.`view_img_dir_relation` rel
                    ON rel.img_img_id = img.img_id
                INNER JOIN `:db`.`view_img_dir` dir
                    ON dir.dir_id = rel.dir_dir_parent_id
                WHERE img_path = :media_name
                    AND dir.dir_name = :media_path
                SQL
            )
        );
        $statement->bindValue(':media_name', $pathInfo['basename']);
        $statement->bindValue(':media_path', $pathInfo['dirname']);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }
}
