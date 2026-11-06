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

use App\MonitoringConfiguration\Domain\Aggregate\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalHostTemplateRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalModuleRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\HostTemplateListingTransformer;
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

final class DbalHostTemplateRepositoryAddTest extends KernelTestCase
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
            new HostTemplateListingTransformer(),
            new AccessScopeResolver(new DbalResourceAccessRepository($this->connection, $realTimeConnection, new DbalAccessGroupRepository($this->connection))),
            new DbalModuleRepository($this->connection),
        );
    }

    public function testItStoresAnActiveUnlockedTemplate(): void
    {
        $template = HostTemplate::create($this->templateName(), new HostAlias('Alias'));

        $this->repository->add($template);

        $row = $this->row('SELECT host_register, host_activate, host_locked, host_name FROM host WHERE host_id = ?', [$template->id()->value]);
        self::assertSame(['0', '1', 0, $template->name->value], [$row['host_register'], $row['host_activate'], self::asInt($row['host_locked']), $row['host_name']]);
        self::assertSame(1, $this->rowCount('extended_host_information', $template->id()->value));
    }

    public function testEveryFieldReadsBackAsWritten(): void
    {
        $commandId = $this->insert('command', ['command_name' => $this->uniqueName(), 'command_line' => '$USER1$/check', 'command_type' => 2]);
        $eventHandlerId = $this->insert('command', ['command_name' => $this->uniqueName(), 'command_line' => '$USER1$/handler', 'command_type' => 2]);
        $periodId = $this->insert('timeperiod', ['tp_name' => $this->uniqueName(), 'tp_alias' => 'alias']);
        $timezoneId = $this->insert('timezone', ['timezone_name' => $this->uniqueName(), 'timezone_offset' => '+00:00', 'timezone_dst_offset' => '+00:00']);
        $iconId = $this->insert('view_img', ['img_name' => $this->uniqueName(), 'img_path' => 'icon.png']);
        $firstParentId = $this->insert('host', ['host_name' => $this->uniqueName(), 'host_register' => '0']);
        $secondParentId = $this->insert('host', ['host_name' => $this->uniqueName(), 'host_register' => '0']);
        $categoryId = $this->insert('hostcategories', ['hc_name' => $this->uniqueName(), 'hc_alias' => 'alias']);
        $severityId = $this->insert('hostcategories', ['hc_name' => $this->uniqueName(), 'hc_alias' => 'alias', 'level' => 1]);
        $contactName = $this->uniqueName();
        $contactId = $this->insert('contact', ['contact_name' => $contactName, 'contact_alias' => $contactName, 'contact_register' => '1']);
        $contactGroupId = $this->insert('contactgroup', ['cg_name' => $this->uniqueName(), 'cg_alias' => 'alias']);
        $secondLinkedId = $this->insert('service', ['service_description' => $this->uniqueName(), 'service_register' => '0']);
        $firstLinkedId = $this->insert('service', ['service_description' => $this->uniqueName(), 'service_register' => '0']);

        $template = HostTemplate::create(
            $this->templateName(),
            new HostAlias('Alias'),
            new CheckOptions(
                new CommandId($commandId),
                ['-w', "80\n90"],
                [
                    new HostMacro(new HostMacroName('PORT'), '443', false),
                    new HostMacro(new HostMacroName('PWD'), 'secret', true),
                ],
            ),
            parentTemplateIds: new Collection([new HostTemplateId($secondParentId), new HostTemplateId($firstParentId)], HostTemplateId::class),
            snmpCommunity: new SnmpCommunity('public'),
            snmpVersion: SnmpVersionEnum::Three,
            timezoneId: new TimezoneId($timezoneId),
            severityId: new HostSeverityId($severityId),
            extendedInformations: new ExtendedInformations('https://n.example.com', 'A note', 'https://a.example.com', new MediaId($iconId), 'An icon', 'A comment'),
            schedulingOptions: new SchedulingOptions(new TimePeriodId($periodId), 3, 5, 1, TriStateEnum::True, TriStateEnum::False),
            dataProcessing: new DataProcessing(TriStateEnum::True, TriStateEnum::False, TriStateEnum::True, 15, 60, 10, 40, new CommandId($eventHandlerId), ['restart']),
            notifications: new Notifications(
                TriStateEnum::True,
                new Collection([new NotificationContactId($contactId)], NotificationContactId::class),
                new Collection([new ContactGroupId($contactGroupId)], ContactGroupId::class),
                [NotificationOptionEnum::Down, NotificationOptionEnum::Recovery],
                30,
                new TimePeriodId($periodId),
                2,
                4,
                true,
                false,
            ),
            categoryIds: new Collection([new HostCategoryId($categoryId)], HostCategoryId::class),
            linkedServiceTemplateIds: new Collection([new ServiceTemplateId($secondLinkedId), new ServiceTemplateId($firstLinkedId)], ServiceTemplateId::class),
        );

        $this->repository->add($template);
        $read = $this->repository->findOne($template->id());

        self::assertInstanceOf(HostTemplate::class, $read);
        self::assertEquals($template->checkOptions->args, $read->checkOptions->args);
        self::assertSame($commandId, $read->checkOptions->checkCommandId?->value);
        self::assertSame(
            [['PORT', '443', false], ['PWD', 'secret', true]],
            array_map(static fn (HostMacro $macro): array => [$macro->name->value, $macro->value, $macro->isPassword], $read->checkOptions->macros),
        );
        self::assertSame('Alias', $read->alias?->value);
        self::assertSame('public', $read->snmpCommunity?->value);
        self::assertSame(SnmpVersionEnum::Three, $read->snmpVersion);
        self::assertSame($timezoneId, $read->timezoneId?->value);
        self::assertSame($severityId, $read->severityId?->value);
        self::assertSame([$categoryId], $this->ids($read->categoryIds));
        self::assertSame([$secondParentId, $firstParentId], $this->ids($read->parentTemplateIds));
        self::assertSame([$secondLinkedId, $firstLinkedId], $this->ids($read->linkedServiceTemplateIds));
        self::assertEquals($template->extendedInformations, $read->extendedInformations);
        self::assertEquals($template->schedulingOptions, $read->schedulingOptions);
        self::assertEquals($template->dataProcessing, $read->dataProcessing);
        self::assertSame($template->notifications?->options, $read->notifications?->options);
        self::assertSame(
            [TriStateEnum::True, 30, $periodId, 2, 4, true, false],
            [
                $read->notifications?->enabled,
                $read->notifications?->interval,
                $read->notifications?->periodId?->value,
                $read->notifications?->firstDelay,
                $read->notifications?->recoveryDelay,
                $read->notifications?->contactAdditiveInheritance,
                $read->notifications?->contactGroupAdditiveInheritance,
            ],
        );
        self::assertSame([$contactId], $this->ids($read->notifications->contactIds ?? new Collection([], NotificationContactId::class)));
        self::assertSame([$contactGroupId], $this->ids($read->notifications->contactGroupIds ?? new Collection([], ContactGroupId::class)));

        $id = $template->id()->value;
        self::assertSame(
            [[$secondParentId, 1], [$firstParentId, 2]],
            array_map(
                static fn (array $row): array => [self::asInt($row['host_tpl_id']), self::asInt($row['order'])],
                $this->connection->fetchAllAssociative('SELECT host_tpl_id, `order` FROM host_template_relation WHERE host_host_id = ? ORDER BY `order`', [$id]),
            ),
        );
        self::assertSame(
            [[null, null], [null, null]],
            array_map(
                static fn (array $row): array => [$row['hostgroup_hg_id'], $row['servicegroup_sg_id']],
                $this->connection->fetchAllAssociative('SELECT hostgroup_hg_id, servicegroup_sg_id FROM host_service_relation WHERE host_host_id = ?', [$id]),
            ),
        );
        self::assertSame(
            ['$_HOSTPORT$' => null, '$_HOSTPWD$' => 1],
            array_map(
                static fn (mixed $value): ?int => $value === null ? null : self::asInt($value),
                array_column($this->connection->fetchAllAssociative('SELECT host_macro_name, is_password FROM on_demand_macro_host WHERE host_host_id = ?', [$id]), 'is_password', 'host_macro_name'),
            ),
        );
    }

    public function testATemplateWithoutNotificationsStoresTheDefaults(): void
    {
        $template = HostTemplate::create($this->templateName(), new HostAlias('Alias'));

        $this->repository->add($template);

        $row = $this->row(
            'SELECT host_notifications_enabled, host_notification_options, contact_additive_inheritance FROM host WHERE host_id = ?',
            [$template->id()->value],
        );
        self::assertSame(['2', null, 0], [$row['host_notifications_enabled'], $row['host_notification_options'], self::asInt($row['contact_additive_inheritance'])]);
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
    private function insert(string $table, array $columns): int
    {
        $this->connection->insert($table, $columns);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param list<int> $parameters
     *
     * @return array<string, mixed>
     */
    private function row(string $sql, array $parameters): array
    {
        $row = $this->connection->fetchAssociative($sql, $parameters);
        self::assertIsArray($row);

        return $row;
    }

    private function rowCount(string $table, int $hostId): int
    {
        return self::asInt($this->connection->fetchOne("SELECT COUNT(*) FROM {$table} WHERE host_host_id = ?", [$hostId]));
    }

    private function templateName(): HostTemplateName
    {
        return new HostTemplateName($this->uniqueName());
    }

    private function uniqueName(): string
    {
        return 'ht-' . Uuid::v4()->toBase58();
    }

    private static function asInt(mixed $value): int
    {
        self::assertTrue(is_int($value) || (is_string($value) && is_numeric($value)), 'Expected an integer column value.');

        return (int) $value;
    }
}
