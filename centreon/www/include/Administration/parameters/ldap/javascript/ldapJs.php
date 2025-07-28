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

?>
<script type="text/javascript">
    function mk_pagination() {}
    function mk_paginationFF() {}
    function set_header_title() {}

    let ldapTemplates = [];

    /*
     * called when the use _dns is to set at no is clicked
     */
    function toggleParams(checkValue) {
        if (checkValue === true) {
            jQuery('#ldap_dns_use_ssl').fadeOut({duration: 0});
            jQuery('#ldap_dns_use_tls').fadeOut({duration: 0});
            jQuery('#ldap_dns_use_domain').fadeOut({duration: 0});
            jQuery('#ldap_header_tr').fadeIn({duration: 0});
            jQuery('#ldap_tr').fadeIn({duration: 0});
        } else {
            jQuery('#ldap_header_tr').fadeOut({duration: 0});
            jQuery('#ldap_tr').fadeOut({duration: 0});
            if (document.getElementById('ldap_dns_use_ssl')) {
                jQuery('#ldap_dns_use_ssl').fadeIn({duration: 0});
            }
            if (document.getElementById('ldap_dns_use_tls')) {
                jQuery('#ldap_dns_use_tls').fadeIn({duration: 0});
            }
            if (document.getElementById('ldap_dns_use_domain')) {
                jQuery('#ldap_dns_use_domain').fadeIn({duration: 0});
            }
        }
    }

    /*
     * called when LDAP is enabled or not
     */
    function toggleParamSync(checkValue) {
        if (checkValue === true) {
            jQuery('#ldap_sync_interval').fadeOut({duration: 0});
        } else {
            jQuery('#ldap_sync_interval').fadeIn({duration: 0});
        }
    }

    /*
     * Initialises advanced parameters
     */
    function initParams() {
        initTemplates();
        let noDns = false;
        if (document.getElementById('ldap_srv_dns_n')) {
            if (document.getElementById('ldap_srv_dns_n').type === 'radio') {
                if (document.getElementById('ldap_srv_dns_n').checked) {
                    noDns = true;
                }
            }
        }
        // getting saved synchronization interval's time field state
        let loginSync = false;
        let loginCheckbox = document.getElementById('ldap_auto_sync_n');
        if (loginCheckbox
            && loginCheckbox.type === 'radio'
            && loginCheckbox.checked
        ) {
            loginSync = true;
        }

        // displaying or hiding toggling fields
        toggleParams(noDns);
        toggleParamSync(loginSync);
    }

    /*
     * Initializes templates
     */
    function initTemplates() {
        ldapTemplates = [];

        ldapTemplates['Active Directory'] = [];
        ldapTemplates['Active Directory']['user_filter'] =
            '(&(samAccountName=%s)(objectClass=user)(samAccountType=805306368))';
        ldapTemplates['Active Directory']['alias'] = 'samaccountname';
        ldapTemplates['Active Directory']['user_group'] = 'memberOf';
        ldapTemplates['Active Directory']['user_name'] = 'name';
        ldapTemplates['Active Directory']['user_firstname'] = 'givenname';
        ldapTemplates['Active Directory']['user_lastname'] = 'sn';
        ldapTemplates['Active Directory']['user_email'] = 'mail';
        ldapTemplates['Active Directory']['user_pager'] = 'mobile';
        ldapTemplates['Active Directory']['group_filter'] =
            '(&(samAccountName=%s)(objectClass=group)(samAccountType=268435456))';
        ldapTemplates['Active Directory']['group_name'] = 'samaccountname';
        ldapTemplates['Active Directory']['group_member'] = 'member';

        ldapTemplates['Posix'] = [];
        ldapTemplates['Posix']['user_filter'] = '(&(uid=%s)(objectClass=inetOrgPerson))';
        ldapTemplates['Posix']['alias'] = 'uid';
        ldapTemplates['Posix']['user_group'] = '';
        ldapTemplates['Posix']['user_name'] = 'cn';
        ldapTemplates['Posix']['user_firstname'] = 'givenname';
        ldapTemplates['Posix']['user_lastname'] = 'sn';
        ldapTemplates['Posix']['user_email'] = 'mail';
        ldapTemplates['Posix']['user_pager'] = 'mobile';
        ldapTemplates['Posix']['group_filter'] = '(&(cn=%s)(objectClass=groupOfNames))';
        ldapTemplates['Posix']['group_name'] = 'cn';
        ldapTemplates['Posix']['group_member'] = 'member';

        ldapTemplates['Okta'] = [];
        ldapTemplates['Okta']['user_filter'] = '(&(nickName=%s)(objectclass=inetorgperson))';
        ldapTemplates['Okta']['alias'] = 'nickname';
        ldapTemplates['Okta']['user_group'] = 'memberof';
        ldapTemplates['Okta']['user_name'] = 'cn';
        ldapTemplates['Okta']['user_firstname'] = 'givenname';
        ldapTemplates['Okta']['user_lastname'] = 'sn';
        ldapTemplates['Okta']['user_email'] = 'mail';
        ldapTemplates['Okta']['user_pager'] = 'mobile';
        ldapTemplates['Okta']['group_filter'] = '(&(cn=%s)(objectclass=groupofuniquenames))';
        ldapTemplates['Okta']['group_name'] = 'cn';
        ldapTemplates['Okta']['group_member'] = 'uniquemember';
    }

    /*
     * Apply template is called from the template selectbox
     */
    function applyTemplate(templateValue) {

        jQuery('input[type^=text]').each(function (index, el) {
            let attr = el.getAttribute('name');
            if (typeof(ldapTemplates[templateValue]) !== 'undefined') {
                if (typeof(ldapTemplates[templateValue][attr]) !== 'undefined') {
                    el.value = ldapTemplates[templateValue][attr];
                }
            }
        });
    }
    jQuery(document).ready(function () {
        initParams();
    });
</script>
