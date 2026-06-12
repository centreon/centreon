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

final class UpdateAiProviderProcessorTest extends AiProviderApiTestCase
{
    public function testUserWithoutPermissionReturns403(): void
    {
        $this->login();
        $id = $this->createProvider('Anthropic production');
        $this->logout();

        $this->login(self::USER_WITHOUT_RIGHTS);
        $this->request('PATCH', self::ENDPOINT . '/' . $id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Renamed'],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testPatchWithoutTokenUpdatesConfiguration(): void
    {
        $this->login();

        $id = $this->createProvider('Anthropic production');

        $response = $this->request('PATCH', self::ENDPOINT . '/' . $id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Anthropic renamed', 'default_model' => 'claude-opus-4-8'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'name' => 'Anthropic renamed',
            'default_model' => 'claude-opus-4-8',
        ]);
        self::assertArrayNotHasKey('api_token', $response->toArray());
    }

    public function testPatchCuratedProviderIgnoresCustomEndpoint(): void
    {
        $this->login();

        $id = $this->createProvider('Anthropic production');

        $this->request('PATCH', self::ENDPOINT . '/' . $id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['endpoint_url' => 'https://llm-gateway.example.com'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'endpoint_url' => 'https://api.anthropic.com',
        ]);
    }

    public function testPatchUnknownProviderReturns404(): void
    {
        $this->login();

        $this->request('PATCH', self::ENDPOINT . '/999999', [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Renamed'],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testPatchToExistingNameReturns409(): void
    {
        $this->login();

        $this->createProvider('Anthropic production');
        $secondId = $this->createProvider('Mistral fallback', type: 'mistral');

        $this->request('PATCH', self::ENDPOINT . '/' . $secondId, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Anthropic production'],
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testPatchDisablesTheActivatedProvider(): void
    {
        $this->login();

        $id = $this->createProvider('Anthropic production');

        $this->request('PATCH', self::ENDPOINT . '/' . $id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['is_activated' => false],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['is_activated' => false]);
    }

    public function testPatchMovesActivationToAnotherProvider(): void
    {
        $this->login();

        $firstId = $this->createProvider('Anthropic production');
        $secondId = $this->createProvider('Mistral fallback', type: 'mistral');

        $this->request('PATCH', self::ENDPOINT . '/' . $secondId, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['is_activated' => true],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['is_activated' => true]);

        $response = $this->request('GET', self::ENDPOINT . '/' . $firstId);
        self::assertFalse($response->toArray()['is_activated']);
    }
}
