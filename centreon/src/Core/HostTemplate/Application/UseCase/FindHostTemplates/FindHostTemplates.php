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
use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\Host\Application\Converter\HostEventConverter;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class FindHostTemplates
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ContactInterface $user
    ) {
    }

    /**
     * @param FindHostTemplatesPresenterInterface $presenter
     */
    public function __invoke(FindHostTemplatesPresenterInterface $presenter): void
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
                $presenter->presentResponse(
                    new ForbiddenResponse(HostTemplateException::accessNotAllowed())
                );

                return;
            }

            if ($this->user->isAdmin()) {
                $hostTemplates = $this->readHostTemplateRepository->findByRequestParameter($this->requestParameters);
            } else {
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $hostTemplates = $this->readHostTemplateRepository->findByRequestParametersAndAccessGroups(
                    $this->requestParameters,
                    $accessGroups
                );
            }

            $presenter->presentResponse($this->createResponse($hostTemplates));
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(HostTemplateException::findHostTemplates()));
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
                'retryCheckInterval' => $hostTemplate->getRetryCheckInterval(),
                'activeCheckEnabled' => YesNoDefaultConverter::toInt($hostTemplate->getActiveCheckEnabled()),
                'passiveCheckEnabled' => YesNoDefaultConverter::toInt($hostTemplate->getPassiveCheckEnabled()),
                'notificationEnabled' => YesNoDefaultConverter::toInt($hostTemplate->getNotificationEnabled()),
                'notificationOptions' => HostEventConverter::toBitFlag($hostTemplate->getNotificationOptions()),
                'notificationInterval' => $hostTemplate->getNotificationInterval(),
                'notificationTimeperiodId' => $hostTemplate->getNotificationTimeperiodId(),
                'addInheritedContactGroup' => $hostTemplate->addInheritedContactGroup(),
                'addInheritedContact' => $hostTemplate->addInheritedContact(),
                'firstNotificationDelay' => $hostTemplate->getFirstNotificationDelay(),
                'recoveryNotificationDelay' => $hostTemplate->getRecoveryNotificationDelay(),
                'acknowledgementTimeout' => $hostTemplate->getAcknowledgementTimeout(),
                'freshnessChecked' => YesNoDefaultConverter::toInt($hostTemplate->getFreshnessChecked()),
                'freshnessThreshold' => $hostTemplate->getfreshnessThreshold(),
                'flapDetectionEnabled' => YesNoDefaultConverter::toInt($hostTemplate->getFlapDetectionEnabled()),
                'lowFlapThreshold' => $hostTemplate->getLowFlapThreshold(),
                'highFlapThreshold' => $hostTemplate->getHighFlapThreshold(),
                'eventHandlerEnabled' => YesNoDefaultConverter::toInt($hostTemplate->getEventHandlerEnabled()),
                'eventHandlerCommandId' => $hostTemplate->getEventHandlerCommandId(),
                'eventHandlerCommandArgs' => $hostTemplate->getEventHandlerCommandArgs(),
                'noteUrl' => $hostTemplate->getNoteUrl(),
                'note' => $hostTemplate->getNote(),
                'actionUrl' => $hostTemplate->getActionUrl(),
                'iconId' => $hostTemplate->getIconId(),
                'iconAlternative' => $hostTemplate->getIconAlternative(),
                'comment' => $hostTemplate->getComment(),
                'isLocked' => $hostTemplate->isLocked(),
            ];
        }

        return $response;
    }
}
