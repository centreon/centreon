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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate;

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\CreateHostTemplateDataProcessingInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\CreateHostTemplateExtendedInformationsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\CreateHostTemplateInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\CreateHostTemplateNotificationsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\CreateHostTemplateSchedulingOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\PatchHostTemplateExtendedInformationsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\PatchHostTemplateInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\PatchHostTemplateNotificationsInput;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Infrastructure\Validator\Constraints\WhenPlatform;
use App\Shared\Infrastructure\Validator\Constraints\WhenPlatformValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The platform is bound when the container is built, so the HTTP suite only sees on premise: the
 * Cloud refusals are checked on the inputs, the repository-backed rules switched off.
 */
final class HostTemplateInputPlatformTest extends TestCase
{
    /**
     * @param list<string> $paths
     */
    #[DataProvider('provideOnPremiseOnlyFields')]
    public function testACloudPlatformRefusesTheOnPremiseOnlyFields(object $input, array $paths): void
    {
        $violations = $this->validator(isCloudPlatform: true)->validate($input);

        $refused = [];
        foreach ($violations as $violation) {
            $refused[] = $violation->getPropertyPath();
        }
        sort($refused);
        self::assertSame($paths, $refused);
    }

    /**
     * @param list<string> $paths
     */
    #[DataProvider('provideOnPremiseOnlyFields')]
    public function testOnPremiseAcceptsThem(object $input, array $paths): void
    {
        self::assertNotSame([], $paths);
        self::assertCount(0, $this->validator(isCloudPlatform: false)->validate($input));
    }

    /**
     * @return iterable<string, array{object, list<string>}>
     */
    public static function provideOnPremiseOnlyFields(): iterable
    {
        yield 'notifications' => [
            new CreateHostTemplateInput(name: 'tpl', alias: 'alias', notifications: new CreateHostTemplateNotificationsInput()),
            ['notifications'],
        ];

        yield 'extended informations' => [
            new CreateHostTemplateExtendedInformationsInput(altIcon: 'icon', comment: 'comment'),
            ['altIcon', 'comment'],
        ];

        yield 'notifications of a change' => [
            new PatchHostTemplateInput(notifications: new PatchHostTemplateNotificationsInput()),
            ['notifications'],
        ];

        yield 'extended informations of a change' => [
            new PatchHostTemplateExtendedInformationsInput(altIcon: 'icon', comment: 'comment'),
            ['altIcon', 'comment'],
        ];

        yield 'scheduling options' => [
            new CreateHostTemplateSchedulingOptionsInput(activeCheckEnabled: TriStateEnum::True, passiveCheckEnabled: TriStateEnum::False),
            ['activeCheckEnabled', 'passiveCheckEnabled'],
        ];

        yield 'data processing' => [
            new CreateHostTemplateDataProcessingInput(
                acknowledgmentTimeout: 5,
                flapDetectionEnabled: TriStateEnum::True,
                lowFlapThreshold: 10,
                highFlapThreshold: 40,
                eventHandlerArgs: ['restart'],
            ),
            ['acknowledgmentTimeout', 'eventHandlerArgs', 'flapDetectionEnabled', 'highFlapThreshold', 'lowFlapThreshold'],
        ];
    }

    private function validator(bool $isCloudPlatform): ValidatorInterface
    {
        $defaultFactory = new ConstraintValidatorFactory();

        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory(
                new class ($isCloudPlatform, $defaultFactory) implements ConstraintValidatorFactoryInterface {
                    public function __construct(
                        private readonly bool $isCloudPlatform,
                        private readonly ConstraintValidatorFactory $defaultFactory,
                    ) {
                    }

                    public function getInstance(Constraint $constraint): ConstraintValidatorInterface
                    {
                        if ($constraint instanceof WhenPlatform) {
                            return new WhenPlatformValidator($this->isCloudPlatform);
                        }

                        if (str_starts_with($constraint::class, 'App\\')) {
                            return new class () extends ConstraintValidator {
                                public function validate(mixed $value, Constraint $constraint): void
                                {
                                }
                            };
                        }

                        return $this->defaultFactory->getInstance($constraint);
                    }
                },
            )
            ->getValidator();
    }
}
