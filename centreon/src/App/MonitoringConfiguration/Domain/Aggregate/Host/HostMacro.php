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

namespace App\MonitoringConfiguration\Domain\Aggregate\Host;

use App\Shared\Domain\Logging\Attribute\Sensitive;
use Webmozart\Assert\Assert;

/**
 * A host custom macro (`$_HOST<NAME>$`), either direct (owned by the host or template it is read
 * from, `parent` null) or inherited from a template or the check command (`parent` set, read-only).
 *
 * Values are always held in their raw stored form: the literal value, or the `secret::` reference
 * of a vaulted password. Every comparison is made on that raw form, never on vault-resolved
 * plaintext (rule R10), so a promotion never re-wraps an already-vaulted reference.
 */
final readonly class HostMacro
{
    public const MAX_VALUE_LENGTH = 4096;

    /**
     * @param bool $isPassword when true the value is a secret, eligible for the vault and never
     *                         echoed back in a response
     * @param ?HostMacroId $id null until persisted; its table is given by $parent
     * @param ?HostMacroParentEnum $parent null for a direct macro, otherwise where it is inherited from
     */
    public function __construct(
        public HostMacroName $name,
        // Masked in logs: a password macro's value is a secret (the flag is per-macro, but the
        // attribute is static, so every macro value is masked — safe, non-secret ones included).
        #[Sensitive] public string $value,
        public bool $isPassword,
        public ?HostMacroId $id = null,
        public ?HostMacroParentEnum $parent = null,
    ) {
        Assert::maxLength($value, self::MAX_VALUE_LENGTH);
    }

    public function isDirect(): bool
    {
        return ! $this->parent instanceof HostMacroParentEnum;
    }

    public function isInherited(): bool
    {
        return $this->parent instanceof HostMacroParentEnum;
    }

    public function hasSameNameAs(self $other): bool
    {
        return $this->name->value === $other->name->value;
    }

    /**
     * Whether both macros define the same thing: same name, same raw value, same password flag
     * (R7, R10). Identity (id) and origin (parent) are not compared: an override equivalent to the
     * macro it shadows is redundant whatever row it lives in.
     */
    public function isEquivalentTo(self $other): bool
    {
        return $this->hasSameNameAs($other)
            && $this->value === $other->value
            && $this->isPassword === $other->isPassword;
    }

    /**
     * Whether this macro genuinely overrides $inherited: it shadows it by name while differing in
     * value and/or password flag (R6, R7). A matching name alone never makes an override.
     */
    public function overrides(self $inherited): bool
    {
        return $this->hasSameNameAs($inherited) && ! $this->isEquivalentTo($inherited);
    }

    public function isIdentifiedBy(?HostMacroParentEnum $parent, HostMacroId $id): bool
    {
        return $this->parent === $parent && $this->id?->value === $id->value;
    }

    /**
     * The same macro seen from a host inheriting it: it keeps its id, which together with $parent
     * lets a client address it on write.
     */
    public function inheritedFrom(HostMacroParentEnum $parent): self
    {
        return new self($this->name, $this->value, $this->isPassword, $this->id, $parent);
    }

    /**
     * Turns an inherited macro into a new direct macro of the host (R4, R6). It loses the source's
     * id: the host gets its own row.
     */
    public function promoteToDirect(): self
    {
        return new self($this->name, $this->value, $this->isPassword);
    }

    public function rename(HostMacroName $name): self
    {
        return new self($name, $this->value, $this->isPassword, $this->id, $this->parent);
    }

    public function withValue(string $value, bool $isPassword): self
    {
        return new self($this->name, $value, $isPassword, $this->id, $this->parent);
    }
}
