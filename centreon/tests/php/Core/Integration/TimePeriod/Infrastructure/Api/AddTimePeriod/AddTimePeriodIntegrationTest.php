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

namespace Tests\Core\Integration\TimePeriod\Infrastructure\Api\AddTimePeriod;

use Tests\Core\Integration\CoreApiTestCase;

/**
 * Integration test for the AddTimePeriod endpoint.
 *
 * Route: POST /api/latest/configuration/timeperiods
 * Controller: Core\TimePeriod\Infrastructure\API\AddTimePeriod\AddTimePeriodController
 *
 * This test also validates that the transaction rollback in CoreApiTestCase works correctly:
 * the time period created in one test must not be visible in subsequent tests.
 */
final class AddTimePeriodIntegrationTest extends CoreApiTestCase
{
    private const TP_NAME = 'integration-test-tp';

    public function testAdminCanCreateTimePeriod(): void
    {
        $this->login('test-tp-admin');

        $response = $this->request('POST', '/api/latest/configuration/timeperiods', [
            'body' => json_encode([
                'name' => self::TP_NAME,
                'alias' => 'Integration test time period',
                'days' => [
                    ['day' => 1, 'time_range' => '00:00-24:00'],
                ],
                'templates' => [],
                'exceptions' => [],
            ]),
        ]);

        $this->assertSame(201, $response->getStatusCode(), $response->getContent());

        // Verify the time period exists in the database within this transaction.
        $stmt = self::$db->prepare('SELECT tp_name FROM timeperiod WHERE tp_name = :name');
        $stmt->execute([':name' => self::TP_NAME]);
        $this->assertSame(self::TP_NAME, $stmt->fetchColumn());
    }

    /**
     * This test runs AFTER testAdminCanCreateTimePeriod.
     * If the rollback works, the time period created above must NOT exist anymore.
     *
     * @depends testAdminCanCreateTimePeriod
     */
    public function testTimePeriodDoesNotPersistAcrossTests(): void
    {
        $stmt = self::$db->prepare('SELECT COUNT(*) FROM timeperiod WHERE tp_name = :name');
        $stmt->execute([':name' => self::TP_NAME]);
        $this->assertSame(0, (int) $stmt->fetchColumn(), 'Time period should have been rolled back');
    }

    protected static function apiUsers(): array
    {
        return [
            ['identifier' => 'test-tp-admin', 'admin' => true],
        ];
    }
}
