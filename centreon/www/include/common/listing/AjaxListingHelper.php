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

/**
 * Helper class for AJAX listing endpoints.
 *
 * Provides boilerplate: session validation, parameter parsing,
 * and JSON response helpers.
 *
 * Usage in an AJAX endpoint:
 *
 *   $helper = AjaxListingHelper::boot();
 *   $params = $helper->getParams();
 *   $centreon = $helper->getCentreon();  // may be null
 *   $db = $helper->getDb();
 *   // ... run your query ...
 *   $helper->jsonResponse($rows, $total, $params['num'], $params['limit']);
 *
 * Security contract: boot() validates the session only. Mutating (POST)
 * endpoints MUST additionally call validateCsrfToken() and requireWriteAccess()
 * before performing any write.
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
     * CSRF token minted by validateCsrfToken(), attached to every subsequent
     * response — including error ones. Static because jsonError() is static and
     * is also reached from requireWriteAccess() and from the endpoints' catch
     * blocks, all of which run after the submitted token has been consumed.
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
            // DB outage / broken session backend is diagnosable (was silently
            // swallowed). Throwable so Error types are handled too, not just Exception.
            Logger::create(LogChannelEnum::WEB)->error(
                'AJAX listing: session validation failed',
                ['exception' => $e]
            );
            self::jsonError('Internal error', 500);
        }

        // Session deserialization needs the Centreon class graph. The critical
        // classes are required explicitly (so we don't depend on composer classmap
        // freshness for the bootstrap); any other www/class/ classes referenced by
        // the session object resolve through the Composer autoloader loaded above.
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
            self::jsonError('Forbidden', 403);
        }

        return $this->centreon;
    }

    /**
     * Get the database connection.
     */
    public function getDb(): CentreonDB
    {
        return $this->db;
    }

    /**
     * Check if the current user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->centreon ? (bool) $this->centreon->user->admin : false;
    }

    /**
     * Get the ACL object for the current user.
     */
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
     * @param int $pageId The topology page number (e.g. 60101 for hosts, 60201 for servicegroups)
     */
    public function requireReadAccess(int $pageId): void
    {
        if ($this->isAdmin()) {
            return;
        }
        $acl = $this->getAcl();
        // CentreonACL::page() returns 0 (no access), 1 (read/write) or 2 (read only).
        if (! $acl || $acl->page($pageId) === 0) {
            self::jsonError('Forbidden', 403);
        }
    }

    /**
     * Require write access on a given topology page. Exits 403 if read-only or no access.
     * Admins always pass.
     *
     * @param int $pageId The topology page number (e.g. 60101 for hosts, 60201 for servicegroups)
     */
    public function requireWriteAccess(int $pageId): void
    {
        if ($this->isAdmin()) {
            return;
        }
        $acl = $this->getAcl();
        if (! $acl || $acl->page($pageId) !== 1) {
            self::jsonError('Write access denied', 403);
        }
    }

    /**
     * Validate and consume a CSRF token from POST. Exits 403 on failure.
     * Returns a fresh token for the next request.
     */
    public function validateCsrfToken(): string
    {
        $token = $_POST['centreon_token'] ?? null;

        if ($token === null || ! in_array($token, $_SESSION['x-centreon-token'] ?? [], true)) {
            self::jsonError('Invalid CSRF token', 403);
        }

        $key = array_search($token, $_SESSION['x-centreon-token'], true);
        unset($_SESSION['x-centreon-token'][$key], $_SESSION['x-centreon-token-generated-at'][$token]);

        return self::$rotatedCsrfToken = createCSRFToken();
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
            $storageDb = new CentreonDB('centstorage');

            // Skip when audit logging is disabled in the configuration.
            $auditOpt = $storageDb->fetchOne('SELECT `audit_log_option` FROM `config` LIMIT 1');
            if ($auditOpt != '1') {
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
            // Don't fail the toggle because auditing failed — but record the lost
            // audit entry so the drop is observable (this is a security/compliance
            // trail; a silent empty catch made schema drift / DB errors invisible).
            Logger::create(LogChannelEnum::WEB)->error(
                sprintf('AJAX listing: audit log write failed for %s#%d (%s)', $objectType, $objectId, $actionType),
                ['exception' => $e]
            );
        }
    }

    /**
     * Send a successful JSON listing response and exit.
     */
    public function jsonResponse(array $rows, int $total, int $num, int $limit): void
    {
        // JSON_INVALID_UTF8_SUBSTITUTE: a single non-UTF-8 byte in row data (common
        // in plugin output/aliases) otherwise makes json_encode() return false,
        // echoing an empty body with HTTP 200 — the client then sees "no results".
        $json = json_encode([
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

        echo json_encode($payload);

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
