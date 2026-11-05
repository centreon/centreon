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

use App\MonitoringConfiguration\Infrastructure\Validator\ExistingServiceTemplates;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingServiceTemplatesValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceTemplateRepository;

/**
 * @extends ConstraintValidatorTestCase<ExistingServiceTemplatesValidator>
 */
final class ExistingServiceTemplatesValidatorTest extends ConstraintValidatorTestCase
{
    private FakeServiceTemplateRepository $serviceTemplateRepository;

    protected function setUp(): void
    {
        $this->serviceTemplateRepository = new FakeServiceTemplateRepository();
        $this->serviceTemplateRepository->names = [5 => 'generic-service'];
        parent::setUp();
    }

    public function testAnEmptyListRaisesNoViolation(): void
    {
        $this->validator->validate([], new ExistingServiceTemplates());

        $this->assertNoViolation();
        self::assertSame([], $this->serviceTemplateRepository->receivedIds);
    }

    public function testExistingServiceTemplatesRaiseNoViolation(): void
    {
        $this->validator->validate([5], new ExistingServiceTemplates());

        $this->assertNoViolation();
    }

    public function testAnUnknownServiceTemplateRaisesAViolation(): void
    {
        $constraint = new ExistingServiceTemplates();
        $this->validator->validate([5, 404], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new ExistingServiceTemplatesValidator($this->serviceTemplateRepository);
    }
}
