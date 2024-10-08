<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace CentreonClapi\Repository;

use PDO;
use PDOException;

/**
 * Class
 *
 * @class SessionRepository
 * @package CentreonClapi\Repository
 */
class SessionRepository
{
    /**
     * @param PDO $db
     */
    public function __construct(private PDO $db) {}

    /**
     * Flag that acl has been updated
     *
     * @param int[] $sessionIds
     *
     * @throws PDOException
     */
    public function flagUpdateAclBySessionIds(array $sessionIds): void
    {
        $statement = $this->db->prepare("UPDATE session SET update_acl = '1' WHERE session_id = :sessionId");
        foreach ($sessionIds as $sessionId) {
            $statement->bindValue(':sessionId', $sessionId, PDO::PARAM_STR);
            $statement->execute();
        }
    }
}
