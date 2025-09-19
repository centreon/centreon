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

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\CommandResource;
use Symfony\Component\HttpFoundation\Response;
use Tests\App\Shared\ApiTestCase;

final class FindCommandProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/latest/configuration/commands';

    // add tests for the findCommandProvider : testFindCommandProviderOk, testFindCommandProviderNotFound, testFindCommandProviderNoRights
    public function testFindCommandProviderOk(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT . '/1', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(CommandResource::class);
        self::assertJsonContains([
            'id' => 1,
        ]);
    }

    public function testFindCommandProviderNotFound(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT . '/9999', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
