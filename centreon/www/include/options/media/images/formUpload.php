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

$tpl = SmartyBC::createSmartyTemplate($path);

$userCanSeeAllFolders = ((int) $centreon->user->admin === 1 || $centreon->user->access->hasAccessToAllImageFolders);

// Directories the user is allowed to upload into
$dirNames = array_values(getListDirectory());
sort($dirNames);

// Files are hard-capped at 2 MB by the upload endpoint, regardless of platform
$maxSize = '2M';

$tpl->assign('pageId', $p);
$tpl->assign('directories', $dirNames);
$tpl->assign('canCreateDir', $userCanSeeAllFolders ? 1 : 0);

// Header
$tpl->assign('pageTitle', _('Add Image(s)'));
$tpl->assign('pageSubtitle', _('Drag and drop your images, or click to browse.'));

// Labels
$tpl->assign('wi_directory', _('Directory'));
$tpl->assign('wi_newDirectory', _('New directory'));
$tpl->assign('wi_comment', _('Comments'));
$tpl->assign('wi_upload', _('Upload'));
$tpl->assign('wi_cancel', _('Cancel'));
$tpl->assign('wi_overwrite', _('Overwrite'));
$tpl->assign('wi_overwriteAll', _('Overwrite all'));
$tpl->assign('wi_skip', _('Skip'));
$tpl->assign('wi_skipAll', _('Skip all'));
$tpl->assign('wi_maxSize', sprintf(_('Allowed: JPG, PNG, GIF, SVG — max %s'), $maxSize));

// JS-safe strings
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
$tpl->assign('csrfTokenJs', json_encode(createCSRFToken(), $jsonFlags));
$tpl->assign('selectDirJs', json_encode(_('Please choose or enter a directory.'), $jsonFlags));
$tpl->assign('overwriteMsgJs', json_encode(_('"%s" already exists in this directory. Overwrite it?'), $jsonFlags));

$tpl->display('formUpload.ihtml');
