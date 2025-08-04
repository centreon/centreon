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

ini_set('display_errors', 'Off');

use App\Kernel;
use Centreon\Domain\Contact\Interfaces\ContactServiceInterface;
use Core\Domain\Engine\Model\EngineCommandGenerator;

require_once realpath(__DIR__ . '/../../../../../config/centreon.config.php');
require_once realpath(__DIR__ . '/../../../../../config/bootstrap.php');
require_once _CENTREON_PATH_ . '/www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . 'www/include/configuration/configGenerate/DB-Func.php';
require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonXML.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonBroker.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonACL.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonUser.class.php';

if (! defined('STATUS_OK')) {
    define('STATUS_OK', 0);
}
if (! defined('STATUS_NOK')) {
    define('STATUS_NOK', 1);
}

$pearDB = new CentreonDB();
$xml = new CentreonXML();

$okMsg = "<b><font color='green'>OK</font></b>";
$nokMsg = "<b><font color='red'>NOK</font></b>";

$kernel = new Kernel('prod', false);
$kernel->boot();
$container = $kernel->getContainer();
if ($container == null) {
    throw new Exception(_('Unable to load the Symfony container'));
}
if (isset($_SERVER['HTTP_X_AUTH_TOKEN'])) {
    $contactService = $container->get(ContactServiceInterface::class);
    $contact = $contactService->findByAuthenticationToken($_SERVER['HTTP_X_AUTH_TOKEN']);

    if ($contact === null) {
        $xml->startElement('response');
        $xml->writeElement('status', $nokMsg);
        $xml->writeElement('statuscode', STATUS_NOK);
        $xml->writeElement('error', 'Contact not found');
        $xml->endElement();

        if (! headers_sent()) {
            header('Content-Type: application/xml');
            header('Cache-Control: no-cache');
            header('Expires: 0');
            header('Cache-Control: no-cache, must-revalidate');
        }

        $xml->output();

        exit();
    }
    $centreon = new Centreon([
        'contact_id' => $contact->getId(),
        'contact_name' => $contact->getName(),
        'contact_alias' => $contact->getAlias(),
        'contact_email' => $contact->getEmail(),
        'contact_admin' => $contact->isAdmin(),
        'contact_lang' => null,
        'contact_passwd' => null,
        'contact_autologin_key' => null,
        'contact_location' => null,
        'reach_api' => $contact->hasAccessToApiConfiguration(),
        'reach_api_rt' => $contact->hasAccessToApiRealTime(),
        'show_deprecated_pages' => false,
    ]);
} else {
    // Check Session
    CentreonSession::start(1);
    if (! CentreonSession::checkSession(session_id(), $pearDB)) {
        echo 'Bad Session';

        exit();
    }

    $centreon = $_SESSION['centreon'];
}

if (! isset($_POST['poller']) || ! isset($_POST['mode'])) {
    exit();
}

/**
 * List of error from php
 */
global $generatePhpErrors;
$generatePhpErrors = [];

/**
 * The error handler for get error from PHP
 *
 * @see set_error_handler
 */
$log_error = function ($errno, $errstr, $errfile, $errline) {
    global $generatePhpErrors;
    if (! (error_reporting() && $errno)) {
        return;
    }

    switch ($errno) {
        case E_ERROR:
        case E_USER_ERROR:
        case E_CORE_ERROR:
            $generatePhpErrors[] = ['error', $errstr];
            break;
        case E_WARNING:
        case E_USER_WARNING:
        case E_CORE_WARNING:
            $generatePhpErrors[] = ['warning', $errstr];
            break;
    }

    return true;
};

try {
    $pollers = explode(',', $_POST['poller']);

    $ret = [];
    $ret['host'] = $pollers;
    $ret['restart_mode'] = $_POST['mode'];

    chdir(_CENTREON_PATH_ . 'www');
    $nagiosCFGPath = _CENTREON_CACHEDIR_ . '/config/engine/';
    $centreonBrokerPath = _CENTREON_CACHEDIR_ . '/config/broker/';

    // Set new error handler
    set_error_handler($log_error);

    $centcoreDirectory = defined('_CENTREON_VARLIB_') ? _CENTREON_VARLIB_ : '/var/lib/centreon';
    if (is_dir($centcoreDirectory . '/centcore')) {
        $centcorePipe = $centcoreDirectory . '/centcore/' . microtime(true) . '-externalcommand.cmd';
    } else {
        $centcorePipe = $centcoreDirectory . '/centcore.cmd';
    }

    $stdout = '';
    if (! isset($msg_restart)) {
        $msg_restart = [];
    }

    $tabs = $centreon->user->access->getPollerAclConf([
        'fields' => [
            'name',
            'id',
            'engine_restart_command',
            'engine_reload_command',
            'broker_reload_command',
        ],
        'order' => ['name'],
        'conditions' => ['ns_activate' => '1'],
        'keys' => ['id'],
    ]);
    foreach ($tabs as $tab) {
        if (isset($ret['host']) && ($ret['host'] == 0 || in_array($tab['id'], $ret['host']))) {
            $poller[$tab['id']] = ['id' => $tab['id'], 'name' => $tab['name'], 'engine_restart_command' => $tab['engine_restart_command'], 'engine_reload_command' => $tab['engine_reload_command'], 'broker_reload_command' => $tab['broker_reload_command']];
        }
    }

    // Restart broker
    $brk = new CentreonBroker($pearDB);
    $brk->reload();
    /**
     * @var EngineCommandGenerator $commandGenerator
     */
    $commandGenerator = $container->get(EngineCommandGenerator::class);
    foreach ($poller as $host) {
        if ($ret['restart_mode'] == 1) {
            if ($fh = @fopen($centcorePipe, 'a+')) {
                $reloadCommand = ($commandGenerator !== null)
                    ? $commandGenerator->getEngineCommand('RELOAD')
                    : 'RELOAD';
                fwrite($fh, $reloadCommand . ':' . $host['id'] . "\n");
                fclose($fh);
            } else {
                throw new Exception(_('Could not write into centcore.cmd. Please check file permissions.'));
            }

            // Manage Error Message
            if (! isset($msg_restart[$host['id']])) {
                $msg_restart[$host['id']] = '';
            }
            $msg_restart[$host['id']] .= _(
                '<br><b>Centreon : </b>A reload signal has been sent to '
                . $host['name'] . "\n"
            );
        } elseif ($ret['restart_mode'] == 2) {
            if ($fh = @fopen($centcorePipe, 'a+')) {
                $restartCommand = ($commandGenerator !== null)
                    ? $commandGenerator->getEngineCommand('RESTART')
                    : 'RESTART';
                fwrite($fh, $restartCommand . ':' . $host['id'] . "\n");
                fclose($fh);
            } else {
                throw new Exception(_('Could not write into centcore.cmd. Please check file permissions.'));
            }

            // Manage error Message
            if (! isset($msg_restart[$host['id']])) {
                $msg_restart[$host['id']] = '';
            }
            $msg_restart[$host['id']] .= _(
                '<br><b>Centreon : </b>A restart signal has been sent to ' . $host['name'] . "\n"
            );
        }
        $DBRESULT = $pearDB->query("UPDATE `nagios_server` SET `last_restart` = '"
            . time() . "', `updated` = '0' WHERE `id` = '" . $host['id'] . "'");
    }

    foreach ($msg_restart as $key => $str) {
        $msg_restart[$key] = str_replace("\n", '<br>', $str);
    }

    $xml->startElement('response');
    $xml->writeElement('status', $okMsg);
    $xml->writeElement('statuscode', STATUS_OK);
} catch (Exception $e) {
    $xml->startElement('response');
    $xml->writeElement('status', $nokMsg);
    $xml->writeElement('statuscode', STATUS_NOK);
    $xml->writeElement('error', $e->getMessage());
}

// Restore default error handler
restore_error_handler();

// Add error form php
$xml->startElement('errorsPhp');
foreach ($generatePhpErrors as $error) {
    if ($error[0] == 'error') {
        $errmsg = '<span style="color: red;">Error</span><span style="margin-left: 5px;">' . $error[1] . '</span>';
    } else {
        $errmsg = '<span style="color: orange;">Warning</span><span style="margin-left: 5px;">' . $error[1] . '</span>';
    }
    $xml->writeElement('errorPhp', $errmsg);
}
$xml->endElement();

$xml->endElement();

// Headers
if (! headers_sent()) {
    header('Content-Type: application/xml');
    header('Cache-Control: no-cache');
    header('Expires: 0');
    header('Cache-Control: no-cache, must-revalidate');
}

// Send Data
$xml->output();
