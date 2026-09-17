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

require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreon-knowledge/wikiApi.class.php';
require_once __DIR__ . '/webService.class.php';

/**
 * Class
 *
 * @class CentreonWiki
 */
class CentreonWiki extends CentreonWebService
{
    /**
     * CentreonWiki constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return array
     */
    public function postDeletePage()
    {
        $wikiApi = new WikiApi();
        $result = $wikiApi->deletePage($this->arguments['title']);

        return ['result' => $result];
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
