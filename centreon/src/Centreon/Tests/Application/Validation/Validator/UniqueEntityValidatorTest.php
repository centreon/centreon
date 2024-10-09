<?php
/*
 * Copyright 2005-2019 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 *
 */

namespace Centreon\Tests\Application\Validation\Validator;

use PHPUnit\Framework\TestCase;
use Centreon\Application\Validation\Validator\UniqueEntityValidator;
use Centreon\Application\Validation\Constraints\UniqueEntity;
use Centreon\ServiceProvider;
use Centreon\Tests\Resources\Dependency;
use Centreon\Tests\Resources\Mock;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilder;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Pimple\Container;
use Pimple\Psr11\Container as Psr11Container;

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
