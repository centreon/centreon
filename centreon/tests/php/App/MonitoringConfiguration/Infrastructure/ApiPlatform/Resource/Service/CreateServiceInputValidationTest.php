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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostState;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\Trap;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapName;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapVendor;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapVendorId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapVendorName;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\CreateServiceInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceAdvancedInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceCheckOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceMonitoringEngineInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceNotificationsInput;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleContactGroupsValidator;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleContactsValidator;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHostValidator;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleServiceCategoriesValidator;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleServiceGroupsValidator;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleServiceSeverityValidator;
use App\MonitoringConfiguration\Infrastructure\Validator\CheckCommandTypeValidator;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingGraphTemplateValidator;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingMediaValidator;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingServiceTemplateValidator;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingTimePeriodValidator;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingTrapsValidator;
use App\MonitoringConfiguration\Infrastructure\Validator\ServiceCommandInheritanceValidator;
use App\MonitoringConfiguration\Infrastructure\Validator\UniqueServiceNameOnHostValidator;
use App\MonitoringConfiguration\Infrastructure\Validator\ValidEventHandlerCommandValidator;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\Role;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Infrastructure\Validator\Constraints\WhenPlatformValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeContactGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeGraphTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeMediaRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeNotificationContactRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceCategoryRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceSeverityRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimePeriodRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTrapRepository;

/**
 * Every rule of the create payload, at the level of the input DTO: validated with its attribute constraints and
 * the reference validators backed by fakes. The paths are the PHP property paths; the HTTP layer renders them in
 * snake_case (pinned once, by the processor's HTTP test).
 *
 * The references that exist: host 10 (service "disk usage" on it), template 30 (with a check command), check
 * command 5, event handler 6, time period 40, media 50, graph template 60, severity 70, service group 80,
 * category 90, trap 100, contact 31, contact group 41.
 */
final class CreateServiceInputValidationTest extends TestCase
{
    public function testAValidPayloadRaisesNoViolation(): void
    {
        self::assertSame([], $this->violations($this->fullInput()));
    }

    public function testAMinimalPayloadRaisesNoViolation(): void
    {
        self::assertSame([], $this->violations(new CreateServiceInput(name: 'ping', hostId: 10, checkOptions: new ServiceCheckOptionsInput(commandId: 5))));
    }

    /**
     * @param array{name?: string, hostId?: int, serviceTemplateId?: ?int, serviceGroupIds?: list<int>, categoryIds?: list<int>, severityId?: ?int, trapIds?: list<int>, graphTemplateId?: ?int, checkOptions?: ?ServiceCheckOptionsInput, monitoringEngine?: ?ServiceMonitoringEngineInput, notifications?: ?ServiceNotificationsInput, advanced?: ?ServiceAdvancedInput} $changes the constructor arguments that differ from the valid payload
     * @param array<string, string> $expected property path => message
     */
    #[DataProvider('onPremiseRuleProvider')]
    public function testOnPremiseRules(array $changes, array $expected): void
    {
        ksort($expected);
        self::assertSame($expected, $this->violations($this->fullInput(...$changes)));
    }

    /**
     * @return iterable<string, array{array{name?: string, hostId?: int, serviceTemplateId?: ?int, serviceGroupIds?: list<int>, categoryIds?: list<int>, severityId?: ?int, trapIds?: list<int>, graphTemplateId?: ?int, checkOptions?: ?ServiceCheckOptionsInput, monitoringEngine?: ?ServiceMonitoringEngineInput, notifications?: ?ServiceNotificationsInput, advanced?: ?ServiceAdvancedInput}, array<string, string>}>
     */
    public static function onPremiseRuleProvider(): iterable
    {
        yield 'blank name' => [['name' => '   '], ['name' => 'This value should not be blank.']];

        yield 'name above 200 characters' => [['name' => str_repeat('a', 201)], ['name' => 'This value is too long. It should have 200 characters or less.']];

        yield 'name with a forbidden character' => [
            ['name' => 'a$b'],
            ['name' => 'This value must not contain any of the following characters: ~ ! $ % ^ & * " | \' < > ? , ( ) ='],
        ];

        yield 'name already used on the host' => [['name' => ' DISK   usage'], ['name' => 'This name is already used by another service on this host.']];

        yield 'host not positive' => [['hostId' => 0], ['hostId' => 'This value should be positive.']];

        yield 'unknown host' => [['hostId' => 999], ['hostId' => 'This host does not exist or is not accessible.']];

        yield 'unknown template' => [['serviceTemplateId' => 999], ['serviceTemplateId' => 'This service template does not exist.']];

        yield 'service group not positive' => [['serviceGroupIds' => [0]], ['serviceGroupIds[0]' => 'This value should be positive.']];

        yield 'template not positive' => [['serviceTemplateId' => 0], ['serviceTemplateId' => 'This value should be positive.']];

        yield 'category not positive' => [['categoryIds' => [0]], ['categoryIds[0]' => 'This value should be positive.']];

        yield 'severity not positive' => [['severityId' => 0], ['severityId' => 'This value should be positive.']];

        yield 'trap not positive' => [['trapIds' => [-1]], ['trapIds[0]' => 'This value should be positive.']];

        yield 'graph template not positive' => [['graphTemplateId' => 0], ['graphTemplateId' => 'This value should be positive.']];

        yield 'check command not positive' => [
            ['checkOptions' => new ServiceCheckOptionsInput(commandId: 0)],
            ['checkOptions.commandId' => 'This value should be positive.'],
        ];

        yield 'check period and icon not positive' => [
            ['monitoringEngine' => new ServiceMonitoringEngineInput(checkTimeperiodId: 0, iconId: 0)],
            [
                'monitoringEngine.checkTimeperiodId' => 'This value should be positive.',
                'monitoringEngine.iconId' => 'This value should be positive.',
            ],
        ];

        yield 'contact, contact group and notification period not positive' => [
            ['notifications' => new ServiceNotificationsInput(contacts: [0], contactGroups: [-1], timeperiodId: 0)],
            [
                'notifications.contacts[0]' => 'This value should be positive.',
                'notifications.contactGroups[0]' => 'This value should be positive.',
                'notifications.timeperiodId' => 'This value should be positive.',
            ],
        ];

        yield 'event handler not positive' => [
            ['advanced' => new ServiceAdvancedInput(eventHandlerCommandId: 0)],
            ['advanced.eventHandlerCommandId' => 'This value should be positive.'],
        ];

        yield 'unknown service group' => [['serviceGroupIds' => [80, 999]], ['serviceGroupIds' => 'One or more service groups do not exist or are not accessible.']];

        yield 'unknown category' => [['categoryIds' => [999]], ['categoryIds' => 'One or more service categories do not exist or are not accessible.']];

        yield 'unknown severity' => [['severityId' => 999], ['severityId' => 'This service severity does not exist or is not accessible.']];

        yield 'unknown trap' => [['trapIds' => [999]], ['trapIds' => 'One or more traps do not exist.']];

        yield 'unknown graph template' => [['graphTemplateId' => 999], ['graphTemplateId' => 'This graph template does not exist.']];

        yield 'check command of another type' => [
            ['checkOptions' => new ServiceCheckOptionsInput(commandId: 6)],
            ['checkOptions.commandId' => 'This command does not exist or is not a check command.'],
        ];

        yield 'no check command and no template' => [
            ['serviceTemplateId' => null, 'checkOptions' => null],
            ['checkOptions.commandId' => 'A check command is required when no service template is set.'],
        ];

        yield 'argument with the delimiter' => [
            ['checkOptions' => new ServiceCheckOptionsInput(commandId: 5, args: ['a!b'])],
            ['checkOptions.args[0]' => 'A check command argument cannot contain "!".'],
        ];

        yield 'arguments partly filled' => [
            ['checkOptions' => new ServiceCheckOptionsInput(commandId: 5, args: ['a', ''])],
            ['checkOptions.args' => 'Either fill every argument or leave them all empty.'],
        ];

        yield 'unknown check period' => [
            ['monitoringEngine' => new ServiceMonitoringEngineInput(checkTimeperiodId: 999)],
            ['monitoringEngine.checkTimeperiodId' => 'This time period does not exist.'],
        ];

        yield 'max check attempts below 1' => [
            ['monitoringEngine' => new ServiceMonitoringEngineInput(maxCheckAttempts: 0, normalCheckInterval: 0, retryCheckInterval: 0)],
            [
                'monitoringEngine.maxCheckAttempts' => 'This value should be greater than or equal to 1.',
                'monitoringEngine.normalCheckInterval' => 'This value should be greater than or equal to 1.',
                'monitoringEngine.retryCheckInterval' => 'This value should be greater than or equal to 1.',
            ],
        ];

        yield 'note above 512 characters' => [
            ['monitoringEngine' => new ServiceMonitoringEngineInput(note: str_repeat('n', 513))],
            ['monitoringEngine.note' => 'This value is too long. It should have 512 characters or less.'],
        ];

        yield 'urls above 2048 characters' => [
            ['monitoringEngine' => new ServiceMonitoringEngineInput(noteUrl: str_repeat('u', 2049), actionUrl: str_repeat('a', 2049))],
            [
                'monitoringEngine.noteUrl' => 'This value is too long. It should have 2048 characters or less.',
                'monitoringEngine.actionUrl' => 'This value is too long. It should have 2048 characters or less.',
            ],
        ];

        yield 'blank note' => [['monitoringEngine' => new ServiceMonitoringEngineInput(note: '  ')], ['monitoringEngine.note' => 'This value should not be blank.']];

        yield 'unknown icon' => [['monitoringEngine' => new ServiceMonitoringEngineInput(iconId: 999)], ['monitoringEngine.iconId' => 'This media does not exist.']];

        yield 'alt icon above 200 characters' => [
            ['monitoringEngine' => new ServiceMonitoringEngineInput(altIcon: str_repeat('a', 201))],
            ['monitoringEngine.altIcon' => 'This value is too long. It should have 200 characters or less.'],
        ];

        yield 'invalid coordinates' => [
            ['monitoringEngine' => new ServiceMonitoringEngineInput(geoCoordinates: '100,0')],
            ['monitoringEngine.geoCoordinates' => 'This value must be a "latitude,longitude" pair (latitude between -90 and 90, longitude between -180 and 180).'],
        ];

        yield 'unknown notification enabled value' => [
            ['notifications' => new ServiceNotificationsInput(enabled: 'yes')],
            ['notifications.enabled' => 'The value you selected is not a valid choice.'],
        ];

        yield 'unknown notification option' => [
            ['notifications' => new ServiceNotificationsInput(options: ['down'])],
            ['notifications.options[0]' => 'The value you selected is not a valid choice.'],
        ];

        yield 'none with another option' => [
            ['notifications' => new ServiceNotificationsInput(options: ['none', 'warning'])],
            ['notifications.options' => 'The "none" notification option cannot be combined with any other option.'],
        ];

        yield 'negative notification delays' => [
            ['notifications' => new ServiceNotificationsInput(interval: -1, firstDelay: -1, recoveryDelay: -1)],
            [
                'notifications.interval' => 'This value should be either positive or zero.',
                'notifications.firstDelay' => 'This value should be either positive or zero.',
                'notifications.recoveryDelay' => 'This value should be either positive or zero.',
            ],
        ];

        yield 'unknown contact' => [
            ['notifications' => new ServiceNotificationsInput(contacts: [999])],
            ['notifications.contacts' => 'One or more contacts do not exist or are not accessible.'],
        ];

        yield 'unknown contact group' => [
            ['notifications' => new ServiceNotificationsInput(contactGroups: [999])],
            ['notifications.contactGroups' => 'One or more contact groups do not exist or are not accessible.'],
        ];

        yield 'unknown notification period' => [
            ['notifications' => new ServiceNotificationsInput(timeperiodId: 999)],
            ['notifications.timeperiodId' => 'This time period does not exist.'],
        ];

        yield 'acknowledgment timeout below 1' => [
            ['advanced' => new ServiceAdvancedInput(acknowledgmentTimeout: 0)],
            ['advanced.acknowledgmentTimeout' => 'This value should be positive.'],
        ];

        yield 'negative freshness threshold' => [
            ['advanced' => new ServiceAdvancedInput(freshnessThreshold: -1)],
            ['advanced.freshnessThreshold' => 'This value should be either positive or zero.'],
        ];

        yield 'flap thresholds out of range' => [
            ['advanced' => new ServiceAdvancedInput(lowFlapThreshold: -1, highFlapThreshold: 101)],
            [
                'advanced.lowFlapThreshold' => 'This value should be between 0 and 100.',
                'advanced.highFlapThreshold' => 'This value should be between 0 and 100.',
            ],
        ];

        yield 'unknown stalking option' => [
            ['advanced' => new ServiceAdvancedInput(stalkingOptions: ['ok', 'down'])],
            ['advanced.stalkingOptions[1]' => 'The value you selected is not a valid choice.'],
        ];

        yield 'unknown event handler' => [
            ['advanced' => new ServiceAdvancedInput(eventHandlerCommandId: 999)],
            ['advanced.eventHandlerCommandId' => 'This command does not exist.'],
        ];

        yield 'event handler argument with the delimiter' => [
            ['advanced' => new ServiceAdvancedInput(eventHandlerCommandId: 6, eventHandlerArgs: ['a!b'])],
            ['advanced.eventHandlerArgs[0]' => 'An event handler argument cannot contain "!".'],
        ];

        yield 'event handler arguments without any event handler' => [
            ['serviceTemplateId' => null, 'advanced' => new ServiceAdvancedInput(eventHandlerArgs: ['a'])],
            ['advanced.eventHandlerArgs' => 'Event handler arguments require an event handler, set on the service or provided by its service template.'],
        ];

        yield 'blank comment' => [['advanced' => new ServiceAdvancedInput(comment: ' ')], ['advanced.comment' => 'This value should not be blank.']];

        // The comment fills a TEXT column, bounded in bytes: 32768 two-byte characters are 65536 bytes.
        yield 'comment above 65535 bytes' => [
            ['advanced' => new ServiceAdvancedInput(comment: str_repeat('é', 32768))],
            ['advanced.comment' => 'This value is too long. It should have 65535 bytes or less.'],
        ];

        yield 'comment at 65535 bytes' => [['advanced' => new ServiceAdvancedInput(comment: str_repeat('é', 32767) . 'c')], []];
    }

    /**
     * The length is measured on the name as it will be stored, its inner runs of spaces collapsed.
     */
    public function testNameLengthIsMeasuredAfterCollapse(): void
    {
        self::assertSame([], $this->violations($this->fullInput(name: str_repeat('a', 100) . str_repeat(' ', 10) . str_repeat('b', 95))));
        self::assertSame(
            ['name' => 'This value is too long. It should have 200 characters or less.'],
            $this->violations($this->fullInput(name: str_repeat('a', 100) . ' ' . str_repeat('b', 100))),
        );
    }

    /**
     * Control characters are refused on the raw name, before any collapse would turn them into a space.
     */
    public function testControlCharactersAreRejectedBeforeCollapse(): void
    {
        foreach (["a\t\tb", "a\tb", "a\nb"] as $name) {
            self::assertSame(['name' => 'This value must not contain control characters.'], $this->violations($this->fullInput(name: $name)), $name);
        }
    }

    /**
     * @param array{serviceTemplateId?: ?int, trapIds?: list<int>, graphTemplateId?: ?int, monitoringEngine?: ?ServiceMonitoringEngineInput, notifications?: ?ServiceNotificationsInput, advanced?: ?ServiceAdvancedInput} $changes
     * @param array<string, string> $expected
     */
    #[DataProvider('cloudRuleProvider')]
    public function testCloudRules(array $changes, array $expected): void
    {
        ksort($expected);
        self::assertSame($expected, $this->violations($this->cloudInput(...$changes), isCloudPlatform: true));
    }

    /**
     * @return iterable<string, array{array{serviceTemplateId?: ?int, trapIds?: list<int>, graphTemplateId?: ?int, monitoringEngine?: ?ServiceMonitoringEngineInput, notifications?: ?ServiceNotificationsInput, advanced?: ?ServiceAdvancedInput}, array<string, string>}>
     */
    public static function cloudRuleProvider(): iterable
    {
        yield 'valid' => [[], []];

        yield 'no template' => [['serviceTemplateId' => null], ['serviceTemplateId' => 'This value should not be null.']];

        yield 'notifications' => [['notifications' => new ServiceNotificationsInput()], ['notifications' => 'Notifications are not available on a Cloud platform.']];

        yield 'traps' => [['trapIds' => [100]], ['trapIds' => 'This field is not available on a Cloud platform.']];

        yield 'graph template' => [['graphTemplateId' => 60], ['graphTemplateId' => 'This field is not available on a Cloud platform.']];

        yield 'on-premise scheduling directives' => [
            ['monitoringEngine' => new ServiceMonitoringEngineInput(activeCheckEnabled: TriStateEnum::True, passiveCheckEnabled: TriStateEnum::True, isVolatile: TriStateEnum::True)],
            [
                'monitoringEngine.activeCheckEnabled' => 'active_check_enabled is not available on a Cloud platform.',
                'monitoringEngine.passiveCheckEnabled' => 'passive_check_enabled is not available on a Cloud platform.',
                'monitoringEngine.isVolatile' => 'is_volatile is not available on a Cloud platform.',
            ],
        ];

        yield 'alt icon' => [
            ['monitoringEngine' => new ServiceMonitoringEngineInput(altIcon: 'alt')],
            ['monitoringEngine.altIcon' => 'This field is not available on a Cloud platform.'],
        ];

        yield 'on-premise advanced settings' => [
            ['advanced' => new ServiceAdvancedInput(
                obsessOverService: TriStateEnum::True,
                acknowledgmentTimeout: 5,
                flapDetectionEnabled: TriStateEnum::True,
                lowFlapThreshold: 10,
                highFlapThreshold: 90,
                retainStatusInformation: TriStateEnum::True,
                retainNonStatusInformation: TriStateEnum::True,
                stalkingOptions: ['ok'],
                eventHandlerCommandId: 6,
                eventHandlerArgs: ['arg'],
                comment: 'a comment',
            )],
            [
                'advanced.obsessOverService' => 'obsess_over_service is not available on a Cloud platform.',
                'advanced.acknowledgmentTimeout' => 'acknowledgment_timeout is not available on a Cloud platform.',
                'advanced.flapDetectionEnabled' => 'flap_detection_enabled is not available on a Cloud platform.',
                'advanced.lowFlapThreshold' => 'low_flap_threshold is not available on a Cloud platform.',
                'advanced.highFlapThreshold' => 'high_flap_threshold is not available on a Cloud platform.',
                'advanced.retainStatusInformation' => 'retain_status_information is not available on a Cloud platform.',
                'advanced.retainNonStatusInformation' => 'retain_non_status_information is not available on a Cloud platform.',
                'advanced.stalkingOptions' => 'stalking_options is not available on a Cloud platform.',
                'advanced.eventHandlerArgs' => 'event_handler_args is not available on a Cloud platform.',
                'advanced.comment' => 'This field is not available on a Cloud platform.',
            ],
        ];
    }

    /**
     * @param list<int> $serviceGroupIds
     * @param list<int> $categoryIds
     * @param list<int> $trapIds
     */
    private function fullInput(
        string $name = 'ping',
        int $hostId = 10,
        ?int $serviceTemplateId = 30,
        array $serviceGroupIds = [80],
        array $categoryIds = [90],
        ?int $severityId = 70,
        array $trapIds = [100],
        ?int $graphTemplateId = 60,
        ?ServiceCheckOptionsInput $checkOptions = new ServiceCheckOptionsInput(commandId: 5, args: ['80']),
        ?ServiceMonitoringEngineInput $monitoringEngine = new ServiceMonitoringEngineInput(
            checkTimeperiodId: 40,
            maxCheckAttempts: 3,
            activeCheckEnabled: TriStateEnum::True,
            note: 'a note',
            iconId: 50,
            altIcon: 'alt',
            geoCoordinates: '48.85,2.35',
        ),
        ?ServiceNotificationsInput $notifications = new ServiceNotificationsInput(
            enabled: 'true',
            contacts: [31],
            contactGroups: [41],
            interval: 0,
            timeperiodId: 40,
            options: ['warning', 'critical'],
        ),
        ?ServiceAdvancedInput $advanced = new ServiceAdvancedInput(
            acknowledgmentTimeout: 1,
            lowFlapThreshold: 0,
            highFlapThreshold: 100,
            stalkingOptions: ['ok'],
            eventHandlerCommandId: 6,
            eventHandlerArgs: ['arg'],
            comment: 'a comment',
        ),
    ): CreateServiceInput {
        return new CreateServiceInput(
            name: $name,
            hostId: $hostId,
            serviceTemplateId: $serviceTemplateId,
            serviceGroupIds: $serviceGroupIds,
            categoryIds: $categoryIds,
            severityId: $severityId,
            trapIds: $trapIds,
            graphTemplateId: $graphTemplateId,
            checkOptions: $checkOptions,
            monitoringEngine: $monitoringEngine,
            notifications: $notifications,
            advanced: $advanced,
        );
    }

    /**
     * What a Cloud payload may hold: no notifications, traps, graph template nor on-premise directive.
     *
     * @param list<int> $trapIds
     */
    private function cloudInput(
        ?int $serviceTemplateId = 30,
        array $trapIds = [],
        ?int $graphTemplateId = null,
        ?ServiceMonitoringEngineInput $monitoringEngine = null,
        ?ServiceNotificationsInput $notifications = null,
        ?ServiceAdvancedInput $advanced = null,
    ): CreateServiceInput {
        return new CreateServiceInput(
            name: 'ping',
            hostId: 10,
            serviceTemplateId: $serviceTemplateId,
            trapIds: $trapIds,
            graphTemplateId: $graphTemplateId,
            checkOptions: new ServiceCheckOptionsInput(commandId: 5),
            monitoringEngine: $monitoringEngine,
            notifications: $notifications,
            advanced: $advanced,
        );
    }

    /**
     * @return array<string, string> property path => message, in the order raised
     */
    private function violations(CreateServiceInput $input, bool $isCloudPlatform = false): array
    {
        $violations = [];
        foreach ($this->validator($isCloudPlatform)->validate($input) as $violation) {
            $violations[$violation->getPropertyPath()] = (string) $violation->getMessage();
        }
        ksort($violations);

        return $violations;
    }

    private function validator(bool $isCloudPlatform): ValidatorInterface
    {
        $credential = new Credential(new CredentialIdentifier('admin'), new UserId(1), active: true);
        $credential->assignRole(new Role('ROLE_SUPER_ADMIN'));

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(new CredentialUser($credential));

        $hosts = new FakeHostRepository();
        $hosts->states[10] = new HostState(true, new PollerId(1));
        $accessScopes = new AccessScopeResolver($hosts->resourceAccess);
        $services = new FakeServiceRepository();
        $services->add(new Service(id: null, name: new ServiceName('disk usage'), hostId: new HostId(10), hostActivated: true, hostPollerId: null));

        $templates = new FakeServiceTemplateRepository();
        $templates->names[30] = 'generic';
        $templates->templates[30] = new ServiceTemplate(new ServiceTemplateId(30), new ServiceTemplateName('generic'), checkCommandId: new CommandId(5));
        $commands = new FakeCommandRepository();
        foreach ([5 => CommandTypeEnum::Check, 6 => CommandTypeEnum::Miscellaneous] as $id => $type) {
            $commands->commands[$id] = new Command(new CommandId($id), new CommandName('command-' . $id), $type, new CommandLine('$USER1$/check'), false, true, false, null, null);
        }
        $timePeriods = new FakeTimePeriodRepository();
        $timePeriods->existingIds = [40];
        $timePeriods->names = [40 => '24x7'];

        $media = new FakeMediaRepository();
        $media->existingIds = [50];

        $graphTemplates = new FakeGraphTemplateRepository();
        $graphTemplates->names = [60 => 'graph'];

        $severities = new FakeServiceSeverityRepository();
        $severities->names = [70 => 'critical'];

        $serviceGroups = new FakeServiceGroupRepository();
        $serviceGroups->names = [80 => 'group'];

        $categories = new FakeServiceCategoryRepository();
        $categories->names = [90 => 'category'];

        $traps = new FakeTrapRepository();
        $traps->traps[100] = new Trap(new TrapId(100), new TrapName('linkDown'), new TrapVendor(new TrapVendorId(1), new TrapVendorName('generic')));
        $contacts = new FakeNotificationContactRepository();
        $contacts->names = [31 => new NotificationContactName('contact')];

        $contactGroups = new FakeContactGroupRepository();
        $contactGroups->names = [41 => new ContactGroupName('group')];

        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                AccessibleHostValidator::class => new AccessibleHostValidator($security, $hosts),
                UniqueServiceNameOnHostValidator::class => new UniqueServiceNameOnHostValidator($security, $services, $hosts),
                ExistingServiceTemplateValidator::class => new ExistingServiceTemplateValidator($templates),
                ServiceCommandInheritanceValidator::class => new ServiceCommandInheritanceValidator($templates),
                AccessibleServiceGroupsValidator::class => new AccessibleServiceGroupsValidator($security, $serviceGroups, $accessScopes),
                AccessibleServiceCategoriesValidator::class => new AccessibleServiceCategoriesValidator($security, $categories, $accessScopes),
                AccessibleServiceSeverityValidator::class => new AccessibleServiceSeverityValidator($security, $severities, $accessScopes),
                ExistingTrapsValidator::class => new ExistingTrapsValidator($traps),
                ExistingGraphTemplateValidator::class => new ExistingGraphTemplateValidator($graphTemplates),
                CheckCommandTypeValidator::class => new CheckCommandTypeValidator($commands),
                ValidEventHandlerCommandValidator::class => new ValidEventHandlerCommandValidator($commands),
                ExistingTimePeriodValidator::class => new ExistingTimePeriodValidator($timePeriods),
                ExistingMediaValidator::class => new ExistingMediaValidator($media),
                AccessibleContactsValidator::class => new AccessibleContactsValidator($security, $contacts, $accessScopes),
                AccessibleContactGroupsValidator::class => new AccessibleContactGroupsValidator($security, $contactGroups, $accessScopes),
                WhenPlatformValidator::class => new WhenPlatformValidator($isCloudPlatform),
            ]))
            ->getValidator();
    }
}
