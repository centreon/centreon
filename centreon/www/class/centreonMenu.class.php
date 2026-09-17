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

/**
 * Class
 *
 * @class CentreonMenu
 */
class CentreonMenu
{
    /** @var CentreonLang */
    protected $centreonLang;

    /** @var CentreonDB */
    protected $db;

    /**
     * CentreonMenu constructor
     *
     * @param CentreonLang $centreonLang
     */
    public function __construct($centreonLang)
    {
        $this->centreonLang = $centreonLang;
    }

    /**
     * translates
     *
     * @param int $isModule
     * @param string $url
     * @param string $menuName
     *
     * @return string
     */
    public function translate($isModule, $url, $menuName)
    {
        $moduleName = '';
        if ($isModule && $url) {
            if (preg_match("/\.\/modules\/([a-zA-Z-_]+)/", $url, $matches)) {
                if (isset($matches[1])) {
                    $moduleName = $matches[1];
                }
            }
        }
        $name = _($menuName);
        if ($moduleName) {
            $this->centreonLang->bindLang('messages', 'www/modules/' . $moduleName . '/locale/');
            $name = _($menuName);
            $this->centreonLang->bindLang();
        }

        return $name;
    }
}
