<?php
/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

require_once(realpath(dirname(__FILE__) . '/../config/centreon.config.php'));
require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreon-knowledge/wikiApi.class.php';

$wikiApi = new WikiApi();
$pages = $wikiApi->getAllPages();

foreach ($pages as $page) {
    $newName = '';
    if (preg_match('/Host\:(.+)/', $page, $matches)) {
        $newName = 'Host : ' . $matches[1];
    } elseif (preg_match('/Host-Template\:(.+)/', $page, $matches)) {
        $newName = 'Host-Template : ' . $matches[1];
    } elseif (preg_match('/Service\:(.+)/', $page, $matches) && !preg_match('/Service\:\s+\/\s+/', $page)) {
        $name = explode(' ', $matches[1]);
        if (count($name) > 1) {
            $hostName = array_shift($name);
            $serviceName = implode(' ', $name);
            $newName = 'Service : ' . $hostName . ' / ' . $serviceName;
        }
    } elseif (preg_match('/Service-Template\:(.+)/', $page, $matches)) {
        $newName = 'Service-Template : ' . $matches[1];
    }

    if (!empty($newName)) {
        $newName = str_replace(' ', '_', $newName);
        $wikiApi->movePage($page, $newName);
    }
}
