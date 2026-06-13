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

/**
 * Only the authorization paths are covered here: a successful lookup downloads
 * the external price catalog, which is exercised by the catalog unit test.
 */
final class FetchModelPricesProcessorTest extends AiProviderApiTestCase
{
    private const PRICES_ENDPOINT = self::ENDPOINT . '/model-prices';

    public function testUnauthenticatedReturns401(): void
    {
        $this->request('POST', self::PRICES_ENDPOINT, [
            'json' => ['default_model' => 'gpt-4o'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testUserWithoutPermissionReturns403(): void
    {
        $this->login(self::USER_WITHOUT_RIGHTS);

        $this->request('POST', self::PRICES_ENDPOINT, [
            'json' => ['default_model' => 'gpt-4o'],
        ]);

        self::assertResponseStatusCodeSame(403);
    }
}
