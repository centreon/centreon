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

use CentreonLegacy\Core\Menu\Menu;
use Pimple\Container;

require_once __DIR__ . '/webService.class.php';

/**
 * Class
 *
 * @class CentreonMenu
 */
class CentreonMenu extends CentreonWebService implements CentreonWebServiceDiInterface
{
    /** @var CentreonDB */
    public $pearDB;

    /** @var Container */
    private $dependencyInjector;

    /**
     * Get the init menu on loading page
     *
     * Argument:
     *   page -> int - The current page
     *
     * Method: GET
     *
     * @throws RestUnauthorizedException
     * @return array
     */
    public function getMenu()
    {
        if (! isset($_SESSION['centreon'])) {
            throw new RestUnauthorizedException('Session does not exists.');
        }
        /**
         * Initialize the language translator
         */
        $this->dependencyInjector['translator'];
        $menu = new Menu($this->pearDB, $_SESSION['centreon']->user);

        return $menu->getMenu();
    }

    /**
     * Define the dependency injector
     *
     * @param Container $dependencyInjector
     *
     * @return void
     */
    public function finalConstruct(Container $dependencyInjector): void
    {
        $this->dependencyInjector = $dependencyInjector;
    }
}
