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

use Centreon_Object_Manufacturer;
use Exception;
use PDOException;
use Pimple\Container;

require_once 'centreonObject.class.php';
require_once 'centreonUtils.class.php';
require_once 'Centreon/Object/Manufacturer/Manufacturer.php';

/**
 * Class
 *
 * @class CentreonManufacturer
 * @package CentreonClapi
 */
class CentreonManufacturer extends CentreonObject
{
    public const ORDER_UNIQUENAME = 0;
    public const ORDER_ALIAS = 1;
    public const FILE_NOT_FOUND = 'Could not find file';

    /**
     * CentreonManufacturer constructor
     *
     * @param Container $dependencyInjector
     *
     * @throws PDOException
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->object = new Centreon_Object_Manufacturer($dependencyInjector);
        $this->params = [];
        $this->insertParams = ['name', 'alias'];
        $this->action = 'VENDOR';
        $this->nbOfCompulsoryParams = count($this->insertParams);
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
        $addParams['alias'] = $params[self::ORDER_ALIAS];
        $this->params = array_merge($this->params, $addParams);
        $this->checkParameters();
    }

    /**
     * @param null $parameters
     * @param array $filters
     *
     * @throws Exception
     */
    public function show($parameters = null, $filters = []): void
    {
        $filters = [];
        if (isset($parameters)) {
            $filters = [$this->object->getUniqueLabelField() => '%' . $parameters . '%'];
        }
        $params = ['id', 'name', 'alias'];
        parent::show($params, $filters);
    }

    /**
     * @param null $parameters
     * @throws CentreonClapiException
     * @return array
     */
    public function initUpdateParameters($parameters = null)
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < self::NB_UPDATE_PARAMS) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $updateParams = [$params[1] => $params[2]];
        $updateParams['objectId'] = $this->getId($params[0]);

        return $updateParams;
    }

    /**
     * Will generate traps from a mib file
     *
     * @param null $parameters
     * @throws CentreonClapiException
     */
    public function generatetraps($parameters = null): void
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < 2) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $vendorId = $this->getId($params[0]);
        $mibFile = $params[1];
        $tmpMibFile = '/tmp/' . basename($mibFile);
        if (! is_file($mibFile)) {
            throw new CentreonClapiException(self::FILE_NOT_FOUND . ': ' . $mibFile);
        }
        copy($mibFile, $tmpMibFile);
        $centreonDir = realpath(__DIR__ . '/../../../');
        passthru("export MIBS=ALL && {$centreonDir}/bin/snmpttconvertmib --in={$tmpMibFile} --out={$tmpMibFile}.conf");
        passthru("{$centreonDir}/bin/centFillTrapDB -f {$tmpMibFile}.conf -m {$vendorId}");
        unlink($tmpMibFile);
        if (file_exists($tmpMibFile . '.conf')) {
            unlink($tmpMibFile . '.conf');
        }
    }

    /**
     * Get manufacturer name from id
     *
     * @param int $id
     * @return string
     */
    public function getName($id)
    {
        $name = $this->object->getParameters($id, [$this->object->getUniqueLabelField()]);

        return $name[$this->object->getUniqueLabelField()];
    }

    /**
     * Get id from name
     *
     * @param $name
     * @throws CentreonClapiException
     * @return mixed
     */
    public function getId($name)
    {
        $ids = $this->object->getIdByParameter($this->object->getUniqueLabelField(), [$name]);
        if (! count($ids)) {
            throw new CentreonClapiException('Unknown instance');
        }

        return $ids[0];
    }
}
