<?php
/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

if (!isset($centreon)) {
    exit();
}

$myinputsGet = [
    'host_search' => isset($_GET['host_search']) ? \HtmlAnalyzer::sanitizeAndRemoveTags($_GET['host_search']) : null,
    'search' => isset($_GET['search']) ? \HtmlAnalyzer::sanitizeAndRemoveTags($_GET['search']) : null,
    'output_search' => isset($_GET['output_search'])
        ? \HtmlAnalyzer::sanitizeAndRemoveTags($_GET['output_search'])
        : null,
    'hg' => isset($_GET['hg']) ? \HtmlAnalyzer::sanitizeAndRemoveTags($_GET['hg']) : null,
    'sg' => isset($_GET['sg']) ? \HtmlAnalyzer::sanitizeAndRemoveTags($_GET['sg']) : null,
    'monitoring_default_hostgroups' => isset($_GET['monitoring_default_hostgroups'])
         ? \HtmlAnalyzer::sanitizeAndRemoveTags($_GET['monitoring_default_hostgroups'])
         : null,
    'monitoring_default_servicegroups' => isset($_GET['monitoring_default_servicegroups'])
        ? \HtmlAnalyzer::sanitizeAndRemoveTags($_GET['monitoring_default_servicegroups'])
        : null,
    'hostgroup' => isset($_GET['hostgroup']) ? \HtmlAnalyzer::sanitizeAndRemoveTags($_GET['hostgroup']) : null,
    'sort_type' => filter_input(INPUT_GET, 'num', FILTER_VALIDATE_INT),
    'host_name' => isset($_GET['host_name']) ? \HtmlAnalyzer::sanitizeAndRemoveTags($_GET['host_name']) : null,
    'global_sort_type' => isset($_GET['global_sort_type'])
        ? \HtmlAnalyzer::sanitizeAndRemoveTags($_GET['global_sort_type'])
        : null,
    'global_sort_order' => isset($_GET['global_sort_order'])
        ? \HtmlAnalyzer::sanitizeAndRemoveTags($_GET['global_sort_order'])
        : null,
    'order' => isset($_GET['order']) ? \HtmlAnalyzer::sanitizeAndRemoveTags($_GET['order']) : null,
    'monitoring_service_status_filter' => isset($_GET['monitoring_service_status_filter'])
        ? \HtmlAnalyzer::sanitizeAndRemoveTags($_GET['lang'])
        : null,
    'monitoring_service_status' => isset($_GET['monitoring_service_status'])
        ? \HtmlAnalyzer::sanitizeAndRemoveTags($_GET['monitoring_service_status'])
        : null,
    'criticality_id' => filter_input(INPUT_GET, 'criticality_id', FILTER_VALIDATE_INT),
    'reset_filter' => filter_input(INPUT_GET, 'reset_filter', FILTER_VALIDATE_INT),
];

$myinputsPost = [
    'host_search' => isset($_POST['host_search']) ? \HtmlAnalyzer::sanitizeAndRemoveTags($_POST['host_search']) : null,
    'search' => isset($_POST['search']) ? \HtmlAnalyzer::sanitizeAndRemoveTags($_POST['search']) : null,
    'output_search' => isset($_POST['output_search'])
        ? \HtmlAnalyzer::sanitizeAndRemoveTags($_POST['output_search'])
        : null,
    'hg' => isset($_POST['hg']) ? \HtmlAnalyzer::sanitizeAndRemoveTags($_POST['hg']) : null,
    'sg' => isset($_POST['sg']) ? \HtmlAnalyzer::sanitizeAndRemoveTags($_POST['sg']) : null,
    'monitoring_default_hostgroups' => isset($_POST['monitoring_default_hostgroups'])
         ? \HtmlAnalyzer::sanitizeAndRemoveTags($_POST['monitoring_default_hostgroups'])
         : null,
    'monitoring_default_servicegroups' => isset($_POST['monitoring_default_servicegroups'])
        ? \HtmlAnalyzer::sanitizeAndRemoveTags($_POST['monitoring_default_servicegroups'])
        : null,
    'hostgroup' => isset($_POST['hostgroup']) ? \HtmlAnalyzer::sanitizeAndRemoveTags($_POST['hostgroup']) : null,
    'sort_type' => filter_input(INPUT_POST, 'num', FILTER_VALIDATE_INT),
    'host_name' => isset($_POST['host_name']) ? \HtmlAnalyzer::sanitizeAndRemoveTags($_POST['host_name']) : null,
    'global_sort_type' => isset($_POST['global_sort_type'])
        ? \HtmlAnalyzer::sanitizeAndRemoveTags($_POST['global_sort_type'])
        : null,
    'global_sort_order' => isset($_POST['global_sort_order'])
        ? \HtmlAnalyzer::sanitizeAndRemoveTags($_POST['global_sort_order'])
        : null,
    'order' => isset($_POST['order']) ? \HtmlAnalyzer::sanitizeAndRemoveTags($_POST['order']) : null,
    'monitoring_service_status_filter' => isset($_POST['monitoring_service_status_filter'])
        ? \HtmlAnalyzer::sanitizeAndRemoveTags($_POST['lang'])
        : null,
    'monitoring_service_status' => isset($_POST['monitoring_service_status'])
        ? \HtmlAnalyzer::sanitizeAndRemoveTags($_POST['monitoring_service_status'])
        : null,
    'criticality_id' => filter_input(INPUT_POST, 'criticality_id', FILTER_VALIDATE_INT),
    'reset_filter' => filter_input(INPUT_POST, 'reset_filter', FILTER_VALIDATE_INT),
];

$resetFilter = (isset($myinputsGet['reset_filter']) && $myinputsGet['reset_filter'] == 1) ? true : false;

if ($resetFilter) {
    $centreon->historySearch[$url] = '';
    $centreon->historySearchService[$url] = '';
    $centreon->historySearchOutput[$url] = '';
    $_SESSION['filters'][$url] = [];
    $_SESSION['monitoring_default_hostgroups'] = '';
    $_SESSION['monitoring_default_servicegroups'] = '';
    $_SESSION['monitoring_default_poller'] = '';
    $_SESSION['monitoring_service_status_filter'] = '';
    $_SESSION['criticality_id'] = '';
}

if (!isset($o) || empty($o)) {
    $o = $_SESSION['monitoring_service_status'] ?? null;
}

foreach ($myinputsGet as $key => $value) {
    if (!empty($value)) {
        $filters[$key] = $value;
    } elseif (!empty($myinputsPost[$key])) {
        $filters[$key] = $myinputsPost[$key];
    } elseif ($resetFilter && isset($_SESSION['filters'][$url][$key]) && !empty($_SESSION['filters'][$url][$key])) {
        $filters[$key] = $_SESSION['filters'][$url][$key];
    } else {
        $filters[$key] = '';
    }
}

if (empty($filters['host_search']) && isset($centreon->historySearch[$url])) {
    $filters['host_search'] = $centreon->historySearch[$url];
} else {
    $centreon->historySearch[$url] = $filters['host_search'];
}

if (empty($filters['search']) && isset($centreon->historySearchService[$url])) {
    $filters['search'] = $centreon->historySearchService[$url];
} else {
    $centreon->historySearchService[$url] = $filters['search'];
}

if (empty($filters['output_search']) && isset($centreon->historySearchOutput[$url])) {
    $filters['output_search'] = $centreon->historySearchOutput[$url];
} else {
    $centreon->historySearchOutput[$url] = $filters['output_search'];
}


$_SESSION['filters'][$url] = $filters;

if (!empty($filters['hg'])) {
    $_SESSION['monitoring_default_hostgroups'] = $filters['hg'];
}

if (!empty($filters['sg'])) {
    $_SESSION['monitoring_default_servicegroups'] = $filters['sg'];
}

$tab_class = ["0" => "list_one", "1" => "list_two"];
$rows = 10;

/*
 * ACL Actions
 */
$GroupListofUser = [];
$GroupListofUser = $centreon->user->access->getAccessGroups();

$allActions = false;
/*
 * Get list of actions allowed for user
 */
if (count($GroupListofUser) > 0 && $is_admin == 0) {
    $authorized_actions = [];
    $authorized_actions = $centreon->user->access->getActions();
} else {
    /*
     * if user is admin, or without ACL, he cans perform all actions
     */
    $allActions = true;
}

include("./include/common/autoNumLimit.php");

/*
 * set limit & num
 */
$DBRESULT = $pearDB->query("SELECT * FROM options WHERE `key` = 'maxViewMonitoring' LIMIT 1");
$data = $DBRESULT->fetchRow();
$gopt[$data['key']] = myDecode($data['key']);

$sort_type = empty($filters["sort_type"]) ? 0 : $filters["sort_type"];
$host_name = empty($filters["host_name"]) ? "" : $filters["host_name"];

$problem_sort_type = 'host_name';
if (!empty($centreon->optGen["problem_sort_type"])) {
    $problem_sort_type = $centreon->optGen["problem_sort_type"];
}
$problem_sort_order = 'asc';
if (!empty($centreon->optGen["problem_sort_type"])) {
    $problem_sort_order = $centreon->optGen["problem_sort_order"];
}

$global_sort_type = 'host_name';
if (!empty($centreon->optGen["global_sort_type"])) {
    $global_sort_type = $centreon->optGen["global_sort_type"];
}
$global_sort_order = 'asc';
if (!empty($centreon->optGen["global_sort_order"])) {
    $global_sort_order = $centreon->optGen["global_sort_order"];
}

if ($o == "svcpb" || $o == "svc_unhandled") {
    $sort_type = !empty($filters["sort_type"]) ? $filters["sort_type"] : $centreon->optGen["problem_sort_type"];
    $order = !empty($filters["order"]) ? $filters["order"] : $centreon->optGen["problem_sort_order"];
} else {
    if (!empty($filters["sort_type"])) {
        $sort_type = $filters["sort_type"];
    } elseif (isset($centreon->optGen["global_sort_type"])) {
        $sort_type = CentreonDB::escape($centreon->optGen["global_sort_type"]);
    } else {
        $sort_type = "host_name";
    }
    if (!empty($filters["order"])) {
        $order = $filters["order"];
    } elseif (isset($centreon->optGen["global_sort_order"]) &&
        $centreon->optGen["global_sort_order"] != ""
    ) {
        $order = $centreon->optGen["global_sort_order"];
    } else {
        $order = "ASC";
    }
}

include_once("./include/monitoring/status/Common/default_poller.php");
include_once("./include/monitoring/status/Common/default_hostgroups.php");
include_once("./include/monitoring/status/Common/default_servicegroups.php");
include_once($svc_path . "/serviceJS.php");

/**
 * Build the resource status listing URI that will be used in the
 * deprecated banner
 */
$kernel = \App\Kernel::createForWeb();
$resourceController = $kernel->getContainer()->get(
    \Centreon\Application\Controller\MonitoringResourceController::class
);

$deprecationMessage = _('[Page deprecated] This page will be removed in the next major version. Please use the new page: ');
$resourcesStatusLabel = _('Resources Status');

$filter = [
    'criterias' => [
        [
            'name' => 'resource_types',
            'value' => [
                [
                    'id' => 'service',
                    'name' => 'Service'
                ]
            ]
        ],
        [
            'name' => 'states',
            'value' => [
                [
                    'id' => 'unhandled_problems',
                    'name' => 'Unhandled'
                ]
            ]
        ],
        [
            'name' => 'search',
            'value' => ''
        ]
    ]
];

$redirectionUrl = $resourceController->buildListingUri(['filter' => json_encode($filter)]);

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($svc_path, '/templates/');

$tpl->assign("p", $p);
$tpl->assign('o', $o);
$tpl->assign("sort_type", $sort_type);
$tpl->assign("num", $num);
$tpl->assign("limit", $limit);
$tpl->assign("mon_host", _("Hosts"));
$tpl->assign("mon_status", _("Status"));
$tpl->assign("mon_ip", _("IP"));
$tpl->assign("mon_last_check", _("Last Check"));
$tpl->assign("mon_duration", _("Duration"));
$tpl->assign("mon_status_information", _("Status information"));

$tab_class = ["0" => "list_one", "1" => "list_two"];
$rows = 10;

$sSetOrderInMemory = !isset($_GET['o']) ? "1" : "0";

$form = new HTML_QuickFormCustom('select_form', 'GET', "?p=" . $p);

$tpl->assign("order", strtolower($order));
$tab_order = ["sort_asc" => "sort_desc", "sort_desc" => "sort_asc"];
$tpl->assign("tab_order", $tab_order);
?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['cmd'].value = _i;
        document.forms['form'].elements['o1'].selectedIndex = 0;
        document.forms['form'].elements['o2'].selectedIndex = 0;
    }
</script>
<?php
$action_list = [];
$action_list[] = _("More actions...");

$informationsService = $dependencyInjector['centreon_remote.informations_service'];
$serverIsMaster = $informationsService->serverIsMaster();

/*
 * Showing actions allowed for current user
 */
if (isset($authorized_actions) && $allActions == false) {
    if (isset($authorized_actions["service_schedule_check"])) {
        $action_list[3] = _("Services : Schedule immediate check");
    }
    if (isset($authorized_actions["service_schedule_forced_check"])) {
        $action_list[4] = _("Services : Schedule immediate check (Forced)");
    }
    if (isset($authorized_actions["service_acknowledgement"])) {
        $action_list[70] = _("Services : Acknowledge");
    }
    if (isset($authorized_actions["service_disacknowledgement"])) {
        $action_list[71] = _("Services : Disacknowledge");
    }
    if ($serverIsMaster && isset($authorized_actions["service_notifications"])) {
        $action_list[80] = _("Services : Enable Notification");
    }
    if ($serverIsMaster && isset($authorized_actions["service_notifications"])) {
        $action_list[81] = _("Services : Disable Notification");
    }
    if ($serverIsMaster && isset($authorized_actions["service_checks"])) {
        $action_list[90] = _("Services : Enable Check");
    }
    if ($serverIsMaster && isset($authorized_actions["service_checks"])) {
        $action_list[91] = _("Services : Disable Check");
    }
    if (isset($authorized_actions["service_schedule_downtime"])) {
        $action_list[74] = _("Services : Set Downtime");
    }
    if (isset($authorized_actions["host_schedule_check"])) {
        $action_list[94] = _("Hosts : Schedule immediate check");
    }
    if (isset($authorized_actions["host_schedule_forced_check"])) {
        $action_list[95] = _("Hosts : Schedule immediate check (Forced)");
    }
    if (isset($authorized_actions["host_acknowledgement"])) {
        $action_list[72] = _("Hosts : Acknowledge");
    }
    if (isset($authorized_actions["host_disacknowledgement"])) {
        $action_list[73] = _("Hosts : Disacknowledge");
    }
    if ($serverIsMaster && isset($authorized_actions["host_notifications"])) {
        $action_list[82] = _("Hosts : Enable Notification");
    }
    if ($serverIsMaster && isset($authorized_actions["host_notifications"])) {
        $action_list[83] = _("Hosts : Disable Notification");
    }
    if ($serverIsMaster && isset($authorized_actions["host_checks"])) {
        $action_list[92] = _("Hosts : Enable Check");
    }
    if ($serverIsMaster && isset($authorized_actions["host_checks"])) {
        $action_list[93] = _("Hosts : Disable Check");
    }
    if (isset($authorized_actions["host_schedule_downtime"])) {
        $action_list[75] = _("Hosts : Set Downtime");
    }
} else {
    $action_list[3] = _("Services : Schedule immediate check");
    $action_list[4] = _("Services : Schedule immediate check (Forced)");
    $action_list[70] = _("Services : Acknowledge");
    $action_list[71] = _("Services : Disacknowledge");

    if ($serverIsMaster) {
        $action_list[80] = _("Services : Enable Notification");
        $action_list[81] = _("Services : Disable Notification");
        $action_list[90] = _("Services : Enable Check");
        $action_list[91] = _("Services : Disable Check");
    }

    $action_list[74] = _("Services : Set Downtime");

    $action_list[94] = _("Hosts : Schedule immediate check");
    $action_list[95] = _("Hosts : Schedule immediate check (Forced)");
    $action_list[72] = _("Hosts : Acknowledge");
    $action_list[73] = _("Hosts : Disacknowledge");

    if ($serverIsMaster) {
        $action_list[82] = _("Hosts : Enable Notification");
        $action_list[83] = _("Hosts : Disable Notification");
        $action_list[92] = _("Hosts : Enable Check");
        $action_list[93] = _("Hosts : Disable Check");
    }

    $action_list[75] = _("Hosts : Set Downtime");
}

$attrs = ['onchange' => "javascript: " .
    " var bChecked = isChecked(); " .
    " if (this.form.elements['o1'].selectedIndex != 0 && !bChecked) {" .
    " alert('" . _("Please select one or more items") . "'); return false;} " .
    " if (this.form.elements['o1'].selectedIndex == 0) {" .
    " return false;} " .
    " if (cmdCallback(this.value)) { setO(this.value); submit();} else { setO(this.value); }"];
$form->addElement('select', 'o1', null, $action_list, $attrs);

$form->setDefaults(['o1' => null]);
$o1 = $form->getElement('o1');
$o1->setValue(null);

$attrs = ['onchange' => "javascript: " .
    " var bChecked = isChecked(); " .
    " if (this.form.elements['o2'].selectedIndex != 0 && !bChecked) {" .
    " alert('" . _("Please select one or more items") . "'); return false;} " .
    " if (this.form.elements['o2'].selectedIndex == 0) {" .
    " return false;} " .
    " if (cmdCallback(this.value)) { setO(this.value); submit();} else { setO(this.value); }"];
$form->addElement('select', 'o2', null, $action_list, $attrs);
$form->setDefaults(['o2' => null]);
$o2 = $form->getElement('o2');
$o2->setValue(null);
$o2->setSelected(null);
$tpl->assign('limit', $limit);

$keyPrefix = "";
$statusList = ["" => "", "ok" => _("OK"), "warning" => _("Warning"), "critical" => _("Critical"), "unknown" => _("Unknown"), "pending" => _("Pending")];

$statusService = ["svc_unhandled" => _("Unhandled Problems"), "svcpb" => _("Service Problems"), "svc" => _("All")];

if ($o == "svc") {
    $keyPrefix = "svc";
} elseif ($o == "svcpb") {
    $keyPrefix = "svc";
    unset($statusList["ok"]);
} elseif ($o == "svc_unhandled") {
    $keyPrefix = "svc_unhandled";
    unset($statusList["ok"]);
    unset($statusList["pending"]);
} elseif (preg_match("/svc_([a-z]+)/", $o, $matches)) {
    if (isset($matches[1])) {
        $keyPrefix = "svc";
        $defaultStatus = $matches[1];
    }
}

$serviceStatusFromO = isset($_GET['o']) && in_array($_GET['o'], array_keys($statusService))
    ? $_GET['o']
    : null;
$defaultStatusService =  $_GET['statusService']
    ?? $_POST['statusService']
    ?? $serviceStatusFromO
    ?: $_SESSION['monitoring_service_status']
    ?? 'svc_unhandled';
$o = $defaultStatusService;
$form->setDefaults(['statusFilter' => $defaultStatusService]);

$defaultStatusFilter = $_GET['statusFilter']
    ?? $_POST['statusFilter']
    ?? $_SESSION['monitoring_service_status_filter']
    ?? '';

$form->addElement(
    'select',
    'statusFilter',
    _('Status'),
    $statusList,
    ['id' => 'statusFilter', 'onChange' => "filterStatus(this.value);"]
);
$form->setDefaults(['statusFilter' => $defaultStatusFilter]);

$form->addElement(
    'select',
    'statusService',
    _('Service Status'),
    $statusService,
    ['id' => 'statusService', 'onChange' => "statusServices(this.value);"]
);
$form->setDefaults(['statusService' => $defaultStatusService]);

$criticality = new CentreonCriticality($pearDB);
$crits = $criticality->getList(null, "level", 'ASC', null, null, true);
$critArray = [0 => ""];
foreach ($crits as $critId => $crit) {
    $critArray[$critId] = $crit['sc_name'] . " ({$crit['level']})";
}
$form->addElement(
    'select',
    'criticality',
    _('Severity'),
    $critArray,
    ['id' => 'critFilter', 'onChange' => "filterCrit(this.value);"]
);
$form->setDefaults(['criticality' => $_SESSION['criticality_id'] ?? "0"]);

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('hostStr', _('Host'));
$tpl->assign('serviceStr', _('Service'));
$tpl->assign('statusService', _('Service Status'));
$tpl->assign("filters", _("Filters"));
$tpl->assign('outputStr', _('Output'));
$tpl->assign('poller_listing', $centreon->user->access->checkAction('poller_listing'));
$tpl->assign('pollerStr', _('Poller'));
$tpl->assign('hgStr', _('Hostgroup'));
$tpl->assign('sgStr', _('Servicegroup'));
$criticality = new CentreonCriticality($pearDB);
$tpl->assign('criticalityUsed', count($criticality->getList()));
$tpl->assign('form', $renderer->toArray());
$tpl->display("service.ihtml");

?>
<script type='text/javascript'>
    var tabSortPb = [];
    tabSortPb['champ'] = '<?php echo $problem_sort_type;?>';
    tabSortPb['ordre'] = '<?php echo $problem_sort_order;?>';

    var tabSortAll = [];
    tabSortAll['champ'] = '<?php echo $global_sort_type;?>';
    tabSortAll['ordre'] = '<?php echo $global_sort_order;?>';

    var ok = '<?php echo _("OK");?>';
    var warning = '<?php echo _("Warning");?>';
    var critical = '<?php echo _("Critical");?>';
    var unknown = '<?php echo _("Unknown");?>';
    var pending = '<?php echo _("Pending");?>';

    display_deprecated_banner();

    function display_deprecated_banner() {
        const url = "<?php echo $redirectionUrl; ?>";
        const message = "<?php echo $deprecationMessage; ?>";
        const label = "<?php echo $resourcesStatusLabel; ?>";
        jQuery('.pathway').append(
            '<span style="color:#FF4500;padding-left:10px;font-weight:bold">' + message +
            '<a style="position:relative" href="' + url + '" isreact="isreact">' + label + '</a></span>'
        );
    }

    jQuery('#statusService').change(function () {
        updateSelect();
    });

    function updateSelect() {
        var oldStatus = jQuery('#statusFilter').val();
        var opts = document.getElementById('statusFilter').options;
        var newTypeOrder = null;
        if (jQuery('#statusService').val() == 'svcpb' || jQuery('#statusService').val() == 'svc_unhandled') {
            opts.length = 0;
            opts[opts.length] = new Option("", "");
            opts[opts.length] = new Option(warning, "warning");
            opts[opts.length] = new Option(critical, "critical");
            opts[opts.length] = new Option(unknown, "unknown");
            newTypeOrder = tabSortPb['champ'];
        } else {
            opts.length = 0;
            opts[opts.length] = new Option("", "");
            opts[opts.length] = new Option(ok, "ok");
            opts[opts.length] = new Option(warning, "warning");
            opts[opts.length] = new Option(critical, "critical");
            opts[opts.length] = new Option(unknown, "unknown");
            opts[opts.length] = new Option(pending, "pending");
            newTypeOrder = tabSortAll['champ'];
        }

        // We define the statusFilter before calling ajax
        if (jQuery("#statusFilter option[value='" + oldStatus + "']").length > 0) {
            jQuery("#statusFilter option[value='" + oldStatus + "']").prop('selected', true);
        } else {
            jQuery("#statusFilter option[value='']").prop('selected', true);
        }

        change_type_order(newTypeOrder);
    }


    var _keyPrefix;

    jQuery(function () {
        preInit();
    });

    function preInit() {
        _keyPrefix = '<?= $keyPrefix; ?>';
        _tm = <?= $tM ?>;
        _o = '<?= $o; ?>';
        _defaultStatusFilter = '<?=  htmlspecialchars($defaultStatusFilter, ENT_QUOTES, 'UTF-8'); ?>';
        _defaultStatusService = '<?= $defaultStatusService; ?>';
        sSetOrderInMemory = '<?= $sSetOrderInMemory; ?>';

        if (_defaultStatusService !== '') {
            jQuery("#statusService option[value='" + _defaultStatusService + "']").prop('selected', true);
        }
        if (_defaultStatusFilter !== '') {
            jQuery("#statusFilter option[value='" + _defaultStatusFilter + "']").prop('selected', true);
        }
        filterStatus(document.getElementById('statusFilter').value, 1);
    }

    function filterStatus(value, isInit) {
        _o = jQuery('#statusService').val();
        if (value) {
            _o = _keyPrefix + '_' + value;
        } else if (!isInit && _o != 'svcpb') {
            _o = _keyPrefix;
        }
        window.clearTimeout(_timeoutID);
        initM(_tm, _o);
    }

    function filterCrit(value) {
        window.clearTimeout(_timeoutID);
        initM(_tm, _o);
    }

    function statusServices(value, isInit) {
        _o = value;
    }
</script>
