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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State;

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ListPluginResource;
use Tests\App\Shared\ApiTestCase;

final class ListPluginsProviderTest extends ApiTestCase
{
    public function testItFindPlugins(): void
    {
        $this->login();

        $response = $this->request('GET', '/api/latest/configuration/plugins');

        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(ListPluginResource::class);
        /** @var array<int, array{name: string}> $members */
        $members = $response->toArray()['member'];
        self::assertContains('urlize', array_column($members, 'name'));
    }
}
