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

namespace Tests\App\AiConfiguration\Infrastructure\ApiPlatform\State\AiProvider;

final class DiscoverModelsProcessorTest extends AiProviderApiTestCase
{
    private const DISCOVER_ENDPOINT = self::ENDPOINT . '/discover-models';

    public function testUnauthenticatedReturns401(): void
    {
        $this->request('POST', self::DISCOVER_ENDPOINT, [
            'json' => ['type' => 'anthropic', 'api_token' => 'a-token'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testUserWithoutPermissionReturns403(): void
    {
        $this->login(self::USER_WITHOUT_RIGHTS);

        $this->request('POST', self::DISCOVER_ENDPOINT, [
            'json' => ['type' => 'anthropic', 'api_token' => 'a-token'],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testOpenAiCompatibleWithoutEndpointReturns422(): void
    {
        $this->login();

        $this->request('POST', self::DISCOVER_ENDPOINT, [
            'json' => ['type' => 'openai_compatible', 'api_token' => 'a-token'],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testUnreachableEndpointReturns502(): void
    {
        $this->login();

        // no service listens on port 1: the discovery request can not succeed,
        // and the provider error is surfaced (not swallowed into an empty list)
        $this->request('POST', self::DISCOVER_ENDPOINT, [
            'json' => [
                'type' => 'openai_compatible',
                'api_token' => 'a-token',
                'endpoint_url' => 'http://127.0.0.1:1',
            ],
        ]);

        self::assertResponseStatusCodeSame(502);
    }
}
