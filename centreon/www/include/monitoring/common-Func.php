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

$configFile = realpath(__DIR__ . '/../../../config/centreon.config.php');
require_once __DIR__ . '/../../class/config-generate/host.class.php';
require_once __DIR__ . '/../../class/config-generate/service.class.php';

if (! isset($centreon)) {
    exit();
}

function get_user_param($user_id, $pearDB)
{
    $list_param = ['ack_sticky', 'ack_notify', 'ack_persistent', 'ack_services', 'force_active', 'force_check'];
    $tab_row = [];
    foreach ($list_param as $param) {
        if (isset($_SESSION[$param])) {
            $tab_row[$param] = $_SESSION[$param];
        }
    }

    return $tab_row;
}

function set_user_param($user_id, $pearDB, $key, $value)
{
    $_SESSION[$key] = $value;
}

/**
 * Get the notified contact/contact group of host tree inheritance
 *
 * @param int $hostId
 * @param Pimple\Container $dependencyInjector
 * @return array
 */
function getNotifiedInfosForHost(int $hostId, Pimple\Container $dependencyInjector): array
{
    $results = ['contacts' => [], 'contactGroups' => []];
    $hostInstance = Host::getInstance($dependencyInjector);
    $notifications = $hostInstance->getCgAndContacts($hostId);

    if (isset($notifications['cg']) && count($notifications['cg']) > 0) {
        $results['contactGroups'] = getContactgroups($notifications['cg']);
    }
    if (isset($notifications['contact']) && count($notifications['contact']) > 0) {
        $results['contacts'] = getContacts($notifications['contact']);
    }

    natcasesort($results['contacts']);
    natcasesort($results['contactGroups']);

    return $results;
}

/**
 * Get the list of enable contact groups (id/name)
 *
 * @param int[] $cg list contact group id
 * @return array
 */
function getContactgroups(array $cg): array
{
    global $pearDB;

    $contactGroups = [];
    $dbResult = $pearDB->query(
        'SELECT cg_id, cg_name 
        FROM contactgroup
        WHERE cg_id IN (' . implode(', ', $cg) . ')'
    );
    while (($row = $dbResult->fetchRow())) {
        $contactGroups[$row['cg_id']] = $row['cg_name'];
    }

    return $contactGroups;
}

/**
 * Get the list of enable contact (id/name)
 *
 * @param int[] $contacts list contact id
 * @return array
 */
function getContacts(array $contacts): array
{
    global $pearDB;

    $contactsResult = [];
    $dbResult = $pearDB->query(
        'SELECT contact_id, contact_name 
        FROM contact
        WHERE contact_id IN (' . implode(', ', $contacts) . ')'
    );
    while (($row = $dbResult->fetchRow())) {
        $contactsResult[$row['contact_id']] = $row['contact_name'];
    }

    return $contactsResult;
}

/**
 * Get the notified contact/contact group of service tree inheritance
 *
 * @param int $serviceId
 * @param int $hostId
 * @param Pimple\Container $dependencyInjector
 * @return array
 */
function getNotifiedInfosForService(int $serviceId, int $hostId, Pimple\Container $dependencyInjector): array
{
    $results = ['contacts' => [], 'contactGroups' => []];

    $serviceInstance = Service::getInstance($dependencyInjector);
    $notifications = $serviceInstance->getCgAndContacts($serviceId);

    if (((! isset($notifications['cg']) || count($notifications['cg']) == 0)
        && (! isset($notifications['contact']) || count($notifications['contact']) == 0))
        || $serviceInstance->getString($serviceId, 'service_use_only_contacts_from_host')
    ) {
        $results = getNotifiedInfosForHost($hostId, $dependencyInjector);
    } else {
        if (isset($notifications['cg']) && count($notifications['cg']) > 0) {
            $results['contactGroups'] = getContactgroups($notifications['cg']);
        }
        if (isset($notifications['contact']) && count($notifications['contact']) > 0) {
            $results['contacts'] = getContacts($notifications['contact']);
        }
    }

    natcasesort($results['contacts']);
    natcasesort($results['contactGroups']);

    return $results;
}
