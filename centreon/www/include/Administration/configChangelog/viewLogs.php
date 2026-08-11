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

use Core\ActionLog\Domain\Model\ActionLog;

if (! isset($centreon)) {
    exit();
}

// Path to the configuration dir
$path = './include/Administration/configChangelog/';

/**
 * Display label of each audited object type.
 *
 * log_action.object_type stores an internal token ('hostcategories',
 * 'menu access', ...). Translating the token itself would both read badly and
 * hijack very generic msgids ('host', 'service') shared with other pages, so
 * each type is mapped to the label its configuration menu already uses.
 *
 * Covers ActionLog::AVAILABLE_OBJECT_TYPES — the types the filter offers — plus
 * the two severity types, which the audit log writes but that list leaves out.
 * A type missing from this map falls back to its raw token.
 */
function getChangelogObjectTypeLabels(): array
{
    return [
        ActionLog::OBJECT_TYPE_COMMAND => _('Command'),
        // Command changes logged through the legacy path are stored under the
        // plural 'commands' token (the Core writer uses the singular constant
        // above). Map it too so both are labelled instead of shown raw.
        'commands' => _('Command'),
        ActionLog::OBJECT_TYPE_TIMEPERIOD => _('Time period'),
        ActionLog::OBJECT_TYPE_CONTACT => _('Contact'),
        ActionLog::OBJECT_TYPE_CONTACTGROUP => _('Contact group'),
        ActionLog::OBJECT_TYPE_HOST => _('Host'),
        ActionLog::OBJECT_TYPE_HOSTGROUP => _('Host Group'),
        ActionLog::OBJECT_TYPE_SERVICE => _('Service'),
        ActionLog::OBJECT_TYPE_SERVICEGROUP => _('Service Group'),
        ActionLog::OBJECT_TYPE_TRAPS => _('SNMP Traps'),
        ActionLog::OBJECT_TYPE_ESCALATION => _('Escalation'),
        ActionLog::OBJECT_TYPE_HOST_DEPENDENCY => _('Host dependency'),
        ActionLog::OBJECT_TYPE_HOSTGROUP_DEPENDENCY => _('Host group dependency'),
        ActionLog::OBJECT_TYPE_SERVICE_DEPENDENCY => _('Service dependency'),
        ActionLog::OBJECT_TYPE_SERVICEGROUP_DEPENDENCY => _('Service group dependency'),
        ActionLog::OBJECT_TYPE_POLLER => _('Poller'),
        ActionLog::OBJECT_TYPE_ENGINE => _('Engine'),
        ActionLog::OBJECT_TYPE_BROKER => _('Broker'),
        ActionLog::OBJECT_TYPE_RESOURCES => _('Resources'),
        ActionLog::OBJECT_TYPE_META => _('Meta Service'),
        ActionLog::OBJECT_TYPE_ACCESS_GROUP => _('Access group'),
        ActionLog::OBJECT_TYPE_MENU_ACCESS => _('Menu access'),
        ActionLog::OBJECT_TYPE_RESOURCE_ACCESS => _('Resource access'),
        ActionLog::OBJECT_TYPE_ACTION_ACCESS => _('Action access'),
        ActionLog::OBJECT_TYPE_MANUFACTURER => _('Manufacturer'),
        ActionLog::OBJECT_TYPE_HOSTCATEGORIES => _('Host Categories'),
        ActionLog::OBJECT_TYPE_SERVICECATEGORIES => _('Service Categories'),
        ActionLog::OBJECT_TYPE_HOST_SEVERITY => _('Host severity'),
        ActionLog::OBJECT_TYPE_SERVICE_SEVERITY => _('Service severity'),
    ];
}

// PHP functions
require_once './include/common/common-Func.php';
require_once './class/centreonDB.class.php';

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);
$tpl->assign('centreon_path', _CENTREON_PATH_);
$tpl->assign('p', $p);

// Detail view: when object_id is in GET and no search form submitted
if (isset($_GET['object_id'], $_GET['object_type'])) {
    // CentreonLogAction reads the centstorage connection as a global.
    $pearDBO = new CentreonDB('centstorage');

    $objectId = (int) $_GET['object_id'];
    $objectType = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['object_type']);

    $tpl->assign('field_name', _('Field Name'));
    $tpl->assign('before', _('Before'));
    $tpl->assign('after', _('After'));
    $tpl->assign('noModifLabel', _('No modification was made.'));
    // Same label as the "Object Type" column of the listing.
    $tpl->assign('objectTypeLabel', getChangelogObjectTypeLabels()[$objectType] ?? $objectType);

    $tpl->assign('action', $centreon->CentreonLogAction->listAction($objectId, $objectType));
    $tpl->assign('modification', $centreon->CentreonLogAction->listModification($objectId, $objectType));

    $tpl->display('viewLogsDetails.ihtml');
} else {
    // Listing view — AJAX-driven
    $defaultLimit = (int) ($centreon->optGen['maxViewConfiguration'] ?? 30) ?: 30;

    $tpl->assign('defaultLimit', $defaultLimit);

    // Single source of truth for the object type filter: the same list the audit
    // log writes into log_action.object_type. The raw token is the filter value
    // (it is what the column stores), the label is the display text.
    $objectTypeLabels = getChangelogObjectTypeLabels();
    $objectTypes = [];
    foreach (ActionLog::AVAILABLE_OBJECT_TYPES as $objectType) {
        $objectTypes[$objectType] = $objectTypeLabels[$objectType] ?? $objectType;
    }
    $tpl->assign('objectTypes', $objectTypes);
    // The "Object Type" column needs every label, including the types the filter
    // does not offer (the severities).
    $tpl->assign('objectTypeLabels', $objectTypeLabels);
    $tpl->display('viewLogs.ihtml');
}
