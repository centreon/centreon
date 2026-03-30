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

/**
 * Helper class for AJAX listing endpoints.
 *
 * Provides boilerplate: session validation, parameter parsing,
 * Centreon autoloader registration, and JSON response helpers.
 *
 * Usage in an AJAX endpoint:
 *
 *   $helper = AjaxListingHelper::boot();
 *   $params = $helper->getParams();
 *   $centreon = $helper->getCentreon();  // may be null
 *   $db = $helper->getDb();
 *   // ... run your query ...
 *   $helper->jsonResponse($rows, $total, $params['num'], $params['limit']);
 */
class AjaxListingHelper
{
    private CentreonDB $db;
    private mixed $centreon;

    private function __construct(CentreonDB $db, mixed $centreon)
    {
        $this->db = $db;
        $this->centreon = $centreon;
    }

    /**
     * Bootstrap the AJAX endpoint: config, session, autoloader, JSON header.
     * Exits with appropriate HTTP error on failure.
     */
    public static function boot(): self
    {
        require_once realpath(__DIR__ . '/../../../../config/centreon.config.php');
        require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
        require_once _CENTREON_PATH_ . '/www/class/centreonSession.class.php';
        require_once _CENTREON_PATH_ . '/www/include/common/common-Func.php';
        require_once _CENTREON_PATH_ . '/www/class/HtmlAnalyzer.php';

        header('Content-Type: application/json');

        session_start();

        $db = new CentreonDB();

        try {
            if (! CentreonSession::checkSession(session_id(), $db)) {
                self::jsonError('Unauthorized', 401);
            }
        } catch (\Exception $e) {
            self::jsonError('Internal error', 500);
        }

        // Register Centreon class autoloader for session deserialization
        require_once _CENTREON_PATH_ . '/www/class/centreon.class.php';
        require_once _CENTREON_PATH_ . '/www/class/centreonACL.class.php';

        spl_autoload_register(function ($sClass): void {
            $fileName = lcfirst($sClass);
            $fileNameType1 = _CENTREON_PATH_ . '/www/class/' . $fileName . '.class.php';
            $fileNameType2 = _CENTREON_PATH_ . '/www/class/' . $fileName . '.php';
            if (file_exists($fileNameType1)) {
                require_once $fileNameType1;
            } elseif (file_exists($fileNameType2)) {
                require_once $fileNameType2;
            }
        });

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
        return [
            'search' => HtmlAnalyzer::sanitizeAndRemoveTags($_GET['search'] ?? ''),
            'num'    => filter_var($_GET['num'] ?? 0, FILTER_VALIDATE_INT) ?: 0,
            'limit'  => filter_var($_GET['limit'] ?? 30, FILTER_VALIDATE_INT) ?: 30,
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

        return createCSRFToken();
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
            $pearDBO = new CentreonDB('centstorage');

            // Check if audit log is enabled
            $optResult = $pearDBO->query("SELECT audit_log_option FROM `config` LIMIT 1");
            $auditOpt = $optResult->fetchColumn();
            if ($auditOpt != '1') {
                return;
            }

            $userId = $this->centreon ? $this->centreon->user->get_id() : 0;

            $stmt = $pearDBO->prepare(
                "INSERT INTO `log_action` (action_log_date, object_type, object_id, object_name, action_type, log_contact_id)"
                . " VALUES (:ts, :obj_type, :obj_id, :obj_name, :action, :uid)"
            );
            $stmt->bindValue(':ts', time(), PDO::PARAM_INT);
            $stmt->bindValue(':obj_type', $objectType, PDO::PARAM_STR);
            $stmt->bindValue(':obj_id', $objectId, PDO::PARAM_INT);
            $stmt->bindValue(':obj_name', $objectName, PDO::PARAM_STR);
            $stmt->bindValue(':action', $actionType, PDO::PARAM_STR);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\Throwable $e) {
            // Silently fail logging — don't break the toggle
        }
    }

    /**
     * Send a successful JSON listing response and exit.
     */
    public function jsonResponse(array $rows, int $total, int $num, int $limit): void
    {
        echo json_encode([
            'rows'           => $rows,
            'total'          => $total,
            'num'            => $num,
            'limit'          => $limit,
            'centreon_token' => createCSRFToken(),
        ]);
        exit;
    }

    /**
     * Send a JSON error response and exit.
     */
    public static function jsonError(string $message, int $httpCode = 400): void
    {
        http_response_code($httpCode);
        echo json_encode(['error' => $message]);
        exit;
    }
}
