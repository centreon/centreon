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

namespace Core\HostTemplate\Infrastructure\API\FindHostTemplates;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\HostTemplate\Application\UseCase\FindHostTemplates\FindHostTemplatesResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class FindHostTemplatesPresenterOnPrem extends AbstractPresenter
{
    use PresenterTrait;

    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @param FindHostTemplatesResponse $data
     */
    public function present(mixed $data): void
    {
        $result = [];

        foreach ($data->hostTemplates as $hostTemplate) {
            $result[] = [
                'id' => $hostTemplate['id'],
                'name' => $hostTemplate['name'],
                'alias' => $hostTemplate['alias'],
                'snmpVersion' => $hostTemplate['snmpVersion'],
                'snmpCommunity' => $this->emptyStringAsNull($hostTemplate['snmpCommunity']),
                'timezoneId' => $hostTemplate['timezoneId'],
                'severityId' => $hostTemplate['severityId'],
                'checkCommandId' => $hostTemplate['checkCommandId'],
                'checkCommandArgs' => $this->emptyStringAsNull($hostTemplate['checkCommandArgs']),
                'checkTimeperiodId' => $hostTemplate['checkTimeperiodId'],
                'maxCheckAttempts' => $hostTemplate['maxCheckAttempts'],
                'normalCheckInterval' => $hostTemplate['normalCheckInterval'],
                'retryCheckInterval' => $hostTemplate['retryCheckInterval'],
                'isActiveCheckEnabled' => $hostTemplate['isActiveCheckEnabled'],
                'isPassiveCheckEnabled' => $hostTemplate['isPassiveCheckEnabled'],
                'isNotificationEnabled' => $hostTemplate['isNotificationEnabled'],
                'notificationOptions' => $hostTemplate['notificationOptions'],
                'notificationInterval' => $hostTemplate['notificationInterval'],
                'notificationTimeperiodId' => $hostTemplate['notificationTimeperiodId'],
                // TODO : should the following two conditioned by options.inheritance_mode === '1' ?
                'addInheritedContactGroup' => $hostTemplate['addInheritedContactGroup'],
                'addInheritedContact' => $hostTemplate['addInheritedContact'],
                'firstNotificationDelay' => $hostTemplate['firstNotificationDelay'],
                'recoveryNotificationDelay' => $hostTemplate['recoveryNotificationDelay'],
                'acknowledgementTimeout' => $hostTemplate['acknowledgementTimeout'],
                'isFreshnessChecked' => $hostTemplate['isFreshnessChecked'],
                'freshnessThreshold' => $hostTemplate['freshnessThreshold'],
                'isFlapDetectionEnabled' => $hostTemplate['isFlapDetectionEnabled'],
                'lowFlapThreshold' => $hostTemplate['lowFlapThreshold'],
                'highFlapThreshold' => $hostTemplate['highFlapThreshold'],
                'isEventHandlerEnabled' => $hostTemplate['isEventHandlerEnabled'],
                'eventHandlerCommandId' => $hostTemplate['eventHandlerCommandId'],
                'eventHandlerCommandArgs' => $this->emptyStringAsNull($hostTemplate['eventHandlerCommandArgs']),
                'noteUrl' => $this->emptyStringAsNull($hostTemplate['noteUrl']),
                'note' => $this->emptyStringAsNull($hostTemplate['note']),
                'actionUrl' => $this->emptyStringAsNull($hostTemplate['actionUrl']),
                'iconId' => $hostTemplate['iconId'],
                'iconAlternative' => $this->emptyStringAsNull($hostTemplate['iconAlternative']),
                'comment' => $this->emptyStringAsNull($hostTemplate['comment']),
                'isActivated' => $hostTemplate['isActivated'],
                'isLocked' => $hostTemplate['isLocked'],
            ];
        }

        parent::present([
            'result' => $result,
            'meta' => $this->requestParameters->toArray(),
        ]);
    }
}
