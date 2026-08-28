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

use Core\ActionLog\Domain\Model\ActionLog;

/**
 * Administration > Logs turns AVAILABLE_OBJECT_TYPES into positional option values:
 * viewLogs.php renders each array index as the <option value> and maps the submitted
 * index back through the same array. That index is persisted in the user's stored
 * search, so inserting a type anywhere but at the end silently reinterprets every
 * saved filter as a different object type.
 *
 * Pinned as literals rather than constant names on purpose: two constants share the
 * value 'host', so only the values and their positions describe the invariant.
 */
it('keeps the object types in their positional order', function (): void {
    expect(ActionLog::AVAILABLE_OBJECT_TYPES)->toBe([
        'command',
        'timeperiod',
        'contact',
        'contactgroup',
        'host',
        'hostgroup',
        'service',
        'servicegroup',
        'traps',
        'escalation',
        'host dependency',
        'hostgroup dependency',
        'service dependency',
        'servicegroup dependency',
        'poller',
        'engine',
        'broker',
        'resources',
        'meta',
        'access group',
        'menu access',
        'resource access',
        'action access',
        'manufacturer',
        'hostcategories',
        'servicecategories',
        'connector',
    ]);
});

it('exposes the connector object type the AJAX toggle writes, appended last', function (): void {
    expect(ActionLog::OBJECT_TYPE_CONNECTOR)->toBe('connector')
        ->and(array_key_last(ActionLog::AVAILABLE_OBJECT_TYPES))
        ->toBe(array_search(ActionLog::OBJECT_TYPE_CONNECTOR, ActionLog::AVAILABLE_OBJECT_TYPES, true));
});
