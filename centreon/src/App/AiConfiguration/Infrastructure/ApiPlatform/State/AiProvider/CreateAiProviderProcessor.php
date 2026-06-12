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

namespace App\AiConfiguration\Infrastructure\ApiPlatform\State\AiProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\AiConfiguration\Application\Command\CreateAiProviderCommand;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderDescription;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Infrastructure\AiFeatureAvailability;
use App\AiConfiguration\Infrastructure\ApiPlatform\Resource\AiProvider\AiProviderResource;
use App\AiConfiguration\Infrastructure\ApiPlatform\Resource\AiProvider\CreateAiProviderResource;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<CreateAiProviderResource, AiProviderResource>
 */
final readonly class CreateAiProviderProcessor implements ProcessorInterface
{
    /**
     * @param TransformerInterface<AiProvider, AiProviderResource> $transformer
     */
    public function __construct(
        private CommandBus $commandBus,
        #[Autowire(service: ResourceAiProviderTransformer::class)]
        private TransformerInterface $transformer,
        private Security $security,
        private AiFeatureAvailability $availability,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): AiProviderResource
    {
        $this->availability->assertAvailable();

        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);

        $type = AiProviderTypeEnum::from($data->type);

        $endpointUrl = $data->endpointUrl !== null
            ? new EndpointUrl($data->endpointUrl)
            : $type->defaultEndpointUrl()
                ?? throw new UnprocessableEntityHttpException(
                    sprintf("An endpoint URL is required for providers of type '%s'.", $type->value)
                );

        $command = new CreateAiProviderCommand(
            name: new AiProviderName(trim($data->name)),
            type: $type,
            apiToken: new ApiToken($data->apiToken),
            endpointUrl: $endpointUrl,
            defaultModel: $data->defaultModel !== null ? new ModelName($data->defaultModel) : null,
            description: $data->description !== null ? new AiProviderDescription($data->description) : null,
            isDefault: $data->isDefault,
            creatorId: $credentialUser->credential->userId->value,
        );

        $aiProvider = $this->commandBus->execute($command);
        Assert::isInstanceOf($aiProvider, AiProvider::class);

        return $this->transformer->transform($aiProvider);
    }
}
