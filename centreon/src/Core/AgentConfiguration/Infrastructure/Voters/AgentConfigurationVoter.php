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
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, mixed>
 */
final class AgentConfigurationVoter extends Voter
{
    use LoggerTrait;
    public const READ_AC = 'read_agent_configuration';
    public const READ_AC_POLLERS = 'read_agent_configuration_pollers';

    public function __construct(
        private readonly ReadAgentConfigurationRepositoryInterface $readRepository,
        private readonly ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository
    ) {
    }

    /**
     * {@inheritDoc}
     */
    protected function supports(string $attribute, $subject): bool
    {
        if ($attribute === self::READ_AC) {
            return $subject === null;
        }

        if ($attribute === self::READ_AC_POLLERS) {
            return is_numeric($subject);
        }

        return false;
    }

    /**
     * {@inheritDoc}
     *
     * @param numeric-string|null $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (! $user instanceof ContactInterface) {
            return false;
        }

        return match ($attribute) {
            self::READ_AC => $this->checkTopologyRole($user),
            self::READ_AC_POLLERS => $this->checkAgentConfigurationPollers($user, (int) $subject),
            default => throw new \LogicException('Action on agent configuration not handled')
        };
    }

    /**
     * Checks if the user has sufficient rights to access agent configurations.
     *
     * @param ContactInterface $user
     *
     * @return bool
     */
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

    /**
     * Checks if the user has the correct access groups for the pollers associated with the given agent configuration.
     *
     * @param ContactInterface $user The user to check
     * @param int $agentConfigurationId The ID of the agent configuration
     *
     * @return bool True if the user has the correct access groups, false otherwise
     */
    private function checkAgentConfigurationPollers(ContactInterface $user, int $agentConfigurationId): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $pollers = $this->readRepository->findPollersByAcId($agentConfigurationId);

        $pollerIds = array_map(
            static fn(Poller $poller): int => $poller->id,
            $pollers
        );
        $validPollerIds = $this->readMonitoringServerRepository->existByAccessGroups(
            $pollerIds,
            $this->readAccessGroupRepository->findByContact($user)
        );
        if ([] === array_diff($pollerIds, $validPollerIds)) {
            return true;
        }

        $this->debug(
            'User does not have the correct access groups for pollers',
            [
                'user_id' => $user->getId(),
                'poller_ids' => array_diff($pollerIds, $validPollerIds),
            ]
        );

        return false;
    }
}
