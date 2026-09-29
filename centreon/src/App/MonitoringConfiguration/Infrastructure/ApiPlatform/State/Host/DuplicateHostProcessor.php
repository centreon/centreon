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
use ApiPlatform\State\ProcessorInterface;
use App\MonitoringConfiguration\Application\Command\DuplicateHostCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\SecurityBundle\Security;
use Webmozart\Assert\Assert;

/**
 * Unitary duplication: one host by id, no request body. The response carries no content (204); the
 * client refetches the listing. A single call per host, so the front fans a multi-selection out over
 * this route rather than sending a bulk payload.
 *
 * @implements ProcessorInterface<null, null>
 */
final readonly class DuplicateHostProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private Security $security,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);

        Assert::integer($uriVariables['id']);

        $this->commandBus->execute(
            new DuplicateHostCommand(
                hostId: new HostId($uriVariables['id']),
                duplicatedBy: $credentialUser->credential->userId->value,
                viewerId: $credentialUser->credential->hasUnrestrictedResourceAccess()
                    ? null
                    : $credentialUser->credential->userId,
            )
        );

        return null;
    }
}
