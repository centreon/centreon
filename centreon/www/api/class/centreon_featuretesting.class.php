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

require_once __DIR__ . '/webService.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonFeature.class.php';

/**
 * Class
 *
 * @class CentreonFeaturetesting
 */
class CentreonFeaturetesting extends CentreonWebService
{
    /** @var CentreonFeature */
    protected $obj;

    /**
     * CentreonFeaturetesting constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->obj = new CentreonFeature($this->pearDB);
    }

    /**
     * Enabled or disabled a feature flipping for an user
     *
     * METHOD POST
     *
     * @throws PDOException
     * @throws RestBadRequestException
     * @throws RestUnauthorizedException
     * @return void
     */
    public function postEnabled(): void
    {
        if (! isset($this->arguments['name'])
            || ! isset($this->arguments['version'])
            || ! isset($this->arguments['enabled'])) {
            throw new RestBadRequestException('Missing arguments');
        }
        if (! isset($_SESSION['centreon'])) {
            throw new RestUnauthorizedException('Session does not exists.');
        }
        $userId = $_SESSION['centreon']->user->user_id;
        $features = [];
        $features[$this->arguments['name']] = [];
        $features[$this->arguments['name']][$this->arguments['version']] = $this->arguments['enabled'] ? 1 : 0;

        $this->obj->saveUserFeaturesValue(
            $userId,
            $features
        );
    }
}
