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

namespace App\Shared\Infrastructure\ApiPlatform;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\OpenApi;
use App\Shared\Domain\Repository\Pagination;
use App\Shared\Infrastructure\ApiPlatform\State\PaginationResolver;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Documents the pagination query parameters as {@see PaginationResolver} applies them: API Platform
 * documents no minimum for the page, and a fixed default and a minimum of 0 for the page size, while
 * both must be at least 1 and the default page size comes from a platform setting at runtime.
 */
#[AsDecorator('api_platform.openapi.factory')]
final readonly class PaginationOpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(
        private OpenApiFactoryInterface $decorated,
        #[Autowire(param: 'api_platform.collection.pagination.items_per_page')]
        private int $fallbackItemsPerPage,
        #[Autowire(param: 'api_platform.collection.pagination.page_parameter_name')]
        private string $pageParameterName = 'page',
        #[Autowire(param: 'api_platform.collection.pagination.items_per_page_parameter_name')]
        private string $itemsPerPageParameterName = 'itemsPerPage',
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $paths = $openApi->getPaths();
        /** @var array<string, PathItem> $pathItems */
        $pathItems = $paths->getPaths();
        foreach ($pathItems as $path => $pathItem) {
            $operation = $pathItem->getGet();
            if ($operation === null) {
                continue;
            }
            /** @var list<Parameter>|null $parameters */
            $parameters = $operation->getParameters();
            if ($parameters === null) {
                continue;
            }

            $paths->addPath($path, $pathItem->withGet($operation->withParameters(array_map($this->describe(...), $parameters))));
        }

        return $openApi;
    }

    private function describe(Parameter $parameter): Parameter
    {
        if ($parameter->getIn() !== 'query') {
            return $parameter;
        }

        if ($parameter->getName() === $this->pageParameterName) {
            return $parameter->withSchema([...$parameter->getSchema(), 'minimum' => 1]);
        }

        if ($parameter->getName() === $this->itemsPerPageParameterName) {
            $schema = $parameter->getSchema();
            unset($schema['default']);

            return $parameter
                ->withDescription(sprintf(
                    'The number of items per page. Values above %d are capped to %d. Defaults to the "Limit per page" platform setting, or to %d when it is not set or invalid.',
                    Pagination::MAX_ITEMS_PER_PAGE,
                    Pagination::MAX_ITEMS_PER_PAGE,
                    $this->fallbackItemsPerPage,
                ))
                ->withSchema([...$schema, 'minimum' => 1, 'maximum' => Pagination::MAX_ITEMS_PER_PAGE]);
        }

        return $parameter;
    }
}
