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

namespace Core\HostTemplate\Application\UseCase\FindHostTemplates;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Common\Domain\HostEvent;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\HostTemplate\Infrastructure\API\FindHostTemplates\FindHostTemplatesPresenterOnPrem;
use Core\HostTemplate\Infrastructure\API\FindHostTemplates\FindHostTemplatesPresenterSaas;

final class FindHostTemplates
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ContactInterface $user
    ) {
    }

    /**
     * @param FindHostTemplatesPresenterOnPrem|FindHostTemplatesPresenterSaas $presenter
     */
    public function __invoke(PresenterInterface $presenter): void
    {
        try {
            if (
                ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_TEMPLATES_READ)
                && ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_TEMPLATES_READ_WRITE)
            ) {
                $this->error(
                    "User doesn't have sufficient rights to see host templates",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(HostTemplateException::accessNotAllowed())
                );

                return;
            }

            $hostTemplates = $this->readHostTemplateRepository->findByRequestParameter($this->requestParameters);
            $presenter->present($this->createResponse($hostTemplates));
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse(HostTemplateException::findHostTemplates($ex)));
            $this->error($ex->getMessage());
        }
    }

    /**
     * @param HostTemplate[] $hostTemplates
     *
     * @return FindHostTemplatesResponse
     */
    private function createResponse(array $hostTemplates): FindHostTemplatesResponse
    {
        $response = new FindHostTemplatesResponse();

        foreach ($hostTemplates as $hostTemplate) {
            $response->hostTemplates[] = [
                'id' => $hostTemplate->getId(),
                'name' => $hostTemplate->getName(),
                'alias' => $hostTemplate->getAlias(),
                'snmpVersion' => $hostTemplate->getSnmpVersion() ? $hostTemplate->getSnmpVersion()->value : null,
                'snmpCommunity' => $hostTemplate->getSnmpCommunity(),
                'timezoneId' => $hostTemplate->getTimezoneId(),
                'severityId' => $hostTemplate->getSeverityId(),
                'checkCommandId' => $hostTemplate->getCheckCommandId(),
                'checkCommandArgs' => $hostTemplate->getCheckCommandArgs(),
                'checkTimeperiodId' => $hostTemplate->getCheckTimeperiodId(),
                'maxCheckAttempts' => $hostTemplate->getMaxCheckAttempts(),
                'normalCheckInterval' => $hostTemplate->getNormalCheckInterval(),
                'retryCheckInterval' => $hostTemplate->getretryCheckInterval(),
                'isActiveCheckEnabled' => $hostTemplate->isActiveCheckEnabled()->toInt(),
                'isPassiveCheckEnabled' => $hostTemplate->isPassiveCheckEnabled()->toInt(),
                'isNotificationEnabled' => $hostTemplate->isNotificationEnabled()->toInt(),
                /**
                 * TODO
                 *  this is related to api (> 23.10) behaviour, where no options is egal to a full bitmask
                 *  Do we keep this behaviour or do we return null ?
                 */
                'notificationOptions' => $hostTemplate->getNotificationOptions() !== []
                    ? HostEvent::toBitmask($hostTemplate->getNotificationOptions())
                    : HostEvent::getMaxBitmask(),
                'notificationInterval' => $hostTemplate->getNotificationInterval(),
                'notificationTimeperiodId' => $hostTemplate->getNotificationTimeperiodId(),
                'addInheritedContactGroup' => $hostTemplate->addInheritedContactGroup(),
                'addInheritedContact' => $hostTemplate->addInheritedContact(),
                'firstNotificationDelay' => $hostTemplate->getfirstNotificationDelay(),
                'recoveryNotificationDelay' => $hostTemplate->getrecoveryNotificationDelay(),
                'acknowledgementTimeout' => $hostTemplate->getAcknowledgementTimeout(),
                'isFreshnessChecked' => $hostTemplate->isFreshnessChecked()->toInt(),
                'freshnessThreshold' => $hostTemplate->getfreshnessThreshold(),
                'isFlapDetectionEnabled' => $hostTemplate->isFlapDetectionEnabled()->toInt(),
                'lowFlapThreshold' => $hostTemplate->getLowFlapThreshold(),
                'highFlapThreshold' => $hostTemplate->getHighFlapThreshold(),
                'isEventHandlerEnabled' => $hostTemplate->isEventHandlerEnabled()->toInt(),
                'eventHandlerCommandId' => $hostTemplate->getEventHandlerCommandId(),
                'eventHandlerCommandArgs' => $hostTemplate->getEventHandlerCommandArgs(),
                'noteUrl' => $hostTemplate->getNoteUrl(),
                'note' => $hostTemplate->getNote(),
                'actionUrl' => $hostTemplate->getActionUrl(),
                'iconId' => $hostTemplate->getIconId(),
                'iconAlternative' => $hostTemplate->getIconAlternative(),
                'comment' => $hostTemplate->getComment(),
                'isActivated' => $hostTemplate->isActivated(),
                'isLocked' => $hostTemplate->isLocked(),
            ];
        }

        return $response;
    }
}
