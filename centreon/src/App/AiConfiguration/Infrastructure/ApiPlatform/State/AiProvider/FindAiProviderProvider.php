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
use ApiPlatform\State\ProviderInterface;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Repository\AiProviderRepository;
use App\AiConfiguration\Infrastructure\AiFeatureAvailability;
use App\AiConfiguration\Infrastructure\ApiPlatform\Resource\AiProvider\AiProviderResource;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @implements ProviderInterface<AiProviderResource>
 */
final readonly class FindAiProviderProvider implements ProviderInterface
{
    /**
     * @param TransformerInterface<AiProvider, AiProviderResource> $transformer
     */
    public function __construct(
        #[Autowire(service: ResourceAiProviderTransformer::class)]
        private TransformerInterface $transformer,
        private AiProviderRepository $repository,
        private AiFeatureAvailability $availability,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AiProviderResource
    {
        $this->availability->assertAvailable();

        Assert::integer($uriVariables['id']);

        $aiProvider = $this->repository->getById(new AiProviderId($uriVariables['id']));

        return $this->transformer->transform($aiProvider);
    }
}
