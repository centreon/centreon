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

namespace Core\ServiceTemplate\Infrastructure\API\FindServiceTemplates;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;
use Core\ServiceTemplate\Application\UseCase\FindServiceTemplates\FindServiceTemplateResponse;
use Core\ServiceTemplate\Application\UseCase\FindServiceTemplates\FindServiceTemplatesPresenterInterface;

class FindServiceTemplatesPresenterOnPrem extends AbstractPresenter implements FindServiceTemplatesPresenterInterface
{
    use PresenterTrait;

    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(ResponseStatusInterface|FindServiceTemplateResponse $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $result = [];
            foreach ($response->serviceTemplates as $dto) {
                $result[] = [
                    'id' => $dto->id,
                    'name' => $dto->name,
                    'alias' => $dto->alias,
                    'comment' => $dto->comment,
                    'service_template_id' => $dto->serviceTemplateId,
                    'check_command_id' => $dto->commandId,
                    'check_command_args' => $dto->commandArguments,
                    'check_timeperiod_id' => $dto->checkTimePeriodId,
                    'max_check_attempts' => $dto->maxCheckAttempts,
                    'normal_check_interval' => $dto->normalCheckInterval,
                    'retry_check_interval' => $dto->retryCheckInterval,
                    'active_check_enabled' => YesNoDefaultConverter::toInt($dto->activeChecks),
                    'passive_check_enabled' => YesNoDefaultConverter::toInt($dto->passiveCheck),
                    'volatility_enabled' => YesNoDefaultConverter::toInt($dto->volatility),
                    'notification_enabled' => YesNoDefaultConverter::toInt($dto->notificationsEnabled),
                    'is_contact_additive_inheritance' => $dto->isContactAdditiveInheritance,
                    'is_contact_group_additive_inheritance' => $dto->isContactGroupAdditiveInheritance,
                    'notification_interval' => $dto->notificationInterval,
                    'notification_timeperiod_id' => $dto->notificationTimePeriodId,
                    'notification_type' => NotificationTypeConverter::toBits($dto->notificationTypes),
                    'first_notification_delay' => $dto->firstNotificationDelay,
                    'recovery_notification_delay' => $dto->recoveryNotificationDelay,
                    'acknowledgement_timeout' => $dto->acknowledgementTimeout,
                    'freshness_checked' => YesNoDefaultConverter::toInt($dto->checkFreshness),
                    'freshness_threshold' => $dto->freshnessThreshold,
                    'flap_detection_enabled' => YesNoDefaultConverter::toInt($dto->flapDetectionEnabled),
                    'low_flap_threshold' => $dto->highFlapThreshold,
                    'high_flap_threshold' => $dto->highFlapThreshold,
                    'event_handler_enabled' => YesNoDefaultConverter::toInt($dto->eventHandlerEnabled),
                    'event_handler_command_id' => $dto->eventHandlerId,
                    'event_handler_command_args' => $dto->eventHandlerArguments,
                    'graph_template_id' => $dto->graphTemplateId,
                    'note' => $dto->note,
                    'note_url' => $dto->noteUrl,
                    'action_url' => $dto->actionUrl,
                    'icon_id' => $dto->iconId,
                    'icon_alternative' => $dto->iconAlternativeText,
                    'severity_id' => $dto->severityId,
                    'is_activated' => $dto->isActivated,
                    'is_locked' => $dto->isLocked,
                ];
            }
            $this->present([
                'result' => $result,
                'meta' => $this->requestParameters->toArray(),
            ]);
        }
    }
}
