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

namespace Tests\App\Shared\Infrastructure\Validator\Constraints;

use App\Shared\Infrastructure\Validator\Constraints\WhenPlatform;
use App\Shared\Infrastructure\Validator\Constraints\WhenPlatformValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * WhenPlatformValidator is resolved through Symfony's validator constraint locator, which is
 * compiled once at container build time — a real container cannot be forced to a different
 * IS_CLOUD_PLATFORM value per test (see CreateHostProcessorTest's docblock on the same subject).
 * So, exactly like the Core equivalent (Core\Common\Infrastructure\Validator\Constraints\WhenPlatform),
 * this builds its own standalone Validator to exercise both branches.
 */
final class WhenPlatformTest extends TestCase
{
    public function testGroupsAreForwardedToParent(): void
    {
        $constraint = new WhenPlatform(
            forCloud: true,
            constraints: [new NotBlank()],
            groups: ['MyGroup'],
        );

        self::assertContains('MyGroup', $constraint->groups ?? []);
    }

    public function testPayloadIsForwardedToParent(): void
    {
        $payload = ['key' => 'value'];
        $constraint = new WhenPlatform(
            forCloud: true,
            constraints: [new NotBlank()],
            payload: $payload,
        );

        self::assertSame($payload, $constraint->payload);
    }

    public function testValidationPassesWhenPlatformMatchesAndValueIsValid(): void
    {
        $validator = $this->buildValidator(isCloudPlatform: true);

        $violations = $validator->validate('hello', new WhenPlatform(
            forCloud: true,
            constraints: [new NotBlank()],
        ));

        self::assertCount(0, $violations);
    }

    public function testValidationFailsWhenPlatformMatchesAndValueIsInvalid(): void
    {
        $validator = $this->buildValidator(isCloudPlatform: true);

        $violations = $validator->validate('', new WhenPlatform(
            forCloud: true,
            constraints: [new NotBlank()],
        ));

        self::assertCount(1, $violations);
    }

    public function testValidationIsSkippedWhenPlatformDoesNotMatch(): void
    {
        $validator = $this->buildValidator(isCloudPlatform: false);

        $violations = $validator->validate('', new WhenPlatform(
            forCloud: true,
            constraints: [new NotBlank()],
        ));

        self::assertCount(0, $violations);
    }

    private function buildValidator(bool $isCloudPlatform): ValidatorInterface
    {
        $defaultFactory = new ConstraintValidatorFactory();

        return Validation::createValidatorBuilder()
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

                        return $this->defaultFactory->getInstance($constraint);
                    }
                },
            )
            ->getValidator();
    }
}
