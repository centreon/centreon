<?php

/*
 * Copyright 2005-2021 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

require_once __DIR__ . "/../../../class/centreonDB.class.php";

class CentreonDSMStats
{
    /**
     * @var CentreonDB
     */
    private $db;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = new centreonDB();
    }

    /**
     * Get statistics of module
     * @throws Exception
     * @return array
     */
    public function getStats()
    {
        try {
            $data = $this->getSlotsUsage();
            return array('dsm' => $data);
        } catch (\Throwable $e) {
            throw new Exception(
                "Unable to get Centreon DSM statistics: " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get Auto Discovery services rules usage
     * @return array
     */
    public function getSlotsUsage()
    {
        $data = array();

        $query = "SELECT COUNT(pool_id) AS pools, MIN(pool_number) AS min,
            MAX(pool_number) AS max, AVG(pool_number) as avg
            FROM mod_dsm_pool";
        $result = $this->db->query($query);
        while ($row = $result->fetch()) {
            $data = array(
                'pools' => $row['pools'],
                'slot_min' => $row['min'],
                'slot_max' => $row['max'],
                'slot_avg' => $row['avg']
            );
        }

        return $data;
    }
}
