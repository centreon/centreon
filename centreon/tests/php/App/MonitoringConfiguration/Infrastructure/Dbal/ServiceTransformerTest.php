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

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceStalkingOptionEnum;
use App\MonitoringConfiguration\Infrastructure\Dbal\ServiceTransformer;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type RowTypeAlias from ServiceTransformer
 */
final class ServiceTransformerTest extends TestCase
{
    public function testAListingRowGivesItsOwnIconAndIntervalsOnly(): void
    {
        $service = (new ServiceTransformer())->transform($this->listingRow());

        self::assertSame(8, $service->extendedInformations?->iconId?->value);
        self::assertNull($service->extendedInformations->note);
        self::assertSame(5, $service->schedulingOptions->normalCheckInterval);
        self::assertSame(1, $service->schedulingOptions->retryCheckInterval);
        self::assertNull($service->notifications);
        self::assertNull($service->checkOptions->checkCommandId);
        self::assertNull($service->severityId);
    }

    public function testOutOfRangeNumbersReadAsUnset(): void
    {
        $service = (new ServiceTransformer())->transform([
            ...$this->formRow(),
            'max_check_attempts' => '0',
            'normal_check_interval' => '0',
            'retry_check_interval' => '-3',
            'acknowledgement_timeout' => '0',
            'freshness_threshold' => '-1',
            'low_flap_threshold' => '101',
            'high_flap_threshold' => '-1',
            'notification_interval' => '-1',
            'first_notification_delay' => '-1',
            'recovery_notification_delay' => '-1',
        ]);

        self::assertSame(
            [null, null, null],
            [$service->schedulingOptions->maxCheckAttempts, $service->schedulingOptions->normalCheckInterval, $service->schedulingOptions->retryCheckInterval],
        );
        self::assertSame(
            [null, null, null, null],
            [$service->dataProcessing->acknowledgmentTimeout, $service->dataProcessing->freshnessThreshold, $service->dataProcessing->lowFlapThreshold, $service->dataProcessing->highFlapThreshold],
        );
        self::assertInstanceOf(ServiceNotifications::class, $service->notifications);
        self::assertSame(
            [null, null, null],
            [$service->notifications->interval, $service->notifications->firstDelay, $service->notifications->recoveryDelay],
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function outOfRangeFlapThresholdProvider(): iterable
    {
        yield 'low below 0' => ['low_flap_threshold', '-1'];

        yield 'low above 100' => ['low_flap_threshold', '101'];

        yield 'high below 0' => ['high_flap_threshold', '-1'];

        yield 'high above 100' => ['high_flap_threshold', '101'];
    }

    /**
     * A flap threshold is a percentage: outside 0..100 on either side, it reads as unset.
     */
    #[DataProvider('outOfRangeFlapThresholdProvider')]
    public function testAFlapThresholdOutsideZeroToOneHundredReadsAsUnset(string $column, string $stored): void
    {
        $row = $column === 'low_flap_threshold'
            ? [...$this->formRow(), 'low_flap_threshold' => $stored]
            : [...$this->formRow(), 'high_flap_threshold' => $stored];
        $dataProcessing = (new ServiceTransformer())->transform($row)->dataProcessing;

        self::assertNull($column === 'low_flap_threshold' ? $dataProcessing->lowFlapThreshold : $dataProcessing->highFlapThreshold);
    }

    public function testNumbersOnTheirBoundsAreKept(): void
    {
        $service = (new ServiceTransformer())->transform([
            ...$this->formRow(),
            'max_check_attempts' => '1',
            'normal_check_interval' => '1',
            'retry_check_interval' => '1',
            'acknowledgement_timeout' => '1',
            'freshness_threshold' => '0',
            'low_flap_threshold' => '0',
            'high_flap_threshold' => '100',
            'notification_interval' => '0',
            'first_notification_delay' => '0',
            'recovery_notification_delay' => '0',
        ]);

        self::assertSame(
            [1, 1, 1],
            [$service->schedulingOptions->maxCheckAttempts, $service->schedulingOptions->normalCheckInterval, $service->schedulingOptions->retryCheckInterval],
        );
        self::assertSame(
            [1, 0, 0, 100],
            [$service->dataProcessing->acknowledgmentTimeout, $service->dataProcessing->freshnessThreshold, $service->dataProcessing->lowFlapThreshold, $service->dataProcessing->highFlapThreshold],
        );
        self::assertSame(
            [0, 0, 0],
            [$service->notifications?->interval, $service->notifications?->firstDelay, $service->notifications?->recoveryDelay],
        );
    }

    /**
     * Notes and URLs stored by legacy above the form bounds are kept as they are.
     */
    public function testOverLongLegacyTextsAreKept(): void
    {
        $service = (new ServiceTransformer())->transform([
            ...$this->formRow(),
            'note' => str_repeat('n', 600),
            'note_url' => str_repeat('u', 3000),
            'action_url' => str_repeat('a', 3000),
        ]);

        self::assertSame(str_repeat('n', 600), $service->extendedInformations?->note);
        self::assertSame(str_repeat('u', 3000), $service->extendedInformations->noteUrl);
        self::assertSame(str_repeat('a', 3000), $service->extendedInformations->actionUrl);
    }

    /**
     * A field cleared outside the form (CLAPI, mass change) can hold an empty or blank string: it reads as unset.
     */
    public function testEmptyOrBlankTextsReadAsUnset(): void
    {
        $service = (new ServiceTransformer())->transform([
            ...$this->formRow(),
            'note' => '',
            'note_url' => '   ',
            'action_url' => '',
            'alt_icon' => ' ',
            'comment' => '',
            'geo_coords' => '',
        ]);

        $extended = $service->extendedInformations;
        self::assertSame(
            [null, null, null, null, null, null],
            [$extended?->note, $extended?->noteUrl, $extended?->actionUrl, $extended?->altIcon, $extended?->comment, $extended?->geoCoordinates],
        );
    }

    public function testItReadsTheStorageLettersOfTheNotificationAndStalkingOptions(): void
    {
        $service = (new ServiceTransformer())->transform([
            ...$this->formRow(),
            'notification_options' => 's,f,r,c,u,w,x',
            'stalking_options' => 'c,u,w,o,x',
        ]);

        self::assertSame(
            [
                ServiceNotificationOptionEnum::Warning,
                ServiceNotificationOptionEnum::Unknown,
                ServiceNotificationOptionEnum::Critical,
                ServiceNotificationOptionEnum::Recovery,
                ServiceNotificationOptionEnum::Flapping,
                ServiceNotificationOptionEnum::DowntimeScheduled,
            ],
            $service->notifications?->options,
        );
        self::assertSame(
            [ServiceStalkingOptionEnum::Ok, ServiceStalkingOptionEnum::Warning, ServiceStalkingOptionEnum::Unknown, ServiceStalkingOptionEnum::Critical],
            $service->dataProcessing->stalkingOptions,
        );
    }

    /**
     * Legacy (mass change, CLAPI) can store "n" next to real options: the real options win.
     */
    public function testNoneStoredNextToRealOptionsIsDropped(): void
    {
        $transformer = new ServiceTransformer();

        self::assertSame(
            [ServiceNotificationOptionEnum::Critical],
            $transformer->transform([...$this->formRow(), 'notification_options' => 'n,c'])->notifications?->options,
        );
        self::assertSame(
            [ServiceNotificationOptionEnum::None],
            $transformer->transform([...$this->formRow(), 'notification_options' => 'n'])->notifications?->options,
        );
    }

    public function testRelationIdListsAreHydrated(): void
    {
        $service = (new ServiceTransformer())->transform([
            ...$this->formRow(),
            'service_group_ids' => ['3', '4'],
            'category_ids' => [5],
            'trap_ids' => ['6', '7'],
            'notification_contact_ids' => ['8'],
            'notification_contact_group_ids' => [9, 10],
        ]);

        self::assertSame([3, 4], $this->idValues($service->serviceGroupIds));
        self::assertSame([5], $this->idValues($service->categoryIds));
        self::assertSame([6, 7], $this->idValues($service->trapIds));
        self::assertInstanceOf(ServiceNotifications::class, $service->notifications);
        self::assertSame([8], $this->idValues($service->notifications->contactIds));
        self::assertSame([9, 10], $this->idValues($service->notifications->contactGroupIds));
    }

    /**
     * @param Collection<covariant AggregateRootId> $ids
     *
     * @return list<int>
     */
    private function idValues(Collection $ids): array
    {
        return array_values(array_map(static fn (AggregateRootId $id): int => $id->value, $ids->toArray()));
    }

    /**
     * @return RowTypeAlias
     */
    private function listingRow(): array
    {
        return [
            'service_id' => 1,
            'service_description' => 'ping',
            'host_id' => 2,
            'host_activate' => '1',
            'host_poller_id' => 3,
            'service_activate' => '1',
            'template_id' => null,
            'icon_id' => 8,
            'normal_check_interval' => 5,
            'retry_check_interval' => 1,
            'macros' => [],
        ];
    }

    /**
     * A findOne() row with nothing set.
     *
     * @return RowTypeAlias
     */
    private function formRow(): array
    {
        return [
            ...$this->listingRow(),
            'icon_id' => null,
            'normal_check_interval' => null,
            'retry_check_interval' => null,
            'check_command_id' => null,
            'check_command_args' => null,
            'check_timeperiod_id' => null,
            'max_check_attempts' => null,
            'active_check_enabled' => '2',
            'passive_check_enabled' => '2',
            'is_volatile' => '2',
            'acknowledgement_timeout' => null,
            'check_freshness' => '2',
            'freshness_threshold' => null,
            'flap_detection_enabled' => '2',
            'low_flap_threshold' => null,
            'high_flap_threshold' => null,
            'event_handler_enabled' => '2',
            'event_handler_command_id' => null,
            'event_handler_args' => null,
            'obsess_over_service' => '2',
            'retain_status_information' => '2',
            'retain_non_status_information' => '2',
            'stalking_options' => null,
            'notifications_enabled' => '2',
            'notification_options' => null,
            'notification_interval' => null,
            'notification_period_id' => null,
            'first_notification_delay' => null,
            'recovery_notification_delay' => null,
            'contact_additive_inheritance' => '0',
            'cg_additive_inheritance' => '0',
            'use_only_contacts_from_host' => '0',
            'notification_contact_ids' => [],
            'notification_contact_group_ids' => [],
            'note' => null,
            'note_url' => null,
            'action_url' => null,
            'alt_icon' => null,
            'comment' => null,
            'geo_coords' => null,
            'graph_template_id' => null,
            'severity_id' => null,
            'service_group_ids' => [],
            'category_ids' => [],
            'trap_ids' => [],
        ];
    }
}
