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

namespace Core\Service\Application\UseCase\GetService;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Service\Application\Exception\ServiceException;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Domain\Model\Service;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Domain\Model\ServiceGroupRelation;
use Core\CommandMacro\Application\Repository\ReadCommandMacroRepositoryInterface;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class GetService
{
    use LoggerTrait;

     /** @var AccessGroup[] */
    private array $accessGroups;

    public function __construct(
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadServiceRepositoryInterface $readServiceRepository,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadServiceCategoryRepositoryInterface $readServiceCategoryRepository,
        private readonly ReadServiceGroupRepositoryInterface $readServiceGroupRepository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ContactInterface $user,
        private readonly ReadCommandMacroRepositoryInterface $readCommandMacroRepository,
        private readonly ReadCommandRepositoryInterface $readCommandRepository,
        private readonly ReadServiceMacroRepositoryInterface $readServiceMacroRepository,
    ) {
    }

    /**
     * @param GetServicePresenterInterface $presenter
     * @param int $serviceId
     */
    public function __invoke(int $serviceId, GetServicePresenterInterface $presenter): void
    {
        try {
            if (
                ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_READ)
                && ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_WRITE)
            ) {
                $this->error(
                    "User doesn't have sufficient rights to see services",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(ServiceException::accessNotAllowed())
                );

                return;
            }

            $service= null;
            if ($this->user->isAdmin()) {
                $service = $this->readServiceRepository->findById($serviceId);
                $serviceCategories = $this->readServiceCategoryRepository->findByService($serviceId);
                $serviceGroups = $this->readServiceGroupRepository->findByService($serviceId);

            } else {
                $this->accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                if ($this->readServiceRepository->existsByAccessGroups($serviceId, $this->accessGroups)) {
                    $service = $this->readServiceRepository->findById($serviceId);
                    $serviceCategories = $this->readServiceCategoryRepository->findByServiceAndAccessGroups(
                            $serviceId,
                            $this->accessGroups
                    );
                    $serviceGroups = $this->readServiceGroupRepository->findByServiceAndAccessGroups(
                        $serviceId,
                        $this->accessGroups
                    );
                }
            }

            if (! $service) {
                 $this->error(
                    'Service not found',
                    ['service_id' => $serviceId]
                );
                $presenter->presentResponse(new NotFoundResponse('Service'));

                return;
            }

            $presenter->presentResponse($this->createResponse($service, $serviceCategories, $serviceGroups));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(
                new ErrorResponse(ServiceException::errorWhileSearching($ex))
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param Service $service
     * @param ServiceCategory[] $serviceCategories
     * @param array<array{relation:ServiceGroupRelation,serviceGroup:ServiceGroup}> $serviceGroups
     *
     * @throws \Throwable
     *
     *
     * @return GetServiceResponse
     */
    private function createResponse(Service $service, array $serviceCategories, array $serviceGroups): GetServiceResponse
    {
        $macros = $this->readServiceMacroRepository->findByServiceIds($service->getId());

        $response = new GetServiceResponse();
        $response->id = $service->getId();
        $response->name = $service->getName();
        $response->commandArguments = $service->getCommandArguments();
        $response->eventHandlerArguments = $service->getEventHandlerArguments();
        $response->notificationTypes = $service->getNotificationTypes();
        $response->isContactAdditiveInheritance = $service->isContactAdditiveInheritance();
        $response->isContactGroupAdditiveInheritance = $service->isContactGroupAdditiveInheritance();
        $response->isActivated = $service->isActivated();
        $response->activeChecks = $service->getActiveChecks();
        $response->passiveCheck = $service->getPassiveCheck();
        $response->volatility = $service->getVolatility();
        $response->checkFreshness = $service->getCheckFreshness();
        $response->eventHandlerEnabled = $service->getEventHandlerEnabled();
        $response->flapDetectionEnabled = $service->getFlapDetectionEnabled();
        $response->notificationsEnabled = $service->getNotificationsEnabled();
        $response->comment = $service->getComment();
        $response->note = $service->getNote();
        $response->noteUrl = $service->getNoteUrl();
        $response->actionUrl = $service->getActionUrl();
        $response->iconAlternativeText = $service->getIconAlternativeText();
        $response->graphTemplateId = $service->getGraphTemplateId();
        $response->serviceTemplateId = $service->getServiceTemplateParentId();
        $response->commandId = $service->getCommandId();
        $response->eventHandlerId = $service->getEventHandlerId();
        $response->notificationTimePeriodId = $service->getNotificationTimePeriodId();
        $response->checkTimePeriodId = $service->getCheckTimePeriodId();
        $response->iconId = $service->getIconId();
        $response->severityId = $service->getSeverityId();
        $response->hostId = $service->getHostId();
        $response->maxCheckAttempts = $service->getMaxCheckAttempts();
        $response->normalCheckInterval = $service->getNormalCheckInterval();
        $response->retryCheckInterval = $service->getRetryCheckInterval();
        $response->freshnessThreshold = $service->getFreshnessThreshold();
        $response->lowFlapThreshold = $service->getLowFlapThreshold();
        $response->highFlapThreshold = $service->getHighFlapThreshold();
        $response->notificationInterval = $service->getNotificationInterval();
        $response->recoveryNotificationDelay = $service->getRecoveryNotificationDelay();
        $response->firstNotificationDelay = $service->getFirstNotificationDelay();
        $response->acknowledgementTimeout = $service->getAcknowledgementTimeout();
        $response->geoCoords = $service->getGeoCoords()?->__toString();
        $response->macros = array_map(
            fn (Macro $macro): MacroDto => new MacroDto(
                $macro->getName(),
                $macro->getValue(),
                $macro->isPassword(),
                $macro->getDescription()
            ),
            $macros
        );

        $response->categories = array_map(
            fn (ServiceCategory $category) => ['id' => $category->getId(), 'name' => $category->getName()],
            $serviceCategories
        );

        $response->groups = array_map(
            fn (array $group) => [
                'id' => $group['serviceGroup']->getId(),
                'name' => $group['serviceGroup']->getName(),
            ],
            $serviceGroups,
        );

        return $response;
    }
}
