<?php
/*
 * Copyright 2016-2019 Centreon (http://www.centreon.com/)
 *
 * Centreon is a full-fledged industry-strength solution that meets
 * the needs in IT infrastructure and application monitoring for
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . '/www/api/class/webService.class.php';

define('CENTREON_OPENTICKET_PATH', _CENTREON_PATH_ . '/www/modules/centreon-open-tickets');

require_once CENTREON_OPENTICKET_PATH . '/class/rule.php';
require_once CENTREON_OPENTICKET_PATH . '/class/automatic.class.php';

class CentreonOpenticket extends CentreonWebService
{
    /**
     *
     * @global type $centreon
     */
    public function __construct()
    {
        global $centreon;

        $this->centreon = $centreon;
        $this->pearDBMonitoring = new CentreonDB('centstorage');

        parent::__construct();
    }

    public function postTestProvider()
    {
        if (!isset($this->arguments['service'])) {
            throw new RestBadRequestException('Missing service argument');
        }
        $service = $this->arguments['service'];

        if (!file_exists(
            CENTREON_OPENTICKET_PATH . '/providers/' . $service . '/' . $service . 'Provider.class.php'
        )) {
            throw new RestBadRequestException('The service provider does not exists.');
        }
        include_once CENTREON_OPENTICKET_PATH . '/providers/Abstract/AbstractProvider.class.php';
        include_once CENTREON_OPENTICKET_PATH . '/providers/' . $service . '/' . $service . 'Provider.class.php';

        $className = $service . 'Provider';
        if (!method_exists($className, 'test')) {
            throw new RestBadRequestException('The service provider has no test function.');
        }

        try {
            if (!$className::test($this->arguments)) {
                throw new RestForbiddenException('Fail.');
            }
            return true;
        } catch (\Exception $e) {
            throw new RestBadRequestException($e->getMessage());
        }
    }

    /**
     * Webservice to open a ticket for a service
     *
     * @return array
     */
    public function postOpenService()
    {
        /* {
         *   "rule_name": "mail",
         *   "contact_name": "test",
         *   "contact_alias": "test2",
         *   "contact_email": "test@localhost",
         *   "host_id": 10,
         *   "service_id": 199,
         *   "host_name": "testhost", [$HOSTNAME$]
         *   "host_alias": "testhost", [$HOSTALIAS$]
         *   "service_description": "service1", [$SERVICEDESC$]
         *   "service_output": "output example", [$SERVICEOUTPUT$]
         *   "service_state": "CRITICAL" [$SERVICESTATE$]
         *   "last_service_state_change": 1498125177, [$LASTSERVICESTATECHANGE$]
         *   "extra_properties": {
         *      "custom_message": "my message"
         *   },
         *   "select": {
         *      "test": "26",
         *      "test2": "my value"
         *   }
         * }
         */
        if (
            !isset($this->arguments['rule_name'])
            || !isset($this->arguments['host_id'])
            || !isset($this->arguments['service_id'])
            || !isset($this->arguments['service_state'])
            || !isset($this->arguments['service_output'])
        ) {
            throw new RestBadRequestException('Parameters missing');
        }

        $rule = new Centreon_OpenTickets_Rule($this->pearDB);
        $automatic = new Automatic(
            $rule,
            _CENTREON_PATH_,
            CENTREON_OPENTICKET_PATH . '/',
            $this->centreon,
            $this->pearDBMonitoring,
            $this->pearDB
        );
        try {
            $rv = $automatic->openService($this->arguments);
        } catch (Exception $e) {
            $rv = [ 'code' => -1, 'message' => $e->getMessage() ];
        }
        return $rv;
    }

    /**
     * Webservice to open a ticket for a host
     *
     * @return array
     */
    public function postOpenHost()
    {
        /* {
         *   "rule_name": "mail",
         *   "contact_name": "test",
         *   "contact_alias": "test2",
         *   "contact_email": "test@localhost",
         *   "host_id": 10,
         *   "host_name": "testhost", [$HOSTNAME$]
         *   "host_alias": "testhost", [$HOSTALIAS$]
         *   "host_output": "output example", [$HOSTOUTPUT$]
         *   "host_state": "UP" [$HOSTSTATE$]
         *   "last_host_state_change": 1498125177, [$LASTHOSTSTATECHANGE$]
         *   "extra_properties": {
         *      "custom_message": "my message"
         *   },
         *   "select": {
         *      "test": "26",
         *      "test2": "my value"
         *   }
         * }
         */
        if (
            !isset($this->arguments['rule_name'])
            || !isset($this->arguments['host_id'])
            || !isset($this->arguments['host_state'])
            || !isset($this->arguments['host_output'])
        ) {
            throw new RestBadRequestException('Parameters missing');
        }

        $rule = new Centreon_OpenTickets_Rule($this->pearDB);
        $automatic = new Automatic(
            $rule,
            _CENTREON_PATH_,
            CENTREON_OPENTICKET_PATH . '/',
            $this->centreon,
            $this->pearDBMonitoring,
            $this->pearDB
        );
        try {
            $rv = $automatic->openHost($this->arguments);
        } catch (Exception $e) {
            $rv = [ 'code' => -1, 'message' => $e->getMessage() ];
        }
        return $rv;
    }

    /**
     * Webservice to close a ticket for a host
     *
     * @return array
     */
    public function postCloseHost()
    {
        /* {
         *   "rule_name": "mail",
         *   "host_id": 10
         */
        if (
            !isset($this->arguments['rule_name'])
            || !isset($this->arguments['host_id'])
        ) {
            throw new RestBadRequestException('Parameters missing');
        }

        $rule = new Centreon_OpenTickets_Rule($this->pearDB);
        $automatic = new Automatic(
            $rule,
            _CENTREON_PATH_,
            CENTREON_OPENTICKET_PATH . '/',
            $this->centreon,
            $this->pearDBMonitoring,
            $this->pearDB
        );
        try {
            $rv = $automatic->closeHost($this->arguments);
        } catch (Exception $e) {
            $rv = [ 'code' => -1, 'message' => $e->getMessage() ];
        }
        return $rv;
    }

    /**
     * Webservice to close a ticket for a service
     *
     * @return array
     */
    public function postCloseService()
    {
        /* {
         *   "rule_name": "mail",
         *   "host_id": 10,
         *   "service_id": 30
         */
        if (
            !isset($this->arguments['rule_name'])
            || !isset($this->arguments['service_id'])
            || !isset($this->arguments['host_id'])
        ) {
            throw new RestBadRequestException('Parameters missing');
        }

        $rule = new Centreon_OpenTickets_Rule($this->pearDB);
        $automatic = new Automatic(
            $rule,
            _CENTREON_PATH_,
            CENTREON_OPENTICKET_PATH . '/',
            $this->centreon,
            $this->pearDBMonitoring,
            $this->pearDB
        );
        try {
            $rv = $automatic->closeService($this->arguments);
        } catch (Exception $e) {
            $rv = [ 'code' => -1, 'message' => $e->getMessage() ];
        }
        return $rv;
    }
}
