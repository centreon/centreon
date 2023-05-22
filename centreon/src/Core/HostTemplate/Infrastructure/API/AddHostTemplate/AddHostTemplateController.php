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

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplate;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplateRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AddHostTemplateController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param AddHostTemplate $useCase
     * @param AddHostTemplatePresenterSaas $saasPresenter
     * @param AddHostTemplatePresenterOnPrem $onPremPresenter
     * @param bool $isCloudPlatform
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        AddHostTemplate $useCase,
        AddHostTemplatePresenterSaas $saasPresenter,
        AddHostTemplatePresenterOnPrem $onPremPresenter,
        bool $isCloudPlatform,
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        if ($isCloudPlatform) {
            return $this->executeUseCaseSaas($useCase, $saasPresenter, $request);
        }

        return $this->executeUseCaseOnPrem($useCase, $onPremPresenter, $request);
    }

    /**
     * @param AddHostTemplate $useCase
     * @param AddHostTemplatePresenterOnPrem $presenter
     * @param Request $request
     *
     * @return Response
     */
    private function executeUseCaseOnPrem(
        AddHostTemplate $useCase,
        AddHostTemplatePresenterOnPrem $presenter,
        Request $request
    ): Response
    {
        try {
            /**
             * @var array{
             *     name: string,
             *     alias: string,
             *     snmp_version?: string,
             *     snmp_community?: string,
             *     timezone_id?: null|int,
             *     severity_id?: null|int,
             *     check_command_id?: null|int,
             *     check_command_args?: string[],
             *     check_timeperiod_id?: null|int,
             *     max_check_attempts?: null|int,
             *     normal_check_interval?: null|int,
             *     retry_check_interval?: null|int,
             *     active_check_enabled?: null|int|string,
             *     passive_check_enabled?: null|int|string,
             *     notification_enabled?: null|int|string,
             *     notification_options?: null|int,
             *     notification_interval?: null|int,
             *     notification_timeperiod_id?: null|int,
             *     add_inherited_contact_group?: bool,
             *     add_inherited_contact?: bool,
             *     first_notification_delay?: null|int,
             *     recovery_notification_delay?: null|int,
             *     acknowledgement_timeout?: null|int,
             *     freshness_checked?: null|int|string,
             *     freshness_threshold?: null|int,
             *     flap_detection_enabled?: null|int|string,
             *     low_flap_threshold?: null|int,
             *     high_flap_threshold?: null|int,
             *     event_handler_enabled?: null|int|string,
             *     event_handler_command_id?: null|int,
             *     event_handler_command_args?: string[],
             *     note_url?: string,
             *     note?: string,
             *     action_url?: string,
             *     icon_id?: null|int,
             *     icon_alternative?: string,
             *     comment?: string,
             *     is_activated?: bool
             * } $data
             */
            $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/AddHostTemplateSchemaOnPrem.json');

            $dto = new AddHostTemplateRequest();
            $dto->name = $data['name'];
            $dto->alias = $data['alias'];
            $dto->snmpVersion = $data['snmp_version'] ?? '';
            $dto->snmpCommunity = $data['snmp_community'] ?? '';
            $dto->timezoneId = $data['timezone_id'] ?? null;
            $dto->severityId = $data['severity_id'] ?? null;
            $dto->checkCommandId = $data['check_command_id'] ?? null;
            $dto->checkCommandArgs = $data['check_command_args'] ?? [];
            $dto->checkTimeperiodId = $data['check_timeperiod_id'] ?? null;
            $dto->maxCheckAttempts = $data['max_check_attempts'] ?? null;
            $dto->normalCheckInterval = $data['normal_check_interval'] ?? null;
            $dto->retryCheckInterval = $data['retry_check_interval'] ?? null;
            $dto->activeCheckEnabled = (string) ($data['active_check_enabled'] ?? '');
            $dto->passiveCheckEnabled = (string) ($data['passive_check_enabled'] ?? '');
            $dto->notificationEnabled = (string) ($data['notification_enabled'] ?? '');
            $dto->notificationOptions = $data['notification_options'] ?? null;
            $dto->notificationInterval = $data['notification_interval'] ?? null;
            $dto->notificationTimeperiodId = $data['notification_timeperiod_id'] ?? null;
            $dto->addInheritedContactGroup = $data['add_inherited_contact_group'] ?? false;
            $dto->addInheritedContact = $data['add_inherited_contact'] ?? false;
            $dto->firstNotificationDelay = $data['first_notification_delay'] ?? null;
            $dto->recoveryNotificationDelay = $data['recovery_notification_delay'] ?? null;
            $dto->acknowledgementTimeout = $data['acknowledgement_timeout'] ?? null;
            $dto->freshnessChecked = (string) ($data['freshness_checked'] ?? '');
            $dto->freshnessThreshold = $data['freshness_threshold'] ?? null;
            $dto->flapDetectionEnabled = (string) ($data['flap_detection_enabled'] ?? '');
            $dto->lowFlapThreshold = $data['low_flap_threshold'] ?? null;
            $dto->highFlapThreshold = $data['high_flap_threshold'] ?? null;
            $dto->eventHandlerEnabled = (string) ($data['event_handler_enabled'] ?? '');
            $dto->eventHandlerCommandId = $data['event_handler_command_id'] ?? null;
            $dto->eventHandlerCommandArgs = $data['event_handler_command_args'] ?? [];
            $dto->noteUrl = $data['note_url'] ?? '';
            $dto->note = $data['note'] ?? '';
            $dto->actionUrl = $data['action_url'] ?? '';
            $dto->iconId = $data['icon_id'] ?? null;
            $dto->iconAlternative = $data['icon_alternative'] ?? '';
            $dto->comment = $data['comment'] ?? '';
            $dto->isActivated = $data['is_activated'] ?? true;

            $useCase($dto, $presenter);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse($ex));
        }

        return $presenter->show();
    }

    /**
     * @param AddHostTemplate $useCase
     * @param AddHostTemplatePresenterSaas $presenter
     * @param Request $request
     *
     * @return Response
     */
    private function executeUseCaseSaas(
        AddHostTemplate $useCase,
        AddHostTemplatePresenterSaas $presenter,
        Request $request
    ): Response
    {
        try {
            /**
             * @var array{
             *     name: string,
             *     alias: string,
             *     snmp_version?: string,
             *     snmp_community?: string,
             *     timezone_id?: null|int,
             *     severity_id?: null|int,
             *     check_timeperiod_id?: null|int,
             *     note_url?: string,
             *     note?: string,
             *     action_url?: string,
             *     is_activated?: bool
             * } $data
             */
            $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/AddHostTemplateSchemaSaas.json');

            $dto = new AddHostTemplateRequest();
            $dto->name = $data['name'];
            $dto->alias = $data['alias'];
            $dto->snmpVersion = $data['snmp_version'] ?? '';
            $dto->snmpCommunity = $data['snmp_community'] ?? '';
            $dto->timezoneId = $data['timezone_id'] ?? null;
            $dto->severityId = $data['severity_id'] ?? null;
            $dto->checkTimeperiodId = $data['check_timeperiod_id'] ?? null;
            $dto->noteUrl = $data['note_url'] ?? '';
            $dto->note = $data['note'] ?? '';
            $dto->actionUrl = $data['action_url'] ?? '';
            $dto->isActivated = $data['is_activated'] ?? true;

            $useCase($dto, $presenter);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse($ex));
        }

        return $presenter->show();
    }
}
