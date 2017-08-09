<?php
/*
 * Copyright 2015 Centreon (http://www.centreon.com/)
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

$currentPath = dirname(__FILE__);

if (!defined('_MODULE_PATH_')) {
    define('_MODULE_PATH_', _CENTREON_PATH_ . '/www/modules/api-web-import-export/');
}

// Autoload
$sAppPath = _MODULE_PATH_ . '/core/class/';
spl_autoload_register(function ($sClass) use ($sAppPath) {
    $sFilePath = '';

    $explodedClassname = explode('\\', $sClass);
    $sCentreonExport = array_shift($explodedClassname);

    $sFilePath .= $sAppPath . implode('/', $explodedClassname);
    $sFilePath .= '.php';

    if (file_exists($sFilePath)) {
        require_once $sFilePath;
    }
});
