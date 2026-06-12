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

use App\AiConfiguration\Infrastructure\ApiPlatform\Resource\AiProvider\AiProviderResource;

final class CreateAiProviderProcessorTest extends AiProviderApiTestCase
{
    public function testUnauthenticatedReturns401(): void
    {
        $this->request('POST', self::ENDPOINT, [
            'json' => [
                'name' => 'Anthropic production',
                'type' => 'anthropic',
                'api_token' => 'a-very-secret-token',
            ],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testUserWithoutPermissionReturns403(): void
    {
        $this->login(self::USER_WITHOUT_RIGHTS);

        $this->request('POST', self::ENDPOINT, [
            'json' => [
                'name' => 'Anthropic production',
                'type' => 'anthropic',
                'api_token' => 'a-very-secret-token',
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCreateAppliesEndpointDefaultAndHidesToken(): void
    {
        $this->login();

        $response = $this->request('POST', self::ENDPOINT, [
            'json' => [
                'name' => 'Anthropic production',
                'type' => 'anthropic',
                'api_token' => 'a-very-secret-token',
                'default_model' => 'claude-sonnet-4-6',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertMatchesResourceItemJsonSchema(AiProviderResource::class);
        self::assertJsonContains([
            'name' => 'Anthropic production',
            'type' => 'anthropic',
            'endpoint_url' => 'https://api.anthropic.com',
            'default_model' => 'claude-sonnet-4-6',
            'is_default' => true, // the first configuration always becomes the default one
        ]);
        self::assertArrayNotHasKey('api_token', $response->toArray());
    }

    public function testCreateWithDuplicateNameReturns409(): void
    {
        $this->login();

        $this->createProvider('Anthropic production');

        $this->request('POST', self::ENDPOINT, [
            'json' => [
                'name' => 'Anthropic production',
                'type' => 'mistral',
                'api_token' => 'another-token',
            ],
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testCreateOpenAiCompatibleWithoutEndpointReturns422(): void
    {
        $this->login();

        $this->request('POST', self::ENDPOINT, [
            'json' => [
                'name' => 'Local Ollama',
                'type' => 'openai_compatible',
                'api_token' => 'a-token',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreateOpenAiCompatibleWithEndpoint(): void
    {
        $this->login();

        $this->request('POST', self::ENDPOINT, [
            'json' => [
                'name' => 'Local Ollama',
                'type' => 'openai_compatible',
                'api_token' => 'a-token',
                'endpoint_url' => 'http://ollama.internal:11434',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['endpoint_url' => 'http://ollama.internal:11434']);
    }

    public function testSecondProviderIsNotDefault(): void
    {
        $this->login();

        $this->createProvider('Anthropic production');

        $this->request('POST', self::ENDPOINT, [
            'json' => [
                'name' => 'Mistral fallback',
                'type' => 'mistral',
                'api_token' => 'a-token',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['is_default' => false]);
    }
}
