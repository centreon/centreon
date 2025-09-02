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

namespace App\ResourceConfiguration\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ResourceConfiguration\Application\Query\ListGlobalMacrosQuery;
use App\ResourceConfiguration\Domain\Collection\GlobalMacroCollection;
use App\ResourceConfiguration\Infrastructure\ApiPlatform\Resource\GlobalMacroResource;
use App\ResourceConfiguration\Infrastructure\ApiPlatform\Resource\GlobalMacroResourceCollection;
use App\ResourceConfiguration\Infrastructure\ApiPlatform\Transformer\GlobalMacroTransformer;
use App\Shared\Application\Query\QueryBus;
use App\Shared\Infrastructure\ApiPlatform\Transformer\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

final readonly class ListGlobalMacrosProvider implements ProviderInterface
{
    public function __construct(
        private QueryBus $queryBus,
        #[Autowire(service: GlobalMacroTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): GlobalMacroResourceCollection
    {
        // TODO: handle request parameters
        $query = new ListGlobalMacrosQuery();

        $collection = $this->queryBus->execute($query);
        Assert::isInstanceOf($collection, GlobalMacroCollection::class);

        $resourceCollection = new GlobalMacroResourceCollection();

        foreach ($collection as $item) {
            $resource = $this->transformer->toResource($item);
            Assert::isInstanceOf($resource, GlobalMacroResource::class);
            $resourceCollection->add($resource);
        }

        return $resourceCollection;
    }
}

