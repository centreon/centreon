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
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceCheckOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceMonitoringEngineInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\UpdateServiceInput;
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
use App\Shared\Infrastructure\Validator\Constraints\WhenPlatformValidator;
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
 * The update payload shares the create one's rules (each pinned by {@see CreateServiceInputValidationTest} on the
 * sub-inputs both reuse); what differs is pinned here: the name uniqueness is left to the handler, the command
 * rules of the template chain still apply, and a legacy text above the form bounds is refused when resent.
 *
 * The references that exist: host 10 (service "disk usage" on it), template 30 (with a check command), check
 * command 5, event handler 6, time period 40, media 50, graph template 60, severity 70, service group 80,
 * category 90, trap 100, contact 31, contact group 41.
 */
final class UpdateServiceInputValidationTest extends TestCase
{
    public function testAValidPayloadRaisesNoViolation(): void
    {
        self::assertSame([], $this->violations($this->input()));
    }

    /**
     * The name of another service of the host is the handler's to refuse, as a conflict: the input cannot leave
     * the service itself out, its id being in the URI.
     */
    public function testANameUsedOnTheHostIsNotAnInputViolation(): void
    {
        self::assertSame([], $this->violations($this->input(name: 'DISK USAGE')));
    }

    public function testWithoutCheckCommandNorTemplateTheCommandIsRequired(): void
    {
        self::assertSame(
            ['checkOptions.commandId' => 'A check command is required when no service template is set.'],
            $this->violations($this->input(checkOptions: null)),
        );
    }

    /**
     * A legacy note longer than the form allows is read back as stored; resending it is refused until it is
     * shortened.
     */
    public function testOverLongNoteIsRejected(): void
    {
        self::assertSame(
            ['monitoringEngine.note' => 'This value is too long. It should have 512 characters or less.'],
            $this->violations($this->input(monitoringEngine: new ServiceMonitoringEngineInput(note: str_repeat('n', 513)))),
        );
    }

    private function input(
        string $name = 'ping',
        ?ServiceCheckOptionsInput $checkOptions = new ServiceCheckOptionsInput(commandId: 5),
        ?ServiceMonitoringEngineInput $monitoringEngine = null,
    ): UpdateServiceInput {
        return new UpdateServiceInput(
            name: $name,
            hostId: 10,
            activated: false,
            checkOptions: $checkOptions,
            monitoringEngine: $monitoringEngine,
        );
    }

    /**
     * @return array<string, string> property path => message, in the order raised
     */
    private function violations(UpdateServiceInput $input, bool $isCloudPlatform = false): array
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
