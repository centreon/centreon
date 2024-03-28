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

namespace Core\Service\Infrastructure\API\PartialUpdateService;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Common\Domain\YesNoDefault;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Service\Application\UseCase\PartialUpdateService\MacroDto;
use Core\Service\Application\UseCase\PartialUpdateService\PartialUpdateService;
use Core\Service\Application\UseCase\PartialUpdateService\PartialUpdateServiceRequest;
use Core\Service\Infrastructure\Model\YesNoDefaultConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @phpstan-type _Service = array{
 *     name?: string,
 *     host_id?: int,
 *     service_template_id?: int|null,
 *     check_command_id?: int|null,
 *     check_timeperiod_id?: int|null,
 *     notification_timeperiod_id?: int|null,
 *     event_handler_command_id?: int|null,
 *     icon_id?: int|null,
 *     severity_id?: int|null,
 *     graph_template_id?: int|null,
 *     check_command_args?: list<string>|null,
 *     event_handler_command_args?: list<string>|null,
 *     max_check_attempts?: int|null,
 *     normal_check_interval?: int|null,
 *     retry_check_interval?: int|null,
 *     active_check_enabled?: int|null,
 *     passive_check_enabled?: int|null,
 *     volatility_enabled?: int|null,
 *     notification_enabled?: int|null,
 *     notification_interval?: int|null,
 *     notification_type?: int|null,
 *     first_notification_delay?: int|null,
 *     recovery_notification_delay?: int|null,
 *     acknowledgement_timeout?: int|null,
 *     freshness_checked?: int|null,
 *     freshness_threshold?: int|null,
 *     flap_detection_enabled?: int|null,
 *     low_flap_threshold?: int|null,
 *     high_flap_threshold?: int|null,
 *     event_handler_enabled?: int|null,
 *     note?: string|null,
 *     note_url?: string|null,
 *     action_url?: string|null,
 *     icon_alternative?: string|null,
 *     comment?: string|null,
 *     geo_coords?: string|null,
 *     macros?: array<array{name: string, value: string|null, is_password: bool, description: string|null}>,
 *     service_categories?: list<int>|null,
 *     service_groups?: list<int>|null,
 *     is_activated?: boolean,
 *     is_contact_group_additive_inheritance?: boolean,
 *     is_contact_additive_inheritance?: boolean,
 * }
 */
final class PartialUpdateServiceController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param PartialUpdateService $useCase
     * @param DefaultPresenter $presenter
     * @param bool $isCloudPlatform
     * @param int $serviceId
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        PartialUpdateService $useCase,
        DefaultPresenter $presenter,
        bool $isCloudPlatform,
        int $serviceId,
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            $dto = $this->createDto($request, $isCloudPlatform);
            $useCase($dto, $presenter, $serviceId);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        }

        return $presenter->show();
    }

    /**
     * @param Request $request
     * @param bool $isCloudPlatform
     *
     * @return PartialUpdateServiceRequest
     */
    private function createDto(Request $request, bool $isCloudPlatform): PartialUpdateServiceRequest
    {
        /** @var _Service $data */
        $data = $this->validateAndRetrieveDataSent(
            $request,
            $isCloudPlatform
                ? __DIR__ . DIRECTORY_SEPARATOR . 'PartialUpdateServiceSaasSchema.json'
                : __DIR__ . DIRECTORY_SEPARATOR . 'PartialUpdateServiceOnPremSchema.json'
        );

        $dto = new PartialUpdateServiceRequest();

        /** @var array<string,string> $nonEmptyProperties */
        $nonEmptyProperties = [
            'name' => 'name',
            'hostId' => 'host_id',
        ];
        if ($isCloudPlatform === true) {
            $nonEmptyProperties['template'] = 'service_template_id';
        }
        foreach ($nonEmptyProperties as $dtoKey => $dataKey) {
            if (\array_key_exists($dataKey, $data)) {
                $dto->{$dtoKey} = $data[$dataKey];
            }
        }

        /** @var array<string,string> $dataOrEmptyArrayProperties */
        $dataOrEmptyArrayProperties = [
            'commandArguments' => 'check_command_args',
            'eventHandlerArguments' => 'event_handler_command_args',
            'categories' => 'service_categories',
            'groups' => 'service_groups',
        ];
        foreach ($dataOrEmptyArrayProperties as $dtoKey => $dataKey) {
            if (\array_key_exists($dataKey, $data)) {
                $dto->{$dtoKey} = $data[$dataKey] ?? [];
            }
        }

        /** @var array<string,string> $dataOrNullProperties */
        $dataOrNullProperties = [
            'note' => 'note',
            'noteUrl' => 'note_url',
            'actionUrl' => 'action_url',
            'iconAlternativeText' => 'icon_alternative',
            'comment' => 'comment',
            'geoCoords' => 'geo_coords',
            'commandId' => 'check_command_id',
            'checkTimePeriodId' => 'check_timeperiod_id',
            'notificationTimePeriodId' => 'notification_timeperiod_id',
            'eventHandlerId' => 'event_handler_command_id',
            'graphTemplateId' => 'graph_template_id',
            'iconId' => 'icon_id',
            'severityId' => 'severity_id',
            'maxCheckAttempts' => 'max_check_attempts',
            'normalCheckInterval' => 'normal_check_interval',
            'retryCheckInterval' => 'retry_check_interval',
            'notificationInterval' => 'notification_interval',
            'notificationTypes' => 'notification_type',
            'firstNotificationDelay' => 'first_notification_delay',
            'recoveryNotificationDelay' => 'recovery_notification_delay',
            'acknowledgementTimeout' => 'acknowledgement_timeout',
            'freshnessThreshold' => 'freshness_threshold',
            'lowFlapThreshold' => 'low_flap_threshold',
            'highFlapThreshold' => 'high_flap_threshold',
        ];
        if ($isCloudPlatform === false) {
            $dataOrNullProperties['template'] = 'service_template_id';
        }
        foreach ($dataOrNullProperties as $dtoKey => $dataKey) {
            if (\array_key_exists($dataKey, $data)) {
                $dto->{$dtoKey} = $data[$dataKey] ?? null;
            }
        }

        /** @var array<string,string> $dataOrDefaultValueProperties */
        $dataOrDefaultValueProperties = [
            'activeChecks' => 'active_check_enabled',
            'passiveCheck' => 'passive_check_enabled',
            'volatility' => 'volatility_enabled',
            'notificationsEnabled' => 'notification_enabled',
            'checkFreshness' => 'freshness_checked',
            'flapDetectionEnabled' => 'flap_detection_enabled',
            'eventHandlerEnabled' => 'event_handler_enabled',
        ];
        foreach ($dataOrDefaultValueProperties as $dtoKey => $dataKey) {
            if (\array_key_exists($dataKey, $data)) {
                $dto->{$dtoKey} = $data[$dataKey] ?? YesNoDefaultConverter::toInt(YesNoDefault::Default);
            }
        }

        if (\array_key_exists('is_contact_group_additive_inheritance', $data)) {
            $dto->isContactGroupAdditiveInheritance = $data['is_contact_group_additive_inheritance'];
        }
        if (\array_key_exists('is_contact_additive_inheritance', $data)) {
            $dto->isContactAdditiveInheritance = $data['is_contact_additive_inheritance'];
        }
        if (\array_key_exists('is_activated', $data)) {
            $dto->isActivated = $data['is_activated'];
        }

        if (\array_key_exists('macros', $data)) {
            $dto->macros = [];
            foreach ($data['macros'] as $macro) {
                $dto->macros[] = new MacroDto(
                    $macro['name'],
                    $macro['value'],
                    (bool) $macro['is_password'],
                    $macro['description']
                );
            }
        }

        return $dto;
    }
}
