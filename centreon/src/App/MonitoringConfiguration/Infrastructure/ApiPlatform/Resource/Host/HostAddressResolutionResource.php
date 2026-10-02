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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Model\ResolvableAddress;
use App\MonitoringConfiguration\Domain\Security\HostPermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\ResolveHostAddressProvider;
use App\MonitoringConfiguration\Infrastructure\Validator\ValidResolvableAddress;
use App\Shared\Infrastructure\ApiPlatform\Routing\PlatformCondition;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'HostAddressResolution',
    operations: [
        new Get(
            uriTemplate: '/configuration/hosts/_resolve',
            // Legacy only offers the "Resolve" button on-premise: on Cloud the lookup would run against
            // Centreon's own infrastructure rather than the customer's network.
            condition: PlatformCondition::ON_PREMISE_ONLY,
            parameters: [
                'hostname' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'IPv4 address or hostname (RFC 1123) to resolve to an IPv4 address',
                    required: true,
                    constraints: [
                        new Assert\Type('string'),
                        new Assert\NotBlank(normalizer: 'trim'),
                        new Assert\Length(max: ResolvableAddress::MAX_LENGTH),
                        new ValidResolvableAddress(),
                    ],
                ),
            ],
            openapi: new Model\Operation(
                summary: 'Resolve a hostname to an IPv4 address',
                description: 'An IPv4 is returned as is. A hostname that does not resolve to an IPv4 is reported '
                    . 'with "resolved" set to false. Not available on Cloud platforms.',
                responses: [
                    401 => new Model\Response('Authentication required'),
                    404 => new Model\Response('Not available on Cloud platforms'),
                    422 => new Model\Response('Neither an IPv4 address nor a hostname'),
                ],
            ),
            security: "is_granted('" . HostPermissionEnum::CanReadAndWrite->value . "')",
            securityMessage: 'You are not allowed to resolve host addresses',
            provider: ResolveHostAddressProvider::class,
        ),
    ],
)]
final readonly class HostAddressResolutionResource
{
    public function __construct(
        #[ApiProperty(required: true)]
        public string $hostname,
        #[ApiProperty(
            schema: ['type' => 'string', 'description' => 'Absent from the response when "resolved" is false.'],
        )]
        #[SerializedName('ip')]
        public ?string $ipv4,
        #[ApiProperty(required: true)]
        public bool $resolved,
    ) {
    }
}
