<?php
/*
 * Copyright 2005-2014 CENTREON
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
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
 * As a special exception, the copyright holders of this program give CENTREON
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of CENTREON choice, provided that
 * CENTREON also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

namespace CentreonBam\Listeners\CentreonEngine;

use Centreon\Internal\Di;
use CentreonEngine\Events\AddService as AddServiceEvent;
use CentreonConfiguration\Repository\ServiceRepository;

class AddService
{
    /**
     * @param CentreonEngine\Events\AddService $event
     */
    public static function execute(AddServiceEvent $event)
    {
        //var_dump($event->getHostId());
        //var_dump($event->getServiceList());
        $hostId = $event->getHostId();
        $serviceList = $event->getServiceList();
        //echo "$pollerId\n";
 
        $dbconn = Di::getDefault()->get('db_centreon');

        $selectRequest = "SELECT host_name"
            . " FROM cfg_hosts"
            . " WHERE host_id=:id";
        $stmtSelect = $dbconn->prepare($selectRequest);
        $stmtSelect->bindParam(':id', $hostId, \PDO::PARAM_INT);
        $stmtSelect->execute();
        $result = $stmtSelect->fetchAll(\PDO::FETCH_ASSOC);

        if ($result[0]['host_name'] === '_Module_BAM') {
            $selectBaRequest = "SELECT ba_id, name"
            . " FROM cfg_bam";
            $stmtBaSelect = $dbconn->prepare($selectBaRequest);
            $stmtBaSelect->execute();
            $resultBa = $stmtBaSelect->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($resultBa as $ba) {
                $addBamService = true;
                foreach ($serviceList as &$service) {
                    if ($service['service_description'] === $ba['name']) {
                        $addBamService = false;
                    }
                }
                if ($addBamService) {
                    $insertRequest = "INSERT INTO cfg_services(service_description, display_name, organization_id, service_register)"
                        . " VALUES(:id_ba, :name, 1, '1')";
                    $serviceName = 'ba_' . $ba['ba_id'];
                    $stmtInsert = $dbconn->prepare($insertRequest);
                    $stmtInsert->bindParam(':id_ba', $serviceName, \PDO::PARAM_STR);
                    $stmtInsert->bindParam(':name', $ba['name'], \PDO::PARAM_STR);
                    $stmtInsert->execute();
                    $lastServiceId = $dbconn->lastInsertId('cfg_services','service_id');

                    $insertRelationRequest = "INSERT INTO cfg_hosts_services_relations(host_host_id, service_service_id)"
                        . " VALUES(:host_id, :service_id)";
                    $stmtRelationInsert = $dbconn->prepare($insertRelationRequest);
                    $stmtRelationInsert->bindParam(':host_id', $hostId, \PDO::PARAM_INT);
                    $stmtRelationInsert->bindParam(':service_id', $lastServiceId, \PDO::PARAM_INT);
                    $stmtRelationInsert->execute();

                    $count = count($serviceList);
                    $serviceList[$count]['host_name'] = '_Module_BAM';
                    $serviceList[$count]['service_description'] = 'ba_' . $ba['ba_id'];
                    $serviceList[$count]['display_name'] = $ba['name'];
                    $serviceList[$count]['host_id'] = $hostId;
                    $serviceList[$count]['service_register'] = '1';
                }
            }
        }
    }
}
