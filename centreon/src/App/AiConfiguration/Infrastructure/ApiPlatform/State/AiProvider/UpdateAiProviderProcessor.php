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
use App\AiConfiguration\Application\Command\UpdateAiProviderCommand;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderDescription;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Infrastructure\ApiPlatform\AiProvider\BillingInput;
use App\AiConfiguration\Infrastructure\ApiPlatform\Resource\AiProvider\AiProviderResource;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<AiProviderResource, AiProviderResource>
 */
final readonly class UpdateAiProviderProcessor implements ProcessorInterface
{
    /**
     * @param TransformerInterface<AiProvider, AiProviderResource> $transformer
     */
    public function __construct(
        private CommandBus $commandBus,
        #[Autowire(service: ResourceAiProviderTransformer::class)]
        private TransformerInterface $transformer,
        private Security $security,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): AiProviderResource
    {
        Assert::keyExists($uriVariables, 'id');
        Assert::integer($uriVariables['id']);

        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);

        // The endpoint URL of curated providers is fixed: any provided value is ignored.
        $endpointUrl = AiProviderTypeEnum::from($data->type)->defaultEndpointUrl()
            ?? new EndpointUrl($data->endpointUrl);

        $billing = BillingInput::fromFields(
            currency: $data->currency,
            priceDefaultInput: $data->priceDefaultInput,
            priceDefaultOutput: $data->priceDefaultOutput,
            priceFastInput: $data->priceFastInput,
            priceFastOutput: $data->priceFastOutput,
            limitDaily: $data->limitDaily,
            limitMonthly: $data->limitMonthly,
        );

        $command = new UpdateAiProviderCommand(
            id: new AiProviderId($uriVariables['id']),
            name: new AiProviderName(trim($data->name)),
            endpointUrl: $endpointUrl,
            apiToken: $data->apiToken !== null ? new ApiToken($data->apiToken) : null,
            defaultModel: new ModelName($data->defaultModel),
            fastModel: $data->fastModel !== null ? new ModelName($data->fastModel) : null,
            description: $data->description !== null ? new AiProviderDescription($data->description) : null,
            isActivated: $data->isActivated,
            currency: $billing->currency,
            pricing: $billing->pricing,
            limit: $billing->limit,
            updatedBy: $credentialUser->credential->userId->value,
        );

        $aiProvider = $this->commandBus->execute($command);
        Assert::isInstanceOf($aiProvider, AiProvider::class);

        return $this->transformer->transform($aiProvider);
    }
}
