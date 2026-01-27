<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\MonitoringConfiguration\Application\Command\CreateServiceCategoryCommand;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategory;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryName;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ServiceCategoryResource;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Bundle\SecurityBundle\Security;
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
        private CommandBus $commandBus,
        #[Autowire(service: ResourceServiceCategoryTransformer::class)]
        private TransformerInterface $transformer,
        private Security $security,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ServiceCategoryResource
    {
        Assert::notNull($data->name);
        Assert::notNull($data->alias);

        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);

        $command = new CreateServiceCategoryCommand(
            name: new ServiceCategoryName($data->name),
            alias: new ServiceCategoryName($data->alias),
            activated: $data->isActivated,
            creatorId: $credentialUser->credential->userId->value,
        );

        $model = $this->commandBus->execute($command);
        Assert::isInstanceOf($model, ServiceCategory::class);

        return $this->transformer->transform($model);
    }
}
