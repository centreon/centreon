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

// Database retrieve information
require_once './class/centreonDB.class.php';

$pearDBO = new CentreonDB('centstorage');

$metric = [];
if (($o == 'cs') && $msr_id) {
    $metric1 = $pearDB->fetchAssociative(
        <<<'SQL'
            SELECT * FROM meta_service_relation WHERE msr_id = :msr_id
            SQL,
        QueryParameters::create([QueryParameter::int('msr_id', (int) $msr_id)])
    );
    if ($metric1) {
        $metric1 = array_map('myDecode', $metric1);
        if ($host_id === false || $metric1['host_id'] == $host_id) {
            $metric2 = $pearDBO->fetchAssociative(
                <<<'SQL'
                    SELECT * FROM metrics, index_data
                    WHERE metric_id = :metric_id AND metrics.index_id = index_data.id
                    SQL,
                QueryParameters::create([QueryParameter::int('metric_id', (int) $metric1['metric_id'])])
            );
            if ($metric2) {
                $metric2 = array_map('myDecode', $metric2);
                $metric = array_merge($metric1, $metric2);
                $host_id = (int) $metric1['host_id'];
                $metric['metric_sel'][0] = getMyServiceID($metric['service_description'], $metric['host_id']);
                $metric['metric_sel'][1] = $metric['metric_id'];
            }
        }
    }
}

//
// # Database retrieve information for differents elements list we need on the page
//

// Host comes from DB -> Store in $hosts Array
$hosts
    = [null => null] + $acl->getHostAclConf(
        null,
        'broker',
        ['fields' => ['host.host_id', 'host.host_name'], 'keys' => ['host_id'], 'get_row' => 'host_name', 'order' => ['host.host_name']]
    );

$services1 = [null => null];
$services2 = [null => null];
if ($host_id !== false) {
    $services
        = [null => null] + $acl->getHostServiceAclConf(
            $host_id,
            'broker',
            ['fields' => ['s.service_id', 's.service_description'], 'keys' => ['service_id'], 'get_row' => 'service_description', 'order' => ['service_description']]
        );

    $hostName = (string) getMyHostName($host_id);
    foreach ($services as $key => $value) {
        foreach (
            $pearDBO->fetchAllAssociative(
                <<<'SQL'
                    SELECT DISTINCT metric_name, metric_id, unit_name
                    FROM metrics m, index_data i
                    WHERE i.host_name = :host_name
                    AND i.service_description = :service_description
                    AND i.id = m.index_id
                    ORDER BY metric_name, unit_name
                    SQL,
                QueryParameters::create([
                    QueryParameter::string('host_name', $hostName),
                    QueryParameter::string('service_description', (string) $value),
                ])
            ) as $metricSV
        ) {
            $services1[$key] = $value;
            $metricSV['metric_name'] = str_replace('#S#', '/', $metricSV['metric_name']);
            $metricSV['metric_name'] = str_replace('#BS#', '\\', $metricSV['metric_name']);
            $services2[$key][$metricSV['metric_id']] = $metricSV['metric_name'] . '  (' . $metricSV['unit_name'] . ')';
        }
    }
}

$debug = 0;
$attrsTextI = ['size' => '3'];
$attrsText = ['size' => '30'];
$attrsTextarea = ['rows' => '5', 'cols' => '40'];

// Form begin
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);
if ($o == 'as') {
    $form->addElement('header', 'title', _('Add a Meta Service indicator'));
} elseif ($o == 'cs') {
    $form->addElement('header', 'title', _('Modify a Meta Service indicator'));
}

// Indicator basic information
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);
$formMsrId = $form->addElement('hidden', 'msr_id');
$formMsrId->setValue($msr_id);
$formMetaId = $form->addElement('hidden', 'meta_id');
$formMetaId->setValue($meta_id);
$formMetricId = $form->addElement('hidden', 'metric_id');
$formMetricId->setValue($metric_id);

$hn = $form->addElement('select', 'host_id', _('Host'), $hosts, ['onChange' => 'this.form.submit()']);
$sel = $form->addElement('hierselect', 'metric_sel', _('Service'));
$sel->setOptions([$services1, $services2]);

$tab = [];
$tab[] = $form->createElement('radio', 'activate', null, _('Enabled'), '1');
$tab[] = $form->createElement('radio', 'activate', null, _('Disabled'), '0');
$form->addGroup($tab, 'activate', _('Status'), '&nbsp;');
$form->setDefaults(['activate' => '1']);
$form->addElement('textarea', 'msr_comment', _('Comments'), $attrsTextarea);

$form->addRule('host_id', _('Compulsory Field'), 'required');

function checkMetric()
{
    global $form;

    $tab = $form->getSubmitValue('metric_sel');
    if (isset($tab[0]) & isset($tab[1])) {
        return 1;
    }

    return 0;
}

$form->registerRule('checkMetric', 'callback', 'checkMetric');
$form->addRule('metric_sel', _('Compulsory Field'), 'checkMetric');

// Just watch

if ($o == 'cs') {
    $subC = $form->addElement('submit', 'submitC', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
    $form->setDefaults($metric);
} elseif ($o == 'as') {
    $subA = $form->addElement('submit', 'submitA', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
}

$valid = false;
if (
    ((isset($_POST['submitA']) && $_POST['submitA']) || (isset($_POST['submitC']) && $_POST['submitC']))
    && $form->validate()
) {
    $msrObj = $form->getElement('msr_id');
    if ($form->getSubmitValue('submitA')) {
        $msrObj->setValue(insertMetric($meta_id));
    } elseif ($form->getSubmitValue('submitC')) {
        updateMetric($msrObj->getValue());
    }
    $valid = true;
}

if ($valid) {
    require_once $path . 'listMetric.php';
} else {

    // Smarty template initialization
    $tpl = SmartyBC::createSmartyTemplate($path);

    // Needed to include the shared cl-/cf- framework translations (clI18n.ihtml).
    $tpl->assign('centreon_path', _CENTREON_PATH_);

    // Apply a template definition
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
    $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $form->accept($renderer);

    $tpl->assign('form', $renderer->toArray());
    $tpl->assign('o', $o);
    $tpl->assign('valid', $valid);
    // In edit mode (o=cs) the request carries msr_id, not meta_id; recover the
    // meta service id from the loaded relation so Back returns to the right list.
    $backMetaId = (int) ($meta_id ?: ($metric['meta_id'] ?? 0));
    $tpl->assign('backUrl', 'main.get.php?p=' . $p . '&o=ci&meta_id=' . $backMetaId);
    $tpl->display('metric.ihtml');
}
