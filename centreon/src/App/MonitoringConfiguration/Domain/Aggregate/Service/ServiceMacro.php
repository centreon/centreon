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

namespace App\MonitoringConfiguration\Domain\Aggregate\Service;

use App\Shared\Domain\Logging\Attribute\Sensitive;
use Webmozart\Assert\Assert;

/**
 * A `$_SERVICE<NAME>$` macro, own (`parent` null) or inherited; value as stored (`secret::` ref when vaulted).
 */
final readonly class ServiceMacro
{
    public const MAX_VALUE_LENGTH = 4096;
    public const MAX_DESCRIPTION_LENGTH = 65535;

    /**
     * @param bool $isPassword when true the value is a secret, eligible for the vault and never
     *                         echoed back in a response
     * @param ?ServiceMacroId $id null until persisted; its table is given by $parent
     * @param ?ServiceMacroParentEnum $parent null for an own macro, otherwise where it is inherited from
     */
    public function __construct(
        public ServiceMacroName $name,
        #[Sensitive] public string $value,
        public bool $isPassword,
        public ?string $description = null,
        public ?ServiceMacroId $id = null,
        public ?ServiceMacroParentEnum $parent = null,
    ) {
        Assert::maxLength($value, self::MAX_VALUE_LENGTH);
        if ($description !== null) {
            // Byte length ('8bit'), not character count: the storage column is bounded to
            // MAX_DESCRIPTION_LENGTH bytes, so a multibyte description must be measured in the
            // bytes it will actually occupy.
            Assert::lessThanEq(
                mb_strlen($description, '8bit'),
                self::MAX_DESCRIPTION_LENGTH,
                'The macro description is too long.',
            );
        }
    }

    public function isInherited(): bool
    {
        return $this->parent instanceof ServiceMacroParentEnum;
    }

    /**
     * The same macro tagged as inherited from $parent; its id is kept.
     */
    public function inheritedFrom(ServiceMacroParentEnum $parent): self
    {
        return new self($this->name, $this->value, $this->isPassword, $this->description, $this->id, $parent);
    }

    public function hasSameNameAs(self $other): bool
    {
        return $this->name->value === $other->name->value;
    }

    /**
     * Whether both macros define the same thing: same name, same raw value, same password flag. Identity (id),
     * origin (parent) and description are not compared: an own macro equivalent to the macro it shadows is
     * redundant whatever row it lives in.
     */
    public function isEquivalentTo(self $other): bool
    {
        return $this->hasSameNameAs($other)
            && $this->value === $other->value
            && $this->isPassword === $other->isPassword;
    }

    public function isIdentifiedBy(?ServiceMacroParentEnum $parent, ServiceMacroId $id): bool
    {
        return $this->parent === $parent && $this->id?->value === $id->value;
    }

    public function rename(ServiceMacroName $name): self
    {
        return new self($name, $this->value, $this->isPassword, $this->description, $this->id, $this->parent);
    }

    public function withValue(string $value, bool $isPassword): self
    {
        return new self($this->name, $value, $isPassword, $this->description, $this->id, $this->parent);
    }
}
