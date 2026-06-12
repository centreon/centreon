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

final class DeleteAiProviderProcessorTest extends AiProviderApiTestCase
{
    public function testUserWithoutPermissionReturns403(): void
    {
        $this->login();
        $id = $this->createProvider('Anthropic production');
        $this->logout();

        $this->login(self::USER_WITHOUT_RIGHTS);
        $this->request('DELETE', self::ENDPOINT . '/' . $id);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteUnknownProviderReturns404(): void
    {
        $this->login();

        $this->request('DELETE', self::ENDPOINT . '/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteActivatedProviderWithOthersLeavesNoneActivated(): void
    {
        $this->login();

        $activatedId = $this->createProvider('Anthropic production');
        $secondId = $this->createProvider('Mistral fallback', type: 'mistral');

        // deleting the activated provider is allowed: it turns AI features off
        $this->request('DELETE', self::ENDPOINT . '/' . $activatedId);
        self::assertResponseStatusCodeSame(204);

        $response = $this->request('GET', self::ENDPOINT . '/' . $secondId);
        self::assertFalse($response->toArray()['is_activated']);
    }

    public function testDeleteProviders(): void
    {
        $this->login();

        $activatedId = $this->createProvider('Anthropic production');
        $secondId = $this->createProvider('Mistral fallback', type: 'mistral');

        $this->request('DELETE', self::ENDPOINT . '/' . $secondId);
        self::assertResponseStatusCodeSame(204);

        $this->request('DELETE', self::ENDPOINT . '/' . $activatedId);
        self::assertResponseStatusCodeSame(204);

        $this->request('GET', self::ENDPOINT . '/' . $activatedId);
        self::assertResponseStatusCodeSame(404);
    }
}
