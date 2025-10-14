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

if (! isset($centreon)) {
    exit();
}

include './include/common/autoNumLimit.php';
include_once './class/centreonUtils.class.php';

$search = null;
if (isset($_POST['searchM'])) {
    $centreon->historySearch[$url] = CentreonUtils::escapeSecure(
        $_POST['searchM'],
        CentreonUtils::ESCAPE_ALL_EXCEPT_LINK
    );
    $search = $centreon->historySearch[$url];
} elseif (isset($_GET['searchM'])) {
    $centreon->historySearch[$url] = CentreonUtils::escapeSecure(
        $_GET['searchM'],
        CentreonUtils::ESCAPE_ALL_EXCEPT_LINK
    );
    $search = $centreon->historySearch[$url];
} elseif (isset($centreon->historySearch[$url])) {
    $search = $centreon->historySearch[$url];
}

try {
    $queryParameters = [];

    if (
        $centreon->user->admin === '1'
        || $centreon->user->access->hasAccessToAllImageFolders
    ) {
        $query = <<<'SQL'
                SELECT
                    images.*,
                    directories.*
                FROM view_img_dir AS `directories`
                LEFT JOIN view_img_dir_relation AS `vidr`
                    ON vidr.dir_dir_parent_id = directories.dir_id
                LEFT JOIN view_img AS `images`
                    ON images.img_id = vidr.img_img_id
            SQL;
    } else {
        $query = <<<'SQL'
                SELECT
                    images.*,
                    directories.*
                FROM view_img_dir AS `directories`
                LEFT JOIN view_img_dir_relation AS `vidr`
                    ON vidr.dir_dir_parent_id = directories.dir_id
                LEFT JOIN view_img AS `images`
                    ON images.img_id = vidr.img_img_id
                INNER JOIN acl_resources_image_folder_relations armdr
                    ON armdr.dir_id = vidr.dir_dir_parent_id
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

    if ($search) {
        $queryParameters[] = QueryParameter::string(
            'search',
            '%' . HtmlSanitizer::createFromString($search)->getString() . '%',
        );
        $query .= <<<'SQL'
                WHERE (images.img_name LIKE :search OR directories.dir_name LIKE :search) AND directories.dir_name NOT IN ('centreon-map', 'dashboards', 'ppm')
            SQL;
    } else {
        $query .= <<<'SQL'
                WHERE directories.dir_name NOT IN ('centreon-map', 'dashboards', 'ppm')
            SQL;
    }
    $query .= ' GROUP BY images.img_id, directories.dir_id';
    $query .= ' ORDER BY dir_alias, img_name LIMIT :offset, :limit';
    $queryParameters[] = QueryParameter::int('offset', $num * $limit);
    $queryParameters[] = QueryParameter::int('limit', $limit);

    /** @var CentreonDB $pearDB */
    $res = $pearDB->fetchAllAssociative($query, QueryParameters::create($queryParameters));
    $rows = count($res);
} catch (ValueObjectException|CollectionException|ConnectionException $e) {
    $exception = new RepositoryException(
        message: 'Unable to retrieve images and directories',
        context: ['search' => $search, 'contactId' => $centreon->user->user_id],
        previous: $e
    );
    ExceptionLogger::create()->log($exception);

    throw $exception;
}

include './include/common/checkPagination.php';

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// start header menu
$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_desc', _('Directory'));
$tpl->assign('headerMenu_img', _('Image'));
$tpl->assign('headerMenu_comment', _('Comment'));

$form = new HTML_QuickFormCustom('form', 'POST', '?p=' . $p);

// Fill a tab with a mutlidimensionnal Array we put in $tpl
$elemArr = [];
foreach ($res as $i => $elem) {
    if (isset($elem['dir_id']) && ! isset($elemArr[$elem['dir_id']])) {
        $selectedDirElem = $form->addElement('checkbox', 'select[' . $elem['dir_id'] . ']');
        $selectedDirElem->setAttribute('onclick', "setSubNodes(this, 'select[" . $elem['dir_id'] . "-')");
        $rowOpt = ['RowMenu_select' => $selectedDirElem->toHtml(), 'RowMenu_DirLink' => 'main.php?p=' . $p . '&o=cd&dir_id=' . $elem['dir_id'], 'RowMenu_dir' => CentreonUtils::escapeSecure(
            $elem['dir_name'],
            CentreonUtils::ESCAPE_ALL_EXCEPT_LINK
        ), 'RowMenu_dir_cmnt' => CentreonUtils::escapeSecure(
            $elem['dir_comment'],
            CentreonUtils::ESCAPE_ALL_EXCEPT_LINK
        ), 'RowMenu_empty' => _('Empty directory'), 'counter' => 0];
        $elemArr[$elem['dir_id']] = ['head' => $rowOpt, 'elem' => []];
    }

    if ($elem['img_id']) {
        $searchOpt = isset($search) && $search ? '&search=' . $search : '';
        $selectedImgElem = $form->addElement(
            'checkbox',
            'select[' . $elem['dir_id'] . '-' . $elem['img_id'] . ']'
        );
        $rowOpt = ['RowMenu_select' => $selectedImgElem->toHtml(), 'RowMenu_ImgLink' => "main.php?p={$p}&o=ci&img_id={$elem['img_id']}", 'RowMenu_DirLink' => "main.php?p={$p}&o=cd&dir_id={$elem['dir_id']}", 'RowMenu_dir' => CentreonUtils::escapeSecure(
            $elem['dir_name'],
            CentreonUtils::ESCAPE_ALL_EXCEPT_LINK
        ), 'RowMenu_img' => CentreonUtils::escapeSecure(
            html_entity_decode($elem['dir_alias'] . '/' . $elem['img_path'], ENT_QUOTES, 'UTF-8'),
            CentreonUtils::ESCAPE_ALL_EXCEPT_LINK
        ), 'RowMenu_name' => CentreonUtils::escapeSecure(
            html_entity_decode($elem['img_name'], ENT_QUOTES, 'UTF-8'),
            CentreonUtils::ESCAPE_ALL_EXCEPT_LINK
        ), 'RowMenu_comment' => CentreonUtils::escapeSecure(
            html_entity_decode($elem['img_comment'], ENT_QUOTES, 'UTF-8'),
            CentreonUtils::ESCAPE_ALL_EXCEPT_LINK
        )];
        $elemArr[$elem['dir_id']]['elem'][$i] = $rowOpt;
        $elemArr[$elem['dir_id']]['head']['counter']++;
    }
}

$tpl->assign('elemArr', $elemArr);

// Calculate available disk's space

$bytes = disk_free_space(CentreonMedia::CENTREON_MEDIA_PATH);
$units = ['B', 'KB', 'MB', 'GB', 'TB'];
$class = min((int) log($bytes, 1024), count($units) - 1);
$availiableSpace = sprintf('%1.2f', $bytes / pow(1024, $class)) . ' ' . $units[$class];
$tpl->assign('availiableSpace', $availiableSpace);
$tpl->assign('Available', _('Available'));

// Different messages we put in the template
$tpl->assign(
    'msg',
    ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add'), 'delConfirm' => _('Do you confirm the deletion?')]
);

?>
    <script type="text/javascript">
        function openPopup(pageNumber) {
            window.open(
              './main.get.php?p=' + pageNumber + '&o=sd&min=1',
              '',
              'toolbar=no,location=no,directories=no,status=no,scrollbars=yes,resizable=yes,copyhistory=no,' +
              'width=350,height=250'
            )
        }

        function setO(_i) {
            document.forms['form'].elements['o'].value = _i;
        }

        function submitO(_i) {
            if (document.forms['form'].elements[_i].selectedIndex == 1 &&
                confirm('<?php echo _('Do you confirm the deletion?'); ?>')
            ) {
                setO(document.forms['form'].elements[_i].value);
                document.forms['form'].submit();
            } else if (document.forms['form'].elements[_i].selectedIndex == 2) {
                setO(document.forms['form'].elements[_i].value);
                document.forms['form'].submit();
            }
            document.forms['form'].elements[_i].selectedIndex = 0;
        }

        function setSubNodes(theElement, like) {
            var theForm = theElement.form;
            var z = 0;
            for (z = 0; z < theForm.length; z++) {
                if (theForm[z].type == 'checkbox' && theForm[z].disabled == '0' && theForm[z].name.indexOf(like) >= 0) {
                    if (theElement.checked && !theForm[z].checked) {
                        theForm[z].checked = true;
                        if (typeof(_selectedElem) != 'undefined') {
                            putInSelectedElem(theForm[z].id);
                        }
                    } else if (!theElement.checked && theForm[z].checked) {
                        theForm[z].checked = false;
                        if (typeof(_selectedElem) != 'undefined') {
                            removeFromSelectedElem(theForm[z].id);
                        }
                    }
                }
            }
        }


    </script>
<?php
$actions = [null => _('More actions'), IMAGE_DELETE => _('Delete'), IMAGE_MOVE => _('Move images')];
$form->addElement('select', 'o1', null, $actions, ['onchange' => "javascript:submitO('o1');"]);
$form->addElement('select', 'o2', null, $actions, ['onchange' => "javascript:submitO('o2');"]);
if ($centreon->user->admin === '1') {
    $form->addElement(
        'button',
        'syncDir',
        _('Synchronize Media Directory'),
        ['onClick' => "openPopup({$p})", 'class' => 'btc bt_info ml-2 mr-1'],
    );
}
$form->setDefaults(['o1' => null]);
$form->setDefaults(['o2' => null]);

$o1 = $form->getElement('o1');
$o1->setValue(null);
$o1->setSelected(null);

$o2 = $form->getElement('o2');
$o2->setValue(null);
$o2->setSelected(null);

$tpl->assign('limit', $limit);
$tpl->assign('p', $p);
$tpl->assign('session_id', session_id());
$tpl->assign('searchM', $search);

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listImg.ihtml');
