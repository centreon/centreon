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

namespace Core\ServiceTemplate\Application\UseCase\FindServiceTemplates;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ServiceTemplate\Application\Exception\ServiceTemplateException;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;

final class FindServiceTemplates
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadServiceTemplateRepositoryInterface $repository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ContactInterface $user
    ) {
    }

    /**
     * @param FindServiceTemplatesPresenterInterface $presenter
     */
    public function __invoke(FindServiceTemplatesPresenterInterface $presenter): void
    {
        try {
            if (
                ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ)
                && ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE)
            ) {
                $this->error(
                    "User doesn't have sufficient rights to see service templates",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(ServiceTemplateException::accessNotAllowed())
                );

                return;
            }

            if ($this->user->isAdmin()) {
                $serviceTemplates = $this->repository->findByRequestParameter($this->requestParameters);
            } else {
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $serviceTemplates = $this->repository->findByRequestParametersAndAccessGroups(
                    $this->requestParameters,
                    $accessGroups
                );
            }

            $presenter->presentResponse($this->createResponse($serviceTemplates));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(ServiceTemplateException::errorWhileSearching($ex)));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param ServiceTemplate[] $serviceTemplates
     *
     * @return FindServiceTemplateResponse
     */
    private function createResponse(array $serviceTemplates): FindServiceTemplateResponse
    {
        $response = new FindServiceTemplateResponse();
        foreach ($serviceTemplates as $serviceTemplate) {
            $dto = new ServiceTemplateDto();
            $dto->id = $serviceTemplate->getId();
            $dto->name = $serviceTemplate->getName();
            $dto->alias = $serviceTemplate->getAlias();
            $dto->comment = $serviceTemplate->getComment();
            $dto->acknowledgementTimeout = $serviceTemplate->getAcknowledgementTimeout();
            $dto->actionUrl = $serviceTemplate->getActionUrl();
            $dto->isContactAdditiveInheritance = $serviceTemplate->isContactAdditiveInheritance();
            $dto->isContactGroupAdditiveInheritance = $serviceTemplate->isContactGroupAdditiveInheritance();
            $dto->commandArguments = $serviceTemplate->getCommandArguments();
            $dto->commandId = $serviceTemplate->getCommandId();
            $dto->checkTimePeriodId = $serviceTemplate->getCheckTimePeriodId();
            $dto->eventHandlerId = $serviceTemplate->getEventHandlerId();
            $dto->eventHandlerArguments = $serviceTemplate->getEventHandlerArguments();
            $dto->firstNotificationDelay = $serviceTemplate->getFirstNotificationDelay();
            $dto->freshnessThreshold = $serviceTemplate->getFreshnessThreshold();
            $dto->graphTemplateId = $serviceTemplate->getGraphTemplateId();
            $dto->flapDetectionEnabled = $serviceTemplate->getFlapDetectionEnabled();
            $dto->lowFlapThreshold = $serviceTemplate->getLowFlapThreshold();
            $dto->highFlapThreshold = $serviceTemplate->getHighFlapThreshold();
            $dto->iconId = $serviceTemplate->getIconId();
            $dto->iconAlternativeText = $serviceTemplate->getIconAlternativeText();
            $dto->isLocked = $serviceTemplate->isLocked();
            $dto->activeChecks = $serviceTemplate->getActiveChecks();
            $dto->eventHandlerEnabled = $serviceTemplate->getEventHandlerEnabled();
            $dto->checkFreshness = $serviceTemplate->getCheckFreshness();
            $dto->notificationsEnabled = $serviceTemplate->getNotificationsEnabled();
            $dto->passiveCheck = $serviceTemplate->getPassiveCheck();
            $dto->volatility = $serviceTemplate->getVolatility();
            $dto->maxCheckAttempts = $serviceTemplate->getMaxCheckAttempts();
            $dto->normalCheckInterval = $serviceTemplate->getNormalCheckInterval();
            $dto->note = $serviceTemplate->getNote();
            $dto->noteUrl = $serviceTemplate->getNoteUrl();
            $dto->notificationInterval = $serviceTemplate->getNotificationInterval();
            $dto->notificationTimePeriodId = $serviceTemplate->getNotificationTimePeriodId();
            $dto->notificationTypes = $serviceTemplate->getNotificationTypes();
            $dto->recoveryNotificationDelay = $serviceTemplate->getRecoveryNotificationDelay();
            $dto->retryCheckInterval = $serviceTemplate->getRetryCheckInterval();
            $dto->serviceTemplateId = $serviceTemplate->getServiceTemplateParentId();
            $dto->severityId = $serviceTemplate->getSeverityId();
            $dto->hostTemplateIds = $serviceTemplate->getHostTemplateIds();

            $response->serviceTemplates[] = $dto;
        }

        return $response;
    }
}
