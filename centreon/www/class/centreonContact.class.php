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

use Core\Security\ProviderConfiguration\Domain\Local\Model\SecurityPolicy;

/**
 * Class
 *
 * @class CentreonContact
 */
class CentreonContact
{
    /** @var CentreonDB */
    protected $db;

    /**
     * CentreonContact constructor
     *
     * @param CentreonDB $db
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get contact templates
     *
     * @param array $fields | columns to return
     * @param array $filters
     * @param array $order |i.e: array('contact_name', 'ASC')
     * @param array $limit |i.e: array($limit, $offset)
     *
     * @throws PDOException
     * @return array
     */
    public function getContactTemplates($fields = [], $filters = [], $order = [], $limit = [])
    {
        $fieldStr = '*';
        if (count($fields)) {
            $fieldStr = implode(', ', $fields);
        }
        $filterStr = " WHERE contact_register = '0' ";
        foreach ($filters as $k => $v) {
            $filterStr .= " AND {$k} LIKE '{$this->db->escape($v)}' ";
        }
        $orderStr = '';
        if (count($order) === 2) {
            $orderStr = " ORDER BY {$order[0]} {$order[1]} ";
        }
        $limitStr = '';
        if (count($limit) === 2) {
            $limitStr = " LIMIT {$limit[0]},{$limit[1]}";
        }
        $res = $this->db->query("SELECT SQL_CALC_FOUND_ROWS {$fieldStr} 
                                FROM contact 
                                {$filterStr}
                                {$orderStr}
                                {$limitStr}");
        $arr = [];
        while ($row = $res->fetchRow()) {
            $arr[] = $row;
        }

        return $arr;
    }

    /**
     * Get contactgroup from contact id
     *
     * @param CentreonDB $db
     * @param int $contactId
     *
     * @throws PDOException
     * @return array
     */
    public static function getContactGroupsFromContact($db, $contactId)
    {
        $sql = 'SELECT cg_id, cg_name
            FROM contactgroup_contact_relation r, contactgroup cg 
            WHERE cg.cg_id = r.contactgroup_cg_id
            AND r.contact_contact_id = ' . $db->escape($contactId);
        $stmt = $db->query($sql);

        $cgs = [];
        while ($row = $stmt->fetchRow()) {
            $cgs[$row['cg_id']] = $row['cg_name'];
        }

        return $cgs;
    }

    /**
     * @param int $field
     * @return array
     */
    public static function getDefaultValuesParameters($field)
    {
        $parameters = [];
        $parameters['currentObject']['table'] = 'contact';
        $parameters['currentObject']['id'] = 'contact_id';
        $parameters['currentObject']['name'] = 'contact_name';
        $parameters['currentObject']['comparator'] = 'contact_id';

        switch ($field) {
            case 'timeperiod_tp_id':
            case 'timeperiod_tp_id2':
                $parameters['type'] = 'simple';
                $parameters['externalObject']['table'] = 'timeperiod';
                $parameters['externalObject']['id'] = 'tp_id';
                $parameters['externalObject']['name'] = 'tp_name';
                $parameters['externalObject']['comparator'] = 'tp_id';
                break;
            case 'contact_hostNotifCmds':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['table'] = 'command';
                $parameters['externalObject']['id'] = 'command_id';
                $parameters['externalObject']['name'] = 'command_name';
                $parameters['externalObject']['comparator'] = 'command_id';
                $parameters['relationObject']['table'] = 'contact_hostcommands_relation';
                $parameters['relationObject']['field'] = 'command_command_id';
                $parameters['relationObject']['comparator'] = 'contact_contact_id';
                break;
            case 'contact_svNotifCmds':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['table'] = 'command';
                $parameters['externalObject']['id'] = 'command_id';
                $parameters['externalObject']['name'] = 'command_name';
                $parameters['externalObject']['comparator'] = 'command_id';
                $parameters['relationObject']['table'] = 'contact_servicecommands_relation';
                $parameters['relationObject']['field'] = 'command_command_id';
                $parameters['relationObject']['comparator'] = 'contact_contact_id';
                break;
            case 'contact_cgNotif':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['table'] = 'contactgroup';
                $parameters['externalObject']['id'] = 'cg_id';
                $parameters['externalObject']['name'] = 'cg_name';
                $parameters['externalObject']['comparator'] = 'cg_id';
                $parameters['relationObject']['table'] = 'contactgroup_contact_relation';
                $parameters['relationObject']['field'] = 'contactgroup_cg_id';
                $parameters['relationObject']['comparator'] = 'contact_contact_id';
                break;
            case 'contact_location':
                $parameters['type'] = 'simple';
                $parameters['externalObject']['table'] = 'timezone';
                $parameters['externalObject']['id'] = 'timezone_id';
                $parameters['externalObject']['name'] = 'timezone_name';
                $parameters['externalObject']['comparator'] = 'timezone_id';
                break;
            case 'contact_acl_groups':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['table'] = 'acl_groups';
                $parameters['externalObject']['id'] = 'acl_group_id';
                $parameters['externalObject']['name'] = 'acl_group_name';
                $parameters['externalObject']['comparator'] = 'acl_group_id';
                $parameters['relationObject']['table'] = 'acl_group_contacts_relations';
                $parameters['relationObject']['field'] = 'acl_group_id';
                $parameters['relationObject']['comparator'] = 'contact_contact_id';
                break;
        }

        return $parameters;
    }

    /**
     * @param array $values
     * @param array $options
     *
     * @throws PDOException
     * @return array
     */
    public function getObjectForSelect2($values = [], $options = [])
    {
        global $centreon;
        $items = [];

        // get list of authorized contacts
        if (! $centreon->user->access->admin) {
            $cAcl = $centreon->user->access->getContactAclConf(
                ['fields' => ['contact_id'], 'get_row' => 'contact_id', 'keys' => ['contact_id'], 'conditions' => ['contact_id' => ['IN', $values]]],
                false
            );
        }

        $listValues = '';
        $queryValues = [];
        if (! empty($values)) {
            foreach ($values as $k => $v) {
                $listValues .= ':contact' . $v . ',';
                $queryValues['contact' . $v] = (int) $v;
            }
            $listValues = rtrim($listValues, ',');
        } else {
            $listValues .= '""';
        }

        // get list of selected contacts
        $query = 'SELECT contact_id, contact_name FROM contact '
            . 'WHERE contact_id IN (' . $listValues . ') ORDER BY contact_name ';

        $stmt = $this->db->prepare($query);

        if ($queryValues !== []) {
            foreach ($queryValues as $key => $id) {
                $stmt->bindValue(':' . $key, $id, PDO::PARAM_INT);
            }
        }
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            // hide unauthorized contacts
            $hide = false;
            if (! $centreon->user->access->admin && ! in_array($row['contact_id'], $cAcl)) {
                $hide = true;
            }

            $items[] = ['id' => $row['contact_id'], 'text' => $row['contact_name'], 'hide' => $hide];
        }

        return $items;
    }

    /**
     * Find contact id from alias
     *
     * @param string $alias
     *
     * @throws PDOException
     * @return int|null
     */
    public function findContactIdByAlias(string $alias): ?int
    {
        $contactId = null;

        $statement = $this->db->prepare(
            'SELECT contact_id
            FROM contact
            WHERE contact_alias = :contactAlias'
        );
        $statement->bindValue(':contactAlias', $alias, PDO::PARAM_STR);
        $statement->execute();

        if ($row = $statement->fetch()) {
            $contactId = (int) $row['contact_id'];
        }

        return $contactId;
    }

    /**
     * Get password security policy
     *
     * @throws PDOException
     * @return array<string,mixed>
     */
    public function getPasswordSecurityPolicy(): array
    {
        $result = $this->db->query(
            "SELECT `custom_configuration` FROM `provider_configuration` WHERE `name` = 'local'"
        );
        $configuration = $result->fetch(PDO::FETCH_ASSOC);
        if ($configuration === false || empty($configuration['custom_configuration'])) {
            throw new Exception('Password security policy not found');
        }

        $customConfiguration = json_decode($configuration['custom_configuration'], true);

        if (! array_key_exists('password_security_policy', $customConfiguration)) {
            throw new Exception('Security Policy not found in custom configuration');
        }

        $securityPolicyData = $customConfiguration['password_security_policy'];

        $securityPolicyData['password_expiration'] = [
            'expiration_delay' => $securityPolicyData['password_expiration_delay'],
            'excluded_users' => $this->getPasswordExpirationExcludedUsers(),
        ];

        return $securityPolicyData;
    }

    /**
     * Get excluded users from password expiration policy
     *
     * @throws PDOException
     * @return string[]
     */
    private function getPasswordExpirationExcludedUsers(): array
    {
        $statement = $this->db->query(
            "SELECT c.`contact_alias`
            FROM `password_expiration_excluded_users` peeu
            INNER JOIN `provider_configuration` pc ON pc.`id` = peeu.`provider_configuration_id`
            AND pc.`name` = 'local'
            INNER JOIN `contact` c ON c.`contact_id` = peeu.`user_id`
            AND c.`contact_register` = 1"
        );

        $excludedUsers = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $excludedUsers[] = $row['contact_alias'];
        }

        return $excludedUsers;
    }

    /**
     * Check if a password respects configured policy
     *
     * @param string $password
     * @param int|null $contactId
     *
     * @throws PDOException
     * @return void
     */
    public function respectPasswordPolicyOrFail(string $password, ?int $contactId): void
    {
        $passwordSecurityPolicy = $this->getPasswordSecurityPolicy();

        $this->respectPasswordCharactersOrFail($passwordSecurityPolicy, $password);

        if ($contactId !== null) {
            $this->respectPasswordChangePolicyOrFail(
                $passwordSecurityPolicy,
                $password,
                $contactId,
            );
        }
    }

    /**
     * Check if a password respects configured policy about characters (length, special characters, ...)
     *
     * @param array<string,mixed> $passwordPolicy
     * @param string $password
     *
     * @throws Exception
     * @return void
     */
    private function respectPasswordCharactersOrFail(array $passwordPolicy, string $password): void
    {
        $doesRespectPassword = true;

        $errorMessage = sprintf(
            _('Your password must be %d characters long'),
            (int) $passwordPolicy['password_length']
        );
        if (strlen($password) < (int) $passwordPolicy['password_length']) {
            $doesRespectPassword = false;
        }

        $characterRules = [
            'has_uppercase_characters' => [
                'pattern' => '/[A-Z]/',
                'error_message' =>  _('uppercase characters'),
            ],
            'has_lowercase_characters' => [
                'pattern' => '/[a-z]/',
                'error_message' =>  _('lowercase characters'),
            ],
            'has_numbers' => [
                'pattern' => '/[0-9]/',
                'error_message' =>  _('numbers'),
            ],
            'has_special_characters' => [
                'pattern' => '/[' . SecurityPolicy::SPECIAL_CHARACTERS_LIST . ']/',
                'error_message' => sprintf(_("special characters among '%s'"), SecurityPolicy::SPECIAL_CHARACTERS_LIST),
            ],
        ];
        $characterPolicyErrorMessages = [];

        foreach ($characterRules as $characterRule => $characterRuleParameters) {
            if ((bool) $passwordPolicy[$characterRule] === true) {
                $characterPolicyErrorMessages[] = $characterRuleParameters['error_message'];
                if (! preg_match($characterRuleParameters['pattern'], $password)) {
                    $doesRespectPassword = false;
                }
            }
        }

        if ($doesRespectPassword === false) {
            if ($characterPolicyErrorMessages !== []) {
                $errorMessage .= ' ' . _('and must contain') . ' : '
                    . implode(', ', $characterPolicyErrorMessages) . '.';
            }

            throw new Exception($errorMessage);
        }
    }

    /**
     * Find last password creation date by contact id
     *
     * @param int $contactId
     *
     * @throws PDOException
     * @return DateTimeImmutable|null
     */
    public function findLastPasswordCreationDate(int $contactId): ?DateTimeImmutable
    {
        $creationDate = null;

        $statement = $this->db->prepare(
            'SELECT creation_date
            FROM contact_password
            WHERE contact_id = :contactId
            ORDER BY creation_date DESC LIMIT 1'
        );
        $statement->bindValue(':contactId', $contactId, PDO::PARAM_INT);
        $statement->execute();

        if ($row = $statement->fetch()) {
            $creationDate = (new DateTimeImmutable())->setTimestamp((int) $row['creation_date']);
        }

        return $creationDate;
    }

    /**
     * Check if a user password respects configured policy when updated (delay, reuse)
     *
     * @param array<string,mixed> $passwordPolicy
     * @param string $password
     * @param int $contactId
     *
     * @throws Exception
     * @return void
     */
    private function respectPasswordChangePolicyOrFail(array $passwordPolicy, string $password, int $contactId): void
    {
        $passwordCreationDate = $this->findLastPasswordCreationDate($contactId);

        if ($passwordCreationDate !== null) {
            $delayBeforeNewPassword = (int) $passwordPolicy['delay_before_new_password'];
            $isPasswordCanBeChanged = $passwordCreationDate->getTimestamp() + $delayBeforeNewPassword < time();
            if (! $isPasswordCanBeChanged) {
                throw new Exception(
                    _("You can't change your password because the delay before changing password is not over.")
                );
            }
        }

        if ((bool) $passwordPolicy['can_reuse_passwords'] === false) {
            $statement = $this->db->prepare(
                'SELECT id, password FROM `contact_password` WHERE `contact_id` = :contactId'
            );
            $statement->bindParam(':contactId', $contactId, PDO::PARAM_INT);
            $statement->execute();

            $passwordHistory = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($passwordHistory as $contactPassword) {
                if (password_verify($password, $contactPassword['password'])) {
                    throw new Exception(
                        _(
                            'Your password has already been used. '
                            . 'Please choose a different password from the previous three.'
                        )
                    );
                }
            }
        }
    }

    /**
     * Add new password to a contact
     *
     * @param int $contactId
     * @param string $hashedPassword
     *
     * @throws PDOException
     * @return void
     */
    public function addPasswordByContactId(int $contactId, string $hashedPassword): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO `contact_password` (`password`, `contact_id`, `creation_date`)
            VALUES (:password, :contactId, :creationDate)'
        );
        $statement->bindValue(':password', $hashedPassword, PDO::PARAM_STR);
        $statement->bindValue(':contactId', $contactId, PDO::PARAM_INT);
        $statement->bindValue(':creationDate', time(), PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * Replace stored password for a contact
     *
     * @param int $contactId
     * @param string $oldHashedPassword
     * @param string $newHashedPassword
     *
     * @throws PDOException
     * @return void
     */
    public function replacePasswordByContactId(
        int $contactId,
        string $oldHashedPassword,
        string $newHashedPassword
    ): void {
        $statement = $this->db->prepare(
            'UPDATE `contact_password`
            SET password = :newPassword
            WHERE contact_id = :contactId
            AND password = :oldPassword'
        );
        $statement->bindValue(':oldPassword', $oldHashedPassword, PDO::PARAM_STR);
        $statement->bindValue(':newPassword', $newHashedPassword, PDO::PARAM_STR);
        $statement->bindValue(':contactId', $contactId, PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * add new contact password and delete old passwords
     *
     * @param int $contactId
     * @param string $hashedPassword
     *
     * @throws PDOException
     * @return void
     */
    public function renewPasswordByContactId(int $contactId, string $hashedPassword): void
    {
        $this->addPasswordByContactId($contactId, $hashedPassword);

        $this->deleteOldPasswords($contactId);
    }

    /**
     * Delete old passwords to store only 3 last passwords
     *
     * @param int $contactId
     *
     * @throws PDOException
     * @return void
     */
    private function deleteOldPasswords(int $contactId): void
    {
        $statement = $this->db->prepare(
            'SELECT creation_date
            FROM `contact_password`
            WHERE `contact_id` = :contactId
            ORDER BY `creation_date` DESC'
        );
        $statement->bindValue(':contactId', $contactId, PDO::PARAM_INT);
        $statement->execute();

        // If 3 or more passwords are saved, delete the oldest ones.
        if (($result = $statement->fetchAll()) && count($result) > 3) {
            $maxCreationDateToDelete = $result[3]['creation_date'];
            $statement = $this->db->prepare(
                'DELETE FROM `contact_password`
                WHERE contact_id = :contactId
                AND creation_date <= :creationDate'
            );
            $statement->bindValue(':contactId', $contactId, PDO::PARAM_INT);
            $statement->bindValue(':creationDate', $maxCreationDateToDelete, PDO::PARAM_INT);
            $statement->execute();
        }
    }
}
