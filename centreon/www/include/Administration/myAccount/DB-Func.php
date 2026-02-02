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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Log\LoggerPassword;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;

require_once __DIR__ . '/../../../class/centreonContact.class.php';
require_once __DIR__ . '/../../../class/centreonAuth.class.php';

function testExistence($name = null)
{
    global $pearDB, $centreon;

    $query = "SELECT contact_name, contact_id FROM contact WHERE contact_name = '" .
        htmlentities($name, ENT_QUOTES, "UTF-8") . "'";
    $dbResult = $pearDB->query($query);
    $contact = $dbResult->fetch();
    /*
     * Modif case
     */
    if ($dbResult->rowCount() >= 1 && $contact["contact_id"] == $centreon->user->get_id()) {
        return true;
    } elseif ($dbResult->rowCount() >= 1 && $contact["contact_id"] != $centreon->user->get_id()) {
        /*
         * Duplicate entry
         */
        return false;
    } else {
        return true;
    }
}

function testAliasExistence($alias = null)
{
    global $pearDB, $centreon;

    $query = "SELECT contact_alias, contact_id FROM contact " .
        "WHERE contact_alias = '" . htmlentities($alias, ENT_QUOTES, "UTF-8") . "'";
    $dbResult = $pearDB->query($query);
    $contact = $dbResult->fetch();

    /*
     * Modif case
     */
    if ($dbResult->rowCount() >= 1 && $contact["contact_id"] == $centreon->user->get_id()) {
        return true;
    } elseif ($dbResult->rowCount() >= 1 && $contact["contact_id"] != $centreon->user->get_id()) {
        /*
         * Duplicate entry
         */
        return false;
    } else {
        return true;
    }
}

function updateNotificationOptions($userIdConnected)
{
    global $form, $pearDB;

    $pearDB->query('DELETE FROM contact_param
        WHERE cp_contact_id = ' . $pearDB->escape($userIdConnected) . "
        AND cp_key LIKE 'monitoring%notification%'");
    $data = $form->getSubmitValues();
    foreach ($data as $k => $v) {
        if (preg_match('/^monitoring_(host|svc)_notification/', $k)) {
            $query = 'INSERT INTO contact_param (cp_key, cp_value, cp_contact_id) '
                . "VALUES ('" . $pearDB->escape($k) . "', '1', " . $pearDB->escape($userIdConnected) . ')';
            $pearDB->query($query);
        } elseif (preg_match('/^monitoring_sound/', $k)) {
            $query = 'INSERT INTO contact_param (cp_key, cp_value, cp_contact_id) '
                . "VALUES ('" . $pearDB->escape($k) . "', '" . $pearDB->escape($v) . "', "
                . $pearDB->escape($userIdConnected) . ')';
            $pearDB->query($query);
        }
    }
    unset($_SESSION['centreon_notification_preferences']);
}

/**
 * @throws RepositoryException
 */
function updateContactByMyAccountInDB(mixed $userIdConnected): void
{
    $userIdConnected = (int) $userIdConnected;

    if (! $userIdConnected > 0) {
        throw new RepositoryException(
            message: 'Invalid connected user ID provided to update contact from my account page for contact id ' . $userIdConnected,
            context: ['contact_id' => $userIdConnected]
        );
    }

    updateContactByMyAccount($userIdConnected);
    updateNotificationOptions($userIdConnected);
}

/**
 * @throws RepositoryException
 */
function updateContactByMyAccount(int $userIdConnected): void
{
    global $form, $pearDB, $centreon;

    if (! $userIdConnected > 0) {
        throw new RepositoryException(
            message: 'Invalid connected user ID provided to update contact from my account page for contact id ' . $userIdConnected,
            context: ['contact_id' => $userIdConnected]
        );
    }

    $submitValues = $form->getSubmitValues();
    // remove illegal chars in data sent by the user
    $submitValues['contact_name'] = CentreonUtils::escapeSecure($submitValues['contact_name'], CentreonUtils::ESCAPE_ILLEGAL_CHARS);
    $submitValues['contact_alias'] = CentreonUtils::escapeSecure($submitValues['contact_alias'], CentreonUtils::ESCAPE_ILLEGAL_CHARS);
    $submitValues['contact_email'] = ! empty($submitValues['contact_email'])
        ? CentreonUtils::escapeSecure($submitValues['contact_email'], CentreonUtils::ESCAPE_ILLEGAL_CHARS) : '';
    $submitValues['contact_pager'] = ! empty($submitValues['contact_pager'])
        ? CentreonUtils::escapeSecure($submitValues['contact_pager'], CentreonUtils::ESCAPE_ILLEGAL_CHARS) : '';
    $submitValues['contact_autologin_key'] = ! empty($submitValues['contact_autologin_key'])
        ? CentreonUtils::escapeSecure($submitValues['contact_autologin_key'], CentreonUtils::ESCAPE_ILLEGAL_CHARS) : '';
    $submitValues['contact_lang'] = ! empty($submitValues['contact_lang'])
        ? CentreonUtils::escapeSecure($submitValues['contact_lang'], CentreonUtils::ESCAPE_ILLEGAL_CHARS) : '';

    $rq = 'UPDATE contact SET ' .
          'contact_name = :contactName, ' .
          'contact_alias = :contactAlias, ' .
          'contact_location = :contactLocation, ' .
          'contact_lang = :contactLang, ' .
          'contact_email = :contactEmail, ' .
          'contact_pager = :contactPager, ' .
          'default_page = :defaultPage, ' .
          'show_deprecated_pages = :showDeprecatedPages, ' .
          'contact_autologin_key = :contactAutologinKey';
    $rq .= ' WHERE contact_id = :contactId';

    try {
        $stmt = $pearDB->prepare($rq);
        $stmt->bindValue(':contactName', $submitValues['contact_name'], PDO::PARAM_STR);
        $stmt->bindValue(':contactAlias', $submitValues['contact_alias'], PDO::PARAM_STR);
        $stmt->bindValue(':contactLang', $submitValues['contact_lang'], PDO::PARAM_STR);
        $stmt->bindValue(
            ':contactEmail',
            ! empty($submitValues['contact_email']) ? $submitValues['contact_email'] : null,
            PDO::PARAM_STR
        );
        $stmt->bindValue(
            ':contactPager',
            ! empty($submitValues['contact_pager']) ? $submitValues['contact_pager'] : null,
            PDO::PARAM_STR
        );
        $stmt->bindValue(
            ':contactAutologinKey',
            ! empty($submitValues['contact_autologin_key']) ? $submitValues['contact_autologin_key'] : null,
            PDO::PARAM_STR
        );
        $stmt->bindValue(
            ':contactLocation',
            ! empty($submitValues['contact_location']) ? $submitValues['contact_location'] : null,
            PDO::PARAM_INT
        );
        $stmt->bindValue(
            ':defaultPage',
            ! empty($submitValues['default_page']) ? $submitValues['default_page'] : null,
            PDO::PARAM_INT
        );
        $stmt->bindValue(':showDeprecatedPages', isset($submitValues['show_deprecated_pages']) ? 1 : 0, PDO::PARAM_STR);
        $stmt->bindValue(':contactId', $userIdConnected, PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        throw new RepositoryException(
            message: 'Unable to update contact from my account for contact id ' . $userIdConnected,
            context: ['userIdConnected' => $userIdConnected],
            previous: $e
        );
    }

    if (isset($submitValues['contact_passwd']) && $submitValues['contact_passwd'] !== '') {
        $hashedPassword = password_hash($submitValues['contact_passwd'], CentreonAuth::PASSWORD_HASH_ALGORITHM);

        try {
            $contact = new CentreonContact($pearDB);
            $contact->renewPasswordByContactId($userIdConnected, $hashedPassword);

            $centreon->user->setPasswd($hashedPassword);

            LoggerPassword::create()->success(
                initiatorId: $userIdConnected,
                targetId: $userIdConnected,
            );
        } catch (PDOException $e) {
            LoggerPassword::create()->warning(
                reason: 'password update failed',
                initiatorId: $userIdConnected,
                targetId: $userIdConnected,
                exception: $e
            );

            throw new RepositoryException(
                message: 'Unable to update password from my account for contact id ' . $userIdConnected,
                context: ['userIdConnected' => $userIdConnected],
                previous: $e
            );
        }
    }

    // Update user object..
    $centreon->user->name = $submitValues['contact_name'];
    $centreon->user->alias = $submitValues['contact_alias'];
    $centreon->user->lang = $submitValues['contact_lang'];
    $centreon->user->email = $submitValues['contact_email'];
    $centreon->user->setToken($submitValues['contact_autologin_key'] ?? "''");
}

/**
 * @param array<string,mixed> $fields
 *
 * @throws InvalidArgumentException
 *
 * @return array<string,string>|true
 */
function validatePasswordModification(array $fields): array|bool
{
    global $pearDB, $centreon;
    $newPassword = $fields['contact_passwd'];
    $confirmPassword = $fields['contact_passwd2'];
    $currentPassword = $fields['current_password'];

    $userIdConnected = (int) $centreon->user->get_id();

    if (! $userIdConnected > 0) {
        throw new InvalidArgumentException('Invalid connected user ID provided for password modification validation');
    }

    // If the user does not want to change his password, we do not need to check it
    if (empty($newPassword) && empty($confirmPassword) && empty($currentPassword)) {
        return true;
    }

    // If the user only provided a confirmation password, he must provide a new password and a current password
    if (empty($newPassword) && ! empty($confirmPassword) && empty($currentPassword)) {
        LoggerPassword::create()->warning(
            reason: 'new password or current password not provided',
            initiatorId: $userIdConnected,
            targetId: $userIdConnected,
        );

        return ['contact_passwd2' => _('Please fill in all password fields')];
    }

    // If the user only provided his current password, he must provide a new password
    if (empty($newPassword) && ! empty($currentPassword)) {
        LoggerPassword::create()->warning(
            reason: 'new password not provided',
            initiatorId: $userIdConnected,
            targetId: $userIdConnected,
        );

        return ['current_password' => _('Please fill in all password fields')];
    }

    // If the user wants to change his password, he must provide his current password
    if (! empty($newPassword) && empty($currentPassword)) {
        LoggerPassword::create()->warning(
            reason: 'current password not provided',
            initiatorId: $userIdConnected,
            targetId: $userIdConnected,
        );

        return ['current_password' => _('Please fill in all password fields')];
    }

    // If the user provided a current password, we check if it matches the one in the database
    if (! empty($currentPassword) && password_verify($currentPassword, $centreon->user->passwd) === false) {
        LoggerPassword::create()->warning(
            reason: 'current password wrong',
            initiatorId: $userIdConnected,
            targetId: $userIdConnected,
        );

        return ['current_password' => _('Authentication failed')];
    }

    try {
        $contact = new CentreonContact($pearDB);
        $contact->respectPasswordPolicyOrFail($newPassword, $userIdConnected);

        return true;
    } catch (Exception $e) {
        LoggerPassword::create()->warning(
            reason: 'new password does not respect password policy',
            initiatorId: $userIdConnected,
            targetId: $userIdConnected,
            exception: $e,
        );

        return ['contact_passwd' => $e->getMessage()];
    }
}

/**
 * @param array<string,mixed> $fields
 *
 * @throws RepositoryException
 * @throws InvalidArgumentException
 *
 * @return array<string,string>|true
 */
function checkAutologinValue(array $fields): array|bool
{
    global $pearDB, $centreon;
    $errors = [];

    if (! empty($fields['contact_autologin_key'])) {

        $userIdConnected = (int) $centreon->user->get_id();

        if (! $userIdConnected > 0) {
            throw new InvalidArgumentException('Invalid connected user ID provided for autologin key check');
        }

        $query = <<<'SQL'
            SELECT * FROM `contact_password`
            WHERE contact_id = :contactId
            ORDER BY creation_date DESC
            LIMIT 1
            SQL;

        try {
            $contactPassword = $pearDB->fetchAssociative(
                $query,
                QueryParameters::create([QueryParameter::int('contactId', $userIdConnected)])
            );
        } catch (ValueObjectException|CollectionException|ConnectionException $e) {
            throw new RepositoryException(
                message: 'Unable to fetch contact password for contact id ' . $userIdConnected,
                context: ['userIdConnected' => $userIdConnected],
                previous: $e
            );
        }

        if (password_verify($fields['contact_autologin_key'], $contactPassword['password'])) {
            $errors['contact_autologin_key'] = _('Your autologin key must be different than your current password');
        } elseif (
            !empty($fields['contact_passwd'])
            && $fields['contact_passwd'] === $fields['contact_autologin_key']
        ) {
            $errorMessage = _('Your new password and autologin key must be different');
            $errors['contact_passwd'] = $errorMessage;
            $errors['contact_autologin_key'] = $errorMessage;

            LoggerPassword::create()->warning(
                reason: 'new password and autologin key are the same',
                initiatorId: $userIdConnected,
                targetId: $userIdConnected,
            );
        }
    }

    return count($errors) > 0 ? $errors : true;
}
function updateNonLocalContactByMyAccountInDB($userIdConnected = null): void
{
    global $pearDB, $centreon, $form;

    if (! $userIdConnected) {
        return;
    }
    $ret = $form->getSubmitValues();
    $ret['contact_pager'] = !empty($ret['contact_pager']) ?
        CentreonUtils::escapeSecure($ret['contact_pager'], CentreonUtils::ESCAPE_ILLEGAL_CHARS) : '';
    $ret['contact_lang'] = !empty($ret['contact_lang']) ?
        CentreonUtils::escapeSecure($ret['contact_lang'], CentreonUtils::ESCAPE_ILLEGAL_CHARS) : '';

    $rq = 'UPDATE contact SET ' .
        'contact_location = :contactLocation, ' .
        'contact_lang = :contactLang, ' .
        'contact_pager = :contactPager, ' .
        'default_page = :defaultPage, ' .
        'show_deprecated_pages = :showDeprecatedPages';

    $rq .= ' WHERE contact_id = :contactId';

    $stmt = $pearDB->prepare($rq);
    $stmt->bindValue(':contactLang', $ret['contact_lang'], \PDO::PARAM_STR);
    $stmt->bindValue(
        ':contactPager',
        !empty($ret['contact_pager']) ? $ret['contact_pager'] : null,
        \PDO::PARAM_STR
    );
    $stmt->bindValue(
        ':contactLocation',
        !empty($ret['contact_location']) ? $ret['contact_location'] : null,
        \PDO::PARAM_INT
    );

    $stmt->bindValue(':defaultPage', ! empty($ret['default_page']) ? $ret['default_page'] : null, PDO::PARAM_INT);
    $stmt->bindValue(':showDeprecatedPages', isset($ret['show_deprecated_pages']) ? 1 : 0, PDO::PARAM_STR);
    $stmt->bindValue(':contactId', $userIdConnected, PDO::PARAM_INT);
    $stmt->execute();
    $stmt->closeCursor();

    /*
     * Update user object..
     */
    $centreon->user->lang = $ret['contact_lang'];

    if ($centreon->user->authType === 'ldap') {
        updateNotificationOptions($userIdConnected);
    }
}
