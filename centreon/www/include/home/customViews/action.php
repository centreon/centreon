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

require_once __DIR__ . '/../../../../bootstrap.php';
require_once realpath(__DIR__ . '/../../../../config/centreon.config.php');
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonCustomView.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonWidget.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonUser.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonXML.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonContactgroup.class.php';

session_start();
session_write_close();

if (empty($_POST['action']) || ! isset($_SESSION['centreon'])) {
    exit();
}

$centreon = $_SESSION['centreon'];

$db = new CentreonDB();
if (CentreonSession::checkSession(session_id(), $db) === false) {
    exit();
}

$action = $_POST['action'];

$postFilter = [
    'widget_id' => [
        'filter' => FILTER_VALIDATE_INT,
        'options' => [
            'default' => false,
        ],
    ],
    'custom_view_id' => [
        'filter' => FILTER_VALIDATE_INT,
        'options' => [
            'default' => false,
        ],
    ],
    'timer' => [
        'filter' => FILTER_VALIDATE_INT,
        'options' => [
            'default' => false,
        ],
    ],
    'public' => [
        'filter' => FILTER_VALIDATE_INT,
        'options' => [
            'default' => false,
        ],
    ],
    'user_id' => [
        'filter' => FILTER_VALIDATE_INT,
        'options' => [
            'default' => false,
        ],
    ],
    'viewLoad' => [
        'filter' => FILTER_VALIDATE_INT,
        'options' => [
            'default' => false,
        ],
    ],
    'layout' => [
        'options' => [
            'default' => '',
        ],
    ],
    'widget_model_id' => [
        'filter' => FILTER_VALIDATE_INT,
        'options' => [
            'default' => false,
        ],
    ],
    'widget_model_id' => [
        'filter' => FILTER_VALIDATE_INT,
        'options' => [
            'default' => false,
        ],
    ],
];

$postInputs = filter_input_array(INPUT_POST, $postFilter);

$postInputs['name'] = isset($_POST['name'])
    ? HtmlAnalyzer::sanitizeAndRemoveTags($_POST['name'])
    : null;

$postInputs['widget_title'] = isset($_POST['widget_title'])
    ? HtmlAnalyzer::sanitizeAndRemoveTags($_POST['widget_title'])
    : null;

$lockedUsers = [];
if (! empty($_POST['lockedUsers'])) {
    foreach ($_POST['lockedUsers'] as $lockedUserId) {
        if (filter_var($lockedUserId, FILTER_VALIDATE_INT) !== false) {
            $lockedUsers[] = (int) $lockedUserId;
        }
    }
}
$unlockedUsers = [];
if (! empty($_POST['unlockedUsers'])) {
    foreach ($_POST['unlockedUsers'] as $unlockedUserId) {
        if (filter_var($unlockedUserId, FILTER_VALIDATE_INT) !== false) {
            $unlockedUsers[] = (int) $unlockedUserId;
        }
    }
}
$lockedUsergroups = [];
if (! empty($_POST['lockedUsergroups'])) {
    foreach ($_POST['lockedUsergroups'] as $lockedUsergroupsId) {
        if (filter_var($lockedUsergroupsId, FILTER_VALIDATE_INT) !== false) {
            $lockedUsergroups[] = (int) $lockedUsergroupsId;
        }
    }
}
$unlockedUsergroups = [];
if (! empty($_POST['unlockedUsergroups'])) {
    foreach ($_POST['unlockedUsergroups'] as $unlockedUsergroupsId) {
        if (filter_var($unlockedUsergroupsId, FILTER_VALIDATE_INT) !== false) {
            $unlockedUsergroups[] = (int) $unlockedUsergroupsId;
        }
    }
}

$positions = [];
if (! empty($_POST['positions'])) {
    foreach ($_POST['positions'] as $position) {
        if (HtmlAnalyzer::sanitizeAndRemoveTags($position) !== false) {
            $positions[] = $position;
        }
    }
}

$createLoad = '';
if (! empty($_POST['create_load']['create_load'])) {
    $createLoad = $_POST['create_load']['create_load'];
}

$postInputs['layout'] = isset($_POST['layout']['layout'])
    ? HtmlAnalyzer::sanitizeAndRemoveTags($_POST['layout']['layout'])
    : null;

$viewObj = new CentreonCustomView($centreon, $db);
$widgetObj = new CentreonWidget($centreon, $db);

$xml = new CentreonXML();

if ($postInputs['custom_view_id']) {
    // check wether or not user can modify the view (locked & visible)
    $permission = $viewObj->checkPermission($postInputs['custom_view_id']);
} else {
    $postInputs['custom_view_id'] = '';
}
// check if the user can perform the provided action
$authorized = ($centreon->user->admin === '0') ? $viewObj->checkUserActions($action) : true;
$xml->startElement('response');
try {
    switch ($action) {
        case 'add':
            if (! empty($createLoad)) {
                if ($createLoad === 'create') {
                    $postInputs['custom_view_id'] = $viewObj->addCustomView(
                        $postInputs['name'],
                        $postInputs['layout'],
                        $postInputs['public'],
                        $authorized
                    );
                    if ($postInputs['widget_id']) {
                        $widgetObj->updateViewWidgetRelations($postInputs['custom_view_id']);
                    }
                } elseif ($createLoad === 'load' && ! empty($postInputs['viewLoad'])) {
                    $postInputs['custom_view_id'] = $viewObj->loadCustomView($postInputs['viewLoad'], $authorized);
                }
            }
            break;
        case 'edit':
            if ($postInputs['custom_view_id']) {
                $viewObj->updateCustomView(
                    $postInputs['custom_view_id'],
                    $postInputs['name'],
                    $postInputs['layout'],
                    $postInputs['public'],
                    $permission,
                    $authorized
                );

                if ($postInputs['widget_id']) {
                    $widgetObj->updateViewWidgetRelations($postInputs['custom_view_id'], $postInputs['widget_id']);
                }

                // update share
                if (! $postInputs['public']) {
                    $postInputs['public'] = 0;
                }

                if (empty($postInputs['user_id'])) {
                    $userId = $centreon->user->user_id;
                }
            }
            break;
        case 'share':
            $viewObj->shareCustomView(
                $postInputs['custom_view_id'],
                $lockedUsers,
                $unlockedUsers,
                $lockedUsergroups,
                $unlockedUsergroups,
                $centreon->user->user_id,
                $permission,
                $authorized
            );
            break;
        case 'remove':
            $viewObj->removeUserFromView($postInputs['user_id'], $postInputs['custom_view_id'], $permission);
            $xml->writeElement('contact_name', $centreon->user->getContactName($db, $postInputs['user_id']));
            break;
        case 'removegroup':
            $cgObj = new CentreonContactgroup($db);
            $viewObj->removeUsergroupFromView($postInputs['custom_view_id'], $postInputs['usergroup_id']);
            $xml->writeElement('contact_name', $cgObj->getNameFromCgId($postInputs['usergroup_id']));
            break;
        case 'setPreferences':
            $widgetObj->updateUserWidgetPreferences($_POST, $permission, $authorized);
            break;
        case 'deleteWidget':
            $widgetObj->deleteWidgetFromView(
                $postInputs['custom_view_id'],
                $postInputs['widget_id'],
                $authorized,
                $permission
            );
            break;
        case 'position':
            $widgetObj->updateWidgetPositions($postInputs['custom_view_id'], $permission, $positions);
            break;
        case 'deleteView':
            if ($postInputs['custom_view_id']) {
                $viewObj->deleteCustomView($postInputs['custom_view_id'], $authorized);
            }
            break;
        case 'addWidget':
            $widgetObj->addWidget(
                $postInputs['custom_view_id'],
                $postInputs['widget_model_id'],
                $postInputs['widget_title'],
                $permission,
                $authorized
            );
            break;
        case 'setDefault':
            if ($postInputs['custom_view_id']) {
                $viewObj->setDefault($postInputs['custom_view_id']);
            }
            break;
        case 'setRotate':
            if ($postInputs['timer'] >= 0) {
                $centreon->user->setContactParameters($db, ['widget_view_rotation' => $postInputs['timer']]);
            }
            break;
        case 'defaultEditMode':
            $_SESSION['customview_edit_mode'] = $_POST['editMode'];
            break;
        case 'get_share_info':
            $viewId = isset($_POST['viewId']) ? filter_var($_POST['viewId'], FILTER_VALIDATE_INT) : false;
            if (false !== $viewId) {
                $viewers = $viewObj->getUsersFromViewId($viewId);
                $viewerGroups = $viewObj->getUsergroupsFromViewId($viewId);
                $xml->startElement('contacts');
                foreach ($viewers as $viewer) {
                    if ($viewer['user_id'] != $centreon->user->user_id) {
                        $xml->startElement('contact');
                        $xml->writeAttribute('locked', $viewer['locked']);
                        $xml->writeAttribute('id', $viewer['user_id']);
                        $xml->text($viewer['contact_name']);
                        $xml->endElement();
                    }
                }
                $xml->endElement();
                $xml->startElement('contactgroups');
                foreach ($viewerGroups as $viewerGroup) {
                    $xml->startElement('contactgroup');
                    $xml->writeAttribute('locked', $viewerGroup['locked']);
                    $xml->writeAttribute('id', $viewerGroup['usergroup_id']);
                    $xml->text($viewerGroup['cg_name']);
                    $xml->endElement();
                }
                $xml->endElement();
            }
            break;
        default:
            throw new CentreonCustomViewException('Unsupported action provided.');
    }
    $xml->writeElement('custom_view_id', $postInputs['custom_view_id']);
} catch (CentreonCustomViewException|CentreonWidgetException $e) {
    $xml->writeElement('error', $e->getMessage());
}
$xml->endElement();

header('Content-Type: text/xml');
header('Pragma: no-cache');
header('Expires: 0');
header('Cache-Control: no-cache, must-revalidate');

$xml->output();
