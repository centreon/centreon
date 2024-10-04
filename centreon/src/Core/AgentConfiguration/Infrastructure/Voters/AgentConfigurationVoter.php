<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\AgentConfiguration\Infrastructure\Voters;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, mixed>
 */
final class AgentConfigurationVoter extends Voter
{
    use LoggerTrait;

    public const READ_AC = 'read_agent_configuration';

    /**
     * {@inheritDoc}
     */
    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::READ_AC], true);
    }

    /**
     * {@inheritDoc}
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (! $user instanceof ContactInterface) {
            return false;
        }

        return match ($attribute) {
            self::READ_AC => $this->checkTopologyRole($user),
            default => throw new \LogicException('Action on agent configuration not handled')
        };
    }

    private function checkTopologyRole(ContactInterface $user): bool
    {
        if ($user->hasTopologyRole(Contact::ROLE_CONFIGURATION_POLLERS_AGENT_CONFIGURATIONS_RW)) {
            return true;
        }

        $this->error(
            "User doesn't have sufficient rights to access agent configurations",
            ['user_id' => $user->getId()]
        );

        return false;
    }
}