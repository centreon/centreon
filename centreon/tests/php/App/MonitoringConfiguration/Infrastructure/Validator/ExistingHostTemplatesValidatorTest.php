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

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingHostTemplates;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingHostTemplatesValidator;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ExistingHostTemplatesValidator>
 */
final class ExistingHostTemplatesValidatorTest extends ConstraintValidatorTestCase
{
    private HostTemplateRepository&MockObject $hostTemplateRepository;

    protected function setUp(): void
    {
        $this->hostTemplateRepository = $this->createMock(HostTemplateRepository::class);
        parent::setUp();
    }

    public function testAnEmptyListRaisesNoViolation(): void
    {
        $this->hostTemplateRepository->expects(self::never())->method('findNamesByIds');

        $this->validator->validate([], new ExistingHostTemplates());

        $this->assertNoViolation();
    }

    public function testExistingTemplatesRaiseNoViolation(): void
    {
        $this->hostTemplateRepository->method('findNamesByIds')->willReturn(new Collection([5 => new HostTemplateName('generic-host')], HostTemplateName::class));

        $this->validator->validate([5], new ExistingHostTemplates());

        $this->assertNoViolation();
    }

    public function testAnUnknownTemplateRaisesAViolation(): void
    {
        $this->hostTemplateRepository->method('findNamesByIds')->willReturn(new Collection([5 => new HostTemplateName('generic-host')], HostTemplateName::class));

        $constraint = new ExistingHostTemplates();
        $this->validator->validate([5, 404], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new ExistingHostTemplatesValidator($this->hostTemplateRepository);
    }
}
