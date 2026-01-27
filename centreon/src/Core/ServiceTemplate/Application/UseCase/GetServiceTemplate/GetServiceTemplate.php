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

namespace Core\ServiceTemplate\Application\UseCase\GetServiceTemplate;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ServiceTemplate\Application\Exception\ServiceTemplateException;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Domain\Model\ServiceGroupRelation;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\ServiceGroup\Domain\Model\ServiceGroup;

final class GetServiceTemplate
{
    use LoggerTrait;

     /** @var AccessGroup[] */
    private array $accessGroups;

    public function __construct(
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadServiceTemplateRepositoryInterface $readServiceTemplateRepository,
        private readonly ReadServiceCategoryRepositoryInterface $readServiceCategoryRepository,
        private readonly ReadServiceGroupRepositoryInterface $readServiceGroupRepository,
        private readonly ContactInterface $user,
        private readonly ReadServiceMacroRepositoryInterface $readServiceMacroRepository,
    ) {
    }

    /**
     * @param GetServiceTemplatePresenterInterface $presenter
     * @param int $serviceTemplateId
     */
    public function __invoke(GetServiceTemplatePresenterInterface $presenter, int $serviceTemplateId): void
    {
        try {
            if (
                ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ)
                && ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE)
            ) {
                $this->error(
                    "User doesn't have sufficient rights to see services templates",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(ServiceTemplateException::accessNotAllowed())
                );

                return;
            }

            $serviceTemplate = null;
            $serviceCategories = [];
            $serviceGroups = [];
            $macros = [];
            if ($this->user->isAdmin()) {
                $serviceTemplate = $this->readServiceTemplateRepository->findById($serviceTemplateId);
                $serviceCategories = $this->readServiceCategoryRepository->findByService($serviceTemplateId);
                $serviceGroups = $this->readServiceGroupRepository->findByService($serviceTemplateId);
                $macros = $this->readServiceMacroRepository->findByServiceIds($serviceTemplateId);

            } else {
                
                $this->accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $serviceTemplate = $this->readServiceTemplateRepository->findByIdAndAccessGroups($serviceTemplateId, $this->accessGroups);
                if ($serviceTemplate) {
                    $serviceCategories = $this->readServiceCategoryRepository->findByServiceAndAccessGroups(
                            $serviceTemplateId,
                            $this->accessGroups
                    );
                    
                    $serviceGroups = $this->readServiceGroupRepository->findByServiceAndAccessGroups(
                        $serviceTemplateId,
                        $this->accessGroups
                    );
                    
                    $macros = $this->readServiceMacroRepository->findByServiceIds($serviceTemplateId); 
                }
                    
            }

            if (! $serviceTemplate) {
                 $this->error(
                    'ServiceTemplate not found',
                    ['service_template_id' => $serviceTemplateId]
                );
                $presenter->presentResponse(new NotFoundResponse('ServiceTemplate'));

                return;
            }
        
            $presenter->presentResponse($this->createResponse($serviceTemplate, $serviceCategories, $serviceGroups, $macros));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(
                new ErrorResponse(ServiceTemplateException::errorWhileSearching($ex))
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param ServiceTemplate $serviceTemplate
     * @param ServiceCategory[] $serviceCategories
     * @param array<array{relation:ServiceGroupRelation,serviceGroup:ServiceGroup}> $serviceGroups
     * @param Macro[] $macros
     *
     * @throws \Throwable
     *
     *
     * @return GetServiceTemplateResponse
     */
    private function createResponse(ServiceTemplate $serviceTemplate, array $serviceCategories, array $serviceGroups, array $macros): GetServiceTemplateResponse
    {
        

        $response = new GetServiceTemplateResponse();
        $response->id = $serviceTemplate->getId();
        $response->name = $serviceTemplate->getName();
        $response->alias = $serviceTemplate->getAlias();
        $response->commandArguments = $serviceTemplate->getCommandArguments();
        $response->eventHandlerArguments = $serviceTemplate->getEventHandlerArguments();
        $response->notificationTypes = $serviceTemplate->getNotificationTypes();
        $response->isContactAdditiveInheritance = $serviceTemplate->isContactAdditiveInheritance();
        $response->isContactGroupAdditiveInheritance = $serviceTemplate->isContactGroupAdditiveInheritance();
        $response->isLocked = $serviceTemplate->isLocked();
        $response->activeChecks = $serviceTemplate->getActiveChecks();
        $response->passiveCheck = $serviceTemplate->getPassiveCheck();
        $response->volatility = $serviceTemplate->getVolatility();
        $response->checkFreshness = $serviceTemplate->getCheckFreshness();
        $response->eventHandlerEnabled = $serviceTemplate->getEventHandlerEnabled();
        $response->flapDetectionEnabled = $serviceTemplate->getFlapDetectionEnabled();
        $response->notificationsEnabled = $serviceTemplate->getNotificationsEnabled();
        $response->comment = $serviceTemplate->getComment();
        $response->note = $serviceTemplate->getNote();
        $response->noteUrl = $serviceTemplate->getNoteUrl();
        $response->actionUrl = $serviceTemplate->getActionUrl();
        $response->iconAlternativeText = $serviceTemplate->getIconAlternativeText();
        $response->graphTemplateId = $serviceTemplate->getGraphTemplateId();
        $response->serviceTemplateId = $serviceTemplate->getServiceTemplateParentId();
        $response->commandId = $serviceTemplate->getCommandId();
        $response->eventHandlerId = $serviceTemplate->getEventHandlerId();
        $response->notificationTimePeriodId = $serviceTemplate->getNotificationTimePeriodId();
        $response->checkTimePeriodId = $serviceTemplate->getCheckTimePeriodId();
        $response->iconId = $serviceTemplate->getIconId();
        $response->severityId = $serviceTemplate->getSeverityId();
        $response->hostTemplateIds = $serviceTemplate->getHostTemplateIds();
        $response->maxCheckAttempts = $serviceTemplate->getMaxCheckAttempts();
        $response->normalCheckInterval = $serviceTemplate->getNormalCheckInterval();
        $response->retryCheckInterval = $serviceTemplate->getRetryCheckInterval();
        $response->freshnessThreshold = $serviceTemplate->getFreshnessThreshold();
        $response->lowFlapThreshold = $serviceTemplate->getLowFlapThreshold();
        $response->highFlapThreshold = $serviceTemplate->getHighFlapThreshold();
        $response->notificationInterval = $serviceTemplate->getNotificationInterval();
        $response->recoveryNotificationDelay = $serviceTemplate->getRecoveryNotificationDelay();
        $response->firstNotificationDelay = $serviceTemplate->getFirstNotificationDelay();
        $response->acknowledgementTimeout = $serviceTemplate->getAcknowledgementTimeout();
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

        $hostTemplateNames = $this->readHostTemplateRepository->findNamesByIds(array_map(
            fn (array $group): int => (int) $group['relation']->getHostId(),
            $serviceGroups
        ));
        $response->groups = array_map(
            fn (array $group) => [
                'serviceGroupId' => $group['serviceGroup']->getId(),
                'serviceGroupName' => $group['serviceGroup']->getName(),
                'hostTemplateId' => (int) $group['relation']->getHostId(),
                'hostTemplateName' => $hostTemplateNames[(int) $group['relation']->getHostId()],
            ],
            $serviceGroups,
        );
       

        return $response;
    }
}