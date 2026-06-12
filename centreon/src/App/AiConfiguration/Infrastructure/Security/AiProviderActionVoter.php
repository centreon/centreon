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

namespace App\AiConfiguration\Infrastructure\Security;

use App\AiConfiguration\Domain\Security\AiProviderActionEnum;
use App\AiConfiguration\Domain\Security\AiProviderPermissionEnum;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants AI provider actions based on the feature permissions: reading
 * requires the read or read-write permission, writing requires read-write.
 *
 * @extends Voter<value-of<AiProviderActionEnum>, mixed>
 */
final class AiProviderActionVoter extends Voter
{
    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return AiProviderActionEnum::tryFrom($attribute) !== null;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $action = AiProviderActionEnum::from($attribute);

        if ($action === AiProviderActionEnum::Read
            && $this->security->isGranted(AiProviderPermissionEnum::CanRead->value)
        ) {
            return true;
        }

        return $this->security->isGranted(AiProviderPermissionEnum::CanReadAndWrite->value);
    }
}
