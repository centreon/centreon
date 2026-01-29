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

namespace Centreon\Tests\Application\Validation\Constraints;

use Centreon\Application\Validation\Constraints\UniqueEntity;
use Centreon\Application\Validation\Validator\UniqueEntityValidator;
use PHPUnit\Framework\TestCase;

/**
 * @group Centreon
 * @group DataRepresenter
 */
class UniqueEntityTest extends TestCase
{
    public function testCheckDefaultValueOfProperties(): void
    {
        $constraint = new UniqueEntity();

        $this->assertEquals('getId', $constraint->entityIdentificatorMethod);
        $this->assertEquals('id', $constraint->entityIdentificatorColumn);
        $this->assertNull($constraint->repository);
        $this->assertEquals('findOneBy', $constraint->repositoryMethod);
        $this->assertEquals([], $constraint->fields);
    }

    public function testValidatedBy(): void
    {
        $this->assertEquals(
            UniqueEntityValidator::class,
            (new UniqueEntity())->validatedBy()
        );
    }

    public function testGetTargets(): void
    {
        $this->assertEquals(
            UniqueEntity::CLASS_CONSTRAINT,
            (new UniqueEntity())->getTargets()
        );
    }

    public function testGetDefaultOption(): void
    {
        $this->assertEquals(
            'fields',
            (new UniqueEntity())->getDefaultOption()
        );
    }
}
