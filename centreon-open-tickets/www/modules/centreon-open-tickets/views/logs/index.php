<?php
/*
 * Copyright 2016-2019 Centreon (http://www.centreon.com/)
 *
 * Centreon is a full-fledged industry-strength solution that meets
 * the needs in IT infrastructure and application monitoring for
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once './modules/centreon-open-tickets/centreon-open-tickets.conf.php';

// Smarty template initialization
$path = "./modules/centreon-open-tickets/views/logs/templates/";
$tpl = SmartyBC::createSmartyTemplate($path);

/*
 * Form begin
 */
$form = new HTML_QuickFormCustom('FormTicketLogs', 'get', "?p=".$p);

$periods = [""=>"", "10800" => _("Last 3 Hours"), "21600" => _("Last 6 Hours"), "43200" => _("Last 12 Hours"), "86400" => _("Last 24 Hours"), "172800" => _("Last 2 Days"), "302400" => _("Last 4 Days"), "604800" => _("Last 7 Days"), "1209600" => _("Last 14 Days"), "2419200" => _("Last 28 Days"), "2592000" => _("Last 30 Days"), "2678400" => _("Last 31 Days"), "5184000" => _("Last 2 Months"), "10368000" => _("Last 4 Months"), "15552000" => _("Last 6 Months"), "31104000" => _("Last Year")];

$form->addElement(
    'select',
    'period',
    _("Log Period"),
    $periods
);
$form->addElement(
    'text',
    'StartDate',
    '',
    ["id" => "StartDate", "class" => "datepicker", "size"=>8]
);
$form->addElement(
    'text',
    'StartTime',
    '',
    ["id" => "StartTime", "class" => "timepicker", "size"=>5]
);
$form->addElement(
    'text',
    'EndDate',
    '',
    ["id" => "EndDate", "class" => "datepicker", "size"=>8]
);
$form->addElement(
    'text',
    'EndTime',
    '',
    ["id" => "EndTime", "class" => "timepicker", "size"=>5]
);
$form->addElement(
    'text',
    'subject',
    _("Subject"),
    ["id" => "subject", "style" => "width: 203px;", "size" => 15, "value" => '']
);
$form->addElement(
    'text',
    'ticket_id',
    _("Ticket ID"),
    ["id" => "ticket_id", "style" => "width: 203px;", "size" => 15, "value" => '']
);

$form->addElement(
    'submit',
    'graph',
    _("Apply"),
    ["onclick" => "return applyForm();", "class" => "btc bt_success"]
);

$attrHosts = ['datasourceOrigin' => 'ajax', 'allowClear' => false, 'availableDatasetRoute' => './include/common/webServices/rest/internal.php' .
    '?object=centreon_configuration_host&action=list', 'multiple' => true];
$attrHost1 = array_merge($attrHosts);
$form->addElement(
    'select2',
    'host_filter',
    _("Hosts"),
    [],
    $attrHost1
);

$attrService = ['datasourceOrigin' => 'ajax', 'allowClear' => false, 'availableDatasetRoute' => './include/common/webServices/rest/internal.php' .
    '?object=centreon_configuration_service&action=list', 'multiple' => true];
$attrService1 = array_merge($attrService);
$form->addElement(
    'select2',
    'service_filter',
    _("Services"),
    [],
    $attrService1
);

$form->setDefaults(["period" => '10800']);

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());

$tpl->display("viewLog.ihtml");
