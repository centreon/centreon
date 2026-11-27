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

namespace Tests\www\class;

use Centreon\Test\Mock\CentreonDB;
use CentreonSession;
use PDOException;
use PHPUnit\Framework\TestCase;

class CentreonSessionTest extends TestCase
{
    private const SESSION_ID = 'keepalivesessionid';
    private const SELECT_SESSION_QUERY = 'SELECT `session_id` FROM session WHERE `session_id` = :session_id';

    // Must match the query string of CentreonSession::updateSession() byte for byte
    private const UPDATE_SESSION_QUERY = 'UPDATE `session`
                    SET `last_reload` = :lastReload, `ip_address` = :ipAddress
                    WHERE `session_id` = :sessionId';

    private CentreonDB $db;

    protected function setUp(): void
    {
        // Let updateSession() start a session although the test runner has already sent output
        ini_set('session.use_cookies', '0');
        ini_set('session.cache_limiter', '');
        session_id(self::SESSION_ID);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->db = new CentreonDB();
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        ini_restore('session.use_cookies');
        ini_restore('session.cache_limiter');
        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testUpdateSessionPropagatesDatabaseErrorOnExistingSession(): void
    {
        $this->db->addResultSet(self::SELECT_SESSION_QUERY, [['session_id' => self::SESSION_ID]]);
        $this->db->addResultSet(
            self::UPDATE_SESSION_QUERY,
            [],
            null,
            static function (): void {
                throw new PDOException(
                    'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock',
                    1213
                );
            }
        );

        $this->expectException(PDOException::class);

        (new CentreonSession())->updateSession($this->db);
    }

    public function testUpdateSessionReturnsFalseWhenSessionDoesNotExist(): void
    {
        $this->db->addResultSet(self::SELECT_SESSION_QUERY, []);

        $this->assertFalse((new CentreonSession())->updateSession($this->db));
    }
}
