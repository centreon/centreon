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

use Centreon\Domain\Repository\Interfaces\AclResourceRefreshInterface;
use Centreon\Infrastructure\CentreonLegacyDB\ServiceEntityRepository;

class AclResourcesHcRelationsRepository extends ServiceEntityRepository implements AclResourceRefreshInterface
{
    /**
     * Refresh
     */
    public function refresh(): void
    {
        $sql = 'DELETE FROM acl_resources_hc_relations '
            . 'WHERE hc_id NOT IN (SELECT t2.hc_id FROM hostcategories AS t2)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
    }
}
