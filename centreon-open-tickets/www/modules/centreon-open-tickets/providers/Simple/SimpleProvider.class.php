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

class SimpleProvider extends AbstractProvider {    
    
    protected function _setDefaultValueExtra() {
        
    }
    
    protected function _setDefaultValueMain() {
        parent::_setDefaultValueMain();
        $this->default_data['format_popup'] = '';
    }
    
    /**
     * Check form
     *
     * @return a string
     */
    protected function _checkConfigForm() {
        $this->_check_error_message = '';
        $this->_check_error_message_append = '';
        
        $this->_checkFormValue('macro_ticket_id', "Please set 'Macro Ticket ID' value");
        $this->_checkFormValue('macro_ticket_time', "Please set 'Macro Ticket Time' value");
        $this->_checkFormInteger('confirm_autoclose', "'Confirm popup autoclose' must be a number");
        
        $this->_checkLists();
        
        if ($this->_check_error_message != '') {
            throw new Exception($this->_check_error_message);
        }
    }
    
    /**
     * Build the specifc config: from, to, subject, body, headers
     *
     * @return void
     */
    protected function _getConfigContainer1Extra() {
    }
    
    /**
     * Build the specific advanced config: -
     *
     * @return void
     */
    protected function _getConfigContainer2Extra() {
        
    }
    
    protected function saveConfigExtra() {
    }
    
    public function validateFormatPopup() {
        $result = array('code' => 0, 'message' => 'ok');
        
        $this->validateFormatPopupLists($result);
        return $result;
    }
    
    protected function doSubmit($db_storage, $contact, $host_problems, $service_problems) {
        $result = array('ticket_id' => null, 'ticket_error_message' => null,
                        'ticket_is_ok' => 0, 'ticket_time' => time());
        
        try {
            $query = "INSERT INTO mod_open_tickets
  (`timestamp`, `user`) VALUES ('" . $result['ticket_time'] . "', '" . $db_storage->escape($contact['name']) . "')";            
            $db_storage->query($query);
            $result['ticket_id'] = $db_storage->lastinsertId('mod_open_tickets');
            $result['ticket_is_ok'] = 1;
        } catch (Exception $e) {
            $result['ticket_error_message'] = $e->getMessage();
            return $result;
        }
        
        return $result;
    }
}
