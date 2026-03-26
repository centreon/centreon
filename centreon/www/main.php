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

require_once __DIR__ . '/../bootstrap.php';

use CentreonLegacy\Core\Menu\Menu;

// Set logging options
if (defined('E_DEPRECATED')) {
    ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
} else {
    ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT);
}

// Purge Values
foreach ($_GET as $key => $value) {
    if (! is_array($value)) {
        $_GET[$key] = HtmlAnalyzer::sanitizeAndRemoveTags($value);
    }
}

$inputGet = [
    'p' => filter_input(INPUT_GET, 'p', FILTER_SANITIZE_NUMBER_INT),
    'num' => filter_input(INPUT_GET, 'num', FILTER_SANITIZE_NUMBER_INT),
    'o' => HtmlAnalyzer::sanitizeAndRemoveTags($_GET['o'] ?? ''),
    'min' => HtmlAnalyzer::sanitizeAndRemoveTags($_GET['min'] ?? ''),
    'type' => HtmlAnalyzer::sanitizeAndRemoveTags($_GET['type'] ?? ''),
    'search' => HtmlAnalyzer::sanitizeAndRemoveTags($_GET['search'] ?? ''),
    'limit' => HtmlAnalyzer::sanitizeAndRemoveTags($_GET['limit'] ?? ''),
];
$inputPost = [
    'p' => filter_input(INPUT_POST, 'p', FILTER_SANITIZE_NUMBER_INT),
    'num' => filter_input(INPUT_POST, 'num', FILTER_SANITIZE_NUMBER_INT),
    'o' => HtmlAnalyzer::sanitizeAndRemoveTags($_POST['o'] ?? ''),
    'min' => HtmlAnalyzer::sanitizeAndRemoveTags($_POST['min'] ?? ''),
    'type' => HtmlAnalyzer::sanitizeAndRemoveTags($_POST['type'] ?? ''),
    'search' => HtmlAnalyzer::sanitizeAndRemoveTags($_POST['search'] ?? ''),
    'limit' => HtmlAnalyzer::sanitizeAndRemoveTags($_POST['limit'] ?? ''),
];

$inputs = [];
foreach ($inputGet as $argumentName => $argumentValue) {
    if (! empty($inputGet[$argumentName]) && trim($inputGet[$argumentName]) != '') {
        $inputs[$argumentName] = $inputGet[$argumentName];
    } elseif (! empty($inputPost[$argumentName]) && trim($inputPost[$argumentName]) != '') {
        $inputs[$argumentName] = $inputPost[$argumentName];
    } else {
        $inputs[$argumentName] = null;
    }
}

$p = $inputs['p'];
$o = $inputs['o'];
$min = $inputs['min'];
$type = $inputs['type'];
$search = $inputs['search'];
$limit = $inputs['limit'];
$num = $inputs['num'];

// Include all func
include_once './include/common/common-Func.php';
include_once './include/core/header/header.php';

$userAgent = $_SERVER['HTTP_USER_AGENT'];
$isMobile = str_contains($userAgent, 'Mobil');

if ($isMobile) {
    $db = $dependencyInjector['configuration_db'];
    $menu = new Menu($db, $_SESSION['centreon']->user);
    $treeMenu = $menu->getMenu();
    require_once 'main.get.php';
} else {
    include './index.html';
}
