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

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);
$tpl->assign('centreon_path', _CENTREON_PATH_);

// Access level
$lvl_access = ($centreon->user->access->page($p) == 1) ? 'w' : 'r';
$tpl->assign('mode_access', $lvl_access);

$tpl->assign('headerMenu_host', _('Host'));
$tpl->assign('headerMenu_service', _('Services'));
$tpl->assign('headerMenu_metric', _('Metrics'));
$tpl->assign('headerMenu_options', _('Options'));

$tpl->assign('msPage', $p);
// metaService.php leaves $meta_id at false when the request value is rejected,
// and the template interpolates it raw into extraParams: { meta_id: {$metaId} }.
// An empty value there is a JavaScript syntax error that takes the whole
// listing script down, so it is cast to an int rather than left to render.
$tpl->assign('metaId', (int) $meta_id);

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
        // metaService.php reserves 'd' for deleting meta services; deleting a
        // metric is 'ds'. clMoreAction only raises its confirmation modal for
        // the generic 'd'/'m' codes, so the option carries 'd' and the page
        // maps it back to the action its own dispatcher expects.
        document.forms['form'].elements['o'].value = (_i === 'd') ? 'ds' : _i;
    }
</script>
<?php

// Styled, secure confirmation modal (clMoreAction in listing.js) replaces the
// native confirm()/alert(); messages passed as data-* attributes so the handler
// stays locale-independent (keyed on the option value).
foreach (['o1'] as $option) {
    $attrs = [
        'onchange' => 'clMoreAction(this);',
        'data-msg-select' => _('Please select one or more items'),
        'data-title-delete-one' => _('Delete metric'),
        'data-title-delete-many' => _('Delete metrics'),
        'data-msg-delete-one' => _('You are about to delete the <strong>{{ name }}</strong> metric. This action cannot be undone. Do you want to delete it?'),
        'data-msg-delete-many' => _('You are about to delete <strong>{{ count }} metrics.</strong> This action cannot be undone. Do you want to delete them?'),
        'data-label-delete' => _('Delete'),
        'data-label-cancel' => _('Cancel'),
    ];
    $form->addElement('select', $option, null, [null => _('More actions'), 'd' => _('Delete')], $attrs);
    $form->setDefaults([$option => null]);
    $el = $form->getElement($option);
    $el->setValue(null);
    $el->setSelected(null);
}

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listMetric.ihtml');
