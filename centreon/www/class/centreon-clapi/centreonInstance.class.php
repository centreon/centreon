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

namespace CentreonClapi;

use Centreon_Object_Instance;
use Centreon_Object_Relation_Instance_Host;
use Exception;
use LogicException;
use PDOException;
use Pimple\Container;

require_once 'centreonObject.class.php';
require_once 'centreon.Config.Poller.class.php';
require_once 'Centreon/Object/Instance/Instance.php';
require_once 'Centreon/Object/Host/Host.php';
require_once 'Centreon/Object/Relation/Instance/Host.php';

/**
 * Class
 *
 * @class CentreonInstance
 * @package CentreonClapi
 */
class CentreonInstance extends CentreonObject
{
    public const ORDER_UNIQUENAME = 0;
    public const ORDER_ADDRESS = 1;
    public const ORDER_SSH_PORT = 2;
    public const ORDER_GORGONE_PROTOCOL = 3;
    public const ORDER_GORGONE_PORT = 4;
    public const GORGONE_COMMUNICATION = ['ZMQ' => '1', 'SSH' => '2'];
    public const INCORRECTIPADDRESS = 'Invalid IP address format';

    /** @var CentreonConfigPoller */
    public $centreonConfigPoller;

    /**
     * CentreonInstance constructor
     *
     * @param Container $dependencyInjector
     *
     * @throws PDOException
     * @throws LogicException
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->object = new Centreon_Object_Instance($dependencyInjector);
        $this->params = [
            'localhost' => '0',
            'ns_activate' => '1',
            'ssh_port' => '22',
            'gorgone_communication_type' => self::GORGONE_COMMUNICATION['ZMQ'],
            'gorgone_port' => '5556',
            'nagios_bin' => '/usr/sbin/centengine',
            'nagiostats_bin' => '/usr/bin/centenginestats',
            'engine_start_command' => 'service centengine start',
            'engine_stop_command' => 'service centengine stop',
            'engine_restart_command' => 'service centengine restart',
            'engine_reload_command' => 'service centengine reload',
            'broker_reload_command' => 'service cbd reload',
            'centreonbroker_cfg_path' => '/etc/centreon-broker',
            'centreonbroker_module_path' => '/usr/share/centreon/lib/centreon-broker',
            'centreonconnector_path' => '/usr/lib64/centreon-connector',
        ];
        $this->insertParams = ['name', 'ns_ip_address', 'ssh_port', 'gorgone_communication_type', 'gorgone_port'];
        $this->exportExcludedParams = array_merge(
            $this->insertParams,
            [$this->object->getPrimaryKey(), 'last_restart']
        );
        $this->action = 'INSTANCE';
        $this->nbOfCompulsoryParams = count($this->insertParams);
        $this->activateField = 'ns_activate';
        $this->centreonConfigPoller = new CentreonConfigPoller(_CENTREON_PATH_, $dependencyInjector);
    }

    /**
     * @param $parameters
     * @throws CentreonClapiException
     * @return void
     */
    public function initInsertParameters($parameters): void
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < $this->nbOfCompulsoryParams) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $addParams = [];
        $addParams[$this->object->getUniqueLabelField()] = $params[self::ORDER_UNIQUENAME];
        $addParams['ns_ip_address'] = $params[self::ORDER_ADDRESS];

        if (is_numeric($params[self::ORDER_GORGONE_PROTOCOL])) {
            $revertGorgoneCom = array_flip(self::GORGONE_COMMUNICATION);
            $params[self::ORDER_GORGONE_PROTOCOL] = $revertGorgoneCom[$params[self::ORDER_GORGONE_PROTOCOL]];
        }
        if (isset(self::GORGONE_COMMUNICATION[strtoupper($params[self::ORDER_GORGONE_PROTOCOL])])) {
            $addParams['gorgone_communication_type']
                = self::GORGONE_COMMUNICATION[strtoupper($params[self::ORDER_GORGONE_PROTOCOL])];
        } else {
            throw new CentreonClapiException('Incorrect connection protocol');
        }

        if (! is_numeric($params[self::ORDER_GORGONE_PORT]) || ! is_numeric($params[self::ORDER_SSH_PORT])) {
            throw new CentreonClapiException('Incorrect port parameters');
        }
        $addParams['ssh_port'] = $params[self::ORDER_SSH_PORT];
        $addParams['gorgone_port'] = $params[self::ORDER_GORGONE_PORT];

        // Check IPv6, IPv4 and FQDN format
        if (
            ! filter_var($addParams['ns_ip_address'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            && ! filter_var($addParams['ns_ip_address'], FILTER_VALIDATE_IP)
        ) {
            throw new CentreonClapiException(self::INCORRECTIPADDRESS);
        }

        if ($addParams['ns_ip_address'] == '127.0.0.1' || strtolower($addParams['ns_ip_address']) == 'localhost') {
            $this->params['localhost'] = '1';
        }
        $this->params = array_merge($this->params, $addParams);
        $this->checkParameters();
    }

    /**
     * @param $parameters
     * @throws CentreonClapiException
     * @return array
     */
    public function initUpdateParameters($parameters)
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < self::NB_UPDATE_PARAMS) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }

        // Check IPv6, IPv4 and FQDN format
        if (
            $params[1] == 'ns_ip_address'
            && ! filter_var($params[2], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            && ! filter_var($params[2], FILTER_VALIDATE_IP)
        ) {
            throw new CentreonClapiException(self::INCORRECTIPADDRESS);
        }

        $objectId = $this->getObjectId($params[self::ORDER_UNIQUENAME]);
        if ($params[1] === 'gorgone_communication_type') {
            $params[2] = self::GORGONE_COMMUNICATION[$params[2]];
        }
        if ($objectId != 0) {
            $updateParams = [$params[1] => $params[2]];
            $updateParams['objectId'] = $objectId;

            return $updateParams;
        }

        throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ':' . $params[self::ORDER_UNIQUENAME]);
    }

    /**
     * @param null $parameters
     * @param array $filters
     * @throws Exception
     */
    public function show($parameters = null, $filters = []): void
    {
        $filters = [];
        if (isset($parameters)) {
            $filters = [$this->object->getUniqueLabelField() => '%' . $parameters . '%'];
        }

        $pollerState = $this->centreonConfigPoller->getPollerState();

        $params = [
            'id',
            'name',
            'localhost',
            'ns_ip_address',
            'ns_activate',
            'ns_status',
            'engine_restart_command',
            'engine_reload_command',
            'broker_reload_command',
            'nagios_bin',
            'nagiostats_bin',
            'ssh_port',
            'gorgone_communication_type',
            'gorgone_port',
        ];
        $paramString = str_replace('_', ' ', implode($this->delim, $params));
        $paramString = str_replace('ns ', '', $paramString);
        $paramString = str_replace('nagios ', '', $paramString);
        $paramString = str_replace('nagiostats', 'stats', $paramString);
        $paramString = str_replace('communication type', 'protocol', $paramString);
        echo $paramString . "\n";
        $elements = $this->object->getList($params, -1, 0, null, null, $filters);
        foreach ($elements as $tab) {
            $tab['ns_status'] = $pollerState[$tab['id']] ?? '-';
            $tab['gorgone_communication_type']
                = array_search($tab['gorgone_communication_type'], self::GORGONE_COMMUNICATION);

            echo implode($this->delim, $tab) . "\n";
        }
    }

    /**
     * Get instance Id
     *
     * @param $name
     * @throws CentreonClapiException
     * @return mixed
     */
    public function getInstanceId($name)
    {
        $instanceIds = $this->object->getIdByParameter($this->object->getUniqueLabelField(), [$name]);
        if (! count($instanceIds)) {
            throw new CentreonClapiException('Unknown instance');
        }

        return $instanceIds[0];
    }

    /**
     * Get instance name
     *
     * @param int $instanceId
     * @return string
     */
    public function getInstanceName($instanceId)
    {
        $instanceName = $this->object->getParameters($instanceId, [$this->object->getUniqueLabelField()]);

        return $instanceName[$this->object->getUniqueLabelField()];
    }

    /**
     * Get hosts monitored by instance
     *
     * @param string $instanceName
     *
     * @throws Exception
     * @return void
     */
    public function getHosts($instanceName): void
    {
        $relObj = new Centreon_Object_Relation_Instance_Host($this->dependencyInjector);
        $fields = ['host_id', 'host_name', 'host_address'];
        $elems = $relObj->getMergedParameters(
            [],
            $fields,
            -1,
            0,
            'host_name',
            'ASC',
            ['name' => $instanceName],
            'AND'
        );

        echo "id;name;address\n";
        foreach ($elems as $elem) {
            if (! preg_match('/^_Module_/', $elem['host_name'])) {
                echo $elem['host_id'] . $this->delim . $elem['host_name'] . $this->delim . $elem['host_address'] . "\n";
            }
        }
    }
}
