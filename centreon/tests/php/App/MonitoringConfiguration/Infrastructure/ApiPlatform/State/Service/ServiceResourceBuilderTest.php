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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupName;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaDirectory;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaName;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceSchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceStalkingOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\Trap;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapName;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapVendor;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapVendorId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapVendorName;
use App\MonitoringConfiguration\Domain\Service\InheritedServiceMacrosResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceMacroOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceMacroTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceNotificationsTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceResourceBuilder;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceResourceTransformer;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeContactGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeGraphTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeMediaRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeNotificationContactRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceCategoryRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceSeverityRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimePeriodRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTrapRepository;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;

final class ServiceResourceBuilderTest extends TestCase
{
    private const HOST_ID = 2;
    private const RESTRICTED_USER_ID = 7;

    private FakeHostRepository $hosts;

    private FakeServiceTemplateRepository $templates;

    private FakeCommandRepository $commands;

    private FakeTimePeriodRepository $timePeriods;

    private FakeMediaRepository $media;

    private FakeServiceGroupRepository $serviceGroups;

    private FakeServiceCategoryRepository $categories;

    private FakeServiceSeverityRepository $severities;

    private FakeGraphTemplateRepository $graphTemplates;

    private FakeTrapRepository $traps;

    private FakeNotificationContactRepository $contacts;

    private FakeContactGroupRepository $contactGroups;

    private FakeResourceAccessRepository $resourceAccess;

    protected function setUp(): void
    {
        $this->resourceAccess = new FakeResourceAccessRepository();
        $this->hosts = new FakeHostRepository($this->resourceAccess);
        $this->hosts->seed(new Host(
            id: null,
            name: new HostName('central'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
        ), self::HOST_ID);
        $this->templates = new FakeServiceTemplateRepository();
        $this->commands = new FakeCommandRepository();
        $this->timePeriods = new FakeTimePeriodRepository();
        $this->media = new FakeMediaRepository();
        $this->serviceGroups = new FakeServiceGroupRepository();
        $this->categories = new FakeServiceCategoryRepository();
        $this->severities = new FakeServiceSeverityRepository();
        $this->graphTemplates = new FakeGraphTemplateRepository();
        $this->traps = new FakeTrapRepository();
        $this->contacts = new FakeNotificationContactRepository();
        $this->contactGroups = new FakeContactGroupRepository();
    }

    public function testItBuildsTheServicesOwnValuesWithTheirNamedReferences(): void
    {
        $this->givenTheReferencesOfAFullService();

        $resource = $this->builder()->build($this->fullService(), TestActingUsers::unrestricted());

        self::assertSame([10, 'ping', false], [$resource->id, $resource->name, $resource->activated]);
        self::assertSame([self::HOST_ID, 'central'], [$resource->host->id, $resource->host->name]);
        self::assertSame([20, 'generic-service'], [$resource->template?->id, $resource->template?->name]);

        self::assertSame([30, 'check-ping'], [$resource->checkOptions->command?->id, $resource->checkOptions->command?->name]);
        self::assertSame(['80', '90'], $resource->checkOptions->args);

        $engine = $resource->monitoringEngine;
        self::assertSame([40, '24x7'], [$engine->checkPeriod?->id, $engine->checkPeriod?->name]);
        self::assertSame([3, 5, 1], [$engine->maxCheckAttempts, $engine->normalCheckInterval, $engine->retryCheckInterval]);
        self::assertSame([TriStateEnum::True, TriStateEnum::False, TriStateEnum::True], [$engine->activeCheckEnabled, $engine->passiveCheckEnabled, $engine->isVolatile]);
        self::assertSame(['https://n', 'a note', 'https://a', 'alt', '48.85,2.35'], [$engine->noteUrl, $engine->note, $engine->actionUrl, $engine->altIcon, $engine->geoCoordinates]);
        self::assertSame([50, 'ping.png', '/img/media/icons/ping.png'], [$engine->icon?->id, $engine->icon?->name, $engine->icon?->url]);

        $notifications = $resource->notifications;
        self::assertNotNull($notifications);
        self::assertSame('true', $notifications->enabled);
        self::assertSame([[60, 'alice']], array_map(static fn (object $contact): array => [$contact->id, $contact->name], $notifications->contacts));
        self::assertSame([[61, 'admins']], array_map(static fn (object $group): array => [$group->id, $group->name], $notifications->contactGroups));
        self::assertSame(['warning', 'critical'], $notifications->options);
        self::assertSame([30, 41, 2, 4], [$notifications->interval, $notifications->timeperiod?->id, $notifications->firstDelay, $notifications->recoveryDelay]);
        self::assertSame([true, true, true], [$notifications->useOnlyContactsFromHost, $notifications->contactAdditiveInheritance, $notifications->contactGroupAdditiveInheritance]);

        $advanced = $resource->advanced;
        self::assertSame(
            [TriStateEnum::False, 15, TriStateEnum::True, 60, TriStateEnum::False, 10, 90, TriStateEnum::True, TriStateEnum::False],
            [
                $advanced->obsessOverService,
                $advanced->acknowledgmentTimeout,
                $advanced->checkFreshness,
                $advanced->freshnessThreshold,
                $advanced->flapDetectionEnabled,
                $advanced->lowFlapThreshold,
                $advanced->highFlapThreshold,
                $advanced->retainStatusInformation,
                $advanced->retainNonStatusInformation,
            ],
        );
        self::assertSame(['ok', 'critical'], $advanced->stalkingOptions);
        self::assertSame([TriStateEnum::True, 31, 'handler', ['arg']], [$advanced->eventHandlerEnabled, $advanced->eventHandler?->id, $advanced->eventHandler?->name, $advanced->eventHandlerArgs]);
        self::assertSame('a comment', $advanced->comment);

        self::assertSame([[70, 'web']], array_map(static fn (object $group): array => [$group->id, $group->name], $resource->serviceGroups));
        self::assertSame([[71, 'network']], array_map(static fn (object $category): array => [$category->id, $category->name], $resource->categories));
        self::assertSame([72, 'critical'], [$resource->severity?->id, $resource->severity?->name]);
        self::assertSame([73, 'latency'], [$resource->graphTemplate?->id, $resource->graphTemplate?->name]);
        self::assertNotNull($resource->traps);
        self::assertSame([[74, 'linkDown', ['id' => 75, 'name' => 'Generic']]], array_map(
            static fn (object $trap): array => [$trap->id, $trap->name, $trap->vendor],
            $resource->traps,
        ));
    }

    public function testAServiceWithoutOwnValuesLeavesThemUnset(): void
    {
        $resource = $this->builder()->build($this->service(), TestActingUsers::unrestricted());

        self::assertNull($resource->template);
        self::assertNull($resource->checkOptions->command);
        self::assertSame([], $resource->checkOptions->args);
        self::assertSame([], $resource->checkOptions->macros);
        self::assertNull($resource->monitoringEngine->checkPeriod);
        self::assertNull($resource->monitoringEngine->maxCheckAttempts);
        self::assertNull($resource->monitoringEngine->icon);
        self::assertSame(TriStateEnum::UseDefault, $resource->monitoringEngine->isVolatile);
        self::assertSame(TriStateEnum::UseDefault, $resource->advanced->checkFreshness);
        self::assertSame([], $resource->advanced->stalkingOptions);
        self::assertNull($resource->advanced->eventHandler);
        self::assertSame([], $resource->serviceGroups);
        self::assertSame([], $resource->categories);
        self::assertNull($resource->severity);
        self::assertNull($resource->graphTemplate);
        self::assertSame([], $resource->traps);
    }

    /**
     * The template reference has no foreign key: a template deleted since is left out, not answered as an error.
     * So is any other reference whose name no longer resolves.
     */
    public function testADanglingReferenceIsLeftOut(): void
    {
        $service = $this->service(
            templateId: 404,
            extendedInformations: new ServiceExtendedInformations(iconId: new MediaId(405)),
            serviceGroupIds: [406],
            categoryIds: [407],
            severityId: 408,
            graphTemplateId: 409,
            trapIds: [410],
        );

        $resource = $this->builder()->build($service, TestActingUsers::unrestricted());

        self::assertNull($resource->template);
        self::assertNull($resource->monitoringEngine->icon);
        self::assertSame([], $resource->serviceGroups);
        self::assertSame([], $resource->categories);
        self::assertNull($resource->severity);
        self::assertNull($resource->graphTemplate);
        self::assertSame([], $resource->traps);
    }

    public function testARestrictedActorSeesOnlyTheGroupsCategoriesContactsAndContactGroupsInItsScope(): void
    {
        $this->givenTheReferencesOfAFullService();
        $this->serviceGroups->names[80] = 'hidden-group';
        $this->categories->names[81] = 'hidden-category';
        $this->contacts->names[82] = new NotificationContactName('hidden-contact');
        $this->contactGroups->names[83] = new ContactGroupName('hidden-group');
        $this->resourceAccess->serviceGroupScope = AccessScope::restrictedTo(ServiceGroupId::class, [new ServiceGroupId(70)]);
        $this->resourceAccess->serviceCategoryScope = AccessScope::restrictedTo(ServiceCategoryId::class, [new ServiceCategoryId(71)]);
        $this->resourceAccess->accessibleContactIds = new Collection([new NotificationContactId(60)], NotificationContactId::class);
        $this->resourceAccess->accessibleContactGroupIds = new Collection([new ContactGroupId(61)], ContactGroupId::class);

        $resource = $this->builder()->build(
            $this->fullService(serviceGroupIds: [70, 80], categoryIds: [71, 81], contactIds: [60, 82], contactGroupIds: [61, 83]),
            TestActingUsers::restricted(self::RESTRICTED_USER_ID),
        );

        self::assertSame([70], array_map(static fn (object $group): int => $group->id, $resource->serviceGroups));
        self::assertSame([71], array_map(static fn (object $category): int => $category->id, $resource->categories));
        self::assertNotNull($resource->notifications);
        self::assertSame([60], array_map(static fn (object $contact): int => $contact->id, $resource->notifications->contacts));
        self::assertSame([61], array_map(static fn (object $group): int => $group->id, $resource->notifications->contactGroups));
        // Not ACL-scoped: the host, the template, the commands, the time periods, the traps and the graph template.
        self::assertSame(self::HOST_ID, $resource->host->id);
        self::assertSame(20, $resource->template?->id);
        self::assertSame(30, $resource->checkOptions->command?->id);
        self::assertNotNull($resource->traps);
        self::assertCount(1, $resource->traps);
        self::assertSame(73, $resource->graphTemplate?->id);
    }

    public function testOnACloudPlatformTheOnPremiseOnlyFieldsAreLeftOut(): void
    {
        $this->givenTheReferencesOfAFullService();

        $resource = $this->builder(isCloudPlatform: true)->build($this->fullService(), TestActingUsers::unrestricted());

        self::assertNull($resource->notifications);
        self::assertSame(
            [null, null, null],
            [$resource->monitoringEngine->activeCheckEnabled, $resource->monitoringEngine->passiveCheckEnabled, $resource->monitoringEngine->isVolatile],
        );
        $advanced = $resource->advanced;
        self::assertSame(
            [null, null, null, null, null, null, null, null],
            [
                $advanced->acknowledgmentTimeout,
                $advanced->flapDetectionEnabled,
                $advanced->lowFlapThreshold,
                $advanced->highFlapThreshold,
                $advanced->obsessOverService,
                $advanced->retainStatusInformation,
                $advanced->retainNonStatusInformation,
                $advanced->stalkingOptions,
            ],
        );
        self::assertSame([], $advanced->eventHandlerArgs);
        self::assertNull($resource->graphTemplate);
        self::assertNull($resource->traps);
        // Returned as stored on Cloud too, as for hosts.
        self::assertSame('alt', $resource->monitoringEngine->altIcon);
        self::assertSame('a comment', $advanced->comment);
        // Platform-agnostic fields stay.
        self::assertSame(3, $resource->monitoringEngine->maxCheckAttempts);
        self::assertSame(TriStateEnum::True, $advanced->checkFreshness);
        self::assertSame(31, $advanced->eventHandler?->id);
    }

    public function testTheMacrosAreTheEffectiveOnesWithoutAnyPasswordValue(): void
    {
        $this->commands->commands[30] = $this->command(30, 'check-ping', '$USER1$/check -a $_SERVICEFROMCOMMAND$');
        $this->templates->names[20] = 'generic-service';
        $this->templates->templates[20] = new ServiceTemplate(
            new ServiceTemplateId(20),
            new ServiceTemplateName('generic-service'),
            macros: new Collection([
                new ServiceMacro(new ServiceMacroName('shadowed'), 'from-template', isPassword: false, id: new ServiceMacroId(21)),
                new ServiceMacro(new ServiceMacroName('inherited'), 'tpl', isPassword: true, id: new ServiceMacroId(22)),
            ], ServiceMacro::class),
        );

        $resource = $this->builder()->build($this->service(
            templateId: 20,
            checkOptions: new ServiceCheckOptions(new CommandId(30), [], [
                new ServiceMacro(new ServiceMacroName('shadowed'), 'own', isPassword: false, id: new ServiceMacroId(1)),
                new ServiceMacro(new ServiceMacroName('secret'), 'secret::vault::path::_SERVICESECRET', isPassword: true, id: new ServiceMacroId(2)),
            ]),
        ), TestActingUsers::unrestricted());

        self::assertSame(
            [
                [1, 'SHADOWED', 'own', false, null],
                [2, 'SECRET', null, true, null],
                [22, 'INHERITED', null, true, 'template'],
                [null, 'FROMCOMMAND', '', false, 'command'],
            ],
            array_map(
                static fn (ServiceMacroOutput $macro): array => [$macro->id, $macro->name, $macro->value, $macro->isPassword, $macro->parent],
                $resource->checkOptions->macros,
            ),
        );
    }

    /**
     * A note stored by legacy above the form bounds is returned as it is.
     */
    public function testAnOverLongLegacyNoteIsReturnedAsStored(): void
    {
        $service = $this->service(extendedInformations: new ServiceExtendedInformations(
            noteUrl: str_repeat('u', 3000),
            note: str_repeat('n', 600),
            actionUrl: str_repeat('a', 3000),
        ));

        $engine = $this->builder()->build($service, TestActingUsers::unrestricted())->monitoringEngine;

        self::assertSame(str_repeat('n', 600), $engine->note);
        self::assertSame(str_repeat('u', 3000), $engine->noteUrl);
        self::assertSame(str_repeat('a', 3000), $engine->actionUrl);
    }

    private function builder(bool $isCloudPlatform = false): ServiceResourceBuilder
    {
        return new ServiceResourceBuilder(
            transformer: new ServiceResourceTransformer(),
            notificationsTransformer: new ServiceNotificationsTransformer($this->contacts, $this->contactGroups, $this->timePeriods),
            hostRepository: $this->hosts,
            serviceTemplateRepository: $this->templates,
            commandRepository: $this->commands,
            timePeriodRepository: $this->timePeriods,
            mediaRepository: $this->media,
            mediaUrlGenerator: new MediaUrlGenerator('/img/media/', $this->requestStack()),
            serviceGroupRepository: $this->serviceGroups,
            serviceCategoryRepository: $this->categories,
            serviceSeverityRepository: $this->severities,
            graphTemplateRepository: $this->graphTemplates,
            trapRepository: $this->traps,
            accessScopes: new AccessScopeResolver($this->resourceAccess),
            macroTransformer: new ServiceMacroTransformer(),
            inheritedServiceMacrosResolver: new InheritedServiceMacrosResolver($this->templates, $this->commands),
            isCloudPlatform: $isCloudPlatform,
        );
    }

    private function requestStack(): RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/'));

        return $requestStack;
    }

    private function givenTheReferencesOfAFullService(): void
    {
        $this->templates->names[20] = 'generic-service';
        $this->commands->commands[30] = $this->command(30, 'check-ping', '$USER1$/check');
        $this->commands->commands[31] = $this->command(31, 'handler', '$USER1$/handle');
        $this->timePeriods->names[40] = '24x7';
        $this->timePeriods->names[41] = 'workhours';
        $this->media->media[50] = new Media(new MediaId(50), new MediaName('ping.png'), new MediaDirectory('icons'));
        $this->contacts->names[60] = new NotificationContactName('alice');
        $this->contactGroups->names[61] = new ContactGroupName('admins');
        $this->serviceGroups->names[70] = 'web';
        $this->categories->names[71] = 'network';
        $this->severities->names[72] = 'critical';
        $this->graphTemplates->names[73] = 'latency';
        $this->traps->traps[74] = new Trap(
            new TrapId(74),
            new TrapName('linkDown'),
            new TrapVendor(new TrapVendorId(75), new TrapVendorName('Generic')),
        );
    }

    /**
     * @param list<int> $serviceGroupIds
     * @param list<int> $categoryIds
     * @param list<int> $contactIds
     * @param list<int> $contactGroupIds
     */
    private function fullService(
        array $serviceGroupIds = [70],
        array $categoryIds = [71],
        array $contactIds = [60],
        array $contactGroupIds = [61],
    ): Service {
        return $this->service(
            activated: false,
            templateId: 20,
            checkOptions: new ServiceCheckOptions(new CommandId(30), ['80', '90']),
            schedulingOptions: new ServiceSchedulingOptions(
                checkTimeperiodId: new TimePeriodId(40),
                maxCheckAttempts: 3,
                normalCheckInterval: 5,
                retryCheckInterval: 1,
                activeCheckEnabled: TriStateEnum::True,
                passiveCheckEnabled: TriStateEnum::False,
                isVolatile: TriStateEnum::True,
            ),
            notifications: new ServiceNotifications(
                enabled: TriStateEnum::True,
                contactIds: new Collection(array_map(static fn (int $id): NotificationContactId => new NotificationContactId($id), $contactIds), NotificationContactId::class),
                contactGroupIds: new Collection(array_map(static fn (int $id): ContactGroupId => new ContactGroupId($id), $contactGroupIds), ContactGroupId::class),
                options: [ServiceNotificationOptionEnum::Critical, ServiceNotificationOptionEnum::Warning],
                interval: 30,
                periodId: new TimePeriodId(41),
                firstDelay: 2,
                recoveryDelay: 4,
                contactAdditiveInheritance: true,
                contactGroupAdditiveInheritance: true,
                useOnlyContactsFromHost: true,
            ),
            dataProcessing: new ServiceDataProcessing(
                checkFreshness: TriStateEnum::True,
                flapDetectionEnabled: TriStateEnum::False,
                eventHandlerEnabled: TriStateEnum::True,
                obsessOverService: TriStateEnum::False,
                retainStatusInformation: TriStateEnum::True,
                retainNonStatusInformation: TriStateEnum::False,
                acknowledgmentTimeout: 15,
                freshnessThreshold: 60,
                lowFlapThreshold: 10,
                highFlapThreshold: 90,
                eventHandlerCommandId: new CommandId(31),
                eventHandlerArgs: ['arg'],
                stalkingOptions: [ServiceStalkingOptionEnum::Critical, ServiceStalkingOptionEnum::Ok],
            ),
            extendedInformations: new ServiceExtendedInformations(
                noteUrl: 'https://n',
                note: 'a note',
                actionUrl: 'https://a',
                iconId: new MediaId(50),
                altIcon: 'alt',
                comment: 'a comment',
                geoCoordinates: new GeoCoordinates('48.85', '2.35'),
            ),
            serviceGroupIds: $serviceGroupIds,
            categoryIds: $categoryIds,
            severityId: 72,
            graphTemplateId: 73,
            trapIds: [74],
        );
    }

    /**
     * @param list<int> $serviceGroupIds
     * @param list<int> $categoryIds
     * @param list<int> $trapIds
     */
    private function service(
        bool $activated = true,
        ?int $templateId = null,
        ServiceCheckOptions $checkOptions = new ServiceCheckOptions(null),
        ServiceSchedulingOptions $schedulingOptions = new ServiceSchedulingOptions(),
        ?ServiceNotifications $notifications = null,
        ServiceDataProcessing $dataProcessing = new ServiceDataProcessing(),
        ?ServiceExtendedInformations $extendedInformations = null,
        array $serviceGroupIds = [],
        array $categoryIds = [],
        ?int $severityId = null,
        ?int $graphTemplateId = null,
        array $trapIds = [],
    ): Service {
        return new Service(
            id: new ServiceId(10),
            name: new ServiceName('ping'),
            hostId: new HostId(self::HOST_ID),
            hostActivated: true,
            hostPollerId: new PollerId(1),
            activated: $activated,
            templateId: $templateId !== null ? new ServiceTemplateId($templateId) : null,
            checkOptions: $checkOptions,
            schedulingOptions: $schedulingOptions,
            notifications: $notifications,
            dataProcessing: $dataProcessing,
            extendedInformations: $extendedInformations,
            graphTemplateId: $graphTemplateId !== null ? new GraphTemplateId($graphTemplateId) : null,
            severityId: $severityId !== null ? new ServiceSeverityId($severityId) : null,
            serviceGroupIds: new Collection(array_map(static fn (int $id): ServiceGroupId => new ServiceGroupId($id), $serviceGroupIds), ServiceGroupId::class),
            categoryIds: new Collection(array_map(static fn (int $id): ServiceCategoryId => new ServiceCategoryId($id), $categoryIds), ServiceCategoryId::class),
            trapIds: new Collection(array_map(static fn (int $id): TrapId => new TrapId($id), $trapIds), TrapId::class),
        );
    }

    private function command(int $id, string $name, string $line): Command
    {
        return new Command(
            new CommandId($id),
            new CommandName($name),
            CommandTypeEnum::Check,
            new CommandLine($line),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: false,
            connector: null,
            comment: null,
        );
    }
}
