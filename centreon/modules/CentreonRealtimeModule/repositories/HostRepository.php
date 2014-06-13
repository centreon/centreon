<?php
/*
 * Copyright 2005-2014 MERETHIS
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
 * As a special exception, the copyright holders of this program give MERETHIS
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of MERETHIS choice, provided that
 * MERETHIS also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

namespace CentreonRealtime\Repository;

use     \Centreon\Internal\Utils\Datetime;

/**
 * @author Julien Mathis <jmathis@merethis.com>
 * @package CentreonRealtime
 * @subpackage Repository
 */
class HostRepository extends \CentreonRealtime\Repository\Repository
{
    /**
     *
     * @var string
     */
    public static $tableName = 'hosts';
    
    /**
     *
     * @var string
     */
    public static $objectName = 'Host';

    /**
     *
     * @var string
     */
    public static $objectId = 'host_id';

    /**
     *
     * @var string
     */
    public static $hook = 'displayHostRtColumn'; 

    /**
     *
     * @var array Default column for datatable
     */
    public static $datatableColumn = array(
        '<input id="allService" class="allService" type="checkbox">' => 'host_id',
        'Host Name' => 'name',
        'Address' => 'address',
        'Status' => 'state',
        'Last Update' => 'last_check',
        'Duration' => '[SPECFIELD](unix_timestamp(NOW())-last_hard_state_change) AS duration',
        'Retry' => "CONCAT(check_attempt, ' / ', max_check_attempts) AS retry",
        'Output' => 'output'
    );
    
    /**
     *
     * @var type 
     */
    public static $additionalColumn = array();
    
    /**
     *
     * @var array 
     */
    public static $researchIndex = array(
        'host_id',
        'name',
        'address',
        'state',
        'last_check',
        '[SPECFIELD](unix_timestamp(NOW())-last_hard_state_change) AS duration',
        "CONCAT(check_attempt, ' / ', max_check_attempts) AS retry",
        'output'
    );
    
    /**
     *
     * @var string 
     */
    public static $specificConditions = " enabled = 1 ";
    
    /**
     *
     * @var string 
     */
    public static $linkedTables = "";
    
    /**
     *
     * @var array 
     */
    public static $datatableHeader = array(
        'none',
        'text',
        'text',
        array('select' => array(
                'OK' => 0,
                'Warning' => 1,
                'Critical' => 2,
                'Unknown' => 3,
                'Pending' => 4
            )
        ),
        'text',
        'text',
        'text',
        'text'
    );
    
    /**
     *
     * @var array 
     */
    public static $columnCast = array(
        'host_id' => array(
            'type' => 'checkbox',
            'parameters' => array(
                'displayName' => '::name::'
            )
        ),
        'state' => array(
            'type' => 'select',
            'parameters' => array(
                '0' => '<span class="label label-success">OK</span>',
                '1' => '<span class="label label-warning">Warning</span>',
                '2' => '<span class="label label-danger">Critical</span>',
                '3' => '<span class="label label-default">Unknown</span>',
                '4' => '<span class="label label-info">Pending</span>',
            )
        ),
        'address' => array(
            'type' => 'url',
            'parameters' => array(
                'route' => '/realtime/host/[i:id]',
                'routeParams' => array(
                    'id' => '::host_id::'
                ),
                'linkName' => '::address::'
            )
        ),
        'name' => array(
            'type' => 'url',
            'parameters' => array(
                'route' => '/realtime/host/[i:id]',
                'routeParams' => array(
                    'id' => '::host_id::'
                ),
                'linkName' => '::name::'
            )
        )
    );
    
    /**
     *
     * @var array 
     */
    public static $datatableFooter = array(
        'none',
        'text',
        'text',
        array('select' => array(
                'OK' => 0,
                'Warning' => 1,
                'Critical' => 2,
                'Unknown' => 3,
                'Pending' => 4
            )
        ),
    );
    
    /**
     * Format data for datatable
     * 
     * @param array $resultSet
     */
    public static function formatDatas(&$resultSet)
    {
        
        $previousHost = '';
        foreach ($resultSet as &$myHostSet) {
            // Set host_name
            if ($myHostSet['name'] === $previousHost) {
                $myHostSet['name'] = '';
            } else {
                $previousHost = $myHostSet['name'];
                $myHostSet['name'] = \CentreonConfiguration\Repository\HostRepository::getIconImage(
                    $myHostSet['name']
                ).'&nbsp;&nbsp;'.$myHostSet['name'];
            }
            $myHostSet['duration'] = Datetime::humanReadable($myHostSet['duration'],
                                                             Datetime::PRECISION_FORMAT,
                                                             2
                                                             ); 
            
        }
        
    }

    /**
     * Get service status
     *
     * @param int $host_id
     * @param int $service_id
     */
    public static function getStatus($host_id)
    {
        // Initializing connection
        $di = \Centreon\Internal\Di::getDefault();
        $dbconn = $di->get('db_storage');
        
        $stmt = $dbconn->query('SELECT state as state FROM hosts WHERE host_id = '.$host_id.' AND enabled = 1 LIMIT 1');
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            return static::getBadgeStatus($row['state']);
        }
    }

    /**
     * Format small badge status
     *
     * @param int $status
     */
    public static function getBadgeStatus($status) 
    {
        if ($status == 0) {
            $status = "label-success";
        } else if ($status == 1) {
            $status = "label-warning";
        } else if ($status == 2) {
            $status = "label-danger";
        } else if ($status == 3) {
            $status = "label-default";
        } else if ($status == 4) {
            $status = "label-info";
        }
        return "<span class='label $status pull-right'>&nbsp;<!--<i class='fa fa-check-square-o'>--></i></span>";
    }

}
