<?php

declare(strict_types=1);

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
 */

namespace Tests\App\ResourceConfiguration\Acceptance;

use App\ResourceConfiguration\Domain\Aggregate\ServiceCategory;
use App\ResourceConfiguration\Domain\Aggregate\ServiceCategoryName;
use App\ResourceConfiguration\Domain\Repository\ServiceCategoryRepository;
use App\ResourceConfiguration\Infrastructure\ApiPlatform\Resource\ServiceCategoryResource;
use Tests\App\ApiTestCase;

final class CreateServiceCategoryTest extends ApiTestCase
{
    public function testCreateServiceCategory(): void
    {
        /** @var ServiceCategoryRepository $repository */
        $repository = static::getContainer()->get(ServiceCategoryRepository::class);

        static::assertNull($repository->findOneByName(new ServiceCategoryName('NAME')));

        $this->login('admin', 'Centreon!2021');

        $this->request('POST', '/api/latest/configuration/services/categories', [
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'name' => 'NAME',
                'alias' => 'ALIAS',
                'is_activated' => false,
            ],
        ]);

        static::assertResponseIsSuccessful();
        static::assertMatchesResourceItemJsonSchema(ServiceCategoryResource::class);
        static::assertJsonContains([
            'name' => 'NAME',
            'alias' => 'ALIAS',
            'is_activated' => false,
        ]);

        $serviceCategory = $repository->findOneByName(new ServiceCategoryName('NAME'));
        static::assertInstanceOf(ServiceCategory::class, $serviceCategory);

        static::assertEquals(new ServiceCategoryName('NAME'), $serviceCategory->name());
    }

    public function testCannotCreateSameServiceCategory(): void
    {
        $this->login('admin', 'Centreon!2021');

        $this->request('POST', '/api/latest/configuration/services/categories', [
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'name' => 'NAME',
                'alias' => 'ALIAS',
                'is_activated' => false,
            ],
        ]);

        static::assertResponseIsSuccessful();

        $this->request('POST', '/api/latest/configuration/services/categories', [
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'name' => 'NAME',
                'alias' => 'ALIAS',
                'is_activated' => false,
            ],
        ]);

        static::assertResponseStatusCodeSame(409);
    }

    public function testCannotCreateServiceCategoryWithInvalidValues(): void
    {
        $this->login('admin', 'Centreon!2021');

        $this->request('POST', '/api/latest/configuration/services/categories', [
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [],
        ]);

        static::assertResponseStatusCodeSame(422);
        static::assertJsonContains([
            'violations' => [
                ['propertyPath' => 'name', 'message' => 'This value should not be null.'],
                ['propertyPath' => 'alias', 'message' => 'This value should not be null.'],
            ],
        ]);
    }

    public function testCannotCreateServiceCategoryIfNotLogged(): void
    {
        $this->request('POST', '/api/latest/configuration/services/categories', [
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'name' => 'NAME',
                'alias' => 'ALIAS',
                'is_activated' => false,
            ],
        ]);

        static::assertResponseStatusCodeSame(401);
    }
}
