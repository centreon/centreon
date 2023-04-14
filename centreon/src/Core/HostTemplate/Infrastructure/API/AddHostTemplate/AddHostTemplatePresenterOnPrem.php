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

namespace Core\HostTemplate\Infrastructure\API\AddHostTemplate;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplateResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class AddHostTemplatePresenterOnPrem extends AbstractPresenter
{
    use PresenterTrait;

    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @param CreatedResponse<AddHostTemplateResponse> $data
     */
    public function present(mixed $data): void
    {
        if (
            $data instanceof CreatedResponse
            && $data->getPayload() instanceof AddHostTemplateResponse
        ) {
            $payload = $data->getPayload();
            $data->setPayload([
                'id' => $payload->id,
                'name' => $payload->name,
                'alias' => $payload->alias,
                'snmpVersion' => $payload->snmpVersion,
                'snmpCommunity' => $this->emptyStringAsNull($payload->snmpCommunity),
                'timezoneId' => $payload->timezoneId,
                'severityId' => $payload->severityId,
                'checkCommandId' => $payload->checkCommandId,
                'checkCommandArgs' => $this->emptyStringAsNull($payload->checkCommandArgs),
                'checkTimeperiodId' => $payload->checkTimeperiodId,
                'maxCheckAttempts' => $payload->maxCheckAttempts,
                'normalCheckInterval' => $payload->normalCheckInterval,
                'retryCheckInterval' => $payload->retryCheckInterval,
                'isActiveCheckEnabled' => $payload->isActiveCheckEnabled,
                'isPassiveCheckEnabled' => $payload->isPassiveCheckEnabled,
                'isNotificationEnabled' => $payload->isNotificationEnabled,
                'notificationOptions' => $payload->notificationOptions,
                'notificationInterval' => $payload->notificationInterval,
                'notificationTimeperiodId' => $payload->notificationTimeperiodId,
                // TODO : should the following two conditioned by options.inheritance_mode === '1' ?
                'addInheritedContactGroup' => $payload->addInheritedContactGroup,
                'addInheritedContact' => $payload->addInheritedContact,
                'firstNotificationDelay' => $payload->firstNotificationDelay,
                'recoveryNotificationDelay' => $payload->recoveryNotificationDelay,
                'acknowledgementTimeout' => $payload->acknowledgementTimeout,
                'isFreshnessChecked' => $payload->isFreshnessChecked,
                'freshnessThreshold' => $payload->freshnessThreshold,
                'isFlapDetectionEnabled' => $payload->isFlapDetectionEnabled,
                'lowFlapThreshold' => $payload->lowFlapThreshold,
                'highFlapThreshold' => $payload->highFlapThreshold,
                'isEventHandlerEnabled' => $payload->isEventHandlerEnabled,
                'eventHandlerCommandId' => $payload->eventHandlerCommandId,
                'eventHandlerCommandArgs' => $this->emptyStringAsNull($payload->eventHandlerCommandArgs),
                'noteUrl' => $this->emptyStringAsNull($payload->noteUrl),
                'note' => $this->emptyStringAsNull($payload->note),
                'actionUrl' => $this->emptyStringAsNull($payload->actionUrl),
                'iconId' => $payload->iconId,
                'iconAlternative' => $this->emptyStringAsNull($payload->iconAlternative),
                'comment' => $this->emptyStringAsNull($payload->comment),
                'isActivated' => $payload->isActivated,
                'isLocked' => $payload->isLocked,
            ]);

            // NOT setting location as required route does not currently exist
        }
        parent::present($data);
    }
}
