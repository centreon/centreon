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

require_once _CENTREON_PATH_ . 'www/class/centreonLDAP.class.php';
require_once _CENTREON_PATH_ . 'www/class/CentreonLDAPAdmin.class.php';
$tpl = new Smarty();

if (isset($_REQUEST['ar_id']) || isset($_REQUEST['new'])) {
    include _CENTREON_PATH_ . 'www/include/Administration/parameters/ldap/form.php';
} else {
    $ldapAction = $_REQUEST['a'] ?? null;
    if (! is_null($ldapAction) && isset($_REQUEST['select']) && is_array($_REQUEST['select'])) {
        $select = $_REQUEST['select'];
        $ldapConf = new CentreonLdapAdmin($pearDB);
        switch ($ldapAction) {
            case 'd':
                purgeOutdatedCSRFTokens();
                if (isCSRFTokenValid()) {
                    purgeCSRFToken();
                    $ldapConf->deleteConfiguration($select);
                } else {
                    unvalidFormMessage();
                }
                break;
            case 'ms':
                purgeOutdatedCSRFTokens();
                if (isCSRFTokenValid()) {
                    purgeCSRFToken();
                    $ldapConf->setStatus(1, $select);
                } else {
                    unvalidFormMessage();
                }
                break;
            case 'mu':
                purgeOutdatedCSRFTokens();
                if (isCSRFTokenValid()) {
                    purgeCSRFToken();
                    $ldapConf->setStatus(0, $select);
                } else {
                    unvalidFormMessage();
                }
                break;
            default:
                break;
        }
    }
    include _CENTREON_PATH_ . 'www/include/Administration/parameters/ldap/list.php';
}
