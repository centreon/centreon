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

use Tests\App\Shared\ApiTestCase;

abstract class AiAssistanceApiTestCase extends ApiTestCase
{
    /**
     * Create a default AI provider configuration pointing at an unreachable
     * endpoint: requests pass the configuration stage and fail at transport.
     */
    final protected function createUnreachableDefaultProvider(): void
    {
        $this->request('POST', '/api/latest/administration/ai-providers', [
            'json' => [
                'name' => 'Unreachable provider',
                'type' => 'openai_compatible',
                'api_token' => 'a-token',
                'endpoint_url' => 'http://127.0.0.1:1',
                'default_model' => 'a-model',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
    }
}
