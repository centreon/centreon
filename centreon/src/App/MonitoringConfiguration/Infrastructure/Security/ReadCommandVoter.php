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

namespace App\MonitoringConfiguration\Infrastructure\Security;

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Security\CommandActionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\CreateCommandResource;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Webmozart\Assert\Assert;

/**
 * @extends Voter<value-of<CommandActionEnum>, mixed>
 */
final class ReadCommandVoter extends Voter
{
    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return CommandActionEnum::tryFrom($attribute) === CommandActionEnum::Read && $subject instanceof CreateCommandResource;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        Assert::isInstanceOf($subject, CreateCommandResource::class);

        $type = CommandTypeEnum::fromName($subject->type);
        if (! $type instanceof CommandTypeEnum) {
            $vote?->addReason('Invalid command type.');

            return false;
        }

        $permission = Command::getReadPermissionForType($type);

        return $this->security->isGranted($permission->value);
    }
}
