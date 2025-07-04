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

require_once _CENTREON_PATH_ . '/www/class/centreonLog.class.php';

/**
 * Class
 *
 * @class CentreonLdapSynchro
 * @description Webservice to request LDAP data synchronization for a selected contact.
 */
class CentreonLdapSynchro extends CentreonWebService
{
    /** @var CentreonDB */
    protected $pearDB;

    /** @var CentreonLog */
    protected $centreonLog;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->pearDB = new CentreonDB();
        $this->centreonLog = new CentreonLog();
    }

    /**
     * Used to request a data synchronization of a contact from the contact page
     * Using the contact ID or the session PHPSESSID value
     *
     * Each instance of Centreon using this contact account will be killed
     *
     * Method POST
     *
     * @throws PDOException
     * @return bool
     */
    public function postRequestLdapSynchro(): bool
    {
        $result = false;

        $contactId = filter_var(
            $_POST['contactId'] ?? false,
            FILTER_VALIDATE_INT
        );

        if (! $this->isLdapEnabled()) {
            return $result;
        }

        if ($contactId === false) {
            $this->centreonLog->insertLog(
                3, // ldap.log
                'LDAP MANUAL SYNC : Error - Chosen contact id is not consistent.'
            );

            return $result;
        }

        $this->pearDB->beginTransaction();
        try {
            $resUser = $this->pearDB->prepare(
                'SELECT `contact_id`, `contact_name` FROM `contact`
                WHERE `contact_id` = :contactId'
            );
            $resUser->bindValue(':contactId', $contactId, PDO::PARAM_INT);
            $resUser->execute();
            $contact = $resUser->fetch();

            // requiring a manual synchronization at next login of the contact
            $stmtRequiredSync = $this->pearDB->prepare(
                'UPDATE contact
                SET `contact_ldap_required_sync` = "1"
                WHERE contact_id = :contactId'
            );
            $stmtRequiredSync->bindValue(':contactId', $contact['contact_id'], PDO::PARAM_INT);
            $stmtRequiredSync->execute();

            // checking if the contact is currently connected to Centreon
            $activeSession = $this->pearDB->prepare(
                'SELECT session_id FROM `session` WHERE user_id = :contactId'
            );
            $activeSession->bindValue(':contactId', $contact['contact_id'], PDO::PARAM_INT);
            $activeSession->execute();

            // disconnecting every session using this contact data
            $logoutContact = $this->pearDB->prepare(
                'DELETE FROM session WHERE session_id = :userSessionId'
            );
            while ($rowSession = $activeSession->fetch()) {
                $logoutContact->bindValue(':userSessionId', $rowSession['session_id'], PDO::PARAM_STR);
                $logoutContact->execute();
            }
            $this->pearDB->commit();
            $this->centreonLog->insertLog(
                3,
                'LDAP MANUAL SYNC : Successfully planned LDAP synchronization for ' . $contact['contact_name']
            );
            $result = true;
        } catch (PDOException $e) {
            $this->centreonLog->insertLog(
                2, // sql-error.log
                'LDAP MANUAL SYNC : Error - unable to read or update the contact data in the DB.'
            );
            $this->pearDB->rollBack();
        }

        return $result;
    }

    /**
     * Checking if LDAP is enabled
     *
     * @throws PDOException
     * @return bool
     */
    private function isLdapEnabled()
    {
        // checking if at least one LDAP configuration is still enabled
        $ldapEnable = $this->pearDB->query(
            "SELECT `value` FROM `options` WHERE `key` = 'ldap_auth_enable'"
        );
        $row = $ldapEnable->fetch();
        if ($row['value'] !== '1') {
            $this->centreonLog->insertLog(
                3,
                'LDAP MANUAL SYNC : Error - No enabled LDAP configuration found.'
            );

            return false;
        }

        return true;
    }
}
