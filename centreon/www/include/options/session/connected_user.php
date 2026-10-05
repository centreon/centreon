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

if (! isset($centreon)) {
    exit();
}

$path = './include/options/session/';
require_once './include/common/common-Func.php';

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// $centreon->user->admin is a string '1' or '0'
$isAdmin = ($centreon->user->admin == 1);

// Default rows-per-page (shared configuration option)
$defaultLimit = 30;
$optResult = $pearDB->query("SELECT `value` FROM `options` WHERE `key` = 'maxViewConfiguration'");
if ($opt = $optResult->fetch()) {
    $defaultLimit = (int) $opt['value'] ?: 30;
}

// Page identity
$tpl->assign('pageId', $p);
$tpl->assign('isAdmin', $centreon->user->admin);
$tpl->assign('currentUserId', (int) $centreon->user->get_id());
$tpl->assign('defaultLimit', $defaultLimit);

// Page header
$tpl->assign('pageTitle', _('Active Sessions'));
$tpl->assign('pageSubtitle', _('Users currently connected to the platform.'));

// Column titles (rendered in the table header)
$tpl->assign('wi_user', _('Users'));
$tpl->assign('wi_where', _('Position'));
$tpl->assign('wi_last_req', _('Last request'));
$tpl->assign('distant_location', _('IP Address'));
if ($isAdmin) {
    $tpl->assign('wi_last_sync', _('Last LDAP sync'));
    $tpl->assign('wi_syncLdap', _('Refresh LDAP'));
    $tpl->assign('wi_logoutUser', _('Logout user'));
}

// JS-safe strings (json_encode produces valid, escaped JS string literals)
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
$tpl->assign('emptyMessageJs', json_encode(_('No active session'), $jsonFlags));
$tpl->assign('wi_kickTitleJs', json_encode(_('Disconnect user'), $jsonFlags));
$tpl->assign('wi_syncTitleJs', json_encode(_('Synchronize LDAP'), $jsonFlags));
$tpl->assign('wi_syncConfirmJs', json_encode(
    _('All this contact sessions will be closed. Are you sure you want to request a '
        . 'synchronization at the next login of this Contact ?'),
    $jsonFlags
));
$tpl->assign('wi_selfKickTitleJs', json_encode(_('You cannot disconnect your own session.'), $jsonFlags));

// Icons (json-encoded for safe embedding in the JS column renderers)
$tpl->assign('adminIconJs', json_encode(returnSvg('www/img/icons/admin.svg', 'var(--icons-fill-color)', 17, 17), $jsonFlags));
$tpl->assign('userIconJs', json_encode(returnSvg('www/img/icons/user.svg', 'var(--icons-fill-color)', 17, 17), $jsonFlags));
$tpl->assign('refreshIconJs', json_encode(returnSvg('www/img/icons/refresh.svg', 'var(--icons-fill-color)', 18, 18), $jsonFlags));
$tpl->assign('logoutIconJs', json_encode(returnSvg('www/img/icons/logout.svg', 'var(--icons-fill-color)', 18, 18), $jsonFlags));

$tpl->display('connected_user.ihtml');
