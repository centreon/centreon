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

final class CorrelateAlertsProcessorTest extends AiAssistanceApiTestCase
{
    private const ENDPOINT = '/api/latest/ai/alert-correlation';
    private const PAYLOAD = [
        'resources' => [
            [
                'name' => 'Ping',
                'resource_type' => 'service',
                'status' => 'CRITICAL',
                'parent_name' => 'db-server-01',
                'information' => 'CRITICAL - Packet loss = 100%',
            ],
            [
                'name' => 'db-server-01',
                'resource_type' => 'host',
                'status' => 'DOWN',
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

    public function testFewerThanTwoResourcesReturns400(): void
    {
        $this->login();

        $payload = self::PAYLOAD;
        $payload['resources'] = [self::PAYLOAD['resources'][0]];

        $this->request('POST', self::ENDPOINT, ['json' => $payload]);

        self::assertResponseStatusCodeSame(400);
    }
}
