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
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\MetaServiceConfiguration\Interfaces\MetaServiceConfigurationReadRepositoryInterface;
use Centreon\Domain\MetaServiceConfiguration\Model\MetaServiceConfiguration;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Configuration\Notification\Repository\ReadMetaServiceNotificationRepositoryInterface;
use Core\Application\RealTime\Repository\ReadMetaServiceRepositoryInterface as ReadRealTimeMetaServiceRepositoryInterface;
use Core\Domain\Configuration\Notification\Model\NotifiedContact;
use Core\Domain\Configuration\Notification\Model\NotifiedContactGroup;
use Core\Domain\RealTime\Model\MetaService as RealtimeMetaService;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

class FindMetaServiceNotificationPolicy
{
    use LoggerTrait;

    /**
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     * @param ReadMetaServiceNotificationRepositoryInterface $readMetaServiceNotificationRepository
     * @param MetaServiceConfigurationReadRepositoryInterface $readMetaServiceRepository
     * @param EngineConfigurationServiceInterface $engineService
     * @param ContactInterface $contact
     * @param ReadRealTimeMetaServiceRepositoryInterface $readRealTimeMetaServiceRepository
     */
    public function __construct(
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadMetaServiceNotificationRepositoryInterface $readMetaServiceNotificationRepository,
        private readonly MetaServiceConfigurationReadRepositoryInterface $readMetaServiceRepository,
        private readonly EngineConfigurationServiceInterface $engineService,
        private readonly ContactInterface $contact,
        private readonly ReadRealTimeMetaServiceRepositoryInterface $readRealTimeMetaServiceRepository,
    ) {
    }

    /**
     * @param int $metaServiceId
     * @param FindNotificationPolicyPresenterInterface $presenter
     */
    public function __invoke(
        int $metaServiceId,
        FindNotificationPolicyPresenterInterface $presenter,
    ): void {
        $metaService = $this->findMetaService($metaServiceId);
        if ($metaService === null) {
            $this->handleMetaServiceNotFound($metaServiceId, $presenter);

            return;
        }

        if ($this->contact->isAdmin()) {
            $notifiedContacts = $this->readMetaServiceNotificationRepository->findNotifiedContactsById($metaServiceId);
            $notifiedContactGroups = $this->readMetaServiceNotificationRepository->findNotifiedContactGroupsById(
                $metaServiceId
            );
        } else {
            $accessGroups = $this->readAccessGroupRepository->findByContact($this->contact);
            $notifiedContacts = $this->readMetaServiceNotificationRepository->findNotifiedContactsByIdAndAccessGroups(
                $metaServiceId,
                $accessGroups
            );
            $notifiedContactGroups
                = $this->readMetaServiceNotificationRepository->findNotifiedContactGroupsByIdAndAccessGroups(
                    $metaServiceId,
                    $accessGroups
                );
        }

        $realtimeMetaService = $this->readRealTimeMetaServiceRepository->findMetaServiceById($metaServiceId);
        if ($realtimeMetaService === null) {
            $this->handleMetaServiceNotFound($metaServiceId, $presenter);

            return;
        }

        $engineConfiguration = $this->engineService->findCentralEngineConfiguration();
        if ($engineConfiguration === null) {
            $this->handleEngineHostConfigurationNotFound($presenter);

            return;
        }
        $this->overrideMetaServiceNotificationByEngineConfiguration($engineConfiguration, $realtimeMetaService);

        $presenter->present(
            $this->createResponse(
                $notifiedContacts,
                $notifiedContactGroups,
                $realtimeMetaService->isNotificationEnabled(),
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
     * @param int $metaServiceId
     *
     * @return MetaServiceConfiguration|null
     */
    private function findMetaService(int $metaServiceId): ?MetaServiceConfiguration
    {
        $this->info('Searching for meta service configuration', ['id' => $metaServiceId]);

        if ($this->contact->isAdmin()) {
            return $this->readMetaServiceRepository->findById($metaServiceId);
        }

        return $this->readMetaServiceRepository->findByIdAndContact($metaServiceId, $this->contact);
    }

    /**
     * @param int $metaServiceId
     * @param FindNotificationPolicyPresenterInterface $presenter
     */
    private function handleMetaServiceNotFound(
        int $metaServiceId,
        FindNotificationPolicyPresenterInterface $presenter,
    ): void {
        $this->error(
            'Meta service not found',
            [
                'id' => $metaServiceId,
                'userId' => $this->contact->getId(),
            ]
        );
        $presenter->setResponseStatus(new NotFoundResponse('Meta service'));
    }

    /**
     * @param FindNotificationPolicyPresenterInterface $presenter
     */
    private function handleEngineHostConfigurationNotFound(
        FindNotificationPolicyPresenterInterface $presenter,
    ): void {
        $this->error(
            'Central engine configuration not found',
            [
                'userId' => $this->contact->getId(),
            ]
        );
        $presenter->setResponseStatus(new NotFoundResponse('Engine configuration'));
    }

    /**
     * If engine configuration related to the meta service has notification disabled,
     * it overrides meta service notification status.
     *
     * @param EngineConfiguration $engineConfiguration
     * @param RealtimeMetaService $realtimeMetaService
     */
    private function overrideMetaServiceNotificationByEngineConfiguration(
        EngineConfiguration $engineConfiguration,
        RealtimeMetaService $realtimeMetaService,
    ): void {
        if (
            $engineConfiguration->getNotificationsEnabledOption()
                === EngineConfiguration::NOTIFICATIONS_OPTION_DISABLED
        ) {
            $realtimeMetaService->setNotificationEnabled(false);
        }
    }
}
