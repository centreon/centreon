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

namespace Tests\App\MonitoringConfiguration\Domain\Exception;

use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ContactGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\DuplicateServiceMacroNameException;
use App\MonitoringConfiguration\Domain\Exception\EventHandlerArgumentsRequireAHandlerException;
use App\MonitoringConfiguration\Domain\Exception\GraphTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostCategoryNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostSeverityNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\MediaNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\NotificationContactNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\PollerNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ReservedServiceMacroNameException;
use App\MonitoringConfiguration\Domain\Exception\ServiceCategoryNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceCheckArgumentsPartlyFilledException;
use App\MonitoringConfiguration\Domain\Exception\ServiceCheckCommandMissingException;
use App\MonitoringConfiguration\Domain\Exception\ServiceExtendedInformationsTooLongException;
use App\MonitoringConfiguration\Domain\Exception\ServiceGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroPasswordUnmaskedException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroValueIsAVaultReferenceException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroValueRequiredException;
use App\MonitoringConfiguration\Domain\Exception\ServiceSeverityNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimePeriodNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimezoneNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TrapNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\UnlicensedServiceTemplatesException;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\CreateHostInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\CreateServiceInput;
use App\Shared\Domain\Exception\AggregateConflictException;
use App\Shared\Domain\Exception\AggregateNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReferenceExceptionCriteriaTest extends TestCase
{
    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('criteriaProvider')]
    public function testTheFactoryNamesTheCriterionAfterTheInputField(
        AggregateNotFoundException $exception,
        array $expected,
    ): void {
        self::assertSame($expected, $exception->criteria);

        // The criterion is what InvalidReferenceExceptionListener matches against the operation's
        // input DTO to decide on a 422; a rename that misses CreateHostInput degrades it silently
        // back to the exception's own status.
        foreach (array_keys($expected) as $criterion) {
            self::assertTrue(
                property_exists(CreateHostInput::class, $criterion),
                sprintf('"%s" must name a property of CreateHostInput.', $criterion),
            );
        }
    }

    /**
     * The counterpart: a criterion that is not a payload field is what keeps a missing target
     * aggregate answering 404 rather than 422.
     */
    public function testThePollerFactoryNamesNoInputField(): void
    {
        foreach (array_keys(new PollerNotFoundException(['id' => 1])->criteria) as $criterion) {
            self::assertFalse(property_exists(CreateHostInput::class, (string) $criterion));
        }
    }

    /**
     * @return iterable<string, array{AggregateNotFoundException, array<string, mixed>}>
     */
    public static function criteriaProvider(): iterable
    {
        yield 'categories' => [new HostCategoryNotFoundException([3]), ['categoryIds' => [3]]];

        yield 'severity' => [new HostSeverityNotFoundException(4), ['severityId' => 4]];

        yield 'timezone' => [new TimezoneNotFoundException(5), ['timezoneId' => 5]];

    }

    /**
     * The service exceptions name their criterion after the PHP property of the service create input, so that
     * the listener turns a reference that vanished after the input validation into the same 422 on that field;
     * their message is the one the 422 shows.
     *
     * @param array<string, mixed> $expected
     */
    #[DataProvider('serviceCriteriaProvider')]
    public function testTheServiceExceptionsNameTheCriterionAfterTheInputField(
        AggregateNotFoundException|AggregateConflictException $exception,
        array $expected,
        string $message,
    ): void {
        self::assertSame($expected, $exception->criteria);
        self::assertSame($message, $exception->getMessage());
        foreach (array_keys($expected) as $criterion) {
            self::assertTrue(
                property_exists(CreateServiceInput::class, $criterion),
                sprintf('"%s" must name a property of CreateServiceInput.', $criterion),
            );
        }
    }

    /**
     * The counterpart for the service: a reference nested in a sub-object of the input (a contact, a contact
     * group, a time period, an icon, a command) names no top-level property, and keeps answering 404.
     */
    public function testTheNestedReferencesOfAServiceNameNoInputField(): void
    {
        foreach ([
            new NotificationContactNotFoundException([1]),
            new ContactGroupNotFoundException([1]),
            new TimePeriodNotFoundException(1),
            new MediaNotFoundException(1),
            new CommandNotFoundException(['id' => 1]),
        ] as $exception) {
            foreach (array_keys($exception->criteria) as $criterion) {
                self::assertFalse(property_exists(CreateServiceInput::class, (string) $criterion), $exception::class);
            }
        }
    }

    /**
     * @return iterable<string, array{AggregateNotFoundException|AggregateConflictException, array<string, mixed>, string}>
     */
    public static function serviceCriteriaProvider(): iterable
    {
        yield 'service template' => [
            new ServiceTemplateNotFoundException(3),
            ['serviceTemplateId' => 3],
            'This service template does not exist.',
        ];

        yield 'service groups' => [
            new ServiceGroupNotFoundException([4, 5]),
            ['serviceGroupIds' => [4, 5]],
            'One or more service groups do not exist or are not accessible.',
        ];

        yield 'service categories' => [
            new ServiceCategoryNotFoundException([6]),
            ['categoryIds' => [6]],
            'One or more service categories do not exist or are not accessible.',
        ];

        yield 'service severity' => [
            new ServiceSeverityNotFoundException(7),
            ['severityId' => 7],
            'This service severity does not exist or is not accessible.',
        ];

        yield 'traps' => [
            new TrapNotFoundException([8, 9]),
            ['trapIds' => [8, 9]],
            'One or more traps do not exist.',
        ];

        yield 'graph template' => [
            new GraphTemplateNotFoundException(10),
            ['graphTemplateId' => 10],
            'This graph template does not exist.',
        ];

        yield 'unlicensed service template' => [
            new UnlicensedServiceTemplatesException(11),
            ['serviceTemplateId' => 11],
            'The license host limit is reached: service templates coming from Monitoring Connectors cannot be used.',
        ];

        yield 'no check command and no service template' => [
            new ServiceCheckCommandMissingException(null),
            ['checkOptions' => ['commandId']],
            'A check command is required when no service template is set.',
        ];

        yield 'no check command from the service template' => [
            new ServiceCheckCommandMissingException(12),
            ['serviceTemplateId' => 12],
            'The selected service template does not provide any check command.',
        ];

        yield 'check command arguments partly filled' => [
            new ServiceCheckArgumentsPartlyFilledException(),
            ['checkOptions' => ['args']],
            'Either fill every argument or leave them all empty.',
        ];

        yield 'event handler arguments without a handler' => [
            new EventHandlerArgumentsRequireAHandlerException(),
            ['advanced' => ['eventHandlerArgs']],
            'Event handler arguments require an event handler, set on the service or provided by its service template.',
        ];

        yield 'two macros of the same name' => [
            new DuplicateServiceMacroNameException(['TOKEN']),
            ['checkOptions' => ['TOKEN']],
            'A service cannot have two macros with the same name.',
        ];

        yield 'macro the service neither owns nor inherits' => [
            new ServiceMacroNotFoundException([13, 14]),
            ['checkOptions' => [13, 14]],
            'One or more macros do not exist on this service.',
        ];

        yield 'password macro turned into a plain one' => [
            new ServiceMacroPasswordUnmaskedException(['PWD']),
            ['checkOptions' => ['PWD']],
            'These macros are passwords and must stay so: is_password cannot be set to false.',
        ];

        yield 'stored value kept on a macro that is not a password' => [
            new ServiceMacroValueRequiredException(['PLAIN']),
            ['checkOptions' => ['PLAIN']],
            'A value is required: these macros are not stored as passwords, so their value cannot be kept.',
        ];

        yield 'macro value that is a vault reference' => [
            new ServiceMacroValueIsAVaultReferenceException(['PWD']),
            ['checkOptions' => ['PWD']],
            'A macro value cannot be a vault reference.',
        ];

        yield 'reserved macro name' => [
            new ReservedServiceMacroNameException(['CRITICALITY_LEVEL']),
            ['checkOptions' => ['CRITICALITY_LEVEL']],
            'This macro name is reserved and cannot be used.',
        ];

        yield 'extended information above the form bounds' => [
            new ServiceExtendedInformationsTooLongException(['note', 'actionUrl']),
            ['monitoringEngine' => ['note', 'actionUrl']],
            'The note cannot exceed 512 characters, the note URL and the action URL 2048 characters.',
        ];
    }
}
