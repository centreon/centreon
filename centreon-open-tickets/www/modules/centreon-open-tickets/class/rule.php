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
use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;

require_once _CENTREON_PATH_ . '/www/include/common/sqlCommonFunction.php';

class Centreon_OpenTickets_Rule
{
    /** @var CentreonDB */
    protected $_db;

    protected $_provider = null;

    /**
     * Constructor
     *
     * @param CentreonDB $db
     * @return void
     */
    public function __construct($db)
    {
        $this->_db = $db;
    }

    public function getAliasAndProviderId($rule_id)
    {
        $result = [];
        if (is_null($rule_id)) {
            return $result;
        }

        $dbResult = $this->_db->query(
            "SELECT alias, provider_id FROM mod_open_tickets_rule WHERE rule_id = '" . $rule_id . "' LIMIT 1"
        );
        if (($row = $dbResult->fetch())) {
            $result['alias'] = $row['alias'];
            $result['provider_id'] = $row['provider_id'];
        }

        return $result;
    }

    public function getUrl($rule_id, $ticket_id, $data, $widget_id)
    {
        $infos = $this->getAliasAndProviderId($rule_id);
        $this->loadProvider($rule_id, $infos['provider_id'], $widget_id);

        return $this->_provider->getUrl($ticket_id, $data);
    }

    public function getMacroNames($rule_id, $widget_id)
    {
        $result = ['ticket_id' => null];

        if (! $rule_id) {
            return $result;
        }

        $infos = $this->getAliasAndProviderId($rule_id);

        if ($infos) {
            $this->loadProvider($rule_id, $infos['provider_id'], $widget_id);
            $result['ticket_id'] = $this->_provider->getMacroTicketId();
        }

        return $result;
    }

    /**
     * @param CentreonDB|null $dbStorage
     * @param string $cmd
     * @param string $selection
     *
     * @return array<string, array>
     */
    public function loadSelection(?CentreonDB $dbStorage, string $cmd, string $selection): array
    {
        global $centreon_bg;

        if (is_null($dbStorage)) {
            $dbStorage = new CentreonDB('centstorage');
        }

        $selected = ['host_selected' => [], 'service_selected' => []];

        if (empty($selection)) {
            return $selected;
        }

        $selectedValues = explode(',', $selection);

        // cmd 3 = open ticket on services
        if ($cmd == 3) {
            $selectedStr = '';
            $selectedStr2 = '';
            $selectedStrAppend = '';
            $queryParams = [];
            $graphQueryParams = [];

            foreach ($selectedValues as $key => $value) {
                [$hostId, $serviceId] = explode(';', $value);
                $selectedStr
                    .= $selectedStrAppend
                    . 'services.host_id = :host_id_' . $key
                    . ' AND services.service_id = :service_id_' . $key;
                $selectedStr2 .= $selectedStrAppend
                    . 'host_id = :host_id_' . $key
                    . ' AND service_id = :service_id_' . $key;
                $queryParams['host_id_' . $key] = $hostId;
                $queryParams['service_id_' . $key] = $serviceId;
                $graphQueryParams['host_id_' . $key] = $hostId;
                $graphQueryParams['service_id_' . $key] = $serviceId;
                $selectedStrAppend = ' OR ';
            }

            $query = <<<SQL
                    SELECT
                        services.*,
                        hosts.address,
                        hosts.state AS host_state,
                        hosts.host_id,
                        hosts.name AS host_name,
                        hosts.instance_id
                    FROM services
                    INNER JOIN hosts
                        ON services.host_id = hosts.host_id
                    WHERE ({$selectedStr})
                SQL;

            if (! $centreon_bg->is_admin) {
                $aclGroupIdsCondition = '';
                foreach (explode(',', str_replace("'", '', $centreon_bg->grouplistStr)) as $aclId) {
                    if (empty($aclGroupIdsCondition)) {
                        $aclGroupIdsCondition .= ':acl_' . $aclId;
                    } else {
                        $aclGroupIdsCondition .= ', :acl_' . $aclId;
                    }
                    $queryParams[':acl_' . $aclId] = (int) $aclId;
                }

                $query .= <<<SQL
                        AND EXISTS (
                            SELECT * FROM centreon_acl
                            WHERE centreon_acl.group_id IN ({$aclGroupIdsCondition})
                            AND hosts.host_id = centreon_acl.host_id
                            AND services.service_id = centreon_acl.service_id
                        )
                    SQL;
            }

            $graphQuery = <<<SQL
                SELECT
                    host_id,
                    service_id,
                    COUNT(*) AS num_metrics
                FROM index_data
                INNER JOIN metrics
                    ON index_data.id = metrics.index_id
                WHERE ({$selectedStr2})
                GROUP BY host_id, service_id
                SQL;

            try {
                $hostServiceStatement = $dbStorage->prepareQuery($query);
                $dbStorage->executePreparedQuery($hostServiceStatement, $queryParams);

                $graphStatement = $dbStorage->prepareQuery($graphQuery);
                $dbStorage->executePreparedQuery($graphStatement, $graphQueryParams);

                $graphData = [];
                while (($row = $dbStorage->fetch($graphStatement))) {
                    $graphData[$row['host_id'] . '.' . $row['service_id']] = $row['num_metrics'];
                }

                while (($row = $dbStorage->fetch($hostServiceStatement))) {
                    $row['service_state'] = $row['state'];
                    $row['state_str'] = $this->getServiceStateStr($row['state']);
                    $row['last_state_change_duration'] = CentreonDuration::toString(
                        time() - $row['last_state_change']
                    );
                    $row['last_hard_state_change_duration'] = CentreonDuration::toString(
                        time() - $row['last_hard_state_change']
                    );
                    $row['num_metrics'] = $graphData[$row['host_id'] . '.' . $row['service_id']] ?? 0;
                    $selected['service_selected'][] = $row;
                }
            } catch (CentreonDbException $e) {
                CentreonLog::create()->error(
                    CentreonLog::TYPE_SQL,
                    'rule:loadSelection Error while retrieving hosts and services',
                    ['selection' => $selection],
                    $e
                );

                return $selected;
            }
            // cmd 4 = open a ticket on hosts
        } elseif ($cmd == 4) {
            $hostsSelectedStr = '';
            $hostsSelectedStrAppend = '';
            $queryParams = [];
            foreach ($selectedValues as $key => $value) {
                [$hostId] = explode(';', $value);
                $hostsSelectedStr .= $hostsSelectedStrAppend . ':host_id_' . $key;
                $queryParams['host_id_' . $key] = $hostId;
                $hostsSelectedStrAppend = ', ';
            }

            $query = <<<SQL
                SELECT *
                FROM hosts
                WHERE host_id IN ({$hostsSelectedStr})
                SQL;

            if (! $centreon_bg->is_admin) {
                $aclGroupIdsCondition = '';
                foreach (explode(',', str_replace("'", '', $centreon_bg->grouplistStr)) as $aclId) {
                    if (empty($aclGroupIdsCondition)) {
                        $aclGroupIdsCondition .= ':acl_' . $aclId;
                    } else {
                        $aclGroupIdsCondition .= ', :acl_' . $aclId;
                    }
                    $queryParams[':acl_' . $aclId] = (int) $aclId;
                }

                $query .= <<<SQL
                        AND EXISTS (
                            SELECT * FROM centreon_acl
                            WHERE centreon_acl.group_id IN ({$aclGroupIdsCondition})
                            AND hosts.host_id = centreon_acl.host_id
                        )
                    SQL;
            }

            try {
                $hostStatement = $dbStorage->prepareQuery($query);
                $dbStorage->executePreparedQuery($hostStatement, $queryParams);

                while (($row = $dbStorage->fetch($hostStatement))) {
                    $row['host_state'] = $row['state'];
                    $row['state_str'] = $this->getHostStateStr($row['state']);
                    $row['last_state_change_duration'] = CentreonDuration::toString(
                        time() - $row['last_state_change']
                    );
                    $row['last_hard_state_change_duration'] = CentreonDuration::toString(
                        time() - $row['last_hard_state_change']
                    );
                    $selected['host_selected'][] = $row;
                }
            } catch (CentreonDbException $e) {
                CentreonLog::create()->error(
                    CentreonLog::TYPE_SQL,
                    'rule:loadSelection Error while retrieving hosts and services',
                    ['selection' => $selection],
                    $e
                );

                return $selected;
            }
        }

        return $selected;
    }

    public function getFormatPopupProvider($rule_id, $args, $widget_id, $uniq_id, $cmd, $selection)
    {
        $infos = $this->getAliasAndProviderId($rule_id);
        $this->loadProvider($rule_id, $infos['provider_id'], $widget_id, $uniq_id);

        $selected = $this->loadSelection(null, (string) $cmd, (string) $selection);
        $args['host_selected'] = $selected['host_selected'];
        $args['service_selected'] = $selected['service_selected'];

        return $this->_provider->getFormatPopup($args);
    }

    public function save($rule_id, $datas): void
    {
        $isTransactionActive = $this->_db->isTransactionActive();

        $ruleId = (int) $rule_id;

        try {
            if (! $isTransactionActive) {
                $this->_db->startTransaction();
            }

            $ruleExists = (bool) $this->_db->fetchOne(
                query: <<<'SQL'
                        SELECT 1 FROM mod_open_tickets_rule WHERE rule_id = :ruleId
                    SQL,
                queryParameters: QueryParameters::create([QueryParameter::int('ruleId', $ruleId)])
            );

            // Rule does not exist
            if (! $ruleExists) {
                $this->_db->insert(
                    query: <<<'SQL'
                            INSERT INTO mod_open_tickets_rule (`alias`, `provider_id`, `provider_name`, `activate`)
                            VALUES (:ruleAlias, :providerId, :providerName, '1')
                        SQL,
                    queryParameters: QueryParameters::create([
                        QueryParameter::string('ruleAlias', $datas['rule_alias']),
                        QueryParameter::int('providerId', $datas['provider_id']),
                        QueryParameter::string('providerName', $datas['provider_name']),
                    ])
                );

                $ruleId = $this->_db->lastInsertId();
            } else {
                $this->_db->update(
                    query: <<<'SQL'
                            UPDATE mod_open_tickets_rule
                            SET
                                `alias` = :ruleAlias,
                                `provider_id` = :providerId,
                                `provider_name` = :providerName
                            WHERE
                                rule_id = :ruleId
                        SQL,
                    queryParameters: QueryParameters::create([
                        QueryParameter::string('ruleAlias', $datas['rule_alias']),
                        QueryParameter::int('providerId', $datas['provider_id']),
                        QueryParameter::string('providerName', $datas['provider_name']),
                        QueryParameter::int('ruleId', $ruleId),
                    ])
                );

                $this->_db->delete(
                    query: <<<'SQL'
                            DELETE FROM mod_open_tickets_form_clone WHERE rule_id = :ruleId
                        SQL,
                    queryParameters: QueryParameters::create([QueryParameter::int('ruleId', $ruleId)])
                );

                $this->_db->delete(
                    query: <<<'SQL'
                            DELETE FROM mod_open_tickets_form_value WHERE rule_id = :ruleId
                        SQL,
                    queryParameters: QueryParameters::create([QueryParameter::int('ruleId', $ruleId)])
                );
            }

            foreach ($datas['simple'] as $uniq_id => $value) {
                $this->_db->insert(
                    query: <<<'SQL'
                            INSERT INTO mod_open_tickets_form_value (`uniq_id`, `value`, `rule_id`) VALUES (:uniqId, :value, :ruleId)
                        SQL,
                    queryParameters: QueryParameters::create([
                        QueryParameter::string('uniqId', $uniq_id),
                        QueryParameter::string('value', $value),
                        QueryParameter::int('ruleId', $ruleId),
                    ])
                );
            }

            foreach ($datas['clones'] as $uniq_id => $orders) {
                foreach ($orders as $order => $values) {
                    foreach ($values as $key => $value) {
                        $this->_db->insert(
                            query: <<<'SQL'
                                INSERT INTO mod_open_tickets_form_clone (`uniq_id`, `label`, `value`, `rule_id`, `order`)
                                VALUES (:uniqId, :label, :value, :ruleId, :order)
                                SQL,
                            queryParameters: QueryParameters::create([
                                QueryParameter::string('uniqId', $uniq_id),
                                QueryParameter::string('label', $key),
                                QueryParameter::string('value', $value),
                                QueryParameter::int('ruleId', $ruleId),
                                QueryParameter::int('order', $order),
                            ])
                        );
                    }
                }
            }

            if (! $isTransactionActive) {
                $this->_db->commitTransaction();
            }
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'An error occured while saving - updating open ticket rule',
                [
                    'rule_id' => $ruleId,
                ]
            );

            if (! $isTransactionActive) {
                try {
                    $this->_db->rollBackTransaction();
                } catch (ConnectionException $rollbackException) {
                    CentreonLog::create()->error(
                        CentreonLog::TYPE_SQL,
                        "Rollback failed for open ticket rule save - update: {$rollbackException->getMessage()}",
                        [
                            'rule_id' => $ruleId,
                        ]
                    );

                    throw new RepositoryException(
                        "Rollback failed for open ticket rule save - update: {$rollbackException->getMessage()}",
                        ['rule_id' => $ruleId],
                        $rollbackException
                    );
                }
            }

            throw new RepositoryException(
                "Open Ticket rule save - update failed : {$exception->getMessage()}",
                ['rule_id' => $ruleId],
                $exception
            );
        }
    }

    /**
     * @return array<int, string>
     */
    public function getRuleList()
    {
        try {
            return $this->_db->fetchAllAssociativeIndexed(
                query: <<<'SQL'
                        SELECT rule_id, alias, activate FROM mod_open_tickets_rule ORDER BY alias
                    SQL
            );

            /**
             * @var array<int, string> $rules
             */
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                "An error occured while saving - updating open ticket rule: {$exception->getMessage()}",
            );

            throw new RepositoryException(
                message: "An error occured while saving - updating open ticket rule: {$exception->getMessage()}",
                previous: $exception->getPrevious()
            );
        }
    }

    public function get($ruleId)
    {
        $rule = [];

        if (is_null($ruleId)) {
            return $rule;
        }

        try {
            $queryParameters = QueryParameters::create([QueryParameter::int('ruleId', $ruleId)]);

            $rule = $this->_db->fetchAssociative(
                query: <<<'SQL'
                        SELECT alias, provider_id FROM mod_open_tickets_rule WHERE rule_id = :ruleId
                    SQL,
                queryParameters: $queryParameters
            );

            if (! $rule) {
                CentreonLog::create()->error(
                    logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                    message: 'Could not get the rule as it does not exist',
                    customContext: ['rule_id' => $ruleId]
                );

                throw new RepositoryException('Could not get the rule as it does not exist');
            }

            $rule['clones'] = [];

            $clonesQuery = <<<'SQL'
                    SELECT * FROM mod_open_tickets_form_clone WHERE rule_id = :ruleId ORDER BY uniq_id, `order` ASC
                SQL;

            foreach ($this->_db->iterateAssociative(query: $clonesQuery, queryParameters: $queryParameters) as $record) {
                if (! isset($rule['clones'][$record['uniq_id']])) {
                    $rule['clones'][$record['uniq_id']] = [];
                }

                if (! isset($rule['clones'][$record['uniq_id']][$record['order']])) {
                    $rule['clones'][$record['uniq_id']][$record['order']] = [];
                }
                $rule['clones'][$record['uniq_id']][$record['order']][$record['label']] = $record['value'];
            }

            $formValueQuery = <<<'SQL'
                    SELECT * FROM mod_open_tickets_form_value WHERE rule_id = :ruleId
                SQL;

            foreach ($this->_db->iterateAssociative(query: $formValueQuery, queryParameters: $queryParameters) as $record) {
                $rule[$record['uniq_id']] = $record['value'];
            }

            return $rule;
        } catch (ValueObjectException|CollectionException|ConnectionException|RepositoryException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                "An error occured while retrieving open ticket rule: {$exception->getMessage()}",
                ['rule_id' => $ruleId]
            );

            throw new RepositoryException(
                message: "An error occured while retrieving open ticket rule: {$exception->getMessage()}",
                previous: $exception->getPrevious()
            );
        }
    }

    /**
     * Enable rules
     *
     * @param array $selectedRules
     */
    public function enable($selectedRules): void
    {
        $this->_setActivate($selectedRules, 1);
    }

    /**
     * Disable rules
     *
     * @param array $selectedRules
     */
    public function disable($selectedRules): void
    {
        $this->_setActivate($selectedRules, 0);
    }

    /**
     * Duplicate rules
     *
     * @param array $select
     * @param array $duplicateNb
     * @return void
     */
    public function duplicate($select = [], $duplicateNb = []): void
    {
        // Do not attempt to do something if nothing has been selected.
        if ($select === []) {
            return;
        }

        $isTransactionActive = $this->_db->isTransactionActive();

        $ruleIds = array_keys($select);

        try {
            if (! $isTransactionActive) {
                $this->_db->startTransaction();
            }

            foreach ($ruleIds as $ruleId) {
                $rule = $this->_db->fetchAssociative(
                    query: <<<'SQL'
                            SELECT
                                rule_id,
                                alias,
                                provider_id,
                                provider_name,
                                activate
                            FROM mod_open_tickets_rule
                            WHERE rule_id = :ruleId
                        SQL,
                    queryParameters: QueryParameters::create([QueryParameter::int('ruleId', $ruleId)])
                );

                if (! $rule) {
                    CentreonLog::create()->error(
                        logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                        message: 'Could not duplicate rule as it does not exist',
                        customContext: ['rule_id' => $ruleId]
                    );

                    throw new RepositoryException(
                        sprintf('Could not duplicate rule identified by ID %s as it does not exist', $ruleId),
                    );
                }

                $duplicationIndex = 1;
                if (isset($duplicateNb[$ruleId]) && $duplicateNb[$ruleId] > 0) {
                    for ($duplicationNumber = 1; $duplicationNumber <= $duplicateNb[$ruleId]; $duplicationNumber++) {
                        $newName = sprintf('%s_%d', $rule['alias'], $duplicationNumber);

                        // Check that alias is not already in use
                        if ($this->isAliasAlreadyUsed($newName)) {
                            $duplicationIndex++;
                            continue;
                        }

                        // insert duplicated rule in database
                        $this->_db->insert(
                            query: <<<'SQL'
                                    INSERT INTO mod_open_tickets_rule (`alias`, `provider_id`, `provider_name`, `activate`)
                                    VALUES (:ruleAlias, :providerId, :providerName, :activated)
                                SQL,
                            queryParameters: QueryParameters::create([
                                QueryParameter::string('ruleAlias', $newName),
                                QueryParameter::int('providerId', $rule['provider_id']),
                                QueryParameter::string('providerName', $rule['provider_name']),
                                QueryParameter::string('activated', $rule['activate']),
                            ])
                        );

                        $duplicatedRuleId = $this->_db->lastInsertId('mod_open_tickets_rule');

                        // Get form values from initial rule
                        $ruleFormValues = $this->_db->fetchAllAssociative(
                            query: <<<'SQL'
                                    SELECT * FROM mod_open_tickets_form_value WHERE rule_id = :ruleId
                                SQL,
                            queryParameters: QueryParameters::create([QueryParameter::int('ruleId', $ruleId)])
                        );

                        foreach ($ruleFormValues as $ruleFormValue) {
                            $this->_db->insert(
                                query: <<<'SQL'
                                        INSERT INTO mod_open_tickets_form_value (`uniq_id`, `value`, `rule_id`) VALUES (:uniqId, :value, :ruleId)
                                    SQL,
                                queryParameters: QueryParameters::create([
                                    QueryParameter::string('uniqId', $ruleFormValue['uniq_id']),
                                    QueryParameter::string('value', $ruleFormValue['value']),
                                    QueryParameter::int('ruleId', $duplicatedRuleId),
                                ])
                            );
                        }

                        $ruleCloneValues = $this->_db->fetchAllAssociative(
                            query: <<<'SQL'
                                    SELECT * FROM mod_open_tickets_form_clone WHERE rule_id = :ruleId
                                SQL,
                            queryParameters: QueryParameters::create([QueryParameter::int('ruleId', $ruleId)])
                        );

                        foreach ($ruleCloneValues as $ruleCloneValue) {
                            $this->_db->insert(
                                query: <<<'SQL'
                                    INSERT INTO mod_open_tickets_form_clone (`uniq_id`, `label`, `value`, `rule_id`, `order`)
                                    VALUES (:uniqId, :label, :value, :ruleId, :order)
                                    SQL,
                                queryParameters: QueryParameters::create([
                                    QueryParameter::string('uniqId', $ruleCloneValue['uniq_id']),
                                    QueryParameter::string('label', $ruleCloneValue['label']),
                                    QueryParameter::string('value', $ruleCloneValue['value']),
                                    QueryParameter::int('ruleId', $duplicatedRuleId),
                                    QueryParameter::int('order', $ruleCloneValue['order']),
                                ])
                            );
                        }
                    }
                }
            }

            if (! $isTransactionActive) {
                $this->_db->commitTransaction();
            }
        } catch (ValueObjectException|CollectionException|ConnectionException|RepositoryException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_BUSINESS_LOG,
                "An error occured while duplicating open ticket rule(s): {$exception->getMessage()}",
                ['rule_ids' => $ruleIds]
            );

            if (! $isTransactionActive) {
                try {
                    $this->_db->rollBackTransaction();
                } catch (ConnectionException $rollbackException) {
                    CentreonLog::create()->error(
                        CentreonLog::TYPE_SQL,
                        "Rollback failed for open ticket rule duplication: {$rollbackException->getMessage()}",
                        ['rule_ids' => $ruleIds]
                    );

                    throw new RepositoryException(
                        "Rollback failed for open ticket rule duplication: {$rollbackException->getMessage()}",
                        ['rule_ids' => $ruleIds],
                        $rollbackException
                    );
                }
            }

            throw new RepositoryException(
                "Open Ticket rule duplication failed : {$exception->getMessage()}",
                ['rule_ids' => $ruleIds],
                $exception
            );
        }
    }

    /**
     * Delete rules
     *
     * @param array $select
     */
    public function delete($select): void
    {
        $selectedRules = array_keys($select);

        if ($selectedRules === []) {
            return;
        }

        try {
            ['parameters' => $queryParameters, 'placeholderList' => $bindQuery] = createMultipleBindParameters(
                values: $selectedRules,
                prefix: 'ruleId',
                paramType: QueryParameterTypeEnum::INTEGER,
            );

            $this->_db->delete(
                query: <<<SQL
                        DELETE FROM mod_open_tickets_rule WHERE rule_id IN ({$bindQuery})
                    SQL,
                queryParameters: QueryParameters::create($queryParameters)
            );
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_BUSINESS_LOG,
                "An error occured while deleting open ticket rule(s): {$exception->getMessage()}",
                ['rule_ids' => $selectedRules]
            );

            throw new RepositoryException(
                "An error occured while deleting open ticket rule(s): {$exception->getMessage()}",
                ['rule_ids' => $selectedRules],
                $exception
            );
        }
    }

    public function getHostgroup($filter)
    {
        $result = [];
        $where = '';
        if (! is_null($filter) && $filter != '') {
            $where = " hg_name LIKE '" . $this->_db->escape($filter) . "' AND ";
        }
        $dbResult = $this->_db->query(
            'SELECT hg_id, hg_name FROM hostgroup WHERE ' . $where . " hg_activate = '1' ORDER BY hg_name ASC"
        );
        while (($row = $dbResult->fetch())) {
            $result[$row['hg_id']] = $row['hg_name'];
        }

        return $result;
    }

    public function getContactgroup($filter)
    {
        $result = [];
        $where = '';
        if (! is_null($filter) && $filter != '') {
            $where = " cg_name LIKE '" . $this->_db->escape($filter) . "' AND ";
        }
        $dbResult = $this->_db->query(
            'SELECT cg_id, cg_name FROM contactgroup WHERE ' . $where . " cg_activate = '1' ORDER BY cg_name ASC"
        );
        while (($row = $dbResult->fetch())) {
            $result[$row['cg_id']] = $row['cg_name'];
        }

        return $result;
    }

    public function getServicegroup($filter)
    {
        $result = [];
        $where = '';
        if (! is_null($filter) && $filter != '') {
            $where = " sg_name LIKE '" . $this->_db->escape($filter) . "' AND ";
        }
        $dbResult = $this->_db->query(
            'SELECT sg_id, sg_name FROM servicegroup WHERE ' . $where . " sg_activate = '1' ORDER BY sg_name ASC"
        );
        while (($row = $dbResult->fetch())) {
            $result[$row['sg_id']] = $row['sg_name'];
        }

        return $result;
    }

    public function getHostcategory($filter)
    {
        $result = [];
        $where = '';
        if (! is_null($filter) && $filter != '') {
            $where = " hc_name LIKE '" . $this->_db->escape($filter) . "' AND ";
        }
        $dbResult = $this->_db->query(
            'SELECT hc_id, hc_name
            FROM hostcategories
            WHERE ' . $where . " hc_activate = '1'
            ORDER BY hc_name ASC"
        );
        while (($row = $dbResult->fetch())) {
            $result[$row['hc_id']] = $row['hc_name'];
        }

        return $result;
    }

    public function getHostseverity($filter)
    {
        $result = [];
        $where = '';
        if (! is_null($filter) && $filter != '') {
            $where = " hc_name LIKE '" . $this->_db->escape($filter) . "' AND ";
        }
        $dbResult = $this->_db->query(
            'SELECT hc_id, hc_name
            FROM hostcategories
            WHERE ' . $where . " level IS NOT NULL
            AND hc_activate = '1'
            ORDER BY level ASC"
        );
        while (($row = $dbResult->fetch())) {
            $result[$row['hc_id']] = $row['hc_name'];
        }

        return $result;
    }

    public function getServicecategory($filter)
    {
        $result = [];
        $where = '';
        if (! is_null($filter) && $filter != '') {
            $where = " sc_name LIKE '" . $this->_db->escape($filter) . "' AND ";
        }
        $dbResult = $this->_db->query(
            'SELECT sc_id, sc_name
            FROM service_categories
            WHERE ' . $where . " sc_activate = '1'
            ORDER BY sc_name ASC"
        );
        while (($row = $dbResult->fetch())) {
            $result[$row['sc_id']] = $row['sc_name'];
        }

        return $result;
    }

    public function getServiceseverity($filter)
    {
        $result = [];
        $where = '';
        if (! is_null($filter) && $filter != '') {
            $where = " sc_name LIKE '" . $this->_db->escape($filter) . "' AND ";
        }
        $dbResult = $this->_db->query(
            'SELECT sc_id, sc_name
            FROM service_categories
            WHERE ' . $where . " level IS NOT NULL
            AND sc_activate = '1'
            ORDER BY level ASC"
        );
        while (($row = $dbResult->fetch())) {
            $result[$row['sc_id']] = $row['sc_name'];
        }

        return $result;
    }

    /**
     * Sets the activate field
     *
     * @param int[] $select
     * @param int $activated
     * @return void
     */
    protected function _setActivate(array $select, int $activated): void
    {
        $selectedRules = array_keys($select);

        if ($selectedRules === []) {
            return;
        }

        try {
            ['parameters' => $queryParameters, 'placeholderList' => $bindQuery] = createMultipleBindParameters(
                values: $selectedRules,
                prefix: 'ruleId',
                paramType: QueryParameterTypeEnum::INTEGER,
            );

            $queryParameters[] = QueryParameter::string('activated', $activated);

            $this->_db->update(
                query: <<<SQL
                        UPDATE mod_open_tickets_rule SET `activate` = :activated WHERE rule_id IN ({$bindQuery})
                    SQL,
                queryParameters: QueryParameters::create($queryParameters)
            );
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_BUSINESS_LOG,
                "An error occured while deleting open ticket rule(s): {$exception->getMessage()}",
                ['rule_ids' => $selectedRules]
            );

            throw new RepositoryException(
                "An error occured while deleting open ticket rule(s): {$exception->getMessage()}",
                ['rule_ids' => $selectedRules],
                $exception
            );
        }
    }

    protected function loadProvider($rule_id, $provider_id, $widget_id, $uniq_id = null)
    {
        global $centreon_path, $register_providers;

        if (! is_null($this->_provider)) {
            return;
        }

        $centreon_open_tickets_path = $centreon_path . 'www/modules/centreon-open-tickets/';
        require_once $centreon_open_tickets_path . 'providers/register.php';
        require_once $centreon_open_tickets_path . 'providers/Abstract/AbstractProvider.class.php';

        $provider_name = null;
        foreach ($register_providers as $name => $id) {
            if ($id == $provider_id) {
                $provider_name = $name;
                break;
            }
        }

        if (is_null($provider_name)
            || ! file_exists(
                $centreon_open_tickets_path
                . 'providers/'
                . $provider_name . '/'
                . $provider_name
                . 'Provider.class.php'
            )
        ) {
            throw new Exception(sprintf('Cannot find provider'));
        }

        include_once $centreon_open_tickets_path
            . 'providers/'
            . $provider_name . '/'
            . $provider_name
            . 'Provider.class.php';
        $classname = $provider_name . 'Provider';
        $this->_provider = new $classname(
            $this,
            $centreon_path,
            $centreon_open_tickets_path,
            $rule_id,
            null,
            $provider_id,
            $provider_name
        );
        $this->_provider->setWidgetId($widget_id);
        $this->_provider->setUniqId($uniq_id);
    }

    /**
     * @param string $alias
     * @return bool
     */
    private function isAliasAlreadyUsed(string $alias): bool
    {
        $exists = $this->_db->fetchAssociative(
            query: <<<'SQL'
                    SELECT 1 FROM mod_open_tickets_rule WHERE alias = :ruleAlias
                SQL,
            queryParameters: QueryParameters::create([QueryParameter::string('ruleAlias', $alias)])
        );

        return (bool) $exists;
    }

    private function getServiceStateStr($state)
    {
        $result = 'CRITICAL';

        if ($state == 0) {
            $result = 'OK';
        } elseif ($state == 1) {
            $result = 'WARNING';
        } elseif ($state == 2) {
            $result = 'CRITICAL';
        } elseif ($state == 3) {
            $result = 'UNKNOWN';
        } elseif ($state == 4) {
            $result = 'PENDING';
        }

        return $result;
    }

    private function getHostStateStr($state)
    {
        $result = 'DOWN';

        if ($state == 0) {
            $result = 'UP';
        } elseif ($state == 1) {
            $result = 'DOWN';
        } elseif ($state == 2) {
            $result = 'UNREACHABLE';
        }

        return $result;
    }
}
