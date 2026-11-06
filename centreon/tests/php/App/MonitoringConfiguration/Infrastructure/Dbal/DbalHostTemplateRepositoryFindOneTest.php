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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacro;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpVersionEnum;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalHostTemplateRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalModuleRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\HostTemplateTransformer;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Security\Infrastructure\Dbal\DbalAccessGroupRepository;
use App\Security\Infrastructure\Dbal\DbalResourceAccessRepository;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalHostTemplateRepositoryFindOneTest extends KernelTestCase
{
    private Connection $connection;

    private DbalHostTemplateRepository $repository;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');

        $this->repository = new DbalHostTemplateRepository(
            $this->connection,
            new HostTemplateTransformer(),
            new AccessScopeResolver(new DbalResourceAccessRepository($this->connection, $realTimeConnection, new DbalAccessGroupRepository($this->connection))),
            new DbalModuleRepository($this->connection),
        );
    }

    public function testItHydratesEveryStoredField(): void
    {
        $checkCommandId = $this->insertCommand();
        $eventHandlerId = $this->insertCommand();
        $checkPeriodId = $this->insertTimePeriod();
        $notificationPeriodId = $this->insertTimePeriod();
        $timezoneId = $this->insertTimezone();
        $iconId = $this->insertImage();
        $secondParentId = $this->insertTemplate();
        $firstParentId = $this->insertTemplate();
        $id = $this->insertTemplate([
            'host_alias' => 'An alias',
            'host_locked' => 1,
            'host_snmp_community' => 'public',
            'host_snmp_version' => '2c',
            'host_location' => $timezoneId,
            'host_comment' => 'A comment',
            'command_command_id' => $checkCommandId,
            'command_command_id_arg1' => '!-w!80#BR#90',
            'timeperiod_tp_id' => $checkPeriodId,
            'host_max_check_attempts' => 3,
            'host_check_interval' => 5,
            'host_retry_check_interval' => 1,
            'host_active_checks_enabled' => '1',
            'host_passive_checks_enabled' => '0',
            'host_check_freshness' => '1',
            'host_freshness_threshold' => 60,
            'host_flap_detection_enabled' => '0',
            'host_low_flap_threshold' => 10,
            'host_high_flap_threshold' => 40,
            'host_event_handler_enabled' => '1',
            'command_command_id2' => $eventHandlerId,
            'command_command_id_arg2' => '!restart',
            'host_acknowledgement_timeout' => 15,
            'host_notifications_enabled' => '1',
            'host_notification_options' => 'r,d',
            'host_notification_interval' => 30,
            'timeperiod_tp_id2' => $notificationPeriodId,
            'host_first_notification_delay' => 2,
            'host_recovery_notification_delay' => 4,
            'contact_additive_inheritance' => 1,
            'cg_additive_inheritance' => 0,
        ]);
        $this->connection->insert('extended_host_information', [
            'host_host_id' => $id,
            'ehi_notes_url' => 'https://notes.example.com',
            'ehi_notes' => 'A note',
            'ehi_action_url' => 'https://action.example.com',
            'ehi_icon_image' => $iconId,
            'ehi_icon_image_alt' => 'An icon',
        ]);
        $this->linkToTemplate($id, $firstParentId, 1);
        $this->linkToTemplate($id, $secondParentId, 2);
        $secondCategoryId = $this->insertCategory(null);
        $firstCategoryId = $this->insertCategory(null);
        $severityId = $this->insertCategory(1);
        foreach ([$secondCategoryId, $severityId, $firstCategoryId] as $categoryId) {
            $this->connection->insert('hostcategories_relation', ['host_host_id' => $id, 'hostcategories_hc_id' => $categoryId]);
        }
        $firstContactId = $this->insertContact();
        $secondContactId = $this->insertContact();
        foreach ([$secondContactId, $firstContactId] as $contactId) {
            $this->connection->insert('contact_host_relation', ['host_host_id' => $id, 'contact_id' => $contactId]);
        }
        $contactGroupId = $this->insertContactGroup();
        $this->connection->insert('contactgroup_host_relation', ['host_host_id' => $id, 'contactgroup_cg_id' => $contactGroupId]);
        $firstLinkedId = $this->insertServiceTemplate();
        $secondLinkedId = $this->insertServiceTemplate();
        foreach ([$secondLinkedId, $firstLinkedId, $secondLinkedId] as $serviceId) {
            $this->connection->insert('host_service_relation', ['host_host_id' => $id, 'service_service_id' => $serviceId]);
        }
        $this->connection->update('service', ['command_command_id' => $checkCommandId], ['service_id' => $firstLinkedId]);
        $this->insertMacro($id, '$_HOSTPORT$', '443', false);
        $this->insertMacro($id, '$_HOSTPWD$', 'secret', true);

        $template = $this->findOne($id);

        self::assertSame('An alias', $template->alias?->value);
        self::assertTrue($template->isLocked);
        self::assertSame('public', $template->snmpCommunity?->value);
        self::assertSame(SnmpVersionEnum::TwoC, $template->snmpVersion);
        self::assertSame($timezoneId, $template->timezoneId?->value);
        self::assertSame($severityId, $template->severityId?->value);
        self::assertSame([$firstParentId, $secondParentId], $this->ids($template->parentTemplateIds));
        self::assertSame([$secondCategoryId, $firstCategoryId], $this->ids($template->categoryIds));
        self::assertSame([$secondLinkedId, $firstLinkedId], $this->ids($template->linkedServiceTemplateIds));
        self::assertSame([$checkCommandId], $this->ids($template->serviceTemplateCheckCommandIds));

        self::assertSame($checkCommandId, $template->checkOptions->checkCommandId?->value);
        self::assertSame(['-w', "80\n90"], $template->checkOptions->args);
        self::assertSame(
            [['PORT', '443', false], ['PWD', 'secret', true]],
            array_map(
                static fn (HostTemplateMacro $macro): array => [$macro->name->value, $macro->value, $macro->isPassword],
                array_values($template->checkOptions->macros->toArray()),
            ),
        );

        $informations = $template->extendedInformations;
        self::assertNotNull($informations);
        self::assertSame(
            ['https://notes.example.com', 'A note', 'https://action.example.com', $iconId, 'An icon', 'A comment'],
            [$informations->noteUrl, $informations->note, $informations->actionUrl, $informations->iconId?->value, $informations->altIcon, $informations->comment],
        );

        $scheduling = $template->schedulingOptions;
        self::assertSame($checkPeriodId, $scheduling->checkTimeperiodId?->value);
        self::assertSame([3, 5, 1], [$scheduling->maxCheckAttempts, $scheduling->normalCheckInterval, $scheduling->retryCheckInterval]);
        self::assertSame([TriStateEnum::True, TriStateEnum::False], [$scheduling->activeCheckEnabled, $scheduling->passiveCheckEnabled]);

        $dataProcessing = $template->dataProcessing;
        self::assertSame(
            [TriStateEnum::True, 60, TriStateEnum::False, 10, 40, TriStateEnum::True, $eventHandlerId, ['restart'], 15],
            [
                $dataProcessing->checkFreshness,
                $dataProcessing->freshnessThreshold,
                $dataProcessing->flapDetectionEnabled,
                $dataProcessing->lowFlapThreshold,
                $dataProcessing->highFlapThreshold,
                $dataProcessing->eventHandlerEnabled,
                $dataProcessing->eventHandlerCommandId?->value,
                $dataProcessing->eventHandlerArgs,
                $dataProcessing->acknowledgmentTimeout,
            ],
        );

        $notifications = $template->notifications;
        self::assertNotNull($notifications);
        self::assertSame(TriStateEnum::True, $notifications->enabled);
        self::assertSame([NotificationOptionEnum::Down, NotificationOptionEnum::Recovery], $notifications->options);
        self::assertSame([30, 2, 4], [$notifications->interval, $notifications->firstDelay, $notifications->recoveryDelay]);
        self::assertSame($notificationPeriodId, $notifications->periodId?->value);
        self::assertSame([$firstContactId, $secondContactId], $this->ids($notifications->contactIds));
        self::assertSame([$contactGroupId], $this->ids($notifications->contactGroupIds));
        self::assertTrue($notifications->contactAdditiveInheritance);
        self::assertFalse($notifications->contactGroupAdditiveInheritance);
    }

    public function testItReadsOutOfBoundValuesAsStored(): void
    {
        $note = str_repeat('n', 600);
        $url = 'https://example.com/' . str_repeat('u', 2980);
        $id = $this->insertTemplate([
            'host_max_check_attempts' => 0,
            'host_check_interval' => 0,
            'host_acknowledgement_timeout' => 0,
            'host_low_flap_threshold' => -1,
            'host_high_flap_threshold' => 150,
            'host_notification_interval' => -1,
            'command_command_id_arg1' => '!-w!80',
        ]);
        $this->connection->insert('extended_host_information', ['host_host_id' => $id, 'ehi_notes' => $note, 'ehi_notes_url' => $url]);

        $template = $this->findOne($id);

        self::assertSame([0, 0], [$template->schedulingOptions->maxCheckAttempts, $template->schedulingOptions->normalCheckInterval]);
        self::assertSame(
            [0, -1, 150],
            [$template->dataProcessing->acknowledgmentTimeout, $template->dataProcessing->lowFlapThreshold, $template->dataProcessing->highFlapThreshold],
        );
        self::assertSame(-1, $template->notifications?->interval);
        self::assertSame($note, $template->extendedInformations?->note);
        self::assertSame($url, $template->extendedInformations->noteUrl);
        self::assertNull($template->checkOptions->checkCommandId);
        self::assertSame(['-w', '80'], $template->checkOptions->args);
    }

    public function testItReadsBlankTextsAndAnUnknownSnmpVersionAsNoValue(): void
    {
        $id = $this->insertTemplate([
            'host_alias' => '  ',
            'host_snmp_community' => ' ',
            'host_snmp_version' => 'v4',
            'host_comment' => '',
            'host_location' => 0,
            'host_notification_options' => '',
        ]);
        $this->connection->insert('extended_host_information', [
            'host_host_id' => $id,
            'ehi_notes' => ' ',
            'ehi_notes_url' => '',
            'ehi_action_url' => '',
            'ehi_icon_image_alt' => '',
        ]);

        $template = $this->findOne($id);

        self::assertNull($template->alias);
        self::assertNull($template->snmpCommunity);
        self::assertNull($template->snmpVersion);
        self::assertNull($template->timezoneId);
        self::assertNull($template->extendedInformations);
        self::assertSame([], $template->notifications?->options);
    }

    public function testTheLowestLevelIsTheSeverityWhenSeveralAreStored(): void
    {
        $id = $this->insertTemplate();
        $levelFiveId = $this->insertCategory(5);
        $levelOneId = $this->insertCategory(1);
        foreach ([$levelOneId, $levelFiveId] as $severityId) {
            $this->connection->insert('hostcategories_relation', ['host_host_id' => $id, 'hostcategories_hc_id' => $severityId]);
        }

        self::assertSame($levelOneId, $this->findOne($id)->severityId?->value);
    }

    public function testTheLowestIdIsTheSeverityAmongSeveralOfTheSameLevel(): void
    {
        $id = $this->insertTemplate();
        $lowerId = $this->insertCategory(2);
        $higherId = $this->insertCategory(2);
        foreach ([$higherId, $lowerId] as $severityId) {
            $this->connection->insert('hostcategories_relation', ['host_host_id' => $id, 'hostcategories_hc_id' => $severityId]);
        }

        self::assertSame($lowerId, $this->findOne($id)->severityId?->value);
    }

    public function testALinkedServiceAndAHostAsParentAreLeftOut(): void
    {
        $id = $this->insertTemplate();
        $serviceTemplateId = $this->insertServiceTemplate();
        $this->connection->insert('service', ['service_description' => $this->uniqueName(), 'service_register' => '1']);
        $serviceId = (int) $this->connection->lastInsertId();
        foreach ([$serviceId, $serviceTemplateId] as $linkedId) {
            $this->connection->insert('host_service_relation', ['host_host_id' => $id, 'service_service_id' => $linkedId]);
        }
        $parentId = $this->insertTemplate();
        $this->connection->insert('host', ['host_name' => $this->uniqueName(), 'host_register' => '1', 'host_activate' => '1']);
        $hostId = (int) $this->connection->lastInsertId();
        $this->linkToTemplate($id, $hostId, 1);
        $this->linkToTemplate($id, $parentId, 2);

        $template = $this->findOne($id);

        self::assertSame([$serviceTemplateId], $this->ids($template->linkedServiceTemplateIds));
        self::assertSame([$parentId], $this->ids($template->parentTemplateIds));
    }

    public function testANoneNotificationOptionNextToRealOnesIsDropped(): void
    {
        $id = $this->insertTemplate(['host_notification_options' => 'n,f,x']);

        self::assertSame([NotificationOptionEnum::Flapping], $this->findOne($id)->notifications?->options);
    }

    public function testAnInactiveTemplateIsFound(): void
    {
        $id = $this->insertTemplate(['host_activate' => '0']);

        self::assertSame($id, $this->findOne($id)->id()->value);
    }

    private function findOne(int $id): HostTemplate
    {
        $template = $this->repository->findOne(new HostTemplateId($id));
        self::assertInstanceOf(HostTemplate::class, $template);

        return $template;
    }

    /**
     * @template T of AggregateRootId
     *
     * @param Collection<T> $ids
     *
     * @return list<int>
     */
    private function ids(Collection $ids): array
    {
        return array_values(array_map(static fn (AggregateRootId $id): int => $id->value, $ids->toArray()));
    }

    /**
     * @param array<string, int|string|null> $columns
     */
    private function insertTemplate(array $columns = []): int
    {
        $this->connection->insert('host', $columns + ['host_name' => $this->uniqueName(), 'host_register' => '0', 'host_activate' => '1']);

        return (int) $this->connection->lastInsertId();
    }

    private function linkToTemplate(int $hostId, int $templateId, int $order): void
    {
        $this->connection->insert('host_template_relation', ['host_host_id' => $hostId, 'host_tpl_id' => $templateId, '`order`' => $order]);
    }

    private function insertCommand(): int
    {
        $this->connection->insert('command', ['command_name' => $this->uniqueName(), 'command_line' => '$USER1$/check', 'command_type' => 2]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertTimePeriod(): int
    {
        $this->connection->insert('timeperiod', ['tp_name' => $this->uniqueName(), 'tp_alias' => 'alias']);

        return (int) $this->connection->lastInsertId();
    }

    private function insertTimezone(): int
    {
        $this->connection->insert('timezone', ['timezone_name' => $this->uniqueName(), 'timezone_offset' => '+00:00', 'timezone_dst_offset' => '+00:00']);

        return (int) $this->connection->lastInsertId();
    }

    private function insertImage(): int
    {
        $name = $this->uniqueName() . '.png';
        $this->connection->insert('view_img', ['img_name' => $name, 'img_path' => $name]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertCategory(?int $level): int
    {
        $this->connection->insert('hostcategories', ['hc_name' => $this->uniqueName(), 'hc_alias' => 'alias', 'hc_activate' => '1', 'level' => $level]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertContact(): int
    {
        $name = $this->uniqueName();
        $this->connection->insert('contact', [
            'contact_name' => $name,
            'contact_alias' => $name,
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => $name . '@example.com',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertContactGroup(): int
    {
        $this->connection->insert('contactgroup', ['cg_name' => $this->uniqueName(), 'cg_alias' => 'alias', 'cg_activate' => '1']);

        return (int) $this->connection->lastInsertId();
    }

    private function insertServiceTemplate(): int
    {
        $this->connection->insert('service', ['service_description' => $this->uniqueName(), 'service_register' => '0']);

        return (int) $this->connection->lastInsertId();
    }

    private function insertMacro(int $hostId, string $name, string $value, bool $isPassword): void
    {
        $this->connection->insert('on_demand_macro_host', [
            'host_host_id' => $hostId,
            'host_macro_name' => $name,
            'host_macro_value' => $value,
            'is_password' => $isPassword ? 1 : null,
            'macro_order' => 0,
        ]);
    }

    private function uniqueName(): string
    {
        return 'ht-' . Uuid::v4()->toBase58();
    }
}
