<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Host\Infrastructure\API\PartialUpdateHost;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\UseCase\PartialUpdateHost\PartialUpdateHost;
use Core\Host\Application\UseCase\PartialUpdateHost\PartialUpdateHostRequest;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class PartialUpdateHostController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param PartialUpdateHost $useCase
     * @param DefaultPresenter $presenter
     * @param bool $isCloudPlatform
     * @param int $hostId
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        PartialUpdateHost $useCase,
        DefaultPresenter $presenter,
        bool $isCloudPlatform,
        int $hostId
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            $dto = $this->setDto($request, $isCloudPlatform);

            $useCase($dto, $presenter, $hostId);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse(HostException::editHost()));
        }

        return $presenter->show();
    }

    /**
     * @param Request $request
     * @param bool $isCloudPlatform
     *
     * @throws \Throwable|\InvalidArgumentException
     *
     * @return PartialUpdateHostRequest
     */
    private function setDto(Request $request, bool $isCloudPlatform): PartialUpdateHostRequest
    {
        /**
         * @var array{
         *     name?: string,
         *     address?: string,
         *     monitoring_server_id?: int,
         *     alias?: string,
         *     snmp_version?: string,
         *     snmp_community?: string,
         *     note_url?: string,
         *     note?: string,
         *     action_url?: string,
         *     icon_alternative?: string,
         *     comment?: string,
         *     geo_coords?: string,
         *     check_command_args?: string[],
         *     event_handler_command_args?: string[],
         *     active_check_enabled?: int,
         *     passive_check_enabled?: int,
         *     notification_enabled?: int,
         *     freshness_checked?: int,
         *     flap_detection_enabled?: int,
         *     event_handler_enabled?: int,
         *     timezone_id?: null|int,
         *     severity_id?: null|int,
         *     check_command_id?: null|int,
         *     check_timeperiod_id?: null|int,
         *     event_handler_command_id?: null|int,
         *     notification_timeperiod_id?: null|int,
         *     icon_id?: null|int,
         *     max_check_attempts?: null|int,
         *     normal_check_interval?: null|int,
         *     retry_check_interval?: null|int,
         *     notification_options?: null|int,
         *     notification_interval?: null|int,
         *     first_notification_delay?: null|int,
         *     recovery_notification_delay?: null|int,
         *     acknowledgement_timeout?: null|int,
         *     freshness_threshold?: null|int,
         *     low_flap_threshold?: null|int,
         *     high_flap_threshold?: null|int,
         *     categories?: int[],
         *     groups?: int[],
         *     templates?: int[],
         *     macros?: array<array{name:string,value:null|string,is_password:bool,description:null|string}>,
         *     add_inherited_contact_group?: bool,
         *     add_inherited_contact?: bool,
         *     is_activated?: bool
         * } $data
         */
        $data = $this->validateAndRetrieveDataSent(
            $request,
            $isCloudPlatform
                ? __DIR__ . '/PartialUpdateHostSaasSchema.json'
                : __DIR__ . '/PartialUpdateHostOnPremSchema.json'
        );

        $dto = new PartialUpdateHostRequest();

        /** @var array<string,string> $nonEmptyProperties */
        $nonEmptyProperties = [
            'name' => 'name',
            'address' => 'address',
            'monitoringServerId' => 'monitoring_server_id',
        ];
        foreach ($nonEmptyProperties as $dtoKey => $dataKey) {
            if (\array_key_exists($dataKey, $data)) {
                $dto->{$dtoKey} = $data[$dataKey];
            }
        }

        /** @var array<string,string> $dataOrEmptyStringProperties */
        $dataOrEmptyStringProperties = [
            'alias' => 'alias',
            'snmpVersion' => 'snmp_version',
            'snmpCommunity' => 'snmp_community',
            'noteUrl' => 'note_url',
            'note' => 'note',
            'actionUrl' => 'action_url',
            'iconAlternative' => 'icon_alternative',
            'comment' => 'comment',
            'geoCoordinates' => 'geo_coords',
        ];
        foreach ($dataOrEmptyStringProperties as $dtoKey => $dataKey) {
            if (\array_key_exists($dataKey, $data)) {
                $dto->{$dtoKey} = $data[$dataKey] ?? '';
            }
        }

        /** @var array<string,string> $dataOrEmptyArrayProperties */
        $dataOrEmptyArrayProperties = [
            'checkCommandArgs' => 'check_command_args',
            'eventHandlerCommandArgs' => 'event_handler_command_args',
            'categories' => 'categories',
            'groups' => 'groups',
            'templates' => 'templates',
            'macros' => 'macros',
        ];
        foreach ($dataOrEmptyArrayProperties as $dtoKey => $dataKey) {
            if (\array_key_exists($dataKey, $data)) {
                $dto->{$dtoKey} = $data[$dataKey] ?? [];
            }
        }

        /** @var array<string,string> $dataOrNullProperties */
        $dataOrNullProperties = [
            'timezoneId' => 'timezone_id',
            'severityId' => 'severity_id',
            'checkCommandId' => 'check_command_id',
            'checkTimeperiodId' => 'check_timeperiod_id',
            'notificationTimeperiodId' => 'notification_timeperiod_id',
            'eventHandlerCommandId' => 'event_handler_command_id',
            'iconId' => 'icon_id',
            'maxCheckAttempts' => 'max_check_attempts',
            'normalCheckInterval' => 'normal_check_interval',
            'retryCheckInterval' => 'retry_check_interval',
            'notificationOptions' => 'notification_options',
            'notificationInterval' => 'notification_interval',
            'firstNotificationDelay' => 'first_notification_delay',
            'recoveryNotificationDelay' => 'recovery_notification_delay',
            'acknowledgementTimeout' => 'acknowledgement_timeout',
            'freshnessThreshold' => 'freshness_threshold',
            'lowFlapThreshold' => 'low_flap_threshold',
            'highFlapThreshold' => 'high_flap_threshold',
        ];
        foreach ($dataOrNullProperties as $dtoKey => $dataKey) {
            if (\array_key_exists($dataKey, $data)) {
                $dto->{$dtoKey} = $data[$dataKey] ?? null;
            }
        }

        /** @var array<string,string> $dataOrDefaultValueProperties */
        $dataOrDefaultValueProperties = [
            'activeCheckEnabled' => 'active_check_enabled',
            'passiveCheckEnabled' => 'passive_check_enabled',
            'notificationEnabled' => 'notification_enabled',
            'freshnessChecked' => 'freshness_checked',
            'flapDetectionEnabled' => 'flap_detection_enabled',
            'eventHandlerEnabled' => 'event_handler_enabled',
        ];
        foreach ($dataOrDefaultValueProperties as $dtoKey => $dataKey) {
            if (\array_key_exists($dataKey, $data)) {
                $dto->{$dtoKey} = $data[$dataKey] ?? 2;
            }
        }

        if (\array_key_exists('add_inherited_contact_group', $data)) {
            $dto->addInheritedContactGroup = $data['add_inherited_contact_group'];
        }
        if (\array_key_exists('add_inherited_contact', $data)) {
            $dto->addInheritedContact = $data['add_inherited_contact'];
        }
        if (\array_key_exists('is_activated', $data)) {
            $dto->isActivated = $data['is_activated'];
        }

        return $dto;
    }
}
