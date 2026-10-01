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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Model\ResolvableAddress;
use App\MonitoringConfiguration\Domain\Service\HostAddressResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostAddressResolutionResource;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

/**
 * @implements ProviderInterface<HostAddressResolutionResource>
 */
final readonly class ResolveHostAddressProvider implements ProviderInterface
{
    public function __construct(
        private HostAddressResolver $hostAddressResolver,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): HostAddressResolutionResource
    {
        // Legacy only offers the "Resolve" button on-premise: on Cloud the lookup would run against
        // Centreon's own infrastructure rather than the customer's network.
        if ($this->isCloudPlatform) {
            throw new NotFoundHttpException('Host address resolution is not available on Cloud platforms.');
        }

        // Guaranteed by the parameter constraints, which run before the provider.
        $hostname = $operation->getParameters()?->get('hostname')?->getValue();
        Assert::string($hostname);

        $resolution = $this->hostAddressResolver->resolve(new ResolvableAddress($hostname));

        return new HostAddressResolutionResource(
            hostname: $resolution->address->value,
            ipv4: $resolution->ipv4,
            resolved: $resolution->ipv4 !== null,
        );
    }
}
