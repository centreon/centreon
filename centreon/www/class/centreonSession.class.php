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
 * Class
 *
 * @class CentreonSession
 */
class CentreonSession
{
    /**
     * @param int $flag
     */
    public static function start($flag = 0): void
    {
        session_start();
        if ($flag) {
            session_write_close();
        }
    }

    /**
     * @return void
     */
    public static function stop(): void
    {
        // destroy the session
        session_unset();
        session_destroy();
    }

    /**
     * @return void
     */
    public static function restart(): void
    {
        static::stop();
        self::start();
        // regenerate the session id value
        session_regenerate_id(true);
    }

    /**
     * Write value in php session and close it
     *
     * @param string $key session attribute
     * @param mixed $value session value to save
     */
    public static function writeSessionClose($key, $value): void
    {
        session_start();
        $_SESSION[$key] = $value;
        session_write_close();
    }

    /**
     * @param mixed $registerVar
     */
    public function unregisterVar($registerVar): void
    {
        unset($_SESSION[$registerVar]);
    }

    /**
     * @param mixed $registerVar
     */
    public function registerVar($registerVar): void
    {
        if (! isset($_SESSION[$registerVar])) {
            $_SESSION[$registerVar] = ${$registerVar};
        }
    }

    /**
     * Check user session status
     *
     * @param string $sessionId Session id to check
     * @param CentreonDB $db
     * @throws PDOException
     * @return bool
     */
    public static function checkSession($sessionId, CentreonDB $db): bool
    {
        if (empty($sessionId)) {
            return false;
        }
        $prepare = $db->prepare('SELECT `session_id` FROM session WHERE `session_id` = :session_id');
        $prepare->bindValue(':session_id', $sessionId, PDO::PARAM_STR);
        $prepare->execute();

        return $prepare->fetch(PDO::FETCH_ASSOC) !== false;
    }

    /**
     * Update session to keep alive
     *
     * @param CentreonDB $pearDB
     *
     * @throws PDOException
     * @return bool If the session is updated or not
     */
    public function updateSession($pearDB): bool
    {
        $sessionUpdated = false;

        session_start();
        $sessionId = session_id();

        if (self::checkSession($sessionId, $pearDB)) {
            try {
                $sessionStatement = $pearDB->prepare(
                    'UPDATE `session`
                    SET `last_reload` = :lastReload, `ip_address` = :ipAddress
                    WHERE `session_id` = :sessionId'
                );
                $sessionStatement->bindValue(':lastReload', time(), PDO::PARAM_INT);
                $sessionStatement->bindValue(':ipAddress', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
                $sessionStatement->bindValue(':sessionId', $sessionId, PDO::PARAM_STR);
                $sessionStatement->execute();

                $sessionExpire = 120;
                $optionResult = $pearDB->query(
                    "SELECT `value`
                    FROM `options`
                    WHERE `key` = 'session_expire'"
                );
                if (($option = $optionResult->fetch()) && ! empty($option['value'])) {
                    $sessionExpire = (int) $option['value'];
                }

                $expirationDate = (new Datetime())
                    ->add(new DateInterval('PT' . $sessionExpire . 'M'))
                    ->getTimestamp();
                $tokenStatement = $pearDB->prepare(
                    'UPDATE `security_token`
                    SET `expiration_date` = :expirationDate
                    WHERE `token` = :sessionId'
                );
                $tokenStatement->bindValue(':expirationDate', $expirationDate, PDO::PARAM_INT);
                $tokenStatement->bindValue(':sessionId', $sessionId, PDO::PARAM_STR);
                $tokenStatement->execute();

                $sessionUpdated = true; // return true if session is properly updated
            } catch (PDOException $e) {
                $sessionUpdated = false; // return false if session is not properly updated in database
            }
        } else {
            $sessionUpdated = false; // return false if session does not exist
        }

        return $sessionUpdated;
    }

    /**
     * @param string $sessionId
     * @param CentreonDB $pearDB
     *
     * @throws PDOException
     * @return int|string
     */
    public static function getUser($sessionId, $pearDB)
    {
        $sessionId = str_replace(['_', '%'], ['', ''], $sessionId);
        $DBRESULT = $pearDB->query(
            "SELECT user_id FROM session
                WHERE `session_id` = '" . htmlentities(trim($sessionId), ENT_QUOTES, 'UTF-8') . "'"
        );
        $row = $DBRESULT->fetchRow();
        if (! $row) {
            return 0;
        }

        return $row['user_id'];
    }
}
