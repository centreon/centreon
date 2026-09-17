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

use Centreon\Application\Validation\Constraints\UniqueEntity;
use Centreon\Application\Validation\Validator\UniqueEntityValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<UniqueEntityValidator>
 */
final class UniqueEntityTest extends ConstraintValidatorTestCase
{
    // -------------------------------------------------------------------------
    // UniqueEntity constraint tests
    // -------------------------------------------------------------------------

    public function testConstructorSetsFieldAndRepository(): void
    {
        $constraint = new UniqueEntity(field: 'name', repository: 'App\Repository\FooRepository');

        $this->assertSame('name', $constraint->field);
        $this->assertSame('App\Repository\FooRepository', $constraint->repository);
    }

    public function testFieldIsTrimmed(): void
    {
        $constraint = new UniqueEntity(field: '  name  ', repository: 'SomeRepo');

        $this->assertSame('name', $constraint->field);
    }

    public function testRepositoryIsTrimmed(): void
    {
        $constraint = new UniqueEntity(field: 'name', repository: '  SomeRepo  ');

        $this->assertSame('SomeRepo', $constraint->repository);
    }

    public function testEmptyFieldThrowsConstraintDefinitionException(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The field name cannot be empty.');

        new UniqueEntity(field: '', repository: 'SomeRepo');
    }

    public function testWhitespaceOnlyFieldThrowsConstraintDefinitionException(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The field name cannot be empty.');

        new UniqueEntity(field: '   ', repository: 'SomeRepo');
    }

    public function testEmptyRepositoryThrowsConstraintDefinitionException(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The repository name cannot be empty.');

        new UniqueEntity(field: 'name', repository: '');
    }

    public function testWhitespaceOnlyRepositoryThrowsConstraintDefinitionException(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The repository name cannot be empty.');

        new UniqueEntity(field: 'name', repository: '   ');
    }

    public function testGroupsAreForwardedToParent(): void
    {
        $constraint = new UniqueEntity(field: 'name', repository: 'SomeRepo', groups: ['MyGroup']);

        $this->assertContains('MyGroup', $constraint->groups);
    }

    public function testPayloadIsForwardedToParent(): void
    {
        $payload = ['key' => 'value'];
        $constraint = new UniqueEntity(field: 'name', repository: 'SomeRepo', payload: $payload);

        $this->assertSame($payload, $constraint->payload);
    }

    public function testGetTargetsReturnsClassConstraint(): void
    {
        $constraint = new UniqueEntity(field: 'name', repository: 'SomeRepo');

        $this->assertSame(Constraint::CLASS_CONSTRAINT, $constraint->getTargets());
    }

    public function testValidatedByReturnsUniqueEntityValidatorClass(): void
    {
        $constraint = new UniqueEntity(field: 'name', repository: 'SomeRepo');

        $this->assertSame(UniqueEntityValidator::class, $constraint->validatedBy());
    }

    // -------------------------------------------------------------------------
    // UniqueEntityValidator tests (cases that do not require the Kernel)
    // -------------------------------------------------------------------------

    public function testNullValueSkipsValidation(): void
    {
        $constraint = new UniqueEntity(field: 'name', repository: 'SomeRepo');

        $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    public function testWrongConstraintTypeThrowsUnexpectedTypeException(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('value', $this->createMock(Constraint::class));
    }

    public function testScalarValueSkipsValidation(): void
    {
        $constraint = new UniqueEntity(field: 'name', repository: 'SomeRepo');

        $this->validator->validate('a-string-value', $constraint);

        $this->assertNoViolation();
    }

    public function testObjectWithoutGetterSkipsValidation(): void
    {
        $constraint = new UniqueEntity(field: 'name', repository: 'SomeRepo');
        $value = new class () {
        };

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    public function testDependenciesReturnsCentreonDbManager(): void
    {
        $this->assertSame(
            ['centreon.db-manager'],
            UniqueEntityValidator::dependencies()
        );
    }

    // -------------------------------------------------------------------------
    // UniqueEntityValidator tests (cases using a mocked container)
    // -------------------------------------------------------------------------

    public function testRepositoryWithoutFindOneByThrowsConstraintDefinitionException(): void
    {
        $this->expectException(ConstraintDefinitionException::class);

        $repository = new class () {
            // intentionally missing findOneBy
        };

        $this->injectContainer($this->mockContainerReturning($repository));

        $value = new class () {
            public function getName(): string
            {
                return 'foo';
            }
        };

        $this->validator->validate($value, new UniqueEntity(field: 'name', repository: 'SomeRepo'));
    }

    public function testNoResultSkipsValidation(): void
    {
        $repository = new class () {
            public function findOneBy(array $criteria): mixed
            {
                return null;
            }
        };

        $this->injectContainer($this->mockContainerReturning($repository));

        $value = new class () {
            public function getName(): string
            {
                return 'foo';
            }
        };

        $this->validator->validate($value, new UniqueEntity(field: 'name', repository: 'SomeRepo'));

        $this->assertNoViolation();
    }

    public function testResultWithoutGetIdThrowsConstraintDefinitionException(): void
    {
        $this->expectException(ConstraintDefinitionException::class);

        $result = new class () {
            // intentionally missing getId
        };

        $repository = new class ($result) {
            public function __construct(private readonly mixed $result)
            {
            }

            public function findOneBy(array $criteria): mixed
            {
                return $this->result;
            }
        };

        $this->injectContainer($this->mockContainerReturning($repository));

        $value = new class () {
            public function getName(): string
            {
                return 'foo';
            }

            public function getId(): int
            {
                return 1;
            }
        };

        $this->validator->validate($value, new UniqueEntity(field: 'name', repository: 'SomeRepo'));
    }

    public function testSameEntitySkipsValidation(): void
    {
        $result = new class () {
            public function getId(): int
            {
                return 1;
            }

            public function findOneBy(array $criteria): mixed
            {
                return $this;
            }
        };

        $repository = new class ($result) {
            public function __construct(private readonly mixed $result)
            {
            }

            public function findOneBy(array $criteria): mixed
            {
                return $this->result;
            }
        };

        $this->injectContainer($this->mockContainerReturning($repository));

        $value = new class () {
            public function getName(): string
            {
                return 'foo';
            }

            public function getId(): int
            {
                return 1;
            }
        };

        $this->validator->validate($value, new UniqueEntity(field: 'name', repository: 'SomeRepo'));

        $this->assertNoViolation();
    }

    public function testDifferentEntityAddsViolation(): void
    {
        $result = new class () {
            public function getId(): int
            {
                return 99;
            }
        };

        $repository = new class ($result) {
            public function __construct(private readonly mixed $result)
            {
            }

            public function findOneBy(array $criteria): mixed
            {
                return $this->result;
            }
        };

        $this->injectContainer($this->mockContainerReturning($repository));

        $value = new class () {
            public function getName(): string
            {
                return 'foo';
            }

            public function getId(): int
            {
                return 1;
            }
        };

        $this->validator->validate($value, new UniqueEntity(field: 'name', repository: 'SomeRepo'));

        $this->buildViolation('Name already in use')
            ->atPath('property.path.name')
            ->setInvalidValue('foo')
            ->setCode(UniqueEntity::NOT_UNIQUE_ERROR)
            ->setCause($result)
            ->assertRaised();
    }

    // -------------------------------------------------------------------------

    protected function createValidator(): UniqueEntityValidator
    {
        return new UniqueEntityValidator();
    }

    private function injectContainer(ContainerInterface $container): void
    {
        $ref = new \ReflectionProperty(UniqueEntityValidator::class, 'container');
        $ref->setValue($this->validator, $container);
    }

    private function mockContainerReturning(object $repository): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($repository);

        return $container;
    }
}
