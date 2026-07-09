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

use App\MonitoringConfiguration\Domain\Exception\PollerTokenNotFoundException;
use App\MonitoringConfiguration\Domain\Model\PollerToken;
use App\MonitoringConfiguration\Domain\Repository\PollerTokenRepository;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingPollerToken;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingPollerTokenValidator;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ExistingPollerTokenValidator>
 */
final class ExistingPollerTokenValidatorTest extends ConstraintValidatorTestCase
{
    private PollerTokenRepository&MockObject $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PollerTokenRepository::class);
        parent::setUp();
    }

    public function testValidTokenNameRaisesNoViolation(): void
    {
        $this->repository->method('getValidPollerTokenByName')->willReturn(
            new PollerToken(
                name: 'my-token',
                value: 'abcdef',
                creationDate: new \DateTimeImmutable(),
                expirationDate: null,
                isRevoked: false,
            ),
        );

        $this->validator->validate('my-token', new ExistingPollerToken());

        $this->assertNoViolation();
    }

    public function testUnknownTokenNameRaisesViolation(): void
    {
        $this->repository->method('getValidPollerTokenByName')->willThrowException(
            new PollerTokenNotFoundException([], 'No valid poller token found.'),
        );

        $constraint = new ExistingPollerToken();
        $this->validator->validate('unknown-token', $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testEmptyValueIsSkipped(): void
    {
        $this->repository->expects(self::never())->method('getValidPollerTokenByName');

        $this->validator->validate('', new ExistingPollerToken());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new ExistingPollerTokenValidator($this->repository);
    }
}
