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

use Centreon\Infrastructure\Event\EventDispatcher;
use Centreon\Infrastructure\Event\EventHandler;
use Centreon\ServiceProvider;

if (! isset($centreon)) {
    exit();
}

// LDAP import form
const LDAP_IMPORT_FORM = 'li';
// Massive Change
const MASSIVE_CHANGE = 'mc';
// Add a contact
const ADD_CONTACT = 'a';
// Watch a contact
const WATCH_CONTACT = 'w';
// Modify a contact
const MODIFY_CONTACT = 'c';
// Activate a contact
const ACTIVATE_CONTACT = 's';
// Massive activate on selected contacts
const MASSIVE_ACTIVATE_CONTACT = 'ms';
// Deactivate a contact
const DEACTIVATE_CONTACT = 'u';
// Massive deactivate on selected contacts
const MASSIVE_DEACTIVATE_CONTACT = 'mu';
// Massive Unblock on selected contacts
const MASSIVE_UNBLOCK_CONTACT = 'mun';
// Duplicate n contacts and notify it
const DUPLICATE_CONTACTS = 'm';
// Delete n contacts and notify it
const DELETE_CONTACTS = 'd';
// display notification
const DISPLAY_NOTIFICATION = 'dn';
// Synchronize selected contacts with the LDAP
const SYNC_LDAP_CONTACTS = 'sync';
// Unblock contact
const UNBLOCK_CONTACT = 'un';

$cG = $_GET['contact_id'] ?? null;
$cP = $_POST['contact_id'] ?? null;
$contactId = $cG ?: $cP;

$cG = $_GET['select'] ?? null;
$cP = $_POST['select'] ?? null;
$select = $cG ?: $cP;

$cG = $_GET['dupNbr'] ?? null;
$cP = $_POST['dupNbr'] ?? null;
$dupNbr = $cG ?: $cP;

// Path to the configuration dir
$path = './include/configuration/configObject/contact/';

require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

// Set the real page
if (isset($ret) && is_array($ret) && $ret['topology_page'] != '' && $p != $ret['topology_page']) {
    $p = $ret['topology_page'];
}

$acl = $oreon->user->access;
$allowedAclGroups = $acl->getAccessGroups();

/**
 * @var EventDispatcher $eventDispatcher
 */
$eventDispatcher = $dependencyInjector[ServiceProvider::CENTREON_EVENT_DISPATCHER];

if (! is_null($eventDispatcher->getDispatcherLoader())) {
    $eventDispatcher->getDispatcherLoader()->load();
}

$duplicateEventHandler = new EventHandler();
$duplicateEventHandler->setProcessing(
    function (array $arguments) {
        if (isset($arguments['contact_ids'], $arguments['numbers'])) {
            $newContactIds = multipleContactInDB(
                $arguments['contact_ids'],
                $arguments['numbers']
            );

            // We store the result for possible future use
            return ['new_contact_ids' => $newContactIds];
        }
    }
);
$eventDispatcher->addEventHandler(
    'contact.form',
    EventDispatcher::EVENT_DUPLICATE,
    $duplicateEventHandler
);

// We define a event to delete a list of contacts
$deleteEventHandler = new EventHandler();
$deleteEventHandler->setProcessing(
    function ($arguments): void {
        if (isset($arguments['contact_ids'])) {
            deleteContactInDB($arguments['contact_ids']);
        }
    }
);
/*
 * We add the delete event in the context named 'contact.form' for and event type
 * EventDispatcher::EVENT_DELETE
 */
$eventDispatcher->addEventHandler(
    'contact.form',
    EventDispatcher::EVENT_DELETE,
    $deleteEventHandler
);

// Defining an event to manually request a LDAP synchronization of an array of contacts
$synchronizeEventHandler = new EventHandler();
$synchronizeEventHandler->setProcessing(
    function ($arguments): void {
        if (isset($arguments['contact_ids'])) {
            synchronizeContactWithLdap($arguments['contact_ids']);
        }
    }
);
$eventDispatcher->addEventHandler(
    'contact.form',
    EventDispatcher::EVENT_SYNCHRONIZE,
    $synchronizeEventHandler
);

switch ($o) {
    case LDAP_IMPORT_FORM:
        require_once $path . 'ldapImportContact.php';
        break;
    case MASSIVE_CHANGE:
    case ADD_CONTACT:
    case WATCH_CONTACT:
    case MODIFY_CONTACT:
        require_once $path . 'formContact.php';
        break;
    case ACTIVATE_CONTACT:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableContactInDB($contactId);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listContact.php';
        break;
    case MASSIVE_ACTIVATE_CONTACT:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableContactInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listContact.php';
        break;
    case DEACTIVATE_CONTACT:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableContactInDB($contactId);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listContact.php';
        break;
    case MASSIVE_DEACTIVATE_CONTACT:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableContactInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listContact.php';
        break;
    case MASSIVE_UNBLOCK_CONTACT:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            unblockContactInDB($select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listContact.php';
        break;
    case DUPLICATE_CONTACTS:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            $eventDispatcher->notify(
                'contact.form',
                EventDispatcher::EVENT_DUPLICATE,
                [
                    'contact_ids' => $select,
                    'numbers' => $dupNbr,
                ]
            );
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listContact.php';
        break;
    case DELETE_CONTACTS:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            $eventDispatcher->notify(
                'contact.form',
                EventDispatcher::EVENT_DELETE,
                ['contact_ids' => $select]
            );
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listContact.php';
        break;
    case DISPLAY_NOTIFICATION:
        require_once $path . 'displayNotification.php';
        break;
    case SYNC_LDAP_CONTACTS:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            $eventDispatcher->notify(
                'contact.form',
                EventDispatcher::EVENT_SYNCHRONIZE,
                ['contact_ids' => $select]
            );
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listContact.php';
        break;
    case UNBLOCK_CONTACT:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            unblockContactInDB($contactId);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listContact.php';
        break;
    default:
        require_once $path . 'listContact.php';
        break;
}
