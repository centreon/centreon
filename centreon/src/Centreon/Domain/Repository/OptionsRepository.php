<?php

namespace Centreon\Domain\Repository;

use Centreon\Domain\Entity\Options;
use Centreon\Infrastructure\CentreonLegacyDB\ServiceEntityRepository;
use PDO;

class OptionsRepository extends ServiceEntityRepository
{
    /**
     * Export options
     *
     * @return Options[]
     */
    public function export(): array
    {
        $sql = 'SELECT * FROM options';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_CLASS, Options::class);

        $result = [];

        while ($row = $stmt->fetch()) {
            $result[] = $row;
        }

        return $result;
    }
}
