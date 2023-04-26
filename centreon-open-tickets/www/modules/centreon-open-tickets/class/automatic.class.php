<?php

/*
 * Copyright 2016-2019 Centreon (http://www.centreon.com/)
 *
 * Centreon is a full-fledged industry-strength solution that meets
 * the needs in IT infrastructure and application monitoring for
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class Automatic
{
    protected $centreon;
    protected $dbCentstorage;
    protected $dbCentreon;
    protected $openTicketPath;
    protected $rule;

    /**
     * Constructor
     *
     * @param  object $rule
     * @param  string $centreonPath
     * @param  string $openTicketPath
     * @param  object $dbCentstorage
     * @param  object $dbCentreon
     * @return void
     */
    public function __construct($rule, $centreonPath, $openTicketPath, $centreon, $dbCentstorage, $dbCentreon)
    {
        global $register_providers;
        require_once $openTicketPath . 'providers/register.php';
        require_once $openTicketPath . 'providers/Abstract/AbstractProvider.class.php';

        $this->registerProviders = $register_providers;
        $this->rule = $rule;
        $this->centreonPath = $centreonPath;
        $this->openTicketPath = $openTicketPath;
        $this->centreon = $centreon;
        $this->dbCentstorage = $dbCentstorage;
        $this->dbCentreon = $dbCentreon;
        $this->uniqId = uniqid();
    }

    /**
     * Get rule information
     *
     * @param  string   $name
     * @return array
     */
    protected function getRuleInfo($name)
    {
        $stmt = $this->dbCentreon->prepare(
            "SELECT rule_id, alias, provider_id FROM mod_open_tickets_rule
            WHERE alias = :alias AND activate = '1'"
        );
        $stmt->bindParam(':alias', $name, PDO::PARAM_STR);
        $stmt->execute();
        if (!($ruleInfo = $stmt->fetch(PDO::FETCH_ASSOC))) {
            throw new Exception('Wrong parameter rule_id');
        }

        return $ruleInfo;
    }

    /**
     * Get contact information
     *
     * @param  string   $name
     * @return array
     */
    protected function getContactInformation($params)
    {
        $rv = ['alias' => '', 'email' => '', 'name' => ''];
        $dbResult = $this->dbCentreon->query(
            "SELECT
                contact_name as `name`,
                contact_alias as `alias`,
                contact_email as email
            FROM contact
            WHERE contact_id = '" . $this->centreon->user->user_id . "' LIMIT 1"
        );
        if (($row = $dbResult->fetch())) {
            $rv = $row;
        }

        if (isset($params['contact_name'])) {
            $row['name'] = $params['contact_name'];
        }
        if (isset($params['contact_alias'])) {
            $row['alias'] = $params['contact_alias'];
        }
        if (isset($params['contact_email'])) {
            $row['email'] = $params['contact_email'];
        }

        return $rv;
    }

    /**
     * Get service information
     *
     * @param  mixed   $params
     * @return mixed
     * @throws \Exception
     */
    protected function getServiceInformation($params)
    {
        $query = 'SELECT
            services.*,
            hosts.address,
            hosts.state AS host_state,
            hosts.host_id,
            hosts.name AS host_name,
            hosts.instance_id
            FROM services, hosts
            WHERE services.host_id = :host_id AND
                services.service_id = :service_id AND
                services.host_id = hosts.host_id';
        if (!$this->centreon->user->admin) {
            $query .=
                ' AND EXISTS(
                SELECT * FROM centreon_acl WHERE centreon_acl.group_id IN (' .
                $this->centreon->user->grouplistStr . ') AND ' .
                '   centreon_acl.host_id = :host_id AND centreon_acl.service_id = :service_id)';
        }
        $stmt = $this->dbCentstorage->prepare($query);
        $stmt->bindParam(':host_id', $params['host_id'], PDO::PARAM_INT);
        $stmt->bindParam(':service_id', $params['service_id'], PDO::PARAM_INT);
        $stmt->execute();
        if (!($service = $stmt->fetch(PDO::FETCH_ASSOC))) {
            throw new Exception('Wrong parameter host_id/service_id or acl');
        }

        $stmt = $this->dbCentstorage->prepare(
            'SELECT host_id, service_id, COUNT(*) AS num_metrics 
            FROM index_data, metrics 
            WHERE index_data.host_id = :host_id AND
                index_data.service_id = :service_id AND
                index_data.id = metrics.index_id 
            GROUP BY host_id, service_id'
        );
        $stmt->bindParam(':host_id', $params['host_id'], PDO::PARAM_INT);
        $stmt->bindParam(':service_id', $params['service_id'], PDO::PARAM_INT);
        $stmt->execute();
        $service['num_metrics'] = 0;
        if (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $service['num_metrics'] = $row['num_metrics'];
        }

        $service['service_state'] = $service['state'];
        $service['state_str'] = $params['service_state'];
        $service['last_state_change_duration'] = CentreonDuration::toString(
            time() - $service['last_state_change']
        );
        $service['last_hard_state_change_duration'] = CentreonDuration::toString(
            time() - $service['last_hard_state_change']
        );

        if (isset($params['last_service_state_change'])) {
            $service['last_state_change_duration'] = CentreonDuration::toString(
                time() - $params['last_service_state_change']
            );
            $service['last_hard_state_change_duration'] = CentreonDuration::toString(
                time() - $params['last_service_state_change']
            );
        }
        if (isset($params['service_output'])) {
            $service['output'] = $params['service_output'];
        }
        if (isset($params['service_description'])) {
            $service['description'] = $params['service_description'];
        }
        if (isset($params['host_name'])) {
            $service['host_name'] = $params['host_name'];
        }
        if (isset($params['host_alias'])) {
            $service['host_alias'] = $params['host_alias'];
        }

        return $service;
    }

    /**
     * Get host information
     *
     * @param  mixed   $params
     * @return mixed
     * @throws \Exception
     */
    protected function getHostInformation($params)
    {
        $query = 'SELECT * FROM hosts WHERE hosts.host_id = :host_id';
        if (!$this->centreon->user->admin) {
            $query .=
                ' AND EXISTS(
                SELECT * FROM centreon_acl WHERE centreon_acl.group_id IN (' .
                $this->centreon->user->grouplistStr . ') AND ' .
                '   centreon_acl.host_id = :host_id)';
        }
        $stmt = $this->dbCentstorage->prepare($query);
        $stmt->bindParam(':host_id', $params['host_id'], PDO::PARAM_INT);
        $stmt->execute();
        if (!($host = $stmt->fetch(PDO::FETCH_ASSOC))) {
            throw new Exception('Wrong parameter host_id or acl');
        }

        $host['host_state'] = $host['state'];
        $host['state_str'] = $params['host_state'];
        $host['last_state_change_duration'] = CentreonDuration::toString(
            time() - $host['last_state_change']
        );
        $host['last_hard_state_change_duration'] = CentreonDuration::toString(
            time() - $host['last_hard_state_change']
        );

        if (isset($params['last_host_state_change'])) {
            $host['last_state_change_duration'] = CentreonDuration::toString(
                time() - $params['last_host_state_change']
            );
            $host['last_hard_state_change_duration'] = CentreonDuration::toString(
                time() - $params['last_host_state_change']
            );
        }
        if (isset($params['host_output'])) {
            $host['output'] = $params['host_output'];
        }
        if (isset($params['host_name'])) {
            $host['name'] = $params['host_name'];
        }
        if (isset($params['host_alias'])) {
            $host['alias'] = $params['host_alias'];
        }

        return $host;
    }

    /**
     * Get provider class
     *
     * @param  array   $ruleInfo
     * @return object
     * @throws \Exception
     */
    protected function getProviderClass($ruleInfo)
    {
        $providerName = null;
        foreach ($this->registerProviders as $name => $id) {
            if (isset($ruleInfo['provider_id']) && $id == $ruleInfo['provider_id']) {
                $providerName = $name;
                break;
            }
        }

        if (is_null($providerName)) {
            throw new Exception('Provider not exist');
        }

        $file = $this->openTicketPath . 'providers/' . $providerName . '/' . $providerName . 'Provider.class.php';
        if (!file_exists($file)) {
            throw new Exception('Provider not exist');
        }

        require_once $file;
        $classname = $providerName . 'Provider';
        $providerClass = new $classname(
            $this->rule,
            $this->centreonPath,
            $this->openTicketPath,
            $ruleInfo['rule_id'],
            null,
            $ruleInfo['provider_id']
        );
        $providerClass->setUniqId($this->uniqId);

        return $providerClass;
    }

    /**
     * Get form values
     *
     * @param  mixed   $params
     * @param  mixed   $groups
     * @return array
     */
    protected function getForm($params, $groups)
    {
        $form = [ 'title' => 'automate' ];
        if (isset($params['extra_properties']) && is_array($params['extra_properties'])) {
            foreach ($params['extra_properties'] as $key => $value) {
                $form[$key] = $value;
            }
        }

        foreach ($groups as $groupId => $groupEntry) {
            if (!isset($params['select'][$groupId])) {
                if (count($groupEntry['values']) == 1) {
                    foreach ($groupEntry['values'] as $key => $value) {
                        $form['select_' . $groupId] = $key . '___' . $value;
                        if (
                            isset($groupEntry['placeholder'])
                            && isset($groupEntry['placeholder'][$key])
                        ) {
                            $form['select_' . $groupId] .= '___' . $groupEntry['placeholder'][$key];
                        }
                    }
                }
                continue;
            }

            foreach ($groupEntry['values'] as $key => $value) {
                if (
                    $params['select'][$groupId] == $key
                    || $params['select'][$groupId] == $value
                    || (
                        isset($groupEntry['placeholder'])
                        && isset($groupEntry['placeholder'][$key])
                        && $params['select'][$groupId] == $groupEntry['placeholder'][$key]
                       )
                ) {
                    $form['select_' . $groupId] = $key . '___' . $value;
                    if (
                        isset($groupEntry['placeholder'])
                        && isset($groupEntry['placeholder'][$key])
                    ) {
                        $form['select_' . $groupId] .= '___' . $groupEntry['placeholder'][$key];
                    }
                }
            }
        }

        return $form;
    }

    /**
     * Submit provider ticket
     *
     * @param  mixed $params
     * @param  array $ruleInfo
     * @param  array $contact
     * @param  array $host
     * @param  array $service
     * @return array
     */
    protected function submitTicket($params, $ruleInfo, $contact, $host, $service)
    {
        $providerClass = $this->getProviderClass($ruleInfo);

        // execute popup to get extra listing in cache
        $rv = $providerClass->getFormatPopup(
            [
                'title' => 'auto',
                'user' => [
                    'name' => $contact['name'],
                    'alias' => $contact['alias'],
                    'email' => $contact['email']
                ]
            ],
            true
        );

        $form = $this->getForm($params, $rv['groups']);
        $providerClass->setForm($form);
        $rv = $providerClass->automateValidateFormatPopupLists();
        if ($rv['code'] == 1) {
            throw new Exception('please select ' . implode(', ', $rv['lists']));
        }

        // validate form
        $rv = $providerClass->submitTicket(
            $this->dbCentstorage,
            $contact,
            $host,
            $service
        );
        if ($rv['ticket_is_ok'] == 0) {
            throw new Exception('open ticket error');
        }
        $rv['chainRuleList'] = $providerClass->getChainRuleList();
        $rv['providerClass'] = $providerClass;

        return $rv;
    }

    /**
     * Do rule chaining
     *
     * @param  array $chainRuleList
     * @param  mixed $params
     * @param  array $contact
     * @param  array $host
     * @param  array $service
     * @return void
     */
    protected function doChainRules($chainRuleList, $params, $contact, $host, $service)
    {
        $loopCheck = [];

        while (($ruleId = array_shift($chainRuleList))) {
            $ruleInfo = $this->rule->getAliasAndProviderId($ruleId);

            if (count($ruleInfo) == 0) {
                continue;
            }
            if (isset($loopCheck[$ruleInfo['provider_id']])) {
                continue;
            }
            $loopCheck[$ruleInfo['provider_id']] = 1;

            $rv = $this->submitTicket($params, $ruleInfo, $contact, $host, $service);

            array_unshift($chainRuleList, $rv['chainRuleList']);
        }
    }

    /**
     * Acknowledge, set macro and force check for a service
     *
     * @param object $providerClass
     * @param string $ticketId
     * @param array  $contact
     * @param array  $service
     * @return void
     */
    protected function externalServiceCommands($providerClass, $ticketId, $contact, $service)
    {
        require_once $this->centreonPath . 'www/class/centreonExternalCommand.class.php';

        $externalCmd = new CentreonExternalCommand($this->centreon);
        $methodExternalName = 'set_process_command';
        if (method_exists($externalCmd, $methodExternalName) == false) {
            $methodExternalName = 'setProcessCommand';
        }

        $command = "CHANGE_CUSTOM_SVC_VAR;%s;%s;%s;%s";
        call_user_func_array(
            [$externalCmd, $methodExternalName],
            [
                sprintf(
                    $command,
                    $service['host_name'],
                    $service['description'],
                    $providerClass->getMacroTicketId(),
                    $ticketId
                ),
                $service['instance_id']
            ]
        );

        if ($providerClass->doAck()) {
            $sticky = ! empty($this->centreon->optGen['monitoring_ack_sticky']) ? 2 : 1;
            $notify = ! empty($this->centreon->optGen['monitoring_ack_notify']) ? 1 : 0;
            $persistent = ! empty($this->centreon->optGen['monitoring_ack_persistent']) ? 1 : 0;

            $command = "ACKNOWLEDGE_SVC_PROBLEM;%s;%s;%s;%s;%s;%s;%s";
            call_user_func_array(
                [$externalCmd, $methodExternalName],
                [
                    sprintf(
                        $command,
                        $service['host_name'],
                        $service['description'],
                        $sticky,
                        $notify,
                        $persistent,
                        $contact['alias'],
                        'open ticket: ' . $ticketId
                    ),
                    $service['instance_id']
                ]
            );
        }

        if ($providerClass->doesScheduleCheck()) {
            $command = "SCHEDULE_FORCED_SVC_CHECK;%s;%s;%d";
            call_user_func_array(
                [$externalCmd, $methodExternalName],
                [
                    sprintf(
                        $command,
                        $service['host_name'],
                        $service['description'],
                        time()
                    ),
                    $service['instance_id']
                ]
            );
        }

        $externalCmd->write();
    }

    /**
     * Acknowledge, set macro and force check for a host
     *
     * @param object $providerClass
     * @param string $ticketId
     * @param array  $contact
     * @param array  $host
     * @return void
     */
    protected function externalHostCommands($providerClass, $ticketId, $contact, $host)
    {
        require_once $this->centreonPath . 'www/class/centreonExternalCommand.class.php';

        $externalCmd = new CentreonExternalCommand($this->centreon);
        $methodExternalName = 'set_process_command';
        if (method_exists($externalCmd, $methodExternalName) == false) {
            $methodExternalName = 'setProcessCommand';
        }

        $command = "CHANGE_CUSTOM_HOST_VAR;%s;%s;%s";
        call_user_func_array(
            [$externalCmd, $methodExternalName],
            [
                sprintf(
                    $command,
                    $host['name'],
                    $providerClass->getMacroTicketId(),
                    $ticketId
                ),
                $host['instance_id']
            ]
        );

        if ($providerClass->doAck()) {
            $sticky = ! empty($this->centreon->optGen['monitoring_ack_sticky']) ? 2 : 1;
            $notify = ! empty($this->centreon->optGen['monitoring_ack_notify']) ? 1 : 0;
            $persistent = ! empty($this->centreon->optGen['monitoring_ack_persistent']) ? 1 : 0;

            $command = "ACKNOWLEDGE_HOST_PROBLEM;%s;%s;%s;%s;%s;%s";
            call_user_func_array(
                [$externalCmd, $methodExternalName],
                [
                    sprintf(
                        $command,
                        $host['name'],
                        $sticky,
                        $notify,
                        $persistent,
                        $contact['alias'],
                        'open ticket: ' . $ticketId
                    ),
                    $host['instance_id']
                ]
            );
        }

        if ($providerClass->doesScheduleCheck()) {
            $command = "SCHEDULE_FORCED_HOST_CHECK;%s;%d";
            call_user_func_array(
                [$externalCmd, $methodExternalName],
                [
                    sprintf(
                        $command,
                        $host['name'],
                        time()
                    ),
                    $host['instance_id']
                ]
            );
        }

        $externalCmd->write();
    }

    /**
     * Open a service ticket
     *
     * @param  mixed $params
     * @return array
     */
    public function openService($params)
    {
        $ruleInfo = $this->getRuleInfo($params['rule_name']);
        $contact = $this->getContactInformation($params);
        $service = $this->getServiceInformation($params);

        $rv = $this->submitTicket($params, $ruleInfo, $contact, [], [$service]);
        $this->doChainRules($rv['chainRuleList'], $params, $contact, [], [$service]);

        $this->externalServiceCommands($rv['providerClass'], $rv['ticket_id'], $contact, $service);

        return ['code' => 0, 'message' => 'Open ticket ' . $rv['ticket_id']];
    }

    /**
     * Open a host ticket
     *
     * @param  mixed $params
     * @return array
     */
    public function openHost($params)
    {
        $ruleInfo = $this->getRuleInfo($params['rule_name']);
        $contact = $this->getContactInformation($params);
        $host = $this->getHostInformation($params);

        $rv = $this->submitTicket($params, $ruleInfo, $contact, [$host], []);
        $this->doChainRules($rv['chainRuleList'], $params, $contact, [$host], []);

        $this->externalHostCommands($rv['providerClass'], $rv['ticket_id'], $contact, $host);

        return ['code' => 0, 'message' => 'Open ticket ' . $rv['ticket_id']];
    }

    /**
     *
     * @param mixed $params
     * @param string $macroName
     * @return int $ticketId
    */
    protected function getHostTicket($params, $macroName)
    {
        $stmt = $this->dbCentstorage->prepare(
            "SELECT SQL_CALC_FOUND_ROWS mot.ticket_value AS ticket_id 
            FROM hosts h 
            LEFT JOIN customvariables cv ON (h.host_id = cv.host_id 
            AND (cv.service_id IS NULL or cv.service_id = 0) 
            AND cv.name = :macro_name)
            LEFT JOIN mod_open_tickets mot ON cv.value = mot.ticket_value 
            WHERE h.host_id = :host_id"
        );
        $stmt->bindParam(':macro_name', $macroName, PDO::PARAM_STR);
        $stmt->bindParam(':host_id', $params['host_id'], PDO::PARAM_INT);
        $stmt->execute();

        if (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $ticketId = $row['ticket_id'];
        }

        return $ticketId;
    }

    /**
     *
     * @param mixed $params
     * @param string $macroName
     * @return int $ticketId
    */
    protected function getServiceTicket($params, $macroName)
    {
        $stmt = $this->dbCentstorage->prepare(
            "SELECT SQL_CALC_FOUND_ROWS mot.ticket_value AS ticket_id 
            FROM services s 
            LEFT JOIN customvariables cv ON ( cv.service_id = :service_id AND cv.name = :macro_name)
            LEFT JOIN mod_open_tickets mot ON cv.value = mot.ticket_value 
            WHERE s.service_id = :service_id"
        );
        $stmt->bindParam(':service_id', $params['service_id'], PDO::PARAM_INT);
        $stmt->bindParam(':macro_name', $macroName, PDO::PARAM_STR);

        $stmt->execute();

        if (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $ticketId = $row['ticket_id'];
        }

        return $ticketId;
    }

    /**
     * Close a host ticket
     *
     * @param  mixed $params
     * @return array
     */
    public function closeHost($params)
    {
        $ruleInfo = $this->getRuleInfo($params['rule_name']);
        $host = $this->getHostInformation($params);
        $providerClass = $this->getProviderClass($ruleInfo);
        $macroName = $providerClass->getMacroTicketId();

        $ticketId = $this->getHostTicket($params, $macroName);

        $rv = ['code' => 0, 'message' => 'no ticket found for host: ' . $host['name']];

        if ($ticketId) {
            try {
                $providerClass->closeTicket([$ticketId]);
                $this->changeMacroHost($macroName, $host);
                $rv = ['code' => 0, 'message' => 'ticket ' . $ticketId . ' has been closed'];
            } catch (Exception $e) {
                $rv = [ 'code' => -1, 'message' => $e->getMessage() ];
            }
        }

        return $rv;
    }

    /**
     * Close a service ticket
     *
     * @param  mixed $params
     * @return array
     */
    public function closeService($params)
    {
        $ruleInfo = $this->getRuleInfo($params['rule_name']);
        $service = $this->getServiceInformation($params);
        $providerClass = $this->getProviderClass($ruleInfo);
        $macroName = $providerClass->getMacroTicketId();

        $ticketId = $this->getServiceTicket($params, $macroName);

        $rv = ['code' => 0, 'message' => 'no ticket found for service: '
               . $service['host_name'] . " " . $service['description']];

        if ($ticketId) {
            try {
                $providerClass->closeTicket([$ticketId]);
                $this->changeMacroService($macroName, $service);
                $rv = ['code' => 0, 'message' => 'ticket ' . $ticketId . ' has been closed'];
            } catch (Exception $e) {
                $rv = [ 'code' => -1, 'message' => $e->getMessage() ];
            }
        }

        return $rv;
    }

    /**
     * Reset the ticket custom macro for host
     *
     * @param  string $macroName
     * @param array $host
     * @return void
     */
    protected function changeMacroHost($macroName, $host)
    {
        require_once $this->centreonPath . 'www/class/centreonExternalCommand.class.php';

        $externalCmd = new CentreonExternalCommand($this->centreon);
        $methodExternalName = 'set_process_command';
        if (method_exists($externalCmd, $methodExternalName) == false) {
            $methodExternalName = 'setProcessCommand';
        }

        $command = "CHANGE_CUSTOM_HOST_VAR;%s;%s;%s";
        call_user_func_array(
            [$externalCmd, $methodExternalName],
            [
                sprintf(
                    $command,
                    $host['name'],
                    $macroName,
                    ""
                ),
                $host['instance_id']
            ]
        );

        $externalCmd->write();
    }

    /**
     * Reset the ticket custom macro for service
     *
     * @param  string $macroName
     * @param array $service
     * @return void
     */
    protected function changeMacroService($macroName, $service)
    {
        require_once $this->centreonPath . 'www/class/centreonExternalCommand.class.php';

        $externalCmd = new CentreonExternalCommand($this->centreon);
        $methodExternalName = 'set_process_command';
        if (method_exists($externalCmd, $methodExternalName) == false) {
            $methodExternalName = 'setProcessCommand';
        }

        $command = "CHANGE_CUSTOM_SVC_VAR;%s;%s;%s;%s";
        call_user_func_array(
            [$externalCmd, $methodExternalName],
            [
                sprintf(
                    $command,
                    $service['host_name'],
                    $service['description'],
                    $macroName,
                    ""
                ),
                $service['instance_id']
            ]
        );

        $externalCmd->write();
    }
}
