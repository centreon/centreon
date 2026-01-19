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

use Adaptation\Database\Connection\Adapter\Pdo\Transformer\PdoParameterTypeTransformer;
use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Log\LoggerPassword;
use App\Kernel;
use Centreon\Domain\Log\Logger;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\TransformerException;
use Core\Common\Domain\Exception\ValueObjectException;

if (!isset($centreon)) {
    exit();
}

require_once __DIR__ . '/../../../../../bootstrap.php';
require_once __DIR__ . '/../../../../class/centreonAuth.class.php';
require_once __DIR__ . '/../../../../class/centreonContact.class.php';
require_once _CENTREON_PATH_ . '/www/include/common/sqlCommonFunction.php';

/**
 * @param string|null $name
 * @param bool|null $preventLog
 * @throws RepositoryException
 * @return bool
 */
function testContactExistence(?string $name = null, ?bool $preventLog = false): bool
{
    global $pearDB, $form, $centreon;

    $id = null;
    if (isset($form)) {
        $id = $form->getSubmitValue('contact_id');
    }

    $contactName = $centreon->checkIllegalChar($name);

    $query = <<<'SQL'
            SELECT contact_name, contact_id
            FROM contact
            WHERE contact_name = :contact_name
        SQL;

    try {
        $contact = $pearDB->fetchAssociative(
            $query,
            QueryParameters::create([
                QueryParameter::string('contact_name', $contactName),
            ])
        );
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        if ($preventLog !== true) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_BUSINESS_LOG,
                'Error while executing testContactExistence: ' . $exception->getMessage(),
                ['contact_name' => $contactName],
                exception: $exception,
            );
        }

        throw new RepositoryException(
            'Error while executing testContactExistence',
            ['contact_name' => $contactName],
            $exception
        );
    }

    if ($contact && $contact['contact_id'] == $id) {
        return true;
    }

    return ! ($contact && $contact['contact_id'] != $id);
}

/**
 * @param string|null $alias
 * @param bool|null $preventLog
 * @throws RepositoryException
 * @return bool
 */
function testAliasExistence(?string $alias = null, ?bool $preventLog = false): bool
{
    global $pearDB, $form;
    $id = null;
    if (isset($form)) {
        $id = $form->getSubmitValue('contact_id');
    }
    $query = <<<'SQL'
            SELECT contact_id
            FROM contact
            WHERE contact_alias = :contact_alias
        SQL;
    try {
        $contact = $pearDB->fetchAssociative(
            $query,
            QueryParameters::create([
                QueryParameter::string('contact_alias', $alias ?? ''),
            ]),
        );
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        if ($preventLog !== true) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_BUSINESS_LOG,
                'Error while executing testAliasExistence: ' . $exception->getMessage(),
                ['contact_alias' => $alias],
                exception: $exception,
            );
        }

        throw new RepositoryException(
            'Error while executing testAliasExistence',
            ['contact_alias' => $alias],
            $exception,
        );
    }

    if ($contact && $contact['contact_id'] == $id) {
        return true;
    }

    return ! ($contact && $contact['contact_id'] != $id);
}

/**
 * @param string|int|null $ct_id
 * @param bool $log
 * @throws RepositoryException
 * @return bool
 */
function keepOneContactAtLeast(string|int|null $ct_id = null, bool $log = true): bool
{
    global $pearDB, $form, $centreon;

    if (isset($ct_id)) {
        $contactId = $ct_id;
    } elseif (isset($_GET['contact_id'])) {
        $contactId = htmlentities($_GET['contact_id'], ENT_QUOTES, 'UTF-8');
    } else {
        $contactId = $form->getSubmitValue('contact_id');
    }

    if (isset($form)) {
        $contactOreOn = $form->getSubmitValue('contact_oreon');
        $contactActivate = $form->getSubmitValue('contact_activate');
    } else {
        $contactOreOn = 0;
        $contactActivate = 0;
    }

    if ($contactId == $centreon->user->get_id()) {
        return false;
    }

    // Get activated contacts
    $query = <<<'SQL'
        SELECT COUNT(*) AS nbr_valid
        FROM contact
        WHERE contact_activate = '1'
          AND contact_oreon = '1'
          AND contact_id <> :contact_id
        SQL;

    try {
        $nbr = $pearDB->fetchOne(
            $query,
            QueryParameters::create([
                QueryParameter::int('contact_id', (int) $contactId),
            ])
        );
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        if ($log) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_BUSINESS_LOG,
                'Error while checking remaining active Centreon contacts: ' . $exception->getMessage(),
                ['contact_id' => $contactId],
                exception: $exception,
            );
        }

        throw new RepositoryException(
            'Error while checking remaining active Centreon contacts',
            ['contact_id' => $contactId],
            $exception
        );
    }

    if ((int) $nbr === 0) {
        if ($contactOreOn == 0 || $contactActivate == 0) {
            return false;
        }
    }
    return true;
}

/**
 *
 * Enable contacts
 * @param $contact_id
 * @param $contact_arr
 */
function enableContactInDB($contact_id = null, $contact_arr = [])
{
    global $pearDB, $centreon;

    if (!$contact_id && !count($contact_arr)) {
        return;
    }
    if ($contact_id) {
        $contact_arr = [$contact_id => "1"];
    }

    foreach (array_keys($contact_arr) as $contactId) {
        $updateQuery = <<<'SQL'
                UPDATE contact
                SET contact_activate = '1'
                WHERE contact_id = :contact_id
            SQL;
        $selectQuery = <<<'SQL'
                SELECT contact_name
                FROM contact
                WHERE contact_id = :contact_id
                LIMIT 1
            SQL;
        try {
            $pearDB->update(
                $updateQuery,
                QueryParameters::create([
                    QueryParameter::int('contact_id', (int) $contactId),
                ])
            );
            $row = $pearDB->fetchAssociative(
                $selectQuery,
                QueryParameters::create([
                    QueryParameter::int('contact_id', (int) $contactId),
                ])
            );
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            throw new RepositoryException(
                'Error while enabling contact',
                ['contact_id' => $contactId],
                $exception,
            );
        }

        if ($row === false) {
            continue;
        }

        $centreon->CentreonLogAction->insertLog('contact', $contactId, $row['contact_name'], 'enable');
    }
}

/**
 *
 * Disable Contacts
 * @param $contact_id
 * @param $contact_arr
 */
function disableContactInDB($contact_id = null, $contact_arr = [])
{
    global $pearDB, $centreon;

    if (!$contact_id && !count($contact_arr)) {
        return;
    }
    if ($contact_id) {
        $contact_arr = [$contact_id => "1"];
    }

    foreach (array_keys($contact_arr) as $contactId) {
        if (keepOneContactAtLeast($contactId)) {
            $updateQuery = <<<'SQL'
                    UPDATE contact
                    SET contact_activate = '0'
                    WHERE contact_id = :contact_id
                SQL;
            $selectQuery = <<<'SQL'
                    SELECT contact_name
                    FROM contact
                    WHERE contact_id = :contact_id
                    LIMIT 1
                SQL;
            try {
                $pearDB->update(
                    $updateQuery,
                    QueryParameters::create([
                        QueryParameter::int('contact_id', (int) $contactId),
                    ])
                );
                $row = $pearDB->fetchAssociative(
                    $selectQuery,
                    QueryParameters::create([
                        QueryParameter::int('contact_id', (int) $contactId),
                    ])
                );
            } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
                throw new RepositoryException(
                    'Error while disabling contact',
                    ['contact_id' => $contactId],
                    $exception,
                );
            }

            if ($row === false) {
                continue;
            }

            $centreon->CentreonLogAction->insertLog('contact', $contactId, $row['contact_name'], 'disable');
        }
    }
}

/**
 * Unblock contacts in the database
 *
 * @param int|array<int, string>|null $contact Contact ID, array of contact IDs or null to unblock all contacts
 * @throws RepositoryException
 */
function unblockContactInDB(int|array|null $contact = null): void
{
    global $pearDB, $centreon;

    if (null === $contact || [] === $contact) {
        return;
    }

    // Normalize input
    $contactIds = is_int($contact) ? [$contact] : array_map('intval', array_keys($contact));

    if ($contactIds === []) {
        return;
    }

    try {
        // Build IN() clause safely
        [$inClause, $queryParameters] = createMultipleBindParameters(
            $contactIds,
            'contact_id_',
            QueryParameterTypeEnum::INTEGER,
        );

        // Retrieve contacts for logging
        $selectQuery = <<<SQL
            SELECT contact_id, contact_name
            FROM contact
            WHERE contact_id IN ({$inClause})
            SQL;

        $contacts = $pearDB->fetchAllAssociative(
            $selectQuery,
            $queryParameters
        );

        // Unblock contacts
        $updateQuery = <<<SQL
            UPDATE contact
            SET blocking_time = NULL
            WHERE contact_id IN ({$inClause})
            SQL;

        $pearDB->update(
            $updateQuery,
            $queryParameters
        );

    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        throw new RepositoryException(
            'Error while unblocking contacts',
            ['contact_ids' => $contactIds],
            $exception
        );
    }

    foreach ($contacts as $contact) {
        $centreon->CentreonLogAction->insertLog(
            'contact',
            (int) $contact['contact_id'],
            $contact['contact_name'],
            'unblock'
        );
    }
}

/**
 * Delete Contacts
 * @param array $contacts
 * @throws RepositoryException
 */
function deleteContactInDB(array $contacts = []): void
{
    global $pearDB, $centreon;

    if ($contacts === []) {
        return;
    }

    try {
        $ownTransaction = ! $pearDB->isTransactionActive();
        if ($ownTransaction) {
            $pearDB->startTransaction();
        }

        foreach (array_keys($contacts) as $contactId) {
            $contactId = (int) $contactId;

            $params = QueryParameters::create([
                QueryParameter::create('contactId', $contactId, QueryParameterTypeEnum::INTEGER),
            ]);

            $row = $pearDB->fetchAssociative(
                <<<'SQL'
                        SELECT contact_name
                        FROM contact
                        WHERE contact_id = :contactId
                        LIMIT 1
                    SQL,
                $params
            );
            if ($row === false) {
                continue;
            }

            $contactName = $row['contact_name'];

            // Fetch authentication tokens
            $tokens = $pearDB->fetchAllAssociative(
                <<<'SQL'
                    SELECT token
                    FROM security_authentication_tokens
                    WHERE user_id = :contactId
                    SQL,
                $params
            );

            // Delete tokens
            foreach ($tokens as $tokenRow) {
                $pearDB->delete(
                    <<<'SQL'
                        DELETE FROM security_token
                        WHERE token = :token
                        SQL,
                    QueryParameters::create([
                        QueryParameter::string('token', $tokenRow['token']),
                    ])
                );
            }

            // Delete contact
            $pearDB->delete(
                <<<'SQL'
                    DELETE FROM contact
                    WHERE contact_id = :contactId
                    SQL,
                $params
            );

            // Log deletion
            $centreon->CentreonLogAction->insertLog(
                'contact',
                $contactId,
                $contactName,
                'd'
            );
        }

        if ($ownTransaction) {
            $pearDB->commitTransaction();
        }
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        try {
            if (($ownTransaction ?? false) && $pearDB->isTransactionActive()) {
                $pearDB->rollBackTransaction();
            }
        } catch (ConnectionException $rollbackException) {
            throw new RepositoryException(
                'Failed to roll back transaction in deleteContactInDB: ' . $rollbackException->getMessage(),
                [
                    'contact_ids' => array_keys($contacts),
                ],
                $rollbackException
            );
        }

        throw new RepositoryException(
            'Error while executing deleteContactInDB',
            [
                'contact_ids' => array_keys($contacts),
            ],
            $exception
        );
    }
}

/**
 * Synchronize LDAP with contacts' data
 * Used for massive sync request
 * @param array $contacts
 */
function synchronizeContactWithLdap(array $contacts = []): void
{
    global $pearDB;
    $centreonLog = new CentreonLog();

    // checking if at least one LDAP configuration is still enabled
    $ldapEnable = $pearDB->query(
        "SELECT `value` FROM `options` WHERE `key` = 'ldap_auth_enable'"
    );
    $rowLdapEnable = $ldapEnable->fetch();

    if ($rowLdapEnable['value'] === '1') {
        // getting the contact name for the logs
        $contactNameStmt = $pearDB->prepare(
            "SELECT contact_name, `ar_id`
            FROM `contact`
            WHERE `contact_id` = :contactId
            AND `ar_id` IS NOT NULL"
        );

        // requiring a manual synchronization at next login of the contact
        $stmtRequiredSync = $pearDB->prepare(
            'UPDATE contact
            SET `contact_ldap_required_sync` = "1"
            WHERE contact_id = :contactId'
        );

        // checking if the contact is currently logged in Centreon
        $activeSession = $pearDB->prepare(
            "SELECT session_id FROM `session` WHERE user_id = :contactId"
        );

        // disconnecting the active user from centreon
        $logoutContact = $pearDB->prepare(
            "DELETE FROM session WHERE session_id = :userSessionId"
        );

        $successfullySync = [];
        $pearDB->beginTransaction();
        try {
            foreach ($contacts as $key => $value) {
                $contactNameStmt->bindValue(':contactId', (int)$key, \PDO::PARAM_INT);
                $contactNameStmt->execute();
                $rowContact = $contactNameStmt->fetch();
                if (!$rowContact['ar_id']) {
                    // skipping chosen contacts not bound to an LDAP
                    continue;
                }

                $stmtRequiredSync->bindValue(':contactId', (int)$key, \PDO::PARAM_INT);
                $stmtRequiredSync->execute();

                $activeSession->bindValue(':contactId', (int)$key, \PDO::PARAM_INT);
                $activeSession->execute();
                //disconnecting every session logged in using this contact data
                while ($rowSession = $activeSession->fetch()) {
                    $logoutContact->bindValue(':userSessionId', $rowSession['session_id'], \PDO::PARAM_STR);
                    $logoutContact->execute();
                }
                $successfullySync[] = $rowContact['contact_name'];
            }
            $pearDB->commit();
            foreach ($successfullySync as $key => $value) {
                $centreonLog->insertLog(
                    3, //ldap.log
                    "LDAP MULTI SYNC : Successfully planned LDAP synchronization for " . $value
                );
            }
        } catch (\PDOException $e) {
            $pearDB->rollBack();
            throw new Exception('Bad Request : ' . $e);
        }
    } else {
        // unable to plan the manual LDAP request of the contacts
        $centreonLog->insertLog(
            3,
            "LDAP MANUAL SYNC : No LDAP configuration is enabled"
        );
    }
}

/**
 * Duplicate a list of contact
 *
 * @param array $contacts list of contact ids to duplicate
 * @param array $nbrDup Number of duplication per contact id
 * @throws RepositoryException
 * @return array List of the new contact ids
 */
function multipleContactInDB($contacts = [], $nbrDup = []): array
{
    global $pearDB, $centreon;
    $newContactIds = [];
    foreach ($contacts as $contactId => $value) {
        $contactId = (int) $contactId;
        $newContactIds[$contactId] = [];

        $selectContactQuery = <<<'SQL'
            SELECT c.*, cp.password, cp.creation_date
            FROM contact c
            LEFT JOIN contact_password cp ON cp.contact_id = c.contact_id
            WHERE c.contact_id = :contactId
            LIMIT 1
            SQL;

        try {
            $row = $pearDB->fetchAssociative(
                $selectContactQuery,
                QueryParameters::create([
                    QueryParameter::int('contactId', $contactId),
                ])
            );
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            throw new RepositoryException(
                'Error while fetching contact to duplicate',
                ['contact_id' => $contactId],
                $exception,
            );
        }

        if ($row === false) {
            continue;
        }

        $password = $row['password'] ?? null;
        $creationDate = $row['creation_date'] ?? null;

        // We do not insert these fields into contact table
        unset($row['password'], $row['creation_date'], $row['contact_id']);
        $baseInsertData = $row;
        $columns = array_keys($baseInsertData);
        $columnSql = implode(', ', array_map(static fn ($column) => '`' . $column . '`', $columns));
        $placeholders = array_map(static fn ($column) => ':c_' . $column, $columns);
        $insertQuery = sprintf(
            'INSERT INTO contact (%s) VALUES (%s)',
            $columnSql,
            implode(', ', $placeholders)
        );

        $dupCount = isset($nbrDup[$contactId]) ? (int) $nbrDup[$contactId] : 0;
        if ($dupCount <= 0) {
            continue;
        }

        for ($i = 1; $i <= $dupCount; $i++) {
            $insertData = $baseInsertData;

            // Prepare duplicated values
            $contactName = isset($insertData['contact_name']) ? ((string) $insertData['contact_name'] . '_' . $i) : null;
            $contactAlias = isset($insertData['contact_alias']) ? ((string) $insertData['contact_alias'] . '_' . $i) : null;

            if ($contactName !== null) {
                $contactName = $centreon->checkIllegalChar($contactName);
            }

            if (! testContactExistence($contactName, true) || ! testAliasExistence($contactAlias, true)) {
                continue;
            }

            $insertData['contact_name'] = $contactName;
            $insertData['contact_alias'] = $contactAlias;

            $queryParameters = [];
            foreach ($insertData as $col => $value) {
                $paramName = 'c_' . $col;
                $queryParameters[] = match (true) {
                    $value === null => QueryParameter::null($paramName),
                    is_int($value) => QueryParameter::int($paramName, $value),
                    is_bool($value) => QueryParameter::bool($paramName, $value),
                    default => QueryParameter::string($paramName, (string) $value),
                };
            }

            try {
                $pearDB->insert($insertQuery, QueryParameters::create($queryParameters));
                $lastId = (int) $pearDB->getLastInsertId();
            } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
                throw new RepositoryException(
                    'Error while inserting duplicated contact',
                    [
                        'original_contact_id' => $contactId,
                        'contact_name' => $contactName,
                        'contact_alias' => $contactAlias,
                    ],
                    $exception,
                );
            }

            if ($lastId <= 0) {
                continue;
            }

            if ($password !== null) {
                try {
                    $contact = new CentreonContact($pearDB);
                    $contact->addPasswordByContactId($lastId, $password);
                    if ($creationDate !== null) {
                        $updateCreationDateQuery = <<<'SQL'
                            UPDATE contact_password
                            SET creation_date = :creationDate
                            WHERE contact_id = :contactId
                            SQL;

                        $pearDB->update(
                            $updateCreationDateQuery,
                            QueryParameters::create([
                                QueryParameter::int('creationDate', (int) $creationDate),
                                QueryParameter::int('contactId', $lastId),
                            ])
                        );
                    }
                } catch (Throwable $exception) {
                    throw new RepositoryException(
                        'Error while duplicating contact password',
                        [
                            'new_contact_id' => $lastId,
                            'original_contact_id' => $contactId,
                        ],
                        $exception,
                    );
                }
            }

            $newContactIds[$contactId][] = $lastId;

            // --- Copy relations (ACL, host commands, service commands, contact groups) ---
            $fields = [];

            try {
                // ACL relations
                $aclIdsQuery = <<<'SQL'
                    SELECT DISTINCT acl_group_id
                    FROM acl_group_contacts_relations
                    WHERE contact_contact_id = :contactId
                    SQL;

                $aclRelations = $pearDB->fetchAllAssociative(
                    $aclIdsQuery,
                    QueryParameters::create([QueryParameter::int('contactId', $contactId)])
                );

                $fields['contact_aclRelation'] = '';
                $insertAclQuery = <<<'SQL'
                    INSERT INTO acl_group_contacts_relations (contact_contact_id, acl_group_id)
                    VALUES (:newContactId, :aclGroupId)
                    SQL;

                foreach ($aclRelations as $aclRelation) {
                    $pearDB->insert(
                        $insertAclQuery,
                        QueryParameters::create([
                            QueryParameter::int('newContactId', $lastId),
                            QueryParameter::int('aclGroupId', (int) $aclRelation['acl_group_id']),
                        ])
                    );
                    $fields['contact_aclRelation'] .= $aclRelation['acl_group_id'] . ',';
                }
                $fields['contact_aclRelation'] = trim($fields['contact_aclRelation'], ',');

                // Host commands
                $hostCmdIdsQuery = <<<'SQL'
                    SELECT DISTINCT command_command_id
                    FROM contact_hostcommands_relation
                    WHERE contact_contact_id = :contactId
                    SQL;

                $hostCmds = $pearDB->fetchAllAssociative(
                    $hostCmdIdsQuery,
                    QueryParameters::create([QueryParameter::int('contactId', $contactId)])
                );

                $fields['contact_hostNotifCmds'] = '';
                $insertHostCmdQuery = <<<'SQL'
                    INSERT INTO contact_hostcommands_relation (contact_contact_id, command_command_id)
                    VALUES (:newContactId, :commandId)
                    SQL;

                foreach ($hostCmds as $hostCmd) {
                    $pearDB->insert(
                        $insertHostCmdQuery,
                        QueryParameters::create([
                            QueryParameter::int('newContactId', $lastId),
                            QueryParameter::int('commandId', (int) $hostCmd['command_command_id']),
                        ])
                    );
                    $fields['contact_hostNotifCmds'] .= $hostCmd['command_command_id'] . ',';
                }
                $fields['contact_hostNotifCmds'] = trim($fields['contact_hostNotifCmds'], ',');

                // Service commands
                $svcCmdIdsQuery = <<<'SQL'
                    SELECT DISTINCT command_command_id
                    FROM contact_servicecommands_relation
                    WHERE contact_contact_id = :contactId
                    SQL;

                $svcCmds = $pearDB->fetchAllAssociative(
                    $svcCmdIdsQuery,
                    QueryParameters::create([QueryParameter::int('contactId', $contactId)])
                );

                $fields['contact_svNotifCmds'] = '';
                $insertSvcCmdQuery = <<<'SQL'
                    INSERT INTO contact_servicecommands_relation (contact_contact_id, command_command_id)
                    VALUES (:newContactId, :commandId)
                    SQL;

                foreach ($svcCmds as $svcCmd) {
                    $pearDB->insert(
                        $insertSvcCmdQuery,
                        QueryParameters::create([
                            QueryParameter::int('newContactId', $lastId),
                            QueryParameter::int('commandId', (int) $svcCmd['command_command_id']),
                        ])
                    );
                    $fields['contact_svNotifCmds'] .= $svcCmd['command_command_id'] . ',';
                }
                $fields['contact_svNotifCmds'] = trim($fields['contact_svNotifCmds'], ',');

                // Contact groups
                $cgIdsQuery = <<<'SQL'
                    SELECT DISTINCT contactgroup_cg_id
                    FROM contactgroup_contact_relation
                    WHERE contact_contact_id = :contactId
                    SQL;

                $cgs = $pearDB->fetchAllAssociative(
                    $cgIdsQuery,
                    QueryParameters::create([QueryParameter::int('contactId', $contactId)])
                );

                $fields['contact_cgNotif'] = '';
                $insertCgQuery = <<<'SQL'
                    INSERT INTO contactgroup_contact_relation (contact_contact_id, contactgroup_cg_id)
                    VALUES (:newContactId, :cgId)
                    SQL;

                foreach ($cgs as $cg) {
                    $pearDB->insert(
                        $insertCgQuery,
                        QueryParameters::create([
                            QueryParameter::int('newContactId', $lastId),
                            QueryParameter::int('cgId', (int) $cg['contactgroup_cg_id']),
                        ])
                    );
                    $fields['contact_cgNotif'] .= $cg['contactgroup_cg_id'] . ',';
                }
                $fields['contact_cgNotif'] = trim($fields['contact_cgNotif'], ',');

            } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
                throw new RepositoryException(
                    'Error while duplicating contact relations',
                    ['original_contact_id' => $contactId, 'new_contact_id' => $lastId],
                    $exception
                );
            }

            // Log (same behavior)
            $centreon->CentreonLogAction->insertLog(
                'contact',
                $lastId,
                (string) $contactName,
                'a',
                $fields
            );
        }
    }

    return $newContactIds;
}

/**
 * @param mixed $contact_id
 * @param bool $from_MC
 * @param bool $isRemote
 * @throws RepositoryException
 */
function updateContactInDB(mixed $contact_id, bool $from_MC = false, bool $isRemote = false): void
{
    global $form;

    $contact_id = (int) $contact_id;

    if (! $contact_id > 0) {
        throw new RepositoryException(
            message: 'Invalid contact ID provided to update contact from contact page',
            context: ['contact_id' => $contact_id]
        );
    }

    $ret = $form->getSubmitValues();

    // Global function to use
    if ($from_MC) {
        updateContact_MC($contact_id);
    } else {
        updateContact($contact_id);
    }

    // Function for updating host commands
    // 1 - MC with deletion of existing cmds
    // 2 - MC with addition of new cmds
    // 3 - Normal update
    if (isset($ret['mc_mod_hcmds']['mc_mod_hcmds']) && $ret['mc_mod_hcmds']['mc_mod_hcmds']) {
        updateContactHostCommands($contact_id);
    } elseif (isset($ret["mc_mod_hcmds"]["mc_mod_hcmds"]) && !$ret["mc_mod_hcmds"]["mc_mod_hcmds"]) {
        updateContactHostCommands_MC($contact_id);
    } else {
        updateContactHostCommands($contact_id);
    }
    # Function for updating service commands
    # 1 - MC with deletion of existing cmds
    # 2 - MC with addition of new cmds
    # 3 - Normal update
    if (isset($ret["mc_mod_svcmds"]["mc_mod_svcmds"]) && $ret["mc_mod_svcmds"]["mc_mod_svcmds"]) {
        updateContactServiceCommands($contact_id);
    } elseif (isset($ret["mc_mod_svcmds"]["mc_mod_svcmds"]) && !$ret["mc_mod_svcmds"]["mc_mod_svcmds"]) {
        updateContactServiceCommands_MC($contact_id);
    } else {
        updateContactServiceCommands($contact_id);
    }
    # Function for updating contact groups
    # 1 - MC with deletion of existing cg
    # 2 - MC with addition of new cg
    # 3 - Normal update
    if (! $isRemote) {
        if (isset($ret["mc_mod_cg"]["mc_mod_cg"]) && $ret["mc_mod_cg"]["mc_mod_cg"]) {
            updateContactContactGroup($contact_id);
        } elseif (isset($ret["mc_mod_cg"]["mc_mod_cg"]) && !$ret["mc_mod_cg"]["mc_mod_cg"]) {
            updateContactContactGroup_MC($contact_id);
        } else {
            updateContactContactGroup($contact_id);
        }
    }

    /**
     * ACL
     */
    if (isset($ret["mc_mod_acl"]["mc_mod_acl"]) && $ret["mc_mod_acl"]["mc_mod_acl"]) {
        updateAccessGroupLinks($contact_id);
    } elseif (isset($ret["mc_mod_acl"]["mc_mod_acl"]) && !$ret["mc_mod_acl"]["mc_mod_acl"]) {
        updateAccessGroupLinks_MC($contact_id, $ret["mc_mod_acl"]["mc_mod_acl"]);
    } else {
        updateAccessGroupLinks($contact_id);
    }
}

/**
 * @param array $ret
 * @throws RepositoryException
 * @return int
 */
function insertContactInDB(array $ret = []): int
{
    $contactId = insertContact($ret);
    updateContactHostCommands($contactId, $ret);
    updateContactServiceCommands($contactId, $ret);
    updateContactContactGroup($contactId, $ret);
    updateAccessGroupLinks($contactId);

    return $contactId;
}

/**
 * @param array $ret
 * @throws RepositoryException
 * @return int
 */
function insertContact(array $ret = []): int
{
    global $form, $pearDB, $centreon;

    if ($ret === []) {
        $ret = $form->getSubmitValues();
    }

    $ret['contact_name'] = $centreon->checkIllegalChar($ret['contact_name']);

    if (
        isset($ret['contact_oreon']['contact_oreon'])
        && $ret['contact_oreon']['contact_oreon'] === '1'
    ) {
        $ret['reach_api_rt']['reach_api_rt'] = '1';
    }

    if (! $centreon->user->admin) {
        $ret = filterNonAdminFields($ret);
    }

    try {
        $bindParams = sanitizeFormContactParameters($ret);
    } catch (InvalidArgumentException $exception) {
        throw new RepositoryException(
            'Error while sanitizing contact parameters during insertContact',
            ['contact_name' => $ret['contact_name'] ?? null],
            $exception,
        );
    }

    try {
        $columns = [];
        foreach (array_keys($bindParams) as $token) {
            $columns[] = ltrim($token, ':');
        }

        $insertQuery = sprintf(
            'INSERT INTO contact (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', array_keys($bindParams))
        );

        $stmtParams = [];
        foreach ($bindParams as $token => $values) {
            foreach ($values as $type => $value) {
                $stmtParams[] = QueryParameter::create(
                    ltrim($token, ':'),
                    $value,
                    PdoParameterTypeTransformer::reverseToQueryParameterType($type),
                );
            }
        }

        $pearDB->insert(
            $insertQuery,
            QueryParameters::create($stmtParams),
        );

        $contactId = (int) $pearDB->getLastInsertId();
        if ($contactId <= 0) {
            throw new RepositoryException('Failed to retrieve inserted contact ID');
        }
    } catch (
        ValueObjectException|CollectionException
        |ConnectionException|TransformerException $exception
    ) {
        throw new RepositoryException(
            'Database error while inserting contact',
            ['contact_name' => $ret['contact_name'] ?? null],
            $exception,
        );
    }

    if (! empty($ret['contact_passwd'])) {
        try {
            $hashed = password_hash(
                $ret['contact_passwd'],
                CentreonAuth::PASSWORD_HASH_ALGORITHM,
            );
            $ret['contact_passwd'] = $hashed;
            $ret['contact_passwd2'] = $hashed;

            $contact = new CentreonContact($pearDB);
            $contact->addPasswordByContactId($contactId, $hashed);
        } catch (Throwable $exception) {
            throw new RepositoryException(
                'Error while inserting contact password',
                ['contact_id' => $contactId],
                $exception,
            );
        }
    }

    /* Prepare value for changelog */
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog(
        'contact',
        $contactId,
        $ret['contact_name'],
        'a',
        $fields
    );

    return $contactId;
}

/**
 * @throws RepositoryException
 */
function updateContact(int $contactId): void
{
    global $form, $pearDB, $centreon;

    if (! $contactId > 0) {
        throw new RepositoryException(
            message: 'Invalid contact ID provided to update contact from contact page for contact id ' . $contactId,
            context: ['contact_id' => $contactId]
        );
    }

    $ret = $form->getSubmitValues();

    // Filter fields to only include whitelisted fields for non-admin users
    if (! $centreon->user->admin) {
        $ret = filterNonAdminFields($ret, $centreon->user->user_id == $contactId);
    }

    // Remove illegal chars in data sent by the user
    $ret['contact_name'] = CentreonUtils::escapeSecure($ret['contact_name'], CentreonUtils::ESCAPE_ILLEGAL_CHARS);
    $ret['contact_alias'] = CentreonUtils::escapeSecure($ret['contact_alias'], CentreonUtils::ESCAPE_ILLEGAL_CHARS);

    try {
        $bindParams = sanitizeFormContactParameters($ret);
    } catch (InvalidArgumentException $e) {
        throw new RepositoryException(
            message: 'Error while sanitizing contact parameters for update from contact page for contact id ' . $contactId,
            context: ['contact_id' => $contactId],
            previous: $e,
        );
    }

    try {
        // Build Query with only setted values.
        $rq = 'UPDATE contact SET ';
        foreach (array_keys($bindParams) as $token) {
            $rq .= ltrim($token, ':') . ' = ' . $token . ', ';
        }
        $rq = rtrim($rq, ', ');
        $rq .= ' WHERE contact_id = :contactId';

        $stmt = $pearDB->prepare($rq);
        foreach ($bindParams as $token => $bindValues) {
            foreach ($bindValues as $paramType => $value) {
                $stmt->bindValue($token, $value, $paramType);
            }
        }
        $stmt->bindValue(':contactId', $contactId, PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        throw new RepositoryException(
            message: 'Database error while updating contact from contact page for contact id ' . $contactId,
            context: ['contact_id' => $contactId],
            previous: $e,
        );
    }

    $userIdConnected = (int) $centreon->user->get_id();

    if (! $userIdConnected > 0) {
        throw new RepositoryException(
            message: 'Fetching connected user ID failed during contact update from contact page for contact id ' . $contactId,
            context: ['contact_id' => $contactId],
        );
    }

    if (isset($ret['contact_lang']) && $contactId === $userIdConnected) {
        $centreon->user->set_lang($ret['contact_lang']);
    }

    if (isset($ret['contact_passwd']) && $ret['contact_passwd'] !== '') {
        $ret['contact_passwd'] = password_hash($ret['contact_passwd'], CentreonAuth::PASSWORD_HASH_ALGORITHM);
        $ret['contact_passwd2'] = $ret['contact_passwd'];

        try {
            $contact = new \CentreonContact($pearDB);
            $contact->renewPasswordByContactId($contactId, $ret['contact_passwd']);

            LoggerPassword::create()->success(
                initiatorId: $userIdConnected,
                targetId: $contactId,
            );
        } catch (PDOException $e) {
            LoggerPassword::create()->warning(
                reason: 'password update failed',
                initiatorId: $userIdConnected,
                targetId: $contactId,
                exception: $e,
            );

            throw new RepositoryException(
                message: 'Unable to update password for contact id ' . $contactId,
                previous: $e
            );
        }
    }

    /* Prepare value for changelog */
    $fields = CentreonLogAction::prepareChanges($ret);

    try {
        $centreon->CentreonLogAction->insertLog('contact', $contactId, $ret['contact_name'], 'c', $fields);
    } catch (PDOException $e) {
        throw new RepositoryException(
            message: 'Database error while logging update of contact from contact page for contact id ' . $contactId,
            context: ['contact_id' => $contactId],
            previous: $e,
        );
    }
}

/**
 * @throws RepositoryException
 */
function updateContact_MC(int $contact_id): void
{
    global $form, $pearDB, $centreon;

    if (! $contact_id > 0) {
        throw new RepositoryException(
            message: 'Invalid contact ID provided to update contact by massive change for contact id ' . $contact_id,
            context: ['contact_id' => $contact_id]
        );
    }

    $ret = $form->getSubmitValues();

    // Remove all parameters that have an empty value in order to keep
    // the contact properties that have not been modified
    foreach ($ret as $name => $value) {
        if (is_string($value) && empty($value)) {
            unset($ret[$name]);
        }
    }
    // Filter fields to only include whitelisted fields for non-admin users
    if (! $centreon->user->admin) {
        $ret = filterNonAdminFields($ret, $centreon->user->user_id == $contact_id);
    }

    try {
        $bindParams = sanitizeFormContactParameters($ret);
    } catch (InvalidArgumentException $e) {
        throw new RepositoryException(
            message: 'Error while sanitizing contact parameters for massive change update for contact id ' . $contact_id,
            context: ['contact_id' => $contact_id],
            previous: $e,
        );
    }

    try {
        $query = 'UPDATE contact SET ';
        foreach (array_keys($bindParams) as $token) {
            $query .= ltrim($token, ':') . ' = ' . $token . ', ';
        }
        $query = rtrim($query, ', ');
        $query .= ' WHERE contact_id = :contactId';

        $stmt = $pearDB->prepare($query);
        foreach ($bindParams as $token => $bindValues) {
            foreach ($bindValues as $paramType => $value) {
                $stmt->bindValue($token, $value, $paramType);
            }
        }
        $stmt->bindValue(':contactId', $contact_id, PDO::PARAM_INT);
        $stmt->execute();

        // Prepare Log
        $query = "SELECT contact_name FROM `contact` WHERE contact_id='" . $contact_id . "' LIMIT 1";
        $dbResult2 = $pearDB->query($query);
        $row = $dbResult2->fetch();
    } catch (PDOException $e) {
        throw new RepositoryException(
            message: 'Database error while updating contact by massive change for contact id ' . $contact_id,
            context: ['contact_id' => $contact_id],
            previous: $e,
        );
    }

    /* Prepare value for changelog */
    $fields = CentreonLogAction::prepareChanges($ret);
    try {
        $centreon->CentreonLogAction->insertLog('contact', $contact_id, $row['contact_name'], 'mc', $fields);
    } catch (PDOException $e) {
        throw new RepositoryException(
            message: 'Database error while logging update of contact by massive change for contact id ' . $contact_id,
            context: ['contact_id' => $contact_id],
            previous: $e,
        );
    }
}

/**
 * @param int $contactId
 * @param array $fields
 * @return bool
 */
function updateContactHostCommands(int $contactId, array $fields = []): bool
{
    global $form, $pearDB;

    $kernel = Kernel::createForWeb();

    /** @var Logger $logger */
    $logger = $kernel->getContainer()->get(Logger::class);

    if ($contactId <= 0) {
        $logger->error(
            "contactId must be an integer greater than 0, given value for contactId : {$contactId}",
            ['file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__, 'contactId' => $contactId]
        );
        return false;
    }

    try {
        $pearDB->delete(
            'DELETE FROM contact_hostcommands_relation WHERE contact_contact_id = :contact_id',
            QueryParameters::create([QueryParameter::int('contact_id', $contactId)])
        );

        $hostCommandIdsFromForm = $fields["contact_hostNotifCmds"] ?? $form->getSubmitValue("contact_hostNotifCmds");

        if (!is_array($hostCommandIdsFromForm)) {
            return false;
        }

        $query = "INSERT INTO contact_hostcommands_relation(contact_contact_id, command_command_id) VALUES(:contact_id, :command_id)";
        foreach ($hostCommandIdsFromForm as $hostCommandIdFromForm) {
            $pearDB->insert(
                $query,
                QueryParameters::create([
                    QueryParameter::int('contact_id', $contactId),
                    QueryParameter::int('command_id', (int)$hostCommandIdFromForm)
                ])
            );
        }
        return true;
    } catch (\Throwable $e) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_BUSINESS_LOG,
            'Error while updating the relationship between contacts and host commands',
            ['contact_id' => $contactId, 'fields' => $fields],
            $e
        );
        return false;
    }
}

/**
 * @param int $contactId
 * @return bool
 */
function updateContactHostCommands_MC(int $contactId): bool
{
    global $form, $pearDB;

    if ($contactId <= 0) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_BUSINESS_LOG,
            "contactId must be an integer greater than 0, given value for contactId : {$contactId}",
            ['file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__, 'contactId' => $contactId],
        );
        return false;
    }

    $hostCommandIdsFromForm = $form->getSubmitValue("contact_hostNotifCmds");

    if (!is_array($hostCommandIdsFromForm)) {
        return false;
    }

    try {
        $existingIds = $pearDB->fetchFirstColumn(
            <<<'SQL'
                    SELECT command_command_id
                    FROM contact_hostcommands_relation
                    WHERE contact_contact_id = :contactId
                SQL,
            QueryParameters::create([
                QueryParameter::int('contactId', $contactId),
            ])
        );

        $insertQuery = <<<'SQL'
                INSERT INTO contact_hostcommands_relation
                    (contact_contact_id, command_command_id)
                VALUES (:contactId, :commandId)
            SQL;

        foreach ($hostCommandIdsFromForm as $commandId) {
            $commandId = (int) $commandId;
            if (! in_array($commandId, $existingIds, true)) {
                $pearDB->insert(
                    $insertQuery,
                    QueryParameters::create([
                        QueryParameter::int('contactId', $contactId),
                        QueryParameter::int('commandId', $commandId),
                    ])
                );
            }
        }
        return true;
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_BUSINESS_LOG,
            'Error while updating contact host commands by massive change',
            ['contact_id' => $contactId],
            $exception
        );
        return false;
    }
}

/**
 * @param int $contactId
 * @param array $fields
 * @return bool
 */
function updateContactServiceCommands(int $contactId, array $fields = []): bool
{
    global $form, $pearDB;

    $kernel = Kernel::createForWeb();

    /** @var Logger $logger */
    $logger = $kernel->getContainer()->get(Logger::class);

    if ($contactId <= 0) {
        $logger->error(
            "contactId must be an integer greater than 0, given value for contactId : {$contactId}",
            ['file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__, 'contactId' => $contactId]
        );
        return false;
    }

    try {
        $query = "DELETE FROM contact_servicecommands_relation WHERE contact_contact_id = :contact_id";
        $successDelete = $pearDB->executePreparedQuery($pearDB->prepareQuery($query), ['contact_id' => $contactId]);

        if ($successDelete === false) {
            return false;
        }

        $serviceCommandsFromForm = $fields["contact_svNotifCmds"] ?? $form->getSubmitValue("contact_svNotifCmds");

        if (!is_array($serviceCommandsFromForm)) {
            return false;
        }

        $query = "INSERT INTO contact_servicecommands_relation (contact_contact_id, command_command_id) VALUES (:contact_id, :command_id)";
        $pdoSth = $pearDB->prepareQuery($query);
        foreach ($serviceCommandsFromForm as $commandId) {
            $pearDB->executePreparedQuery(
                $pdoSth,
                ['contact_id' => $contactId, 'command_id' => (int)$commandId]
            );
        }
        return true;
    } catch (CentreonDbException $e) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_BUSINESS_LOG,
            'Error while updating the relationship between contacts and service commands',
            ['contact_id' => $contactId, 'fields' => $fields],
            $e
        );
        return false;
    }
}

/*
 * For massive change. We just add the new list if the elem doesn't exist yet
 */
/**
 * @param int $contactId
 * @return bool
 */
function updateContactServiceCommands_MC(int $contactId): bool
{
    global $form, $pearDB;

    if ($contactId <= 0) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_BUSINESS_LOG,
            "contactId must be an integer greater than 0, given value for contactId : {$contactId}",
            ['file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__, 'contactId' => $contactId],
        );
        return false;
    }

    $serviceCommandsFromForm = $form->getSubmitValue("contact_svNotifCmds");

    if (!is_array($serviceCommandsFromForm)) {
        return false;
    }

    try {
        $existingIds = $pearDB->fetchFirstColumn(
            <<<'SQL'
                    SELECT command_command_id
                    FROM contact_servicecommands_relation
                    WHERE contact_contact_id = :contactId
                SQL,
            QueryParameters::create([
                QueryParameter::int('contactId', $contactId),
            ])
        );

        $insertQuery = <<<'SQL'
                INSERT INTO contact_servicecommands_relation
                    (contact_contact_id, command_command_id)
                VALUES (:contactId, :commandId)
            SQL;

        foreach ($serviceCommandsFromForm as $commandId) {
            $commandId = (int) $commandId;
            if (! in_array($commandId, $existingIds, true)) {
                $pearDB->insert(
                    $insertQuery,
                    QueryParameters::create([
                        QueryParameter::int('contactId', $contactId),
                        QueryParameter::int('commandId', $commandId),
                    ])
                );
            }
        }
        return true;
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_BUSINESS_LOG,
            'Error while updating contact service commands by massive change',
            ['contact_id' => $contactId],
            $exception
        );
        return false;
    }
}

/**
 * @param int $contactId
 * @param array $fields
 * @return bool
 */
function updateContactContactGroup(int $contactId, array $fields = []): bool
{
    global $centreon, $form, $pearDB;

    $kernel = Kernel::createForWeb();

    /** @var Logger $logger */
    $logger = $kernel->getContainer()->get(Logger::class);

    if ($contactId <= 0) {
        $logger->error(
            "contactId must be an integer greater than 0, given value for contactId : {$contactId}",
            ['file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__, 'contactId' => $contactId]
        );
        return false;
    }

    try {
        $contactGroupIdsFromForm = $fields["contact_cgNotif"] ?? CentreonUtils::mergeWithInitialValues(
            $form,
            'contact_cgNotif'
        );
    } catch (InvalidArgumentException $e) {
        $logger->error(
            "Error while merging with initial values : [InvalidArgumentException] {$e->getMessage()}",
            ['file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__, 'contactId' => $contactId]
        );
        return false;
    }

    if (!is_array($contactGroupIdsFromForm)) {
        return false;
    }

    try {
        $query = "DELETE FROM contactgroup_contact_relation "
            . "WHERE contact_contact_id = :contact_id "
            . "AND ( "
            . "    contactgroup_cg_id IN (SELECT cg_id FROM contactgroup WHERE cg_type = 'local') "
            . "    OR contact_contact_id IN (SELECT contact_id FROM contact WHERE contact_auth_type = 'local') "
            . ") ";
        $successDelete = $pearDB->executePreparedQuery($pearDB->prepareQuery($query), ['contact_id' => $contactId]);

        if (!$successDelete) {
            return false;
        }

        $query = "INSERT INTO contactgroup_contact_relation (contact_contact_id, contactgroup_cg_id) VALUES (:contact_id, :contactgroup_id)";
        $pdoSth = $pearDB->prepareQuery($query);
        foreach ($contactGroupIdsFromForm as $contactGroupId) {
            $pearDB->executePreparedQuery(
                $pdoSth,
                ['contact_id' => $contactId, 'contactgroup_id' => (int)$contactGroupId]
            );
        }
    } catch (CentreonDbException $e) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_BUSINESS_LOG,
            'Error while updating the relationship between contacts and contact groups',
            ['contact_id' => $contactId, 'fields' => $fields],
            $e
        );
        return false;
    }

    try {
        CentreonCustomView::syncContactGroupCustomView($centreon, $pearDB, $contactId);
    } catch (Exception $e) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_BUSINESS_LOG,
            "CentreonCustomView::syncContactGroupCustomView failed with contact_id : {$contactId}",
            ['contact_id' => $contactId],
            $e
        );
        return false;
    }

    return true;
}

/*
 * For massive change. We just add the new list if the elem doesn't exist yet
 */
/**
 * @param int $contactId
 * @return bool
 */
function updateContactContactGroup_MC(int $contactId): bool
{
    global $centreon, $form, $pearDB;

    $kernel = Kernel::createForWeb();

    /** @var Logger $logger */
    $logger = $kernel->getContainer()->get(Logger::class);

    if ($contactId <= 0) {
        $logger->error(
            "contactId must be an integer greater than 0, given value for contactId : {$contactId}",
            ['file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__, 'contactId' => $contactId]
        );
        return false;
    }

    $contactGroupIdsFromForm = $form->getSubmitValue("contact_cgNotif");

    if (!is_array($contactGroupIdsFromForm)) {
        return false;
    }

    try {
        $query = "SELECT contactgroup_cg_id FROM contactgroup_contact_relation WHERE contact_contact_id = {$contactId}";
        $contactGroupIdsFromDb = $pearDB->executeQueryFetchColumn($query);

        $query = "INSERT INTO contactgroup_contact_relation (contact_contact_id, contactgroup_cg_id) VALUES (:contact_id, :contactgroup_id)";
        $pdoSth = $pearDB->prepareQuery($query);
        foreach ($contactGroupIdsFromForm as $contactGroupIdFromForm) {
            if (!in_array($contactGroupIdFromForm, $contactGroupIdsFromDb, false)) {
                $pearDB->executePreparedQuery(
                    $pdoSth,
                    ['contact_id' => $contactId, 'contactgroup_id' => (int)$contactGroupIdFromForm]
                );
            }
        }
    } catch (CentreonDbException $e) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_BUSINESS_LOG,
            'Error while updating the relationship between contacts and contact groups by massive change',
            ['contact_id' => $contactId],
            $e
        );
        return false;
    }

    try {
        CentreonCustomView::syncContactGroupCustomView($centreon, $pearDB, $contactId);
    } catch (Exception $e) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_BUSINESS_LOG,
            "CentreonCustomView::syncContactGroupCustomView failed with contact_id : {$contactId}",
            ['contact_id' => $contactId],
            $e
        );
        return false;
    }

    return true;
}

/**
 * @param array $tmpContacts
 * @throws RepositoryException
 * @return bool
 */
function insertLdapContactInDB($tmpContacts = [])
{
    global $nbr, $centreon, $pearDB;
    $tmpConf = [];
    $ldapInstances = [];
    $contactTemplates = [];
    foreach ($tmpContacts["select"] as $select_key => $select_value) {
        if ($tmpContacts['contact_name'][$select_key] == '-') {
            $tmpContacts['contact_name'][$select_key] = $tmpContacts["contact_alias"][$select_key];
        }
        $tmpContacts["contact_name"][$select_key] = str_replace(
            [" ", ","],
            ["_", "_"],
            $tmpContacts["contact_name"][$select_key]
        );
        $arId = $tmpContacts["ar_id"][$select_key];

        if (
            isset($tmpContacts['contact_name'][$select_key])
            && testContactExistence($tmpContacts['contact_name'][$select_key], true)
        ) {
            $tmpConf["contact_name"] = $tmpContacts["contact_name"][$select_key];
            $tmpConf["contact_alias"] = $tmpContacts["contact_alias"][$select_key];
            $tmpConf["contact_email"] = $tmpContacts["contact_email"][$select_key];
            $tmpConf["contact_pager"] = $tmpContacts["contact_pager"][$select_key];
            $tmpConf["contact_oreon"]["contact_oreon"] = "0";
            $tmpConf["contact_admin"]["contact_admin"] = "0";
            $tmpConf["contact_type_msg"] = "txt";
            $tmpConf["contact_lang"] = "en_US";
            $tmpConf["contact_auth_type"] = "ldap";
            $tmpConf["contact_ldap_dn"] = $tmpContacts["dn"][$select_key];
            $tmpConf["contact_activate"]["contact_activate"] = "1";
            $tmpConf["contact_comment"] = "Ldap Import - " . date("d/m/Y - H:i:s", time());
            $tmpConf["contact_location"] = "0";
            $tmpConf["contact_register"] = "1";
            $tmpConf["contact_enable_notifications"]["contact_enable_notifications"] = "2";
            insertContactInDB($tmpConf);
            unset($tmpConf);
        }
        /*
         * Get the contact_id
         */
        $query = "SELECT contact_id FROM contact WHERE contact_ldap_dn = '" .
            $pearDB->escape($tmpContacts["dn"][$select_key]) . "'";
        try {
            $res = $pearDB->query($query);
        } catch (\PDOException $e) {
            return false;
        }
        $row = $res->fetch();
        $contact_id = $row['contact_id'];

        if (!isset($ldapInstances[$arId])) {
            $ldap = new CentreonLDAP($pearDB, null, $arId);
            $ldapAdmin = new CentreonLdapAdmin($pearDB);
            $opt = $ldapAdmin->getGeneralOptions($arId);
            if (isset($opt['ldap_contact_tmpl']) && $opt['ldap_contact_tmpl']) {
                $contactTemplates[$arId] = $opt['ldap_contact_tmpl'];
            }
        } else {
            $ldap = $ldapInstances[$arId];
        }
        if ($contact_id) {
            $sqlUpdate = "UPDATE contact SET ar_id = " . $pearDB->escape($arId) .
                " %s  WHERE contact_id = " . (int)$contact_id;
            $tmplSql = "";
            if (isset($contactTemplates[$arId])) {
                $tmplSql = ", contact_template_id = " . $pearDB->escape($contactTemplates[$arId]);
            }
            $pearDB->query(sprintf($sqlUpdate, $tmplSql));
        }
        $listGroup = [];
        if (false !== $ldap->connect()) {
            $listGroup = $ldap->listGroupsForUser($tmpContacts["dn"][$select_key]);
        }
        if ($listGroup !== []) {
            $query = "SELECT cg_id FROM contactgroup WHERE cg_name IN ('" . join("','", $listGroup) . "')";
            try {
                $res = $pearDB->query($query);
            } catch (\PDOException $e) {
                return false;
            }

            // Insert the relation between contact and contactgroups
            $query = <<<SQL
                INSERT INTO contactgroup_contact_relation (contactgroup_cg_id, contact_contact_id)
                VALUES (:contactgroup_cg_id, :contact_contact_id)
                SQL;
            $statement = $pearDB->prepare($query);
            while ($row = $res->fetch()) {
                $statement->bindValue(':contactgroup_cg_id', (int) $row['cg_id'], \PDO::PARAM_INT);
                $statement->bindValue(':contact_contact_id', (int) $contact_id, \PDO::PARAM_INT);
                $statement->execute();
            }
        }

        //Insert a relation between LDAP's default contactgroup and the contact
        $ldap->addUserToLdapDefaultCg(
            $arId,
            $contact_id
        );
    }
    return true;
}

/**
 *
 * Update ACL groups links with this user
 * @param int $contactId
 * @param array $fields
 * @return bool
 */
function updateAccessGroupLinks(int $contactId, array $fields = []): bool
{
    global $form, $pearDB;

    $kernel = Kernel::createForWeb();

    /** @var Logger $logger */
    $logger = $kernel->getContainer()->get(Logger::class);

    if ($contactId <= 0) {
        $logger->error(
            "contactId must be an integer greater than 0, given value for contactId : {$contactId}",
            ['file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__, 'contactId' => $contactId]
        );
        return false;
    }

    try {
        $aclGroupIds = $fields['contact_acl_groups'] ?? CentreonUtils::mergeWithInitialValues(
            $form,
            'contact_acl_groups'
        );
    } catch (InvalidArgumentException $e) {
        $logger->error(
            "Error while merging with initial values : [InvalidArgumentException] {$e->getMessage()}",
            ['file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__, 'contactId' => $contactId]
        );
        return false;
    }

    if (!is_array($aclGroupIds)) {
        return false;
    }

    try {
        $query = "DELETE FROM acl_group_contacts_relations WHERE contact_contact_id = :contact_id";
        $successDelete = $pearDB->executePreparedQuery($pearDB->prepareQuery($query), ['contact_id' => $contactId]);

        if (!$successDelete) {
            return false;
        }

        $query = "INSERT INTO acl_group_contacts_relations (contact_contact_id, acl_group_id) VALUES (:contact_id, :acl_group_id)";
        $pdoSth = $pearDB->prepareQuery($query);
        foreach ($aclGroupIds as $aclGroupId) {
            $pearDB->executePreparedQuery($pdoSth, ['contact_id' => $contactId, 'acl_group_id' => (int)$aclGroupId]);
        }
    } catch (CentreonDbException $e) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_BUSINESS_LOG,
            'Error while updating the relationship between contacts and acl groups',
            ['contact_id' => $contactId, 'fields' => $fields],
            $e
        );
        return false;
    }

    return true;
}

/**
 *
 * Update ACL groups links with this user during massive changes
 * @param int $contactId
 * @param $flag
 * @return bool
 */
function updateAccessGroupLinks_MC(int $contactId, $flag): bool
{
    global $form, $pearDB;

    $kernel = Kernel::createForWeb();

    /** @var Logger $logger */
    $logger = $kernel->getContainer()->get(Logger::class);

    if ($contactId <= 0) {
        $logger->error(
            "contactId must be an integer greater than 0, given value for contactId : {$contactId}",
            ['file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__, 'contactId' => $contactId]
        );
        return false;
    }

    $aclGroupIds = $form->getSubmitValue("contact_acl_groups");

    if (!is_array($aclGroupIds)) {
        return false;
    }

    try {
        if ($flag) {
            $query = "DELETE FROM acl_group_contacts_relations WHERE contact_contact_id = :contact_id";
            $successDelete = $pearDB->executePreparedQuery(
                $pearDB->prepareQuery($query),
                ['contact_id' => $contactId]
            );
            if (!$successDelete) {
                return false;
            }
        }

        $query = "INSERT INTO acl_group_contacts_relations (contact_contact_id, acl_group_id) VALUES (:contact_id, :acl_group_id)";
        $pdoSth = $pearDB->prepareQuery($query);
        foreach ($aclGroupIds as $aclGroupId) {
            $pearDB->executePreparedQuery($pdoSth, ['contact_id' => $contactId, 'acl_group_id' => (int)$aclGroupId]);
        }

        return true;
    } catch (CentreonDbException $e) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_BUSINESS_LOG,
            'Error while updating the relationship between contacts and acl groups by massive change',
            ['contact_id' => $contactId, 'flag' => $flag],
            $e
        );
        return false;
    }
}

/**
 * Get contact ID by name
 *
 * @param string $name
 * @throws RepositoryException
 * @return int
 */
function getContactIdByName($name): int
{
    global $pearDB;

    $query = <<<'SQL'
        SELECT contact_id
        FROM contact
        WHERE contact_name = :contact_name
        LIMIT 1
        SQL;

    try {
        $row = $pearDB->fetchAssociative(
            $query,
            QueryParameters::create([
                QueryParameter::string('contact_name', $name),
            ])
        );
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        throw new RepositoryException(
            'Error while fetching contact id by name',
            ['contact_name' => $name],
            $exception
        );
    }

    return $row !== false ? (int) $row['contact_id'] : 0;
}


/**
 * Sanitize all the contact parameters from the contact form and return a ready to bind array.
 *
 * @param array $ret
 * @throws InvalidArgumentException
 * @return array
 */
function sanitizeFormContactParameters(array $ret): array
{
    global $encryptType, $dependencyInjector;
    $bindParams = [];
    $bindParams[':contact_host_notification_options'] = [\PDO::PARAM_STR => null];
    $bindParams[':contact_service_notification_options'] = [\PDO::PARAM_STR => null];

    // Local sanitizer
    $sanitize = static function (?string $value): string {
        return HtmlSanitizer::createFromString($value ?? '')
            ->removeTags()
            ->sanitize()
            ->getString();
    };

    foreach ($ret as $inputName => $inputValue) {
        switch ($inputName) {
            case 'timeperiod_tp_id':
            case 'timeperiod_tp_id2':
            case 'contact_template_id':
                $bindParams[':' . $inputName] = [
                    \PDO::PARAM_INT => (filter_var($inputValue, FILTER_VALIDATE_INT) === false)
                        ? null
                        : (int) $inputValue
                ];
                break;
            case 'contact_location':
                $bindParams[':' . $inputName] = [
                    \PDO::PARAM_INT => (filter_var($inputValue, FILTER_VALIDATE_INT) === false)
                        ? 0
                        : (int) $inputValue
                ];
                break;
            case 'contact_register':
                $bindParams[':' . $inputName] = [
                    \PDO::PARAM_INT => (filter_var($inputValue, FILTER_VALIDATE_INT) === false)
                        ? 1
                        : (int) $inputValue
                ];
                break;
            case 'contact_hostNotifOpts':
                $inputValue = $sanitize(implode(',', array_keys($inputValue)));
                if (! empty($inputValue)) {
                    $bindParams[':contact_host_notification_options'] = [\PDO::PARAM_STR => $inputValue];
                }
                break;
            case 'contact_svNotifOpts':
                $inputValue = $sanitize(implode(',', array_keys($inputValue)));
                if (! empty($inputValue)) {
                    $bindParams[':contact_service_notification_options'] = [\PDO::PARAM_STR => $inputValue];
                }
                break;
            case 'contact_oreon':
                // ldap import, then force contact to be a user
                if (isset($_POST['contact_select']['select'])) {
                    $bindParams[':' . $inputName] = [
                        \PDO::PARAM_STR => '1'
                    ];
                } else {
                    $bindParams[':' . $inputName] = [
                        \PDO::PARAM_STR => in_array($inputValue[$inputName], ['0', '1'])
                            ? $inputValue[$inputName]
                            : null
                    ];
                }
                break;
            case 'contact_activate':
                $bindParams[':' . $inputName] = [
                    \PDO::PARAM_STR => in_array($inputValue[$inputName], ['0', '1'])
                        ? $inputValue[$inputName]
                        : null
                ];
                break;
            case 'reach_api':
            case 'reach_api_rt':
                $bindParams[':' . $inputName] = [
                    \PDO::PARAM_INT => in_array($inputValue[$inputName], ['0', '1'])
                        ? (int) $inputValue[$inputName]
                        : 0
                ];
                break;
            case 'contact_enable_notifications':
                $bindParams[':' . $inputName] = [
                    \PDO::PARAM_STR => in_array($inputValue[$inputName], ['0', '1', '2'])
                        ? $inputValue[$inputName]
                        : '2'
                ];
                break;
            case 'contact_admin':
                $bindParams[':' . $inputName] = [
                    \PDO::PARAM_STR => in_array($inputValue[$inputName], ['0', '1'])
                        ? $inputValue[$inputName]
                        : '0'
                ];
                break;
            case 'contact_type_msg':
                $bindParams[':' . $inputName] = [
                    \PDO::PARAM_STR => in_array($inputValue, ['txt', 'html', 'pdf'])
                        ? $inputValue
                        : 'txt'
                ];
                break;
            case 'contact_lang':
                if (! empty($inputValue)) {
                    $inputValue = $sanitize((string) $inputValue);
                    $bindParams[':' . $inputName] = empty($inputValue) ? [PDO::PARAM_STR => 'browser'] : [PDO::PARAM_STR => $inputValue];
                }
                break;
            case 'default_page':
                $bindParams[':' . $inputName] = [
                    \PDO::PARAM_INT => (filter_var($inputValue, FILTER_VALIDATE_INT) === false)
                        ? null
                        : (int) $inputValue
                ];
                break;
            case 'contact_auth_type':
                if (! empty($inputValue)) {
                    $inputValue = $sanitize((string) $inputValue);
                    $bindParams[':' . $inputName] = empty($inputValue) ? [PDO::PARAM_STR => 'local'] : [PDO::PARAM_STR => $inputValue];
                }
                break;
            case 'contact_alias':
            case 'contact_name':
                $inputValue = $sanitize((string) $inputValue);
                if (! empty($inputValue)) {
                    $bindParams[':' . $inputName] = [PDO::PARAM_STR => $inputValue];
                } else {
                    throw new InvalidArgumentException('Bad Parameter');
                }
                break;
            case 'contact_autologin_key':
            case 'contact_email':
            case 'contact_pager':
            case 'contact_comment':
            case 'contact_ldap_dn':
            case 'contact_address1':
            case 'contact_address2':
            case 'contact_address3':
            case 'contact_address4':
            case 'contact_address5':
            case 'contact_address6':
                if (
                    ($inputValue = $sanitize((string) $inputValue)) !== ''
                ) {
                    $bindParams[':' . $inputName] = [\PDO::PARAM_STR => $inputValue];
                }
                break;
        }
    }
    return $bindParams;
}

/**
 * Validate password creation using defined security policy.
 *
 * @param array $fields
 *
 * @return array|true
 */
function validatePasswordCreation(array $fields): true|array
{
    global $pearDB;
    $errors = [];

    if (empty($fields['contact_passwd'])) {
        return true;
    }

    $password = $fields['contact_passwd'];

    try {
        $contact = new \CentreonContact($pearDB);
        $contact->respectPasswordPolicyOrFail($password, null);
    } catch (\Exception $e) {
        $errors['contact_passwd'] = $e->getMessage();
    }

    return $errors !== [] ? $errors : true;
}

/**
 * Validate password creation using defined security policy.
 *
 * @param array<string,mixed> $fields
 *
 * @throws InvalidArgumentException
 *
 * @return array<string,string>|true
 */
function validatePasswordModification(array $fields): array|true
{
    global $pearDB, $centreon;
    $newPassword = $fields['contact_passwd'];
    $confirmPassword = $fields['contact_passwd2'];
    $currentPassword = $fields['current_password'];

    $contactId = (int) $fields['contact_id'];
    if (! $contactId > 0) {
        throw new InvalidArgumentException('Invalid contact ID provided for password modification validation');
    }

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
            targetId: $contactId,
        );

        return ['contact_passwd2' => _('Please fill in all password fields')];
    }

    // If the user only provided his current password, he must provide a new password
    if (empty($newPassword) && ! empty($currentPassword)) {
        LoggerPassword::create()->warning(
            reason: 'new password not provided',
            initiatorId: $userIdConnected,
            targetId: $contactId,
        );

        return ['current_password' => _('Please fill in all password fields')];
    }

    // If the user wants to change his password, he must provide his current password
    if (! empty($newPassword) && empty($currentPassword)) {
        LoggerPassword::create()->warning(
            reason: 'current password not provided',
            initiatorId: $userIdConnected,
            targetId: $contactId,
        );

        return ['current_password' => _('Please fill in all password fields')];
    }

    // If the user provided a current password, we check if it matches the one in the database
    if (! empty($currentPassword) && password_verify($currentPassword, $centreon->user->passwd) === false) {
        LoggerPassword::create()->warning(
            reason: 'current password wrong',
            initiatorId: $userIdConnected,
            targetId: $contactId,
        );

        return ['current_password' => _('Authentication failed')];
    }

    try {
        $contact = new CentreonContact($pearDB);
        $contact->respectPasswordPolicyOrFail($newPassword, $contactId);

        return true;
    } catch (Exception $e) {
        LoggerPassword::create()->warning(
            reason: 'new password does not respect the password policy',
            initiatorId: $userIdConnected,
            targetId: $contactId,
            exception: $e,
        );

        return ['contact_passwd' => $e->getMessage()];
    }
}

/**
 * Validate autologin key is not equal to a password
 *
 * @param array<string,mixed> $fields
 *
 * @throws RepositoryException
 * @throws InvalidArgumentException
 *
 * @return array<string,string>|true
 */
function validateAutologin(array $fields): array|true
{
    global $pearDB, $centreon;
    $errors = [];

    // If adding a new contact, contact_id will not be set
    $contactId = (int) $fields['contact_id'] ?? 0;

    if (! empty($fields['contact_autologin_key']) && $contactId > 0) {

        $userIdConnected = (int) $centreon->user->get_id();

        if (! $userIdConnected > 0) {
            throw new InvalidArgumentException('Invalid connected user ID provided for autologin validation');
        }

        if (empty($fields['contact_passwd'])) {
            $query = <<<'SQL'
                SELECT * FROM `contact_password`
                WHERE contact_id = :contactId
                ORDER BY creation_date DESC
                LIMIT 1
                SQL;

            try {
                $contactPassword = $pearDB->fetchAssociative(
                    $query,
                    QueryParameters::create([QueryParameter::int('contactId', $contactId)])
                );
            } catch (ValueObjectException|CollectionException|ConnectionException $e) {
                throw new RepositoryException(
                    message: 'Unable to fetch contact password for contact id ' . $userIdConnected,
                    context: ['userIdConnected' => $userIdConnected],
                    previous: $e
                );
            }

            if (password_verify($fields['contact_autologin_key'], $contactPassword['password'])) {
                $errors['contact_autologin_key'] = _(
                    'Your autologin key must be different than your current password'
                );

                LoggerPassword::create()->warning(
                    reason: 'autologin key is the same as current password',
                    initiatorId: $userIdConnected,
                    targetId: $contactId,
                );
            }
        }
        if (
            !empty($fields['contact_passwd'])
            && $fields['contact_passwd'] === $fields['contact_autologin_key']
        ) {
            $errorMessage = 'Your password and autologin key should be different';
            $errors['contact_passwd'] = _($errorMessage);
            $errors['contact_autologin_key'] = _($errorMessage);

            LoggerPassword::create()->warning(
                reason: 'autologin key is the same as new password',
                initiatorId: $userIdConnected,
                targetId: $contactId,
            );
        }
    }

    return $errors !== [] ? $errors : true;
}

/**
 * Filter the fields in the $ret array to only include whitelisted fields for non-admin users.
 *
 * @param array $ret
 * @param bool $isSelfContact
 * @return array
 */
function filterNonAdminFields(array $ret, bool $isSelfContact = false): array
{
    $allowedFields = [
        'contact_alias', 'contact_name', 'contact_email', 'contact_pager',
        'contact_cgNotif', 'contact_enable_notifications', 'contact_hostNotifOpts',
        'timeperiod_tp_id', 'contact_hostNotifCmds', 'contact_svNotifOpts', 'contact_passwd2',
        'timeperiod_tp_id2', 'contact_svNotifCmds', 'contact_oreon', 'contact_passwd',
        'contact_lang', 'default_page', 'contact_location', 'contact_auth_type',
        'contact_acl_groups', 'contact_address1', 'contact_address2', 'contact_address3', 'contact_address4',
        'contact_address5', 'contact_address6', 'contact_comment', 'contact_register', 'contact_activate',
        'contact_id', 'initialValues', 'centreon_token', 'contact_template_id', 'contact_type_msg','contact_ldap_dn'
    ];

    if ($isSelfContact) {
        $allowedFields[] = 'contact_autologin_key';
    }

    foreach ($ret as $field => $value) {
        if (!in_array($field, $allowedFields, true)) {
            unset($ret[$field]);
        }
    }

    return $ret;
}
