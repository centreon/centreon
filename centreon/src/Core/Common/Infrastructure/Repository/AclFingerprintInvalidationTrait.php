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

namespace Core\Common\Infrastructure\Repository;

/**
 * cron/centAcl.php skips an access group whose fingerprint (centreon_storage.centreon_acl_checksum) still matches the
 * rows it computes for it. A repository that writes centreon_acl rows outside the cron removes the fingerprint of the
 * groups it wrote rows for, so that their next recompute compares their rows instead of skipping them.
 *
 * @phpstan-require-extends AbstractRepositoryRDB
 */
trait AclFingerprintInvalidationTrait
{
    use SqlMultipleBindTrait;

    /**
     * @param list<int> $accessGroupIds
     */
    private function invalidateAclFingerprints(array $accessGroupIds): void
    {
        if ($accessGroupIds === []) {
            return;
        }

        [$bindValues, $placeholders] = $this->createMultipleBindQuery($accessGroupIds, ':group_');
        $statement = $this->db->prepare($this->translateDbName(
            "DELETE FROM `:dbstg`.`centreon_acl_checksum` WHERE `group_id` IN ({$placeholders})"
        ));
        foreach ($bindValues as $name => $accessGroupId) {
            $statement->bindValue($name, $accessGroupId, \PDO::PARAM_INT);
        }
        $statement->execute();
    }
}
