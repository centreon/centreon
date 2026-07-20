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

namespace Tests\Centreon\Application\Validation\Constraints;

use Centreon\Application\Validation\Constraints\RepositoryCallback;
use Centreon\Application\Validation\Validator\RepositoryCallbackValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<RepositoryCallbackValidator>
 */
final class RepositoryCallbackTest extends ConstraintValidatorTestCase
{
    private const FIELD = 'name';
    private const FIELD_ACCESSOR = 'getName';
    private const REPOSITORY = 'App\Repository\FooRepository';
    private const REPOSITORY_METHOD = 'isValid';
    private const ERROR_MESSAGE = 'This value is not valid.';

    // -------------------------------------------------------------------------
    // RepositoryCallback constraint tests
    // -------------------------------------------------------------------------

    public function testConstructorSetsAllProperties(): void
    {
        $constraint = new RepositoryCallback(
            field: self::FIELD,
            fieldAccessor: self::FIELD_ACCESSOR,
            repository: self::REPOSITORY,
            repositoryMethod: self::REPOSITORY_METHOD,
            errorMessage: self::ERROR_MESSAGE,
        );

        $this->assertSame(self::FIELD, $constraint->field);
        $this->assertSame(self::FIELD_ACCESSOR, $constraint->fieldAccessor);
        $this->assertSame(self::REPOSITORY, $constraint->repository);
        $this->assertSame(self::REPOSITORY_METHOD, $constraint->repositoryMethod);
        $this->assertSame(self::ERROR_MESSAGE, $constraint->errorMessage);
    }

    public function testAllStringPropertiesAreTrimmed(): void
    {
        $constraint = new RepositoryCallback(
            field: '  name  ',
            fieldAccessor: '  getName  ',
            repository: '  SomeRepo  ',
            repositoryMethod: '  isValid  ',
            errorMessage: '  Error  ',
        );

        $this->assertSame('name', $constraint->field);
        $this->assertSame('getName', $constraint->fieldAccessor);
        $this->assertSame('SomeRepo', $constraint->repository);
        $this->assertSame('isValid', $constraint->repositoryMethod);
        $this->assertSame('Error', $constraint->errorMessage);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('emptyStringProvider')]
    public function testEmptyFieldThrowsConstraintDefinitionException(string $empty): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The field name cannot be empty.');

        new RepositoryCallback(
            field: $empty,
            fieldAccessor: self::FIELD_ACCESSOR,
            repository: self::REPOSITORY,
            repositoryMethod: self::REPOSITORY_METHOD,
            errorMessage: self::ERROR_MESSAGE,
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('emptyStringProvider')]
    public function testEmptyFieldAccessorThrowsConstraintDefinitionException(string $empty): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The field accessor cannot be empty.');

        new RepositoryCallback(
            field: self::FIELD,
            fieldAccessor: $empty,
            repository: self::REPOSITORY,
            repositoryMethod: self::REPOSITORY_METHOD,
            errorMessage: self::ERROR_MESSAGE,
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('emptyStringProvider')]
    public function testEmptyRepositoryThrowsConstraintDefinitionException(string $empty): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The repository cannot be empty.');

        new RepositoryCallback(
            field: self::FIELD,
            fieldAccessor: self::FIELD_ACCESSOR,
            repository: $empty,
            repositoryMethod: self::REPOSITORY_METHOD,
            errorMessage: self::ERROR_MESSAGE,
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('emptyStringProvider')]
    public function testEmptyRepositoryMethodThrowsConstraintDefinitionException(string $empty): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The repository method cannot be empty.');

        new RepositoryCallback(
            field: self::FIELD,
            fieldAccessor: self::FIELD_ACCESSOR,
            repository: self::REPOSITORY,
            repositoryMethod: $empty,
            errorMessage: self::ERROR_MESSAGE,
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('emptyStringProvider')]
    public function testEmptyErrorMessageThrowsConstraintDefinitionException(string $empty): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The validation error message cannot be empty.');

        new RepositoryCallback(
            field: self::FIELD,
            fieldAccessor: self::FIELD_ACCESSOR,
            repository: self::REPOSITORY,
            repositoryMethod: self::REPOSITORY_METHOD,
            errorMessage: $empty,
        );
    }

    public function testGroupsAreForwardedToParent(): void
    {
        $constraint = new RepositoryCallback(
            field: self::FIELD,
            fieldAccessor: self::FIELD_ACCESSOR,
            repository: self::REPOSITORY,
            repositoryMethod: self::REPOSITORY_METHOD,
            errorMessage: self::ERROR_MESSAGE,
            groups: ['MyGroup'],
        );

        $this->assertContains('MyGroup', $constraint->groups);
    }

    public function testPayloadIsForwardedToParent(): void
    {
        $payload = ['key' => 'value'];
        $constraint = new RepositoryCallback(
            field: self::FIELD,
            fieldAccessor: self::FIELD_ACCESSOR,
            repository: self::REPOSITORY,
            repositoryMethod: self::REPOSITORY_METHOD,
            errorMessage: self::ERROR_MESSAGE,
            payload: $payload,
        );

        $this->assertSame($payload, $constraint->payload);
    }

    public function testGetTargetsReturnsClassConstraint(): void
    {
        $constraint = $this->buildConstraint();

        $this->assertSame(Constraint::CLASS_CONSTRAINT, $constraint->getTargets());
    }

    public function testValidatedByReturnsRepositoryCallbackValidatorClass(): void
    {
        $constraint = $this->buildConstraint();

        $this->assertSame(RepositoryCallbackValidator::class, $constraint->validatedBy());
    }

    // -------------------------------------------------------------------------
    // RepositoryCallbackValidator tests (cases that do not require the Kernel)
    // -------------------------------------------------------------------------

    public function testWrongConstraintTypeThrowsUnexpectedTypeException(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('value', $this->createMock(Constraint::class));
    }

    public function testNullValueSkipsValidation(): void
    {
        $this->validator->validate(null, $this->buildConstraint());

        $this->assertNoViolation();
    }

    public function testNonObjectValueSkipsValidation(): void
    {
        $this->validator->validate('a scalar value', $this->buildConstraint());

        $this->assertNoViolation();
    }

    public function testObjectWithoutFieldAccessorSkipsValidation(): void
    {
        $this->validator->validate(new \stdClass(), $this->buildConstraint());

        $this->assertNoViolation();
    }

    public function testMethodNotFoundInRepositoryThrowsConstraintDefinitionException(): void
    {
        $this->expectException(ConstraintDefinitionException::class);

        $repository = new class () {
            // intentionally missing the 'nonExistentMethod' method
        };

        $this->injectContainer($this->mockContainerReturning($repository));

        $value = new class () {
            public function getName(): string
            {
                return 'foo';
            }
        };

        $constraint = new RepositoryCallback(
            field: self::FIELD,
            fieldAccessor: self::FIELD_ACCESSOR,
            repository: self::REPOSITORY,
            repositoryMethod: 'nonExistentMethod',
            errorMessage: self::ERROR_MESSAGE,
        );

        $this->validator->validate($value, $constraint);
    }

    // -------------------------------------------------------------------------

    /**
     * @return \Generator<string, array{string}>
     */
    public static function emptyStringProvider(): \Generator
    {
        yield 'empty string' => [''];

        yield 'whitespace only' => ['   '];
    }

    protected function createValidator(): RepositoryCallbackValidator
    {
        return new RepositoryCallbackValidator();
    }

    private function injectContainer(ContainerInterface $container): void
    {
        $ref = new \ReflectionProperty(RepositoryCallbackValidator::class, 'container');
        $ref->setValue($this->validator, $container);
    }

    private function mockContainerReturning(object $repository): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($repository);

        return $container;
    }

    private function buildConstraint(): RepositoryCallback
    {
        return new RepositoryCallback(
            field: self::FIELD,
            fieldAccessor: self::FIELD_ACCESSOR,
            repository: self::REPOSITORY,
            repositoryMethod: self::REPOSITORY_METHOD,
            errorMessage: self::ERROR_MESSAGE,
        );
    }
}
