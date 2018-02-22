<?php
/**
 * Copyright 2018 Centreon
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

if (!isset($oreon)) {
    exit();
}

require_once _CENTREON_PATH_ . '/www/modules/centreon-awie/centreon-awie.conf.php';

$export = './modules/centreon-awie/core/submitExport.php';
// Smarty template Init
$path = _MODULE_PATH_ . "/core/templates/";
$tpl = new Smarty();
$tpl = initSmartyTpl($path, $tpl);
$tpl->assign('formPath', $export);
$tpl->display('formExport.tpl');
