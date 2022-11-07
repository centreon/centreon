<?php
namespace Centreon\Domain\Repository;

use Centreon\Infrastructure\CentreonLegacyDB\ServiceEntityRepository;
use Centreon\Domain\Repository\Interfaces\AclResourceRefreshInterface;

class AclResourcesSgRelationsRepository extends ServiceEntityRepository implements AclResourceRefreshInterface
{
<<<<<<< HEAD
=======

>>>>>>> centreon/dev-21.10.x
    /**
     * Refresh
     */
    public function refresh(): void
    {
        $sql = "DELETE FROM acl_resources_sg_relations "
            . "WHERE sg_id NOT IN (SELECT t2.sg_id FROM servicegroup AS t2)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
    }
}
