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

namespace Core\Host\Application\UseCase\GetHost;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\Contact\Domain\AdminResolver;
use Core\Host\Application\Converter\HostEventConverter;
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Domain\Model\Host;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class GetHost
{
    use LoggerTrait;

    /** @var AccessGroup[] */
    private array $accessGroups;

    /**
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     * @param ReadHostRepositoryInterface $readHostRepository
     * @param ReadHostTemplateRepositoryInterface $readHostTemplateRepository
     * @param ReadHostCategoryRepositoryInterface $readHostCategoryRepository
     * @param ReadHostGroupRepositoryInterface $readHostGroupRepository
     * @param ContactInterface $user
     * @param ReadHostMacroRepositoryInterface $readHostMacroRepository
     * @param AdminResolver $adminResolver
     */
    public function __construct(
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ReadHostCategoryRepositoryInterface $readHostCategoryRepository,
        private readonly ReadHostGroupRepositoryInterface $readHostGroupRepository,
        private readonly ContactInterface $user,
        private readonly ReadHostMacroRepositoryInterface $readHostMacroRepository,
        private readonly AdminResolver $adminResolver,
    ) {
    }

    /**
     * @param GetHostPresenterInterface $presenter
     * @param int $hostId
     */
    public function __invoke(GetHostPresenterInterface $presenter, int $hostId): void
    {
        try {
            if (
                ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_READ)
                && ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_WRITE)
            ) {
                $this->error(
                    "User doesn't have sufficient rights to see hosts",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(HostException::accessNotAllowed())
                );

                return;
            }

            $host = null;
            $hostCategories = [];
            $hostGroups = [];
            $macros = [];
            $parentTemplates = [];
            if ($this->adminResolver->isAdmin($this->user)) {
                $host = $this->readHostRepository->findById($hostId);
                if ($host !== null) {
                    $hostCategories = $this->readHostCategoryRepository->findByHost($hostId);
                    $hostGroups = $this->readHostGroupRepository->findByHost($hostId);
                    $macros = $this->readHostMacroRepository->findByHostId($hostId);
                    $parentTemplates = $this->findParentTemplates($hostId);
                }
            } else {
                $this->accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                if ($this->readHostRepository->existsByAccessGroups($hostId, $this->accessGroups)) {
                    $host = $this->readHostRepository->findById($hostId);
                    if ($host !== null) {
                        $hostCategories = $this->readHostCategoryRepository->findByHostAndAccessGroups(
                            $hostId,
                            $this->accessGroups
                        );

                        $hostGroups = $this->readHostGroupRepository->findByHostAndAccessGroups(
                            $hostId,
                            $this->accessGroups
                        );

                        $macros = $this->readHostMacroRepository->findByHostId($hostId);
                        $parentTemplates = $this->findParentTemplates($hostId);
                    }
                }
            }

            if (! $host) {
                $this->info(
                    'Host not found',
                    ['host_id' => $hostId]
                );
                $presenter->presentResponse(new NotFoundResponse('Host'));

                return;
            }

            $presenter->presentResponse($this->createResponse($host, $hostCategories, $hostGroups, $parentTemplates, $macros));
        } catch (\Throwable $ex) {
            $presenter->presentResponse(
                new ErrorResponse(HostException::errorWhileRetrievingObject())
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param Host $host
     * @param HostCategory[] $hostCategories
     * @param HostGroup[] $hostGroups
     * @param array<array{id:int,name:string}> $parentTemplates
     * @param Macro[] $macros
     *
     * @throws \Throwable
     *
     * @return GetHostResponse
     */
    private function createResponse(Host $host, array $hostCategories, array $hostGroups, array $parentTemplates, array $macros): GetHostResponse
    {
        $response = new GetHostResponse();

        $response->id = $host->getId();
        $response->monitoringServerId = $host->getMonitoringServerId();
        $response->name = $host->getName();
        $response->address = $host->getAddress();
        $response->snmpVersion = $host->getSnmpVersion()?->value;
        $response->geoCoords = $host->getGeoCoordinates()?->__toString();
        $response->alias = $host->getAlias();
        $response->snmpCommunity = $host->getSnmpCommunity();
        $response->noteUrl = $host->getNoteUrl();
        $response->note = $host->getNote();
        $response->actionUrl = $host->getActionUrl();
        $response->iconId = $host->getIconId();
        $response->iconAlternative = $host->getIconAlternative();
        $response->comment = $host->getComment();
        $response->checkCommandArgs = $host->getCheckCommandArgs();
        $response->eventHandlerCommandArgs = $host->getEventHandlerCommandArgs();
        $response->activeCheckEnabled = YesNoDefaultConverter::toInt($host->getActiveCheckEnabled());
        $response->passiveCheckEnabled = YesNoDefaultConverter::toInt($host->getPassiveCheckEnabled());
        $response->notificationEnabled = YesNoDefaultConverter::toInt($host->getNotificationEnabled());
        $response->notificationOptions = HostEventConverter::toBitFlag($host->getNotificationOptions());
        $response->freshnessChecked = YesNoDefaultConverter::toInt($host->getFreshnessChecked());
        $response->flapDetectionEnabled = YesNoDefaultConverter::toInt($host->getFlapDetectionEnabled());
        $response->eventHandlerEnabled = YesNoDefaultConverter::toInt($host->getEventHandlerEnabled());
        $response->timezoneId = $host->getTimezoneId();
        $response->severityId = $host->getSeverityId();
        $response->checkCommandId = $host->getCheckCommandId();
        $response->checkTimeperiodId = $host->getCheckTimeperiodId();
        $response->eventHandlerCommandId = $host->getEventHandlerCommandId();
        $response->maxCheckAttempts = $host->getMaxCheckAttempts();
        $response->normalCheckInterval = $host->getNormalCheckInterval();
        $response->retryCheckInterval = $host->getRetryCheckInterval();
        $response->notificationInterval = $host->getNotificationInterval();
        $response->notificationTimeperiodId = $host->getNotificationTimeperiodId();
        $response->firstNotificationDelay = $host->getFirstNotificationDelay();
        $response->recoveryNotificationDelay = $host->getRecoveryNotificationDelay();
        $response->acknowledgementTimeout = $host->getAcknowledgementTimeout();
        $response->freshnessThreshold = $host->getFreshnessThreshold();
        $response->lowFlapThreshold = $host->getLowFlapThreshold();
        $response->highFlapThreshold = $host->getHighFlapThreshold();
        $response->addInheritedContactGroup = $host->addInheritedContactGroup();
        $response->addInheritedContact = $host->addInheritedContact();
        $response->isActivated = $host->isActivated();

        $response->categories = array_map(
            fn (HostCategory $category) => ['id' => $category->getId(), 'name' => $category->getName()],
            $hostCategories
        );

        $response->groups = array_map(
            fn (HostGroup $group) => ['id' => $group->getId(), 'name' => $group->getName()],
            $hostGroups
        );

        $response->templates = array_map(
            fn (array $template) => ['id' => $template['id'], 'name' => $template['name']],
            $parentTemplates
        );

        $response->macros = array_map(
            static fn (Macro $macro): array => [
                'id' => $macro->getId(),
                'name' => $macro->getName(),
                'value' => $macro->getValue(),
                'isPassword' => $macro->isPassword(),
                'description' => $macro->getDescription(),
            ],
            $macros
        );

        return $response;
    }

    /**
     * @param int $hostId
     *
     * @throws HostException
     * @throws \Throwable
     *
     * @return array<array{id:int,name:string}>
     */
    private function findParentTemplates(int $hostId): array
    {
        $templateIds = $this->readHostTemplateRepository->findByHostId($hostId);
        $templateNames = $this->readHostTemplateRepository->findNamesByIds($templateIds);

        $parentTemplates = [];
        foreach ($templateIds as $templateId) {
            if (! isset($templateNames[$templateId])) {
                $this->warning('Template name not found for template', [
                    'host_id' => $hostId,
                    'template_id' => $templateId,
                ]);
                continue;
            }
            $parentTemplates[] = [
                'id' => $templateId,
                'name' => $templateNames[$templateId],
            ];
        }

        return $parentTemplates;
    }
}
