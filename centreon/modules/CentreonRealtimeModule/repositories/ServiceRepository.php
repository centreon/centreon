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

/**
 * @author Sylvestre Ho <sho@merethis.com>
 * @package CentreonRealtime
 * @subpackage Repository
 */
class ServiceRepository extends \CentreonRealtime\Repository\Repository
{
    /**
     *
     * @var string
     */
    public static $tableName = 'services';
    
    /**
     *
     * @var string
     */
    public static $objectName = 'Service';

    /**
     *
     * @var string
     */
    public static $objectId = 'service_id';

    /**
     *
     * @var string
     */
    public static $hook = 'displayServiceRtColumn';

    /**
     *
     * @var array Default column for datatable
     */
    public static $datatableColumn = array(
        '<input id="allService" class="allService" type="checkbox">' => 'service_id',
        'Host Name' => 'name',
        'Name' => 'description',
        'Status' => 'services.state',
        'Output' => 'services.output'
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
        'service_id',
        'name',
        'description',
        'services.state',
        'services.output'
    );
    
    /**
     *
     * @var string 
     */
    public static $specificConditions = "h.host_id = services.host_id AND services.enabled = 1 ";
    
    /**
     *
     * @var string 
     */
    public static $linkedTables = "hosts h";
    
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
        'text'
    );
    
    /**
     *
     * @var array 
     */
    public static $columnCast = array(
        'state' => array(
            'type' => 'select',
            'parameters' =>array(
                '0' => '<span class="label label-success">OK</span>',
                '1' => '<span class="label label-warning">Warning</span>',
                '2' => '<span class="label label-danger">Critical</span>',
                '3' => '<span class="label label-default">Unknown</span>',
                '4' => '<span class="label label-info">Pending</span>',
            )
        ),
        'service_id' => array(
            'type' => 'checkbox',
            'parameters' => array(
                'displayName' => '::service_description::'
            )
        ),
        'description' => array(
            'type' => 'url',
            'parameters' => array(
                'route' => '/realtime/service/[i:id]',
                'routeParams' => array(
                    'id' => '::service_id::'
                ),
                'linkName' => '::description::'
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
        'text'
    );
    
    /**
     * Format data for datatable
     * 
     * @param array $resultSet
     */
    public static function formatDatas(&$resultSet)
    {
        $previousHost = '';
        foreach ($resultSet as &$myServiceSet) {
            // Set host_name
            if ($myServiceSet['name'] === $previousHost) {
                $myServiceSet['name'] = '';
            } else {
                $previousHost = $myServiceSet['name'];
                $myServiceSet['name'] = \CentreonConfiguration\Repository\HostRepository::getIconImage(
                    $myServiceSet['name']
                ).'&nbsp;'.$myServiceSet['name'];
            }
        }
    }
}
