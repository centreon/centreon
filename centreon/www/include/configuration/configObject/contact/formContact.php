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
require_once __DIR__ . '/../../../../class/centreonContact.class.php';

use Centreon\Infrastructure\Event\EventDispatcher;

if (!isset($centreon)) {
    exit();
}

if (!$centreon->user->admin && $contactId) {
    $aclOptions = ['fields' => ['contact_id', 'contact_name'], 'keys' => ['contact_id'], 'get_row' => 'contact_name', 'conditions' => ['contact_id' => $contactId]];
    $contacts = $acl->getContactAclConf($aclOptions);
    if (!count($contacts)) {
        $msg = new CentreonMsg();
        $msg->setImage("./img/icons/warning.png");
        $msg->setTextStyle("bold");
        $msg->setText(_('You are not allowed to access this contact'));
        return null;
    }
}

$cgs = $acl->getContactGroupAclConf(
    ['fields' => ['cg_id', 'cg_name'], 'keys' => ['cg_id'], 'get_row' => 'cg_name', 'order' => ['cg_name']]
);

require_once _CENTREON_PATH_ . 'www/class/centreonLDAP.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonContactgroup.class.php';

$initialValues = [];

/*
 * Check if this server is a Remote Server to hide some part of form
 */
$dbResult = $pearDB->query("SELECT i.value FROM informations i WHERE i.key = 'isRemote'");
$result = $dbResult->fetch();
if ($result === false) {
    $isRemote = false;
} else {
    $isRemote = array_map("myDecode", $result);
    $isRemote = $isRemote['value'] === 'yes';
}
$dbResult->closeCursor();

/**
 * Get the Security Policy for automatic generation password.
 */
try {
    $passwordSecurityPolicy = (new CentreonContact($pearDB))->getPasswordSecurityPolicy();
    $encodedPasswordPolicy = json_encode($passwordSecurityPolicy);
} catch (\PDOException $e) {
    return false;
}

$cct = [];
if (($o == MODIFY_CONTACT || $o == WATCH_CONTACT) && $contactId) {
    /**
     * Init Tables informations
     */
    $cct["contact_hostNotifCmds"] = [];
    $cct["contact_svNotifCmds"] = [];
    $cct["contact_cgNotif"] = [];

    $dbResult = $pearDB->prepare("SELECT * FROM contact WHERE contact_id = :contactId LIMIT 1");
    $dbResult->bindValue(':contactId', $contactId, \PDO::PARAM_INT);
    $dbResult->execute();
    $cct = array_map("myDecode", $dbResult->fetch());
    $cct["contact_passwd"] = null;
    $dbResult->closeCursor();

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
    $DBRESULT->closeCursor();

    /**
     * Get DLAP auth informations
     */
    $DBRESULT = $pearDB->query("SELECT * FROM `options` WHERE `key` = 'ldap_auth_enable'");
    while ($ldap_auths = $DBRESULT->fetchRow()) {
        $ldap_auth[$ldap_auths["key"]] = myDecode($ldap_auths["value"]);
    }
    $DBRESULT->closeCursor();

    /**
     * Get ACL informations for this user
     */
    $DBRESULT = $pearDB->query("SELECT acl_group_id
                                FROM `acl_group_contacts_relations`
                                WHERE `contact_contact_id` = '" . intval($contactId) . "'");
    for ($i = 0; $data = $DBRESULT->fetchRow(); $i++) {
        if (!$centreon->user->admin && !isset($allowedAclGroups[$data['acl_group_id']])) {
            $initialValues['contact_acl_groups'][] = $data['acl_group_id'];
        } else {
            $cct["contact_acl_groups"][$i] = $data["acl_group_id"];
        }
    }
    $DBRESULT->closeCursor();
}

/**
 * Get Langs
 */
$langs = [];
$langs = getLangs();
if ($o == MASSIVE_CHANGE) {
    array_unshift($langs, null);
}

/**
 * Contact Groups come from DB -> Store in $notifCcts Array
 */
$notifCgs = [];

$cg = new CentreonContactgroup($pearDB);
$notifCgs = $cg->getListContactgroup(false);

if (
    $centreon->optGen['ldap_auth_enable'] == 1
    && !empty($cct['contact_id'])
    && $cct['contact_auth_type'] === 'ldap'
    && !empty($cct['ar_id'])
    && !empty($cct['contact_ldap_dn'])
) {
    $ldap = new CentreonLDAP($pearDB, null, $cct['ar_id']);
    if (false !== $ldap->connect()) {
        $cgLdap = $ldap->listGroupsForUser($cct['contact_ldap_dn']);
    }
}

/**
 * Contacts Templates
 */
$strRestrinction = isset($contactId) ? " AND contact_id != '" . intval($contactId) . "'" : "";

$contactTpl = [null => "           "];
$DBRESULT = $pearDB->query("SELECT contact_id, contact_name
                            FROM contact
                            WHERE contact_register = '0' $strRestrinction
                            ORDER BY contact_name");
while ($contacts = $DBRESULT->fetchRow()) {
    $contactTpl[$contacts["contact_id"]] = $contacts["contact_name"];
}
$DBRESULT->closeCursor();

/**
 * Template / Style for Quickform input
 */
$attrsText = ["size" => "30"];
$attrsText2 = ["size" => "60"];
$attrsTextDescr = ["size" => "80"];
$attrsTextMail = ["size" => "90"];
$attrsAdvSelect = ["style" => "width: 300px; height: 100px;"];
$attrsTextarea = ["rows" => "15", "cols" => "100"];
$eTemplate = '<table><tr><td><div class="ams">{label_2}</div>{unselected}</td><td align="center">{add}<br /><br />'
    . '<br />{remove}</td><td><div class="ams">{label_3}</div>{selected}</td></tr></table>';
$timeRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_timeperiod&action=list';
$attrTimeperiods = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $timeRoute, 'multiple' => false, 'linkedObject' => 'centreonTimeperiod'];
$attrCommands = ['datasourceOrigin' => 'ajax', 'multiple' => true, 'linkedObject' => 'centreonCommand'];
$contactRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_contactgroup&action=list&type=local';
$attrContactgroups = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $contactRoute, 'multiple' => true, 'linkedObject' => 'centreonContactgroup'];
$aclRoute = './include/common/webServices/rest/internal.php?object=centreon_administration_aclgroup&action=list';
$attrAclgroups = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $aclRoute, 'multiple' => true, 'linkedObject' => 'centreonAclGroup'];

$form = new HTML_QuickFormCustom('Form', 'post', "?p=" . $p);

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

/**
 * @var $moduleFormManager \Centreon\Domain\Service\ModuleFormManager
 */

if ($o == ADD_CONTACT) {
    $form->addElement('header', 'title', _("Add a User"));

    $eventDispatcher->notify(
        'contact.form',
        EventDispatcher::EVENT_DISPLAY,
        [
            'form' => $form,
            'tpl' => $tpl,
            'contact_id' => $contactId
        ]
    );
} elseif ($o == MODIFY_CONTACT) {
    $form->addElement('header', 'title', _("Modify a User"));

    $eventDispatcher->notify(
        'contact.form',
        EventDispatcher::EVENT_READ,
        [
            'form' => $form,
            'tpl' => $tpl,
            'contact_id' => $contactId
        ]
    );
} elseif ($o == WATCH_CONTACT) {
    $form->addElement('header', 'title', _("View a User"));

    $eventDispatcher->notify(
        'contact.form',
        EventDispatcher::EVENT_READ,
        [
            'form' => $form,
            'tpl' => $tpl,
            'contact_id' => $contactId
        ]
    );
} elseif ($o == MASSIVE_CHANGE) {
    $form->addElement('header', 'title', _("Mass Change"));

    $eventDispatcher->notify(
        'contact.form',
        EventDispatcher::EVENT_DISPLAY,
        [
            'form' => $form,
            'tpl' => $tpl,
            'contact_id' => $contactId
        ]
    );
}

/**
 * Contact basic information
 */
$form->addElement('header', 'information', _("General Information"));
$form->addElement('header', 'additional', _("Additional Information"));
$form->addElement('header', 'centreon', _("Centreon Authentication"));
$form->addElement('header', 'acl', _("Access lists"));

/**
 * No possibility to change name and alias, because there's no interest
 */
/**
 * Don't change contact name and alias in massif change
 * Don't change contact name, alias or autologin key in massive change
 */
if ($o != MASSIVE_CHANGE) {
    /**
     * Contact name attributes
     */
    $attrsTextDescr["id"] = "contact_name";
    $attrsTextDescr["data-testid"] = "contact_name";
    $form->addElement('text', 'contact_name', _("Full Name"), $attrsTextDescr);

    /**
     * Contact alias attributes
     */
    $attrsText["id"] = "contact_alias";
    $attrsText["data-testid"] = "contact_alias";
    $form->addElement('text', 'contact_alias', _("Alias / Login"), $attrsText);

    $form->addElement(
        'text',
        'contact_autologin_key',
        _("Autologin Key"),
        [
            "size" => "90",
            "id" => "aKey",
            "data-testid" => "aKey"
        ]
    );
    $form->addElement(
        'button',
        'contact_gen_akey',
        _("Generate"),
        [
            'onclick' => "generatePassword('aKey', '$encodedPasswordPolicy');",
            "id" => "generateAutologinKeyButton",
            "data-testid" => "generateAutologinKeyButton"
        ]
    );
    /**
     * Contact email attributes
     */
    $attrsTextMail["id"] = "contact_email";
    $attrsTextMail["data-testid"] = "contact_email";
    $form->addElement('text', 'contact_email', _("Email"), $attrsTextMail);
    /**
     * Contact Pager attributes
     */
    $attrsText["id"] = "contact_pager";
    $attrsText["data-testid"] = "contact_pager";
    $form->addElement('text', 'contact_pager', _("Pager"), $attrsText);
}


/**
 * Contact template used
 */
$form->addElement(
    'select',
    'contact_template_id',
    _("Contact template used"),
    $contactTpl,
    [
        "id" => "contact_template_id",
        "data-testid" => "contact_template_id"
    ]
);
$form->addElement('header', 'furtherAddress', _("Additional Addresses"));
for ($i=0; $i < 6; $i++) {
    $attrsText["id"] = "contact_address" . ($i + 1);
    $attrsText["data-testid"] = "contact_address" . ($i + 1);
    $form->addElement('text', 'contact_address' . ($i + 1), _("Address" . ($i + 1)), $attrsText);
}

/**
 * Contact Groups Field
 */
$form->addElement('header', 'groupLinks', _("Group Relations"));
if ($o == MASSIVE_CHANGE) {
    $mc_mod_cg = [];
    $mc_mod_cg[] = $form->createElement('radio', 'mc_mod_cg', null, _("Incremental"), '0');
    $mc_mod_cg[] = $form->createElement('radio', 'mc_mod_cg', null, _("Replacement"), '1');
    $form->addGroup($mc_mod_cg, 'mc_mod_cg', _("Update mode"), '&nbsp;');
    $form->setDefaults(['mc_mod_cg' => '0']);
}

$defaultDatasetRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_contactgroup'
    . '&action=defaultValues&target=contact&field=contact_cgNotif&id=' . $contactId;

$attrContactgroup1 = array_merge(
    $attrContactgroups,
    ['defaultDatasetRoute' => $defaultDatasetRoute]
);
$form->addElement(
    'select2',
    'contact_cgNotif',
    _("Linked to Contact Groups"),
    [],
    $attrContactgroup1
);

/**
 * Contact Centreon information
 */
$form->addElement('header', 'oreon', _("Centreon"));
$tab = [];
$tab[] = $form->createElement(
    'radio',
    'contact_oreon',
    null,
    _("Yes"),
    '1',
    [
        "id" => "contact_oreon_yes",
        "data-testid" => "contact_oreon_yes"
    ]
);
$tab[] = $form->createElement(
    'radio',
    'contact_oreon',
    null,
    _("No"),
    '0',
    [
        "id" => "contact_oreon_no",
        "data-testid" => "contact_oreon_no"
    ]
);
$form->addGroup($tab, 'contact_oreon', _("Reach Centreon Front-end"), '&nbsp;');

if ($o !== MASSIVE_CHANGE) {
    $form->addElement(
        'password',
        'contact_passwd',
        _("Password"),
        [
            "size" => "30",
            "autocomplete" => "new-password",
            "id" => "passwd1",
            "data-testid" => "passwd1",
            "onkeypress" => "resetPwdType(this);"
        ]
    );
    $form->addElement(
        'password',
        'contact_passwd2',
        _("Confirm Password"),
        [
            "size" => "30",
            "autocomplete" => "new-password",
            "id" => "passwd2",
            "data-testid" => "passwd2",
            "onkeypress" => "resetPwdType(this);"
        ]
    );
    $form->addElement(
        'button',
        'contact_gen_passwd',
        _("Generate"),
        [
            'onclick' => "generatePassword('passwd', '$encodedPasswordPolicy');",
            "id" => "contact_gen_passwd",
            "data-testid" => "contact_gen_passwd"
        ]
    );
}

/* ------------------------ Topoogy ---------------------------- */
$pages = [null => ""];
$aclUser = $centreon->user->lcaTStr;
if (! empty($aclUser)) {
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

$form->addElement(
    'select',
    'contact_lang',
    _("Default Language"),
    $langs,
    [
        "id" => "contact_lang",
        "data-testid" => "contact_lang"
    ]
);
$form->addElement('select', 'default_page', _("Default page"), $pages);
$form->addElement(
    'select',
    'contact_type_msg',
    _("Mail Type"),
    [null => null, "txt" => "txt", "html" => "html", "pdf" => "pdf"]
);

if ($centreon->user->admin) {
    $tab = [];
    $tab[] = $form->createElement(
        'radio',
        'contact_admin',
        null,
        _("Yes"),
        '1',
        ["id" => "contact_admin_yes", "data-testid" => "contact_admin_yes"]
    );
    $tab[] = $form->createElement(
        'radio',
        'contact_admin',
        null,
        _("No"),
        '0',
        ["id" => "contact_admin_no", "data-testid" => "contact_admin_no"]
    );
    $form->addGroup($tab, 'contact_admin', _("Admin"), '&nbsp;');

    $tab = [];
    $tab[] = $form->createElement(
            'radio',
            'reach_api',
            null,
            _("Yes"),
            '1',
            ["id" => "reach_api_yes", "data-testid" => "reach_api_yes"]
        );
    $tab[] = $form->createElement(
        'radio',
        'reach_api',
        null,
        _("No"),
        '0',
        ["id" => "reach_api_no", "data-testid" => "reach_api_no"]
    );
    $form->addGroup($tab, 'reach_api', _("Reach API Configuration"), '&nbsp;');

    $tab = [];
    $tab[] = $form->createElement(
            'radio',
            'reach_api_rt',
            null,
            _("Yes"),
            '1',
            ["id" => "reach_api_rt_yes", "data-testid" => "reach_api_rt_yes"]
        );
    $tab[] = $form->createElement(
        'radio',
        'reach_api_rt',
        null,
        _("No"),
        '0',
        ["id" => "reach_api_rt_no", "data-testid" => "reach_api_rt_no"]
    );
    $form->addGroup($tab, 'reach_api_rt', _("Reach API Realtime"), '&nbsp;');
}

/**
 * ACL configurations
 */
if ($o == MASSIVE_CHANGE) {
    $mc_mod_cg = [];
    $mc_mod_cg[] = $form->createElement('radio', 'mc_mod_acl', null, _("Incremental"), '0');
    $mc_mod_cg[] = $form->createElement('radio', 'mc_mod_acl', null, _("Replacement"), '1');
    $form->addGroup($mc_mod_cg, 'mc_mod_acl', _("Update mode"), '&nbsp;');
    $form->setDefaults(['mc_mod_acl' => '0']);
}

$defaultDatasetRoute = './include/common/webServices/rest/internal.php?object=centreon_administration_aclgroup'
    . '&action=defaultValues&target=contact&field=contact_acl_groups&id=' . $contactId;
$attrAclgroup1 = array_merge(
    $attrAclgroups,
    ['defaultDatasetRoute' => $defaultDatasetRoute]
);
$form->addElement(
    'select2',
    'contact_acl_groups',
    _("Access list groups"),
    [],
    $attrAclgroup1,
);

/**
 * Include GMT Class
 */
require_once _CENTREON_PATH_ . "www/class/centreonGMT.class.php";

$CentreonGMT = new CentreonGMT($pearDB);

$availableDatasetRoute = './include/common/webServices/rest/internal.php'
    . '?object=centreon_configuration_timezone&action=list';
$defaultDatasetRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_timezone'
    . '&action=defaultValues&target=contact&field=contact_location&id=' . $contactId;
$attrTimezones = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $availableDatasetRoute, 'defaultDatasetRoute' => $defaultDatasetRoute, 'multiple' => false, 'linkedObject' => 'centreonGMT'];
$form->addElement(
    'select2',
    'contact_location',
    _("Timezone / Location"),
    [],
    $attrTimezones
);

$auth_type = $o != MASSIVE_CHANGE ? [] : [null => null];

$auth_type["local"] = "Centreon";
if ($centreon->optGen['ldap_auth_enable'] == 1) {
    $auth_type["ldap"] = "LDAP";
    /**
     * LDAP Distinguished Name attributes
     */
    $attrsText2["id"] = "contact_ldap_dn";
    $attrsText2["data-testid"] = "contact_ldap_dn";
    $dnElement = $form->addElement('text', 'contact_ldap_dn', _("LDAP DN (Distinguished Name)"), $attrsText2);
    if (!$centreon->user->admin) {
        $dnElement->freeze();
    }
}
if ($o != MASSIVE_CHANGE) {
    $form->setDefaults([
        'contact_oreon' => ['contact_oreon' => '1'],
        'contact_admin' => ['contact_admin' => '0'],
        'reach_api' => ['reach_api' => '0'],
        'reach_api_rt' => ['reach_api_rt' => '0']
    ]);
}
$form->addElement(
    'select',
    'contact_auth_type',
    _("Authentication Source"),
    $auth_type,
    [
        "id" => "contact_auth_type",
        "data-testid" => "contact_auth_type"
    ]
);

/**
 * Notification informations
 */
$form->addElement('header', 'notification', _("Notification"));

$tab = [];
$tab[] = $form->createElement(
    'radio',
    'contact_enable_notifications',
    null,
    _("Yes"),
    '1',
    ["data-testid" => "contact_enable_notifications_yes"]
);
$tab[] = $form->createElement(
    'radio',
    'contact_enable_notifications',
    null,
    _("No"),
    '0',
    ["data-testid" => "contact_enable_notifications_no"]
);
$tab[] = $form->createElement(
    'radio',
    'contact_enable_notifications',
    null,
    _("Default"),
    '2',
    [
        "id" => "contact_enable_notifications_default",
        "data-testid" => "contact_enable_notifications_default"
    ]
);
$form->addGroup($tab, 'contact_enable_notifications', _("Enable Notifications"), '&nbsp;');
if ($o != MASSIVE_CHANGE) {
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
    ['id' => 'hDown', 'onClick' => 'uncheckAllH(this);', "data-testid" => "hDown"]
);
$hostNotifOpt[] = $form->createElement(
    'checkbox',
    'u',
    '&nbsp;',
    _("Unreachable"),
    ['id' => 'hUnreachable', 'onClick' => 'uncheckAllH(this);', "data-testid" => "hUnreachable"]
);
$hostNotifOpt[] = $form->createElement(
    'checkbox',
    'r',
    '&nbsp;',
    _("Recovery"),
    ['id' => 'hRecovery', 'onClick' => 'uncheckAllH(this);', "data-testid" => "hRecovery"]
);
$hostNotifOpt[] = $form->createElement(
    'checkbox',
    'f',
    '&nbsp;',
    _("Flapping"),
    ['id' => 'hFlapping', 'onClick' => 'uncheckAllH(this);', "data-testid" => "hFlapping"]
);
$hostNotifOpt[] = $form->createElement(
    'checkbox',
    's',
    '&nbsp;',
    _("Downtime Scheduled"),
    ['id' => 'hScheduled', 'onClick' => 'uncheckAllH(this);', "data-testid" => "hScheduled"]
);
$hostNotifOpt[] = $form->createElement(
    'checkbox',
    'n',
    '&nbsp;',
    _("None"),
    ['id' => 'hNone', 'onClick' => 'javascript:uncheckAllH(this);', "data-testid" => "hNone"]
);
$form->addGroup($hostNotifOpt, 'contact_hostNotifOpts', _("Host Notification Options"), '&nbsp;&nbsp;');

$defaultDatasetRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_timeperiod'
    . '&action=defaultValues&target=contact&field=timeperiod_tp_id&id=' . $contactId;
$attrTimeperiod1 = array_merge(
    $attrTimeperiods,
    ['defaultDatasetRoute' => $defaultDatasetRoute]
);
$form->addElement(
    'select2',
    'timeperiod_tp_id',
    _("Host Notification Period"),
    [],
    $attrTimeperiod1
);


unset($hostNotifOpt);

if ($o == MASSIVE_CHANGE) {
    $mc_mod_hcmds = [];
    $mc_mod_hcmds[] = $form->createElement('radio', 'mc_mod_hcmds', null, _("Incremental"), '0');
    $mc_mod_hcmds[] = $form->createElement('radio', 'mc_mod_hcmds', null, _("Replacement"), '1');
    $form->addGroup($mc_mod_hcmds, 'mc_mod_hcmds', _("Update mode"), '&nbsp;');
    $form->setDefaults(['mc_mod_hcmds' => '0']);
}

$defaultDatasetRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_command'
    . '&action=defaultValues&target=contact&field=contact_hostNotifCmds&id=' . $contactId;
$availableDatasetRoute = './include/common/webServices/rest/internal.php'
    . '?object=centreon_configuration_command&action=list&t=1';
$attrCommand1 = array_merge(
    $attrCommands,
    ['defaultDatasetRoute' => $defaultDatasetRoute, 'availableDatasetRoute' => $availableDatasetRoute]
);
$form->addElement(
    'select2',
    'contact_hostNotifCmds',
    _("Host Notification Commands"),
    [],
    $attrCommand1
);

/** * *****************************
 * Service notifications
 */
$form->addElement('header', 'serviceNotification', _("Service"));
$svNotifOpt[] = $form->createElement(
    'checkbox',
    'w',
    '&nbsp;',
    _("Warning"),
    ['id' => 'sWarning', 'onClick' => 'uncheckAllS(this);', "data-testid" => "sWarning"]
);
$svNotifOpt[] = $form->createElement(
    'checkbox',
    'u',
    '&nbsp;',
    _("Unknown"),
    ['id' => 'sUnknown', 'onClick' => 'uncheckAllS(this);', "data-testid" => "sUnknown"]
);
$svNotifOpt[] = $form->createElement(
    'checkbox',
    'c',
    '&nbsp;',
    _("Critical"),
    ['id' => 'sCritical', 'onClick' => 'uncheckAllS(this);', "data-testid" => "sCritical"]
);
$svNotifOpt[] = $form->createElement(
    'checkbox',
    'r',
    '&nbsp;',
    _("Recovery"),
    ['id' => 'sRecovery', 'onClick' => 'uncheckAllS(this);', "data-testid" => "sRecovery"]
);
$svNotifOpt[] = $form->createElement(
    'checkbox',
    'f',
    '&nbsp;',
    _("Flapping"),
    ['id' => 'sFlapping', 'onClick' => 'uncheckAllS(this);', "data-testid" => "sFlapping"]
);
$svNotifOpt[] = $form->createElement(
    'checkbox',
    's',
    '&nbsp;',
    _("Downtime Scheduled"),
    ['id' => 'sScheduled', 'onClick' => 'uncheckAllS(this);', "data-testid" => "sScheduled"]
);
$svNotifOpt[] = $form->createElement(
    'checkbox',
    'n',
    '&nbsp;',
    _("None"),
    ['id' => 'sNone', 'onClick' => 'uncheckAllS(this);', "data-testid" => "sNone"]
);
$form->addGroup($svNotifOpt, 'contact_svNotifOpts', _("Service Notification Options"), '&nbsp;&nbsp;');

$defaultAttrTimeperiod2Route = './include/common/webServices/rest/internal.php?'
    . 'object=centreon_configuration_timeperiod&action=defaultValues&target=contact&field=timeperiod_tp_id2&id='
    . $contactId;

$attrTimeperiod2 = array_merge(
    $attrTimeperiods,
    ['defaultDatasetRoute' => $defaultAttrTimeperiod2Route]
);
$form->addElement(
    'select2',
    'timeperiod_tp_id2',
    _("Service Notification Period"),
    [],
    $attrTimeperiod2
);

if ($o == MASSIVE_CHANGE) {
    $mc_mod_svcmds = [];
    $mc_mod_svcmds[] = $form->createElement('radio', 'mc_mod_svcmds', null, _("Incremental"), '0');
    $mc_mod_svcmds[] = $form->createElement('radio', 'mc_mod_svcmds', null, _("Replacement"), '1');
    $form->addGroup($mc_mod_svcmds, 'mc_mod_svcmds', _("Update mode"), '&nbsp;');
    $form->setDefaults(['mc_mod_svcmds' => '0']);
}

$defaultattrCommand2Route = './include/common/webServices/rest/internal.php?object=centreon_configuration_command'
    . '&action=defaultValues&target=contact&field=contact_svNotifCmds&id=' . $contactId;
$availableCommand2Route = './include/common/webServices/rest/internal.php?'
    . 'object=centreon_configuration_command&action=list&t=1';

$attrCommand2 = array_merge(
    $attrCommands,
    ['defaultDatasetRoute' => $defaultattrCommand2Route, 'availableDatasetRoute' => $availableCommand2Route]
);
$form->addElement(
    'select2',
    'contact_svNotifCmds',
    _("Service Notification Commands"),
    [],
    $attrCommand2
);

/**
 * Further informations
 */
$form->addElement('header', 'furtherInfos', _("Additional Information"));
$cctActivation[] = $form->createElement(
    'radio',
    'contact_activate',
    null,
    _("Enabled"),
    '1',
    ["id" => "contact_activate_enable", "data-testid" => "contact_activate_enable"]
);
$cctActivation[] = $form->createElement(
    'radio',
    'contact_activate',
    null,
    _("Disabled"),
    '0',
    ["id" => "contact_activate_disable", "data-testid" => "contact_activate_disable"]
);
$form->addGroup($cctActivation, 'contact_activate', _("Status"), '&nbsp;');
$form->setDefaults(['contact_activate' => '1']);
if ($o == MODIFY_CONTACT && $centreon->user->get_id() == $cct["contact_id"]) {
    $form->freeze('contact_activate');
}

$form->addElement('hidden', 'contact_register');
$form->setDefaults(['contact_register' => '1']);
/**
 * Comments attributes
 */
$attrsTextarea["id"] = "contact_comment";
$attrsTextarea["data-testid"] = "contact_comment";
$form->addElement('textarea', 'contact_comment', _("Comments"), $attrsTextarea);

$form->addElement('hidden', 'contact_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

$init = $form->addElement('hidden', 'initialValues');
$init->setValue(serialize($initialValues));

if (is_array($select)) {
    $select_str = null;
    foreach ($select as $key => $value) {
        $select_str .= $key . ",";
    }
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
if ($o != MASSIVE_CHANGE) {
    $ret = $form->getSubmitValues();
    $form->addRule('contact_name', _("Compulsory Name"), 'required');
    $form->addRule('contact_alias', _("Compulsory Alias"), 'required');
    if ($isRemote === false) {
        $form->addRule('contact_email', _("Valid Email"), 'required');
    }
    $form->addRule('contact_oreon', _("Required Field"), 'required');
    $form->addRule('contact_lang', _("Required Field"), 'required');
    if ($centreon->user->admin) {
        $form->addRule('contact_admin', _("Required Field"), 'required');
    }
    $form->addRule('contact_auth_type', _("Required Field"), 'required');

    if (
        (isset($ret["contact_enable_notifications"]["contact_enable_notifications"])
        && $ret["contact_enable_notifications"]["contact_enable_notifications"] == 1)
        && ($isRemote === false)
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

    $form->addRule(['contact_passwd', 'contact_passwd2'], _("Passwords do not match"), 'compare');
    if ($o === ADD_CONTACT || $o === MODIFY_CONTACT) {
        $form->addFormRule('validatePasswordCreation');
        $form->addFormRule('validateAutologin');
    }
    if ($o === MODIFY_CONTACT) {
        $form->addFormRule('validatePasswordModification');
    }
    $form->registerRule('exist', 'callback', 'testContactExistence');
    $form->addRule('contact_name', "<font style='color: red;'>*</font>&nbsp;" . _("Contact already exists"), 'exist');
    $form->registerRule('existAlias', 'callback', 'testAliasExistence');
    $form->addRule(
        'contact_alias',
        "<font style='color: red;'>*</font>&nbsp;" . _("Alias already exists"),
        'existAlias'
    );
    $form->registerRule('keepOneContactAtLeast', 'callback', 'keepOneContactAtLeast');
    $form->addRule(
        'contact_alias',
        _("You have to keep at least one contact to access to Centreon"),
        'keepOneContactAtLeast'
    );
} elseif ($o == MASSIVE_CHANGE) {
    $from_list_menu = $form->getSubmitValue("submitMC") ? false : true;
}
$form->setRequiredNote("<font style='color: red;'>*</font>&nbsp;" . _("Required fields"));

$tpl->assign(
    "helpattr",
    'TITLE, "' . _("Help") . '", CLOSEBTN, true, FIX, [this, 0, 5], BGCOLOR, "#ffff99", BORDERCOLOR, '
    . '"orange", TITLEFONTCOLOR, "black", TITLEBGCOLOR, "orange", CLOSEBTNCOLORS, ["","black", "white", "red"], '
    . 'WIDTH, -300, SHADOW, true, TEXTALIGN, "justify"'
);

# prepare help texts
$helptext = "";
include_once("help.php");
foreach ($help as $key => $text) {
    $helptext .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign("helptext", $helptext);
if ($o == WATCH_CONTACT) {
    # Just watch a contact information
    if ($centreon->user->access->page($p) != 2) {
        $form->addElement(
            "button",
            "change",
            _("Modify"),
            ["onClick" => "javascript:window.location.href='?p=" . $p . "&o=c&contact_id=" . $contactId . "'"]
        );
    }
    $form->setDefaults($cct);
    $form->freeze();
} elseif ($o == MODIFY_CONTACT) {
    # Modify a contact information
    $subC = $form->addElement('submit', 'submitC', _("Save"), ["class" => "btc bt_success"]);
    $res = $form->addElement('reset', 'reset', _("Reset"), ["class" => "btc bt_default"]);
    $form->setDefaults($cct);
} elseif ($o == ADD_CONTACT) {
    # Add a contact information
    $subA = $form->addElement('submit', 'submitA', _("Save"), ["class" => "btc bt_success"]);
    $res = $form->addElement('reset', 'reset', _("Reset"), ["class" => "btc bt_default"]);
} elseif ($o == MASSIVE_CHANGE) {
    # Massive Change
    $subMC = $form->addElement('submit', 'submitMC', _("Save"), ["class" => "btc bt_success"]);
    $res = $form->addElement('reset', 'reset', _("Reset"), ["class" => "btc bt_default"]);
}

if (
    !empty($cct['contact_id'])
    && $centreon->optGen['ldap_auth_enable'] == 1
    && $cct['contact_auth_type'] === 'ldap'
) {
    $tpl->assign("ldap_group", _("Group Ldap"));
    if (isset($cgLdap)) {
        $tpl->assign("ldapGroups", $cgLdap);
    }
}

$valid = false;

if ($form->validate() && $from_list_menu == false) {
    $cctObj = $form->getElement('contact_id');
    if ($form->getSubmitValue("submitA")) {
        $newContactId = insertContactInDB();
        $cctObj->setValue($contactId);

        $eventDispatcher->notify(
            'contact.form',
            EventDispatcher::EVENT_ADD,
            [
                'form' => $form,
                'contact_id' => $newContactId
            ]
        );
    } elseif ($form->getSubmitValue("submitC")) {
        updateContactInDB(
            contact_id:$cctObj->getValue(),
            isRemote: $isRemote
        );

        $eventDispatcher->notify(
            'contact.form',
            EventDispatcher::EVENT_UPDATE,
            [
                'form' => $form,
                'contact_id' => $contactId
            ]
        );
    } elseif ($form->getSubmitValue("submitMC")) {
        $select = explode(",", $select);
        foreach ($select as $key => $selectedContactId) {
            if ($selectedContactId) {
                updateContactInDB($selectedContactId, true, $isRemote);

                $eventDispatcher->notify(
                    'contact.form',
                    EventDispatcher::EVENT_UPDATE,
                    [
                        'form' => $form,
                        'contact_id' => $selectedContactId
                    ]
                );
            }
        }
    }
    $o = null;
    $valid = true;
}

if ($valid) {
    require_once($path . "listContact.php");
} else {
    /*
     * Apply a template definition
     */
    $contactAuthType = $cct['contact_auth_type'] ?? null;
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl, true);
    $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $form->accept($renderer);
    $tpl->assign('form', $renderer->toArray());
    $tpl->assign('o', $o);
    $tpl->assign('displayAdminFlag', $centreon->user->admin);
    $tpl->assign("tzUsed", $CentreonGMT->used());
    if ($centreon->optGen['ldap_auth_enable']) {
        $tpl->assign('ldap', $centreon->optGen['ldap_auth_enable']);
    }
    $tpl->assign('auth_type', $contactAuthType);

    if ($isRemote === false) {
        $tpl->display("formContact.ihtml");
    } else {
        $tpl->display("formContactLight.ihtml");
    }
}
?>
<script type="text/javascript" src="./include/common/javascript/keygen.js"></script>
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
