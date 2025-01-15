<?php

/*
 * Copyright 2005-2022 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

require_once __DIR__ . '/webService.class.php';
require_once __DIR__ . '/../../class/centreonDB.class.php';
require_once __DIR__ . '/../../class/centreonContact.class.php';

/**
 * Class
 *
 * @class CentreonTopCounter
 */
class CentreonTopCounter extends CentreonWebService
{
    /** @var CentreonDB */
    protected $pearDBMonitoring;

    /** @var int */
    protected $timeUnit = 60;

    /** @var int */
    protected $refreshTime;

    /** @var bool */
    protected $hasAccessToTopCounter = false;

    /** @var bool */
    protected $hasAccessToPollers = false;

    /** @var bool */
    protected $hasAccessToProfile = false;

    /** @var bool */
    protected $soundNotificationsEnabled = false;

    /** @var Centreon */
    protected $centreon;

    /**
     * CentreonTopCounter constructor
     * @throws Exception
     */
    public function __construct()
    {
        global $centreon;
        $this->centreon = $centreon;

        parent::__construct();
        $this->pearDBMonitoring = new CentreonDB('centstorage');

        // get refresh interval from database
        $this->initRefreshInterval();

        $this->checkAccess();
    }

    /**
     * Get refresh interval of top counter
     *
     * @return void
     * @throws PDOException
     */
    private function initRefreshInterval(): void
    {
        $refreshInterval = 60;

        $query = 'SELECT `value` FROM options WHERE `key` = "AjaxTimeReloadStatistic"';
        $res = $this->pearDB->query($query);
        if ($row = $res->fetch()) {
            $refreshInterval = (int)$row['value'];
        }

        $this->refreshTime = $refreshInterval;
    }

    /**
     * @return void
     */
    private function checkAccess(): void
    {
        if ($this->centreon->user->access->admin == 0) {
            $tabActionACL = $this->centreon->user->access->getActions();
            session_start();
            $_SESSION['centreon'] = $this->centreon;
            session_write_close();
            if (isset($tabActionACL["top_counter"])) {
                $this->hasAccessToTopCounter = true;
            }
            if (isset($tabActionACL["poller_stats"])) {
                $this->hasAccessToPollers = true;
            }
        } else {
            $this->hasAccessToTopCounter = true;
            $this->hasAccessToPollers = true;
        }

        if (
            isset($this->centreon->user->access->topology[50104]) &&
            $this->centreon->user->access->topology[50104] === 1
        ) {
            $this->hasAccessToProfile = true;
        }
    }

    /**
     * The current time of the server
     *
     * Method GET
     *
     * @return array
     */
    public function getClock()
    {
        $locale = $this->centreon->user->lang === 'browser'
            ? null
            : $this->centreon->user->lang;

        return ['time' => time(), 'locale' => $locale, 'timezone' => $this->centreon->CentreonGMT->getActiveTimezone($this->centreon->user->gmt)];
    }

    /**
     * If the user must be disconnected
     *
     * Method GET
     *
     * @return bool[]
     * @throws CentreonDbException
     */
    public function getAutologout()
    {
        $logout = true;
        if (isset($_SESSION['centreon'])) {
            $query = $this->pearDB->prepare('SELECT user_id FROM session WHERE session_id = ?');
            $res = $this->pearDB->execute($query, [session_id()]);
            if ($res->rowCount()) {
                $logout = false;
            }
        }

        return ['autologout' => $logout];
    }

    /**
     * Get the user information
     *
     * Method PUT
     *
     * @return void
     * @throws PDOException
     */
    public function putAutoLoginToken(): void
    {
        $userId = $this->arguments['userId'];
        $autoLoginKey = $this->arguments['token'];

        $query = "UPDATE contact SET contact_autologin_key = :autoKey WHERE contact_id = :userId";
        $stmt = $this->pearDB->prepare($query);
        $stmt->bindParam(':autoKey', $autoLoginKey, PDO::PARAM_STR);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $res = $stmt->execute();

        if (!$res) {
            throw new Exception('Error while update autologinKey ' . $autoLoginKey);
        }

        /**
         * Update user object
         */
        $this->centreon->user->setToken($autoLoginKey);
    }

    /**
     * Get the user information
     *
     * Method GET
     *
     * @return array
     * @throws RestInternalServerErrorException
     * @throws RestUnauthorizedException
     */
    public function getUser()
    {
        $locale = $this->centreon->user->lang === 'browser'
            ? null
            : $this->centreon->user->lang;
        $autoLoginKey = null;

        $this->soundNotificationsEnabled = isset($_SESSION['disable_sound']) ? !$_SESSION['disable_sound'] : true;

        // Is the autologin feature enabled ?
        try {
            $res = $this->pearDB->query(
                'SELECT value FROM options WHERE options.key = "enable_autologin"'
            );
        } catch (Exception $e) {
            throw new RestInternalServerErrorException('Error getting the user.');
        }

        $rowEnableShortcut = $res->fetch();

        // Do we need to display the autologin shortcut ?
        try {
            $res = $this->pearDB->query(
                'SELECT value FROM options WHERE options.key = "display_autologin_shortcut"'
            );
        } catch (Exception $e) {
            throw new RestInternalServerErrorException('Error getting the user.');
        }

        $rowEnableAutoLogin = $res->fetch();

        // If the autologin feature is enabled then fetch the autologin key
        // And display the shortcut if the option is enabled
        if (
            isset($rowEnableAutoLogin['value'])
            && isset($rowEnableShortcut['value'])
            && $rowEnableAutoLogin['value'] === '1'
            && $rowEnableShortcut['value'] === '1'
        ) {
            // Get autologinkey
            try {
                $res = $this->pearDB->prepare(
                    'SELECT contact_autologin_key FROM contact WHERE contact_id = :userId'
                );
                $res->bindValue(':userId', (int) $this->centreon->user->user_id, PDO::PARAM_INT);
                $res->execute();
            } catch (Exception $e) {
                throw new RestInternalServerErrorException('Error getting the user.');
            }

            if ($res->rowCount() === 0) {
                throw new RestUnauthorizedException('User does not exist.');
            }

            $row = $res->fetch();
            $autoLoginKey = $row['contact_autologin_key'] ?? null;
        }

        return [
            'userId' => $this->centreon->user->user_id,
            'fullname' => $this->centreon->user->name,
            'username' => $this->centreon->user->alias,
            'locale' => $locale,
            'timezone' => $this->centreon->CentreonGMT->getActiveTimezone($this->centreon->user->gmt),
            'hasAccessToProfile' => $this->hasAccessToProfile,
            'autologinkey' => $autoLoginKey,
            'soundNotificationsEnabled' => $this->soundNotificationsEnabled,
            'password_remaining_time' => $this->getPasswordRemainingTime(),
        ];
    }

    /**
     * Get password remaining time
     * null : never expired
     * int : number of seconds before expiration
     *
     * @return int|null
     * @throws PDOException
     */
    private function getPasswordRemainingTime(): ?int
    {
        if ($this->centreon->user->authType === CentreonAuth::AUTH_TYPE_LDAP) {
            return null;
        }
        $passwordRemainingTime = null;
        $contact = new CentreonContact($this->pearDB);
        $passwordCreationDate = $contact->findLastPasswordCreationDate((int) $this->centreon->user->user_id);

        if ($passwordCreationDate !== null) {
            $passwordPolicy = $contact->getPasswordSecurityPolicy();
            $expirationDelay = $passwordPolicy['password_expiration']['expiration_delay'];
            $excludedUsers = $passwordPolicy['password_expiration']['excluded_users'];

            if ($expirationDelay !== null && !in_array($this->centreon->user->alias, $excludedUsers)) {
                $passwordRemainingTime = $passwordCreationDate->getTimestamp() + $expirationDelay - time();
                if ($passwordRemainingTime < 0) {
                    $passwordRemainingTime = 0;
                }
            }
        }

        return $passwordRemainingTime;
    }

    /**
     * Get the pollers status
     *
     * Method GET
     *
     * @return array
     * @throws RestInternalServerErrorException
     * @throws RestUnauthorizedException
     */
    public function getPollersStatus()
    {
        if (!$this->hasAccessToPollers) {
            throw new RestUnauthorizedException("You're not authorized to access poller datas");
        }

        $pollers = $this->pollersStatusList();
        $result = ['latency' => ['warning' => 0, 'critical' => 0], 'stability' => ['warning' => 0, 'critical' => 0], 'database' => ['warning' => 0, 'critical' => 0], 'total' => count($pollers), 'refreshTime' => $this->refreshTime];

        foreach ($pollers as $poller) {
            if ($poller['stability'] === 1) {
                $result['stability']['warning']++;
            } elseif ($poller['stability'] === 2) {
                $result['stability']['critical']++;
            }
            if ($poller['database']['state'] === 1) {
                $result['database']['warning']++;
            } elseif ($poller['database']['state'] === 2) {
                $result['database']['critical']++;
            }
            if ($poller['latency']['state'] === 1) {
                $result['latency']['warning']++;
            } elseif ($poller['latency']['state'] === 2) {
                $result['latency']['critical']++;
            }
        }

        return $result;
    }


    /**
     * Get the list of pollers by status type
     *
     * Method GET
     *
     * @return array
     * @throws RestBadRequestException
     * @throws RestInternalServerErrorException
     */
    public function getPollers()
    {
        $listType = ['configuration', 'stability', 'database', 'latency'];
        if (!isset($this->arguments['type']) || !in_array($this->arguments['type'], $listType)) {
            throw new RestBadRequestException('Missing type argument or bad type name.');
        }

        $result = [
            'type' => $this->arguments['type'],
            'pollers' => [],
            'total' => 0,
            'refreshTime' => $this->refreshTime
        ];

        if ($this->arguments['type'] === 'configuration') {
            $pollers = $this->pollersList();
            $changeStateServers = [];
            foreach ($pollers as $poller) {
                $changeStateServers[$poller['id']] = $poller['lastRestart'];
            }
            $changeStateServers = getChangeState($changeStateServers);
            foreach ($pollers as $poller) {
                if ($poller['updated']) {
                    $result['pollers'][] = ['id' => $poller['id'], 'name' => $poller['name'], 'status' => 1, 'information' => ''];
                }
            }
        } else {
            $type = $this->arguments['type'];
            $pollers = $this->pollersStatusList();
            foreach ($pollers as $poller) {
                $state = 0;
                $info = '';
                if ($type === 'stability') {
                    $state = $poller['stability'];
                } else {
                    $state = $poller[$type]['state'];
                    $info = $poller[$type]['time'];
                }
                if ($state > 0) {
                    $result['pollers'][] = ['id' => $poller['id'], 'name' => $poller['name'], 'status' => $state, 'information' => $info];
                }
            }
        }

        $result['total'] = count($pollers);
        return $result;
    }

    /**
     * Get the list of pollers with problems
     *
     * Method GET
     *
     * @return array
     * @throws RestInternalServerErrorException
     * @throws RestUnauthorizedException
     */
    public function getPollersListIssues()
    {
        if (!$this->hasAccessToPollers) {
            throw new RestUnauthorizedException(_("You're not authorized to access poller data"));
        }

        $pollers = $this->pollersStatusList();
        $result = ['issues' => ['latency' => ['warning' => ['poller' => [], 'total' => 0], 'critical' => ['poller' => [], 'total' => 0], 'total' => 0], 'stability' => ['warning' => ['poller' => [], 'total' => 0], 'critical' => ['poller' => [], 'total' => 0], 'total' => 0], 'database' => ['warning' => ['poller' => [], 'total' => 0], 'critical' => ['poller' => [], 'total' => 0], 'total' => 0]], 'total' => count($pollers), 'refreshTime' => $this->refreshTime];
        $staWar = 0;
        $staCri = 0;
        $datWar = 0;
        $datCri = 0;
        $latWar = 0;
        $latCri = 0;

        foreach ($pollers as $poller) {
            //stability
            if ($poller['stability'] === 1) {
                $result['issues']['stability']['warning']['poller'][] = ['id' => $poller['id'], 'name' => $poller['name'], 'since' => ''];
                $staWar++;
            } elseif ($poller['stability'] === 2) {
                $result['issues']['stability']['critical']['poller'][] = ['id' => $poller['id'], 'name' => $poller['name'], 'since' => ''];
                $staCri++;
            }

            //database
            if ($poller['database']['state'] === 1) {
                $result['issues']['database']['warning']['poller'][] = ['id' => $poller['id'], 'name' => $poller['name'], 'since' => $poller['database']['time']];
                $datWar++;
            } elseif ($poller['database']['state'] === 2) {
                $result['issues']['database']['critical']['poller'][] = ['id' => $poller['id'], 'name' => $poller['name'], 'since' => $poller['database']['time']];
                $datCri++;
            }

            //latency
            if ($poller['latency']['state'] === 1) {
                $result['issues']['latency']['warning']['poller'][] = ['id' => $poller['id'], 'name' => $poller['name'], 'since' => $poller['latency']['time']];
                $latWar++;
            } elseif ($poller['latency']['state'] === 2) {
                $result['issues']['latency']['critical']['poller'][] = ['id' => $poller['id'], 'name' => $poller['name'], 'since' => $poller['latency']['time']];
                $latCri++;
            }
        }

        //total and unset empty
        $staTotal = $staWar + $staCri;
        if ($staTotal === 0) {
            unset($result['issues']['stability']);
        } else {
            if ($staWar === 0) {
                unset($result['issues']['stability']['warning']);
                $result['issues']['stability']['critical']['total'] = $staCri;
            } elseif ($staCri === 0) {
                unset($result['issues']['stability']['critical']);
                $result['issues']['stability']['warning']['total'] = $staWar;
            } else {
                $result['issues']['stability']['warning']['total'] = $staWar;
                $result['issues']['stability']['critical']['total'] = $staCri;
            }
            $result['issues']['stability']['total'] = $staTotal;
        }

        $datTotal = $datWar + $datCri;
        if ($datTotal === 0) {
            unset($result['issues']['database']);
        } else {
            if ($datWar === 0) {
                unset($result['issues']['database']['warning']);
                $result['issues']['database']['critical']['total'] = $datCri;
            } elseif ($datCri === 0) {
                unset($result['issues']['database']['critical']);
                $result['issues']['database']['warning']['total'] = $datWar;
            } else {
                $result['issues']['database']['warning']['total'] = $datWar;
                $result['issues']['database']['critical']['total'] = $datCri;
            }
            $result['issues']['database']['total'] = $datTotal;
        }

        $latTotal = $latWar + $latCri;
        if ($latTotal === 0) {
            unset($result['issues']['latency']);
        } else {
            if ($latWar === 0) {
                unset($result['issues']['latency']['warning']);
                $result['issues']['latency']['critical']['total'] = $latCri;
            } elseif ($latCri === 0) {
                unset($result['issues']['latency']['critical']);
                $result['issues']['latency']['warning']['total'] = $latWar;
            } else {
                $result['issues']['latency']['warning']['total'] = $latWar;
                $result['issues']['latency']['critical']['total'] = $latCri;
            }
            $result['issues']['latency']['total'] = $latTotal;
        }

        return $result;
    }

    /**
     * Get the hosts status
     *
     * Method GET
     *
     * @return array|mixed
     * @throws RestInternalServerErrorException
     * @throws RestUnauthorizedException
     */
    public function getHosts_status()
    {
        if (!$this->hasAccessToTopCounter) {
            throw new RestUnauthorizedException("You're not authorized to access resource data");
        }

        if (
            isset($_SESSION['topCounterHostStatus']) &&
            (time() - $this->refreshTime) < $_SESSION['topCounterHostStatus']['time']
        ) {
            return $_SESSION['topCounterHostStatus'];
        }

        $query = 'SELECT 1 AS REALTIME,
            COALESCE(SUM(CASE WHEN h.state = 0 THEN 1 ELSE 0 END), 0) AS up_total,
            COALESCE(SUM(CASE WHEN h.state = 1 THEN 1 ELSE 0 END), 0) AS down_total,
            COALESCE(SUM(CASE WHEN h.state = 2 THEN 1 ELSE 0 END), 0) AS unreachable_total,
            COALESCE(SUM(CASE WHEN h.state = 4 THEN 1 ELSE 0 END), 0) AS pending_total,
            COALESCE(SUM(CASE WHEN h.state = 1 AND (h.acknowledged = 0 AND h.scheduled_downtime_depth = 0)
                THEN 1 ELSE 0 END), 0) AS down_unhandled,
            COALESCE(SUM(CASE WHEN h.state = 2 AND (h.acknowledged = 0 AND h.scheduled_downtime_depth = 0)
                THEN 1 ELSE 0 END), 0) AS unreachable_unhandled
            FROM hosts h, instances i';
        $query .= ' WHERE i.deleted = 0
            AND h.instance_id = i.instance_id
            AND h.enabled = 1
            AND h.name NOT LIKE "\_Module\_%"';

        if (!$this->centreon->user->admin) {
            $query .= ' AND EXISTS (
                SELECT a.host_id FROM centreon_acl a
                  WHERE a.host_id = h.host_id
                    AND a.group_id IN (' . $this->centreon->user->access->getAccessGroupsString() . '))';
        }

        try {
            $res = $this->pearDBMonitoring->query($query);
        } catch (Exception $e) {
            throw new RestInternalServerErrorException($e);
        }

        $row = $res->fetch();

        $result = ['down' => ['total' => $row['down_total'], 'unhandled' => $row['down_unhandled']], 'unreachable' => ['total' => $row['unreachable_total'], 'unhandled' => $row['unreachable_unhandled']], 'ok' => $row['up_total'], 'pending' => $row['pending_total'], 'total' => $row['up_total'] + $row['pending_total'] + $row['down_total'] + $row['unreachable_total'], 'refreshTime' => $this->refreshTime, 'time' => time()];

        CentreonSession::writeSessionClose('topCounterHostStatus', $result);
        return $result;
    }

    /**
     * Get the services status
     *
     * Method GET
     *
     * @return array|mixed
     * @throws RestInternalServerErrorException
     * @throws RestUnauthorizedException
     */
    public function getServicesStatus()
    {
        if (!$this->hasAccessToTopCounter) {
            throw new RestUnauthorizedException("You're not authorized to access resource data");
        }

        if (
            isset($_SESSION['topCounterServiceStatus']) &&
            (time() - $this->refreshTime) < $_SESSION['topCounterServiceStatus']['time']
        ) {
            return $_SESSION['topCounterServiceStatus'];
        }

        $query = 'SELECT 1 AS REALTIME,
            COALESCE(SUM(CASE WHEN s.state = 0 THEN 1 ELSE 0 END), 0) AS ok_total,
            COALESCE(SUM(CASE WHEN s.state = 1 THEN 1 ELSE 0 END), 0) AS warning_total,
            COALESCE(SUM(CASE WHEN s.state = 2 THEN 1 ELSE 0 END), 0) AS critical_total,
            COALESCE(SUM(CASE WHEN s.state = 3 THEN 1 ELSE 0 END), 0) AS unknown_total,
            COALESCE(SUM(CASE WHEN s.state = 4 THEN 1 ELSE 0 END), 0) AS pending_total,
            COALESCE(SUM(CASE WHEN s.state = 1 AND (h.acknowledged = 0 AND h.scheduled_downtime_depth = 0
                AND s.state_type = 1 AND s.acknowledged = 0 AND s.scheduled_downtime_depth = 0)
                THEN 1 ELSE 0 END), 0) AS warning_unhandled,
            COALESCE(SUM(CASE WHEN s.state = 2 AND (h.acknowledged = 0 AND h.scheduled_downtime_depth = 0
                AND s.state_type = 1 AND s.acknowledged = 0 AND s.scheduled_downtime_depth = 0)
                THEN 1 ELSE 0 END), 0) AS critical_unhandled,
            COALESCE(SUM(CASE WHEN s.state = 3 AND (h.acknowledged = 0 AND h.scheduled_downtime_depth = 0
                AND s.state_type = 1 AND s.acknowledged = 0 AND s.scheduled_downtime_depth = 0)
                THEN 1 ELSE 0 END), 0) AS unknown_unhandled
            FROM hosts h, services s, instances i';
        $query .= ' WHERE i.deleted = 0
            AND h.instance_id = i.instance_id
            AND h.enabled = 1
            AND (h.name NOT LIKE "\_Module\_%" OR h.name LIKE "\_Module\_Meta%")
            AND s.enabled = 1
            AND h.host_id = s.host_id';
        if (!$this->centreon->user->admin) {
            $query .= ' AND EXISTS (
                SELECT a.service_id FROM centreon_acl a
                    WHERE a.host_id = h.host_id
                        AND a.service_id = s.service_id
                        AND a.group_id IN (' . $this->centreon->user->access->getAccessGroupsString() . ')
            )';
        }

        try {
            $res = $this->pearDBMonitoring->query($query);
        } catch (Exception $e) {
            throw new RestInternalServerErrorException($e);
        }

        $row = $res->fetch();

        $result = ['critical' => ['total' => $row['critical_total'], 'unhandled' => $row['critical_unhandled']], 'warning' => ['total' => $row['warning_total'], 'unhandled' => $row['warning_unhandled']], 'unknown' => ['total' => $row['unknown_total'], 'unhandled' => $row['unknown_unhandled']], 'ok' => $row['ok_total'], 'pending' => $row['pending_total'], 'total' => $row['ok_total'] + $row['pending_total'] + $row['critical_total'] + $row['unknown_total'] +
            $row['warning_total'], 'refreshTime' => $this->refreshTime, 'time' => time()];

        CentreonSession::writeSessionClose('topCounterServiceStatus', $result);
        return $result;
    }

    /**
     * Get intervals for refreshing header data
     * Method: GET
     *
     * @return array
     * @throws RestInternalServerErrorException
     */
    public function getRefreshIntervals()
    {
        $query = "SELECT * FROM `options` WHERE `key` IN ('AjaxTimeReloadMonitoring','AjaxTimeReloadStatistic')";
        try {
            $res = $this->pearDB->query($query);
        } catch (Exception $e) {
            throw new RestInternalServerErrorException($e);
        }
        $row = $res->fetchAll();

        $result = [];
        foreach ($row as $item) {
            $result[$item['key']] = (intval($item['value']) > 10) ? $item['value'] : 10;
        }

        return $result;
    }

    /**
     * Get the configured pollers
     *
     * @return array
     * @throws RestInternalServerErrorException
     */
    protected function pollersList()
    {
        /* Get the list of configured pollers */
        $listPoller = [];
        $query = 'SELECT id, name, last_restart, updated FROM nagios_server WHERE ns_activate = "1"';

        /* Add ACL */
        $aclPoller = $this->centreon->user->access->getPollerString('id');
        if (!$this->centreon->user->admin) {
            if ($aclPoller === '') {
                return [];
            }
            $query .= ' AND id IN (' . $aclPoller . ')';
        }

        try {
            $res = $this->pearDB->query($query);
        } catch (Exception $e) {
            throw new RestInternalServerErrorException($e);
        }

        if ($res->rowCount() === 0) {
            return [];
        }
        while ($row = $res->fetch()) {
            $listPoller[$row['id']] = ['id' => $row['id'], 'name' => $row['name'], 'lastRestart' => $row['last_restart'], 'updated' => $row['updated']];
        }
        return $listPoller;
    }

    /**
     * Get information for pollers
     *
     * @return array
     * @throws RestInternalServerErrorException
     */
    protected function pollersStatusList()
    {
        $listPoller = [];
        $listConfPoller = $this->pollersList();
        foreach ($listConfPoller as $poller) {
            $listPoller[$poller['id']] = ['id' => $poller['id'], 'name' => $poller['name'], 'stability' => 0, 'database' => ['state' => 0, 'time' => null], 'latency' => ['state' => 0, 'time' => null]];
        }

        /* Get status of pollers */
        $query = 'SELECT 1 AS REALTIME, instance_id, last_alive, running FROM instances
            WHERE deleted = 0 AND instance_id IN (' . implode(', ', array_keys($listPoller)) . ')';

        try {
            $res = $this->pearDBMonitoring->query($query);
        } catch (Exception $e) {
            throw new RestInternalServerErrorException($e);
        }

        while ($row = $res->fetch()) {
            /* Test if poller running and activity */
            if (time() - $row['last_alive'] >= $this->timeUnit * 10) {
                $listPoller[$row['instance_id']]['stability'] = 2;
                $listPoller[$row['instance_id']]['database']['state'] = 2;
                $listPoller[$row['instance_id']]['database']['time'] = time() - $row['last_alive'];
            } elseif (time() - $row['last_alive'] >= $this->timeUnit * 5) {
                $listPoller[$row['instance_id']]['stability'] = 1;
                $listPoller[$row['instance_id']]['database']['state'] = 1;
                $listPoller[$row['instance_id']]['database']['time'] = time() - $row['last_alive'];
            }
            if ($row['running'] == 0) {
                $listPoller[$row['instance_id']]['stability'] = 2;
            }
        }
        /* Get latency */
        $query = 'SELECT 1 AS REALTIME, n.stat_value, i.instance_id
            FROM nagios_stats n, instances i
            WHERE n.stat_label = "Service Check Latency"
                AND n.stat_key = "Average"
                AND n.instance_id = i.instance_id
                AND i.deleted = 0
                AND i.instance_id IN (' . implode(', ', array_keys($listPoller)) . ')';

        try {
            $res = $this->pearDBMonitoring->query($query);
        } catch (Exception $e) {
            throw new RestInternalServerErrorException($e);
        }

        while ($row = $res->fetch()) {
            if ($row['stat_value'] >= 120) {
                $listPoller[$row['instance_id']]['latency']['state'] = 2;
                $listPoller[$row['instance_id']]['latency']['time'] = $row['stat_value'];
            } elseif ($row['stat_value'] >= 60) {
                $listPoller[$row['instance_id']]['latency']['state'] = 1;
                $listPoller[$row['instance_id']]['latency']['time'] = $row['stat_value'];
            }
        }

        return $listPoller;
    }

    /**
     * Authorize to access to the action
     *
     * @param string $action The action name
     * @param CentreonUser $user The current user
     * @param bool $isInternal If the api is call in internal
     * @return bool If the has access to the action
     */
    public function authorize($action, $user, $isInternal = false)
    {
        if (
            parent::authorize($action, $user, $isInternal)
            || ($user && $user->hasAccessRestApiRealtime())
        ) {
            return true;
        }

        return false;
    }
}
