<?php
/*
 * Copyright 2005-2015 CENTREON
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

namespace CentreonClapi;

use PDOException;
use Pimple\Container;

require_once "centreonContact.class.php";

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
        $this->action = "CONTACTTPL";
        $this->insertParams = [
            'contact_name',
            'contact_alias',
            'contact_email',
            'contact_admin',
            'contact_oreon',
            'contact_lang',
            'contact_auth_type'
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
