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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Infrastructure\Validator\UniqueHostName;
use App\MonitoringConfiguration\Infrastructure\Validator\UniqueHostNameValidator;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<UniqueHostNameValidator>
 */
final class UniqueHostNameValidatorTest extends ConstraintValidatorTestCase
{
    private HostRepository&MockObject $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(HostRepository::class);
        parent::setUp();
    }

    public function testAnUnusedNameRaisesNoViolation(): void
    {
        $this->repository->expects(self::once())
            ->method('isNameUsedByHostOrTemplate')
            ->with(new HostName('my-host'))
            ->willReturn(false);

        $this->validator->validate('my-host', new UniqueHostName());

        $this->assertNoViolation();
    }

    public function testAnAlreadyUsedNameRaisesAViolation(): void
    {
        $this->repository->method('isNameUsedByHostOrTemplate')->willReturn(true);

        $constraint = new UniqueHostName();
        $this->validator->validate('my-host', $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testANameTheHostNameValueObjectWouldRejectRaisesNoViolation(): void
    {
        $this->repository->expects(self::never())->method('isNameUsedByHostOrTemplate');

        $this->validator->validate(str_repeat('a', HostName::MAX_LENGTH + 1), new UniqueHostName());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new UniqueHostNameValidator($this->repository);
    }
}
