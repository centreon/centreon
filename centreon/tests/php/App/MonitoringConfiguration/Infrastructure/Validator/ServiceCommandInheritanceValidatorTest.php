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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Validator;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\CreateServiceInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceAdvancedInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceCheckOptionsInput;
use App\MonitoringConfiguration\Infrastructure\Validator\ServiceCommandInheritance;
use App\MonitoringConfiguration\Infrastructure\Validator\ServiceCommandInheritanceValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceTemplateRepository;

/**
 * @extends ConstraintValidatorTestCase<ServiceCommandInheritanceValidator>
 */
final class ServiceCommandInheritanceValidatorTest extends ConstraintValidatorTestCase
{
    private FakeServiceTemplateRepository $templates;

    protected function setUp(): void
    {
        $this->templates = new FakeServiceTemplateRepository();
        // 30 inherits from 31, which has a check command and an event handler; 40 has neither.
        $this->templates->templates[31] = new ServiceTemplate(new ServiceTemplateId(31), new ServiceTemplateName('base'), checkCommandId: new CommandId(5), eventHandlerCommandId: new CommandId(6));
        $this->templates->templates[30] = new ServiceTemplate(new ServiceTemplateId(30), new ServiceTemplateName('child'), parentId: new ServiceTemplateId(31));
        $this->templates->templates[40] = new ServiceTemplate(new ServiceTemplateId(40), new ServiceTemplateName('bare'));
        parent::setUp();
    }

    public function testACheckCommandSetOnTheServiceIsEnough(): void
    {
        $this->validator->validate($this->input(commandId: 5, args: ['80']), new ServiceCommandInheritance());

        $this->assertNoViolation();
    }

    public function testACheckCommandInheritedFromTheTemplateChainIsEnough(): void
    {
        $this->validator->validate($this->input(templateId: 30, args: ['80']), new ServiceCommandInheritance());

        $this->assertNoViolation();
    }

    public function testWithoutCheckCommandNorTemplateTheCommandIsRequired(): void
    {
        $constraint = new ServiceCommandInheritance();
        $this->validator->validate($this->input(), $constraint);

        $this->buildViolation($constraint->missingCommandMessage)->atPath('property.path.checkOptions.commandId')->assertRaised();
    }

    public function testATemplateChainWithoutCheckCommandIsReportedOnTheTemplate(): void
    {
        $constraint = new ServiceCommandInheritance();
        $this->validator->validate($this->input(templateId: 40), $constraint);

        $this->buildViolation($constraint->templateWithoutCommandMessage)->atPath('property.path.serviceTemplateId')->assertRaised();
    }

    /**
     * Arguments need a command too: both rules are reported.
     */
    public function testArgumentsWithoutAnyCheckCommandAreRefused(): void
    {
        $constraint = new ServiceCommandInheritance();
        $this->validator->validate($this->input(args: ['80']), $constraint);

        $this->buildViolation($constraint->missingCommandMessage)->atPath('property.path.checkOptions.commandId')
            ->buildNextViolation($constraint->argumentsWithoutCommandMessage)->atPath('property.path.checkOptions.args')
            ->assertRaised();
    }

    /**
     * A list of empty arguments only is no argument.
     */
    public function testEmptyArgumentsWithoutCheckCommandOnlyReportTheMissingCommand(): void
    {
        $constraint = new ServiceCommandInheritance();
        $this->validator->validate($this->input(args: ['', '']), $constraint);

        $this->buildViolation($constraint->missingCommandMessage)->atPath('property.path.checkOptions.commandId')->assertRaised();
    }

    /**
     * An unknown template is reported by its own constraint: no chain rule is added on top of it.
     */
    public function testItAddsNothingForAnUnknownTemplate(): void
    {
        $this->validator->validate($this->input(templateId: 999, eventHandlerArgs: ['arg']), new ServiceCommandInheritance());

        $this->assertNoViolation();
    }

    public function testEventHandlerArgumentsWithoutAnyEventHandlerAreRefused(): void
    {
        $constraint = new ServiceCommandInheritance();
        $this->validator->validate($this->input(commandId: 5, eventHandlerArgs: ['arg']), $constraint);

        $this->buildViolation($constraint->eventHandlerArgumentsWithoutHandlerMessage)->atPath('property.path.advanced.eventHandlerArgs')->assertRaised();
    }

    public function testEventHandlerArgumentsWithAnEventHandlerFromTheServiceOrItsTemplatesAreAccepted(): void
    {
        $this->validator->validate($this->input(commandId: 5, eventHandlerCommandId: 7, eventHandlerArgs: ['arg']), new ServiceCommandInheritance());
        $this->validator->validate($this->input(templateId: 30, eventHandlerArgs: ['arg']), new ServiceCommandInheritance());
        $this->validator->validate($this->input(commandId: 5, eventHandlerArgs: ['', '']), new ServiceCommandInheritance());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new ServiceCommandInheritanceValidator($this->templates);
    }

    /**
     * @param list<string> $args
     * @param list<string> $eventHandlerArgs
     */
    private function input(
        ?int $templateId = null,
        ?int $commandId = null,
        array $args = [],
        ?int $eventHandlerCommandId = null,
        array $eventHandlerArgs = [],
    ): CreateServiceInput {
        return new CreateServiceInput(
            name: 'ping',
            hostId: 10,
            serviceTemplateId: $templateId,
            checkOptions: new ServiceCheckOptionsInput(commandId: $commandId, args: $args),
            advanced: new ServiceAdvancedInput(eventHandlerCommandId: $eventHandlerCommandId, eventHandlerArgs: $eventHandlerArgs),
        );
    }
}
