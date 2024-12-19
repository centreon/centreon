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

namespace Core\Application\Configuration\NotificationPolicy\UseCase;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Engine\EngineConfiguration;
use Centreon\Domain\Engine\Interfaces\EngineConfigurationServiceInterface;
use Centreon\Domain\HostConfiguration\Host;
use Centreon\Domain\HostConfiguration\Interfaces\HostConfigurationRepositoryInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Option\OptionService;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Configuration\Notification\Repository\ReadHostNotificationRepositoryInterface;
use Core\Application\RealTime\Repository\ReadHostRepositoryInterface as ReadRealTimeHostRepositoryInterface;
use Core\Domain\Configuration\Notification\Model\NotifiedContact;
use Core\Domain\Configuration\Notification\Model\NotifiedContactGroup;
use Core\Domain\RealTime\Model\Host as RealtimeHost;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

class FindHostNotificationPolicy
{
    use LoggerTrait;

    /**
     * @param ReadHostNotificationRepositoryInterface $readHostNotificationRepository
     * @param HostConfigurationRepositoryInterface $hostRepository
     * @param EngineConfigurationServiceInterface $engineService
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param ContactInterface $contact
     * @param ReadRealTimeHostRepositoryInterface $readRealTimeHostRepository
     */
    public function __construct(
        private ReadHostNotificationRepositoryInterface $readHostNotificationRepository,
        private HostConfigurationRepositoryInterface $hostRepository,
        private EngineConfigurationServiceInterface $engineService,
        private ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private ContactInterface $contact,
        private ReadRealTimeHostRepositoryInterface $readRealTimeHostRepository,
        private readonly OptionService $optionService,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ReadHostRepositoryInterface $readHostRepository,
    ) {
    }

    /**
     * @param int $hostId
     * @param FindNotificationPolicyPresenterInterface $presenter
     */
    public function __invoke(
        int $hostId,
        FindNotificationPolicyPresenterInterface $presenter,
    ): void {
        $this->info('Searching for host notification policy', ['id' => $hostId]);

        $host = $this->findHost($hostId);
        if ($host === null) {
            $this->handleHostNotFound($hostId, $presenter);

            return;
        }

        $inheritanceMode = $this->optionService->findSelectedOptions(['inheritance_mode']);
            $inheritanceMode = isset($inheritanceMode[0])
            ? (int) $inheritanceMode[0]->getValue()
            : 0;

        [$notifiedContactsIds, $notifiedContactGroupsIds] = $this->getNotifiedContactsAndContactGroupsIds($hostId, $inheritanceMode);

        if (! $this->contact->isAdmin()) {
            $accessGroups = $this->accessGroupRepository->findByContact($this->contact);
            $notifiedContacts = $this->readHostNotificationRepository->findNotifiedContactsByIdAndAccessGroups(
                $hostId,
                $notifiedContactsIds,
                $notifiedContactGroupsIds,
                $accessGroups
            );
            $notifiedContactGroups = $this->readHostNotificationRepository->findNotifiedContactGroupsByIdAndAccessGroups(
                $hostId,
                $notifiedContactsIds,
                $notifiedContactGroupsIds,
                $accessGroups
            );
        } else {
            $notifiedContacts = $this->readHostNotificationRepository->findNotifiedContactsById(
                $hostId,
                $notifiedContactsIds,
                $notifiedContactGroupsIds
            );
            $notifiedContactGroups = $this->readHostNotificationRepository->findNotifiedContactGroupsById(
                $hostId,
                $notifiedContactsIds,
                $notifiedContactGroupsIds
            );
        }

        $realtimeHost = $this->readRealTimeHostRepository->findHostById($hostId);
        if ($realtimeHost === null) {
            $this->handleHostNotFound($hostId, $presenter);

            return;
        }

        $engineConfiguration = $this->engineService->findEngineConfigurationByHost($host);
        if ($engineConfiguration === null) {
            $this->handleEngineHostConfigurationNotFound($hostId, $presenter);

            return;
        }
        $this->overrideHostNotificationByEngineConfiguration($engineConfiguration, $realtimeHost);

        $presenter->present(
            $this->createResponse(
                $notifiedContacts,
                $notifiedContactGroups,
                $realtimeHost->isNotificationEnabled(),
            )
        );
    }

    /**
     * @param NotifiedContact[] $notifiedContacts
     * @param NotifiedContactGroup[] $notifiedContactGroups
     * @param bool $isNotificationEnabled
     *
     * @return FindNotificationPolicyResponse
     */
    public function createResponse(
        array $notifiedContacts,
        array $notifiedContactGroups,
        bool $isNotificationEnabled,
    ): FindNotificationPolicyResponse {
        return new FindNotificationPolicyResponse(
            $notifiedContacts,
            $notifiedContactGroups,
            $isNotificationEnabled,
        );
    }

    /**
     * Find host by id.
     *
     * @param int $hostId
     *
     * @return Host|null
     */
    private function findHost(int $hostId): ?Host
    {
        $this->info('Searching for host configuration', ['id' => $hostId]);

        $host = null;

        if ($this->contact->isAdmin()) {
            $host = $this->hostRepository->findHost($hostId);
        } else {
            $accessGroups = $this->accessGroupRepository->findByContact($this->contact);
            $accessGroupIds = array_map(
                fn($accessGroup) => $accessGroup->getId(),
                $accessGroups
            );

            if ($this->readRealTimeHostRepository->isAllowedToFindHostByAccessGroupIds($hostId, $accessGroupIds)) {
                $host = $this->hostRepository->findHost($hostId);
            }
        }

        return $host;
    }

    /**
     * @param int $hostId
     * @param FindNotificationPolicyPresenterInterface $presenter
     */
    private function handleHostNotFound(
        int $hostId,
        FindNotificationPolicyPresenterInterface $presenter,
    ): void {
        $this->error(
            'Host not found',
            [
                'id' => $hostId,
                'userId' => $this->contact->getId(),
            ]
        );
        $presenter->setResponseStatus(new NotFoundResponse('Host'));
    }

    /**
     * @param int $hostId
     * @param FindNotificationPolicyPresenterInterface $presenter
     */
    private function handleEngineHostConfigurationNotFound(
        int $hostId,
        FindNotificationPolicyPresenterInterface $presenter,
    ): void {
        $this->error(
            'Engine configuration not found for Host',
            [
                'id' => $hostId,
                'userId' => $this->contact->getId(),
            ]
        );
        $presenter->setResponseStatus(new NotFoundResponse('Engine configuration'));
    }

    /**
     * If engine configuration related to the host has notification disabled,
     * it overrides host notification status.
     *
     * @param EngineConfiguration $engineConfiguration
     * @param RealtimeHost $realtimeHost
     */
    private function overrideHostNotificationByEngineConfiguration(
        EngineConfiguration $engineConfiguration,
        RealtimeHost $realtimeHost,
    ): void {
        if (
            $engineConfiguration->getNotificationsEnabledOption()
                === EngineConfiguration::NOTIFICATIONS_OPTION_DISABLED
        ) {
            $realtimeHost->setNotificationEnabled(false);
        }
    }

    /**
     * Returns contacts and ContactGroups Ids related to a host by inheritance mode.
     *
     * @param int $hostId
     * @param int $inheritanceMode
     *
     * @return array
     */
    private function getNotifiedContactsAndContactGroupsIds(int $hostId, int $inheritanceMode): array
    {
        $host = $this->readHostRepository->findById($hostId);

        // check if notifications are enabled for host
        if ($host->getNotificationEnabled() !== null && $host->getNotificationEnabled() === 0) {
            return ['contact' => [], 'cg' => []];
        }

        $hostContacts = $this->readHostNotificationRepository->findContactsForHostAndHostTemplate($hostId);
        $hostContactGroups = $this->readHostNotificationRepository->findContactGroupsForHostAndHostTemplate($hostId);
        $hostTemplates = $this->readHostNotificationRepository->findHostTemplates($hostId);
        $parents = $this->findHostTemplatesParents($hostTemplates);

        switch ($inheritanceMode) {
            case 3:
                [$notifiedContacts, $notifiedContactGroups] = $this->cumulativeInheritance($hostTemplates, $parents);
                $hostContacts = array_unique(
                    array_merge($hostContacts, $notifiedContacts),
                    SORT_NUMERIC
                );
                $hostContactGroups = array_unique(
                    array_merge($hostContactGroups, $notifiedContactGroups),
                    SORT_NUMERIC
                );
                break;
            case 2:
                if (count($hostContacts) === 0) {
                    $hostContacts = array_unique(
                        array_merge($hostContacts, $this->closeInheritance($hostTemplates, $parents, 'contact')),
                        SORT_NUMERIC
                    );
                }
                if (count($hostContactGroups) === 0) {
                    $hostContactGroups = array_unique(
                        array_merge($hostContactGroups, $this->closeInheritance($hostTemplates, $parents, 'cg')),
                        SORT_NUMERIC
                    );
                }
                break;
            default:
                if ($host->addInheritedContact()) {
                    $hostContacts = array_unique(
                        array_merge($hostContacts, $this->verticalInheritance($hostTemplates, $parents, 'contact')),
                        SORT_NUMERIC
                    );
                }
                if ($host->addInheritedContactGroup()) {
                    $hostContactGroups = array_unique(
                        array_merge($hostContactGroups, $this->verticalInheritance($hostTemplates, $parents, 'cg')),
                        SORT_NUMERIC
                    );
                }
                break;
        }

        return [$hostContacts, $hostContactGroups];
    }

    /**
     * Returns contacts and contact groups ids by cumulative inheritance.
     *
     * @param array $hostTemplates
     * @param array $parents
     *
     * @return array
     */
    private function cumulativeInheritance(array $hostTemplates, array $parents): array
    {
        $notifiedContacts = [];
        $notifiedContactGroups = [];

        foreach ($hostTemplates as $hostTemplateId) {
            $stack = [$hostTemplateId];
            $processed = [];

            $contactGroups = [];
            $contacts = [];

            while ($currentTemplateId = array_shift($stack)) {
                // skip if already processed
                if (isset($processed[$currentTemplateId])) {
                    continue;
                }

                $processed[$currentTemplateId] = true;

                // check if notifications are enabled for hostTemplate
                $hostTemplateData = $this->readHostTemplateRepository->findById($currentTemplateId);
                if ($hostTemplateData->getNotificationEnabled() === 0) { // check the YesNoDefault return type
                    continue;
                }

                $hostTemplateContactGroups = $this->readHostNotificationRepository->findContactGroupsForHostAndHostTemplate($currentTemplateId);
                $contactGroups = array_merge($contactGroups, $hostTemplateContactGroups);

                $hostTemplateContacts = $this->readHostNotificationRepository->findContactsForHostAndHostTemplate($currentTemplateId);
                $contacts = array_merge($contacts, $hostTemplateContacts);

                $stack = array_merge($parents[$currentTemplateId]['htpl'], $stack);
            }

            $notifiedContacts = array_unique(array_merge($notifiedContacts, $contacts), SORT_NUMERIC);
            $notifiedContactGroups = array_unique(array_merge($notifiedContactGroups, array_unique($contactGroups)), SORT_NUMERIC);
        }

        return [$notifiedContacts, $notifiedContactGroups];
    }

    /**
     * Returns contacts or contact groups ids by close inheritance.
     *
     * @param array $hostTemplates
     * @param array $parents
     * @param string $type contact|cg
     *
     * @return array
     */
    private function closeInheritance(array $hostTemplates, array $parents, string $type)
    {
        foreach ($hostTemplates as $hostTemplateId) {
            $stack = [$hostTemplateId];
            $loopedTemplates = [];

            while ($currentTemplateId = array_shift($stack)) {
                // skip if already processed
                if (isset($loopedTemplates[$currentTemplateId])) {
                    continue;
                }
                $loopedTemplates[$currentTemplateId] = true;

                // check if notifications are enabled for hostTemplate
                $hostTemplateData = $this->readHostTemplateRepository->findById($currentTemplateId);
                if ($hostTemplateData->getNotificationEnabled() === 0) { // check the YesNoDefault return type
                    continue;
                }

                $values = $type === 'contact'
                ? $this->readHostNotificationRepository->findContactsForHostAndHostTemplate($currentTemplateId)
                : $this->readHostNotificationRepository->findContactGroupsForHostAndHostTemplate($currentTemplateId);

                if (count($values) > 0) {
                    return $values;
                }

                $stack = array_merge($parents[$currentTemplateId]['htpl'], $stack);
            }
        }

        return [];
    }

    /**
     * Returns contacts or contact groups ids by vertical inheritance.
     *
     * @param array $hostTemplates
     * @param array $parents
     * @param string $type contact|cg
     *
     * @return array
     */
    private function verticalInheritance(array $hostTemplates, array $parents, string $type): array
    {
        foreach ($hostTemplates as $hostTemplateId) {
            $computed = [];
            $stack = [[$hostTemplateId, 1]];
            $loopedTemplates = [];
            $currentLevelCatch = null;

            while([$currentTemplateId, $level] = array_shift($stack)) {
                if ($currentLevelCatch !== null && $currentLevelCatch >= $level) {
                    break;
                }
                // skip if template already processed
                if (isset($loopedTemplates[$currentTemplateId])) {
                    continue;
                }
                $loopedTemplates[$currentTemplateId] = true;

                // check if notifications are enabled for hostTemplate
                $hostTemplateData = $this->readHostTemplateRepository->findById($currentTemplateId);
                if ($hostTemplateData->getNotificationEnabled() === 0) { //check the YesNoDefault return type
                    continue;
                }

                [$values, $additive] = $type === 'contact'
                ? [$this->readHostNotificationRepository->findContactsForHostAndHostTemplate($currentTemplateId), $hostTemplateData->addInheritedContact()]
                : [$this->readHostNotificationRepository->findContactGroupsForHostAndHostTemplate($currentTemplateId), $hostTemplateData->addInheritedContactGroup()];

                if (count($values) > 0) {
                    $computed = array_merge($computed, $values);
                    $currentLevelCatch = $level;

                    if ($additive === null || ! $additive) {
                        break;
                    }
                }

                foreach (array_reverse($parents[$currentTemplateId]['htpl']) as $parent) {
                    array_unshift($stack, [$parent, $level + 1]);
                }
            }

            if (count(value: $computed) > 0) {
                return array_unique($computed, SORT_NUMERIC);
            }
        }

        return [];
    }

    /**
     * Find host templates recursive parents.
     *
     * @param array $hostTemplates
     *
     * @return array
     */
    private function findHostTemplatesParents(array $hostTemplates): array
    {
        $stack = $hostTemplates;
        $processed = [];
        while(($hostTemplateId = array_shift($stack))) {
            if (isset($processed[$hostTemplateId])) {
                continue;
            }
            $processed[$hostTemplateId] = true;
            $parents[$hostTemplateId]['htpl'] = $this->readHostNotificationRepository->findHostTemplates($hostTemplateId);
            $stack = array_merge($parents[$hostTemplateId]['htpl'], $stack);
        }

        return $parents;
    }
}
