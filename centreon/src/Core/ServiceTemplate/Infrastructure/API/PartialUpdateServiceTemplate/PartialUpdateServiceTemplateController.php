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

namespace Core\ServiceTemplate\Infrastructure\API\PartialUpdateServiceTemplate;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate\MacroDto;
use Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate\PartialUpdateServiceTemplate;
use Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate\PartialUpdateServiceTemplateRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-type _ServiceTemplate = array{
 *     name: string,
 *     alias: string,
 *     comment: string|null,
 *     service_template_id: int|null,
 *     check_command_id: int|null,
 *     check_command_args: list<string>,
 *     check_timeperiod_id: int,
 *     max_check_attempts: int|null,
 *     normal_check_interval: int|null,
 *     retry_check_interval: int|null,
 *     active_check_enabled: int,
 *     passive_check_enabled: int,
 *     volatility_enabled: int,
 *     notification_enabled: int,
 *     is_contact_additive_inheritance: boolean,
 *     is_contact_group_additive_inheritance: boolean,
 *     notification_interval: int|null,
 *     notification_timeperiod_id: int,
 *     notification_type: int,
 *     first_notification_delay: int|null,
 *     recovery_notification_delay: int|null,
 *     acknowledgement_timeout: int|null,
 *     freshness_checked: int,
 *     freshness_threshold: int|null,
 *     flap_detection_enabled: int,
 *     low_flap_threshold: int|null,
 *     high_flap_threshold: int|null,
 *     event_handler_enabled: int,
 *     event_handler_command_id: int|null,
 *     event_handler_command_args: list<string>,
 *     graph_template_id: int|null,
 *     host_templates: list<int>,
 *     note: string|null,
 *     note_url: string|null,
 *     action_url: string|null,
 *     icon_id: int|null,
 *     icon_alternative: string|null,
 *     severity_id: int|null,
 *     is_activated: boolean,
 *     host_templates: list<int>,
 *     service_categories: list<int>,
 *     macros: array<array{name: string, value: string|null, is_password: bool, description: string|null}>
 * }
 */
class PartialUpdateServiceTemplateController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param PartialUpdateServiceTemplate $useCase
     * @param DefaultPresenter $presenter
     * @param bool $isCloudPlatform
     * @param int $serviceTemplateId
     * @param Request $request
     *
     * @return Response
     */
    public function __invoke(
        PartialUpdateServiceTemplate $useCase,
        DefaultPresenter $presenter,
        bool $isCloudPlatform,
        int $serviceTemplateId,
        Request $request,
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        $validationSchema = $isCloudPlatform
            ? 'PartialUpdateServiceTemplateSaasSchema.json'
            : 'PartialUpdateServiceTemplateOnPremSchema.json';

        try {
            /** @var _ServiceTemplate $data
             */
            $data = $this->validateAndRetrieveDataSent(
                $request,
                __DIR__ . DIRECTORY_SEPARATOR . $validationSchema
            );
            $useCase($this->createDto($serviceTemplateId, $data), $presenter);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        }

        return $presenter->show();
    }

    /**
     * @param int $serviceTemplateId
     * @param _ServiceTemplate $request
     *
     * @return PartialUpdateServiceTemplateRequest
     */
    private function createDto(int $serviceTemplateId, array $request): PartialUpdateServiceTemplateRequest
    {
        $serviceTemplate = new PartialUpdateServiceTemplateRequest($serviceTemplateId);

        if (array_key_exists('name', $request)) {
            $serviceTemplate->name = $request['name'];
        }

        if (array_key_exists('alias', $request)) {
            $serviceTemplate->alias = $request['alias'];
        }

        if (array_key_exists('comment', $request)) {
            $serviceTemplate->comment = $request['comment'];
        }

        if (array_key_exists('service_template_id', $request)) {
            $serviceTemplate->serviceTemplateParentId = $request['service_template_id'];
        }

        if (array_key_exists('check_command_id', $request)) {
            $serviceTemplate->commandId = $request['check_command_id'];
        }

        if (array_key_exists('check_command_args', $request)) {
            $serviceTemplate->commandArguments = $request['check_command_args'];
        }

        if (array_key_exists('check_timeperiod_id', $request)) {
            $serviceTemplate->checkTimePeriodId = $request['check_timeperiod_id'];
        }

        if (array_key_exists('max_check_attempts', $request)) {
            $serviceTemplate->maxCheckAttempts = $request['max_check_attempts'];
        }

        if (array_key_exists('normal_check_interval', $request)) {
            $serviceTemplate->normalCheckInterval = $request['normal_check_interval'];
        }

        if (array_key_exists('retry_check_interval', $request)) {
            $serviceTemplate->retryCheckInterval = $request['retry_check_interval'];
        }

        if (array_key_exists('active_check_enabled', $request)) {
            $serviceTemplate->activeChecksEnabled = $request['active_check_enabled'];
        }

        if (array_key_exists('passive_check_enabled', $request)) {
            $serviceTemplate->passiveCheckEnabled = $request['passive_check_enabled'];
        }

        if (array_key_exists('volatility_enabled', $request)) {
            $serviceTemplate->volatility = $request['volatility_enabled'];
        }

        if (array_key_exists('notification_enabled', $request)) {
            $serviceTemplate->notificationsEnabled = $request['notification_enabled'];
        }

        if (array_key_exists('is_contact_additive_inheritance', $request)) {
            $serviceTemplate->isContactAdditiveInheritance = $request['is_contact_additive_inheritance'];
        }

        if (array_key_exists('is_contact_group_additive_inheritance', $request)) {
            $serviceTemplate->isContactGroupAdditiveInheritance = $request['is_contact_group_additive_inheritance'];
        }

        if (array_key_exists('notification_interval', $request)) {
            $serviceTemplate->notificationInterval = $request['notification_interval'];
        }

        if (array_key_exists('notification_timeperiod_id', $request)) {
            $serviceTemplate->notificationTimePeriodId = $request['notification_timeperiod_id'];
        }

        if (array_key_exists('notification_type', $request)) {
            $serviceTemplate->notificationTypes = $request['notification_type'];
        }

        if (array_key_exists('first_notification_delay', $request)) {
            $serviceTemplate->firstNotificationDelay = $request['first_notification_delay'];
        }

        if (array_key_exists('recovery_notification_delay', $request)) {
            $serviceTemplate->recoveryNotificationDelay = $request['recovery_notification_delay'];
        }

        if (array_key_exists('acknowledgement_timeout', $request)) {
            $serviceTemplate->acknowledgementTimeout = $request['acknowledgement_timeout'];
        }

        if (array_key_exists('freshness_checked', $request)) {
            $serviceTemplate->checkFreshness = $request['freshness_checked'];
        }

        if (array_key_exists('freshness_threshold', $request)) {
            $serviceTemplate->freshnessThreshold = $request['freshness_threshold'];
        }

        if (array_key_exists('flap_detection_enabled', $request)) {
            $serviceTemplate->flapDetectionEnabled = $request['flap_detection_enabled'];
        }

        if (array_key_exists('low_flap_threshold', $request)) {
            $serviceTemplate->lowFlapThreshold = $request['low_flap_threshold'];
        }

        if (array_key_exists('high_flap_threshold', $request)) {
            $serviceTemplate->highFlapThreshold = $request['high_flap_threshold'];
        }

        if (array_key_exists('event_handler_enabled', $request)) {
            $serviceTemplate->eventHandlerEnabled = $request['event_handler_enabled'];
        }

        if (array_key_exists('event_handler_command_id', $request)) {
            $serviceTemplate->eventHandlerId = $request['event_handler_command_id'];
        }

        if (array_key_exists('event_handler_command_args', $request)) {
            $serviceTemplate->eventHandlerArguments = $request['event_handler_command_args'];
        }

        if (array_key_exists('graph_template_id', $request)) {
            $serviceTemplate->graphTemplateId = $request['graph_template_id'];
        }

        if (array_key_exists('note', $request)) {
            $serviceTemplate->note = $request['note'];
        }

        if (array_key_exists('note_url', $request)) {
            $serviceTemplate->noteUrl = $request['note_url'];
        }

        if (array_key_exists('action_url', $request)) {
            $serviceTemplate->actionUrl = $request['action_url'];
        }

        if (array_key_exists('icon_id', $request)) {
            $serviceTemplate->iconId = $request['icon_id'];
        }

        if (array_key_exists('icon_alternative', $request)) {
            $serviceTemplate->iconAlternativeText = $request['icon_alternative'];
        }

        if (array_key_exists('severity_id', $request)) {
            $serviceTemplate->severityId = $request['severity_id'];
        }

        if (array_key_exists('is_activated', $request)) {
            $serviceTemplate->isActivated = $request['is_activated'];
        }

        if (array_key_exists('host_templates', $request)) {
            $serviceTemplate->hostTemplates = $request['host_templates'];
        }

        if (array_key_exists('service_categories', $request)) {
            $serviceTemplate->serviceCategories = $request['service_categories'];
        }

        if (array_key_exists('macros', $request)) {
            $serviceTemplate->macros = array_map(
                fn(array $macro): MacroDto => new MacroDto(
                    $macro['name'],
                    $macro['value'],
                    (bool) $macro['is_password'],
                    $macro['description']
                ),
                $request['macros']
            );
        }

        return $serviceTemplate;
    }
}
