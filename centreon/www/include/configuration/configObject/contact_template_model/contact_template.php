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

$cG = array_key_exists('contact_id', $_GET) && $_GET['contact_id'] !== null
    ? HtmlSanitizer::createFromString($_GET['contact_id'])->sanitize()->getString()
    : null;
$cP = array_key_exists('contact_id', $_POST) && $_POST['contact_id'] !== null
    ? HtmlSanitizer::createFromString($_POST['contact_id'])->sanitize()->getString()
    : null;
$contact_id = $cG ?: $cP;
$cG = array_key_exists('select', $_GET) && $_GET['select'] !== null
    ? validateInput($_GET['select'])
    : null;
$cP = array_key_exists('select', $_POST) && $_POST['select'] !== null
    ? validateInput($_POST['select'])
    : null;
$select = $cG ?: $cP;

$cG = array_key_exists('dupNbr', $_GET) && $_GET['dupNbr'] !== null
    ? validateInput($_GET['dupNbr'])
    : null;
$cP = array_key_exists('dupNbr', $_POST) && $_POST['dupNbr'] !== null
    ? validateInput($_POST['dupNbr'])
    : null;
$dupNbr = $cG ?: $cP;

function validateInput(array|string $inputs): array
{
    if (is_string($inputs)) {
        $inputs = explode(',', trim($inputs, ','));
    }
    foreach ($inputs as $contactTemplateId => $value) {
        if (
            filter_var($contactTemplateId, FILTER_VALIDATE_INT) !== false
            && filter_var($value, FILTER_VALIDATE_INT) !== false
        ) {
            continue;
        }

        throw new Exception('Invalid value supplied');
    }

    return $inputs;
}

// Path to the configuration dir
$path = './include/configuration/configObject/contact_template_model/';

// PHP functions
require_once './include/configuration/configObject/contact/DB-Func.php';
require_once './include/common/common-Func.php';

// Set the real page
if (isset($ret) && is_array($ret) && $ret['topology_page'] != '' && $p != $ret['topology_page']) {
    $p = $ret['topology_page'];
}

$contactObj = new CentreonContact($pearDB);

/**
 * @var EventDispatcher $eventDispatcher
 */
$eventDispatcher = $dependencyInjector[ServiceProvider::CENTREON_EVENT_DISPATCHER];
$eventContext = 'contact.template.form';

if (! is_null($eventDispatcher->getDispatcherLoader())) {
    $eventDispatcher->getDispatcherLoader()->load();
}

$eventDispatcher->addEventHandler(
    $eventContext,
    EventDispatcher::EVENT_DUPLICATE,
    (function (): EventHandler {
        $handler = new EventHandler();
        $handler->setProcessing(function (array $arguments) {
            if (isset($arguments['contact_ids'], $arguments['numbers'])) {
                $newContactIds = multipleContactInDB(
                    $arguments['contact_ids'],
                    $arguments['numbers']
                );

                // We store the result for possible future use
                return ['new_contact_ids' => $newContactIds];
            }
        });

        return $handler;
    })()
);

/*
 * We add the delete event in the context named 'contact.template.form' for and event type
 * EventDispatcher::EVENT_DELETE
 */
$eventDispatcher->addEventHandler(
    $eventContext,
    EventDispatcher::EVENT_DELETE,
    (function () {
        // We define an event to delete a list of contacts
        $handler = new EventHandler();
        $handler->setProcessing(function ($arguments): void {
            if (isset($arguments['contact_ids'])) {
                deleteContactInDB($arguments['contact_ids']);
            }
        });

        return $handler;
    })()
);

switch ($o) {
    case 'mc':
        require_once $path . 'formContactTemplateModel.php';
        break; // Massive Change
    case 'a':
        require_once $path . 'formContactTemplateModel.php';
        break; // Add a contact template
    case 'w':
        require_once $path . 'formContactTemplateModel.php';
        break; // Watch a contact template
    case 'c':
        require_once $path . 'formContactTemplateModel.php';
        break; // Modify a contact template
    case 's':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableContactInDB($contact_id);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listContactTemplateModel.php';
        break; // Activate a contact template
    case 'ms':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableContactInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listContactTemplateModel.php';
        break;
    case 'u':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableContactInDB($contact_id);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listContactTemplateModel.php';
        break; // Desactivate a contact
    case 'mu':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableContactInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listContactTemplateModel.php';
        break;
    case 'm':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            // We notify that we have made a duplicate
            $eventDispatcher->notify(
                $eventContext,
                EventDispatcher::EVENT_DUPLICATE,
                [
                    'contact_ids' => $select ?? [],
                    'numbers' => $dupNbr,
                ]
            );
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listContactTemplateModel.php';
        break; // Duplicate n contacts
    case 'd':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            // We notify that we have made a delete
            $eventDispatcher->notify(
                $eventContext,
                EventDispatcher::EVENT_DELETE,
                ['contact_ids' => $select ?? []]
            );
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listContactTemplateModel.php';
        break; // Delete n contacts
    default:
        require_once $path . 'listContactTemplateModel.php';
        break;
}
