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

namespace Core\HostTemplate\Infrastructure\API\PartialUpdateHostTemplate;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\UseCase\PartialUpdateHostTemplate\PartialUpdateHostTemplate;
use Core\HostTemplate\Application\UseCase\PartialUpdateHostTemplate\PartialUpdateHostTemplateRequest;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class PartialUpdateHostTemplateController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param PartialUpdateHostTemplate $useCase
     * @param DefaultPresenter $presenter
     * @param bool $isCloudPlatform
     * @param int $hostTemplateId
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        PartialUpdateHostTemplate $useCase,
        DefaultPresenter $presenter,
        bool $isCloudPlatform,
        int $hostTemplateId,
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            $dto = $isCloudPlatform ? $this->setDtoForSaas($request) : $this->setDtoForOnPrem($request);

            $useCase($dto, $presenter, $hostTemplateId);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse(HostTemplateException::partialUpdateHostTemplate()));
        }

        return $presenter->show();
    }

    private function setDtoForOnPrem(Request $request): PartialUpdateHostTemplateRequest
    {
        /**
         * @var array{
         *      macros?:array<array{name:string,value:string|null,is_password:bool,description:string|null}>,
         *      categories?: int[],
         *      templates?: int[],
         *      name?: string,
         *      alias?: string,
         *      snmp_version?: null|string,
         *      snmp_community?: null|string,
         *      timezone_id?: null|int,
         *      severity_id?: null|int,
         *      check_command_id?: null|int,
         *      check_command_args?: string[],
         *      check_timeperiod_id?: null|int,
         *      max_check_attempts?: null|int,
         *      normal_check_interval?: null|int,
         *      retry_check_interval?: null|int,
         *      active_check_enabled?: int,
         *      passive_check_enabled?: int,
         *      notification_enabled?: int,
         *      notification_options?: null|int,
         *      notification_interval?: null|int,
         *      notification_timeperiod_id?: null|int,
         *      add_inherited_contact_group?: bool,
         *      add_inherited_contact?: bool,
         *      first_notification_delay?: null|int,
         *      recovery_notification_delay?: null|int,
         *      acknowledgement_timeout?: null|int,
         *      freshness_checked?: int,
         *      freshness_threshold?: null|int,
         *      flap_detection_enabled?: int,
         *      low_flap_threshold?: null|int,
         *      high_flap_threshold?: null|int,
         *      event_handler_enabled?: int,
         *      event_handler_command_id?: null|int,
         *      event_handler_command_args?: string[],
         *      note_url?: null|string,
         *      note?: null|string,
         *      action_url?: null|string,
         *      icon_id?: null|int,
         *      icon_alternative?: null|string,
         *      comment?: string,
         *      is_activated?: bool
         * } $data
         */
        $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/PartialUpdateHostTemplateOnPremSchema.json');

        $dto = new PartialUpdateHostTemplateRequest();

        if (\array_key_exists('macros', $data)) {
            $dto->macros = $data['macros'];
        }

        if (\array_key_exists('categories', $data)) {
            $dto->categories = $data['categories'];
        }

        if (\array_key_exists('templates', $data)) {
            $dto->templates = $data['templates'];
        }

        if (\array_key_exists('name', $data)) {
            $dto->name = $data['name'];
        }
        if (\array_key_exists('alias', $data)) {
            $dto->alias = $data['alias'];
        }
        if (\array_key_exists('snmp_version', $data)) {
            $dto->snmpVersion = $data['snmp_version'] ?? '';
        }
        if (\array_key_exists('snmp_community', $data)) {
            $dto->snmpCommunity = $data['snmp_community'] ?? '';
        }
        if (\array_key_exists('timezone_id', $data)) {
            $dto->timezoneId = $data['timezone_id'];
        }
        if (\array_key_exists('severity_id', $data)) {
            $dto->severityId = $data['severity_id'];
        }
        if (\array_key_exists('check_command_id', $data)) {
            $dto->checkCommandId = $data['check_command_id'];
        }
        if (\array_key_exists('check_command_args', $data)) {
            $dto->checkCommandArgs = $data['check_command_args'];
        }
        if (\array_key_exists('check_timeperiod_id', $data)) {
            $dto->checkTimeperiodId = $data['check_timeperiod_id'];
        }
        if (\array_key_exists('max_check_attempts', $data)) {
            $dto->maxCheckAttempts = $data['max_check_attempts'];
        }
        if (\array_key_exists('normal_check_interval', $data)) {
            $dto->normalCheckInterval = $data['normal_check_interval'];
        }
        if (\array_key_exists('retry_check_interval', $data)) {
            $dto->retryCheckInterval = $data['retry_check_interval'];
        }
        if (\array_key_exists('active_check_enabled', $data)) {
            $dto->activeCheckEnabled = $data['active_check_enabled'];
        }
        if (\array_key_exists('passive_check_enabled', $data)) {
            $dto->passiveCheckEnabled = $data['passive_check_enabled'];
        }
        if (\array_key_exists('notification_enabled', $data)) {
            $dto->notificationEnabled = $data['notification_enabled'];
        }
        if (\array_key_exists('notification_options', $data)) {
            $dto->notificationOptions = $data['notification_options'];
        }
        if (\array_key_exists('notification_interval', $data)) {
            $dto->notificationInterval = $data['notification_interval'];
        }
        if (\array_key_exists('notification_timeperiod_id', $data)) {
            $dto->notificationTimeperiodId = $data['notification_timeperiod_id'];
        }
        if (\array_key_exists('add_inherited_contact_group', $data)) {
            $dto->addInheritedContactGroup = $data['add_inherited_contact_group'];
        }
        if (\array_key_exists('add_inherited_contact', $data)) {
            $dto->addInheritedContact = $data['add_inherited_contact'];
        }
        if (\array_key_exists('first_notification_delay', $data)) {
            $dto->firstNotificationDelay = $data['first_notification_delay'];
        }
        if (\array_key_exists('recovery_notification_delay', $data)) {
            $dto->recoveryNotificationDelay = $data['recovery_notification_delay'];
        }
        if (\array_key_exists('acknowledgement_timeout', $data)) {
            $dto->acknowledgementTimeout = $data['acknowledgement_timeout'];
        }
        if (\array_key_exists('freshness_checked', $data)) {
            $dto->freshnessChecked = $data['freshness_checked'];
        }
        if (\array_key_exists('freshness_threshold', $data)) {
            $dto->freshnessThreshold = $data['freshness_threshold'];
        }
        if (\array_key_exists('flap_detection_enabled', $data)) {
            $dto->flapDetectionEnabled = $data['flap_detection_enabled'];
        }
        if (\array_key_exists('low_flap_threshold', $data)) {
            $dto->lowFlapThreshold = $data['low_flap_threshold'];
        }
        if (\array_key_exists('high_flap_threshold', $data)) {
            $dto->highFlapThreshold = $data['high_flap_threshold'];
        }
        if (\array_key_exists('event_handler_enabled', $data)) {
            $dto->eventHandlerEnabled = $data['event_handler_enabled'];
        }
        if (\array_key_exists('event_handler_command_id', $data)) {
            $dto->eventHandlerCommandId = $data['event_handler_command_id'];
        }
        if (\array_key_exists('event_handler_command_args', $data)) {
            $dto->eventHandlerCommandArgs = $data['event_handler_command_args'];
        }
        if (\array_key_exists('note_url', $data)) {
            $dto->noteUrl = $data['note_url'] ?? '';
        }
        if (\array_key_exists('note', $data)) {
            $dto->note = $data['note'] ?? '';
        }
        if (\array_key_exists('action_url', $data)) {
            $dto->actionUrl = $data['action_url'] ?? '';
        }
        if (\array_key_exists('icon_id', $data)) {
            $dto->iconId = $data['icon_id'];
        }
        if (\array_key_exists('icon_alternative', $data)) {
            $dto->iconAlternative = $data['icon_alternative'] ?? '';
        }
        if (\array_key_exists('comment', $data)) {
            $dto->comment = $data['comment'] ?? '';
        }
        if (\array_key_exists('is_activated', $data)) {
            $dto->isActivated = $data['is_activated'];
        }

        return $dto;
    }

    /**
     * @param Request $request
     *
     * @throws \Throwable|\InvalidArgumentException
     *
     * @return PartialUpdateHostTemplateRequest
     */
    private function setDtoForSaas(Request $request): PartialUpdateHostTemplateRequest
    {
        /**
         * @var array{
         *      macros?:array<array{name:string,value:string|null,is_password:bool,description:string|null}>,
         *      categories?: int[],
         *      templates?: int[],
         *      name: string,
         *      alias: string,
         *      snmp_version?: null|string,
         *      snmp_community?: null|string,
         *      timezone_id?: null|int,
         *      severity_id?: null|int,
         *      check_timeperiod_id?: null|int,
         *      note_url?: null|string,
         *      note?: null|string,
         *      action_url?: null|string,
         *      is_activated?: bool,
         * } $data
         */
        $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/PartialUpdateHostTemplateSaasSchema.json');

        $dto = new PartialUpdateHostTemplateRequest();

        if (\array_key_exists('macros', $data)) {
            $dto->macros = $data['macros'];
        }
        if (\array_key_exists('categories', $data)) {
            $dto->categories = $data['categories'];
        }
        if (\array_key_exists('templates', $data)) {
            $dto->templates = $data['templates'];
        }
        if (\array_key_exists('name', $data)) {
            $dto->name = $data['name'];
        }
        if (\array_key_exists('alias', $data)) {
            $dto->alias = $data['alias'];
        }
        if (\array_key_exists('snmp_version', $data)) {
            $dto->snmpVersion = $data['snmp_version'] ?? '';
        }
        if (\array_key_exists('snmp_community', $data)) {
            $dto->snmpCommunity = $data['snmp_community'] ?? '';
        }
        if (\array_key_exists('timezone_id', $data)) {
            $dto->timezoneId = $data['timezone_id'];
        }
        if (\array_key_exists('severity_id', $data)) {
            $dto->severityId = $data['severity_id'];
        }
        if (\array_key_exists('check_timeperiod_id', $data)) {
            $dto->checkTimeperiodId = $data['check_timeperiod_id'];
        }
        if (\array_key_exists('note_url', $data)) {
            $dto->noteUrl = $data['note_url'] ?? '';
        }
        if (\array_key_exists('note', $data)) {
            $dto->note = $data['note'] ?? '';
        }
        if (\array_key_exists('action_url', $data)) {
            $dto->actionUrl = $data['action_url'] ?? '';
        }
        if (\array_key_exists('is_activated', $data)) {
            $dto->isActivated = $data['is_activated'];
        }

        return $dto;
    }
}