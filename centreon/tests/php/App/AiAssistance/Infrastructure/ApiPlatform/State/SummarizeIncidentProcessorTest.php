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

namespace Tests\App\AiAssistance\Infrastructure\ApiPlatform\State;

final class SummarizeIncidentProcessorTest extends AiAssistanceApiTestCase
{
    private const ENDPOINT = '/api/latest/ai/incident-summary';
    private const PAYLOAD = [
        'name' => 'Ping',
        'resource_type' => 'service',
        'status' => 'CRITICAL',
        'information' => 'CRITICAL - Packet loss = 100%',
        'parent_name' => 'db-server-01',
        'since' => '2026-06-11T10:00:00+02:00',
        'is_truncated' => false,
        'events' => [
            [
                'date' => '2026-06-12T08:00:00+02:00',
                'type' => 'status_change',
                'content' => 'Packet loss = 100%',
                'status' => 'CRITICAL',
            ],
        ],
    ];

    public function testUnauthenticatedReturns401(): void
    {
        $this->request('POST', self::ENDPOINT, ['json' => self::PAYLOAD]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testWithoutDefaultProviderReturns409(): void
    {
        $this->login();

        $this->request('POST', self::ENDPOINT, ['json' => self::PAYLOAD]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testUnreachableProviderReturns502(): void
    {
        $this->login();
        $this->createUnreachableDefaultProvider();

        $this->request('POST', self::ENDPOINT, ['json' => self::PAYLOAD]);

        self::assertResponseStatusCodeSame(502);
    }

    public function testWithoutEventsReturns400(): void
    {
        $this->login();

        $payload = self::PAYLOAD;
        $payload['events'] = [];

        $this->request('POST', self::ENDPOINT, ['json' => $payload]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testWithTooManyEventsReturns400(): void
    {
        $this->login();

        $payload = self::PAYLOAD;
        $payload['events'] = array_fill(0, 101, self::PAYLOAD['events'][0]);

        $this->request('POST', self::ENDPOINT, ['json' => $payload]);

        self::assertResponseStatusCodeSame(400);
    }
}
