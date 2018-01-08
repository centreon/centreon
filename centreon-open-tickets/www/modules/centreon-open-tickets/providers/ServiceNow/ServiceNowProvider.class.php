<?php
/*
 * Copyright 2016 Centreon (http://www.centreon.com/)
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

class ServiceNowProvider extends AbstractProvider {
  const SERVICENOW_LIST_CATEGORY = 20;
  const SERVICENOW_LIST_SUBCATEGORY = 21;
  const SERVICENOW_LIST_IMPACT = 22;
  const SERVICENOW_LIST_URGENCY = 23;
  const SERVICENOW_LIST_ASSIGNMENT_GROUP = 24;
  const SERVICENOW_LIST_ASSIGNED_TO = 25;

  /**
   * Set the default extra data
   */
  protected function _setDefaultValueExtra() {}

  /**
   * Add default data
   */
  protected function _setDefaultValueMain() {
    parent::_setDefaultValueMain();

    $this->default_data['url'] = 'https://{$servicenow_instance}.service-now.com/nav_to.do?uri=incident.do?sys_id={$ticket_id}';

    $this->default_data['clones']['groupList'] = array(
      array('Id' => 'servicenow_catergory', 'Label' => _('Category'), 'Type' => self::SERVICENOW_LIST_CATEGORY, 'Filter' => '', 'Mandatory' => ''),
      array('Id' => 'servicenow_subcatergory', 'Label' => _('Subcategory'), 'Type' => self::SERVICENOW_LIST_SUBCATEGORY, 'Filter' => '', 'Mandatory' => ''),
      array('Id' => 'servicenow_impact', 'Label' => _('Impact'), 'Type' => self::SERVICENOW_LIST_IMPACT, 'Filter' => '', 'Mandatory' => true),
      array('Id' => 'servicenow_urgency', 'Label' => _('Urgency'), 'Type' => self::SERVICENOW_LIST_URGENCY, 'Filter' => '', 'Mandatory' => true),
      array('Id' => 'servicenow_assignment_group', 'Label' => _('Assignment group'), 'Type' => self::SERVICENOW_LIST_ASSIGNMENT_GROUP, 'Filter' => '', 'Mandatory' => ''),
      array('Id' => 'servicenow_assign_to', 'Label' => _('Assign to'), 'Type' => self::SERVICENOW_LIST_ASSIGNED_TO, 'Filter' => '', 'Mandatory' => '')
    );
  }

  /**
   * Check the configuration form
   */
  protected function _checkConfigForm() {
    $this->_check_error_message = '';
    $this->_check_error_message_append = '';

    $this->_checkFormValue('servicenow_instance', 'Please set a instance.');
    $this->_checkFormValue('servicenow_clientid', 'Please set a OAuth2 client id.');
    $this->_checkFormValue('servicenow_clientsecret', 'Please set a OAuth2 client secret.');
    $this->_checkFormValue('servicenow_username', 'Please set a OAuth2 username.');
    $this->_checkFormValue('servicenow_password', 'Please set a OAuth2 password.');

    $this->_checkLists();

    if ($this->_check_error_message != '') {
        throw new Exception($this->_check_error_message);
    }
  }

  /**
   * Prepare the extra configuration block
   */
  protected function _getConfigContainer1Extra() {
    $tpl = new Smarty();
    $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/ServiceNow/templates', $this->_centreon_path);
    $tpl->assign('centreon_open_tickets_path', $this->_centreon_open_tickets_path);
    $tpl->assign('webServiceUrl', './api/internal.php');

    $values = array(
      'instance' => $this->_getFormValue('servicenow_instance'),
      'clientid' => $this->_getFormValue('servicenow_clientid'),
      'clientsecret' => $this->_getFormValue('servicenow_clientsecret'),
      'username' => $this->_getFormValue('servicenow_username'),
      'password' => $this->_getFormValue('servicenow_password'),
    );
    $tpl->assign('values', $values);

    $this->_config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');
  }

  protected function _getConfigContainer2Extra() {}

  /**
   * Add specific configuration field
   */
  protected function saveConfigExtra() {
    $this->_save_config['simple']['servicenow_instance'] = $this->_submitted_config['servicenow_instance'];
    $this->_save_config['simple']['servicenow_clientid'] = $this->_submitted_config['servicenow_clientid'];
    $this->_save_config['simple']['servicenow_clientsecret'] = $this->_submitted_config['servicenow_clientsecret'];
    $this->_save_config['simple']['servicenow_username'] = $this->_submitted_config['servicenow_username'];
    $this->_save_config['simple']['servicenow_password'] = $this->_submitted_config['servicenow_password'];
  }

  /**
   * Append additional list
   *
   * @return string
   */
  protected function getGroupListOptions() {
    $str = '<option value="' . self::SERVICENOW_LIST_CATEGORY . '">ServiceNow category</options>' .
      '<option value="' . self::SERVICENOW_LIST_SUBCATEGORY . '">ServiceNow subcategory</options>' .
      '<option value="' . self::SERVICENOW_LIST_IMPACT . '">ServiceNow impact</options>' .
      '<option value="' . self::SERVICENOW_LIST_URGENCY . '">ServiceNow urgency</options>' .
      '<option value="' . self::SERVICENOW_LIST_ASSIGNMENT_GROUP . '">ServiceNow assignment group</options>' .
      '<option value="' . self::SERVICENOW_LIST_ASSIGNED_TO . '">ServiceNow assign to</options>';

    return $str;
  }

  /**
   * Add field in popin for create a ticket
   */
  protected function assignOthers($entry, &$groups_order, &$groups) {
    $listValues = array();
    if ($entry['Type'] == self::SERVICENOW_LIST_ASSIGNED_TO) {
      $listValues = $this->getCache('assignTo');
      if (is_null($listValues)) {
        $listValues = $this->callServiceNow('getListSysUser');
        $this->setCache('assignTo', $listValues, 24 * 3600);
      }
    } else if ($entry['Type'] == self::SERVICENOW_LIST_ASSIGNMENT_GROUP) {
      $listValues = $this->getCache('assignmentGroup');
      if (is_null($listValues)) {
        $listValues = $this->callServiceNow('getListSysUserGroup');
        $this->setCache('assignmentGroup', $listValues, 24 * 3600);
      }
    } else if ($entry['Type'] == self::SERVICENOW_LIST_IMPACT) {
      $listValues = $this->getCache('impact');
      if (is_null($listValues)) {
        $listValues = $this->callServiceNow('getListImpact');
        $this->setCache('impact', $listValues, 24 * 3600);
      }
    } else if ($entry['Type'] == self::SERVICENOW_LIST_URGENCY) {
      $listValues = $this->getCache('urgency');
      if (is_null($listValues)) {
        $listValues = $this->callServiceNow('getListUrgency');
        $this->setCache('urgency', $listValues, 24 * 3600);
      }
    } else if ($entry['Type'] == self::SERVICENOW_LIST_CATEGORY) {
      $listValues = $this->getCache('category');
      if (is_null($listValues)) {
        $listValues = $this->callServiceNow('getListCategory');
        $this->setCache('category', $listValues, 24 * 3600);
      }
    } else if ($entry['Type'] == self::SERVICENOW_LIST_SUBCATEGORY) {
      $listValues = $this->getCache('subcategory');
      if (is_null($listValues)) {
        $listValues = $this->callServiceNow('getListSubcategory');
        $this->setCache('subcategory', $listValues, 24 * 3600);
      }
    }
    if (count($listValues) > 0) {
      $groups[$entry['Id']] = array('label' => _($entry['Label']) .
        (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''));
      $groups_order[] = $entry['Id'];
      $groups[$entry['Id']]['values'] = $listValues;
    }
  }

  /**
     * Create a ticket
     *
     * @param CentreonDB $db_storage The centreon_storage database connection
     * @param string $contact The contact who open the ticket
     * @param array $host_problems The list of host issues link to the ticket
     * @param array $service_problems The list of service issues link to the ticket
     * @param array $extra_ticket_arguments Extra arguments
     * @return array The status of action (
     *  'code' => int,
     *  'message' => string
     * )
     */
  protected function doSubmit($dbStorage, $contact, $hostProblems, $serviceProblems, $extra_ticket_arguments=array()) {
    $result = array('ticket_id' => null, 'ticket_error_message' => null,
      'ticket_is_ok' => 0, 'ticket_time' => time());

    /* Build the short description */
    $title = '';
    for ($i = 0; $i < count($hostProblems); $i++) {
      if ($title !== '') {
        $title .= ' | ';
      }
      $title .= $hostProblems[$i]['name'];
    }
    for ($i = 0; $i < count($serviceProblems); $i++) {
      if ($title !== '') {
        $title .= ' | ';
      }
      $title .= $serviceProblems[$i]['host_name'] . ' - ' . $serviceProblems[$i]['description'];
    }
    /* Get default body */
    $tpl = new Smarty();
    $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Abstract/templates', $this->_centreon_path);

    $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
    $tpl->assign('user', $contact);
    $tpl->assign('host_selected', $hostProblems);
    $tpl->assign('service_selected', $serviceProblems);
    $this->assignSubmittedValues($tpl);
    $tpl->assign('string', '{$body}');
    $body = $tpl->fetch('eval.ihtml');

    /* Create ticket */
    try {
      $data = $this->_submitted_config;
      $data['title'] = 'Incident on ' . $title;
      $data['body'] = $body;
      $resultInfo = $this->callServiceNow('createTicket', $data);
    } catch (\Exception $e) {
      $result['ticket_error_message'] = 'Error during create ServiceNow ticket';
    }
    $this->saveHistory(
      $dbStorage,
      $result,
      array(
        'contact' => $contact,
        'host_problems' => $hostProblems,
        'service_problems' => $serviceProblems,
        'ticket_value' => $resultInfo['sysTicketId'],
        'subject' => $title,
        'data_type' => self::DATA_TYPE_JSON,
        'data' => json_encode($data)
      )
    );
    return $result;
  }

  /**
   * Validate the popup for submit a ticket
   */
  public function validateFormatPopup() {
    $result = array('code' => 0, 'message' => 'ok');

    $this->validateFormatPopupLists($result);

    return $result;
  }

  /**
   * Get a a access token
   *
   * @param string $instance The ServiceNow instance name
   * @param string $clientId The ServiceNow OAuth client ID
   * @param string $clientSecret The ServiceNow OAuth client secret
   * @param string $username The ServiceNow OAuth username
   * @param string $password The ServiceName OAuth password
   * @return array The tokens
   */
  static protected function getAccessToken($instance, $clientId, $clientSecret, $username, $password) {
    $url = 'https://' . $instance . '.service-now.com/oauth_token.do';
    $postfields = 'grant_type=password';
    $postfields .= '&client_id=' . urlencode($clientId);
    $postfields .= '&client_secret=' . urlencode($clientSecret);
    $postfields .= '&username=' . urlencode($username);
    $postfields .= '&password=' . urlencode($password);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    /* @todo proxy */

    $returnJson = curl_exec($ch);
    if ($returnJson === false) {
      throw new \Exception(curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($status !== 200) {
      throw new \Exception(curl_error($ch));
    }
    curl_close($ch);

    $return = json_decode($returnJson, true);

    return array(
      'accessToken' => $return['access_token'],
      'refreshToken' => $return['refresh_token']
    );
  }

  /**
   * Test the service
   *
   * @param array The post information from webservice
   * @return boolean
   */
  static public function test($info) {
    /* Test arguments */
    if (!isset($info['instance']) ||
      !isset($info['clientId']) ||
      !isset($info['clientSecret']) ||
      !isset($info['username']) ||
      !isset($info['password'])) {
      throw new \Exception('Missing arguments.');
    }

    try {
      $tokens = self::getAccessToken(
        $info['instance'],
        $info['clientId'],
        $info['clientSecret'],
        $info['username'],
        $info['password']
      );
      return true;
    } catch (\Exception $e) {
      return false;
    }
  }

  /**
   * Refresh the access token
   *
   * @return string The access token
   */
  protected function refreshToken($refreshToken) {
    $instance = $this->_getFormValue('servicenow_instance');
    $url = 'https://' . $instance . '.service-now.com/oauth_token.do';
    $postfields = 'grant_type=refresh_token';
    $postfields .= '&client_id=' . urlencode($this->_getFormValue('servicenow_clientid'));
    $postfields .= '&client_secret=' . urlencode($this->_getFormValue('servicenow_clientsecret'));
    $postfields .= '&refresh_token=' . $refreshToken;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    /* @todo proxy */

    $returnJson = curl_exec($ch);
    if ($returnJson === false) {
      throw new \Exception(curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($status !== 200) {
      throw new \Exception(curl_error($ch));
    }
    curl_close($ch);

    $return = json_decode($returnJson, true);

    return array(
      'accessToken' => $return['access_token'],
      'refreshToken' => $return['refresh_token']
    );
  }

  /**
   * Call a service now Rest webservices
   */
  protected function callServiceNow($methodName, $params = array()) {
    $accessToken = $this->getCache('accessToken');
    $refreshToken = $this->getCache('refreshToken');

    if (is_null($refreshToken)) {
      $tokens = self::getAccessToken(
        $this->_getFormValue('servicenow_instance'),
        $this->_getFormValue('servicenow_clientid'),
        $this->_getFormValue('servicenow_clientsecret'),
        $this->_getFormValue('servicenow_username'),
        $this->_getFormValue('servicenow_password')
      );
      $accessToken = $tokens['accessToken'];
      $this->setCache('accessToken', $tokens['accessToken'], 1600);
      $this->setCache('refreshToken', $tokens['refreshToken'], 8400);
    } elseif (is_null($accessToken)) {
      $tokens = $this->refreshToken($refreshToken);
      $accessToken = $tokens['accessToken'];
      $this->setCache('accessToken', $tokens['accessToken'], 1600);
      $this->setCache('refreshToken', $tokens['refreshToken'], 8400);
    }

    return $this->$methodName($params, $accessToken);
  }

  /**
   * Execute the http request
   *
   * @param string $uri The URI to call
   * @param string $accessToken The OAuth access token
   * @param string $method The http method
   * @param string $data The data to send, used in method POST, PUT, PATCH
   */
  protected function runHttpRequest($uri, $accessToken, $method = 'GET', $data = null) {
    $instance = $this->_getFormValue('servicenow_instance');
    $url = 'https://' . $instance . '.service-now.com' . $uri;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Accept: application/json',
      'Content-Type: application/json',
      'Authorization: Bearer ' . $accessToken
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    if ($method !== 'GET') {
      curl_setopt($ch, CURLOPT_POST, 1);
      if (!is_null($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
      }
    }

    $returnJson = curl_exec($ch);
    if ($returnJson === false) {
      throw new \Exception(curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($status < 200 && $status >= 300) {
      throw new \Exception(curl_error($ch));
    }
    curl_close($ch);

    return json_decode($returnJson, true);
  }

  /**
   * Add a value to the cache
   *
   * @param string $key The cache key name
   * @param mixed $value The value to cache
   * @param int|null $ttl The ttl of expire this cache, if it's null no expire
   */
  protected function setCache($key, $value, $ttl = null) {
    $cacheFile = $this->getCacheFilename($key);
    file_put_contents($cacheFile, json_encode(array(
      'value' => $value,
      'ttl' => $ttl,
      'created' => time()
    )));
  }

  /**
   * Get a cache value
   *
   * @param string $key The cache key name
   * @return mixed The cache value or null if not found or expired
   */
  protected function getCache($key) {
    $cacheFile = $this->getCacheFilename($key);
    if (!file_exists($cacheFile)) {
      return null;
    }
    $cacheJson = file_get_contents($cacheFile);
    $cache = json_decode($cacheJson, true);
    if (!is_null($cache['ttl'])) {
      $timeTtl = $cache['ttl'] + $cache['created'];
      if ($timeTtl < time()) {
        unlink($cacheFile);
        return null;
      }
    }
    return $cache['value'];
  }

  /**
   * Get the cache file name
   *
   * @param string $key The cache key name
   * @return string The full path to the cache file
   */
  protected function getCacheFilename($key) {
    $tmpDir = sys_get_temp_dir();
    return $tmpDir . '/' . $this->_getFormValue('servicenow_instance') . '_' . $key;
  }

  /**
   * Get the list of user from ServiceNow for Assigned to
   *
   * @param array $param The parameters for filter (no used)
   * @param string $accessToken The access token
   * @return array The list of user
   */
  protected function getListSysUser($param, $accessToken) {
    $uri = '/api/now/table/sys_user';
    $result = $this->runHttpRequest($uri, $accessToken);

    $user = array();
    for ($i = 0; $i < count($result['result']); $i++) {
      if ($result['result'][$i]['active'] === 'true') {
        $user[$result['result'][$i]['sys_id']] = $result['result'][$i]['name'];
      }
    }

    return $user;
  }

  /**
   * Get the list of user group from ServiceNow for Assigned to
   *
   * @param array $param The parameters for filter (no used)
   * @param string $accessToken The access token
   * @return array The list of user group
   */
  protected function getListSysUserGroup($param, $accessToken) {
    $uri = '/api/now/table/sys_user_group';
    $result = $this->runHttpRequest($uri, $accessToken);

    $group = array();
    for ($i = 0; $i < count($result['result']); $i++) {
      if ($result['result'][$i]['active'] === 'true') {
        $group[$result['result'][$i]['sys_id']] = $result['result'][$i]['name'];
      }
    }

    return $group;
  }

  /**
   * Getting the list of impact from ServiceNow
   *
   * @param array $param The parameters for filter (no used)
   * @param string $accessToken The access token
   * @return array The list of impact
   */
  protected function getListImpact($params, $accessToken) {
    $uri = '/api/now/table/sys_choice?sysparm_query=nameSTARTSWITHtask%5EelementSTARTSWITHimpact';
    $result = $this->runHttpRequest($uri, $accessToken);

    $impact = array();
    for ($i = 0; $i < count($result['result']); $i++) {
      if ('false' === $result['result'][$i]['inactive']
        && $result['result'][$i]['name'] === 'task'
        && $result['result'][$i]['element'] === 'impact') {
        $impact[$result['result'][$i]['value']] = $result['result'][$i]['label'];
      }
    }
    return $impact;
  }

  /**
   * Getting the list of urgency from ServiceNow
   *
   * @param array $param The parameters for filter (no used)
   * @param string $accessToken The access token
   * @return array The list of urgency
   */
  protected function getListUrgency($params, $accessToken) {
    $uri = '/api/now/table/sys_choice?sysparm_query=nameSTARTSWITHincident%5EelementSTARTSWITHseverity';
    $result = $this->runHttpRequest($uri, $accessToken);
    error_log(json_encode($result));

    $urgency = array();
    for ($i = 0; $i < count($result['result']); $i++) {
      if ('false' === $result['result'][$i]['inactive']
        && $result['result'][$i]['name'] === 'incident'
        && $result['result'][$i]['element'] === 'severity') {
        $urgency[$result['result'][$i]['value']] = $result['result'][$i]['label'];
      }
    }
    return $urgency;
  }

  /**
   * Getting the list of category from ServiceNow
   *
   * @param array $param The parameters for filter (no used)
   * @param string $accessToken The access token
   * @return array The list of category
   */
  protected function getListCategory($params, $accessToken) {
    $uri = '/api/now/table/sys_choice?sysparm_query=nameSTARTSWITHincident%5EelementSTARTSWITHcategory';
    $result = $this->runHttpRequest($uri, $accessToken);

    $category = array();
    for ($i = 0; $i < count($result['result']); $i++) {
      if ('false' === $result['result'][$i]['inactive']
        && $result['result'][$i]['name'] === 'incident'
        && $result['result'][$i]['element'] === 'category') {
        $category[$result['result'][$i]['value']] = $result['result'][$i]['label'];
      }
    }
    return $category;
  }

  /**
   * Getting the list of subcategory from ServiceNow
   *
   * @param array $param The parameters for filter (no used)
   * @param string $accessToken The access token
   * @return array The list of subcategory
   */
  protected function getListSubcategory($params, $accessToken) {
    $uri = '/api/now/table/sys_choice?sysparm_query=nameSTARTSWITHincident%5EelementSTARTSWITHsubcategory';
    $result = $this->runHttpRequest($uri, $accessToken);

    $subcategory = array();
    for ($i = 0; $i < count($result['result']); $i++) {
      if ('false' === $result['result'][$i]['inactive']
        && $result['result'][$i]['name'] === 'incident'
        && $result['result'][$i]['element'] === 'subcategory') {
        $subcategory[$result['result'][$i]['value']] = $result['result'][$i]['label'];
      }
    }
    return $subcategory;
  }

  protected function createTicket($params, $accessToken) {
    $uri = '/api/now/v1/table/incident';

    $impacts = explode('_', $params['select_servicenow_impact'], 2);
    $urgencies = explode('_', $params['select_servicenow_urgency'], 2);

    $data = array(
      'impact' => $impacts[0],
      'urgency' => $urgencies[0],
      'short_description' => $params['title']
    );

    if ($params['select_servicenow_catergory'] !== -1) {
      $category = explode('_', $params['select_servicenow_catergory'], 2);
      $data['category'] = $category[0];
    }
    if ($params['select_servicenow_subcatergory'] !== -1) {
      $subcategory = explode('_', $params['select_servicenow_subcatergory'], 2);
      $data['subcategory'] = $subcategory[0];
    }
    if ($params['select_servicenow_assign_to'] !== -1) {
      $assignedTo = explode('_', $params['select_servicenow_assign_to'], 2);
      $data['assigned_to'] = $assignedTo[0];
    }
    if ($params['select_servicenow_assignment_group'] !== -1) {
      $assignmentGroup = explode('_', $params['select_servicenow_assignment_group'], 2);
      $data['assignment_group'] = $assignmentGroup[0];
    }
    if ($params['custom_message'] !== '') {
      $data['comments'] = $params['body'];
    }

    $result = $this->runHttpRequest($uri, $accessToken, 'POST', $data);

    return array(
      'sysTicketId' => $result['result']['sys_id'],
      'ticketId' => $result['result']['number']
    );
  }
}
