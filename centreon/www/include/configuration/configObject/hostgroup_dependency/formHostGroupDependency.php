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
use Adaptation\Database\Connection\ValueObject\QueryParameter;

if (! isset($centreon)) {
    exit();
}

//
// # Database retrieve information for Dependency
//
$dep = [];
$initialValues = [];
if (($o == MODIFY_DEPENDENCY || $o == WATCH_DEPENDENCY) && $depId) {
    $qb = $pearDB->createQueryBuilder()
        ->select('*')
        ->from('dependency')
        ->where('dep_id = :depId')
        ->limit(1)
        ->getQuery();
    $params = QueryParameters::create([
        QueryParameter::int('depId', (int) $depId),
    ]);
    $result = $pearDB->fetchAssociative($qb, $params);

    if ($result !== false) {
        // Set base value
        $dep = array_map('myDecode', $result);

        // Set Notification Failure Criteria
        $dep['notification_failure_criteria'] = explode(',', $dep['notification_failure_criteria']);
        foreach ($dep['notification_failure_criteria'] as $key => $value) {
            $dep['notification_failure_criteria'][trim($value)] = 1;
        }

        // Set Execution Failure Criteria
        $dep['execution_failure_criteria'] = explode(',', $dep['execution_failure_criteria']);
        foreach ($dep['execution_failure_criteria'] as $key => $value) {
            $dep['execution_failure_criteria'][trim($value)] = 1;
        }
    } else {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Dependency not found',
            ['depId' => $depId]
        );
    }
}

// Var information to format the element
$attrsText = ['size' => '30'];
$attrsText2 = ['size' => '10'];
$attrsAdvSelect = ['style' => 'width: 300px; height: 150px;'];
$attrsTextarea = ['rows' => '3', 'cols' => '30'];
$eTemplate = '<table><tr><td><div class="ams">{label_2}</div>{unselected}</td><td align="center">{add}<br /><br />'
    . '<br />{remove}</td><td><div class="ams">{label_3}</div>{selected}</td></tr></table>';

$route = './include/common/webServices/rest/internal.php?object=centreon_configuration_hostgroup&action=list';
$attrHostgroups = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $route, 'multiple' => true, 'linkedObject' => 'centreonHostgroups'];

// Form begin
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);
if ($o == ADD_DEPENDENCY) {
    $form->addElement('header', 'title', _('Add a Dependency'));
} elseif ($o == MODIFY_DEPENDENCY) {
    $form->addElement('header', 'title', _('Modify a Dependency'));
} elseif ($o == WATCH_DEPENDENCY) {
    $form->addElement('header', 'title', _('View a Dependency'));
}

// Dependency basic information

$form->addElement('header', 'information', _('Information'));
$form->addElement('text', 'dep_name', _('Name'), $attrsText);
$form->addElement('text', 'dep_description', _('Description'), $attrsText);
$tab = [];
$tab[] = $form->createElement('radio', 'inherits_parent', null, _('Yes'), '1');
$tab[] = $form->createElement('radio', 'inherits_parent', null, _('No'), '0');
$form->addGroup($tab, 'inherits_parent', _('Parent relationship'), '&nbsp;');
$form->setDefaults(['inherits_parent' => '1']);

$tab = [];
$tab[] = $form->createElement(
    'checkbox',
    'o',
    '&nbsp;',
    _('Ok/Up'),
    ['id' => 'nUp', 'onClick' => 'applyNotificationRules(this);']
);
$tab[] = $form->createElement(
    'checkbox',
    'd',
    '&nbsp;',
    _('Down'),
    ['id' => 'nDown', 'onClick' => 'applyNotificationRules(this);']
);
$tab[] = $form->createElement(
    'checkbox',
    'u',
    '&nbsp;',
    _('Unreachable'),
    ['id' => 'nUnreachable', 'onClick' => 'applyNotificationRules(this);']
);
$tab[] = $form->createElement(
    'checkbox',
    'p',
    '&nbsp;',
    _('Pending'),
    ['id' => 'nPending', 'onClick' => 'applyNotificationRules(this);']
);
$tab[] = $form->createElement(
    'checkbox',
    'n',
    '&nbsp;',
    _('None'),
    ['id' => 'nNone', 'onClick' => 'applyNotificationRules(this);']
);
$form->addGroup($tab, 'notification_failure_criteria', _('Notification Failure Criteria'), '&nbsp;&nbsp;');

$tab = [];
$tab[] = $form->createElement(
    'checkbox',
    'o',
    '&nbsp;',
    _('Ok/Up'),
    ['id' => 'eUp', 'onClick' => 'applyExecutionRules(this);']
);
$tab[] = $form->createElement(
    'checkbox',
    'd',
    '&nbsp;',
    _('Down'),
    ['id' => 'eDown', 'onClick' => 'applyExecutionRules(this);']
);
$tab[] = $form->createElement(
    'checkbox',
    'u',
    '&nbsp;',
    _('Unreachable'),
    ['id' => 'eUnreachable', 'onClick' => 'applyExecutionRules(this);']
);
$tab[] = $form->createElement(
    'checkbox',
    'p',
    '&nbsp;',
    _('Pending'),
    ['id' => 'ePending', 'onClick' => 'applyExecutionRules(this);']
);
$tab[] = $form->createElement(
    'checkbox',
    'n',
    '&nbsp;',
    _('None'),
    ['id' => 'eNone', 'onClick' => 'applyExecutionRules(this);']
);
$form->addGroup($tab, 'execution_failure_criteria', _('Execution Failure Criteria'), '&nbsp;&nbsp;');

$route = './include/common/webServices/rest/internal.php?object=centreon_configuration_hostgroup'
    . '&action=defaultValues&target=dependency&field=dep_hgParents&id=' . $depId;
$attrHostgroup1 = array_merge(
    $attrHostgroups,
    ['defaultDatasetRoute' => $route]
);
$form->addElement('select2', 'dep_hgParents', _('Host Groups Name'), [], $attrHostgroup1);

$route = './include/common/webServices/rest/internal.php?object=centreon_configuration_hostgroup'
    . '&action=defaultValues&target=dependency&field=dep_hgChilds&id=' . $depId;
$attrHostgroup2 = array_merge(
    $attrHostgroups,
    ['defaultDatasetRoute' => $route]
);
$form->addElement('select2', 'dep_hgChilds', _('Dependent Host Groups Name'), [], $attrHostgroup2);

$form->addElement('textarea', 'dep_comment', _('Comments'), $attrsTextarea);

$form->addElement('hidden', 'dep_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

$init = $form->addElement('hidden', 'initialValues');
$init->setValue(serialize($initialValues));

// Form Rules
$form->applyFilter('__ALL__', 'myTrim');
$form->registerRule('sanitize', 'callback', 'isNotEmptyAfterStringSanitize');
$form->addRule('dep_name', _('Compulsory Name'), 'required');
$form->addRule('dep_name', _('Unauthorized value'), 'sanitize');
$form->addRule('dep_description', _('Required Field'), 'required');
$form->addRule('dep_description', _('Unauthorized value'), 'sanitize');
$form->addRule('dep_hgParents', _('Required Field'), 'required');
$form->addRule('dep_hgChilds', _('Required Field'), 'required');
$form->addRule('execution_failure_criteria', _('Required Field'), 'required');
$form->addRule('notification_failure_criteria', _('Required Field'), 'required');

$form->registerRule('cycle', 'callback', 'testHostGroupDependencyCycle');
$form->addRule('dep_hgChilds', _('Circular Definition'), 'cycle');
$form->registerRule('exist', 'callback', 'testHostGroupDependencyExistence');
$form->addRule('dep_name', _('Name is already in use'), 'exist');
$form->setRequiredNote("<font style='color: red;'>*</font>&nbsp;" . _('Required fields'));

if ($o === ADD_DEPENDENCY || $o === MODIFY_DEPENDENCY) {
    $form->addFormRule('validateParentChildAreNotCircular');
}

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// Just watch a Dependency information
if ($o == WATCH_DEPENDENCY) {
    if ($centreon->user->access->page($p) != 2) {
        $form->addElement(
            'button',
            'change',
            _('Modify'),
            ['onClick' => "javascript:window.location.href='?p=" . $p . '&o=c&dep_id=' . $depId . "'"]
        );
    }
    $form->setDefaults($dep);
    $form->freeze();
} elseif ($o == MODIFY_DEPENDENCY) {
    $subC = $form->addElement('submit', 'submitC', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
    $form->setDefaults($dep);
} elseif ($o == ADD_DEPENDENCY) {
    $subA = $form->addElement('submit', 'submitA', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
    $form->setDefaults(['inherits_parent', '0']);
}

$tpl->assign(
    'helpattr',
    'TITLE, "' . _('Help') . '", CLOSEBTN, true, FIX, [this, 0, 5], BGCOLOR, "#ffff99", BORDERCOLOR, "orange", '
    . 'TITLEFONTCOLOR, "black", TITLEBGCOLOR, "orange", CLOSEBTNCOLORS, ["","black", "white", "red"], WIDTH, -300, '
    . 'SHADOW, true, TEXTALIGN, "justify"'
);
// prepare help texts
$helptext = '';
include_once 'include/configuration/configObject/host_dependency/help.php';
foreach ($help as $key => $text) {
    $helptext .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign('helptext', $helptext);

$valid = false;
if ($form->validate()) {
    $depObj = $form->getElement('dep_id');
    if ($form->getSubmitValue('submitA')) {
        $depObj->setValue(insertHostGroupDependencyInDB());
    } elseif ($form->getSubmitValue('submitC')) {
        updateHostGroupDependencyInDB($depObj->getValue('dep_id'));
    }
    $o = null;
    $valid = true;
}

if ($valid) {
    require_once 'listHostGroupDependency.php';
} else {
    // Apply a template definition
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl, true);
    $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $form->accept($renderer);
    $tpl->assign('form', $renderer->toArray());
    $tpl->assign('o', $o);
    $tpl->display('formHostGroupDependency.ihtml');
}
?>
<script type="text/javascript">
    function applyNotificationRules(object) {
        if (object.id === "nNone" && object.checked) {
            document.getElementById('nUp').checked = false;
            document.getElementById('nDown').checked = false;
            document.getElementById('nUnreachable').checked = false;
            document.getElementById('nPending').checked = false;
        }
        else {
            document.getElementById('nNone').checked = false;
        }
    }
    function applyExecutionRules(object) {
        if (object.id === "eNone" && object.checked) {
            document.getElementById('eUp').checked = false;
            document.getElementById('eDown').checked = false;
            document.getElementById('eUnreachable').checked = false;
            document.getElementById('ePending').checked = false;
        }
        else {
            document.getElementById('eNone').checked = false;
        }
    }
</script>
