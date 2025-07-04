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

require_once _CENTREON_PATH_ . '/www/class/centreonDBInstance.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonWidget.class.php';
require_once __DIR__ . '/webService.class.php';
require_once __DIR__ . '/../interface/di.interface.php';
require_once __DIR__ . '/../trait/diAndUtilis.trait.php';

/**
 * Class
 *
 * @class CentreonAdministrationWidget
 */
class CentreonAdministrationWidget extends CentreonWebService implements CentreonWebServiceDiInterface
{
    use CentreonWebServiceDiAndUtilisTrait;

    /**
     * @throws RestBadRequestException
     * @return array
     */
    public function getListInstalled()
    {
        global $centreon;

        // Check for select2 'q' argument
        $q = false === isset($this->arguments['q']) ? '' : $this->arguments['q'];

        if (isset($this->arguments['page_limit'], $this->arguments['page'])) {
            if (
                ! is_numeric($this->arguments['page'])
                || ! is_numeric($this->arguments['page_limit'])
                || $this->arguments['page_limit'] < 1
            ) {
                throw new RestBadRequestException('Error, limit must be an integer greater than zero');
            }
            $limit = ($this->arguments['page'] - 1) * $this->arguments['page_limit'];
            $range = [(int) $limit, (int) $this->arguments['page_limit']];
        } else {
            $range = [];
        }

        $widgetObj = new CentreonWidget($centreon, $this->pearDB);

        return $widgetObj->getWidgetModels($q, $range);
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
            || ($user && $user->hasAccessRestApiConfiguration())
        );
    }
}
