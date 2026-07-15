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

if (! isset($oreon)) {
    exit();
}

include_once './class/centreonUtils.class.php';

include './include/common/autoNumLimit.php';

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// Access level
$lvl_access = ($centreon->user->access->page($p) == 1) ? 'w' : 'r';
$tpl->assign('mode_access', $lvl_access);

$tpl->assign('headerMenu_host', _('Host'));
$tpl->assign('headerMenu_service', _('Services'));
$tpl->assign('headerMenu_metric', _('Metrics'));
$tpl->assign('headerMenu_options', _('Options'));

$tpl->assign('msPage', $p);
$tpl->assign('metaId', $meta_id);

// Form for bulk actions
$form = new HTML_QuickFormCustom('Form', 'POST', '?p=' . $p);

$tpl->assign('msg', [
    'addL1' => 'main.php?p=' . $p . '&o=as&meta_id=' . $meta_id,
    'addT' => _('Add'),
]);

// Element we need when we reload the page
$form->addElement('hidden', 'p');
$form->addElement('hidden', 'meta_id');
$form->setDefaults(['p' => $p, 'meta_id' => $meta_id]);

?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }
</script>
<?php
// Styled confirmation modals (clMoreAction in listing.js) replace the
// native confirm()/alert(); messages passed as data-* attributes.
$attrs1 = [
    'onchange' => 'clMoreAction(this);',
    'data-msg-select' => _('Please select one or more items'),
    'data-title-delete' => _('Delete metric'),
    'data-msg-delete' => _('You are about to delete the selected metric(s). This action cannot be undone. Do you want to delete?'),
    'data-label-delete' => _('Delete'),
    'data-title-duplicate' => _('Duplicate metric'),
    'data-msg-duplicate' => _('Do you want to duplicate the selected metric(s)?'),
    'data-label-duplicate' => _('Duplicate'),
    'data-label-cancel' => _('Cancel'),
];
$form->addElement('select', 'o1', null, [null => _('More actions'), 'ds' => _('Delete')], $attrs1);
$form->setDefaults(['o1' => null]);

// Styled confirmation modals (clMoreAction in listing.js) replace the
// native confirm()/alert(); messages passed as data-* attributes.
$attrs2 = [
    'onchange' => 'clMoreAction(this);',
    'data-msg-select' => _('Please select one or more items'),
    'data-title-delete' => _('Delete metric'),
    'data-msg-delete' => _('You are about to delete the selected metric(s). This action cannot be undone. Do you want to delete?'),
    'data-label-delete' => _('Delete'),
    'data-title-duplicate' => _('Duplicate metric'),
    'data-msg-duplicate' => _('Do you want to duplicate the selected metric(s)?'),
    'data-label-duplicate' => _('Duplicate'),
    'data-label-cancel' => _('Cancel'),
];
$form->addElement('select', 'o2', null, [null => _('More actions'), 'ds' => _('Delete')], $attrs2);
$form->setDefaults(['o2' => null]);

$o1 = $form->getElement('o1');
$o1->setValue(null);
$o1->setSelected(null);

$o2 = $form->getElement('o2');
$o2->setValue(null);
$o2->setSelected(null);

$tpl->assign('limit', $limit);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listMetric.ihtml');
