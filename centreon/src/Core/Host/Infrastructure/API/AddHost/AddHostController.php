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

namespace Core\Host\Infrastructure\API\AddHost;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\UseCase\AddHost\AddHost;
use Core\Host\Application\UseCase\AddHost\AddHostRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AddHostController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param AddHost $useCase
     * @param AddHostSaasPresenter $saasPresenter
     * @param AddHostOnPremPresenter $onPremPresenter
     * @param bool $isCloudPlatform
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        AddHost $useCase,
        AddHostSaasPresenter $saasPresenter,
        AddHostOnPremPresenter $onPremPresenter,
        bool $isCloudPlatform,
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        if ($isCloudPlatform) {
            return $this->executeUseCaseSaas($useCase, $saasPresenter, $request);
        }

        return $this->executeUseCaseOnPrem($useCase, $onPremPresenter, $request);
    }

    /**
     * @param AddHost $useCase
     * @param AddHostOnPremPresenter $presenter
     * @param Request $request
     *
     * @return Response
     */
    private function executeUseCaseOnPrem(
        AddHost $useCase,
        AddHostOnPremPresenter $presenter,
        Request $request
    ): Response
    {
        try {
            /**
             * @var array{
             *     name: string,
             *     address: string,
             *     monitoring_server_id: int,
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
             *     templates?: int[],
             *     macros?: array<array{name:string,value:null|string,is_password:bool,description:null|string}>,
             *     add_inherited_contact_group?: bool,
             *     add_inherited_contact?: bool,
             *     is_activated?: bool
             * } $data
             */
            $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/AddHostOnPremSchema.json');

            $dto = new AddHostRequest();
            $dto->name = $data['name'];
            $dto->address = $data['address'];
            $dto->monitoringServerId = $data['monitoring_server_id'];
            $dto->alias = $data['alias'] ?? '';
            $dto->snmpVersion = $data['snmp_version'] ?? '';
            $dto->snmpCommunity = $data['snmp_community'] ?? '';
            $dto->noteUrl = $data['note_url'] ?? '';
            $dto->note = $data['note'] ?? '';
            $dto->actionUrl = $data['action_url'] ?? '';
            $dto->iconAlternative = $data['icon_alternative'] ?? '';
            $dto->comment = $data['comment'] ?? '';
            $dto->geoCoordinates = $data['geo_coords'] ?? '';
            $dto->checkCommandArgs = $data['check_command_args'] ?? [];
            $dto->eventHandlerCommandArgs = $data['event_handler_command_args'] ?? [];
            $dto->activeCheckEnabled = $data['active_check_enabled'] ?? 2;
            $dto->passiveCheckEnabled = $data['passive_check_enabled'] ?? 2;
            $dto->notificationEnabled = $data['notification_enabled'] ?? 2;
            $dto->freshnessChecked = $data['freshness_checked'] ?? 2;
            $dto->flapDetectionEnabled = $data['flap_detection_enabled'] ?? 2;
            $dto->eventHandlerEnabled = $data['event_handler_enabled'] ?? 2;
            $dto->timezoneId = $data['timezone_id'] ?? null;
            $dto->severityId = $data['severity_id'] ?? null;
            $dto->checkCommandId = $data['check_command_id'] ?? null;
            $dto->checkTimeperiodId = $data['check_timeperiod_id'] ?? null;
            $dto->notificationTimeperiodId = $data['notification_timeperiod_id'] ?? null;
            $dto->eventHandlerCommandId = $data['event_handler_command_id'] ?? null;
            $dto->iconId = $data['icon_id'] ?? null;
            $dto->maxCheckAttempts = $data['max_check_attempts'] ?? null;
            $dto->normalCheckInterval = $data['normal_check_interval'] ?? null;
            $dto->retryCheckInterval = $data['retry_check_interval'] ?? null;
            $dto->notificationOptions = $data['notification_options'] ?? null;
            $dto->notificationInterval = $data['notification_interval'] ?? null;
            $dto->firstNotificationDelay = $data['first_notification_delay'] ?? null;
            $dto->recoveryNotificationDelay = $data['recovery_notification_delay'] ?? null;
            $dto->acknowledgementTimeout = $data['acknowledgement_timeout'] ?? null;
            $dto->freshnessThreshold = $data['freshness_threshold'] ?? null;
            $dto->lowFlapThreshold = $data['low_flap_threshold'] ?? null;
            $dto->highFlapThreshold = $data['high_flap_threshold'] ?? null;
            $dto->categories = $data['categories'] ?? [];
            $dto->templates = $data['templates'] ?? [];
            $dto->macros = $data['macros'] ?? [];
            $dto->addInheritedContactGroup = $data['add_inherited_contact_group'] ?? false;
            $dto->addInheritedContact = $data['add_inherited_contact'] ?? false;
            $dto->isActivated = $data['is_activated'] ?? true;

            $useCase($dto, $presenter);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse(HostException::addHost()));
        }

        return $presenter->show();
    }

    /**
     * @param AddHost $useCase
     * @param AddHostSaasPresenter $presenter
     * @param Request $request
     *
     * @return Response
     */
    private function executeUseCaseSaas(
        AddHost $useCase,
        AddHostSaasPresenter $presenter,
        Request $request
    ): Response
    {
        try {
            /**
             * @var array{
             *     name: string,
             *     address: string,
             *     monitoring_server_id: int,
             *     alias?: string,
             *     snmp_version?: string,
             *     snmp_community?: string,
             *     note_url?: string,
             *     note?: string,
             *     action_url?: string,
             *     geo_coords?: string,
             *     timezone_id?: null|int,
             *     severity_id?: null|int,
             *     check_timeperiod_id?: null|int,
             *     categories?: int[],
             *     templates?: int[],
             *     macros?: array<array{name:string,value:null|string,is_password:bool,description:null|string}>,
             *     is_activated?: bool
             * } $data
             */
            $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/AddHostSaasSchema.json');

            $dto = new AddHostRequest();
            $dto->name = $data['name'];
            $dto->address = $data['address'];
            $dto->monitoringServerId = $data['monitoring_server_id'];
            $dto->alias = $data['alias'] ?? '';
            $dto->snmpVersion = $data['snmp_version'] ?? '';
            $dto->snmpCommunity = $data['snmp_community'] ?? '';
            $dto->noteUrl = $data['note_url'] ?? '';
            $dto->note = $data['note'] ?? '';
            $dto->actionUrl = $data['action_url'] ?? '';
            $dto->geoCoordinates = $data['geo_coords'] ?? '';
            $dto->timezoneId = $data['timezone_id'] ?? null;
            $dto->severityId = $data['severity_id'] ?? null;
            $dto->checkTimeperiodId = $data['check_timeperiod_id'] ?? null;
            $dto->categories = $data['categories'] ?? [];
            $dto->templates = $data['templates'] ?? [];
            $dto->macros = $data['macros'] ?? [];
            $dto->isActivated = $data['is_activated'] ?? true;

            $useCase($dto, $presenter);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse(HostException::addHost()));
        }

        return $presenter->show();
    }
}
