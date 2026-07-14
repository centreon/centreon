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

$path = './include/options/media/images/';
require_once './include/common/common-Func.php';

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

$isAdmin = ($centreon->user->admin === '1');

// Default rows-per-page (shared configuration option)
$defaultLimit = 30;
$optResult = $pearDB->query("SELECT `value` FROM `options` WHERE `key` = 'maxViewConfiguration'");
if ($opt = $optResult->fetch()) {
    $defaultLimit = (int) $opt['value'] ?: 30;
}

// Available disk space on the media partition (hidden on cloud platforms,
// where the filesystem is not relevant to the user)
$availableSpace = '';
$isCloudPlatform = function_exists('isCloudPlatform') ? isCloudPlatform() : false;
if (! $isCloudPlatform) {
    $bytes = @disk_free_space(CentreonMedia::CENTREON_MEDIA_PATH);
    if ($bytes !== false) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $class = min((int) log($bytes, 1024), count($units) - 1);
        $availableSpace = sprintf('%1.2f', $bytes / pow(1024, $class)) . ' ' . $units[$class];
    }
}

// Write access: admin, or write level (1) on this page
$writeAccess = $isAdmin || ($centreon->user->access->page($p) == 1);

// Folder creation is only offered to users allowed to see every folder
// (same restriction as uploading into a brand new directory)
$canCreateFolder = $writeAccess
    && ($isAdmin || ! empty($centreon->user->access->hasAccessToAllImageFolders));

// Page identity
$tpl->assign('pageId', $p);
$tpl->assign('isAdmin', $isAdmin ? 1 : 0);
$tpl->assign('writeAccess', $writeAccess ? 1 : 0);
$tpl->assign('canCreateFolder', $canCreateFolder ? 1 : 0);
$tpl->assign('defaultLimit', $defaultLimit);
$tpl->assign('availableSpace', $availableSpace);

// Page header
$tpl->assign('pageTitle', _('Images'));
$tpl->assign('pageSubtitle', _('Browse and manage the images available in Centreon.'));

// Column titles + labels
$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_img', _('Image'));
$tpl->assign('headerMenu_comment', _('Comment'));
$tpl->assign('headerMenu_size', _('Size'));
$tpl->assign('headerMenu_date', _('Date'));
$tpl->assign('wi_available', _('Available'));
$tpl->assign('wi_add', _('Add'));
$tpl->assign('wi_delete', _('Delete'));
$tpl->assign('wi_sync', _('Synchronize Media Directory'));
$tpl->assign('wi_search', _('Search'));

// JS-safe strings
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
$tpl->assign('emptyMessageJs', json_encode(_('No image found'), $jsonFlags));
$tpl->assign('emptyDirJs', json_encode(_('Empty directory'), $jsonFlags));
$tpl->assign('delConfirmJs', json_encode(_('Do you confirm the deletion ?'), $jsonFlags));
$tpl->assign('addTitleJs', json_encode(_('Add Image(s)'), $jsonFlags));
$tpl->assign('moveOkJs', json_encode(_('Image moved.'), $jsonFlags));
$tpl->assign('moveKoJs', json_encode(_('The image could not be moved.'), $jsonFlags));
$tpl->assign('newFolderPromptJs', json_encode(_('New directory name:'), $jsonFlags));
$tpl->assign('folderCreatedJs', json_encode(_('Directory created.'), $jsonFlags));
$tpl->assign('folderInvalidJs', json_encode(_('Invalid directory name (allowed: letters, digits, "-" and "_").'), $jsonFlags));

$tpl->display('listImg.ihtml');
