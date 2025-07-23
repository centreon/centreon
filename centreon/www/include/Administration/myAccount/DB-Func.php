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

require_once __DIR__ . '/../../../class/centreonContact.class.php';
require_once __DIR__ . '/../../../class/centreonAuth.class.php';

function testExistence($name = null)
{
    global $pearDB, $centreon;

    $query = "SELECT contact_name, contact_id FROM contact WHERE contact_name = '"
        . htmlentities($name, ENT_QUOTES, 'UTF-8') . "'";
    $dbResult = $pearDB->query($query);
    $contact = $dbResult->fetch();
    // Modif case
    if ($dbResult->rowCount() >= 1 && $contact['contact_id'] == $centreon->user->get_id()) {
        return true;
    }

    return ! ($dbResult->rowCount() >= 1 && $contact['contact_id'] != $centreon->user->get_id());
    // Duplicate entry

}

function testAliasExistence($alias = null)
{
    global $pearDB, $centreon;

    $query = 'SELECT contact_alias, contact_id FROM contact '
        . "WHERE contact_alias = '" . htmlentities($alias, ENT_QUOTES, 'UTF-8') . "'";
    $dbResult = $pearDB->query($query);
    $contact = $dbResult->fetch();

    // Modif case
    if ($dbResult->rowCount() >= 1 && $contact['contact_id'] == $centreon->user->get_id()) {
        return true;
    }

    return ! ($dbResult->rowCount() >= 1 && $contact['contact_id'] != $centreon->user->get_id());
    // Duplicate entry

}

function updateNotificationOptions($contact_id)
{
    global $form, $pearDB;

    $pearDB->query('DELETE FROM contact_param
        WHERE cp_contact_id = ' . $pearDB->escape($contact_id) . "
        AND cp_key LIKE 'monitoring%notification%'");
    $data = $form->getSubmitValues();
    foreach ($data as $k => $v) {
        if (preg_match('/^monitoring_(host|svc)_notification/', $k)) {
            $query = 'INSERT INTO contact_param (cp_key, cp_value, cp_contact_id) '
                . "VALUES ('" . $pearDB->escape($k) . "', '1', " . $pearDB->escape($contact_id) . ')';
            $pearDB->query($query);
        } elseif (preg_match('/^monitoring_sound/', $k)) {
            $query = 'INSERT INTO contact_param (cp_key, cp_value, cp_contact_id) '
                . "VALUES ('" . $pearDB->escape($k) . "', '" . $pearDB->escape($v) . "', "
                . $pearDB->escape($contact_id) . ')';
            $pearDB->query($query);
        }
    }
    unset($_SESSION['centreon_notification_preferences']);
}

function updateContactInDB($contact_id = null)
{
    if (! $contact_id) {
        return;
    }
    updateContact($contact_id);
    updateNotificationOptions($contact_id);
}

function updateContact($contactId = null)
{
    global $form, $pearDB, $centreon;

    if (! $contactId) {
        return;
    }

    $ret = [];
    $ret = $form->getSubmitValues();
    // remove illegal chars in data sent by the user
    $ret['contact_name'] = CentreonUtils::escapeSecure($ret['contact_name'], CentreonUtils::ESCAPE_ILLEGAL_CHARS);
    $ret['contact_alias'] = CentreonUtils::escapeSecure($ret['contact_alias'], CentreonUtils::ESCAPE_ILLEGAL_CHARS);
    $ret['contact_email'] = ! empty($ret['contact_email'])
        ? CentreonUtils::escapeSecure($ret['contact_email'], CentreonUtils::ESCAPE_ILLEGAL_CHARS) : '';
    $ret['contact_pager'] = ! empty($ret['contact_pager'])
        ? CentreonUtils::escapeSecure($ret['contact_pager'], CentreonUtils::ESCAPE_ILLEGAL_CHARS) : '';
    $ret['contact_autologin_key'] = ! empty($ret['contact_autologin_key'])
        ? CentreonUtils::escapeSecure($ret['contact_autologin_key'], CentreonUtils::ESCAPE_ILLEGAL_CHARS) : '';
    $ret['contact_lang'] = ! empty($ret['contact_lang'])
        ? CentreonUtils::escapeSecure($ret['contact_lang'], CentreonUtils::ESCAPE_ILLEGAL_CHARS) : '';

    $rq = 'UPDATE contact SET '
          . 'contact_name = :contactName, '
          . 'contact_alias = :contactAlias, '
          . 'contact_location = :contactLocation, '
          . 'contact_lang = :contactLang, '
          . 'contact_email = :contactEmail, '
          . 'contact_pager = :contactPager, '
          . 'default_page = :defaultPage, '
          . 'show_deprecated_pages = :showDeprecatedPages, '
          . 'contact_autologin_key = :contactAutologinKey, '
          . 'show_deprecated_custom_views = :showDeprecatedCustomViews';
    $rq .= ' WHERE contact_id = :contactId';

    $stmt = $pearDB->prepare($rq);
    $stmt->bindValue(':contactName', $ret['contact_name'], PDO::PARAM_STR);
    $stmt->bindValue(':contactAlias', $ret['contact_alias'], PDO::PARAM_STR);
    $stmt->bindValue(':contactLang', $ret['contact_lang'], PDO::PARAM_STR);
    $stmt->bindValue(
        ':contactEmail',
        ! empty($ret['contact_email']) ? $ret['contact_email'] : null,
        PDO::PARAM_STR
    );
    $stmt->bindValue(
        ':contactPager',
        ! empty($ret['contact_pager']) ? $ret['contact_pager'] : null,
        PDO::PARAM_STR
    );
    $stmt->bindValue(
        ':contactAutologinKey',
        ! empty($ret['contact_autologin_key']) ? $ret['contact_autologin_key'] : null,
        PDO::PARAM_STR
    );
    $stmt->bindValue(
        ':contactLocation',
        ! empty($ret['contact_location']) ? $ret['contact_location'] : null,
        PDO::PARAM_INT
    );
    $stmt->bindValue(':defaultPage', ! empty($ret['default_page']) ? $ret['default_page'] : null, PDO::PARAM_INT);
    $stmt->bindValue(':showDeprecatedPages', isset($ret['show_deprecated_pages']) ? 1 : 0, PDO::PARAM_STR);
    $stmt->bindValue(
        ':showDeprecatedCustomViews',
        isset($ret['show_deprecated_custom_views']) ? '1' : '0',
        PDO::PARAM_STR
    );
    $stmt->bindValue(':contactId', $contactId, PDO::PARAM_INT);
    $stmt->execute();

    if (isset($ret['contact_passwd']) && ! empty($ret['contact_passwd'])) {
        $hashedPassword = password_hash($ret['contact_passwd'], CentreonAuth::PASSWORD_HASH_ALGORITHM);
        $contact = new CentreonContact($pearDB);
        $contact->renewPasswordByContactId($contactId, $hashedPassword);
    }

    // Update user object..
    $centreon->user->name = $ret['contact_name'];
    $centreon->user->alias = $ret['contact_alias'];
    $centreon->user->lang = $ret['contact_lang'];
    $centreon->user->email = $ret['contact_email'];
    $centreon->user->setToken($ret['contact_autologin_key'] ?? "''");
}

/**
 * @param array<string,mixed> $fields
 */
function validatePasswordModification(array $fields)
{
    global $pearDB, $centreon;
    $errors = [];
    $password = $fields['contact_passwd'];
    $contactId = (int) $centreon->user->get_id();
    if (empty($password)) {
        return true;
    }

    try {
        $contact = new CentreonContact($pearDB);
        $contact->respectPasswordPolicyOrFail($password, $contactId);
    } catch (Throwable $e) {
        $errors['contact_passwd'] = $e->getMessage();
    }

    return $errors !== [] ? $errors : true;
}

/**
 * @param array<string,mixed> $fields
 * @return array<string,string>|bool
 */
function checkAutologinValue(array $fields)
{
    global $pearDB, $centreon;
    $errors = [];

    if (! empty($fields['contact_autologin_key'])) {
        $contactId = $centreon->user->get_id();
        $statement = $pearDB->prepare(
            'SELECT * FROM `contact_password` WHERE contact_id = :contactId ORDER BY creation_date DESC LIMIT 1'
        );
        $statement->bindValue(':contactId', $contactId, PDO::PARAM_INT);
        $statement->execute();

        if (
            ($result = $statement->fetch(PDO::FETCH_ASSOC))
            && password_verify($fields['contact_autologin_key'], $result['password'])
        ) {
            $errors['contact_autologin_key'] = _('Your autologin key must be different than your current password');
        } elseif (
            ! empty($fields['contact_passwd'])
            && $fields['contact_passwd'] === $fields['contact_autologin_key']
        ) {
            $errorMessage = _('Your new password and autologin key must be different');
            $errors['contact_passwd'] = $errorMessage;
            $errors['contact_autologin_key'] = $errorMessage;
        }
    }

    return $errors !== [] ? $errors : true;
}
function updateNonLocalContactInDB($contact_id = null): void
{
    global $pearDB, $centreon, $form;

    if (! $contact_id) {
        return;
    }
    $ret = $form->getSubmitValues();
    $ret['contact_pager'] = ! empty($ret['contact_pager'])
        ? CentreonUtils::escapeSecure($ret['contact_pager'], CentreonUtils::ESCAPE_ILLEGAL_CHARS) : '';
    $ret['contact_lang'] = ! empty($ret['contact_lang'])
        ? CentreonUtils::escapeSecure($ret['contact_lang'], CentreonUtils::ESCAPE_ILLEGAL_CHARS) : '';

    $rq = 'UPDATE contact SET '
        . 'contact_location = :contactLocation, '
        . 'contact_lang = :contactLang, '
        . 'contact_pager = :contactPager, '
        . 'default_page = :defaultPage, '
        . 'show_deprecated_pages = :showDeprecatedPages, '
        . 'show_deprecated_custom_views = :showDeprecatedCustomViews';
    $rq .= ' WHERE contact_id = :contactId';

    $stmt = $pearDB->prepare($rq);
    $stmt->bindValue(':contactLang', $ret['contact_lang'], PDO::PARAM_STR);
    $stmt->bindValue(
        ':contactPager',
        ! empty($ret['contact_pager']) ? $ret['contact_pager'] : null,
        PDO::PARAM_STR
    );
    $stmt->bindValue(
        ':contactLocation',
        ! empty($ret['contact_location']) ? $ret['contact_location'] : null,
        PDO::PARAM_INT
    );

    $stmt->bindValue(':defaultPage', ! empty($ret['default_page']) ? $ret['default_page'] : null, PDO::PARAM_INT);
    $stmt->bindValue(':showDeprecatedPages', isset($ret['show_deprecated_pages']) ? 1 : 0, PDO::PARAM_STR);
    $stmt->bindValue(
        ':showDeprecatedCustomViews',
        isset($ret['show_deprecated_custom_views']) ? '1' : '0',
        PDO::PARAM_STR
    );
    $stmt->bindValue(':contactId', $contact_id, PDO::PARAM_INT);
    $stmt->execute();
    $stmt->closeCursor();
    if (! empty($ret['contact_passwd'])) {
        $hashedPassword = password_hash($ret['contact_passwd'], CentreonAuth::PASSWORD_HASH_ALGORITHM);
        $contact = new CentreonContact($pearDB);
        $contact->renewPasswordByContactId($contact_id, $hashedPassword);
    }

    // Update user object..
    $centreon->user->lang = $ret['contact_lang'];
}
