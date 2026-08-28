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

declare(strict_types=1);

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Log\Enum\LogChannelEnum;
use Adaptation\Log\Logger;
use Core\ActionLog\Domain\Model\ActionLog;

require_once realpath(__DIR__ . '/../..') . '/common/listing/AjaxListingHelper.php';

/** Topology page id of Administration > Logs. */
const CHANGELOG_PAGE_ID = 508;

$helper = AjaxListingHelper::boot();
$centreon = $helper->requireCentreon();
$params = $helper->getParams();

// $pearDB / $pearDBO are read as globals by CentreonLogAction, which is used
// below to resolve the host a logged service belonged to. This file is the
// entry point, so its top-level scope is the global scope.
$pearDB = $helper->getDb();
$pearDBO = new CentreonDB('centstorage');

$search = $params['search'];
$num = $params['num'];
$limit = $params['limit'];

// getParams() bounds num from below only. Left unchecked, num * limit overflows
// into a float, QueryParameter::int raises a TypeError, and a malformed request
// is reported as a 500 with a log entry instead of a 400.
if ($num > intdiv(PHP_INT_MAX, max($limit, 1))) {
    AjaxListingHelper::jsonError('Invalid pagination parameters', 400);
}

// ACL: require at least read access on the changelog page.
if (! $helper->isAdmin()) {
    $acl = $helper->getAcl();
    if (! $acl || $acl->page(CHANGELOG_PAGE_ID) === 0) {
        AjaxListingHelper::jsonError('Access denied', 403, 'access_denied');
    }
}

$sanitize = static fn (string $value): string => HtmlSanitizer::createFromString($value)
    ->sanitize()
    ->removeTags()
    ->getString();

$searchUser = $sanitize((string) ($_GET['searchUser'] ?? ''));
$objectType = $sanitize((string) ($_GET['objectType'] ?? ''));

// Reject rather than drop the clause: silently ignoring an unknown type returns
// rows of every type while the filter still reads as active in the UI.
if ($objectType !== '' && ! in_array($objectType, ActionLog::AVAILABLE_OBJECT_TYPES, true)) {
    AjaxListingHelper::jsonError('Invalid object type', 400);
}

require_once _CENTREON_PATH_ . '/www/class/centreonLogAction.class.php';
$logAction = $centreon->CentreonLogAction ?? null;

if ($logAction === null) {
    // Only the "host / service" prefix is lost, so the request still succeeds.
    // Logged once here rather than once per row.
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX changelog listing: CentreonLogAction unavailable, service parents not resolved'
    );
}

try {
    $conditions = [];
    $parameters = [];

    if ($search !== '') {
        $conditions[] = 'object_name LIKE :object_name';
        $parameters[] = QueryParameter::string('object_name', '%' . $search . '%');
    }

    // A user filter narrows the logs to the contacts whose name or alias
    // matches. No match means the result is empty by construction: answer
    // right away instead of running the log queries for nothing.
    if ($searchUser !== '') {
        $contactIds = $pearDB->fetchFirstColumn(
            <<<'SQL'
                SELECT contact_id
                FROM contact
                WHERE contact_name LIKE :name
                    OR contact_alias LIKE :alias
                SQL,
            QueryParameters::create([
                QueryParameter::string('name', '%' . $searchUser . '%'),
                QueryParameter::string('alias', '%' . $searchUser . '%'),
            ])
        );

        if ($contactIds === []) {
            $helper->jsonResponse([], 0, $num, $limit);
        }

        $contactIds = array_map('intval', $contactIds);

        $placeholders = [];
        foreach ($contactIds as $index => $contactId) {
            $placeholders[] = ":contact_{$index}";
            $parameters[] = QueryParameter::int("contact_{$index}", $contactId);
        }
        $conditions[] = 'log_contact_id IN (' . implode(', ', $placeholders) . ')';
    }

    if ($objectType !== '') {
        // Command changes are written under two tokens: the Core writer uses the
        // singular constant, the legacy path the plural 'commands'. Both are
        // labelled "Command" in the listing, so the filter has to match both or
        // rows vanish when the user selects the type they are labelled with.
        $typeTokens = $objectType === ActionLog::OBJECT_TYPE_COMMAND
            ? [ActionLog::OBJECT_TYPE_COMMAND, 'commands']
            : [$objectType];

        $placeholders = [];
        foreach ($typeTokens as $index => $typeToken) {
            $placeholders[] = ":object_type_{$index}";
            $parameters[] = QueryParameter::string("object_type_{$index}", $typeToken);
        }
        $conditions[] = 'object_type IN (' . implode(', ', $placeholders) . ')';
    }

    // Rows with no object id are unusable (no detail page to link to) and are
    // filtered out in SQL so that they don't skew the total either. object_id is
    // a NOT NULL int column, so 0 is the only "missing" value it can hold —
    // comparing it to '' would coerce the literal and forfeit the index.
    $conditions[] = 'object_id <> 0';

    $whereClause = 'WHERE ' . implode(' AND ', $conditions);

    $total = (int) $pearDBO->fetchOne(
        <<<SQL
            SELECT COUNT(*)
            FROM log_action
            {$whereClause}
            SQL,
        QueryParameters::create($parameters)
    );

    $logs = $pearDBO->fetchAllAssociative(
        <<<SQL
            SELECT action_log_id,
                object_id,
                object_type,
                object_name,
                action_log_date,
                action_type,
                log_contact_id
            FROM log_action
            {$whereClause}
            ORDER BY action_log_date DESC
            LIMIT :offset, :limit
            SQL,
        QueryParameters::create([
            ...$parameters,
            QueryParameter::int('offset', $num * $limit),
            QueryParameter::int('limit', $limit),
        ])
    );

    // Author labels are resolved for the contacts of this page only — loading
    // the whole contact table on every request does not scale.
    $authors = [];
    $contactIdsOnPage = array_values(array_unique(array_filter(
        array_map(static fn (array $log): int => (int) $log['log_contact_id'], $logs)
    )));

    if ($contactIdsOnPage !== []) {
        $placeholders = [];
        $authorParameters = [];
        foreach ($contactIdsOnPage as $index => $contactId) {
            $placeholders[] = ":author_{$index}";
            $authorParameters[] = QueryParameter::int("author_{$index}", $contactId);
        }
        $authorPlaceholders = implode(', ', $placeholders);

        $authors = $pearDB->fetchAllKeyValue(
            <<<SQL
                SELECT contact_id,
                    CONCAT(contact_name, ' (', contact_alias, ')')
                FROM contact
                WHERE contact_id IN ({$authorPlaceholders})
                SQL,
            QueryParameters::create($authorParameters)
        );
    }
} catch (Throwable $e) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX changelog listing: could not read the configuration logs',
        ['exception' => $e]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}

/**
 * A logged service is only identified by its own name; prefix it with the host
 * (or hostgroup) it was attached to at the time so the row is unambiguous.
 * Best effort: the relation may have been deleted since the event.
 */
$parentFailureCount = 0;
$parentFailureCause = null;

$resolveServiceParent = static function (int $objectId, string $objectName) use (
    $logAction,
    &$parentFailureCount,
    &$parentFailureCause
): string {
    if ($logAction === null) {
        return $objectName;
    }

    try {
        $parents = $logAction->getHostId($objectId);
        if (! is_array($parents)) {
            return $objectName;
        }

        if (isset($parents['h'])) {
            $hostIds = explode(',', $parents['h']);
            $hostNames = [];
            foreach ($hostIds as $hostId) {
                $hostName = $logAction->getHostName($hostId);
                if ((int) $hostName !== -1) {
                    $hostNames[] = (string) $hostName;
                }
            }

            if (count($hostNames) === 1) {
                return $hostNames[0] . ' / ' . $objectName;
            }
            if ($hostNames !== []) {
                return '(' . implode(', ', $hostNames) . ') ' . $objectName;
            }
        } elseif (isset($parents['hg'])) {
            $hostGroupIds = explode(',', $parents['hg']);
            $hostGroupNames = [];
            foreach ($hostGroupIds as $hostGroupId) {
                $hostGroupName = $logAction->getHostGroupName($hostGroupId);
                if ((int) $hostGroupName !== -1) {
                    $hostGroupNames[] = (string) $hostGroupName;
                }
            }

            if (count($hostGroupNames) === 1) {
                return $hostGroupNames[0] . ' / ' . $objectName;
            }
            if ($hostGroupNames !== []) {
                return '(' . implode(', ', $hostGroupNames) . ') ' . $objectName;
            }
        }
    } catch (Throwable $e) {
        // Enrichment only — the row is still displayable without its parent.
        // Counted here and logged once after the loop: a systemic failure would
        // otherwise write one identical entry per row of the page.
        $parentFailureCount++;
        $parentFailureCause ??= $e;
    }

    return $objectName;
};

$rows = [];
foreach ($logs as $log) {
    $objectId = (int) $log['object_id'];
    // '#S#' / '#BS#' are the legacy escapes for the '/' and '\' that the
    // service naming convention uses.
    $objectName = str_replace(['#S#', '#BS#'], ['/', '\\'], (string) $log['object_name']);

    if ($log['object_type'] === ActionLog::OBJECT_TYPE_SERVICE) {
        $objectName = $resolveServiceParent($objectId, $objectName);
    }

    $contactId = (int) $log['log_contact_id'];

    $rows[] = [
        'action_log_id' => (int) $log['action_log_id'],
        'object_id' => $objectId,
        'object_type' => (string) $log['object_type'],
        'object_name' => $objectName,
        'date' => (int) $log['action_log_date'],
        // The raw action code: the template owns the translated label and the
        // badge colour (an AJAX endpoint has no gettext locale bound).
        'action_type' => (string) $log['action_type'],
        // Contact id 0 (or NULL) means the change was made by the platform
        // itself; the template turns a missing name into "System"/"unknown".
        'author' => $authors[$contactId] ?? null,
        'author_id' => $contactId,
    ];
}

if ($parentFailureCount > 0) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX changelog listing: could not resolve the parent of some services',
        ['failed_count' => $parentFailureCount, 'exception' => $parentFailureCause]
    );
}

$helper->jsonResponse($rows, $total, $num, $limit);
