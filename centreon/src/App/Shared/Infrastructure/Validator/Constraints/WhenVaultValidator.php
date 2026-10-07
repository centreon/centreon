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

namespace App\Shared\Infrastructure\Validator\Constraints;

use App\Shared\Domain\VaultInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class WhenVaultValidator extends ConstraintValidator
{
    public function __construct(
        private readonly VaultInterface $vault,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof WhenVault) {
            throw new UnexpectedTypeException($constraint, WhenVault::class);
        }

        // Not redundant with a nested Length, which ignores null anyway: Symfony runs property
        // validators on null too, and reading the vault state boots a second kernel through the
        // `#[Lazy]` LegacyContainer. WhenPlatformValidator needs no such guard, its flag is a
        // plain injected bool. A nested NotNull/NotBlank would have to sit outside WhenVault.
        if ($value === null) {
            return;
        }

        if ($this->vault->isEnabled() === $constraint->forVault) {
            $this->context->getValidator()->inContext($this->context)
                ->validate($value, $constraint->constraints);
        }
    }
}
