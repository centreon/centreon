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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupName;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodName;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneName;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\DataProcessingOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCategoryOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCheckOptionsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostExtendedInformationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostGroupOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostNotificationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostPollerOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostSchedulingOptionsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostSeverityOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostTemplateOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostTimezoneOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\RelatedHostOutput;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * Every lookup is indexed by id and resolved by the caller; a name missing from them is left out,
 * as when the related row is gone.
 *
 * @phpstan-import-type ExtraDataTypeAlias from DataProcessingOutputTransformer as DataProcessingExtraDataTypeAlias
 * @phpstan-import-type ExtraDataTypeAlias from HostExtendedInformationsOutputTransformer as ExtendedInformationsExtraDataTypeAlias
 * @phpstan-import-type ExtraDataTypeAlias from HostSchedulingOptionsOutputTransformer as SchedulingOptionsExtraDataTypeAlias
 * @phpstan-import-type ExtraDataTypeAlias from HostCheckOptionsOutputTransformer as CheckOptionsExtraDataTypeAlias
 * @phpstan-import-type ExtraDataTypeAlias from HostNotificationsTransformer as NotificationsExtraDataTypeAlias
 *
 * @phpstan-type ExtraDataTypeAlias array{
 *     pollerNames?: array<int, PollerName>,
 *     groupNames?: array<int, HostGroupName>,
 *     templateNames?: array<int, HostTemplateName>,
 *     categoryNames?: array<int, HostCategoryName>,
 *     hostNames?: array<int, HostName>,
 *     timezoneNames?: array<int, TimezoneName>,
 *     severityNames?: array<int, HostSeverityName>,
 *     commands?: array<int, Command>,
 *     timePeriodNames?: array<int, TimePeriodName>,
 *     icons?: array<int, Media>,
 *     contactNames?: array<int, NotificationContactName>,
 *     contactGroupNames?: array<int, ContactGroupName>,
 * }
 *
 * @implements TransformerInterface<Host, HostResource, ExtraDataTypeAlias>
 */
final readonly class HostResourceTransformer implements TransformerInterface
{
    /**
     * @param TransformerInterface<DataProcessing, DataProcessingOutput, DataProcessingExtraDataTypeAlias> $dataProcessingTransformer
     * @param TransformerInterface<ExtendedInformations, HostExtendedInformationsOutput, ExtendedInformationsExtraDataTypeAlias> $extendedInformationsTransformer
     * @param TransformerInterface<SchedulingOptions, HostSchedulingOptionsOutput, SchedulingOptionsExtraDataTypeAlias> $schedulingOptionsTransformer
     * @param TransformerInterface<CheckOptions, HostCheckOptionsOutput, CheckOptionsExtraDataTypeAlias> $checkOptionsTransformer
     * @param TransformerInterface<?Notifications, ?HostNotificationsOutput, NotificationsExtraDataTypeAlias> $notificationsTransformer
     */
    public function __construct(
        #[Autowire(service: DataProcessingOutputTransformer::class)]
        private TransformerInterface $dataProcessingTransformer,
        #[Autowire(service: HostExtendedInformationsOutputTransformer::class)]
        private TransformerInterface $extendedInformationsTransformer,
        #[Autowire(service: HostSchedulingOptionsOutputTransformer::class)]
        private TransformerInterface $schedulingOptionsTransformer,
        #[Autowire(service: HostCheckOptionsOutputTransformer::class)]
        private TransformerInterface $checkOptionsTransformer,
        #[Autowire(service: HostNotificationsTransformer::class)]
        private TransformerInterface $notificationsTransformer,
    ) {
    }

    public function transform(mixed $from, array $extraData = []): HostResource
    {
        Assert::keyExists($extraData, 'pollerNames');
        Assert::keyExists($extraData, 'groupNames');
        Assert::keyExists($extraData, 'templateNames');
        Assert::keyExists($extraData, 'categoryNames');
        Assert::keyExists($extraData, 'hostNames');
        Assert::keyExists($extraData, 'timezoneNames');
        Assert::keyExists($extraData, 'severityNames');
        Assert::keyExists($extraData, 'commands');
        Assert::keyExists($extraData, 'timePeriodNames');
        Assert::keyExists($extraData, 'icons');
        Assert::keyExists($extraData, 'contactNames');
        Assert::keyExists($extraData, 'contactGroupNames');

        $groups = [];
        foreach ($from->hostGroupIds as $groupId) {
            if (isset($extraData['groupNames'][$groupId->value])) {
                $groups[] = new HostGroupOutput($groupId->value, $extraData['groupNames'][$groupId->value]->value);
            }
        }

        $templates = [];
        foreach ($from->templateIds as $templateId) {
            if (isset($extraData['templateNames'][$templateId->value])) {
                $templates[] = new HostTemplateOutput($templateId->value, $extraData['templateNames'][$templateId->value]->value);
            }
        }

        $categories = [];
        foreach ($from->categoryIds as $categoryId) {
            if (isset($extraData['categoryNames'][$categoryId->value])) {
                $categories[] = new HostCategoryOutput($categoryId->value, $extraData['categoryNames'][$categoryId->value]->value);
            }
        }

        $timezone = $from->timezoneId !== null && isset($extraData['timezoneNames'][$from->timezoneId->value])
            ? new HostTimezoneOutput($from->timezoneId->value, $extraData['timezoneNames'][$from->timezoneId->value]->value)
            : null;
        $severity = $from->severityId !== null && isset($extraData['severityNames'][$from->severityId->value])
            ? new HostSeverityOutput($from->severityId->value, $extraData['severityNames'][$from->severityId->value]->value)
            : null;

        return new HostResource(
            id: $from->id()->value,
            name: $from->name->value,
            alias: $from->alias?->value,
            address: $from->address->value,
            activated: $from->activated,
            snmpVersion: $from->snmpVersion,
            poller: new HostPollerOutput($from->pollerId->value, $extraData['pollerNames'][$from->pollerId->value]->value ?? ''),
            templates: $templates,
            groups: $groups,
            dataProcessing: $this->dataProcessingTransformer->transform(
                $from->dataProcessing,
                ['commands' => $extraData['commands']],
            ),
            categories: $categories,
            parentHosts: $this->toRelatedHosts($from->parentHostIds, $extraData['hostNames']),
            childHosts: $this->toRelatedHosts($from->childHostIds, $extraData['hostNames']),
            timezone: $timezone,
            severity: $severity,
            extendedInformations: $this->extendedInformationsTransformer->transform(
                $from->extendedInformations ?? new ExtendedInformations(),
                ['icons' => $extraData['icons']],
            ),
            schedulingOptions: $this->schedulingOptionsTransformer->transform(
                $from->schedulingOptions,
                ['timePeriodNames' => $extraData['timePeriodNames']],
            ),
            checkOptions: $this->checkOptionsTransformer->transform(
                $from->checkOptions,
                ['commands' => $extraData['commands']],
            ),
            notifications: $this->notificationsTransformer->transform($from->notifications, [
                'contactNames' => $extraData['contactNames'],
                'contactGroupNames' => $extraData['contactGroupNames'],
                'timePeriodNames' => $extraData['timePeriodNames'],
            ]),
        );
    }

    /**
     * @param Collection<HostId> $hostIds
     * @param array<int, HostName> $names indexed by host id
     *
     * @return list<RelatedHostOutput>
     */
    private function toRelatedHosts(Collection $hostIds, array $names): array
    {
        $related = [];
        foreach ($hostIds as $hostId) {
            if (isset($names[$hostId->value])) {
                $related[] = new RelatedHostOutput($hostId->value, $names[$hostId->value]->value);
            }
        }

        return $related;
    }
}
