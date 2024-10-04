<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

global $form_service_type;

require_once _CENTREON_PATH_ . 'www/class/centreonLDAP.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonContactgroup.class.php';

$serviceObj = new CentreonService($pearDB);
$serviceHgParsFieldIsAdded = false;
$serviceHParsFieldIsAdded = false;

const BASE_ROUTE = './include/common/webServices/rest/internal.php';

$datasetRoutes = [
    'timeperiods' => BASE_ROUTE . '?object=centreon_configuration_timeperiod&action=list',
    'contacts' => BASE_ROUTE . '?object=centreon_configuration_contact&action=list',
    'default_contacts' => BASE_ROUTE . '?object=centreon_configuration_contact&action=defaultValues&target=service&field=service_cs&id=' . $service_id,
    'contact_groups' => BASE_ROUTE . '?object=centreon_configuration_contactgroup&action=list',
    'default_contact_groups' => BASE_ROUTE . '?object=centreon_configuration_contactgroup&action=defaultValues&target=service&field=service_cgs&id=' . $service_id,
    'hosts' => BASE_ROUTE . '?object=centreon_configuration_host&action=list',
    'default_hosts' => BASE_ROUTE . '?object=centreon_configuration_host&action=defaultValues&target=service&field=service_hPars&id=' . $service_id,
    'host_groups' => BASE_ROUTE . '?object=centreon_configuration_hostgroup&action=list',
    'service_templates' => BASE_ROUTE . '?object=centreon_configuration_servicetemplate&action=list',
    'service_groups' => BASE_ROUTE . '?object=centreon_configuration_servicegroup&action=list',
    'service_categories' => BASE_ROUTE . '?object=centreon_configuration_servicecategory&action=list&t=c',
    'traps' => BASE_ROUTE . '?object=centreon_configuration_trap&action=list',
    'check_commands' => BASE_ROUTE . '?object=centreon_configuration_command&action=list&t=2',
    'event_handlers' => BASE_ROUTE . '?object=centreon_configuration_command&action=list',
    'default_check_commands' => BASE_ROUTE . '?object=centreon_configuration_command&action=defaultValues&target=service&field=command_command_id&id=' . $service_id,
    'graph_templates' => BASE_ROUTE . '?object=centreon_configuration_graphtemplate&action=list',
    'default_service_templates' => BASE_ROUTE . '?object=centreon_configuration_servicetemplate&action=defaultValues&target=service&field=service_template_model_stm_id&id=' . $service_id,
    'default_event_handlers' => BASE_ROUTE . '?object=centreon_configuration_command&action=defaultValues&target=service&field=command_command_id2&id=' . $service_id,
    'default_check_periods' => BASE_ROUTE . '?object=centreon_configuration_timeperiod&action=defaultValues&target=service&field=timeperiod_tp_id&id=' . $service_id,
    'default_notification_periods' => BASE_ROUTE . '?object=centreon_configuration_timeperiod&action=defaultValues&target=service&field=timeperiod_tp_id2&id=' . $service_id,
    'default_host_groups' => BASE_ROUTE . '?object=centreon_configuration_hostgroup&action=defaultValues&target=service&field=service_hgPars&id=' . $service_id,
    'default_service_groups' => BASE_ROUTE . '?object=centreon_configuration_servicegroup&action=defaultValues&target=service&field=service_sgs&id=' . $service_id,
    'default_traps' => BASE_ROUTE . '?object=centreon_configuration_trap&action=defaultValues&target=service&field=service_traps&id=' . $service_id,
    'default_graph_templates' => BASE_ROUTE . '?object=centreon_configuration_graphtemplate&action=defaultValues&target=service&field=graph_id&id=' . $service_id,
    'default_service_categories' => BASE_ROUTE . '?object=centreon_configuration_servicecategory&action=defaultValues&target=service&field=service_categories&id=' . $service_id,
];

$attributes = [
    'timeperiods' => [
        'datasourceOrigin' => 'ajax',
        'availableDatasetRoute' => $datasetRoutes['timeperiods'],
        'multiple' => false,
        'linkedObject' => 'centreonTimeperiod',
    ],
    'contacts' => [
        'datasourceOrigin' => 'ajax',
        'availableDatasetRoute' => $datasetRoutes['contacts'],
        'defaultDatasetRoute' => $datasetRoutes['default_contacts'],
        'multiple' => true,
        'linkedObject' => 'centreonContact',
    ],
    'contact_groups' => [
        'datasourceOrigin' => 'ajax',
        'availableDatasetRoute' => $datasetRoutes['contact_groups'],
        'defaultDatasetRoute' => $datasetRoutes['default_contact_groups'],
        'multiple' => true,
        'linkedObject' => 'centreonContactgroup',
    ],
    'check_commands' => [
        'datasourceOrigin' => 'ajax',
        'multiple' => false,
        'linkedObject' => 'centreonCommand',
        'defaultDatasetRoute' => $datasetRoutes['default_check_commands'],
        'availableDatasetRoute' => $datasetRoutes['check_commands'],
    ],
    'hosts' => [
        'datasourceOrigin' => 'ajax',
        'availableDatasetRoute' => $datasetRoutes['hosts'],
        'defaultDatasetRoute' => $datasetRoutes['default_hosts'],
        'multiple' => true,
        'linkedObject' => 'centreonHost',
    ],
    'hosts_cloud_specific' => [
        'datasourceOrigin' => 'ajax',
        'availableDatasetRoute' => $datasetRoutes['hosts'],
        'defaultDatasetRoute' => $datasetRoutes['default_hosts'],
        'multiple' => false,
        'linkedObject' => 'centreonHost',
    ],
    'host_groups' => [
        'datasourceOrigin' => 'ajax',
        'availableDatasetRoute' => $datasetRoutes['host_groups'],
        'defaultDatasetRoute' => $datasetRoutes['default_host_groups'],
        'multiple' => true,
        'linkedObject' => 'centreonHostgroups',
    ],
    'templates' => [
        'datasourceOrigin' => 'ajax',
        'availableDatasetRoute' => $datasetRoutes['service_templates'],
        'defaultDatasetRoute' => $datasetRoutes['default_service_templates'],
        'multiple' => false,
        'linkedObject' => 'centreonServicetemplates',
    ],
    'service_groups' => [
        'datasourceOrigin' => 'ajax',
        'availableDatasetRoute' => $datasetRoutes['service_groups'],
        'defaultDatasetRoute' => $datasetRoutes['default_service_groups'],
        'multiple' => true,
        'linkedObject' => 'centreonServicegroups',
    ],
    'service_categories' => [
        'datasourceOrigin' => 'ajax',
        'availableDatasetRoute' => $datasetRoutes['service_categories'],
        'defaultDatasetRoute' => $datasetRoutes['default_service_categories'],
        'multiple' => true,
        'linkedObject' => 'centreonServicecategories',
    ],
    'traps' => [
        'datasourceOrigin' => 'ajax',
        'availableDatasetRoute' => $datasetRoutes['traps'],
        'defaultDatasetRoute' => $datasetRoutes['default_traps'],
        'multiple' => true,
        'linkedObject' => 'centreonTraps',
    ],
    'graph_templates' => [
        'datasourceOrigin' => 'ajax',
        'availableDatasetRoute' => $datasetRoutes['graph_templates'],
        'defaultDatasetRoute' => $datasetRoutes['default_graph_templates'],
        'multiple' => false,
        'linkedObject' => 'centreonGraphTemplate',
    ],
    'event_handlers' => [
        'datasourceOrigin' => 'ajax',
        'multiple' => false,
        'linkedObject' => 'centreonCommand',
        'defaultDatasetRoute' => $datasetRoutes['default_event_handlers'],
        'availableDatasetRoute' => $datasetRoutes['event_handlers'],
    ],
    'check_periods' => [
        'datasourceOrigin' => 'ajax',
        'availableDatasetRoute' => $datasetRoutes['timeperiods'],
        'defaultDatasetRoute' => $datasetRoutes['default_check_periods'],
        'multiple' => false,
        'linkedObject' => 'centreonTimeperiod',
    ],
    'notification_periods' => [
        'datasourceOrigin' => 'ajax',
        'availableDatasetRoute' => $datasetRoutes['timeperiods'],
        'defaultDatasetRoute' => $datasetRoutes['default_notification_periods'],
        'multiple' => false,
        'linkedObject' => 'centreonTimeperiod',
    ],
];

const PASSWORD_REPLACEMENT_VALUE = '**********';

/**
 * Database retrieve information for Service.
 *
 * @param ?string $arg
 */
function myDecodeService($arg)
{
    $arg = str_replace('#BR#', '\\n', $arg ?? '');
    $arg = str_replace('#T#', '\\t', $arg);
    $arg = str_replace('#R#', '\\r', $arg);
    $arg = str_replace('#S#', '/', $arg);
    $arg = str_replace('#BS#', '\\', $arg);

    return html_entity_decode($arg, ENT_QUOTES, 'UTF-8');
}

if (
    ! $centreon->user->admin
    && is_numeric($service_id)
) {
    $checkres = $pearDB->query(
        "SELECT service_id
        FROM `{$aclDbName}`.centreon_acl
        WHERE service_id = " . $pearDB->escape($service_id) . '
        AND group_id IN (' . $acl->getAccessGroupsString() . ')'
    );
    if (! $checkres->rowCount()) {
        $msg = new CentreonMsg();
        $msg->setImage('./img/icons/warning.png');
        $msg->setTextStyle('bold');
        $msg->setText(_('You are not allowed to access this service'));

        return;
    }
}

$cmdId = 0;
$service = [];
$serviceTplId = null;
$initialValues = [];

// Used to store all macro passwords
$macroPasswords = [];

if (($o === SERVICE_MODIFY || $o === SERVICE_WATCH) && $service_id) {
    $statement = $pearDB->prepare(
        'SELECT *
        FROM service
        LEFT JOIN extended_service_information esi
            ON esi.service_service_id = service_id
        WHERE service_id = :service_id LIMIT 1'
    );
    $statement->bindValue(':service_id', $service_id, \PDO::PARAM_INT);
    $statement->execute();

    // Set base value
    $service = array_map('myDecodeService', $statement->fetch());
    $serviceTplId = $service['service_template_model_stm_id'];
    $cmdId = $service['command_command_id'];

    // Set Service Notification Options
    $tmp = explode(',', $service['service_notification_options']);
    foreach ($tmp as $key => $value) {
        $service['service_notifOpts'][trim($value)] = 1;
    }

    // Set Stalking Options
    $tmp = explode(',', $service['service_stalking_options']);
    foreach ($tmp as $key => $value) {
        $service['service_stalOpts'][trim($value)] = 1;
    }

    // Set criticality
    $statement = $pearDB->prepare(
        'SELECT sc.sc_id
        FROM service_categories sc
        INNER JOIN service_categories_relation scr
            ON scr.sc_id = sc.sc_id
        WHERE scr.service_service_id = :service_id AND sc.level IS NOT NULL
        ORDER BY sc.level ASC LIMIT 1'
    );
    $statement->bindValue(':service_id', $service_id, \PDO::PARAM_INT);
    $statement->execute();
    if ($statement->rowCount()) {
        $cr = $statement->fetch();
        $service['criticality_id'] = $cr['sc_id'];
    }
}

/**
 * define variable to avoid null count.
 */
$aMacros = [];

if (($o === SERVICE_MODIFY || $o === SERVICE_WATCH) && $service_id) {
    $aListTemplate = getListTemplates($pearDB, $service_id);

    if (! isset($cmdId)) {
        $cmdId = '';
    }

    if (isset($_REQUEST['macroInput'])) {
        /**
         * We don't taking into account the POST data sent from the interface in order the retrieve the original value
         * of all passwords.
         */
        $aMacros = $serviceObj->getMacros($service_id, $aListTemplate, $cmdId);

        /**
         * If a password has been modified from the interface, we retrieve the old password existing in the repository
         * (giving by the $aMacros variable) to inject it before saving.
         * Passwords will be saved using the $_REQUEST variable.
         */
        foreach ($_REQUEST['macroInput'] as $index => $macroName) {
            if (
                ! isset($_REQUEST['macroFrom'][$index])
                || ! isset($_REQUEST['macroPassword'][$index])
                || $_REQUEST['macroPassword'][$index] !== '1'                      // Not a password
                || $_REQUEST['macroValue'][$index] !== PASSWORD_REPLACEMENT_VALUE  // The password has not changed
            ) {
                continue;
            }
            foreach ($aMacros as $macroAlreadyExist) {
                if (
                    $macroAlreadyExist['macroInput_#index#'] === $macroName
                    && $_REQUEST['macroFrom'][$index] === $macroAlreadyExist['source']
                ) {
                    /**
                     * if the password has not been changed, we replace the password coming from the interface with
                     * the original value (from the repository) before saving.
                     */
                    $_REQUEST['macroValue'][$index] = $macroAlreadyExist['macroValue_#index#'];
                }
            }
        }
    }
    // We taking into account the POST data sent from the interface
    $aMacros = $serviceObj->getMacros($service_id, $aListTemplate, $cmdId, $_POST);

    // We hide all passwords in the jsData property to prevent them from appearing in the HTML code.
    foreach ($aMacros as $index => $macroValues) {
        if ($macroValues['macroPassword_#index#'] === 1) {
            $macroPasswords[$index]['password'] = $aMacros[$index]['macroValue_#index#'];
            // It's a password macro
            $aMacros[$index]['macroOldValue_#index#'] = PASSWORD_REPLACEMENT_VALUE;
            $aMacros[$index]['macroValue_#index#'] = PASSWORD_REPLACEMENT_VALUE;
            // Keep the original name of the input field in case its name changes.
            $aMacros[$index]['macroOriginalName_#index#'] = $aMacros[$index]['macroInput_#index#'];
        }
    }
}

$cdata = CentreonData::getInstance();
$cdata->addJsData('clone-values-macro', htmlspecialchars(
    json_encode($aMacros),
    ENT_QUOTES
));

$cdata->addJsData('clone-count-macro', count($aMacros));

// IMG comes from DB -> Store in $extImg Array
$extImg = [];
$extImg = return_image_list(1);

//
// End of "database-retrieved" information
// #########################################################
// #########################################################
// Var information to format the element
//
$attrsText = ['size' => '30'];
$attrsText2 = ['size' => '6'];
$attrsTextURL = ['size' => '50'];
$attrsAdvSelect_small = ['style' => 'width: 270px; height: 70px;'];
$attrsAdvSelect = ['style' => 'width: 270px; height: 100px;'];
$attrsAdvSelect_big = ['style' => 'width: 270px; height: 200px;'];
$attrsTextarea = ['rows' => '5', 'cols' => '40'];
$eTemplate = '<table><tr><td><div class="ams">{label_2}</div>{unselected}</td><td align="center">{add}<br />'
    . '<br /><br />{remove}</td><td><div class="ams">{label_3}</div>{selected}</td></tr></table>';

// For a shitty reason, Quickform set checkbox with stal[o] name
unset($_POST['o']);
//
// # Form begin
//
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);
if ($o === SERVICE_ADD) {
    $form->addElement('header', 'title', _('Add a Service'));
} elseif ($o === SERVICE_MODIFY) {
    $form->addElement('header', 'title', _('Modify a Service'));
} elseif ($o === SERVICE_WATCH) {
    $form->addElement('header', 'title', _('View a Service'));
} elseif ($o === SERVICE_MASSIVE_CHANGE) {
    $form->addElement('header', 'title', _('Mass Change'));
}

//
// # Service basic information
//

/*
 * - No possibility to change name and alias, because there's no interest
 * - May be ? #409
 */
if ($o !== SERVICE_MASSIVE_CHANGE) {
    $form->addElement('text', 'service_description', _('Name'), $attrsText);
}
$form->addElement('text', 'service_alias', _('Alias'), $attrsText);

$serviceTplSelect = $form->addElement(
    'select2',
    'service_template_model_stm_id',
    _('Template'),
    [],
    $attributes['templates']
);
$serviceTplSelect->addJsCallback('change', 'changeServiceTemplate(this.value)');

$form->addElement('static', 'tplText', _('Using a Template exempts you to fill required fields'));

//
// # Check information
//
$form->addElement('header', 'check', _('Service State'));

$checkCommandSelect = $form->addElement(
    'select2',
    'command_command_id',
    _('Check Command'),
    [],
    $attributes['check_commands']
);
if ($o === SERVICE_MASSIVE_CHANGE) {
    $checkCommandSelect->addJsCallback(
        'change',
        'setArgument(jQuery(this).closest("form").get(0),"command_command_id","example1");'
    );
} else {
    $checkCommandSelect->addJsCallback('change', 'changeCommand(this.value);');
}

$form->addElement('text', 'command_command_id_arg', _('Args'), $attrsText);


if (! $isCloudPlatform) {
    $serviceIV = [
        $form->createElement('radio', 'service_is_volatile', null, _('Yes'), '1'),
        $form->createElement('radio', 'service_is_volatile', null, _('No'), '0'),
        $form->createElement('radio', 'service_is_volatile', null, _('Default'), '2'),
    ];
    $form->addGroup($serviceIV, 'service_is_volatile', _('Is Volatile'), '&nbsp;');
    if ($o !== SERVICE_MASSIVE_CHANGE) {
        $form->setDefaults(['service_is_volatile' => '2']);
    }

    $serviceEHE = [
        $form->createElement('radio', 'service_event_handler_enabled', null, _('Yes'), '1'),
        $form->createElement('radio', 'service_event_handler_enabled', null, _('No'), '0'),
        $form->createElement('radio', 'service_event_handler_enabled', null, _('Default'), '2'),
    ];
    $form->addGroup($serviceEHE, 'service_event_handler_enabled', _('Event Handler Enabled'), '&nbsp;');
    if ($o !== SERVICE_MASSIVE_CHANGE) {
        $form->setDefaults(['service_event_handler_enabled' => '2']);
    }

    $eventHandlerSelect = $form->addElement(
        'select2',
        'command_command_id2',
        _('Event Handler'),
        [],
        $attributes['event_handlers']
    );
    $eventHandlerSelect->addJsCallback(
        'change',
        'setArgument(jQuery(this).closest("form").get(0),"command_command_id2","example2");'
    );

    $form->addElement('text', 'command_command_id_arg2', _('Args'), $attrsText);
    $serviceACE = [
        $form->createElement('radio', 'service_active_checks_enabled', null, _('Yes'), '1'),
        $form->createElement('radio', 'service_active_checks_enabled', null, _('No'), '0'),
        $form->createElement('radio', 'service_active_checks_enabled', null, _('Default'), '2'),
    ];
    $form->addGroup($serviceACE, 'service_active_checks_enabled', _('Active Checks Enabled'), '&nbsp;');

    if ($o !== SERVICE_MASSIVE_CHANGE) {
        $form->setDefaults(['service_active_checks_enabled' => '2']);
    }

    $servicePCE = [
        $form->createElement('radio', 'service_passive_checks_enabled', null, _('Yes'), '1'),
        $form->createElement('radio', 'service_passive_checks_enabled', null, _('No'), '0'),
        $form->createElement('radio', 'service_passive_checks_enabled', null, _('Default'), '2'),
    ];
    $form->addGroup($servicePCE, 'service_passive_checks_enabled', _('Passive Checks Enabled'), '&nbsp;');
    if ($o !== SERVICE_MASSIVE_CHANGE) {
        $form->setDefaults(['service_passive_checks_enabled' => '2']);
    }
    $form->addElement('header', 'information', _('Service Basic Information'));
    // Notification information
    $form->addElement('header', 'notification', _('Notification'));

    $serviceNE = [
        $form->createElement('radio', 'service_notifications_enabled', null, _('Yes'), '1'),
        $form->createElement('radio', 'service_notifications_enabled', null, _('No'), '0'),
        $form->createElement('radio', 'service_notifications_enabled', null, _('Default'), '2'),
    ];

    $form->addGroup($serviceNE, 'service_notifications_enabled', _('Notification Enabled'), '&nbsp;');
    if ($o !== SERVICE_MASSIVE_CHANGE) {
        $form->setDefaults(['service_notifications_enabled' => '2']);
    }

    if ($o === SERVICE_MASSIVE_CHANGE) {
        $mc_mod_cgs = [
            $form->createElement('radio', 'mc_mod_cgs', null, _('Incremental'), '0'),
            $form->createElement('radio', 'mc_mod_cgs', null, _('Replacement'), '1'),
        ];
        $form->addGroup($mc_mod_cgs, 'mc_mod_cgs', _('Update mode'), '&nbsp;');
        $form->setDefaults(['mc_mod_cgs' => '0']);
    }

    // Use only contacts/contacts group of host and host template
    $form->addElement('header', 'use_only_contacts_from_host', _('Inherit only contacts/contacts group from host'));
    $serviceIOHC = [
        $form->createElement('radio', 'service_use_only_contacts_from_host', null, _('Yes'), '1'),
        $form->createElement('radio', 'service_use_only_contacts_from_host', null, _('No'), '0'),
    ];
    $form->addGroup(
        $serviceIOHC,
        'service_use_only_contacts_from_host',
        _('Inherit only contacts/contacts group from host'),
        '&nbsp;'
    );
    if ($o !== SERVICE_MASSIVE_CHANGE) {
        $form->setDefaults(['service_use_only_contacts_from_host' => '0']);
    }

    // Additive
    if ($o === SERVICE_MASSIVE_CHANGE) {
        $contactAdditive = [
            $form->createElement('radio', 'mc_contact_additive_inheritance', null, _('Yes'), '1'),
            $form->createElement('radio', 'mc_contact_additive_inheritance', null, _('No'), '0'),
            $form->createElement(
                'radio',
                'mc_contact_additive_inheritance',
                null,
                _('Default'),
                '2'
            )
        ];
        $form->addGroup($contactAdditive, 'mc_contact_additive_inheritance', _('Contact additive inheritance'), '&nbsp;');

        $contactGroupAdditive = [
            $form->createElement('radio', 'mc_cg_additive_inheritance', null, _('Yes'), '1'),
            $form->createElement('radio', 'mc_cg_additive_inheritance', null, _('No'), '0'),
            $form->createElement(
                'radio',
                'mc_cg_additive_inheritance',
                null,
                _('Default'),
                '2'
            )
        ];
        $form->addGroup(
            $contactGroupAdditive,
            'mc_cg_additive_inheritance',
            _('Contact group additive inheritance'),
            '&nbsp;'
        );
    } else {
        $form->addElement('checkbox', 'contact_additive_inheritance', '', _('Contact additive inheritance'));
        $form->addElement('checkbox', 'cg_additive_inheritance', '', _('Contact group additive inheritance'));
    }

    // Contacts
    $form->addElement('select2', 'service_cs', _('Implied Contacts'), [], $attributes['contacts']);

    // Contact groups
    $form->addElement('select2', 'service_cgs', _('Implied Contact Groups'), [], $attributes['contact_groups']);

    if ($o === SERVICE_MASSIVE_CHANGE) {
        $mc_mod_notifopt_first_notification_delay = [
            $form->createElement(
                'radio',
                'mc_mod_notifopt_first_notification_delay',
                null,
                _('Incremental'),
                '0'
            ),
            $form->createElement(
                'radio',
                'mc_mod_notifopt_first_notification_delay',
                null,
                _('Replacement'),
                '1'
            ),
        ];
        $form->addGroup(
            $mc_mod_notifopt_first_notification_delay,
            'mc_mod_notifopt_first_notification_delay',
            _('Update mode'),
            '&nbsp;'
        );
        $form->setDefaults(['mc_mod_notifopt_first_notification_delay' => '0']);
    }

    $form->addElement('text', 'service_first_notification_delay', _('First notification delay'), $attrsText2);

    $form->addElement('text', 'service_recovery_notification_delay', _('Recovery notification delay'), $attrsText2);

    if ($o === SERVICE_MASSIVE_CHANGE) {
        $mc_mod_notifopt_notification_interval = [
            $form->createElement(
                'radio',
                'mc_mod_notifopt_notification_interval',
                null,
                _('Incremental'),
                '0'
            ),
            $mc_mod_notifopt_notification_interval[] = $form->createElement(
                'radio',
                'mc_mod_notifopt_notification_interval',
                null,
                _('Replacement'),
                '1'
            ),
        ];
        $form->addGroup(
            $mc_mod_notifopt_notification_interval,
            'mc_mod_notifopt_notification_interval',
            _('Update mode'),
            '&nbsp;'
        );
        $form->setDefaults(['mc_mod_notifopt_notification_interval' => '0']);
    }

    $form->addElement('text', 'service_notification_interval', _('Notification Interval'), $attrsText2);

    if ($o === SERVICE_MASSIVE_CHANGE) {
        $mc_mod_notifopt_timeperiod = [
            $form->createElement(
                'radio',
                'mc_mod_notifopt_timeperiod',
                null,
                _('Incremental'),
                '0'
            ),
            $form->createElement(
                'radio',
                'mc_mod_notifopt_timeperiod',
                null,
                _('Replacement'),
                '1'
            ),
        ];
        $form->addGroup($mc_mod_notifopt_timeperiod, 'mc_mod_notifopt_timeperiod', _('Update mode'), '&nbsp;');
        $form->setDefaults(['mc_mod_notifopt_timeperiod' => '0']);
    }

    $form->addElement('select2', 'timeperiod_tp_id2', _('Notification Period'), [], $attributes['notification_periods']);

    if ($o === SERVICE_MASSIVE_CHANGE) {
        $mc_mod_notifopts = [
            $form->createElement('radio', 'mc_mod_notifopts', null, _('Incremental'), '0'),
            $form->createElement('radio', 'mc_mod_notifopts', null, _('Replacement'), '1'),
        ];
        $form->addGroup($mc_mod_notifopts, 'mc_mod_notifopts', _('Update mode'), '&nbsp;');
        $form->setDefaults(['mc_mod_notifopts' => '0']);
    }

    $serviceNotifOpt = [
        $form->createElement(
            'checkbox',
            'w',
            '&nbsp;',
            _('Warning'),
            ['id' => 'notifW', 'onClick' => 'uncheckNotifOption(this);']
        ),
        $form->createElement(
            'checkbox',
            'u',
            '&nbsp;',
            _('Unknown'),
            ['id' => 'notifU', 'onClick' => 'uncheckNotifOption(this);']
        ),
        $form->createElement(
            'checkbox',
            'c',
            '&nbsp;',
            _('Critical'),
            ['id' => 'notifC', 'onClick' => 'uncheckNotifOption(this);']
        ),
        $form->createElement(
            'checkbox',
            'r',
            '&nbsp;',
            _('Recovery'),
            ['id' => 'notifR', 'onClick' => 'uncheckNotifOption(this);']
        ),
        $form->createElement(
            'checkbox',
            'f',
            '&nbsp;',
            _('Flapping'),
            ['id' => 'notifF', 'onClick' => 'uncheckNotifOption(this);']
        ),
        $form->createElement(
            'checkbox',
            's',
            '&nbsp;',
            _('Downtime Scheduled'),
            ['id' => 'notifDS', 'onClick' => 'uncheckNotifOption(this);']
        ),
        $form->createElement(
            'checkbox',
            'n',
            '&nbsp;',
            _('None'),
            ['id' => 'notifN', 'onClick' => 'uncheckNotifOption(this);']
        ),
    ];
    $form->addGroup($serviceNotifOpt, 'service_notifOpts', _('Notification Type'), '&nbsp;&nbsp;');

    $serviceStalOpt = [
        $form->createElement('checkbox', 'o', '&nbsp,', _('Ok')),
        $form->createElement('checkbox', 'w', '&nbsp,', _('Warning')),
        $form->createElement('checkbox', 'u', '&nbsp,', _('Unknown')),
        $form->createElement('checkbox', 'c', '&nbsp,', _('Critical')),
    ];
    $form->addGroup($serviceStalOpt, 'service_stalOpts', _('Stalking Options'), '&nbsp;&nbsp;');

    $form->addElement('textarea', 'service_comment', _('Comments'), $attrsTextarea);
}

$serviceActivation = [
    $form->createElement('radio', 'service_activate', null, _('Enabled'), '1'),
    $form->createElement('radio', 'service_activate', null, _('Disabled'), '0'),
];
$form->addGroup($serviceActivation, 'service_activate', _('Enable/disable resource'), '&nbsp;');
if ($o !== SERVICE_MASSIVE_CHANGE) {
    $form->setDefaults(['service_activate' => '1']);
}
$form->addElement('text', 'service_max_check_attempts', _('Max Check Attempts'), $attrsText2);
$form->addElement('text', 'service_normal_check_interval', _('Normal Check Interval'), $attrsText2);
$form->addElement('text', 'service_retry_check_interval', _('Retry Check Interval'), $attrsText2);

$form->addElement('select2', 'timeperiod_tp_id', _('Check Period'), [], $attributes['check_periods']);

$cloneSetMacro = [
    $form->addElement(
        'text',
        'macroInput[#index#]',
        _('Name'),
        [
            'id' => 'macroInput_#index#',
            'size' => 25,
        ]
    ),
    $form->addElement(
        'text',
        'macroValue[#index#]',
        _('Value'),
        [
            'id' => 'macroValue_#index#',
            'size' => 25,
        ]
    ),
    $form->addElement(
        'checkbox',
        'macroPassword[#index#]',
        _('Password'),
        null,
        [
            'id' => 'macroPassword_#index#',
            'onClick' => 'javascript:change_macro_input_type(this, false)',
        ]
    ),
    $form->addElement(
        'hidden',
        'macroFrom[#index#]',
        'direct',
        ['id' => 'macroFrom_#index#']
    ),
];

if ($isCloudPlatform) {
    $form->addElement('header', 'information', _('General information'));
}

// Acknowledgement timeout.
$form->addElement('text', 'service_acknowledgement_timeout', _('Acknowledgement timeout'), $attrsText2);


// Further information
$form->addElement('header', 'furtherInfos', _('Additional Information'));


//
// # Sort 2 - Service Relations
//
if ($o === SERVICE_ADD) {
    $form->addElement('header', 'title2', _('Add relations'));
} elseif ($o === SERVICE_MODIFY) {
    $form->addElement('header', 'title2', _('Modify relations'));
} elseif ($o === SERVICE_WATCH) {
    $form->addElement('header', 'title2', _('View relations'));
} elseif ($o === SERVICE_MASSIVE_CHANGE) {
    $form->addElement('header', 'title2', _('Mass Change'));
}

if ($o === SERVICE_MASSIVE_CHANGE) {
    $mc_mod_Pars = [];
    $mc_mod_Pars[] = $form->createElement('radio', 'mc_mod_Pars', null, _('Incremental'), '0');
    $mc_mod_Pars[] = $form->createElement('radio', 'mc_mod_Pars', null, _('Replacement'), '1');
    $form->addGroup($mc_mod_Pars, 'mc_mod_Pars', _('Update mode'), '&nbsp;');
    $form->setDefaults(['mc_mod_Pars' => '0']);
}

$sgReadOnly = false;
if ($form_service_type === 'BYHOST') {
    if ($isCloudPlatform) {
        $defaultDataset = [];
        if ($service_id !== false) {
            $hostsBounded = findHostsOfService($service_id);
            $defaultDataset = ($hostsBounded !== [])
                ? ['0' => $hostsBounded[0]]
                : [];
        };
        $form->addElement(
            'select2',
            'service_hPars',
            _('Host'),
            [],
            array_merge($attributes['hosts_cloud_specific'], ['defaultDataset' => $defaultDataset])
        );
        $form->addRule('service_hPars', _("Host / Service Required"), 'required');
    } else {
        if (isset($service['service_hPars']) && count($service['service_hPars']) > 1) {
            $sgReadOnly = true;
        }

        $form->addElement('select2', 'service_hPars', _('Hosts'), [], $attributes['hosts']);
        $serviceHParsFieldIsAdded = true;
    }
}

if ($form_service_type === 'BYHOSTGROUP') {
    $form->addElement('select2', 'service_hgPars', _('Linked with Host Groups'), [], $attributes['host_groups']);
    $serviceHgParsFieldIsAdded = true;

    if (isset($service['service_hgPars']) && count($service['service_hgPars']) > 1) {
        $sgReadOnly = true;
    }
}

// Service relations
if ($isCloudPlatform) {
    $form->addElement('header', 'classification', _("Classification"));
} else {
    $form->addElement('header', 'links', _('Relations'));
}


if ($o === SERVICE_MASSIVE_CHANGE) {
    $mc_mod_sgs = [];
    $mc_mod_sgs[] = $form->createElement('radio', 'mc_mod_sgs', null, _('Incremental'), '0');
    $mc_mod_sgs[] = $form->createElement('radio', 'mc_mod_sgs', null, _('Replacement'), '1');
    $form->addGroup($mc_mod_sgs, 'mc_mod_sgs', _('Update mode'), '&nbsp;');
    $form->setDefaults(['mc_mod_sgs' => '0']);
}
$ams3 = $form->addElement('select2', 'service_sgs', _('Service groups'), [], $attributes['service_groups']);
if ($sgReadOnly === true) {
    $ams3->freeze();
}

$form->addElement('header', 'traps', _('SNMP Traps'));
if ($o === SERVICE_MASSIVE_CHANGE) {
    $mc_mod_traps = [];
    $mc_mod_traps[] = $form->createElement('radio', 'mc_mod_traps', null, _('Incremental'), '0');
    $mc_mod_traps[] = $form->createElement('radio', 'mc_mod_traps', null, _('Replacement'), '1');
    $form->addGroup($mc_mod_traps, 'mc_mod_traps', _('Update mode'), '&nbsp;');
    $form->setDefaults(['mc_mod_traps' => '0']);
}
$form->addElement('select2', 'service_traps', _('Service Trap Relation'), [], $attributes['traps']);

//
// # Sort 3 - Data treatment
//
if (! $isCloudPlatform) {
    if ($o === SERVICE_ADD) {
        $form->addElement('header', 'title3', _('Add Data Processing'));
    } elseif ($o === SERVICE_MODIFY) {
        $form->addElement('header', 'title3', _('Modify Data Processing'));
    } elseif ($o === SERVICE_WATCH) {
        $form->addElement('header', 'title3', _('View Data Processing'));
    } elseif ($o === SERVICE_MASSIVE_CHANGE) {
        $form->addElement('header', 'title2', _('Mass Change'));
    }

    $form->addElement('header', 'treatment', _('Data Processing'));

    $serviceOOS = [
        $form->createElement('radio', 'service_obsess_over_service', null, _('Yes'), '1'),
        $form->createElement('radio', 'service_obsess_over_service', null, _('No'), '0'),
        $form->createElement('radio', 'service_obsess_over_service', null, _('Default'), '2'),
    ];

    $form->addGroup($serviceOOS, 'service_obsess_over_service', _('Obsess Over Service'), '&nbsp;');
    if ($o !== SERVICE_MASSIVE_CHANGE) {
        $form->setDefaults(['service_obsess_over_service' => '2']);
    }

    $serviceCF = [
        $form->createElement('radio', 'service_check_freshness', null, _('Yes'), '1'),
        $form->createElement('radio', 'service_check_freshness', null, _('No'), '0'),
        $form->createElement('radio', 'service_check_freshness', null, _('Default'), '2'),
    ];
    $form->addGroup($serviceCF, 'service_check_freshness', _('Check Freshness'), '&nbsp;');
    if ($o !== SERVICE_MASSIVE_CHANGE) {
        $form->setDefaults(['service_check_freshness' => '2']);
    }

    $serviceFDE = [
        $form->createElement('radio', 'service_flap_detection_enabled', null, _('Yes'), '1'),
        $form->createElement('radio', 'service_flap_detection_enabled', null, _('No'), '0'),
        $form->createElement('radio', 'service_flap_detection_enabled', null, _('Default'), '2'),
    ];
    $form->addGroup($serviceFDE, 'service_flap_detection_enabled', _('Flap Detection Enabled'), '&nbsp;');
    if ($o !== SERVICE_MASSIVE_CHANGE) {
        $form->setDefaults(['service_flap_detection_enabled' => '2']);
    }

    $form->addElement('text', 'service_freshness_threshold', _('Freshness Threshold'), $attrsText2);
    $form->addElement('text', 'service_low_flap_threshold', _('Low Flap Threshold'), $attrsText2);
    $form->addElement('text', 'service_high_flap_threshold', _('High Flap Threshold'), $attrsText2);

    $serviceRSI = [
        $form->createElement('radio', 'service_retain_status_information', null, _('Yes'), '1'),
        $form->createElement('radio', 'service_retain_status_information', null, _('No'), '0'),
        $form->createElement('radio', 'service_retain_status_information', null, _('Default'), '2'),
    ];
    $form->addGroup($serviceRSI, 'service_retain_status_information', _('Retain Status Information'), '&nbsp;');
    if ($o !== SERVICE_MASSIVE_CHANGE) {
        $form->setDefaults(['service_retain_status_information' => '2']);
    }

    $serviceRNI = [
        $form->createElement('radio', 'service_retain_nonstatus_information', null, _('Yes'), '1'),
        $form->createElement('radio', 'service_retain_nonstatus_information', null, _('No'), '0'),
        $form->createElement('radio', 'service_retain_nonstatus_information', null, _('Default'), '2'),
    ];
    $form->addGroup($serviceRNI, 'service_retain_nonstatus_information', _('Retain Non Status Information'), '&nbsp;');
    if ($o !== SERVICE_MASSIVE_CHANGE) {
        $form->setDefaults(['service_retain_nonstatus_information' => '2']);
    }

    $form->addElement('header', 'nagios', _('Monitoring Engine'));
    $form->addElement('text', 'esi_icon_image_alt', _('Alt icon'), $attrsText);
}

// Sort 4 - Extended Infos
if ($o === SERVICE_ADD) {
    $form->addElement('header', 'title4', _('Add an Extended Info'));
} elseif ($o === SERVICE_MODIFY) {
    $form->addElement('header', 'title4', _('Modify an Extended Info'));
} elseif ($o === SERVICE_WATCH) {
    $form->addElement('header', 'title4', _('View an Extended Info'));
} elseif ($o === SERVICE_MASSIVE_CHANGE) {
    $form->addElement('header', 'title3', _('Mass Change'));
    $form->addElement('header', 'title4', _('Modify an Extended Info'));
}

$form->addElement('text', 'esi_notes', _('Note'), $attrsText);
$form->addElement('text', 'esi_notes_url', _('Note URL'), $attrsTextURL);
$form->addElement('text', 'esi_action_url', _('Action URL'), $attrsTextURL);
$form->addElement('select', 'esi_icon_image', _('Icon'), $extImg, [
    'id' => 'esi_icon_image',
    'onChange' => "showLogo('esi_icon_image_img',this.value)",
    'onkeyup' => 'this.blur();this.focus();',
]);

$form->registerRule('validate_geo_coords', 'function', 'validateGeoCoords');
$form->addElement('text', 'geo_coords', _('Geographic coordinates'), $attrsText);
$form->addRule('geo_coords', _('geo coords are not valid'), 'validate_geo_coords');

// Criticality
$criticality = new CentreonCriticality($pearDB);
$critList = $criticality->getList(null, 'level', 'ASC', null, null, true);
$criticalityIds = [null => null];
foreach ($critList as $critId => $critData) {
    $criticalityIds[$critId] = $critData['sc_name'] . ' (' . $critData['level'] . ')';
}
$form->addElement('select', 'criticality_id', _('Service severity'), $criticalityIds);

$form->addElement('header', 'oreon', _('Centreon'));

$form->addElement('select2', 'graph_id', _('Graph Template'), [], $attributes['graph_templates']);

if ($o === SERVICE_MASSIVE_CHANGE) {
    $mc_mod_sc = [];
    $mc_mod_sc[] = $form->createElement('radio', 'mc_mod_sc', null, _('Incremental'), '0');
    $mc_mod_sc[] = $form->createElement('radio', 'mc_mod_sc', null, _('Replacement'), '1');
    $form->addGroup($mc_mod_sc, 'mc_mod_sc', _('Update mode'), '&nbsp;');
    $form->setDefaults(['mc_mod_sc' => '0']);
}

$form->addElement('select2', 'service_categories', _('Service Categories'), [], $attributes['service_categories']);

// Sort 5
if ($o === SERVICE_ADD) {
    $form->addElement('header', 'title5', _('Add macros'));
} elseif ($o === SERVICE_MODIFY) {
    $form->addElement('header', 'title5', _('Modify macros'));
} elseif ($o === SERVICE_WATCH) {
    $form->addElement('header', 'title5', _('View macros'));
} elseif ($o === SERVICE_MASSIVE_CHANGE) {
    $form->addElement('header', 'title5', _('Mass Change'));
}

$form->addElement('header', 'macro', _('Macros'));

$form->addElement('text', 'add_new', _('Add a new macro'), $attrsText2);
$form->addElement('text', 'macroName', _('Name'), $attrsText2);
$form->addElement('text', 'macroValue', _('Value'), $attrsText2);
$form->addElement('text', 'macroDelete', _('Delete'), $attrsText2);

$form->addElement('hidden', 'service_id');
$reg = $form->addElement('hidden', 'service_register');
$reg->setValue('1');
$service_register = 1;
$page = $form->addElement('hidden', 'p');
$page->setValue($p);
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

$init = $form->addElement('hidden', 'initialValues');
$init->setValue(serialize($initialValues));

if (is_array($select)) {
    $select_pear = $form->addElement('hidden', 'select');
    $select_pear->setValue(implode(',', array_keys($select)));
}

// Form Rules
$form->applyFilter('__ALL__', 'myTrim');
$form->applyFilter('esi_notes', 'limitNotesLength');
$form->applyFilter('esi_notes_url', 'limitUrlLength');
$form->applyFilter('esi_action_url', 'limitUrlLength');

$from_list_menu = false;
if ($o !== SERVICE_MASSIVE_CHANGE) {
    $form->addRule('service_description', _('Compulsory Name'), 'required');
    // If we are using a Template, no need to check the value, we hope there are in the Template
    if (! $form->getSubmitValue('service_template_model_stm_id')) {
        if (! $isCloudPlatform) {
            $form->addRule('command_command_id', _('Compulsory Command'), 'required');
        }
        if (! $form->getSubmitValue('service_hPars') && $serviceHgParsFieldIsAdded) {
            $form->addRule('service_hgPars', _('HostGroup or Host Required'), 'required');
        }
        if (! $form->getSubmitValue('service_hgPars') && $serviceHParsFieldIsAdded) {
            $form->addRule('service_hPars', _('HostGroup or Host Required'), 'required');
        }
    }
    if (! $form->getSubmitValue('service_hPars') && $serviceHgParsFieldIsAdded) {
        $form->addRule('service_hgPars', _('HostGroup or Host Required'), 'required');
    }
    if (! $form->getSubmitValue('service_hgPars') && $serviceHParsFieldIsAdded) {
        $form->addRule('service_hPars', _('HostGroup or Host Required'), 'required');
    }
    $form->registerRule('exist', 'callback', 'testServiceExistence');
    $form->addRule(
        'service_description',
        _('This description is in conflict with another one that is already defined in the selected relation(s)'),
        'exist'
    );

    $argChecker = $form->addElement('hidden', 'argChecker');
    $argChecker->setValue(1);
    $form->registerRule('argHandler', 'callback', 'argHandler');
    $form->addRule('argChecker', _('You must either fill all the arguments or leave them all empty'), 'argHandler');

    $macChecker = $form->addElement('hidden', 'macChecker');
    $macChecker->setValue(1);
    $form->registerRule('macHandler', 'callback', 'serviceMacHandler');
    $form->addRule('macChecker', _('You cannot override reserved macros'), 'macHandler');

    $form->registerRule('cg_group_exists', 'callback', 'testCg2');

    if (! $isCloudPlatform) {
        $form->addRule(
            'service_cgs',
            _('Contactgroups exists. If you try to use a LDAP contactgroup, '
                . 'please verified if a Centreon contactgroup has the same name.'),
            'cg_group_exists'
        );
    }

    $form->setRequiredNote("<font style='color: red;'>*</font>&nbsp;" . _('Required fields'));
} elseif ($o === SERVICE_MASSIVE_CHANGE) {
    $from_list_menu = $form->getSubmitValue('submitMC') ? false : true;
}

if (isset($service['service_template_model_stm_id']) && ($service['service_template_model_stm_id'] === '')) {
    unset($service['service_template_model_stm_id']);
}

//
// #End of form definition
//
// Smarty template Init
$tpl = new Smarty();
$tpl = initSmartyTpl($path, $tpl);

$tpl->assign(
    'alert_check_interval',
    _('Warning, unconventional use of interval check. You should prefer to use an interval lower than 24h, '
        . 'if needed, pair this configuration with the use of timeperiods')
);

$tpl->assign(
    'alert_max_length_exceeded',
    _("Warning, maximum size exceeded for input '%s' (max: %d), it will be truncated upon saving")
);

// Just watch a host information
if ($o === SERVICE_WATCH) {
    if (! $min && $centreon->user->access->page($p) !== 2) {
        $form->addElement(
            'button',
            'change',
            _('Modify'),
            ['onClick' => "javascript:window.location.href='?p=" . $p . '&o=c&service_id=' . $service_id . "'"]
        );
    }
    $form->setDefaults($service);
    $form->freeze();
} elseif ($o === SERVICE_MODIFY) {
    // Modify a service information
    $subC = $form->addElement('submit', 'submitC', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement(
        'button',
        'reset',
        _('Reset'),
        ['onClick' => 'history.go(0);', 'class' => 'btc bt_default']
    );
    $form->setDefaults($service);
} elseif ($o === SERVICE_ADD) {
    // Add a service information
    $subA = $form->addElement('submit', 'submitA', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
} elseif ($o === SERVICE_MASSIVE_CHANGE) {
    // Massive Change
    $subMC = $form->addElement('submit', 'submitMC', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
}

$tpl->assign('msg', ['nagios' => $centreon->user->get_version(), 'tpl' => 0]);
$tpl->assign(
    'javascript',
    '<script type="text/javascript" src="./include/common/javascript/showLogo.js"></script>'
    . '<script type="text/javascript" src="./include/common/javascript/centreon/macroPasswordField.js"></script>'
    . '<script type="text/javascript" src="./include/common/javascript/centreon/macroLoadDescription.js"></script>'
);
$tpl->assign('time_unit', ' * ' . $centreon->optGen['interval_length'] . ' ' . _('seconds'));
$tpl->assign('p', $p);
$tpl->assign(
    'helpattr',
    'TITLE, "' . _('Help') . '", CLOSEBTN, true, FIX, [this, 0, 5], BGCOLOR, "#ffff99", BORDERCOLOR, '
    . '"orange", TITLEFONTCOLOR, "black", TITLEBGCOLOR, "orange", CLOSEBTNCOLORS, ["","black", "white", "red"], '
    . 'WIDTH, -300, SHADOW, true, TEXTALIGN, "justify"'
);

// prepare help texts
$helptext = '';
include_once 'help.php';
foreach ($help as $key => $text) {
    $helptext .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign('helptext', $helptext);

$valid = false;
if ($form->validate() && $from_list_menu === false) {
    $serviceObj = $form->getElement('service_id');
    if ($form->getSubmitValue('submitA')) {
        $serviceObj->setValue(insertServiceInDB());
    } elseif ($form->getSubmitValue('submitC')) {
        /*
         * Before saving, we check if a password macro has changed its name to be able to give it the right password
         * instead of wildcards (PASSWORD_REPLACEMENT_VALUE).
         */
        if (array_key_exists('macroInput', $_REQUEST)) {
            foreach ($_REQUEST['macroInput'] as $index => $macroName) {
                if (array_key_exists('macroOriginalName_' . $index, $_REQUEST)) {
                    $originalMacroName = $_REQUEST['macroOriginalName_' . $index];
                    if ($_REQUEST['macroValue'][$index] === PASSWORD_REPLACEMENT_VALUE) {
                        /*
                         * The password has not been changed along with the name, so its value is equal to the wildcard.
                         * We will therefore recover the password stored for its original name.
                         */
                        foreach ($aMacros as $indexMacro => $macroDetails) {
                            if ($macroDetails['macroInput_#index#'] === $originalMacroName) {
                                $_REQUEST['macroValue'][$index] = $macroPasswords[$indexMacro]['password'];
                                break;
                            }
                        }
                    }
                }
            }
        }
        updateServiceInDB($serviceObj->getValue());
    } elseif ($form->getSubmitValue('submitMC')) {
        foreach (array_keys($select) as $serviceIdToUpdate) {
            updateServiceInDB($serviceIdToUpdate, true);
        }
    }
    $o = SERVICE_WATCH;
    $valid = true;
} elseif ($form->isSubmitted()) {
    $tpl->assign('argChecker', "<font color='red'>" . $form->getElementError('argChecker') . '</font>');
    $tpl->assign('macChecker', "<font color='red'>" . $form->getElementError('macChecker') . '</font>');
}

require_once $path . 'javascript/argumentJs.php';

if ($valid) {
    if ($p === '60201') {
        require_once $path . 'listServiceByHost.php';
    } elseif ($p === '60202') {
        require_once $path . 'listServiceByHostGroup.php';
    } elseif ($p === '602') {
        require_once $path . 'listServiceByHost.php';
    }
} else {
    $dbResult = $pearDB->query('SELECT `value` FROM options WHERE `key` = "inheritance_mode"');
    $inheritanceMode = $dbResult->fetch();
    // Apply a template definition
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl, true);
    $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $form->accept($renderer);
    $tpl->assign('is_not_template', $service_register);
    $tpl->assign('form', $renderer->toArray());
    $tpl->assign('o', $o);
    $tpl->assign('inheritance', $inheritanceMode['value']);
    $tpl->assign('custom_macro_label', _('Custom macros'));
    $tpl->assign('template_inheritance', _('Template inheritance'));
    $tpl->assign('command_inheritance', _('Command inheritance'));
    $tpl->assign('cloneSetMacro', $cloneSetMacro);
    $tpl->assign('macros', $aMacros);
    $tpl->assign('centreon_path', $centreon->optGen['oreon_path']);
    $tpl->assign('Freshness_Control_options', _('Freshness Control options'));
    $tpl->assign('Flapping_Options', _('Flapping options'));
    $tpl->assign('History_Options', _('History Options'));
    $tpl->assign('Event_Handler', _('Event Handler'));
    $tpl->assign('topdoc', _('Documentation'));
    $tpl->assign('seconds', _('seconds'));

    $tpl->assign('v', $centreon->user->get_version());
    if (! $isCloudPlatform) {
        $tpl->assign('service_type', $form_service_type);
        $tpl->display('formServiceOnPrem.ihtml');
    } else {
        $tpl->display('formServiceCloud.ihtml');
    }
    ?>
    <script type="text/javascript">
        setTimeout('transformForm()', 200);
        showLogo('esi_icon_image_img', document.getElementById('esi_icon_image').value);

        function uncheckNotifOption(object) {
            if (object.id == "notifN" && object.checked) {
                document.getElementById('notifW').checked = false;
                document.getElementById('notifU').checked = false;
                document.getElementById('notifC').checked = false;
                document.getElementById('notifR').checked = false;
                document.getElementById('notifF').checked = false;
                document.getElementById('notifDS').checked = false;
            } else {
                document.getElementById('notifN').checked = false;
            }
        }
    </script>
<?php }
