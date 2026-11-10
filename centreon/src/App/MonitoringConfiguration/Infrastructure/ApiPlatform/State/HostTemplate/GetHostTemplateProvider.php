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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateResource;
use App\Security\Infrastructure\Security\CredentialUser;
use Symfony\Bundle\SecurityBundle\Security;
use Webmozart\Assert\Assert;

/**
 * Templates are not ACL-scoped, active or not: the page permission alone gates access.
 *
 * @implements ProviderInterface<HostTemplateResource>
 */
final readonly class GetHostTemplateProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private HostTemplateRepository $repository,
        private HostTemplateResourceBuilder $resourceBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): HostTemplateResource
    {
        Assert::integer($uriVariables['id']);
        $templateId = new HostTemplateId($uriVariables['id']);

        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);

        $template = $this->repository->findOne($templateId);
        if (! $template instanceof HostTemplate) {
            throw new HostTemplateNotFoundException([$templateId->value], 'id');
        }

        return $this->resourceBuilder->build($template, $credentialUser->credential->actingUser());
    }
}
