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

namespace Centreon\Tests\Application\Validation\Validator;

use Centreon\Application\Validation\Constraints\UniqueEntity;
use Centreon\Application\Validation\Validator\UniqueEntityValidator;
use Centreon\ServiceProvider;
use Centreon\Tests\Resources\Dependency;
use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @group Centreon
 * @group DataRepresenter
 */
class UniqueEntityValidatorTest extends TestCase
{
    use Dependency\CentreonDbManagerDependencyTrait;

    /** @var Container */
    public $container;

    /** @var (object&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject|ExecutionContext|(ExecutionContext&object&\PHPUnit\Framework\MockObject\MockObject)|(ExecutionContext&\PHPUnit\Framework\MockObject\MockObject) */
    public $executionContext;

    /** @var UniqueEntityValidator */
    public $validator;

    public function setUp(): void
    {
        $this->container = new Container();
        $this->executionContext = $this->createMock(ExecutionContext::class);

        // dependency
        $this->setUpCentreonDbManager($this->container);

        $this->validator = new UniqueEntityValidator();
        $this->validator->initialize($this->executionContext);
    }

    public function testValidateWithDifferentConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(null, $this->createMock(Constraint::class));
    }

    public function testValidateWithDifferentTypeOfFields(): void
    {
        $constraint = $this->createMock(UniqueEntity::class);
        $constraint->fields = new \stdClass();

        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(null, $constraint);
    }

    public function testValidateWithDifferentTypeOfErrorPath(): void
    {
        $constraint = $this->createMock(UniqueEntity::class);
        $constraint->errorPath = [];

        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(null, $constraint);
    }

    public function testValidateWithEmptyFields(): void
    {
        $constraint = $this->createMock(UniqueEntity::class);
        $constraint->fields = [];

        $this->expectException(ConstraintDefinitionException::class);

        $this->validator->validate(null, $constraint);
    }

    public function testValidateWithNullAsEntity(): void
    {
        $constraint = $this->createMock(UniqueEntity::class);
        $constraint->fields = 'name';

        $this->assertNull($this->validator->validate(null, $constraint));
    }

    public function testDependencies(): void
    {
        $this->assertEquals([
            ServiceProvider::CENTREON_DB_MANAGER,
        ], $this->validator::dependencies());
    }
}
