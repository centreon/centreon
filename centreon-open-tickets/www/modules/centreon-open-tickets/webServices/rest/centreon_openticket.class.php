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

class CentreonOpenticket extends CentreonWebService
{
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
}
