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

final class TestAiProviderProcessorTest extends AiProviderApiTestCase
{
    public function testUnauthenticatedReturns401(): void
    {
        $this->request('POST', self::ENDPOINT . '/1/test', ['json' => []]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testUserWithoutPermissionReturns403(): void
    {
        $this->login();
        $id = $this->createProvider('Anthropic production');
        $this->logout();

        $this->login(self::USER_WITHOUT_RIGHTS);
        $this->request('POST', self::ENDPOINT . '/' . $id . '/test', ['json' => []]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testUnknownProviderReturns404(): void
    {
        $this->login();

        $this->request('POST', self::ENDPOINT . '/999999/test', ['json' => []]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnreachableProviderIsReportedAsFailedTest(): void
    {
        $this->login();

        // no service listens on port 1: the test request can not succeed
        $response = $this->request('POST', self::ENDPOINT, [
            'json' => [
                'name' => 'Unreachable provider',
                'type' => 'openai_compatible',
                'api_token' => 'a-token',
                'endpoint_url' => 'http://127.0.0.1:1',
                'default_model' => 'a-model',
            ],
        ]);
        self::assertResponseStatusCodeSame(201);
        /** @var int $id */
        $id = $response->toArray()['id'];

        $testResponse = $this->request('POST', self::ENDPOINT . '/' . $id . '/test', ['json' => []]);

        self::assertResponseStatusCodeSame(200);
        /** @var array{success: bool, message: string|null} $result */
        $result = $testResponse->toArray();
        self::assertFalse($result['success']);
        self::assertNotEmpty($result['message']);
    }

    public function testProviderWithoutAnyModelIsReportedAsFailedTest(): void
    {
        $this->login();

        // created without a default model: the test can not pick a model
        $id = $this->createProvider('Anthropic production');

        $testResponse = $this->request('POST', self::ENDPOINT . '/' . $id . '/test', ['json' => []]);

        self::assertResponseStatusCodeSame(200);
        /** @var array{success: bool, message: string|null} $result */
        $result = $testResponse->toArray();
        self::assertFalse($result['success']);
        self::assertStringContainsString('model', (string) $result['message']);
    }
}
