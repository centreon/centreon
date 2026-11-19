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

namespace Tests\App\Shared\Infrastructure\ApiPlatform;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\OpenApi;
use App\Shared\Infrastructure\ApiPlatform\PaginationOpenApiFactory;
use PHPUnit\Framework\TestCase;

final class PaginationOpenApiFactoryTest extends TestCase
{
    public function testItDescribesTheRuntimeDefaultPageSize(): void
    {
        $itemsPerPage = $this->parameter('/configuration/hosts', 'itemsPerPage');

        self::assertSame(
            'The number of items per page. Values above 100 are capped to 100. Defaults to the "Limit per page" platform setting, or to 30 when it is not set or invalid.',
            $itemsPerPage->getDescription()
        );
        self::assertSame(['type' => 'integer', 'minimum' => 1, 'maximum' => 100], $itemsPerPage->getSchema());
    }

    public function testItSetsTheMinimumPage(): void
    {
        self::assertSame(
            ['type' => 'integer', 'default' => 1, 'minimum' => 1],
            $this->parameter('/configuration/hosts', 'page')->getSchema()
        );
    }

    public function testItLeavesTheOtherParametersUntouched(): void
    {
        self::assertSame(['type' => 'string'], $this->parameter('/configuration/hosts', 'name[lk]')->getSchema());
    }

    public function testItLeavesAPathWithoutGetUntouched(): void
    {
        $pathItem = $this->decorate()->getPaths()->getPath('/configuration/hosts/{id}');

        self::assertNotNull($pathItem);
        self::assertNull($pathItem->getGet());
        self::assertNotNull($pathItem->getDelete());
    }

    private function parameter(string $path, string $name): Parameter
    {
        foreach ($this->decorate()->getPaths()->getPath($path)?->getGet()?->getParameters() ?? [] as $parameter) {
            if ($parameter->getName() === $name) {
                return $parameter;
            }
        }

        self::fail(sprintf('No "%s" parameter on GET %s.', $name, $path));
    }

    private function decorate(): OpenApi
    {
        $paths = new Paths();
        $paths->addPath('/configuration/hosts', (new PathItem())->withGet(new Operation(parameters: [
            new Parameter('page', 'query', 'The collection page number', schema: ['type' => 'integer', 'default' => 1]),
            new Parameter('itemsPerPage', 'query', 'The number of items per page', schema: ['type' => 'integer', 'default' => 30, 'minimum' => 0, 'maximum' => 100]),
            new Parameter('name[lk]', 'query', schema: ['type' => 'string']),
        ])));
        $paths->addPath('/configuration/hosts/{id}', (new PathItem())->withDelete(new Operation()));

        $inner = new class (new OpenApi(new Info('Centreon', '1'), [], $paths)) implements OpenApiFactoryInterface {
            public function __construct(private readonly OpenApi $openApi)
            {
            }

            public function __invoke(array $context = []): OpenApi
            {
                return $this->openApi;
            }
        };

        return (new PaginationOpenApiFactory($inner, fallbackItemsPerPage: 30))();
    }
}
