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

use App\MonitoringConfiguration\Infrastructure\Validator\ExistingGraphTemplate;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingGraphTemplateValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeGraphTemplateRepository;

/**
 * @extends ConstraintValidatorTestCase<ExistingGraphTemplateValidator>
 */
final class ExistingGraphTemplateValidatorTest extends ConstraintValidatorTestCase
{
    private FakeGraphTemplateRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new FakeGraphTemplateRepository();
        $this->repository->names = [4 => 'graph'];
        parent::setUp();
    }

    public function testAnExistingIdRaisesNoViolation(): void
    {
        $this->validator->validate(4, new ExistingGraphTemplate());

        $this->assertNoViolation();
    }

    /**
     * Never scoped by ACL.
     */
    public function testAnUnknownIdRaisesAViolation(): void
    {
        $constraint = new ExistingGraphTemplate();
        $this->validator->validate(999, $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testNoIdRaisesNoViolation(): void
    {
        $this->validator->validate(null, new ExistingGraphTemplate());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new ExistingGraphTemplateValidator($this->repository);
    }
}
