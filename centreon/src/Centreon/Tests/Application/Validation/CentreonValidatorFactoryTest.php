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

namespace Centreon\Tests\Application\Validation;

use Centreon\Application\Validation\CentreonValidatorFactory;
use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotBlankValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @group Centreon
 * @group DataRepresenter
 */
class CentreonValidatorFactoryTest extends TestCase
{
    public function testGetInstance(): void
    {
        $factory = new CentreonValidatorFactory(new Container());

        $this->assertInstanceOf(NotBlankValidator::class, $factory->getInstance(new NotBlank()));
    }

    public function testGetInstanceWithService(): void
    {
        $service = 'service.constraint';

        $constraint = $this->createMock(NotBlank::class);
        $constraint->method('validatedBy')
            ->willReturn($service);

        $factory = new CentreonValidatorFactory(new Container([
            $service => new class () implements \Symfony\Component\Validator\ConstraintValidatorInterface {
                public function initialize(ExecutionContextInterface $context): void
                {
                    // TODO: Implement initialize() method.
                }

                public function validate(mixed $value, Constraint $constraint): void
                {
                    // TODO: Implement validate() method.
                }
            },
        ]));

        $this->assertInstanceOf(
            \Symfony\Component\Validator\ConstraintValidatorInterface::class,
            $factory->getInstance($constraint)
        );
    }

    public function testGetInstanceWithoutValidator(): void
    {
        $service = 'service.constraint';

        $constraint = $this->createMock(NotBlank::class);
        $constraint->method('validatedBy')
            ->willReturn($service);

        $factory = new CentreonValidatorFactory(new Container());

        $this->expectException(\RuntimeException::class);

        $factory->getInstance($constraint);
    }
}
