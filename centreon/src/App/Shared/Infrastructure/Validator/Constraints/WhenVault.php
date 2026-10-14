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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Composite;

/**
 * Applies the given constraints only when a vault is (or is not) configured.
 *
 * For a secret whose stored form depends on the vault: what the column holds is the plaintext
 * without a vault, and the `secret::` reference with one, so a length bound on the payload only
 * describes the first case.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class WhenVault extends Composite
{
    /**
     * @param Constraint[] $constraints
     * @param string[]|null $groups
     */
    public function __construct(
        public bool $forVault,
        public array $constraints,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(groups: $groups, payload: $payload);
    }

    /**
     * @return string[]
     */
    public function getTargets(): array
    {
        return [self::CLASS_CONSTRAINT, self::PROPERTY_CONSTRAINT];
    }

    protected function getCompositeOption(): string
    {
        return 'constraints';
    }
}
