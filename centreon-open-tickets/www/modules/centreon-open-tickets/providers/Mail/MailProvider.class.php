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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

require_once __DIR__ . '/library/PHPMailer.php';
require_once __DIR__ . '/library/Exception.php';

class MailProvider extends AbstractProvider
{
    protected $attach_files = 1;

    protected function setDefaultValueMain($body_html = 0)
    {
        parent::setDefaultValueMain(1);
    }

    protected function setDefaultValueExtra()
    {
        $this->default_data['from'] = '{$user.email}';
        $this->default_data['subject'] =
            'Issue {$ticket_id} - {include file="file:$centreon_open_tickets_path' .
            '/providers/Abstract/templates/display_title.ihtml"}';
        $this->default_data['clones']['headerMail'] = array();
        $this->default_data['ishtml'] = 'yes';
    }

    /**
     * Check form
     *
     * @return a string
     */
    protected function checkConfigForm()
    {
        $this->check_error_message = '';
        $this->check_error_message_append = '';
        $this->checkFormValue('from', "Please set 'From' value");
        $this->checkFormValue('to', "Please set 'To' value");
        $this->checkFormValue('subject', "Please set 'Subject' value");
        $this->checkFormValue('macro_ticket_id', "Please set 'Macro Ticket ID' value");
        $this->checkFormInteger('confirm_autoclose', "'Confirm popup autoclose' must be a number");

        $this->checkLists();

        if ($this->check_error_message != '') {
            throw new Exception($this->check_error_message);
        }
    }

    /**
     * Build the specifc config: from, to, subject, body, headers
     *
     * @return void
     */
    protected function getConfigContainer1Extra()
    {
        $tpl = $this->initSmartyTemplate('providers/Mail/templates');

        $tpl->assign("centreon_open_tickets_path", $this->centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", ["mail" => _("Mail")]);

        // Form
        $from_html = '<input size="50" name="from" type="text" value="' . $this->getFormValue('from') . '" />';
        $to_html = '<input size="50" name="to" type="text" value="' . $this->getFormValue('to') . '" />';
        $subject_html = '<input size="50" name="subject" type="text" value="'
            . $this->getFormValue('subject')
            . '" />';
        $ishtml_html = '<div class="md-checkbox md-checkbox-inline">' .
            '<input type="checkbox" id="ishtml" name="ishtml" value="yes" ' .
            ($this->getFormValue('ishtml') === 'yes' ? 'checked' : '') . '/>' .
            '<label class="empty-label" for="ishtml"></label></div>';

        $array_form = array(
            'from' => array('label' => _("From") . $this->required_field, 'html' => $from_html),
            'to' => array('label' => _("To") . $this->required_field, 'html' => $to_html),
            'subject' => array('label' => _("Subject") . $this->required_field, 'html' => $subject_html),
            'header' => array('label' => _("Headers")),
            'ishtml' => array('label' => _("Use html"), 'html' => $ishtml_html),
        );

        // Clone part
        $headerMailName_html = '<input id="headerMailName_#index#" size="20" name="headerMailName[#index#]" ' .
            'type="text" />';
        $headerMailValue_html = '<input id="headerMailValue_#index#" size="20" name="headerMailValue[#index#]" ' .
            'type="text" />';
        $array_form['headerMail'] = array(
            array('label' => _("Name"), 'html' => $headerMailName_html),
            array('label' => _("Value"), 'html' => $headerMailValue_html),
        );

        $tpl->assign('form', $array_form);
        $this->config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');
        $this->config['clones']['headerMail'] = $this->getCloneValue('headerMail');
    }

    /**
     * Build the specific advanced config: -
     *
     * @return void
     */
    protected function getConfigContainer2Extra()
    {
    }

    protected function saveConfigExtra()
    {
        $this->save_config['clones']['headerMail'] = $this->getCloneSubmitted('headerMail', array('Name', 'Value'));
        $this->save_config['simple']['from'] = $this->submitted_config['from'];
        $this->save_config['simple']['to'] = $this->submitted_config['to'];
        $this->save_config['simple']['subject'] = $this->submitted_config['subject'];
        $this->save_config['simple']['ishtml'] = (
            isset($this->submitted_config['ishtml'])
            && $this->submitted_config['ishtml'] == 'yes'
        ) ? $this->submitted_config['ishtml'] : '';
    }

    public function validateFormatPopup()
    {
        $result = array('code' => 0, 'message' => 'ok');
        $this->validateFormatPopupLists($result);
        return $result;
    }

    protected function doSubmit($db_storage, $contact, $host_problems, $service_problems)
    {
        $result = array(
            'ticket_id' => null,
            'ticket_error_message' => null,
            'ticket_is_ok' => 0,
            'ticket_time' => time()
        );

        try {
            $db_storage->query(
                "INSERT INTO mod_open_tickets (`timestamp`, `user`) VALUES
                ('" . $result['ticket_time'] . "', '" . $db_storage->escape($contact['name']) . "')"
            );
            $result['ticket_id'] = $db_storage->lastinsertId('mod_open_tickets');
        } catch (Exception $e) {
            $result['ticket_error_message'] = $e->getMessage();
            return $result;
        }

        $tpl = $this->initSmartyTemplate();
        $tpl->assign("centreon_open_tickets_path", $this->centreon_open_tickets_path);
        $tpl->assign('user', $contact);
        $tpl->assign('host_selected', $host_problems);
        $tpl->assign('service_selected', $service_problems);
        $tpl->assign('ticket_id', $result['ticket_id']);
        $this->assignSubmittedValues($tpl);

        // We send the mail
        $tpl->assign('string', $this->rule_data['from']);
        $from = $tpl->fetch('eval.ihtml');

        $tpl->assign('string', $this->rule_data['subject']);
        $subject = $tpl->fetch('eval.ihtml');

        $mail = new PHPMailer(true);
        try {
            $mail->CharSet = 'utf-8';
            $mail->setFrom($from);
            $mail->addAddress($this->rule_data['to']);
            if (isset($this->rule_data['ishtml']) && $this->rule_data['ishtml'] == 'yes') {
                $mail->isHTML(true);
            }
            $attach_files = $this->getUploadFiles();
            foreach ($attach_files as $file) {
                $mail->addAttachment($file['filepath'], $file['filename']);
            }

            $headers = "From: " . $from;

            if (isset($this->rule_data['clones']['headerMail'])) {
                foreach ($this->rule_data['clones']['headerMail'] as $values) {
                    $mail->addCustomHeader($values['Name'], $values['Value']);
                    $headers .= "\r\n" . $values['Name'] . ':' . $values['Value'];
                }
            }

            $mail->Subject = $subject;
            $mail->Body = $this->body;
            $mail->send();
            $this->saveHistory(
                $db_storage,
                $result,
                [
                    'no_create_ticket_id' => true,
                    'contact' => $contact,
                    'host_problems' => $host_problems,
                    'service_problems' => $service_problems,
                    'subject' => $subject,
                    'data_type' => self::DATA_TYPE_JSON,
                    'data' => json_encode(
                        [
                            'mail' => $mail->getSentMIMEMessage()
                        ]
                    )
                ]
            );
        } catch (MailerException $e) {
            $result['ticket_error_message'] = 'Mailer Error: ' . $mail->ErrorInfo;
        } catch (\Exception $e) {
            $result['ticket_error_message'] = 'Mailer Error: ' . $e->getMessage();
        }

        return $result;
    }
}
