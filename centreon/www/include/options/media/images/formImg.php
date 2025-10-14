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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;

require_once _CENTREON_PATH_ . 'www/class/centreonImageManager.php';
require_once __DIR__ . '/../../../../../bootstrap.php';

const BASE_CENTREON_IMG_DIRECTORY = './img/media';

/** @var Centreon $centreon */
if (! isset($centreon)) {
    exit();
}

$userCanSeeAllFolders = ((int) $centreon->user->admin === 1 || $centreon->user->access->hasAccessToAllImageFolders);

// Database retrieve information
$img = ['img_path' => null];

if ($o == IMAGE_MODIFY || $o == IMAGE_WATCH) {
    try {
        $query = <<<'SQL'
                SELECT
                    image.img_id,
                    image.img_name,
                    image.img_path,
                    image.img_comment,
                    directory.dir_id,
                    directory.dir_name AS `directories`,
                    directory.dir_alias
                FROM view_img AS image
                INNER JOIN view_img_dir_relation AS vidr
                ON vidr.img_img_id = image.img_id
                INNER JOIN view_img_dir AS directory
                    ON directory.dir_id = vidr.dir_dir_parent_id
                WHERE image.img_id = :imageId
                LIMIT 1
            SQL;

        $queryParameters = QueryParameters::create([QueryParameter::int('imageId', $imageId)]);
        $img = $pearDB->fetchAssociative($query, $queryParameters);
        $img_path = sprintf('%s/%s/%s', BASE_CENTREON_IMG_DIRECTORY, $img['dir_alias'], $img['img_path']);
    } catch (ValueObjectException|CollectionException|ConnectionException $e) {
        $exception = new RepositoryException(
            message: 'Error while retrieving image information',
            context: ['imageId' => $imageId],
            previous: $e
        );
        ExceptionLogger::create()->log($exception);

        throw $exception;
    }

}

// Get Directories
try {
    $directoryIds = getListDirectory();
} catch (RepositoryException $e) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
        message: 'Error while retrieving image directories: ' . $e->getMessage(),
        exception: $e
    );

    throw $e;
}
$directoryListForSelect = $directoryIds;
$directoryListForSelect[0] = '';
asort($directoryListForSelect);

// Styles
$attrsText = ['size' => '35'];
$attrsAdvSelect = ['style' => 'width: 200px; height: 100px;'];
$attrsTextarea = ['rows' => '5', 'cols' => '80'];

// Form begin
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);

if ($o == IMAGE_ADD) {
    $form->addElement('header', 'title', _('Add Image(s)'));
    $form->addElement(
        'autocomplete',
        'directories',
        $userCanSeeAllFolders ? _('Existing or new directory') : _('Existing directory'),
        $directoryIds,
        [
            'id' => 'directories',
            'style' => $userCanSeeAllFolders ? '' : 'display:none;',
        ]
    );
    $form->addElement(
        'select',
        'list_dir',
        '',
        $directoryListForSelect,
        ['onchange' => 'document.getElementById("directories").value = this.options[this.selectedIndex].text;']
    );
    $form->addElement('file', 'filename', _('Image or archive'));
    $subA = $form->addElement(
        'submit',
        'submitA',
        _('Save'),
        ['class' => 'btc bt_success']
    );
    $form->registerRule('isCorrectMIMEType', 'callback', 'isCorrectMIMEType');
    $form->addRule('filename', _('Invalid Image Format.'), 'isCorrectMIMEType');
} elseif ($o == IMAGE_MODIFY) {
    $form->addElement('header', 'title', _('Modify Image'));
    $form->addElement('text', 'img_name', _('Image Name'), $attrsText);

    // Small hack for user with not enough rights to see all folders and avoid creation of a directory that user will not see
    // post creation. Pure cosmetic
    $form->addElement(
        'autocomplete',
        'directories',
        $userCanSeeAllFolders ? _('Existing or new directory') : _('Existing directory'),
        $directoryIds,
        [
            'id' => 'directories',
            'style' => $userCanSeeAllFolders ? '' : 'display:none;',
        ]
    );

    $directorySelect = $form->addElement(
        'select',
        'list_dir',
        '&nbsp;',
        $directoryListForSelect,
        ['onchange' => 'document.getElementById("directories").value = this.options[this.selectedIndex].text;']
    );

    $directorySelect->setSelected($img['dir_id']);
    $form->addElement('file', 'filename', _('Image'));
    $subC = $form->addElement(
        'submit',
        'submitC',
        _('Save'),
        ['class' => 'btc bt_success']
    );
    $form->setDefaults($img);
    $form->addRule('img_name', _('Compulsory image name'), 'required');
    $form->registerRule('isCorrectMIMEType', 'callback', 'isCorrectMIMEType');
    $form->addRule('filename', _('Invalid Image Format.'), 'isCorrectMIMEType');
} elseif ($o == IMAGE_WATCH) {
    $form->addElement('header', 'title', _('View Image'));
    $form->addElement('text', 'img_name', _('Image Name'), $attrsText);
    $form->addElement('text', 'img_path', $img_path, null);
    $form->addElement(
        'autocomplete',
        'directories',
        _('Directory'),
        $directoryIds,
        ['id', 'directories']
    );
    $form->addElement('file', 'filename', _('Image'));
    $form->addElement(
        'button',
        'change',
        _('Modify'),
        ['onClick' => "javascript:window.location.href='?p={$p}&o=ci&img_id={$imageId}'"],
        ['class' => 'btc bt_success']
    );
    $form->setDefaults($img);
}

$form->addElement(
    'button',
    'cancel',
    _('Cancel'),
    [
        'onClick' => "javascript:window.location.href='?p={$p}'",
        'class' => 'btc bt_default',
    ]
);

$form->addElement('textarea', 'img_comment', _('Comments'), $attrsTextarea);

$tab = [];
$tab[] = $form->createElement('radio', 'action', null, _('Return to list'), '1');
$tab[] = $form->createElement('radio', 'action', null, _('Review form after save'), '0');
$form->addGroup($tab, 'action', _('Action'), '&nbsp;');
$form->setDefaults(['action' => '1']);

$form->addElement('hidden', 'img_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

// Form Rules
$form->applyFilter('__ALL__', 'myTrim');
$form->addRule('directories', _('Required Field'), 'required');
$form->setRequiredNote(_('Required Field'));

// watch/view
if ($o == IMAGE_WATCH) {
    $form->freeze();
}

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

$tpl->assign(
    'helpattr',
    'TITLE, "' . _('Help') . '", CLOSEBTN, true, FIX, [this, 0, 5], '
    . 'BGCOLOR, "#ffff99", BORDERCOLOR, "orange", TITLEFONTCOLOR, '
    . '"black", TITLEBGCOLOR, "orange", CLOSEBTNCOLORS, ["","black", '
    . '"white", "red"], WIDTH, -300, SHADOW, true, TEXTALIGN, "justify"'
);

// prepare help texts
$helptext = '';
include_once 'help.php';
foreach ($help as $key => $text) {
    $helptext .= '<span style="display:none" id="help:'
        . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign('helptext', $helptext);

$valid = false;
if ($form->validate()) {
    $imgId = $form->getElement('img_id')->getValue();
    $imgPath = $form->getElement('directories')->getValue();
    $imgComment = $form->getElement('img_comment')->getValue();
    /**
     * Check if an archive has been extracted into pendingMedia folder
     */
    $filesToUpload = getFilesFromTempDirectory('pendingMedia');

    /**
     * If a single image is uploaded
     */
    if ($filesToUpload === []) {
        $oImageUploader = new CentreonImageManager(
            $dependencyInjector,
            $_FILES,
            './img/media/',
            $imgPath,
            $imgComment
        );
        if ($form->getSubmitValue('submitA')) {
            $valid = $oImageUploader->upload();
        } elseif ($form->getSubmitValue('submitC')) {
            $imgName = $form->getElement('img_name')->getValue();
            $valid = $oImageUploader->update($imgId, $imgName);
        }
        $form->freeze();
        if ($valid === false) {
            $form->setElementError('filename', 'An image is not uploaded.');
        }
        /**
         * If an archive .zip or .tgz is uploaded
         */
    } else {
        foreach ($filesToUpload as $file) {
            $oImageUploader = new CentreonImageManager(
                $dependencyInjector,
                $file,
                './img/media/',
                $imgPath,
                $imgComment
            );
            if ($form->getSubmitValue('submitA')) {
                $valid = $oImageUploader->uploadFromDirectory('pendingMedia');
            } elseif ($form->getSubmitValue('submitC')) {
                $imgName = $form->getElement('img_name')->getValue();
                $valid = $oImageUploader->update($imgId, $imgName);
            }
            $form->freeze();
            if ($valid === false) {
                $form->setElementError('filename', 'Images already uploaded.');
            }
        }
        /**
         * Remove the folder after upload complete
         */
        removeRecursiveTempDirectory(sys_get_temp_dir() . '/pendingMedia');
    }
}
$action = $form->getSubmitValue('action');

if ($valid) {
    require_once 'listImg.php';
} else {
    // Apply a template definition
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
    $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $form->accept($renderer);
    $tpl->assign('form', $renderer->toArray());
    $tpl->assign('max_uploader_file', ini_get('upload_max_filesize'));
    $tpl->assign('o', $o);
    $tpl->display('formImg.ihtml');
}
