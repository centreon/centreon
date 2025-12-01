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

use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;
use Psr\Log\LogLevel;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class EasyVistaRestProvider extends AbstractProvider
{
    public const EZV_ASSET_TYPE = 16;
    public const ARG_TITLE = 1;
    public const ARG_URGENCY_ID = 2;
    public const ARG_REQUESTOR_NAME = 3;
    public const ARG_RECIPIENT_NAME = 4;
    public const ARG_PHONE = 5;
    public const ARG_ORIGIN = 6;
    public const ARG_IMPACT_ID = 7;
    public const ARG_DESCRIPTION = 8;
    public const ARG_DEPARTMENT_CODE = 9;
    public const ARG_CI_NAME = 10;
    public const ARG_ASSET_NAME = 11;
    public const ARG_LOCATION_CODE = 12;
    public const ARG_CATALOG_GUID = 13;
    public const ARG_CATALOG_CODE = 14;
    public const ARG_CUSTOM_EZV = 15;

    protected $close_advanced = 1;

    protected $proxy_enabled = 1;

    protected $internal_arg_name = [
        self::ARG_TITLE => 'title',
        self::ARG_URGENCY_ID => 'urgency',
        self::ARG_REQUESTOR_NAME => 'requestor',
        self::ARG_RECIPIENT_NAME => 'recipient',
        self::ARG_PHONE => 'phone',
        self::ARG_ORIGIN => 'origin',
        self::ARG_IMPACT_ID => 'impact',
        self::ARG_DESCRIPTION => 'description',
        self::ARG_DEPARTMENT_CODE => 'department',
        self::ARG_CI_NAME => 'CI',
        self::ARG_ASSET_NAME => 'asset',
        self::ARG_LOCATION_CODE => 'requester',
        self::ARG_CATALOG_GUID => 'catalog_guid',
        self::ARG_CATALOG_CODE => 'catalog_code',
    ];

    /*
    * checks if all mandatory fields have been filled
    *
    * @return {array} telling us if there is a missing parameter
    */
    public function validateFormatPopup()
    {
        $result = ['code' => 0, 'message' => 'ok'];
        $this->validateFormatPopupLists($result);

        return $result;
    }

    public static function test($info): void
    {
        // not implemented because there's no known url to test the api connection
    }

    /*
    * check if the close option is enabled, if so, try to close every selected ticket
    *
    * @param {array} $tickets
    *
    * @return void
    */
    public function closeTicket(&$tickets): void
    {
        if ($this->doCloseTicket()) {
            foreach ($tickets as $k => $v) {
                try {
                    $this->closeTicketEzv($k);
                    $tickets[$k]['status'] = 2;
                } catch (Exception $e) {
                    $tickets[$k]['status'] = -1;
                    $tickets[$k]['msg_error'] = $e->getMessage();
                }
            }
        } else {
            parent::closeTicket($tickets);
        }
    }

    // webservice methods
    public function getHostgroups($centreon_path, $data)
    {
        $hostCount = count($data['host_list']);
        $listIds = '';

        $queryValues = [];
        foreach ($data['host_list'] as $hostId) {
            $listIds .= ':hId_' . $hostId . ', ';
            $queryValues[':hId_' . $hostId] = (int) $hostId;
        }

        $listIds = rtrim($listIds, ', ');

        require_once $centreon_path . 'www/modules/centreon-open-tickets/class/centreonDBManager.class.php';
        $db_storage = new CentreonDBManager('centstorage');

        $query = 'SELECT name FROM hostgroups WHERE hostgroup_id IN'
            . ' (SELECT hostgroup_hg_id FROM centreon.hostgroup_relation WHERE host_host_id IN (' . $listIds . ')'
            . ' GROUP BY hostgroup_hg_id HAVING count(hostgroup_hg_id) = :host_count)';

        $dbQuery = $db_storage->prepare($query);
        foreach ($queryValues as $bindName => $bindValue) {
            $dbQuery->bindValue($bindName, $bindValue, PDO::PARAM_INT);
        }
        $dbQuery->bindValue(':host_count', $hostCount, PDO::PARAM_INT);
        $dbQuery->execute();

        $result = [];
        while ($row = $dbQuery->fetch()) {
            array_push($result, $row['name']);
        }

        return $result;
    }

    // Set default values for our rule form options
    protected function setDefaultValueExtra()
    {
        $this->default_data['address'] = '127.0.0.1';
        $this->default_data['api_path'] = '/api/v1';
        $this->default_data['protocol'] = 'https';
        $this->default_data['account'] = '';
        $this->default_data['token'] = '';
        $this->default_data['use_token'] = 1;
        $this->default_data['timeout'] = 60;

        $this->default_data['clones']['mappingTicket'] = [
            [
                'Arg' => self::ARG_TITLE,
                'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers'
                    . '/Abstract/templates/display_title.ihtml"}',
            ],
            [
                'Arg' => self::ARG_DESCRIPTION,
                'Value' => '{$body}',
            ],
            [
                'Arg' => self::ARG_URGENCY_ID,
                'Value' => '{$select.ezv_urgency.id}',
            ],
            [
                'Arg' => self::ARG_REQUESTOR_NAME,
                'Value' => '{$select.ezv_requestor_name.id}',
            ],
            [
                'Arg' => self::ARG_RECIPIENT_NAME,
                'Value' => '{$select.ezv_recipient_name.id}',
            ],
            [
                'Arg' => self::ARG_PHONE,
                'Value' => '{$select.ezv_phone.id}',
            ],
            [
                'Arg' => self::ARG_ORIGIN,
                'Value' => '{$select.ezv_origin.value}',
            ],
            [
                'Arg' => self::ARG_IMPACT_ID,
                'Value' => '{$select.ezv_impact_id.id}',
            ],
            [
                'Arg' => self::ARG_DEPARTMENT_CODE,
                'Value' => '{$select.ezv_department_code.value}',
            ],
            [
                'Arg' => self::ARG_CI_NAME,
                'Value' => '{$select.ezv_ci_name.value}',
            ],
            [
                'Arg' => self::ARG_ASSET_NAME,
                'Value' => '{$select.ezv_asset_name.value}',
            ],
            [
                'Arg' => self::ARG_LOCATION_CODE,
                'Value' => '{$select.ezv_location_code.value}',
            ],
            [
                'Arg' => self::ARG_CATALOG_GUID,
                'Value' => '{$select.ezv_catalog_guid.id}',
            ],
            [
                'Arg' => self::ARG_CATALOG_CODE,
                'Value' => '{$select.ezv_catalog_code.id}',
            ],
        ];
    }

    /*
    * Set default values for the widget popup when opening a ticket
    *
    * @return void
    */
    protected function setDefaultValueMain($body_html = 0)
    {
        parent::setDefaultValueMain($body_html);

        $this->default_data['url'] = 'tobedetermined';

        $this->default_data['format_popup'] = '
<table class="table">
    <tr>
        <td class="FormHeader" colspan="2"><h3 style="color: #00bfb3;">{$title}</h3></td>
    </tr>
    <tr>
        <td class="FormRowField" style="padding-left:15px;">{$custom_message.label}</td>
        <td class="FormRowValue" style="padding-left:15px;">
            <textarea id="custom_message" name="custom_message" cols="50" rows="6"></textarea>
        </td>
    </tr>
    <tr>
        <td class="FormRowField" style="padding-left:15px;">Use hostgroup name as CI</td>
        <td class="FormRowField" style="padding-left:15px;"><input id="ci_type_selector" type="checkbox"></input></td>
    </tr>
    </tr>
    {include file="file:$centreon_open_tickets_path/providers/Abstract/templates/groups.ihtml"}
    {include file="file:$centreon_open_tickets_path/providers/EasyVistaRest/templates/handle_ci.ihtml"}
</table>';

        // $this->default_data['clones']['groupList'] = [
        //     [
        //         'Id' => 'ezv_asset_name',
        //         'Label' => _('Assets'),
        //         'Type' => self::EZV_ASSET_TYPE,
        //         'Filter' => '',
        //         'Mandatory' => ''
        //     ]
        // ];

        // $this->default_data['clones']['customList'] = [
        //     [
        //         'Id' => 'ezv_origin',
        //         'Value' => '1',
        //         'Label' => 'Very Low',
        //         'Default' => ''
        //     ]
        // ];
    }

    /*
    * Verify if every mandatory form field is filled with data
    *
    * @throw \Exception when a form field is not set
    */
    protected function checkConfigForm()
    {
        $this->check_error_message = '';
        $this->check_error_message_append = '';

        $this->checkFormValue('address', 'Please set "Address" value');
        $this->checkFormValue('api_path', 'Please set "API path" value');
        $this->checkFormValue('protocol', 'Please set "Protocol" value');
        $this->checkFormValue('account', 'Please set "Account" value');
        $this->checkFormValue('token', 'Please set "Token or Password" value');
        $this->checkFormInteger('timeout', '"Timeout" must be an integer');
        $this->checkFormInteger('use_token', '"Use token" must be an integer');

        $this->checkLists();

        if ($this->check_error_message != '') {
            throw new Exception($this->check_error_message);
        }
    }

    // Initiate your html configuration and let Smarty display it in the rule form
    protected function getConfigContainer1Extra()
    {
        $tpl = $this->initSmartyTemplate('providers/EasyVistaRest/templates');
        $tpl->assign('centreon_open_tickets_path', $this->centreon_open_tickets_path);
        $tpl->assign('img_brick', './modules/centreon-open-tickets/images/brick.png');
        $tpl->assign('header', ['EasyVistaRest' => _('Easyvista Rest Api')]);
        $tpl->assign('webServiceUrl', './api/internal.php');

        // we create the html that is going to be displayed
        $address_html = '<input size="50" name="address" type="text" value="'
            . $this->getFormValue('address') . '" />';
        $api_path_html = '<input size="50" name="api_path" type="text" value="'
            . $this->getFormValue('api_path') . '" />';
        $protocol_html = '<input size="50" name="protocol" type="text" value="'
            . $this->getFormValue('protocol') . '" />';
        $account_html = '<input size="50" name="account" type="text" value="'
            . $this->getFormValue('account') . '" autocomplete="off" />';
        $token_html = '<input size="50" name="token" type="password" value="'
            . $this->getFormValue('token') . '" autocomplete="off" />';
        $timeout_html = '<input size="50" name="timeout" type="text" value="'
            . $this->getFormValue('timeout') . '" :>';
        $use_token_html = '<input size="50" name="use_token" type="text" value="'
            . $this->getFormValue('use_token') . '" :>';

        // this array is here to link a label with the html code that we've wrote above
        $array_form = [
            'address' => ['label' => _('Address') . $this->required_field, 'html' => $address_html],
            'api_path' => ['label' => _('API path') . $this->required_field, 'html' => $api_path_html],
            'protocol' => ['label' => _('Protocol') . $this->required_field, 'html' => $protocol_html],
            'account' => ['label' => _('Account') . $this->required_field, 'html' => $account_html],
            'token' => ['label' => _('Bearer token or account password') . $this->required_field, 'html' => $token_html],
            'timeout' => ['label' => _('Timeout'), 'html' => $timeout_html],
            'use_token' => ['label' => _('Use token'), 'html' => $use_token_html],
            // we add a key to our array
            'mappingTicketLabel' => ['label' => _('Mapping ticket arguments')],
        ];

        // html
        $mappingTicketValue_html = '<input id="mappingTicketValue_#index#" '
            . 'name="mappingTicketValue[#index#]" size="20" type="text"';

        // html code for a dropdown list where we will be able to select something from the following list
        $mappingTicketArg_html = '<select id="mappingTicketArg_#index#" '
            . 'name="mappingTicketArg[#index#]" type="select-one">'
            . '<option value="' . self::ARG_TITLE . '">' . _('Title') . '</option>'
            . '<option value="' . self::ARG_URGENCY_ID . '">' . _('Urgency') . '</option>'
            . '<option value="' . self::ARG_REQUESTOR_NAME . '">' . _('Requester') . '</option>'
            . '<option value="' . self::ARG_RECIPIENT_NAME . '">' . _('Recipient') . '</option>'
            . '<option value="' . self::ARG_PHONE . '">' . _('Phone') . '</option>'
            . '<option value="' . self::ARG_ORIGIN . '">' . _('Origin') . '</option>'
            . '<option value="' . self::ARG_IMPACT_ID . '">' . _('Impact') . '</option>'
            . '<option value="' . self::ARG_DESCRIPTION . '">' . _('Description') . '</option>'
            . '<option value="' . self::ARG_DEPARTMENT_CODE . '">' . _('Department') . '</option>'
            . '<option value="' . self::ARG_CI_NAME . '">' . _('CI') . '</option>'
            . '<option value="' . self::ARG_ASSET_NAME . '">' . _('Asset') . '</option>'
            . '<option value="' . self::ARG_LOCATION_CODE . '">' . _('Location') . '</option>'
            . '<option value="' . self::ARG_CATALOG_GUID . '">' . _('Catalog GUID') . '</option>'
            . '<option value="' . self::ARG_CATALOG_CODE . '">' . _('Catalog code') . '</option>'
            . '<option value="' . self::ARG_CUSTOM_EZV . '">' . _('Custom Field') . '</option>'
            . '</select>';

        // we asociate the label with the html code but for the arguments that we've been working on lately
        $array_form['mappingTicket'] = [['label' => _('Argument'), 'html' => $mappingTicketArg_html], ['label' => _('Value'), 'html' => $mappingTicketValue_html]];

        $tpl->assign('form', $array_form);
        $this->config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');
        $this->config['clones']['mappingTicket'] = $this->getCloneValue('mappingTicket');
    }

    protected function getConfigContainer2Extra()
    {
    }

    // Saves the rule form in the database
    protected function saveConfigExtra()
    {
        $this->save_config['simple']['address'] = $this->submitted_config['address'];
        $this->save_config['simple']['api_path'] = $this->submitted_config['api_path'];
        $this->save_config['simple']['protocol'] = $this->submitted_config['protocol'];
        $this->save_config['simple']['account'] = $this->submitted_config['account'];
        $this->save_config['simple']['token'] = $this->submitted_config['token'];
        $this->save_config['simple']['timeout'] = $this->submitted_config['timeout'];
        $this->save_config['simple']['use_token'] = $this->submitted_config['use_token'];

        // saves the ticket arguments
        $this->save_config['clones']['mappingTicket'] = $this->getCloneSubmitted('mappingTicket', ['Arg', 'Value']);
    }

    /*
    * Adds new types to the list of types
    *
    * @return {string} $str html code that add an option to a select
    */
    protected function getGroupListOptions()
    {
        return '<option value="' . self::EZV_ASSET_TYPE . '">Asset</option>';
    }

    protected function assignOthers($entry, &$groups_order, &$groups)
    {
        if ($entry['Type'] == self::EZV_ASSET_TYPE) {
            $this->assignEzvAssets($entry, $groups_order, $groups);
        }
    }

    protected function assignEzvAssets($entry, &$groups_order, &$groups)
    {
        // add a label to our entry and activate sorting or not.
        $groups[$entry['Id']] = ['label' => _($entry['Label'])
        . (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->required_field : ''), 'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)];
        // adds our entry in the group order array
        $groups_order[] = $entry['Id'];

        // try to get entities
        try {
            $listAssets = $this->getCache($entry['Id']);
            if (is_null($listAssets)) {
                $listAssets = $this->getAssets($entry['Filter']);
                $this->setCache($entry['Id'], $listAssets, 8 * 3600);
            }
        } catch (Exception $e) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $e->getMessage();
        }
        $result = [];

        foreach ($listAssets['records'] ?? [] as $asset) {
            // HREF structure is the following: https://{your_server}/api/v1/{your_account}/assets/9478 we only keep id
            preg_match('/.*\/([0-9]+)$/', $asset['HREF'], $match);
            $result[$match[1]] = $this->to_utf8($asset['ASSET_TAG']);
        }

        $groups[$entry['Id']]['values'] = $result;
    }

    protected function getAssets($filter)
    {
        // add the api endpoint and method to our info array
        $info['query_endpoint'] = '/assets?fields=asset_tag,HREF';

        if (! empty($filter)) {
            $info['query_endpoint'] .= '&' . $filter;
        }

        $info['method'] = 'GET';

        // try to get assets from ezv
        try {
            // the variable is going to be used outside of this method.
            $result = $this->curlQuery($info);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        return $result;
    }

    /*
    * brings all parameters together in order to build the ticket arguments and save
    * ticket data in the database
    *
    * @param {object} $db_storage centreon storage database informations
    * @param {array} $contact centreon contact informations
    * @param {array} $host_problems centreon host information
    * @param {array} $service_problems centreon service information
    * @param {array} $extraTicketArguments
    *
    * @return {array} $result will tell us if the submit ticket action resulted in a ticket being opened
    */
    protected function doSubmit($db_storage, $contact, $host_problems, $service_problems, $extraTicketArguments = [])
    {
        // initiate a result array
        $result = ['ticket_id' => null, 'ticket_error_message' => null, 'ticket_is_ok' => 0, 'ticket_time' => time()];

        // Smarty template initialization
        $tpl = SmartyBC::createSmartyTemplate($this->centreon_open_tickets_path, 'providers/Abstract/templates');

        $tpl->assign('centreon_open_tickets_path', $this->centreon_open_tickets_path);
        $tpl->assign('user', $contact);
        $tpl->assign('host_selected', $host_problems);
        $tpl->assign('service_selected', $service_problems);
        // assign submitted values from the widget to the template
        $this->assignSubmittedValues($tpl);

        $ticketArguments = $extraTicketArguments;
        if (isset($this->rule_data['clones']['mappingTicket'])) {
            // for each ticket argument in the rule form, we retrieve its value
            foreach ($this->rule_data['clones']['mappingTicket'] as $value) {
                $tpl->assign('string', $value['Value']);
                $resultString = $tpl->fetch('eval.ihtml');
                if ($resultString == '') {
                    $resultstring = null;
                }

                // specific condition to handle ezv custom field "dynamically"
                if ($this->internal_arg_name[$value['Arg']] == $this->internal_arg_name[self::ARG_CUSTOM_EZV]) {
                    $ticketArguments[$value['Value']] = $resultString;
                } else {
                    $ticketArguments[$this->internal_arg_name[$value['Arg']]] = $resultString;
                }
            }
        }

        // we try to open the ticket
        try {
            $ticketId = $this->createTicket($ticketArguments);
        } catch (Exception $e) {
            $result['ticket_error_message'] = $e->getMessage();

            return $result;
        }

        // we save ticket data in our database
        $this->saveHistory($db_storage, $result, ['contact' => $contact, 'host_problems' => $host_problems, 'service_problems' => $service_problems, 'ticket_value' => $ticketId, 'subject' => $ticketArguments[$this->internal_arg_name[self::ARG_TITLE]], 'data_type' => self::DATA_TYPE_JSON, 'data' => json_encode($ticketArguments)]);

        return $result;
    }

    protected function curlQuery($info)
    {
        $apiAddress = $this->getFormValue('protocol') . '://'
        . $this->getFormValue('address')
        . $this->getFormValue('api_path')
        . $info['query_endpoint'];

        $headers = [
            'Content-Type' => 'application/json',
        ];

        // Token mode
        if ((int) $this->getFormValue('use_token') === 1) {
            $headers['Authorization'] = 'Bearer ' . $this->getFormValue('token');
        }

        // Build HTTP options
        $options = [
            'headers' => $headers,
            'timeout' => (int) $this->getFormValue('timeout'),
            'verify_peer' => true,
            'verify_host' => true,
        ];

        // Custom CA
        if (
            $this->getFormValue('ca_certificate') !== ''
            && is_readable($this->getFormValue('ca_certificate'))
        ) {
            $options['cafile'] = $this->getFormValue('ca_certificate');
        }

        // Basic auth if not using token
        if ((int) $this->getFormValue('use_token') !== 1) {
            $options['auth_basic'] = [
                $this->getFormValue('account'),
                $this->getFormValue('token'),
            ];
        }

        // JSON body
        if (! empty($info['data'])) {
            $options['json'] = $info['data'];
        }

        // Proxy
        if (
            $this->getFormValue('proxy_address') !== ''
            && $this->getFormValue('proxy_port') !== ''
        ) {
            $proxy = $this->getFormValue('proxy_address') . ':' . $this->getFormValue('proxy_port');

            if (
                $this->getFormValue('proxy_username') !== ''
                && $this->getFormValue('proxy_password') !== ''
            ) {
                $options['proxy'] = sprintf(
                    'http://%s:%s@%s',
                    $this->getFormValue('proxy_username'),
                    $this->getFormValue('proxy_password'),
                    $proxy
                );
            } else {
                $options['proxy'] = 'http://' . $proxy;
            }
        }

        // Determine method
        $method = $info['method'] ?? 'GET';

        if (isset($info['custom_request'])) {
            $method = $info['custom_request'];
        }

        $context = [
            'endpoint' => $apiAddress,
            'method' => $method,
            'timeout' => $this->getFormValue('timeout'),
            'proxy_enabled' => $this->getFormValue('proxy_address') !== '',
            'proxy_address' => $this->getFormValue('proxy_address'),
            'proxy_port' => $this->getFormValue('proxy_port'),
            'response_status' => null,
        ];

        try {
            $client = HttpClient::create();
            $response = $client->request($method, $apiAddress, $options);

            $status = $response->getStatusCode();
            $context['response_status'] = $status;

            if ($status !== 200 && $status !== 201) {
                $body = $response->getContent(false);

                throw new Exception(
                    'An error happened with endpoint: ' . $apiAddress
                    . '. Easyvista response is: ' . $body
                );
            }

            $body = $response->getContent();

            return json_decode($body, true);
        } catch (
            TransportExceptionInterface|
            ClientExceptionInterface|
            ServerExceptionInterface|
            RedirectionExceptionInterface $exception
        ) {
            ExceptionLogger::create()->log($exception, $context, LogLevel::ERROR);

            throw new Exception(
                'HTTP client error while calling ' . $apiAddress . ': ' . $exception->getMessage(),
                previous: $exception,
            );
        } catch (Exception $exception) {
            ExceptionLogger::create()->log($exception, $context, LogLevel::ERROR);

            throw $exception;
        }
    }

    protected function createTicket($ticketArguments)
    {
        // $file = fopen("/var/log/php-fpm/ezv", "a") or die ("Unable to open file!");
        // add the api endpoint and method to our info array
        $info['query_endpoint'] = '/requests';
        $info['method'] = 'POST';
        $info['data'] = [
            'requests' => [
                [
                    'catalog_guid' => $ticketArguments[$this->internal_arg_name[self::ARG_CATALOG_GUID]],
                    'catalog_code' => $ticketArguments[$this->internal_arg_name[self::ARG_CATALOG_CODE]],
                    'title' => $ticketArguments[$this->internal_arg_name[self::ARG_TITLE]],
                ],
            ],
        ];

        if (! empty($ticketArguments[$this->internal_arg_name[self::ARG_ASSET_NAME]])) {
            $info['data']['requests'][0]['asset_name'] = $ticketArguments[$this->internal_arg_name[self::ARG_ASSET_NAME]];
        }

        if (! empty($ticketArguments[$this->internal_arg_name[self::ARG_URGENCY_ID]])) {
            $info['data']['requests'][0]['urgency_id'] = $ticketArguments[$this->internal_arg_name[self::ARG_URGENCY_ID]];
        }

        if (! empty($ticketArguments[$this->internal_arg_name[self::ARG_REQUESTOR_NAME]])) {
            $info['data']['requests'][0]['requester_name'] = $ticketArguments[$this->internal_arg_name[self::ARG_REQUESTOR_NAME]];
        }

        if (! empty($ticketArguments[$this->internal_arg_name[self::ARG_RECIPIENT_NAME]])) {
            $info['data']['requests'][0]['recipient_name'] = $ticketArguments[$this->internal_arg_name[self::ARG_RECIPIENT_NAME]];
        }

        if (! empty($ticketArguments[$this->internal_arg_name[self::ARG_PHONE]])) {
            $info['data']['requests'][0]['phone'] = $ticketArguments[$this->internal_arg_name[self::ARG_PHONE]];
        }

        if (! empty($ticketArguments[$this->internal_arg_name[self::ARG_ORIGIN]])) {
            $info['data']['requests'][0]['origin'] = $ticketArguments[$this->internal_arg_name[self::ARG_ORIGIN]];
        }

        if (! empty($ticketArguments[$this->internal_arg_name[self::ARG_IMPACT_ID]])) {
            $info['data']['requests'][0]['impact_id'] = $ticketArguments[$this->internal_arg_name[self::ARG_IMPACT_ID]];
        }

        if (! empty($ticketArguments[$this->internal_arg_name[self::ARG_DESCRIPTION]])) {
            $info['data']['requests'][0]['description'] = $ticketArguments[$this->internal_arg_name[self::ARG_DESCRIPTION]];
        }

        if (! empty($ticketArguments[$this->internal_arg_name[self::ARG_DEPARTMENT_CODE]])) {
            $info['data']['requests'][0]['department_code'] = $ticketArguments[$this->internal_arg_name[self::ARG_DEPARTMENT_CODE]];
        }

        if (! empty($ticketArguments[$this->internal_arg_name[self::ARG_CI_NAME]])) {
            $info['data']['requests'][0]['ci_name'] = $ticketArguments[$this->internal_arg_name[self::ARG_CI_NAME]];
        }

        if (! empty($ticketArguments[$this->internal_arg_name[self::ARG_LOCATION_CODE]])) {
            $info['data']['requests'][0]['location_code'] = $ticketArguments[$this->internal_arg_name[self::ARG_LOCATION_CODE]];
        }

        foreach ($ticketArguments as $id => $value) {
            // $id is structure is "{$select.e_my_custom_field_name.value}" we keep "e_my_custom_field_name"
            if (preg_match('/.*\.(e_.*)\.[id|value|placeholder].*/', $id, $match)) {
                $info['data']['requests'][0][$match[1]] = $value;
            }
        }

        //         fwrite($file, print_r("\n ticketargs \n",true));
        //         fwrite($file, print_r($ticketArguments,true));

        // fwrite($file, print_r("\n info \n",true));
        // fwrite($file, print_r(json_encode($info['data']),true));
        $result = $this->curlQuery($info);
        preg_match('~' . $this->getFormValue('address') . $this->getFormValue('api_path') . $info['query_endpoint'] . '/(.*)$~', $result['HREF'], $match);

        return $match[1];
        // fclose($file);

        // return 1234;
    }

    protected function closeTicketEzv($ticketId)
    {
        // add the api endpoint and method to our info array
        $info['query_endpoint'] = '/requests/' . $ticketId;
        $info['method'] = 0;
        $info['custom_request'] = 'PUT';
        $info['data'] = [
            'closed' => [],
        ];

        try {
            $this->curlQuery($info);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        return 0;
    }
}
