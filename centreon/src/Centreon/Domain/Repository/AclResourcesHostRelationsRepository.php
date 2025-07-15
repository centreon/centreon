<?php

namespace Centreon\Domain\Repository;

use Centreon\Domain\Repository\Interfaces\AclResourceRefreshInterface;
use Centreon\Infrastructure\CentreonLegacyDB\ServiceEntityRepository;

class AclResourcesHostRelationsRepository extends ServiceEntityRepository implements AclResourceRefreshInterface
{
    /**
     * Refresh
     */
    public function refresh(): void
    {
        $sql = 'DELETE FROM acl_resources_host_relations '
            . 'WHERE host_host_id NOT IN (SELECT t2.host_id FROM host AS t2)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
    }
}
