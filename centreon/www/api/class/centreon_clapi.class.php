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

use CentreonClapi\CentreonAPI;
use CentreonClapi\CentreonClapiException;
use CentreonClapi\CentreonObject;
use Pimple\Container;

require_once __DIR__ . '/../../include/common/csvFunctions.php';
require_once __DIR__ . '/webService.class.php';

define('_CLAPI_LIB_', _CENTREON_PATH_ . '/lib');
define('_CLAPI_CLASS_', _CENTREON_PATH_ . '/www/class/centreon-clapi');

set_include_path(implode(PATH_SEPARATOR, [_CENTREON_PATH_ . '/lib', _CENTREON_PATH_ . '/www/class/centreon-clapi', get_include_path()]));

require_once _CENTREON_PATH_ . '/www/class/centreon-clapi/centreonAPI.class.php';

/**
 * Class wrapper for CLAPI to expose in REST
 */
class CentreonClapi extends CentreonWebService implements CentreonWebServiceDiInterface
{
    /** @var Container */
    private $dependencyInjector;

    /**
     * @param Container $dependencyInjector
     *
     * @return void
     */
    public function finalConstruct(Container $dependencyInjector): void
    {
        $this->dependencyInjector = $dependencyInjector;
    }

    /**
     * Post
     *
     * @global Centreon $centreon
     * @global array $conf_centreon
     * @throws RestBadRequestException
     * @throws RestNotFoundException
     * @throws RestConflictException
     * @throws RestInternalServerErrorException
     * @return array
     */
    public function postAction()
    {
        global $centreon;
        global $conf_centreon;

        $dbConfig['host'] = $conf_centreon['hostCentreon'];
        $dbConfig['username'] = $conf_centreon['user'];
        $dbConfig['password'] = $conf_centreon['password'];
        $dbConfig['dbname'] = $conf_centreon['db'];
        if (isset($conf_centreon['port'])) {
            $dbConfig['port'] = $conf_centreon['port'];
        } elseif ($p = strstr($dbConfig['host'], ':')) {
            $p = substr($p, 1);
            if (is_numeric($p)) {
                $dbConfig['port'] = $p;
            }
        }

        $db = $this->dependencyInjector['configuration_db'];
        $db_storage = $this->dependencyInjector['realtime_db'];
        $username = $centreon->user->alias;

        CentreonClapi\CentreonUtils::setUserName($username);

        if (false === isset($this->arguments['action'])) {
            throw new RestBadRequestException('Bad parameters');
        }

        // Prepare options table
        $action = $this->arguments['action'];

        $options = [];
        if (isset($this->arguments['object'])) {
            $options['o'] = $this->arguments['object'];
        }

        if (isset($this->arguments['values'])) {
            if (is_array($this->arguments['values'])) {
                $options['v'] = join(';', $this->arguments['values']);
            } else {
                $options['v'] = $this->arguments['values'];
            }
        }

        // Load and execute clapi option
        try {
            $clapi = new CentreonAPI(
                $username,
                '',
                $action,
                _CENTREON_PATH_,
                $options,
                $this->dependencyInjector
            );
            ob_start();
            $retCode = $clapi->launchAction(false);
            $contents = ob_get_contents();
            ob_end_clean();
        } catch (CentreonClapiException $e) {
            $message = $e->getMessage();
            if (str_starts_with($message, CentreonObject::UNKNOWN_METHOD)) {
                throw new RestNotFoundException($message);
            }
            if (str_starts_with($message, CentreonObject::MISSINGPARAMETER)) {
                throw new RestBadRequestException($message);
            }
            if (str_starts_with($message, CentreonObject::MISSINGNAMEPARAMETER)) {
                throw new RestBadRequestException($message);
            }
            if (str_starts_with($message, CentreonObject::OBJECTALREADYEXISTS)) {
                throw new RestConflictException($message);
            }
            if (str_starts_with($message, CentreonObject::OBJECT_NOT_FOUND)) {
                throw new RestNotFoundException($message);
            }
            if (str_starts_with($message, CentreonObject::NAMEALREADYINUSE)) {
                throw new RestConflictException($message);
            }
            if (str_starts_with($message, CentreonObject::UNKNOWNPARAMETER)) {
                throw new RestBadRequestException($message);
            }
            if (str_starts_with($message, CentreonObject::OBJECTALREADYLINKED)) {
                throw new RestConflictException($message);
            }
            if (str_starts_with($message, CentreonObject::OBJECTNOTLINKED)) {
                throw new RestBadRequestException($message);
            }

            throw new RestInternalServerErrorException($message);
        }
        if ($retCode != 0) {
            $contents = trim($contents);
            if (preg_match('/^Object ([\w\d ]+) not found in Centreon API.$/', $contents)) {
                throw new RestBadRequestException($contents);
            }

            throw new RestInternalServerErrorException($contents);
        }

        if (preg_match("/^.*;.*(?:\n|$)/", $contents)) {
            $result = csvToArray($contents, true);
            if ($result === false) {
                throw new RestInternalServerErrorException($contents);
            }

            $lastRecord = end($result);
            if ($lastRecord && str_starts_with($lastRecord[0] ?? '', 'Return code end :')) {
                array_pop($result);
            }
        } else {
            $result = [];
            foreach (explode("\n", $contents) as &$line) {
                if (trim($line) !== '' && ! str_starts_with($line, 'Return code end :')) {
                    $result[] = $line;
                }
            }
        }

        $return = [];
        $return['result'] = $result;

        return $return;
    }

    /**
     * Authorize to access to the action
     *
     * @param string $action The action name
     * @param CentreonUser $user The current user
     * @param bool $isInternal If the api is call in internal
     * @return bool If the user has access to the action
     */
    public function authorize($action, $user, $isInternal = false)
    {
        return (bool) (
            parent::authorize($action, $user, $isInternal)
            || ($user && $user->is_admin())
        );
    }

    /**
     * Removes carriage returns from $item if string
     *
     * @param $item
     *
     * @return void
     */
    private function clearCarriageReturns(&$item): void
    {
        $item = (is_string($item)) ? str_replace(["\n", "\t", "\r", '<br/>'], '', $item) : $item;
    }
}
