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

use App\MonitoringConfiguration\Infrastructure\Validator\ExistingServiceTemplate;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingServiceTemplateValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceTemplateRepository;

/**
 * @extends ConstraintValidatorTestCase<ExistingServiceTemplateValidator>
 */
final class ExistingServiceTemplateValidatorTest extends ConstraintValidatorTestCase
{
    private FakeServiceTemplateRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new FakeServiceTemplateRepository();
        $this->repository->names = [4 => 'generic'];
        parent::setUp();
    }

    public function testAnExistingIdRaisesNoViolation(): void
    {
        $this->validator->validate(4, new ExistingServiceTemplate());

        $this->assertNoViolation();
    }

    /**
     * Never scoped by ACL; an id that is not a service template (a service, a dangling id) is unknown.
     */
    public function testAnUnknownIdRaisesAViolation(): void
    {
        $constraint = new ExistingServiceTemplate();
        $this->validator->validate(999, $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testNoIdRaisesNoViolation(): void
    {
        $this->validator->validate(null, new ExistingServiceTemplate());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new ExistingServiceTemplateValidator($this->repository);
    }
}
