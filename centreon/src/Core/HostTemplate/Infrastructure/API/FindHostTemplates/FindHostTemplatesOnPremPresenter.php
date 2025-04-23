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
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\HostTemplate\Application\UseCase\FindHostTemplates\FindHostTemplatesPresenterInterface;
use Core\HostTemplate\Application\UseCase\FindHostTemplates\FindHostTemplatesResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class FindHostTemplatesOnPremPresenter extends AbstractPresenter implements FindHostTemplatesPresenterInterface
{
    use PresenterTrait;

    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @inheritDoc
     */
    public function presentResponse(FindHostTemplatesResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $result = [];
            foreach ($response->hostTemplates as $hostTemplate) {
                $result[] = [
                    'id' => $hostTemplate['id'],
                    'name' => $hostTemplate['name'],
                    'alias' => $hostTemplate['alias'],
                    'snmp_version' => $hostTemplate['snmpVersion'],
                    'timezone_id' => $hostTemplate['timezoneId'],
                    'severity_id' => $hostTemplate['severityId'],
                    'check_command_id' => $hostTemplate['checkCommandId'],
                    'check_command_args' => $hostTemplate['checkCommandArgs'],
                    'check_timeperiod_id' => $hostTemplate['checkTimeperiodId'],
                    'max_check_attempts' => $hostTemplate['maxCheckAttempts'],
                    'normal_check_interval' => $hostTemplate['normalCheckInterval'],
                    'retry_check_interval' => $hostTemplate['retryCheckInterval'],
                    'active_check_enabled' => $hostTemplate['activeCheckEnabled'],
                    'passive_check_enabled' => $hostTemplate['passiveCheckEnabled'],
                    'notification_enabled' => $hostTemplate['notificationEnabled'],
                    'notification_options' => $hostTemplate['notificationOptions'],
                    'notification_interval' => $hostTemplate['notificationInterval'],
                    'notification_timeperiod_id' => $hostTemplate['notificationTimeperiodId'],
                    'add_inherited_contact_group' => $hostTemplate['addInheritedContactGroup'],
                    'add_inherited_contact' => $hostTemplate['addInheritedContact'],
                    'first_notification_delay' => $hostTemplate['firstNotificationDelay'],
                    'recovery_notification_delay' => $hostTemplate['recoveryNotificationDelay'],
                    'acknowledgement_timeout' => $hostTemplate['acknowledgementTimeout'],
                    'freshness_checked' => $hostTemplate['freshnessChecked'],
                    'freshness_threshold' => $hostTemplate['freshnessThreshold'],
                    'flap_detection_enabled' => $hostTemplate['flapDetectionEnabled'],
                    'low_flap_threshold' => $hostTemplate['lowFlapThreshold'],
                    'high_flap_threshold' => $hostTemplate['highFlapThreshold'],
                    'event_handler_enabled' => $hostTemplate['eventHandlerEnabled'],
                    'event_handler_command_id' => $hostTemplate['eventHandlerCommandId'],
                    'event_handler_command_args' => $hostTemplate['eventHandlerCommandArgs'],
                    'note_url' => $this->emptyStringAsNull($hostTemplate['noteUrl']),
                    'note' => $this->emptyStringAsNull($hostTemplate['note']),
                    'action_url' => $this->emptyStringAsNull($hostTemplate['actionUrl']),
                    'icon_id' => $hostTemplate['iconId'],
                    'icon_alternative' => $this->emptyStringAsNull($hostTemplate['iconAlternative']),
                    'comment' => $this->emptyStringAsNull($hostTemplate['comment']),
                    'is_locked' => $hostTemplate['isLocked'],
                ];
            }

            $this->present([
                'result' => $result,
                'meta' => $this->requestParameters->toArray(),
            ]);
        }
    }
}
