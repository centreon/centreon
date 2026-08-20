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

include_once './class/centreonUtils.class.php';

$tpl = SmartyBC::createSmartyTemplate($path);

// Needed to include the shared cl-/cf- framework translations (clI18n.ihtml).
$tpl->assign('centreon_path', _CENTREON_PATH_);

$lvl_access = ($centreon->user->access->page($p) == 1) ? 'w' : 'r';
$tpl->assign('mode_access', $lvl_access);

$tpl->assign('headerMenu_name', _('Name'));
$tpl->assign('headerMenu_oid', _('OID'));
$tpl->assign('headerMenu_status', _('Status'));
$tpl->assign('headerMenu_vendor', _('Vendor'));
$tpl->assign('headerMenu_output', _('Output Message'));
$tpl->assign('headerMenu_options', _('Options'));

$tpl->assign('trapsPage', $p);

$search = $centreon->historySearch[$url]['search'] ?? '';
$tpl->assign('searchT', $search);

$defaultLimit = (int) ($centreon->optGen['maxViewConfiguration'] ?? 30) ?: 30;
$tpl->assign('defaultLimit', $defaultLimit);

$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);
$attrBtnSuccess = ['class' => 'btc bt_success', 'onClick' => "window.history.replaceState('', '', '?p=" . $p . "');"];
$form->addElement('submit', 'Search', _('Search'), $attrBtnSuccess);

/**
 * Advanced filters: static select2 elements. Their options are rendered
 * client-side from the element configuration, and the value the user picked is
 * restored by CentreonListing from its own session state -- so the filters need
 * neither an initial dataset nor a custom clear control. The element label is
 * what select2 shows as placeholder while the filter is empty.
 *
 * The one case that does need a dataset is a value coming back from a POST
 * (the filters sit inside the listing form, so a bulk action resubmits them):
 * QuickForm wraps a submitted scalar in a list, and select2 would then render
 * the option with its array index as label ("0") instead of the status name.
 *
 * @param array<int|string, string> $options
 *
 * @return array<string, array<string, int|string>>
 */
$submittedFilterDataset = static function (string $name, array $options): array {
    $submitted = filter_var($_POST[$name] ?? null, FILTER_VALIDATE_INT);

    return ($submitted !== false && isset($options[$submitted]))
        ? ['defaultDataset' => [$options[$submitted] => $submitted]]
        : [];
};

$tabStatusFilter = [1 => _('OK'), 2 => _('Warning'), 3 => _('Critical'), 4 => _('Unknown'), 5 => _('Pending')];
$form->addElement(
    'select2',
    'status',
    _('Select'),
    $tabStatusFilter,
    $submittedFilterDataset('status', $tabStatusFilter)
);

$vendors = [];
foreach ($pearDB->fetchAllAssociative('SELECT id, name FROM traps_vendor ORDER BY name') as $vendor) {
    $vendors[(int) $vendor['id']] = $vendor['name'];
}
$form->addElement(
    'select2',
    'vendor',
    _('Select'),
    $vendors,
    $submittedFilterDataset('vendor', $vendors)
);

$tpl->assign('msg', ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add')]);

?>
<script type="text/javascript">
    function setO(_i) { document.forms['form'].elements['o'].value = _i; }
</script>
<?php

foreach (['o1'] as $option) {
    // Styled, secure confirmation modal (clMoreAction in listing.js) replaces
    // the native confirm()/alert(); messages passed as data-* attributes so the
    // handler stays locale-independent (keyed on the option value).
    $attrs = [
        'onchange' => 'clMoreAction(this);',
        'data-msg-select' => _('Please select one or more items'),
        'data-title-delete-one' => _('Delete trap'),
        'data-title-delete-many' => _('Delete traps'),
        'data-msg-delete-one' => _('You are about to delete the <strong>{{ name }}</strong> trap. This action cannot be undone. Do you want to delete it?'),
        'data-msg-delete-many' => _('You are about to delete <strong>{{ count }} traps.</strong> This action cannot be undone. Do you want to delete them?'),
        'data-label-delete' => _('Delete'),
        'data-title-duplicate-one' => _('Duplicate trap'),
        'data-title-duplicate-many' => _('Duplicate traps'),
        'data-msg-duplicate-one' => _('You are about to duplicate the <strong>{{ name }}</strong> trap. Do you want to duplicate it?'),
        'data-msg-duplicate-many' => _('You are about to duplicate <strong>{{ count }} traps.</strong> Do you want to duplicate them?'),
        'data-label-duplicate' => _('Duplicate'),
        'data-label-cancel' => _('Cancel'),
    ];
    $form->addElement('select', $option, null,
        [null => _('More actions'), 'm' => _('Duplicate'), 'd' => _('Delete')], $attrs);
    $form->setDefaults([$option => null]);
    $el = $form->getElement($option);
    $el->setValue(null);
    $el->setSelected(null);
}

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listTraps.ihtml');
