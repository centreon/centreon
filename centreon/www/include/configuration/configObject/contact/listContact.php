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

if (! isset($centreon)) {
    exit();
}

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);
$tpl->assign('centreon_path', _CENTREON_PATH_);

// Access level
$lvl_access = ($centreon->user->access->page($p) == 1) ? 'w' : 'r';
$tpl->assign('mode_access', $lvl_access);

$tpl->assign('headerMenu_name', _('Full Name'));
$tpl->assign('headerMenu_desc', _('Alias / Login'));
$tpl->assign('headerMenu_email', _('Email'));
$tpl->assign('headerMenu_hostNotif', _('Host Notification Period'));
$tpl->assign('headerMenu_svNotif', _('Services Notification Period'));
$tpl->assign('headerMenu_lang', _('Language'));
$tpl->assign('headerMenu_access', _('Access'));
$tpl->assign('headerMenu_admin', _('Admin'));
$tpl->assign('headerMenu_options', _('Options'));
$tpl->assign('isAdmin', $centreon->user->admin);

// Per-row LDAP synchronization column, shown to admins only
$tpl->assign('headerMenu_refreshLdap', _('Refresh'));
$tpl->assign(
    'headerMenu_refreshLdapTitleTooltip',
    _('To manually request a LDAP synchronization of a contact')
);
$tpl->assign('refreshLdapHelpNone', _("This user isn't linked to a LDAP"));
$tpl->assign('refreshLdapHelpAvailable', _('Manually request to synchronize this contact with his LDAP'));
$tpl->assign(
    'refreshLdapHelpRequested',
    _('Already requested, please wait the CRON execution or for the user to login')
);

$tpl->assign('contactPage', $p);

// CSRF token for the single-contact unblock action link
$tpl->assign('centreonToken', createCSRFToken());

// Restore search from history
$search = $centreon->historySearch[$url]['search'] ?? '';
$tpl->assign('searchC', $search);

// Default limit from DB
$defaultLimit = (int) ($centreon->optGen['maxViewConfiguration'] ?? 30) ?: 30;
$tpl->assign('defaultLimit', $defaultLimit);

// Check LDAP configured
$res = $pearDB->query('SELECT count(ar_id) as count_ldap FROM auth_ressource');
$row = $res->fetch();
if ($row['count_ldap'] > 0) {
    $tpl->assign('ldap', '1');
}

// The Unblock bulk action is offered only when at least one contact is blocked.
// The listing is AJAX now, so the count no longer falls out of the page query.
// It only drives a menu entry for admins, so it must never abort the render.
$blockedContactsCount = 0;
if ($centreon->user->admin) {
    try {
        // One quoted line rather than a heredoc: xgettext does not implement
        // heredoc and never resynchronizes, so it would drop every _() below.
        $blockedContactsCount = (int) $pearDB->fetchOne(
            "SELECT COUNT(*) FROM contact WHERE contact_register = '1' AND blocking_time IS NOT NULL"
        );
    } catch (Throwable $exception) {
        Adaptation\Log\Logger::create(Adaptation\Log\Enum\LogChannelEnum::WEB)->error(
            'Contacts listing: failed to count blocked contacts',
            ['exception' => $exception]
        );
    }
}

// Form for bulk actions
$form = new HTML_QuickFormCustom('select_form', 'POST', '?p=' . $p);

$attrBtnSuccess = ['class' => 'btc bt_success', 'onClick' => "window.history.replaceState('', '', '?p=" . $p . "');"];
$form->addElement('submit', 'Search', _('Search'), $attrBtnSuccess);

// Contact group filter (select2 AJAX)
$contactGrRoute = './api/internal.php?object=centreon_configuration_contactgroup&action=list';
// No linkedObject / defaultDataset here: the filter must start empty, and the
// listing restores the chosen value and its label from its own session state.
$attrContactgroups = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $contactGrRoute, 'multiple' => false];
$form->addElement('select2', 'contactGroup', _('Select'), [], $attrContactgroups);

$tpl->assign(
    'msg',
    ['addL' => 'main.php?p=' . $p . '&o=a', 'addT' => _('Add'), 'ldap_importL' => 'main.php?p=' . $p . '&o=li', 'ldap_importT' => _('LDAP Import'), 'view_notif' => _('View contact notifications')]
);

?>
<script type="text/javascript">
    function setO(_i) {
        document.forms['form'].elements['o'].value = _i;
    }

    // The shared modal only confirms Delete and Duplicate. The legacy page also
    // confirmed the two admin bulk actions — one of them disconnects every
    // session of the selected contacts — so they keep their prompt here rather
    // than in the framework.
    function clContactMoreAction(select) {
        var prompts = {
            sync: ['data-title-sync', 'data-msg-sync'],
            mun: ['data-title-unblock', 'data-msg-unblock']
        };
        var value = select.value;
        if (!prompts[value]) {
            clMoreAction(select);

            return;
        }
        var attr = function (name, fallback) { return select.getAttribute(name) || fallback; };
        var form = select.form;
        var scope = form || document;
        var checked = scope.querySelectorAll('.cl-col-picker input[type="checkbox"][name^="select["]:checked');
        if (checked.length === 0) {
            clShowConfirmModal({
                alert: true,
                title: '',
                message: attr('data-msg-select', 'Please select one or more items')
            });
            select.selectedIndex = 0;

            return;
        }
        clShowConfirmModal({
            title: attr(prompts[value][0], ''),
            message: attr(prompts[value][1], ''),
            confirmLabel: attr(prompts[value][0], ''),
            cancelLabel: attr('data-label-cancel', 'Cancel')
        }, function (confirmed) {
            if (!confirmed) {
                select.selectedIndex = 0;

                return;
            }
            if (typeof window.setO === 'function') {
                window.setO(value);
            }
            if (form) {
                HTMLFormElement.prototype.submit.call(form);
            }
        });
    }

    // The two row-level admin actions confirm through the same styled modal as
    // the bulk ones, where the legacy page used a native confirm(). The request
    // is no longer synchronous either: the modal already gates it on an answer.
    function submitSync(p, contactId) {
        clShowConfirmModal({
            title: <?= json_encode(_('Synchronize LDAP'), JSON_THROW_ON_ERROR); ?>,
            message: <?= json_encode(_('If the contact is connected, all his instances will be closed. Are you sure you want to '
                . 'request a data synchronization at the next login of this Contact ?'), JSON_THROW_ON_ERROR); ?>,
            confirmLabel: <?= json_encode(_('Synchronize LDAP'), JSON_THROW_ON_ERROR); ?>
        }, function (confirmed) {
            if (!confirmed) {
                return;
            }
            $.ajax({
                url: './api/internal.php?object=centreon_ldap_synchro&action=requestLdapSynchro',
                type: 'POST',
                data: {contactId: contactId},
                success: function(data) {
                    if (data === true) {
                        window.location.href = "?p=" + p;
                    }
                }
            });
        });
    }

    // Unblock is a plain link on the row: confirm first, then follow it.
    function unblockContact(url) {
        clShowConfirmModal({
            title: <?= json_encode(_('Unblock'), JSON_THROW_ON_ERROR); ?>,
            message: <?= json_encode(_('Do you really want to unblock this user?'), JSON_THROW_ON_ERROR); ?>,
            confirmLabel: <?= json_encode(_('Unblock'), JSON_THROW_ON_ERROR); ?>
        }, function (confirmed) {
            if (confirmed) {
                window.location.href = url;
            }
        });
    }
</script>
<?php

foreach (['o1'] as $option) {
    // Styled, secure confirmation modal (clMoreAction in listing.js) replaces
    // the native confirm()/alert(); messages passed as data-* attributes so the
    // handler stays locale-independent (keyed on the option value).
    $attrs = [
        'onchange' => 'clContactMoreAction(this);',
        'data-msg-select' => _('Please select one or more items'),
        'data-title-delete-one' => _('Delete contact'),
        'data-title-delete-many' => _('Delete contacts'),
        'data-msg-delete-one' => _('You are about to delete the <strong>{{ name }}</strong> contact. This action cannot be undone. Do you want to delete it?'),
        'data-msg-delete-many' => _('You are about to delete <strong>{{ count }} contacts.</strong> This action cannot be undone. Do you want to delete them?'),
        'data-label-delete' => _('Delete'),
        'data-title-duplicate-one' => _('Duplicate contact'),
        'data-title-duplicate-many' => _('Duplicate contacts'),
        'data-msg-duplicate-one' => _('You are about to duplicate the <strong>{{ name }}</strong> contact. Do you want to duplicate it?'),
        'data-msg-duplicate-many' => _('You are about to duplicate <strong>{{ count }} contacts.</strong> Do you want to duplicate them?'),
        'data-label-duplicate' => _('Duplicate'),
        'data-label-cancel' => _('Cancel'),
        'data-title-sync' => _('Synchronize LDAP'),
        'data-msg-sync' => _('The chosen contact(s) will be disconnected. Do you confirm the LDAP synchronization request ?'),
        'data-title-unblock' => _('Unblock'),
        'data-msg-unblock' => _('The user(s) will be unblocked. Do you confirm the request?'),
    ];

    $formOptions = [null => _('More actions'), 'm' => _('Duplicate'), 'd' => _('Delete'), 'mc' => _('Mass Change'), 'ms' => _('Enable'), 'mu' => _('Disable')];
    // adding a specific option available only for admin users
    if ($centreon->user->admin) {
        $formOptions['sync'] = _('Synchronize LDAP');
    }
    // adding a specific option available only for admin users and if at least one user is blocked
    if ($centreon->user->admin && $blockedContactsCount) {
        $formOptions['mun'] = _('Unblock');
    }

    $form->addElement('select', $option, null, $formOptions, $attrs);
    $form->setDefaults([$option => null]);
    $el = $form->getElement($option);
    $el->setValue(null);
    $el->setSelected(null);
}

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('listContact.ihtml');
