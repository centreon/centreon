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

//
// # Database retrieve information for Directory
//

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;

$dir = [];
$list = [];
$selected = [];

$userCanSeeAllFolders = ((int) $centreon->user->admin === 1 || $centreon->user->access->hasAccessToAllImageFolders);

// Change Directory
if ($o == IMAGE_MODIFY_DIRECTORY && $directoryId) {
    $directories = $pearDB->fetchAssociative(
        <<<'SQL'
            SELECT * FROM view_img_dir WHERE dir_id = :directoryId LIMIT 1
            SQL,
        QueryParameters::create(
            [QueryParameter::int('directoryId', $directoryId)]
        )
    );

    $dir = array_map('myDecode', $directories);
    // Set Child elements
    $childElements = $pearDB->fetchAllAssociative(
        <<<'SQL'
            SELECT DISTINCT img_img_id FROM view_img_dir_relation WHERE dir_dir_parent_id = :directoryId
            SQL,
        QueryParameters::create(
            [QueryParameter::int('directoryId', $directoryId)]
        )
    );
    foreach ($childElements as $i => $imgs) {
        $dir['dir_imgs'][$i] = $imgs['img_img_id'];
    }
} elseif ($o == IMAGE_MOVE) {
    $selected = [];
    if (isset($selectIds) && $selectIds) {
        $list = $selectIds;
    } elseif (isset($dir_imgs) && $dir_imgs) {
        $list = $dir_imgs;
    }

    foreach ($list as $selector => $status) {
        $ids = explode('-', $selector);
        if (count($ids) != 2) {
            continue;
        }
        $selected[] = $ids[1];
    }
}

//
// # Database retrieve information for differents elements list we need on the page
//
// Images comes from DB -> Store in $imgs Array
try {
    $imgs = [];

    $queryParameters = [];
    $request = <<<'SQL'
            SELECT
                images.img_id,
                CONCAT(directories.dir_alias, '/', images.img_name) AS imagePath
            FROM view_img AS images
            INNER JOIN view_img_dir_relation vidr
                ON vidr.img_img_id = images.img_id
            INNER JOIN view_img_dir AS directories
                ON directories.dir_id = vidr.dir_dir_parent_id
        SQL;

    if (! $userCanSeeAllFolders) {
        $request .= <<<'SQL'
                INNER JOIN acl_resources_image_folder_relations armdr
                    ON armdr.dir_id = directories.dir_id
                INNER JOIN acl_resources ar
                    ON ar.acl_res_id = armdr.acl_res_id
                INNER JOIN acl_res_group_relations argr
                    ON argr.acl_res_id = ar.acl_res_id
                LEFT JOIN acl_group_contacts_relations gcr
                    ON gcr.acl_group_id = argr.acl_group_id
                LEFT JOIN acl_group_contactgroups_relations gcgr
                    ON gcgr.acl_group_id = argr.acl_group_id
                LEFT JOIN contactgroup_contact_relation cgcr
                    ON cgcr.contactgroup_cg_id = gcgr.cg_cg_id
                    AND (cgcr.contact_contact_id = :contactId OR gcr.contact_contact_id = :contactId)
            SQL;
        $queryParameters[] = QueryParameter::int('contactId', $centreon->user->user_id);
    }

    if ($o == IMAGE_MOVE && $selected !== []) {
        [
            'parameters' => $bindImageParameters,
            'placeholderList' => $bindQuery,
        ] = createMultipleBindParameters($selected, 'imageId', QueryParameterTypeEnum::INTEGER);

        $request .= <<<SQL
                WHERE images.img_id IN ({$bindQuery}) AND directories.dir_name NOT IN ('centreon-map', 'ppm', 'dashboards')
            SQL;

        $queryParameters = array_merge($queryParameters, $bindImageParameters);
    }

    $request .= ' GROUP BY directories.dir_id, images.img_id ORDER BY directories.dir_alias, images.img_name';

    /** @var CentreonDB $pearDB */
    $records = $pearDB->iterateAssociative($request, QueryParameters::create($queryParameters));

    foreach ($records as $record) {
        $imgs[$record['img_id']] = htmlentities($record['imagePath'], ENT_QUOTES, 'utf-8');
    }
} catch (ValueObjectException|CollectionException|ConnectionException $e) {
    $exception =  new RepositoryException(
        message: 'Error while retrieving images from database: ' . $e->getMessage(),
        previous: $e
    );
    ExceptionLogger::create()->log($exception);

    throw $exception;
}

try {
    $directories = getListDirectory();
} catch (RepositoryException $e) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
        message: 'Error while retrieving image directories: ' . $e->getMessage(),
        exception: $e
    );

    throw $e;
}

// #########################################################
// Var information to format the element
//
$attrsText = ['size' => '30'];
$attrsSelect = ['size' => '5', 'multiple' => '1', 'cols' => '40', 'required' => 'true'];
$attrsAdvSelect = ['style' => 'width: 250px; height: 250px;'];
$attrsTextarea = ['rows' => '5', 'cols' => '40'];

//
// # Form begin
//
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);
if ($o == IMAGE_MODIFY_DIRECTORY) {
    $form->addElement('header', 'title', _('Modify directory'));
    $form->addElement('autocomplete', 'dir_name', _('Directory name'), $directories);
    $form->addElement('textarea', 'dir_comment', _('Comments'), $attrsTextarea);
    $form->setDefaults($dir);
} elseif ($o == IMAGE_MOVE) {
    $form->addElement('header', 'title', _('Move files to directory'));

    $userCanSeeAllFolders
        ? $form->addElement('autocomplete', 'dir_name', _('Destination directory'), $directories)
        : $form->addElement('select', 'dir_name', _('Destination Directory'), $directories);

    $form->addElement('select', 'dir_imgs', _('Images'), $imgs, $attrsSelect);
}

$tab = [];
$tab[] = $form->createElement('radio', 'action', null, _('List'), '1');
$tab[] = $form->createElement('radio', 'action', null, _('Form'), '0');
$form->addGroup($tab, 'action', _('Action'), '&nbsp;');
$form->setDefaults(['action' => '1']);

$form->addElement('hidden', 'dir_id');
$form->addElement('hidden', 'select');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

//
// # Form Rules
//
$form->applyFilter('__ALL__', 'myTrim');
if ($o == IMAGE_MODIFY_DIRECTORY && $directoryId) {
    $form->addRule('dir_name', _('Compulsory Name'), 'required');
    $form->setRequiredNote(_('Required Field'));
}

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// prepare help texts
$helptext = '';
include_once 'help.php';
foreach ($help as $key => $text) {
    $helptext .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign('helptext', $helptext);

if ($o == IMAGE_MOVE) {
    $subM = $form->addElement('submit', 'submitM', _('Apply'));
    $res = $form->addElement(
        'button',
        'cancel',
        _('Cancel'),
        ['onClick' => "javascript:window.location.href='?p={$p}'"]
    );
} elseif ($o == IMAGE_MODIFY_DIRECTORY) {
    $confirm = isset($dir['dir_imgs']) ? implode(',', $dir['dir_imgs']) : '';
    $subC = $form->addElement('submit', 'submitC', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement(
        'button',
        'cancel',
        _('Cancel'),
        [
            'class' => 'btc bt_success',
            'onClick' => "javascript:window.location.href='?p={$p}'",
        ]
    );
    $form->setDefaults($dir);
}

$valid = false;
if ($form->validate()) {
    if ($form->getSubmitValue('submitM')) {
        /**
         * Move files to new directory
         */
        $dir_name = $form->getSubmitValue('dir_name');
        $imgs = $form->getSubmitValue('dir_imgs');
        moveMultImg($imgs, $dir_name);
        $valid = true;
        // modify dir
    } elseif ($form->getSubmitValue('submitC')
        && ($directoryId = $form->getSubmitValue('dir_id'))
    ) {
        /**
         * Update directory name
         */
        $dirName = $form->getSubmitValue('dir_name');
        $dirCmnt = $form->getSubmitValue('dir_comment');
        updateDirectory($directoryId, $dirName, $dirCmnt);
        $valid = true;
    }
}
if ($valid) {
    $o = null;
    $form->freeze();
    require_once $path . 'listImg.php';
} else {
    // Apply a template definition
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
    $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $form->accept($renderer);
    $tpl->assign('form', $renderer->toArray());
    $tpl->assign('o', $o);
    $tpl->display('formDirectory.ihtml');
}
