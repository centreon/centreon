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

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplatePresenterInterface;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplateResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class AddHostTemplatePresenterOnPrem extends AbstractPresenter implements AddHostTemplatePresenterInterface
{
    use PresenterTrait;

    public function __construct(
        protected PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @inheritDoc
     */
    public function presentResponse(AddHostTemplateResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $this->present([
                'id' => $response->id,
                'name' => $response->name,
                'alias' => $response->alias,
                'snmpVersion' => $response->snmpVersion,
                'snmpCommunity' => $this->emptyStringAsNull($response->snmpCommunity),
                'timezoneId' => $response->timezoneId,
                'severityId' => $response->severityId,
                'checkCommandId' => $response->checkCommandId,
                'checkCommandArgs' => $response->checkCommandArgs,
                'checkTimeperiodId' => $response->checkTimeperiodId,
                'maxCheckAttempts' => $response->maxCheckAttempts,
                'normalCheckInterval' => $response->normalCheckInterval,
                'retryCheckInterval' => $response->retryCheckInterval,
                'activeCheckEnabled' => $response->activeCheckEnabled,
                'passiveCheckEnabled' => $response->passiveCheckEnabled,
                'notificationEnabled' => $response->notificationEnabled,
                'notificationOptions' => $response->notificationOptions,
                'notificationInterval' => $response->notificationInterval,
                'notificationTimeperiodId' => $response->notificationTimeperiodId,
                'addInheritedContactGroup' => $response->addInheritedContactGroup,
                'addInheritedContact' => $response->addInheritedContact,
                'firstNotificationDelay' => $response->firstNotificationDelay,
                'recoveryNotificationDelay' => $response->recoveryNotificationDelay,
                'acknowledgementTimeout' => $response->acknowledgementTimeout,
                'freshnessChecked' => $response->freshnessChecked,
                'freshnessThreshold' => $response->freshnessThreshold,
                'flapDetectionEnabled' => $response->flapDetectionEnabled,
                'lowFlapThreshold' => $response->lowFlapThreshold,
                'highFlapThreshold' => $response->highFlapThreshold,
                'eventHandlerEnabled' => $response->eventHandlerEnabled,
                'eventHandlerCommandId' => $response->eventHandlerCommandId,
                'eventHandlerCommandArgs' => $response->eventHandlerCommandArgs,
                'noteUrl' => $this->emptyStringAsNull($response->noteUrl),
                'note' => $this->emptyStringAsNull($response->note),
                'actionUrl' => $this->emptyStringAsNull($response->actionUrl),
                'iconId' => $response->iconId,
                'iconAlternative' => $this->emptyStringAsNull($response->iconAlternative),
                'comment' => $this->emptyStringAsNull($response->comment),
                'isActivated' => $response->isActivated,
                'isLocked' => $response->isLocked,
            ]);

            // NOT setting location as required route does not currently exist
        }

    }
}
