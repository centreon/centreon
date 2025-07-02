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

use Centreon_Object_Timezone;
use PDOException;
use Pimple\Container;

require_once 'centreonObject.class.php';
require_once _CENTREON_PATH_ . '/lib/Centreon/Object/Timezone/Timezone.php';
require_once _CENTREON_PATH_ . '/lib/Centreon/Object/Object.php';

/**
 * Class
 *
 * @class CentreonSettings
 * @package CentreonClapi
 */
class CentreonSettings extends CentreonObject
{
    public const ISSTRING = 0;
    public const ISNUM = 1;
    public const KEYNOTALLOWED = 'This parameter cannot be modified';
    public const VALUENOTALLOWED = 'This parameter value is not valid';

    /** @var array */
    protected $authorizedOptions = ['broker' => ['values' => ['ndo', 'broker']], 'centstorage' => ['values' => ['0', '1']], 'gmt' => ['format' => self::ISSTRING, 'getterFormatMethod' => 'getTimezonenameFromId', 'setterFormatMethod' => 'getTimezoneIdFromName'], 'mailer_path_bin' => ['format' => self::ISSTRING], 'snmptt_unknowntrap_log_file' => ['format' => self::ISSTRING], 'snmpttconvertmib_path_bin' => ['format' => self::ISSTRING], 'perl_library_path' => ['format' => self::ISSTRING], 'rrdtool_path_bin' => ['format' => self::ISSTRING], 'debug_path' => ['format' => self::ISSTRING], 'debug_auth' => ['values' => ['0', '1']], 'debug_nagios_import' => ['values' => ['0', '1']], 'debug_rrdtool' => ['values' => ['0', '1']], 'debug_ldap_import' => ['values' => ['0', '1']], 'enable_autologin' => ['values' => ['0', '1']], 'interval_length' => ['format' => self::ISNUM], 'enable_gmt' => ['values' => ['0', '1']]];

    /**
     * CentreonSettings constructor
     *
     * @param Container $dependencyInjector
     *
     * @throws PDOException
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
    }

    /**
     * Display unsupported method
     *
     * @param string $method
     * @return void
     */
    protected function unsupportedMethod($method)
    {
        echo sprintf("The %s method is not supported on this object\n", $method);
    }

    /**
     * @param null $params
     * @param array $filters
     *
     * @throws PDOException
     */
    public function show($params = null, $filters = []): void
    {
        $sql = 'SELECT `key`, `value` FROM `options` ORDER BY `key`';
        $stmt = $this->db->query($sql);
        $res = $stmt->fetchAll();
        echo 'parameter' . $this->delim . "value\n";
        foreach ($res as $row) {
            if (isset($this->authorizedOptions[$row['key']])) {
                if (isset($this->authorizedOptions[$row['key']]['getterFormatMethod'])) {
                    $method = $this->authorizedOptions[$row['key']]['getterFormatMethod'];
                    $row['value'] = $this->{$method}($row['value']);
                }
                echo $row['key'] . $this->delim . $row['value'] . "\n";
            }
        }
    }

    /**
     * @param null $parameters
     * @return void
     */
    public function add($parameters = null): void
    {
        $this->unsupportedMethod(__FUNCTION__);
    }

    /**
     * @param string|null $objectName
     *
     * @return void
     */
    public function del($objectName = null): void
    {
        $this->unsupportedMethod(__FUNCTION__);
    }

    /**
     * Set parameters
     *
     * @param null $parameters
     *
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function setparam($parameters = null): void
    {
        if (is_null($parameters)) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $params = explode($this->delim, $parameters);
        if (count($params) < 2) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }

        [$key, $value] = $params;
        if (! isset($this->authorizedOptions[$key])) {
            throw new CentreonClapiException(self::KEYNOTALLOWED);
        }

        if (isset($this->authorizedOptions[$key]['format'])) {
            if ($this->authorizedOptions[$key]['format'] == self::ISNUM && ! is_numeric($value)) {
                throw new CentreonClapiException(self::VALUENOTALLOWED);
            }
            if (is_array($this->authorizedOptions[$key]['format']) == self::ISSTRING && ! is_string($value)) {
                throw new CentreonClapiException(self::VALUENOTALLOWED);
            }
        }

        if (isset($this->authorizedOptions[$key]['values'])
            && ! in_array($value, $this->authorizedOptions[$key]['values'])) {
            throw new CentreonClapiException(self::VALUENOTALLOWED);
        }

        if (isset($this->authorizedOptions[$key]['setterFormatMethod'])) {
            $method = $this->authorizedOptions[$key]['setterFormatMethod'];
            $value = $this->{$method}($value);
        }

        $this->db->query('UPDATE `options` SET `value` = ? WHERE `key` = ?', [$value, $key]);
    }

    /**
     * @param $value
     * @throws CentreonClapiException
     * @return mixed
     */
    private function getTimezoneIdFromName($value)
    {
        $timezone = new Centreon_Object_Timezone($this->dependencyInjector);
        $timezoneId = $timezone->getIdByParameter('timezone_name', $value);
        if (! isset($timezoneId[0])) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND);
        }

        return $timezoneId[0];
    }

    /**
     * @param $value
     * @throws CentreonClapiException
     * @return mixed
     */
    private function getTimezonenameFromId($value)
    {
        $timezone = new Centreon_Object_Timezone($this->dependencyInjector);
        $timezoneName = $timezone->getParameters($value, ['timezone_name']);
        if (! isset($timezoneName['timezone_name'])) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND);
        }

        return $timezoneName['timezone_name'];
    }
}
