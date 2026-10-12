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

namespace Tests\App\Shared\Infrastructure\Validator\Constraints;

use App\Shared\Domain\VaultInterface;
use App\Shared\Infrastructure\Validator\Constraints\WhenVault;
use App\Shared\Infrastructure\Validator\Constraints\WhenVaultValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tests\App\Shared\Double\FakeVault;

/**
 * Builds its own standalone Validator, as WhenPlatformTest does: the real constraint locator is
 * compiled once at container build time and cannot be forced to a different vault state per test.
 */
final class WhenVaultTest extends TestCase
{
    #[DataProvider('vaultStateProvider')]
    public function testItAppliesTheConstraintsOnlyWhenTheVaultStateMatches(
        bool $forVault,
        bool $vaultEnabled,
        int $expectedViolations,
    ): void {
        $violations = $this->buildValidator($vaultEnabled)->validate('too long', new WhenVault(
            forVault: $forVault,
            constraints: [new Length(max: 3)],
        ));

        self::assertCount($expectedViolations, $violations);
    }

    /**
     * @return iterable<string, array{bool, bool, int}>
     */
    public static function vaultStateProvider(): iterable
    {
        yield 'for no vault, none configured' => [false, false, 1];

        yield 'for no vault, one configured' => [false, true, 0];

        yield 'for a vault, one configured' => [true, true, 1];

        yield 'for a vault, none configured' => [true, false, 0];
    }

    /**
     * Symfony runs property validators on null too, and `snmp_community` is null on most payloads.
     * Without the short circuit, every request would resolve VaultEligibilityService through the
     * `#[Lazy]` LegacyContainer and boot a second kernel for nothing.
     */
    public function testANullValueNeverReachesTheVault(): void
    {
        $vault = $this->vault(vaultEnabled: false);

        $violations = $this->validatorFor($vault)->validate(null, new WhenVault(
            forVault: false,
            constraints: [new Length(max: 3)],
        ));

        self::assertCount(0, $violations);
        self::assertSame(0, $vault->isEnabledCalls);
    }

    public function testGroupsAndPayloadAreForwardedToParent(): void
    {
        $payload = ['key' => 'value'];
        $constraint = new WhenVault(
            forVault: true,
            constraints: [new Length(max: 3)],
            groups: ['MyGroup'],
            payload: $payload,
        );

        self::assertContains('MyGroup', $constraint->groups ?? []);
        self::assertSame($payload, $constraint->payload);
    }

    private function buildValidator(bool $vaultEnabled): ValidatorInterface
    {
        return $this->validatorFor($this->vault($vaultEnabled));
    }

    private function vault(bool $vaultEnabled): FakeVault
    {
        $vault = new FakeVault();
        $vault->vaultEnabled = $vaultEnabled;

        return $vault;
    }

    private function validatorFor(VaultInterface $vault): ValidatorInterface
    {
        $defaultFactory = new ConstraintValidatorFactory();

        return Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(
                new class ($vault, $defaultFactory) implements ConstraintValidatorFactoryInterface {
                    public function __construct(
                        private readonly VaultInterface $vault,
                        private readonly ConstraintValidatorFactory $defaultFactory,
                    ) {
                    }

                    public function getInstance(Constraint $constraint): ConstraintValidatorInterface
                    {
                        if ($constraint instanceof WhenVault) {
                            return new WhenVaultValidator($this->vault);
                        }

                        return $this->defaultFactory->getInstance($constraint);
                    }
                },
            )
            ->getValidator();
    }
}
