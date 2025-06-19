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

namespace App\ResourceConfiguration\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ResourceConfiguration\Application\Command\CreateServiceCateogoryCommand;
use App\ResourceConfiguration\Domain\Aggregate\ServiceCategory;
use App\ResourceConfiguration\Domain\Aggregate\ServiceCategoryName;
use App\ResourceConfiguration\Infrastructure\ApiPlatform\Resource\ServiceCategoryResource;
use App\ResourceConfiguration\Infrastructure\ApiPlatform\Transformer\ServiceCategoryTransformer;
use App\Shared\Application\Command\CommandBusInterface;
use App\Shared\Infrastructure\ApiPlatform\Transformer\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<ServiceCategoryResource, ServiceCategoryResource>
 */
final readonly class CreateServiceCategoryProcessor implements ProcessorInterface
{
    /**
     * @param TransformerInterface<ServiceCategory, ServiceCategoryResource> $transformer
     */
    public function __construct(
        private CommandBusInterface $commandBus,
        #[Autowire(service: ServiceCategoryTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ServiceCategoryResource
    {
        // TODO check for permissions

        Assert::notNull($data->name);
        Assert::notNull($data->alias);

        $command = new CreateServiceCateogoryCommand(
            new ServiceCategoryName($data->name),
            new ServiceCategoryName($data->alias),
            $data->isActivated,
        );

        $model = $this->commandBus->dispatch($command);
        Assert::isInstanceOf($model, ServiceCategory::class);

        return $this->transformer->toResource($model);
    }
}
