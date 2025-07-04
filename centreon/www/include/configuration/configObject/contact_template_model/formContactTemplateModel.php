<?php
/*
 * Copyright 2005-2019 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
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

use Centreon\Infrastructure\Event\EventDispatcher;

if (!isset($centreon)) {
    exit();
}

require_once _CENTREON_PATH_ . 'www/class/centreonContactgroup.class.php';

$cct = [];
if (($o == "c" || $o == "w") && $contact_id) {
    /**
     * Init Tables informations
     */
    $cct["contact_hostNotifCmds"] = [];
    $cct["contact_svNotifCmds"] = [];
    $cct["contact_cgNotif"] = [];

    $statement = $pearDB->prepare("SELECT * FROM contact WHERE contact_id = :contactId LIMIT 1");
    $statement->bindValue(':contactId', $contact_id, \PDO::PARAM_INT);
    $statement->execute();
    $cct = array_map("myDecode", $statement->fetchRow());
    $statement->closeCursor();

    /**
     * Set Host Notification Options
     */
    $tmp = explode(',', $cct["contact_host_notification_options"]);
    foreach ($tmp as $key => $value) {
        $cct["contact_hostNotifOpts"][trim($value)] = 1;
    }

    /**
     * Set Service Notification Options
     */
    $tmp = explode(',', $cct["contact_service_notification_options"]);
    foreach ($tmp as $key => $value) {
        $cct["contact_svNotifOpts"][trim($value)] = 1;
    }

    /**
     * Set Host Notification Commands
     */
    $statement = $pearDB->prepare(<<<SQL
        SELECT DISTINCT command_command_id FROM contact_hostcommands_relation
        WHERE contact_contact_id = :contactId
        SQL
    );
    $statement->bindValue(':contactId', $contact_id, \PDO::PARAM_INT);
    $statement->execute();
    for ($i = 0; $notifCmd = $statement->fetchRow(); $i++) {
        $cct["contact_hostNotifCmds"][$i] = $notifCmd["command_command_id"];
    }
    $statement->closeCursor();

    /**
     * Set Service Notification Commands
     */
    $statement = $pearDB->prepare(<<<SQL
        SELECT DISTINCT command_command_id FROM contact_servicecommands_relation
        WHERE contact_contact_id = :contactId
        SQL
    );
    $statement->bindValue(':contactId', $contact_id, \PDO::PARAM_INT);
    $statement->execute();
    for ($i = 0; $notifCmd = $statement->fetchRow(); $i++) {
        $cct["contact_svNotifCmds"][$i] = $notifCmd["command_command_id"];
    }
    $statement->closeCursor();
}

/**
 * Get Langs
 */
$langs = [];
$langs = getLangs();
if ($o == "mc") {
    array_unshift($langs, null);
}

/**
 * Timeperiods comes from DB -> Store in $notifsTps Array
 * When we make a massive change, give the possibility to not crush value
 */
$notifTps = [null => null];
$DBRESULT = $pearDB->query("SELECT tp_id, tp_name FROM timeperiod ORDER BY tp_name");
while ($notifTp = $DBRESULT->fetchRow()) {
    $notifTps[$notifTp["tp_id"]] = $notifTp["tp_name"];
}
$DBRESULT->closeCursor();

/**
 * Notification commands comes from DB -> Store in $notifsCmds Array
 */
$notifCmds = [];
$query = "SELECT command_id, command_name FROM command WHERE command_type = '1' ORDER BY command_name";
$DBRESULT = $pearDB->query($query);
while ($notifCmd = $DBRESULT->fetchRow()) {
    $notifCmds[$notifCmd["command_id"]] = $notifCmd["command_name"];
}
$DBRESULT->closeCursor();

/**
 * Contacts Templates
 */
$strRestrinction = isset($contact_id) ? " AND contact_id != :contactId " : "";

$contactTpl = [null => ""];
$statement = $pearDB->prepare(<<<SQL
    SELECT contact_id, contact_name FROM contact
    WHERE contact_register = '0' $strRestrinction ORDER BY contact_name
    SQL
);
if (! empty($strRestrinction)) {
    $statement->bindValue(':contactId', $contact_id, \PDO::PARAM_INT);
}
$statement->execute();

while ($contacts = $statement->fetchRow()) {
    $contactTpl[$contacts["contact_id"]] = $contacts["contact_name"];
}
$statement->closeCursor();

/**
 * Template / Style for Quickform input
 */
$attrsText = ["size" => "30"];
$attrsText2 = ["size" => "60"];
$attrsTextDescr = ["size" => "80"];
$attrsTextMail = ["size" => "90"];
$attrsAdvSelect = ["style" => "width: 300px; height: 100px;"];
$attrsTextarea = ["rows" => "15", "cols" => "100"];
$eTemplate = '<table><tr><td><div class="ams">{label_2}</div>{unselected}</td><td align="center">{add}<br /><br />' .
    '<br />{remove}</td><td><div class="ams">{label_3}</div>{selected}</td></tr></table>';
$route = './include/common/webServices/rest/internal.php?object=centreon_configuration_timeperiod&action=list';
$attrTimeperiods = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $route, 'multiple' => false, 'linkedObject' => 'centreonTimeperiod'];
$attrCommands = ['datasourceOrigin' => 'ajax', 'multiple' => true, 'linkedObject' => 'centreonCommand'];


$form = new HTML_QuickFormCustom('Form', 'post', "?p=" . $p);

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

// prepare event data
$eventData = [
    'form' => $form,
    'tpl' => $tpl,
    'contact_id' => $contact_id
];

switch ($o) {
    case 'a':
        $form->addElement('header', 'title', _("Add a User Template"));
        $eventDispatcher->notify($eventContext, EventDispatcher::EVENT_DISPLAY, $eventData);
        break;
    case 'c':
        $form->addElement('header', 'title', _("Modify a User Template"));
        $eventDispatcher->notify($eventContext, EventDispatcher::EVENT_READ, $eventData);
        break;
    case 'w':
        $form->addElement('header', 'title', _("View a User Template"));
        $eventDispatcher->notify($eventContext, EventDispatcher::EVENT_READ, $eventData);
        break;
    case 'mc':
        $form->addElement('header', 'title', _("Mass Change"));
        $eventDispatcher->notify($eventContext, EventDispatcher::EVENT_DISPLAY, $eventData);
        break;
}

/**
 * Contact basic information
 */
$form->addElement('header', 'information', _("General Information"));
$form->addElement('header', 'additional', _("Additional Information"));
$form->addElement('header', 'centreon', _("Centreon Authentication"));

/**
 * Don't change contact name and alias in massif change
 * Don't change contact name, alias or autologin key in massive change
 */
if ($o != "mc") {
    $form->addElement('text', 'contact_name', _("Full Name"), $attrsTextDescr);
    $form->addElement('text', 'contact_alias', _("Alias / Login"), $attrsText);
}

/**
 * Contact template used
 */
$form->addElement('select', 'contact_template_id', _("Contact template used"), $contactTpl);

/* ------------------------ Topoogy ---------------------------- */
$pages = [];
$aclUser = $centreon->user->lcaTStr;
if ($aclUser !== '') {
    $acls = array_flip(explode(',', $aclUser));
    /**
     * Transform [1, 2, 101, 202, 10101, 20201] to :
     *
     * 1
     *   101
     *     10101
     * 2
     *   202
     *     20201
     */
    $createTopologyTree = function (array $topologies): array {
        ksort($topologies, \SORT_ASC);
        $parentsLvl = [];

        // Classify topologies by parents
        foreach (array_keys($topologies) as $page) {
            if (strlen($page) === 1) {
                // MENU level 1
                if (! array_key_exists($page, $parentsLvl)) {
                    $parentsLvl[$page] = [];
                }
            } elseif (strlen($page) === 3) {
                // MENU level 2
                $parentLvl1 = substr($page, 0, 1);
                if (! array_key_exists($parentLvl1, $parentsLvl)) {
                    $parentsLvl[$parentLvl1] = [];
                }
                if (! array_key_exists($page, $parentsLvl[$parentLvl1])) {
                    $parentsLvl[$parentLvl1][$page] = [];
                }
            } elseif (strlen($page) === 5) {
                // MENU level 3
                $parentLvl1 = substr($page, 0, 1);
                $parentLvl2 = substr($page, 0, 3);
                if (! array_key_exists($parentLvl1, $parentsLvl)) {
                    $parentsLvl[$parentLvl1] = [];
                }
                if (! array_key_exists($parentLvl2, $parentsLvl[$parentLvl1])) {
                    $parentsLvl[$parentLvl1][$parentLvl2] = [];
                }
                if (! in_array($page, $parentsLvl[$parentLvl1][$parentLvl2])) {
                    $parentsLvl[$parentLvl1][$parentLvl2][] = $page;
                }
            }
        }

        return $parentsLvl;
    };

    /**
     * Check if at least one child can be shown
     */
    $oneChildCanBeShown = function () use (&$childrenLvl3, &$translatedPages): bool {
        foreach ($childrenLvl3 as $topologyPage) {
            if ($translatedPages[$topologyPage]['show']) {
              return true;
            }
        }
        return false;
    };

    $topologies = $createTopologyTree($acls);

    /**
     * Retrieve the name of all topologies available for this user
     */
    $aclTopologies = $pearDB->query(
        "SELECT topology_page, topology_name, topology_show "
        . "FROM topology "
        . "WHERE topology_page IN ($aclUser)"
    );

    $translatedPages = [];

    while ($topology = $aclTopologies->fetch(\PDO::FETCH_ASSOC)) {
        $translatedPages[$topology['topology_page']] = [
            'i18n' => _($topology['topology_name']),
            'show' => ((int)$topology['topology_show'] === 1)
        ];
    }

    /**
     * Create flat tree for menu with the topologies names
     * [item1Id] = menu1 > submenu1 > item1
     * [item2Id] = menu2 > submenu2 > item2
     */
    foreach ($topologies as $parentLvl1 => $childrenLvl2) {
        $parentNameLvl1 = $translatedPages[$parentLvl1]['i18n'];
        foreach ($childrenLvl2 as $parentLvl2 => $childrenLvl3) {
            $parentNameLvl2 = $translatedPages[$parentLvl2]['i18n'];
            $isThirdLevelMenu = false;
            $parentLvl3 = null;

            if ($oneChildCanBeShown()) {
                /**
                 * There is at least one child that can be shown then we can
                 * process the third level
                 */
                foreach ($childrenLvl3 as $parentLvl3) {
                    if ($translatedPages[$parentLvl3]['show']) {
                        $parentNameLvl3 = $translatedPages[$parentLvl3]['i18n'];

                        if ($parentNameLvl2 === $parentNameLvl3) {
                            /**
                             * The name between lvl2 and lvl3 are equals.
                             * We keep only lvl1 and lvl3
                             */
                            $pages[$parentLvl3] = $parentNameLvl1 . ' > '
                                . $parentNameLvl3;
                        } else {
                            $pages[$parentLvl3] = $parentNameLvl1 . ' > '
                                . $parentNameLvl2 . ' > '
                                . $parentNameLvl3;
                        }
                    }
                }

                $isThirdLevelMenu = true;
            }

            // select parent from level 2 if level 3 is missing
            $pageId = $parentLvl3 ?? $parentLvl2;

            if (! $isThirdLevelMenu && $translatedPages[$pageId]['show']) {
                /**
                 * We show only first and second level
                 */
                $pages[$pageId] =
                    $parentNameLvl1 . ' > ' . $parentNameLvl2;
            }
        }
    }
}

$form->addElement('select', 'default_page', _("Default page"), $pages);
$form->addElement('header', 'furtherAddress', _("Additional Addresses"));
$form->addElement('text', 'contact_address1', _("Address1"), $attrsText);
$form->addElement('text', 'contact_address2', _("Address2"), $attrsText);
$form->addElement('text', 'contact_address3', _("Address3"), $attrsText);
$form->addElement('text', 'contact_address4', _("Address4"), $attrsText);
$form->addElement('text', 'contact_address5', _("Address5"), $attrsText);
$form->addElement('text', 'contact_address6', _("Address6"), $attrsText);

/**
 * Notification informations
 */
$form->addElement('header', 'notification', _("Notification"));

$tab = [];
$tab[] = $form->createElement('radio', 'contact_enable_notifications', null, _("Yes"), '1');
$tab[] = $form->createElement('radio', 'contact_enable_notifications', null, _("No"), '0');
$tab[] = $form->createElement('radio', 'contact_enable_notifications', null, _("Default"), '2');
$form->addGroup($tab, 'contact_enable_notifications', _("Enable Notifications"), '&nbsp;');
if ($o != "mc") {
    $form->setDefaults(['contact_enable_notifications' => '2']);
}

/** * *****************************
 * Host notifications
 */
$form->addElement('header', 'hostNotification', _("Host"));
$hostNotifOpt[] = $form->createElement(
    'checkbox',
    'd',
    '&nbsp;',
    _("Down"),
    ['id' => 'hDown', 'onClick' => 'uncheckAllH(this);']
);
$hostNotifOpt[] = $form->createElement(
    'checkbox',
    'u',
    '&nbsp;',
    _("Unreachable"),
    ['id' => 'hUnreachable', 'onClick' => 'uncheckAllH(this);']
);
$hostNotifOpt[] = $form->createElement(
    'checkbox',
    'r',
    '&nbsp;',
    _("Recovery"),
    ['id' => 'hRecovery', 'onClick' => 'uncheckAllH(this);']
);
$hostNotifOpt[] = $form->createElement(
    'checkbox',
    'f',
    '&nbsp;',
    _("Flapping"),
    ['id' => 'hFlapping', 'onClick' => 'uncheckAllH(this);']
);
$hostNotifOpt[] = $form->createElement(
    'checkbox',
    's',
    '&nbsp;',
    _("Downtime Scheduled"),
    ['id' => 'hScheduled', 'onClick' => 'uncheckAllH(this);']
);
$hostNotifOpt[] = $form->createElement(
    'checkbox',
    'n',
    '&nbsp;',
    _("None"),
    ['id' => 'hNone', 'onClick' => 'javascript:uncheckAllH(this);']
);
$form->addGroup($hostNotifOpt, 'contact_hostNotifOpts', _("Host Notification Options"), '&nbsp;&nbsp;');

$route = './include/common/webServices/rest/internal.php?object=centreon_configuration_timeperiod' .
    '&action=defaultValues&target=contact&field=timeperiod_tp_id&id=' . $contact_id;
$attrTimeperiod1 = array_merge(
    $attrTimeperiods,
    ['defaultDatasetRoute' => $route]
);
$form->addElement('select2', 'timeperiod_tp_id', _("Host Notification Period"), [], $attrTimeperiod1);

unset($hostNotifOpt);

if ($o == "mc") {
    $mc_mod_hcmds = [];
    $mc_mod_hcmds[] = $form->createElement('radio', 'mc_mod_hcmds', null, _("Incremental"), '0');
    $mc_mod_hcmds[] = $form->createElement('radio', 'mc_mod_hcmds', null, _("Replacement"), '1');
    $form->addGroup($mc_mod_hcmds, 'mc_mod_hcmds', _("Update mode"), '&nbsp;');
    $form->setDefaults(['mc_mod_hcmds' => '0']);
}

$defaultRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_command' .
    '&action=defaultValues&target=contact&field=contact_hostNotifCmds&id=' . $contact_id;
$availableRoute = './include/common/webServices/rest/internal.php' .
    '?object=centreon_configuration_command&action=list&t=1';
$attrCommand1 = array_merge(
    $attrCommands,
    ['defaultDatasetRoute' => $defaultRoute, 'availableDatasetRoute' => $availableRoute]
);
$form->addElement('select2', 'contact_hostNotifCmds', _("Host Notification Commands"), [], $attrCommand1);

/** * *****************************
 * Service notifications
 */
$form->addElement('header', 'serviceNotification', _("Service"));
$svNotifOpt[] = $form->createElement(
    'checkbox',
    'w',
    '&nbsp;',
    _("Warning"),
    ['id' => 'sWarning', 'onClick' => 'uncheckAllS(this);']
);
$svNotifOpt[] = $form->createElement(
    'checkbox',
    'u',
    '&nbsp;',
    _("Unknown"),
    ['id' => 'sUnknown', 'onClick' => 'uncheckAllS(this);']
);
$svNotifOpt[] = $form->createElement(
    'checkbox',
    'c',
    '&nbsp;',
    _("Critical"),
    ['id' => 'sCritical', 'onClick' => 'uncheckAllS(this);']
);
$svNotifOpt[] = $form->createElement(
    'checkbox',
    'r',
    '&nbsp;',
    _("Recovery"),
    ['id' => 'sRecovery', 'onClick' => 'uncheckAllS(this);']
);
$svNotifOpt[] = $form->createElement(
    'checkbox',
    'f',
    '&nbsp;',
    _("Flapping"),
    ['id' => 'sFlapping', 'onClick' => 'uncheckAllS(this);']
);
$svNotifOpt[] = $form->createElement(
    'checkbox',
    's',
    '&nbsp;',
    _("Downtime Scheduled"),
    ['id' => 'sScheduled', 'onClick' => 'uncheckAllS(this);']
);
$svNotifOpt[] = $form->createElement(
    'checkbox',
    'n',
    '&nbsp;',
    _("None"),
    ['id' => 'sNone', 'onClick' => 'uncheckAllS(this);']
);
$form->addGroup($svNotifOpt, 'contact_svNotifOpts', _("Service Notification Options"), '&nbsp;&nbsp;');

$route = './include/common/webServices/rest/internal.php?object=centreon_configuration_timeperiod' .
    '&action=defaultValues&target=contact&field=timeperiod_tp_id2&id=' . $contact_id;
$attrTimeperiod2 = array_merge(
    $attrTimeperiods,
    ['defaultDatasetRoute' => $route]
);
$form->addElement('select2', 'timeperiod_tp_id2', _("Service Notification Period"), [], $attrTimeperiod2);

if ($o == "mc") {
    $mc_mod_svcmds = [];
    $mc_mod_svcmds[] = $form->createElement('radio', 'mc_mod_svcmds', null, _("Incremental"), '0');
    $mc_mod_svcmds[] = $form->createElement('radio', 'mc_mod_svcmds', null, _("Replacement"), '1');
    $form->addGroup($mc_mod_svcmds, 'mc_mod_svcmds', _("Update mode"), '&nbsp;');
    $form->setDefaults(['mc_mod_svcmds' => '0']);
}

$defaultRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_command' .
    '&action=defaultValues&target=contact&field=contact_svNotifCmds&id=' . $contact_id;
$availableRoute = './include/common/webServices/rest/internal.php' .
    '?object=centreon_configuration_command&action=list&t=1';

$attrCommand2 = array_merge(
    $attrCommands,
    ['defaultDatasetRoute' => $defaultRoute, 'availableDatasetRoute' => $availableRoute]
);
$form->addElement('select2', 'contact_svNotifCmds', _("Service Notification Commands"), [], $attrCommand2);

/**
 * Further informations
 */
$form->addElement('header', 'furtherInfos', _("Additional Information"));
$cctActivation[] = $form->createElement('radio', 'contact_activate', null, _("Enabled"), '1');
$cctActivation[] = $form->createElement('radio', 'contact_activate', null, _("Disabled"), '0');
$form->addGroup($cctActivation, 'contact_activate', _("Status"), '&nbsp;');
$form->setDefaults(['contact_activate' => '1']);
if ($o == "c" && $centreon->user->get_id() == $cct["contact_id"]) {
    $form->freeze('contact_activate');
}

$form->addElement('hidden', 'contact_register');
$form->setDefaults(['contact_register' => '0']);

$form->addElement('textarea', 'contact_comment', _("Comments"), $attrsTextarea);

$form->addElement('hidden', 'contact_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

if (is_array($select)) {
    $select_str = ! empty($select)
        ? implode(",", array_keys($select))
        : null;
    $select_pear = $form->addElement('hidden', 'select');
    $select_pear->setValue($select_str);
}

/**
 * Form Rules
 */
function myReplace()
{
    global $form;
    $ret = $form->getSubmitValues();
    return (str_replace(" ", "_", $ret["contact_name"]));
}

$form->applyFilter('__ALL__', 'myTrim');
$form->applyFilter('contact_name', 'myReplace');
$from_list_menu = false;
if ($o != "mc") {
    $ret = $form->getSubmitValues();
    $form->addRule('contact_name', _("Compulsory Name"), 'required');
    $form->addRule('contact_alias', _("Compulsory Alias"), 'required');

    if (isset($ret["contact_enable_notifications"]["contact_enable_notifications"]) &&
        $ret["contact_enable_notifications"]["contact_enable_notifications"] == 1
    ) {
        if (isset($ret["contact_template_id"]) && $ret["contact_template_id"] == '') {
            $form->addRule('timeperiod_tp_id', _("Compulsory Period"), 'required');
            $form->addRule('timeperiod_tp_id2', _("Compulsory Period"), 'required');
            $form->addRule('contact_hostNotifOpts', _("Compulsory Option"), 'required');
            $form->addRule('contact_svNotifOpts', _("Compulsory Option"), 'required');
            $form->addRule('contact_hostNotifCmds', _("Compulsory Command"), 'required');
            $form->addRule('contact_svNotifCmds', _("Compulsory Command"), 'required');
        }
    }
    $form->registerRule('exist', 'callback', 'testContactExistence');
    $form->addRule('contact_name', "<font style='color: red;'>*</font>&nbsp;" . _("Contact already exists"), 'exist');
    $form->registerRule('existAlias', 'callback', 'testAliasExistence');
    $form->addRule(
        'contact_alias',
        "<font style='color: red;'>*</font>&nbsp;" . _("Alias already exists"),
        'existAlias'
    );
} elseif ($o == "mc") {
    $from_list_menu = $form->getSubmitValue("submitMC") ? false : true;
}
$form->setRequiredNote("<font style='color: red;'>*</font>&nbsp;" . _("Required fields"));

$tpl->assign(
    "helpattr",
    'TITLE, "' . _("Help") . '", CLOSEBTN, true, FIX, [this, 0, 5], BGCOLOR, "#ffff99", BORDERCOLOR, ' .
    '"orange", TITLEFONTCOLOR, "black", TITLEBGCOLOR, "orange", CLOSEBTNCOLORS, ["","black", "white", "red"], ' .
    'WIDTH, -300, SHADOW, true, TEXTALIGN, "justify"'
);

// prepare help texts
$helptext = "";
include_once(_CENTREON_PATH_ . "/www/include/configuration/configObject/contact/help.php");
foreach ($help as $key => $text) {
    $helptext .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign("helptext", $helptext);

if ($o == "w") {
    // Just watch a contact information
    if ($centreon->user->access->page($p) != 2) {
        $form->addElement(
            "button",
            "change",
            _("Modify"),
            ["onClick" => "javascript:window.location.href='?p=" . $p . "&o=c&contact_id=" . $contact_id . "'"]
        );
    }
    $form->setDefaults($cct);
    $form->freeze();
} elseif ($o == "c") {
    // Modify a contact information
    $subC = $form->addElement('submit', 'submitC', _("Save"), ["class" => "btc bt_success"]);
    $res = $form->addElement('reset', 'reset', _("Reset"), ["class" => "btc bt_default"]);
    $form->setDefaults($cct);
} elseif ($o == "a") {
    // Add a contact information
    $subA = $form->addElement('submit', 'submitA', _("Save"), ["class" => "btc bt_success"]);
    $res = $form->addElement('reset', 'reset', _("Reset"), ["class" => "btc bt_default"]);
} elseif ($o == "mc") {
    // Massive Change
    $subMC = $form->addElement('submit', 'submitMC', _("Save"), ["class" => "btc bt_success"]);
    $res = $form->addElement('reset', 'reset', _("Reset"), ["class" => "btc bt_default"]);
}

$valid = false;
if ($form->validate() && $from_list_menu == false) {
    $cctObj = $form->getElement('contact_id');
    $eventData = [
        'form' => $form,
        'contact_id' => $cctObj->getValue()
    ];

    if ($form->getSubmitValue("submitA")) {
        $newContactId = insertContactInDB();
        $cctObj->setValue($newContactId);
        $eventData['contact_id'] = $newContactId;

        $eventDispatcher->notify($eventContext, EventDispatcher::EVENT_ADD, $eventData);
    } elseif ($form->getSubmitValue("submitC")) {
        updateContactInDB($cctObj->getValue());

        $eventDispatcher->notify($eventContext, EventDispatcher::EVENT_UPDATE, $eventData);
    } elseif ($form->getSubmitValue("submitMC")) {
        if (! is_array($select)) {
            $select = explode(",", $select);
        }
        foreach ($select as $key => $value) {
            if ($value) {
                updateContactInDB($value, true);

                $eventDispatcher->notify($eventContext, EventDispatcher::EVENT_UPDATE, $eventData);
            }
        }
    }

    $o = null;
    $valid = true;
}

if ($valid) {
    require_once($path . "listContactTemplateModel.php");
} else {
    // Apply a template definition
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl, true);
    $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $form->accept($renderer);
    $tpl->assign('form', $renderer->toArray());
    $tpl->assign('o', $o);
    $tpl->display("formContactTemplateModel.ihtml");
}
?>
<script type="text/javascript">
    function uncheckAllH(object) {
        if (object.id == "hNone" && object.checked) {
            document.getElementById('hDown').checked = false;
            document.getElementById('hUnreachable').checked = false;
            document.getElementById('hRecovery').checked = false;
            if (document.getElementById('hFlapping')) {
                document.getElementById('hFlapping').checked = false;
            }
            if (document.getElementById('hScheduled')) {
                document.getElementById('hScheduled').checked = false;
            }
        } else {
            document.getElementById('hNone').checked = false;
        }
    }

    function uncheckAllS(object) {
        if (object.id == "sNone" && object.checked) {
            document.getElementById('sWarning').checked = false;
            document.getElementById('sUnknown').checked = false;
            document.getElementById('sCritical').checked = false;
            document.getElementById('sRecovery').checked = false;
            if (document.getElementById('sFlapping')) {
                document.getElementById('sFlapping').checked = false;
            }
            if (document.getElementById('sScheduled')) {
                document.getElementById('sScheduled').checked = false;
            }
        } else {
            document.getElementById('sNone').checked = false;
        }
    }
</script>
