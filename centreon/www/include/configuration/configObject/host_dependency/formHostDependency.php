<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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
 */

declare(strict_types=1);

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\ValueObjectException;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Core\Common\Domain\Exception\RepositoryException;

if (!isset($centreon)) {
    exit();
}


$dep = [];
$childServices = [];
$initialValues = [];

// Fetch existing dependency for modify or view
if (in_array($o, [MODIFY_DEPENDENCY, WATCH_DEPENDENCY], true) && $dep_id) {
    try {
        $queryBuilder = $pearDB->createQueryBuilder();
        $queryBuilder->select('*')
           ->from('dependency', 'dep')
           ->where('dep.dep_id = :depId')
           ->limit(1);

        $sql = $queryBuilder->getQuery();

        $result = $pearDB->fetchAssociative($sql, QueryParameters::create([QueryParameter::int('depId', $dep_id)]));
        if ($result === false) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Dependency not found',
                ['depId' => $dep_id]
            );
            $msg = new CentreonMsg();
            $msg->setImage("./img/icons/warning.png");
            $msg->setTextStyle("bold");
            $msg->setText('Dependency not found');
        }

        # Set base value
        if ($result !== false) {
            $dep = array_map('myDecode', $result);
        } else {
            $dep = [];
        }

        # Set Notification Failure Criteria
        $dep["notification_failure_criteria"] = explode(',', $dep["notification_failure_criteria"]);
        foreach ($dep["notification_failure_criteria"] as $key => $value) {
            $dep["notification_failure_criteria"][trim($value)] = 1;
        }

        # Set Execution Failure Criteria
        $dep["execution_failure_criteria"] = explode(',', $dep["execution_failure_criteria"]);
        foreach ($dep["execution_failure_criteria"] as $key => $value) {
            $dep["execution_failure_criteria"][trim($value)] = 1;
        }
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error retrieving dependency: ' . $exception->getMessage(),
            ['depId' => $dep_id],
            $exception
        );
        $msg = new CentreonMsg();
        $msg->setImage("./img/icons/warning.png");
        $msg->setTextStyle("bold");
        $msg->setText('Error loading dependency ' . $dep_id);
        $dep = [];
    }
}

// Form attributes
$attrsText = ['size' => '30'];
$attrsText2 = ['size' => '10'];
$attrsAdvSelect = ['style' => 'width: 300px; height: 150px;'];
$attrsTextarea = ['rows' => '3', 'cols' => '30'];
$eTemplate = '<table><tr><td><div class="ams">{label_2}</div>{unselected}</td><td align="center">{add}<br /><br />' .
    '<br />{remove}</td><td><div class="ams">{label_3}</div>{selected}</td></tr></table>';

// AJAX datasource routes
$hostRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_host&action=list';
$serviceRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_service&action=list';

$attrHosts = [
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute'=> $hostRoute,
    'multiple' => true,
    'linkedObject' => 'centreonHost'
];
$attrServices = [
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute'=> $serviceRoute,
    'multiple' => true,
    'linkedObject' => 'centreonService'
];

// Build form
$form = new HTML_QuickFormCustom('Form', 'post', "?p=$p");

// Header
switch ($o) {
    case ADD_DEPENDENCY:
        $form->addElement('header', 'title', _('Add a Dependency'));
        break;
    case MODIFY_DEPENDENCY:
        $form->addElement('header', 'title', _('Modify a Dependency'));
        break;
    case WATCH_DEPENDENCY:
        $form->addElement('header', 'title', _('View a Dependency'));
        break;
}

// Information section
$form->addElement('header', 'information', _('Information'));
$form->addElement('text', 'dep_name', _('Name'), $attrsText);
$form->addElement('text', 'dep_description', _('Description'), $attrsText);

// Parent relationship radios
$radios = [
    $form->createElement('radio', 'inherits_parent', null, _('Yes'), '1'),
    $form->createElement('radio', 'inherits_parent', null, _('No'), '0'),
];
$form->addGroup($radios, 'inherits_parent', _('Parent relationship'), '&nbsp;');
$form->setDefaults(['inherits_parent' => '1']);

// Notification criteria checkboxes
$notifIds = [
    'o' => ['nUp','Ok/Up'],
    'd' => ['nDown','Down'],
    'u' => ['nUnreachable','Unreachable'],
    'p' => ['nPending','Pending'],
    'n' => ['nNone','None']
];
$notif = [];
foreach ($notifIds as $key => $values) {
    $notif[] = $form->createElement(
        'checkbox', $key, '&nbsp;', _($values[1]),
        ['id' => $values[0],'onClick' => 'applyNotificationRules(this);']
    );
}
$form->addGroup($notif, 'notification_failure_criteria', _('Notification Failure Criteria'), '&nbsp;&nbsp;');

// Execution criteria checkboxes
$execIds = [
    'o' => ['eUp','Up'],
    'd' => ['eDown','Down'],
    'u' => ['eUnreachable','Unreachable'],
    'p' => ['ePending','Pending'],
    'n' => ['eNone','None']
];
$exec = [];
foreach ($execIds as $key => $values) {
    $exec[] = $form->createElement(
        'checkbox', $key, '&nbsp;', _($values[1]),
        ['id' => $values[0],'onClick' => 'applyExecutionRules(this);']
    );
}
$form->addGroup($exec, 'execution_failure_criteria', _('Execution Failure Criteria'), '&nbsp;&nbsp;');

// Hosts and services multi-selects with default values
$hostDefaultsRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_host' .
    '&action=defaultValues&target=dependency&field=dep_hostParents&id=' . $dep_id;
$form->addElement(
    'select2', 'dep_hostParents', _('Host Names'), [],
    array_merge($attrHosts, ['defaultDatasetRoute' => $hostDefaultsRoute])
);

$childDefaultsRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_host&action=defaultValues&target=dependency&field=dep_hostChilds&id=' . $dep_id;
$form->addElement(
    'select2', 'dep_hostChilds', _('Dependent Host Names'), [],
    array_merge($attrHosts, ['defaultDatasetRoute' => $childDefaultsRoute])
);

$serviceDefaultsRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_service&action=defaultValues&target=dependency&field=dep_hSvChi&id=' . $dep_id;
$form->addElement(
    'select2', 'dep_hSvChi', _('Dependent Services'), [],
    array_merge($attrServices, ['defaultDatasetRoute' => $serviceDefaultsRoute])
);

$form->addElement('textarea', 'dep_comment', _('Comments'), $attrsTextarea);
$form->addElement('hidden', 'dep_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

$init = $form->addElement('hidden', 'initialValues');
$init->setValue(serialize($initialValues));

// Validation rules
$form->applyFilter('__ALL__', 'myTrim');
$form->registerRule('sanitize', 'callback', 'isNotEmptyAfterStringSanitize');
$form->addRule('dep_name', _('Compulsory Name'), 'required');
$form->addRule('dep_name', _('Unauthorized value'), 'sanitize');
$form->addRule('dep_description', _('Required Field'), 'required');
$form->addRule('dep_description', _('Unauthorized value'), 'sanitize');
$form->addRule('dep_hostParents', _('Required Field'), 'required');

$form->registerRule('cycle', 'callback', 'testHostDependencyCycle');
$form->addRule('dep_hostChilds', _('Circular Definition'), 'cycle');
$form->registerRule('exist', 'callback', 'testHostDependencyExistence');
$form->addRule('dep_name', _('Name is already in use'), 'exist');
$form->setRequiredNote("<font style='color: red;'>*</font>&nbsp;" . _('Required fields'));
$form->addRule('execution_failure_criteria', _('Required Field'), 'required');
$form->addRule('notification_failure_criteria', _('Required Field'), 'required');

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

$tpl->assign(
    "helpattr",
    'TITLE, "' . _("Help") . '", CLOSEBTN, true, FIX, [this, 0, 5], BGCOLOR, "#ffff99", BORDERCOLOR, ' .
    '"orange", TITLEFONTCOLOR, "black", TITLEBGCOLOR, "orange", CLOSEBTNCOLORS, ["","black", "white", "red"], ' .
    'WIDTH, -300, SHADOW, true, TEXTALIGN, "justify"'
);
# prepare help texts
$helptext = "";
include_once("help.php");
foreach ($help as $key => $text) {
    $helptext .= "<span style='display:none' id='help:$key'>$text</span>\n";
}
$tpl->assign('helptext', $helptext);

// View/Modify/Add buttons and defaults
if ($o == WATCH_DEPENDENCY) {
    if ($centreon->user->access->page($p) != 2) {
        $form->addElement(
            'button',
            'change',
            _('Modify'),
            ['onClick' => "javascript:window.location.href='?p=$p&o=c&dep_id=$dep_id'"]
        );
    }
    $form->setDefaults($dep);
    $form->freeze();
} elseif ($o == MODIFY_DEPENDENCY) {
    $form->addElement('submit', 'submitC', _('Save'), ['class' => 'btc bt_success']);
    $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
    $form->setDefaults($dep);
} elseif ($o == ADD_DEPENDENCY) {
    $form->addElement('submit', 'submitA', _('Save'), ['class' => 'btc bt_success']);
    $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
    $form->setDefaults(['inherits_parent' => '0']);
}
$tpl->assign("nagios", $oreon->user->get_version());

// Process submission
if ($form->validate()) {
    try {
        $depObj = $form->getElement('dep_id');
        if ($form->getSubmitValue('submitA')) {
            $depObj->setValue(insertHostDependencyInDB());
        } elseif ($form->getSubmitValue('submitC')) {
            updateHostDependencyInDB((int) $depObj->getValue());
        }
        require_once('listHostDependency.php');
        return;
    } catch (CentreonException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error processing host dependancy form: ' . $exception->getMessage(),
            ['depId' => (int) $depObj->getValue()],
            $exception
        );
        $msg = new CentreonMsg();
        $msg->setImage("./img/icons/warning.png");
        $msg->setTextStyle("bold");
        $msg->setText('Error processing host dependancy form');
        $o = null;
    }
}

// Render form
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl, true);
$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->assign('o', $o);
$tpl->display('formHostDependency.ihtml');

?>
<script type="text/javascript">
    function applyNotificationRules(object) {
        if (object.id == "nNone" && object.checked) {
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
