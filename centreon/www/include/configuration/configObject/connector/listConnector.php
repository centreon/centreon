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

include_once './class/centreonUtils.class.php';

include_once './include/common/autoNumLimit.php';

// restoring the pagination if we stay on this menu
$num = 0;
if ($centreon->historyLastUrl === $url && isset($_GET['num'])) {
    $num = $_GET['num'];
}

try {
    $connectorsList = $connectorObj->getList(false, (int) $num, (int) $limit);

    // Smarty template initialization
    $tpl = SmartyBC::createSmartyTemplate($path);

    $tpl->assign('mode_access', $lvl_access);

    $form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);

    $tpl->assign(
        'msg',
        ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add'), 'delConfirm' => _('Do you confirm the deletion ?')]
    );

    // Toolbar select
    foreach (['o1', 'o2'] as $option) {
        $attrs1 = ['onchange' => 'javascript: '
            . ' var bChecked = isChecked(); '
            . " if (this.form.elements['" . $option . "'].selectedIndex != 0 && !bChecked) {"
            . " alert('" . _('Please select one or more items') . "'); return false;} "
            . "if (this.form.elements['" . $option . "'].selectedIndex == 1 && confirm('"
            . _('Do you confirm the duplication ?') . "')) {"
            . "   setO(this.form.elements['" . $option . "'].value); submit();} "
            . "else if (this.form.elements['" . $option . "'].selectedIndex == 2 && confirm('"
            . _('Do you confirm the deletion ?') . "')) {"
            . "   setO(this.form.elements['" . $option . "'].value); submit();} "
            . "else if (this.form.elements['" . $option . "'].selectedIndex == 3) {"
            . "   setO(this.form.elements['" . $option . "'].value); submit();} "
            . "this.form.elements['" . $option . "'].selectedIndex = 0"];

        $form->addElement(
            'select',
            $option,
            null,
            [null => _('More actions...'), 'm' => _('Duplicate'), 'd' => _('Delete')],
            $attrs1
        );
        $form->setDefaults([$option => null]);
        $o1 = $form->getElement($option);
        $o1->setValue(null);
        $o1->setSelected(null);
    }

    $elemArr = [];
    $j = 0;
    $attrsText = ['size' => '2'];
    $nbConnectors = count($connectorsList);
    $centreonToken = createCSRFToken();

    for ($i = 0; $i < $nbConnectors; $i++) {
        $result = $connectorsList[$i];
        $moptions = '';
        $MyOption = $form->addElement('text', 'options[' . $result['id'] . ']', _('Options'), $attrsText);
        $form->setDefaults(['options[' . $result['id'] . ']' => '1']);
        $selectedElements = $form->addElement('checkbox', 'select[' . $result['id'] . ']');
        if ($result) {
            if ($lvl_access == 'w') {
                if ($result['enabled']) {
                    $moptions = "<a href='main.php?p="
                        . $p . '&id=' . $result['id'] . '&o=u&limit=' . $limit . '&num=' . $num
                        . '&centreon_token=' . $centreonToken
                        . "'><img src='img/icons/disabled.png' class='ico-14 margin_right' border='0' alt='"
                        . _('Disabled') . "'></a>&nbsp;&nbsp;";
                } else {
                    $moptions = "<a href='main.php?p="
                        . $p . '&id=' . $result['id'] . '&o=s&limit=' . $limit . '&num=' . $num
                        . '&centreon_token=' . $centreonToken
                        . "'><img src='img/icons/enabled.png' class='ico-14 margin_right' border='0' alt='"
                        . _('Enabled') . "'></a>&nbsp;&nbsp;";
                }
                $moptions .= '&nbsp;'
                    . '<input onKeypress="if(event.keyCode > 31 '
                    . '&& (event.keyCode < 45 || event.keyCode > 57)) event.returnValue = false;'
                    . ' if(event.which > 31 && (event.which < 45 || event.which > 57)) return false;"'
                    . " maxlength=\"3\" size=\"3\" value='1'"
                    . " style=\"margin-bottom:0px;\" name='options[" . $result['id'] . "]'></input>";
                $moptions .= '&nbsp;&nbsp;';
            } else {
                $moptions = '&nbsp;';
            }

            $elemArr[$j] = ['RowMenu_select' => $selectedElements->toHtml(), 'RowMenu_link' => 'main.php?p=' . $p . '&o=c&id=' . $result['id'], 'RowMenu_name' => CentreonUtils::escapeSecure($result['name']), 'RowMenu_description' => CentreonUtils::escapeSecure($result['description']), 'RowMenu_command_line' => CentreonUtils::escapeSecure($result['command_line']), 'RowMenu_enabled' => $result['enabled'] ? _('Enabled') : _('Disabled'), 'RowMenu_badge' => $result['enabled'] ? 'service_ok' : 'service_critical', 'RowMenu_options' => $moptions];
        }
        $j++;
    }

    /**
     * @todo implement
     */
    $rows = $connectorObj->count(false);

    include_once './include/common/checkPagination.php';

    $tpl->assign('elemArr', $elemArr);
    $tpl->assign('p', $p);
    $tpl->assign('connectorsList', $connectorsList);
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
    $form->accept($renderer);
    $tpl->assign('form', $renderer->toArray());
    $tpl->assign('limit', $limit);
    $tpl->display('listConnector.ihtml');
} catch (Exception $e) {
    echo 'Erreur n°' . $e->getCode() . ' : ' . $e->getMessage();
}
