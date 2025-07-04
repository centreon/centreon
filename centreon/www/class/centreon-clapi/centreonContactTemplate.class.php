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

use PDOException;
use Pimple\Container;

require_once 'centreonContact.class.php';

/**
 * Class
 *
 * @class CentreonContactTemplate
 * @package CentreonClapi
 */
class CentreonContactTemplate extends CentreonContact
{
    public const ORDER_NAME = 0;
    public const ORDER_UNIQUENAME = 1;
    public const ORDER_MAIL = 2;
    public const ORDER_ADMIN = 3;
    public const ORDER_ACCESS = 4;
    public const ORDER_LANG = 5;
    public const ORDER_AUTHTYPE = 6;
    public const ORDER_DEFAULT_PAGE = 7;

    /** @var string[] */
    public static $aDepends = ['CMD', 'TP'];

    /**
     * CentreonContactTemplate constructor
     *
     * @param Container $dependencyInjector
     *
     * @throws PDOException
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->params['contact_register'] = 0;
        $this->register = 0;
        $this->action = 'CONTACTTPL';
        $this->insertParams = [
            'contact_name',
            'contact_alias',
            'contact_email',
            'contact_admin',
            'contact_oreon',
            'contact_lang',
            'contact_auth_type',
        ];
        $this->nbOfCompulsoryParams = count($this->insertParams);
    }

    /**
     * @param $parameters
     *
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function initInsertParameters($parameters): void
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < $this->nbOfCompulsoryParams) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $this->addParams = [];
        $this->initUniqueField($params);
        $this->initUserInformation($params);
        $this->initUserAccess($params);
        $this->initLang($params);
        $this->initAuthenticationType($params);
        $this->initDefaultPage($params);

        $this->params = array_merge($this->params, $this->addParams);
        $this->checkParameters();
    }
}
