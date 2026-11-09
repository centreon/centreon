<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Tests\App\ActivityLogging\Domain\Factory;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\Actor;
use App\ActivityLogging\Domain\Aggregate\ActorId;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\ActivityLogging\Domain\Factory\ServiceActivityLogFactory;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceSchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceStalkingOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServiceActivityLogFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $factory = new ServiceActivityLogFactory();

        $service = new Service(
            id: new ServiceId(42),
            name: new ServiceName('web-check'),
            hostId: new HostId(7),
            hostActivated: true,
            hostPollerId: null,
        );

        $firedAt = new \DateTimeImmutable();

        $activityLog = $factory->create(
            action: ActionEnum::Delete,
            aggregate: $service,
            firedBy: new Actor(id: new ActorId(1)),
            firedAt: $firedAt,
        );

        self::assertSame(ActionEnum::Delete, $activityLog->action);
        self::assertSame(42, $activityLog->target->id->value);
        self::assertSame('web-check', $activityLog->target->name->value);
        self::assertSame(TargetTypeEnum::Service, $activityLog->target->type);
        self::assertSame(1, $activityLog->actor->id->value);
        self::assertSame($firedAt, $activityLog->performedAt);
        self::assertSame([
            'service_name' => 'web-check',
            'host_id' => '7',
        ], $activityLog->details);
    }

    /**
     * A creation logs every field of the form, keyed by legacy column name and valued in the legacy storage
     * format, plus the host. Service groups, categories, severity, traps and macros are not logged.
     */
    public function testAddDetails(): void
    {
        $activityLog = (new ServiceActivityLogFactory())->create(
            action: ActionEnum::Add,
            aggregate: $this->completeService(),
            firedBy: new Actor(id: new ActorId(1)),
            firedAt: new \DateTimeImmutable(),
        );

        self::assertSame(
            [
                'service_description' => 'web-check',
                'service_activate' => '1',
                'service_template_model_stm_id' => '5',
                'command_command_id' => '11',
                'command_command_id_arg' => '!80!line#BR#two',
                'timeperiod_tp_id' => '21',
                'service_max_check_attempts' => '3',
                'service_normal_check_interval' => '5',
                'service_retry_check_interval' => '1',
                'service_active_checks_enabled' => '0',
                'service_passive_checks_enabled' => '1',
                'service_is_volatile' => '2',
                'service_notifications_enabled' => '1',
                'service_use_only_contacts_from_host' => '1',
                'contact_additive_inheritance' => '1',
                'cg_additive_inheritance' => '0',
                'service_cs' => '31,32',
                'service_cgs' => '41',
                'service_notification_interval' => '30',
                'timeperiod_tp_id2' => '22',
                'service_notification_options' => 'w,c,r',
                'service_first_notification_delay' => '2',
                'service_recovery_notification_delay' => '4',
                'service_obsess_over_service' => '0',
                'service_acknowledgement_timeout' => '15',
                'service_check_freshness' => '1',
                'service_freshness_threshold' => '60',
                'service_flap_detection_enabled' => '2',
                'service_low_flap_threshold' => '10',
                'service_high_flap_threshold' => '90',
                'service_retain_status_information' => '1',
                'service_retain_nonstatus_information' => '0',
                'service_stalking_options' => 'o,c',
                'service_event_handler_enabled' => '1',
                'command_command_id2' => '12',
                'command_command_id_arg2' => '!handler-arg',
                'esi_notes_url' => 'https://example.com/notes',
                'esi_notes' => 'a note',
                'esi_action_url' => 'https://example.com/action',
                'esi_icon_image' => '51',
                'esi_icon_image_alt' => 'alt text',
                'graph_id' => '61',
                'geo_coords' => '48.85,2.35',
                'service_comment' => 'a comment',
                'host_id' => '7',
            ],
            $activityLog->details,
        );
    }

    /**
     * Without notifications block (Cloud), the notification keys are left out, as for hosts; an unset value is
     * an empty string.
     */
    public function testAddDetailsOfAServiceWithoutNotificationsBlock(): void
    {
        $service = new Service(
            id: new ServiceId(42),
            name: new ServiceName('web-check'),
            hostId: new HostId(7),
            hostActivated: true,
            hostPollerId: null,
        );

        $details = (new ServiceActivityLogFactory())->create(ActionEnum::Add, $service, new Actor(id: new ActorId(1)), new \DateTimeImmutable())->details;

        self::assertSame([], array_intersect_key($details, array_flip([
            'service_notifications_enabled',
            'service_use_only_contacts_from_host',
            'contact_additive_inheritance',
            'cg_additive_inheritance',
            'service_cs',
            'service_cgs',
            'service_notification_interval',
            'timeperiod_tp_id2',
            'service_notification_options',
            'service_first_notification_delay',
            'service_recovery_notification_delay',
        ])));
        self::assertSame(
            ['', '', '', '', '', '2', '', '7'],
            [
                $details['service_template_model_stm_id'],
                $details['command_command_id'],
                $details['command_command_id_arg'],
                $details['service_max_check_attempts'],
                $details['service_stalking_options'],
                $details['service_check_freshness'],
                $details['esi_notes'],
                $details['host_id'],
            ],
        );
    }

    /**
     * Only a creation logs the form: the other actions keep the name of the service and its host.
     */
    #[DataProvider('otherActionProvider')]
    public function testOtherActionsLogTheNameAndTheHostOnly(ActionEnum $action): void
    {
        $details = (new ServiceActivityLogFactory())->create($action, $this->completeService(), new Actor(id: new ActorId(1)), new \DateTimeImmutable())->details;

        self::assertSame(['service_name' => 'web-check', 'host_id' => '7'], $details);
    }

    /**
     * @return iterable<string, array{ActionEnum}>
     */
    public static function otherActionProvider(): iterable
    {
        yield 'delete' => [ActionEnum::Delete];

        yield 'enable' => [ActionEnum::Enable];

        yield 'disable' => [ActionEnum::Disable];

        yield 'update' => [ActionEnum::Update];

        yield 'mass change' => [ActionEnum::MassChange];
    }

    private function completeService(): Service
    {
        return new Service(
            id: new ServiceId(42),
            name: new ServiceName('web-check'),
            hostId: new HostId(7),
            hostActivated: true,
            hostPollerId: new PollerId(3),
            activated: true,
            templateId: new ServiceTemplateId(5),
            checkOptions: new ServiceCheckOptions(
                new CommandId(11),
                ['80', "line\ntwo"],
                [new ServiceMacro(new ServiceMacroName('TOKEN'), 'secret-value', isPassword: true)],
            ),
            schedulingOptions: new ServiceSchedulingOptions(
                checkTimeperiodId: new TimePeriodId(21),
                maxCheckAttempts: 3,
                normalCheckInterval: 5,
                retryCheckInterval: 1,
                activeCheckEnabled: TriStateEnum::False,
                passiveCheckEnabled: TriStateEnum::True,
                isVolatile: TriStateEnum::UseDefault,
            ),
            notifications: new ServiceNotifications(
                enabled: TriStateEnum::True,
                contactIds: new Collection([new NotificationContactId(31), new NotificationContactId(32)], NotificationContactId::class),
                contactGroupIds: new Collection([new ContactGroupId(41)], ContactGroupId::class),
                options: [ServiceNotificationOptionEnum::Recovery, ServiceNotificationOptionEnum::Critical, ServiceNotificationOptionEnum::Warning],
                interval: 30,
                periodId: new TimePeriodId(22),
                firstDelay: 2,
                recoveryDelay: 4,
                contactAdditiveInheritance: true,
                contactGroupAdditiveInheritance: false,
                useOnlyContactsFromHost: true,
            ),
            dataProcessing: new ServiceDataProcessing(
                checkFreshness: TriStateEnum::True,
                flapDetectionEnabled: TriStateEnum::UseDefault,
                eventHandlerEnabled: TriStateEnum::True,
                obsessOverService: TriStateEnum::False,
                retainStatusInformation: TriStateEnum::True,
                retainNonStatusInformation: TriStateEnum::False,
                acknowledgmentTimeout: 15,
                freshnessThreshold: 60,
                lowFlapThreshold: 10,
                highFlapThreshold: 90,
                eventHandlerCommandId: new CommandId(12),
                eventHandlerArgs: ['handler-arg'],
                stalkingOptions: [ServiceStalkingOptionEnum::Critical, ServiceStalkingOptionEnum::Ok],
            ),
            extendedInformations: new ServiceExtendedInformations(
                noteUrl: 'https://example.com/notes',
                note: 'a note',
                actionUrl: 'https://example.com/action',
                iconId: new MediaId(51),
                altIcon: 'alt text',
                comment: 'a comment',
                geoCoordinates: new GeoCoordinates('48.85', '2.35'),
            ),
            graphTemplateId: new GraphTemplateId(61),
            severityId: new ServiceSeverityId(71),
            serviceGroupIds: new Collection([new ServiceGroupId(81)], ServiceGroupId::class),
            categoryIds: new Collection([new ServiceCategoryId(91)], ServiceCategoryId::class),
            trapIds: new Collection([new TrapId(101)], TrapId::class),
        );
    }
}
