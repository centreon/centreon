<?php

namespace Centreon\Domain\Repository;

use Centreon\Domain\Repository\Interfaces\AclResourceRefreshInterface;
use Centreon\Infrastructure\CentreonLegacyDB\ServiceEntityRepository;

class AclResourcesHgRelationsRepository extends ServiceEntityRepository implements AclResourceRefreshInterface
{
    /**
     * Refresh
     */
    public function refresh(): void
    {
        $sql = 'DELETE FROM acl_resources_hg_relations '
            . 'WHERE hg_hg_id NOT IN (SELECT t2.hg_id FROM hostgroup AS t2)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
    }
}
