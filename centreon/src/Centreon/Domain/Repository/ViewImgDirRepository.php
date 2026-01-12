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

namespace Centreon\Domain\Repository;

use Centreon\Infrastructure\CentreonLegacyDB\ServiceEntityRepository;

class ViewImgDirRepository extends ServiceEntityRepository
{
    /**
     * Export
     *
     * @param array $imgList
     * @return array
     */
    public function export(?array $imgList = null): array
    {
        if (! $imgList) {
            return [];
        }

        $list = implode(',', $imgList);

        $sql = <<<SQL
            SELECT
                t.*
            FROM view_img_dir AS t
            INNER JOIN view_img_dir_relation AS vidr ON vidr.dir_dir_parent_id = t.dir_id
                AND vidr.img_img_id IN ({$list})
            GROUP BY t.dir_id
            SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $result = [];

        while ($row = $stmt->fetch()) {
            $result[] = $row;
        }

        return $result;
    }
}
