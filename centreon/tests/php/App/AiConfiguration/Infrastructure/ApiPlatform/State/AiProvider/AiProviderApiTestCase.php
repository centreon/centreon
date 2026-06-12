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

use Tests\App\Shared\ApiTestCase;

abstract class AiProviderApiTestCase extends ApiTestCase
{
    protected const ENDPOINT = '/api/latest/administration/ai-providers';
    protected const USER_WITHOUT_RIGHTS = 'ai-provider-user-without-rights';

    protected static function apiUsers(): array
    {
        return [self::USER_WITHOUT_RIGHTS];
    }

    final protected function createProvider(string $name, string $type = 'anthropic'): int
    {
        $response = $this->request('POST', self::ENDPOINT, [
            'json' => [
                'name' => $name,
                'type' => $type,
                'api_token' => 'a-very-secret-token',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        /** @var array{id: int} $content */
        $content = $response->toArray();

        return $content['id'];
    }
}
