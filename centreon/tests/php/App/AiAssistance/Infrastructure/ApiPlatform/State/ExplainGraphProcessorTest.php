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

final class ExplainGraphProcessorTest extends AiAssistanceApiTestCase
{
    private const ENDPOINT = '/api/latest/ai/graph-explanation';
    private const PAYLOAD = [
        'name' => 'Ping',
        'resource_type' => 'service',
        'parent_name' => 'db-server-01',
        'time_range' => 'the last 24 hours',
        'performance_data' => 'rta=5ms;200;500;0; pl=0%;20;60;0;100',
        'metrics' => [
            [
                'name' => 'Round-Trip-Time Average (ms)',
                'unit' => 'ms',
                'minimum' => 2,
                'maximum' => 12,
                'average' => 6.5,
                'current' => 5,
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

    public function testNoMetricsReturns400(): void
    {
        $this->login();

        $payload = self::PAYLOAD;
        $payload['metrics'] = [];

        $this->request('POST', self::ENDPOINT, ['json' => $payload]);

        self::assertResponseStatusCodeSame(400);
    }
}
