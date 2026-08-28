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
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\Model\ConnectionConfig;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Log\Enum\LogChannelEnum;
use Adaptation\Log\Logger;

/**
 * Helper class for AJAX listing endpoints.
 *
 * Provides boilerplate: session validation, parameter parsing,
 * and JSON response helpers.
 *
 * Usage in an AJAX endpoint:
 *
 *   $helper = AjaxListingHelper::boot();
 *   $helper->requireCentreon();
 *   $helper->requireReadAccess(60101);   // the endpoint's topology page
 *   $params = $helper->getParams();
 *   $db = $helper->getDb();
 *   // ... run your query, ACL-filtered when the objects carry a resource ACL ...
 *   $helper->jsonResponse($rows, $total, $params['num'], $params['limit']);
 *
 * Security contract: boot() validates the session and NOTHING else. These
 * endpoints are addressable directly, so each one owns its own access control:
 *
 * - every endpoint MUST call requireCentreon() first;
 * - a read-only endpoint then calls requireReadAccess($pageId); a mutating one
 *   calls requireWriteAccess($pageId) instead, which subsumes it, plus
 *   validateCsrfToken() before it writes;
 * - page-level access says whether the user may act on this kind of object,
 *   never on which one — an endpoint naming an object MUST additionally scope it
 *   to the caller's resource ACL, as ajaxHostListing/ajaxHostToggle do.
 */
class AjaxListingHelper
{
    /** Hard fallback page size when the configured default can't be read. */
    private const DEFAULT_LIMIT = 30;

    /** Absolute ceiling on the requested page size (crafted-input safety net). */
    private const MAX_LIMIT = 1000;

    private CentreonDB $db;

    private mixed $centreon;

    /** Cached configured default page size (options.maxViewConfiguration). */
    private ?int $defaultLimit = null;

    /**
     * CSRF token minted by validateCsrfToken(), handed back on error responses —
     * see jsonError(). Static because jsonError() is static.
     */
    private static ?string $rotatedCsrfToken = null;

    private function __construct(CentreonDB $db, mixed $centreon)
    {
        $this->db = $db;
        $this->centreon = $centreon;
    }

    /**
     * Bootstrap the AJAX endpoint: config, Composer autoloader, session, JSON header.
     * Exits with appropriate HTTP error on failure.
     */
    public static function boot(): self
    {
        require_once realpath(__DIR__ . '/../../../../config/centreon.config.php');
        // Composer autoloader (PSR-4 for src/, e.g. Adaptation\Log\Logger). This
        // standalone endpoint doesn't go through the full bootstrap, so load it
        // explicitly — otherwise src/ classes used below aren't resolvable.
        require_once realpath(__DIR__ . '/../../../../vendor/autoload.php');
        require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
        require_once _CENTREON_PATH_ . '/www/class/centreonSession.class.php';
        require_once _CENTREON_PATH_ . '/www/include/common/common-Func.php';

        header('Content-Type: application/json');

        session_start();

        $db = new CentreonDB();

        try {
            if (! CentreonSession::checkSession(session_id(), $db)) {
                self::jsonError('Unauthorized', 401);
            }
        } catch (Throwable $e) {
            // Never leak internals to the client, but record the real cause so a
            // DB outage or a broken session backend stays diagnosable.
            Logger::create(LogChannelEnum::WEB)->error(
                'AJAX listing: session validation failed',
                ['exception' => $e]
            );
            self::jsonError('Internal error', 500);
        }

        // Belt and braces on the session objects: deserialization already happened
        // in session_start() above, resolved by the Composer classmap, so these are
        // no-ops on a healthy install — and the guard against an install whose
        // classmap has not been regenerated, where they are what stands between the
        // caller and a __PHP_Incomplete_Class.
        require_once _CENTREON_PATH_ . '/www/class/centreon.class.php';
        require_once _CENTREON_PATH_ . '/www/class/centreonACL.class.php';

        $centreon = $_SESSION['centreon'] ?? null;

        return new self($db, $centreon);
    }

    /**
     * Get sanitized listing parameters from the request.
     *
     * @return array{search: string, num: int, limit: int}
     */
    public function getParams(): array
    {
        // Clamp both bounds: FILTER_VALIDATE_INT accepts negatives and has no
        // ceiling, so an unclamped num/limit would feed a malformed or unbounded
        // LIMIT/OFFSET (SQL error or an unbounded result set) to every caller.
        $defaultLimit = $this->getDefaultLimit();

        $num = filter_var($_GET['num'] ?? 0, FILTER_VALIDATE_INT);
        $num = ($num === false || $num < 0) ? 0 : $num;

        $limit = filter_var($_GET['limit'] ?? $defaultLimit, FILTER_VALIDATE_INT);
        $limit = ($limit === false || $limit < 1) ? $defaultLimit : min($limit, self::MAX_LIMIT);

        return [
            'search' => HtmlSanitizer::createFromString((string) ($_GET['search'] ?? ''))->sanitize()->removeTags()->getString(),
            'num'    => $num,
            'limit'  => $limit,
        ];
    }

    /**
     * Get the Centreon session object (or null if deserialization failed).
     */
    public function getCentreon(): mixed
    {
        return $this->centreon;
    }

    /**
     * Get the Centreon session object, or exit 403 if unavailable.
     */
    public function requireCentreon(): mixed
    {
        if (! $this->centreon) {
            // The first check every endpoint runs, so the likeliest refusal of all —
            // and it says nothing about ACLs, which is exactly what an operator
            // reading "Forbidden" needs to know.
            Logger::create(LogChannelEnum::WEB)->warning(
                'AJAX listing: no Centreon session object, request refused'
            );
            self::jsonError('Forbidden', 403);
        }

        return $this->centreon;
    }

    public function getDb(): CentreonDB
    {
        return $this->db;
    }

    public function isAdmin(): bool
    {
        return $this->centreon ? (bool) $this->centreon->user->admin : false;
    }

    public function getAcl(): ?CentreonACL
    {
        return $this->centreon ? $this->centreon->user->access : null;
    }

    /**
     * Require read access on a given topology page. Exits 403 when the page is
     * outside the user's menu. Admins always pass.
     *
     * These endpoints are reachable directly, unlike the legacy pages they
     * replace, which were only served through main.php and its topology check.
     * Listings whose objects carry no per-object ACL rely on this alone.
     *
     * @param int $pageId The topology page number (e.g. 60101 for hosts, 60203 for service groups)
     */
    public function requireReadAccess(int $pageId): void
    {
        if ($this->isAdmin()) {
            return;
        }
        $acl = $this->getAcl();
        // CentreonACL::page() returns 0 (no access), 1 (read/write) or 2 (read only).
        if (! $acl || $acl->page($pageId) === 0) {
            $this->logAccessDenial('read', $pageId);
            self::jsonError('Forbidden', 403);
        }
    }

    /**
     * Require write access on a given topology page. Exits 403 if read-only or no access.
     * Admins always pass.
     *
     * @param int $pageId The topology page number (e.g. 60101 for hosts, 60203 for service groups)
     */
    public function requireWriteAccess(int $pageId): void
    {
        if ($this->isAdmin()) {
            return;
        }
        $acl = $this->getAcl();
        if (! $acl || $acl->page($pageId) !== 1) {
            $this->logAccessDenial('write', $pageId);
            self::jsonError('Write access denied', 403);
        }
    }

    /**
     * Validate and consume a CSRF token from POST. Exits 403 on failure.
     * Returns a fresh token for the next request.
     */
    public function validateCsrfToken(): string
    {
        // Purge first. Nothing outside these endpoints expires tokens on the AJAX
        // path, so both sites that touch the pool purge before touching it: this
        // one and jsonResponse(). On a POST the two run in the same request, which
        // is harmless — the second finds nothing left to drop. Without either, the
        // pool grows with each refresh tick and the 15-minute lifetime the rest of
        // the application assumes never applies to these endpoints.
        if (isset($_SESSION['x-centreon-token-generated-at'])) {
            purgeOutdatedCSRFTokens();
        }

        $token = $_POST['centreon_token'] ?? null;

        if ($token === null || ! in_array($token, $_SESSION['x-centreon-token'] ?? [], true)) {
            // Info, not warning: a double-click sends the same single-use token twice
            // and lands here, so this fires on ordinary use and cannot carry an alert.
            // What it does give is the pairing with the access denials below.
            Logger::create(LogChannelEnum::WEB)->info(
                'AJAX listing: CSRF token rejected',
                [
                    'userId' => $this->centreon ? (int) $this->centreon->user->get_id() : 0,
                    'submitted' => $token === null ? 'none' : 'unknown-or-consumed',
                ]
            );
            self::jsonError('Invalid CSRF token', 403);
        }

        $key = array_search($token, $_SESSION['x-centreon-token'], true);
        unset($_SESSION['x-centreon-token'][$key], $_SESSION['x-centreon-token-generated-at'][$token]);

        return self::$rotatedCsrfToken = createCSRFToken();
    }

    /**
     * Open a real-time connection that fails by throwing.
     *
     * `new CentreonDB('centstorage')` cannot be caught: under a web SAPI its
     * constructor prints an HTML error page and exits, so a catch around it never
     * runs and the caller emits HTML under a JSON content type. Building the
     * config here and going through the factory turns an unreachable centstorage
     * into a ConnectionException the caller can degrade on.
     *
     * @throws ConnectionException
     *
     * @return CentreonDB
     */
    public static function realtimeConnection(): CentreonDB
    {
        return CentreonDB::connectToCentreonStorageDb(new ConnectionConfig(
            host: hostCentstorage,
            user: user,
            password: password,
            databaseNameConfiguration: dbcstg,
            databaseNameRealTime: dbcstg,
            port: port ?? 3306
        ));
    }

    /**
     * Log a toggle action (enable/disable) to the audit log.
     * Uses direct SQL to avoid dependencies on CentreonLogAction.
     *
     * @param string $objectType Object type (e.g. 'servicegroup', 'host', 'contact')
     * @param int $objectId Object ID
     * @param string $objectName Object name for display
     * @param string $actionType Action type ('enable' or 'disable')
     */
    public function logToggleAction(string $objectType, int $objectId, string $objectName, string $actionType): void
    {
        try {
            $storageDb = self::realtimeConnection();

            // Skip when audit logging is disabled in the configuration. false means
            // the row is missing, which is not the same answer as "disabled" and must
            // not pass for one on a compliance trail.
            $auditOpt = $storageDb->fetchOne('SELECT `audit_log_option` FROM `config` LIMIT 1');
            if ($auditOpt === false) {
                Logger::create(LogChannelEnum::WEB)->error(
                    'AJAX listing: could not read audit_log_option, skipping the audit entry',
                    ['objectType' => $objectType, 'objectId' => $objectId]
                );

                return;
            }
            if ((string) $auditOpt !== '1') {
                return;
            }

            $userId = $this->centreon ? (int) $this->centreon->user->get_id() : 0;

            $storageDb->executeStatement(
                'INSERT INTO `log_action` '
                . '(action_log_date, object_type, object_id, object_name, action_type, log_contact_id) '
                . 'VALUES (:ts, :obj_type, :obj_id, :obj_name, :action, :uid)',
                QueryParameters::create([
                    QueryParameter::int('ts', time()),
                    QueryParameter::string('obj_type', $objectType),
                    QueryParameter::int('obj_id', $objectId),
                    QueryParameter::string('obj_name', $objectName),
                    QueryParameter::string('action', $actionType),
                    QueryParameter::int('uid', $userId),
                ])
            );
        } catch (Throwable $e) {
            // Don't fail the toggle because auditing failed, but record the lost
            // entry so the drop stays observable on a compliance trail.
            Logger::create(LogChannelEnum::WEB)->error(
                sprintf('AJAX listing: audit log write failed for %s#%d (%s)', $objectType, $objectId, $actionType),
                ['exception' => $e]
            );
        }
    }

    /**
     * Send a successful JSON listing response and exit.
     *
     * @param array<string, mixed> $extra Endpoint-specific top-level fields, e.g. a
     *                                    flag saying a decorative data source was
     *                                    unreachable so the client can say so
     */
    public function jsonResponse(array $rows, int $total, int $num, int $limit, array $extra = []): void
    {
        // Purge before minting: a listing on a 15s auto-refresh mints a token per
        // tick. Same reason as validateCsrfToken(), which purges too — see there.
        if (isset($_SESSION['x-centreon-token-generated-at'])) {
            purgeOutdatedCSRFTokens();
        }

        // JSON_INVALID_UTF8_SUBSTITUTE: a single non-UTF-8 byte in row data (common
        // in plugin output/aliases) otherwise makes json_encode() return false,
        // echoing an empty body with HTTP 200 — the client then sees "no results".
        $json = json_encode([
            ...$extra,
            'rows'           => $rows,
            'total'          => $total,
            'num'            => $num,
            'limit'          => $limit,
            'centreon_token' => createCSRFToken(),
        ], JSON_INVALID_UTF8_SUBSTITUTE);

        if ($json === false) {
            Logger::create(LogChannelEnum::WEB)->error(
                'AJAX listing: failed to encode response',
                ['error' => json_last_error_msg()]
            );
            self::jsonError('Encoding error', 500);
        }

        echo $json;

        exit;
    }

    /**
     * Send a JSON error response and exit.
     */
    public static function jsonError(string $message, int $httpCode = 400): void
    {
        http_response_code($httpCode);

        $payload = ['error' => $message];
        // The submitted CSRF token is consumed by validateCsrfToken() before the
        // write is attempted, so a failure here would otherwise leave the client
        // holding a dead token: the next call would 403 on "invalid CSRF token"
        // and mask the real cause. Hand the rotated token back on error too.
        if (self::$rotatedCsrfToken !== null) {
            $payload['centreon_token'] = self::$rotatedCsrfToken;
        }

        echo json_encode($payload, JSON_THROW_ON_ERROR);

        exit;
    }

    /**
     * Escape the LIKE wildcards of a user-supplied search term.
     *
     * Bound parameters keep the query safe from injection, but they do not stop
     * `%`, `_` or `\` from being read as pattern syntax: without this, searching
     * for `foo_bar` also matches `fooXbar`, and a lone `%` matches everything.
     * Backslash first, so the escapes added below are not escaped again.
     */
    public static function escapeLikeWildcards(string $search): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
    }

    /**
     * Build a bound `IN (...)` clause for a list of integer ids, so a set of
     * rows can be fetched in one query instead of one query per row.
     *
     * @param int[] $ids Must not be empty (an empty `IN ()` is a syntax error)
     * @param string $prefix Placeholder prefix, unique within the query
     *
     * @return array{clause: string, parameters: QueryParameter[]}
     */
    public static function buildIntInClause(array $ids, string $prefix): array
    {
        $placeholders = [];
        $parameters   = [];
        foreach (array_values($ids) as $index => $id) {
            $placeholder    = $prefix . $index;
            $placeholders[] = ':' . $placeholder;
            $parameters[]   = QueryParameter::int($placeholder, $id);
        }

        return ['clause' => implode(', ', $placeholders), 'parameters' => $parameters];
    }

    /**
     * Record a refused access. The client tells the user they lack the rights, but
     * nothing tells the administrator: without this line a misconfigured ACL and an
     * attempted privilege escalation are equally invisible on the server side.
     *
     * Public because an endpoint denying a specific object — an id outside the
     * caller's resource ACL — has to report it too, and only the endpoint knows it.
     */
    public function logAccessDenial(string $kind, int $pageId): void
    {
        Logger::create(LogChannelEnum::WEB)->warning(
            sprintf('AJAX listing: %s access denied on page %d', $kind, $pageId),
            ['userId' => $this->centreon ? (int) $this->centreon->user->get_id() : 0]
        );
    }

    /**
     * Configured default page size — the platform-wide setting from
     * `options.maxViewConfiguration` (Administration > Parameters > "Limit per
     * page"). Falls back to DEFAULT_LIMIT when unset/invalid/unreadable. Cached
     * for the lifetime of the request.
     */
    private function getDefaultLimit(): int
    {
        if ($this->defaultLimit !== null) {
            return $this->defaultLimit;
        }

        $limit = self::DEFAULT_LIMIT;
        try {
            // Constant key (no user input) → no bound parameters needed.
            $value = $this->db->fetchOne("SELECT `value` FROM `options` WHERE `key` = 'maxViewConfiguration'");
            if ($value !== false && (int) $value > 0) {
                $limit = min((int) $value, self::MAX_LIMIT);
            }
        } catch (Throwable $e) {
            Logger::create(LogChannelEnum::WEB)->error(
                'AJAX listing: could not read maxViewConfiguration, using fallback limit',
                ['exception' => $e]
            );
        }

        return $this->defaultLimit = $limit;
    }
}
